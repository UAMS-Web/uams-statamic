<?php

declare(strict_types=1);

namespace App\Services\PubMed;

use App\Jobs\WarmPubMedSummaries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Fetches and caches PubMed E-summary rows for a PMID, builds HTML citations for
 * computed fields, and supports PMID existence checks for validation.
 *
 * Cached values are either the decoded summary row array (success) or false
 * (HTTP failure or missing record) so callers can distinguish outcomes without
 * colliding with legacy cache entries that mixed unrelated payloads.
 */
class PubMedEsummaryService
{
    /**
     * Cache key namespace prefix for decoded E-summary rows (versioned to avoid
     * collisions).
     */
    private const string CACHE_KEY_PREFIX = 'pubmed_esummary_row_v1:';

    /**
     * Cache key for the application-wide E-utilities rate limiter.
     *
     * Deliberately a single fixed key rather than one per instance or per PMID:
     * NCBI's ceiling applies to the caller, not to a PHP object, so every
     * process sharing this cache store must contend for the same counter. That
     * is the whole difference between this and the per-call sleep it replaced.
     */
    private const string RATE_LIMITER_KEY = 'pubmed_api_requests';

    /** Window the ceiling is expressed over. NCBI documents it per second. */
    private const int RATE_LIMIT_WINDOW_SECONDS = 1;

    /** Requests per second NCBI permits an unauthenticated client. */
    private const int RATE_LIMIT_WITHOUT_API_KEY = 3;

    /** Requests per second NCBI permits a client sending an `api_key`. */
    private const int RATE_LIMIT_WITH_API_KEY = 10;

    /**
     * How many times to wait for a limiter slot before giving up.
     *
     * A bound rather than an unbounded wait so a wedged counter cannot hang a
     * queue worker forever; generous enough that ordinary contention between
     * two warming workers and Control-Panel validation clears well inside it.
     */
    private const int RATE_LIMIT_MAX_WAITS = 30;

    /**
     * Enrich each `research_publication_select` set with `pubmed_information` HTML.
     *
     * Request-time only: reads the E-summary cache and NEVER fetches over HTTP or
     * sleeps (UAMS-Web/uams-statamic-archive#865). Computed fields run during CP listings, API augmentation, and
     * search indexing, so a cold cache must degrade to an empty citation rather
     * than block a render on serialized PubMed calls. The cache is warmed out of
     * band by {@see WarmPubMedSummaries} (via the `pubmed:warm` command).
     *
     * @param  array<int|string, mixed>  $selectPublications  Replicator sets keyed by index or ID
     * @return array<int|string, mixed> Sets with `pubmed_information` added for array rows with a PMID
     */
    public function enrichResearchPublicationSelectSets(
        array $selectPublications
    ): array {
        // Return early if the select publications are empty
        if ($selectPublications === []) {
            return $selectPublications;
        }

        // Map the select publications to add the pubmed information (cache-only)
        return \array_map(
            function (mixed $set): mixed {
                // Return early if the set is not an array
                if (! \is_array($set)) {
                    return $set;
                }

                // Get the PMID from the set
                $pmid = $set['pmid'] ?? null;

                // Return early if the PMID is not set
                if (! $pmid) {
                    $set['pubmed_information'] = '';

                    return $set;
                }

                // Convert the PMID to a string
                $pmid = \is_scalar($pmid) ? (string) $pmid : '';

                // Read the summary row from the cache WITHOUT fetching on miss
                $row = $this->cachedSummaryRow($pmid);

                // Set the pubmed information (empty when not yet warmed)
                $set['pubmed_information'] = \is_array($row)
                    ? $this->citationHtmlFromSummaryRow(
                        $row,
                        $pmid
                    )
                    : '';

                // Return the set with the pubmed information
                return $set;
            },
            $selectPublications
        );
    }

    /**
     * Return a cached E-summary row for a PMID WITHOUT fetching on a miss.
     *
     * The synchronous augmentation path uses this so it never performs blocking
     * PubMed HTTP or pacing sleeps (UAMS-Web/uams-statamic-archive#865); the cache is warmed out of band. Use
     * {@see rememberSummaryRow()} (fetch-on-miss) only from the warming job/command
     * and from explicit validation ({@see pmidExists()}).
     *
     * @param  string  $pmid  PubMed identifier string
     * @return array<string, mixed>|false|null Cached row, false for a cached miss, null when absent
     */
    public function cachedSummaryRow(string $pmid): array|false|null
    {
        /** @var array<string, mixed>|false|null $row */
        $row = Cache::get($this->summaryCacheKey($pmid));

        return $row;
    }

    /**
     * Whether the PMID resolves to a PubMed E-summary record.
     *
     * @param  string  $pmid  PubMed identifier string (digits only)
     * @return bool True when a cached or fetched summary row exists for the PMID
     */
    public function pmidExists(
        string $pmid
    ): bool {
        // Get the summary row from the cache
        $row = $this->rememberSummaryRow($pmid);

        // Return true if the summary row exists
        return $row !== false;
    }

    /**
     * Return a cached summary row for a PMID, fetching once on miss.
     *
     * Pacing is no longer a per-call concern and the caller no longer chooses
     * it: every uncached fetch contends for the application-wide limiter in
     * {@see awaitRateLimitSlot()}. The `$sleepMicrosecondsBeforeUncachedNetworkFetch`
     * flag this method used to take was removed with it (UAMS-Web/uams-statamic-archive#2409) rather than left
     * inert, because the warming job passed `true` to mean "pace me" and would
     * otherwise have gone on saying so to a no-op.
     *
     * @param  string  $pmid  PubMed identifier string
     * @return array<string, mixed>|false Summary row on success, false when missing or on HTTP failure
     */
    public function rememberSummaryRow(
        string $pmid
    ): array|false {
        // Remember the summary row
        return Cache::remember(
            $this->summaryCacheKey($pmid),
            $this->cacheTtlSeconds(),
            // Fetch the summary row from the network
            fn (): array|false => $this->fetchSummaryRowFromNetwork($pmid)
        );
    }

    /**
     * Build an HTML citation string from a decoded PubMed E-summary row.
     *
     * @param  array<string, mixed>  $pubmedData  Decoded `result.{pmid}` payload from E-summary JSON
     * @param  string  $pmid  PubMed identifier echoed in the citation and link
     * @return string HTML fragment with authors, journal metadata, PMID, and PubMed link
     */
    public function citationHtmlFromSummaryRow(
        array $pubmedData,
        string $pmid
    ): string {
        // Get the authors from the pubmed data
        $authorsValue = $pubmedData['authors'] ?? [];
        /** @var iterable<int|string, mixed> $authorsValue */
        $authorsValue = \is_iterable($authorsValue) ? $authorsValue : [];

        $authors = collect($authorsValue)
            ->pluck('name')
            ->implode(', ').'.';

        // Get the title from the pubmed data
        $titleValue = $pubmedData['title'] ?? '';
        $title = \is_scalar($titleValue) ? (string) $titleValue : '';

        // Get the source from the pubmed data
        $sourceValue = $pubmedData['source'] ?? '';
        $source = \is_scalar($sourceValue) ? (string) $sourceValue : '';

        // Get the publication date from the pubmed data
        $pubdateValue = $pubmedData['pubdate'] ?? '';
        $pubdate = \is_scalar($pubdateValue) ? (string) $pubdateValue : '';

        // Get the volume from the pubmed data
        $volumeValue = $pubmedData['volume'] ?? '';
        $volume = \is_scalar($volumeValue) ? (string) $volumeValue : '';

        // Get the issue from the pubmed data
        $issueValue = $pubmedData['issue'] ?? '';
        $issue = \is_scalar($issueValue) ? (string) $issueValue : '';

        // Get the pages from the pubmed data
        $pagesValue = $pubmedData['pages'] ?? '';
        $pages = \is_scalar($pagesValue) ? (string) $pagesValue : '';

        // Get the elocationid from the pubmed data
        $elocationidValue = $pubmedData['elocationid'] ?? '';
        $elocationid = \is_scalar($elocationidValue) ? (string) $elocationidValue : '';

        // Build the citation
        $citation = sprintf('%s %s %s. %s', $authors, $title, $source, $pubdate);

        // Add the volume if it is set
        if ($volume || $issue || $pages) {
            $citation .= '; '.$volume;

            // Add the issue if it is set
            if ($issue !== '' && $issue !== '0') {
                $citation .= sprintf('(%s)', $issue);
            }

            // Add the pages if it is set
            if ($pages !== '' && $pages !== '0') {
                $citation .= ':'.$pages;
            }
        }

        // Add the elocationid, PMID, and view in PubMed link
        $citation .= sprintf('. %s PMID: %s.<br/>View in PubMed: <a href="https://www.ncbi.nlm.nih.gov/pubmed/%s" target="_blank">%s</a>', $elocationid, $pmid, $pmid, $pmid);

        // Return the citation
        return $citation;
    }

    /**
     * Build the cache key for a PMID E-summary row.
     *
     * @param  string  $pmid  PubMed identifier string
     * @return string Namespaced cache key
     */
    private function summaryCacheKey(
        string $pmid
    ): string {
        // Return the cache key
        return self::CACHE_KEY_PREFIX.$pmid;
    }

    /**
     * TTL in seconds for positive and negative E-summary cache entries.
     *
     * @return int Seconds from `config('pubmed.cache_ttl_seconds')`
     */
    private function cacheTtlSeconds(): int
    {
        // Return the cache TTL seconds
        $cacheTtlSeconds = config(
            'pubmed.cache_ttl_seconds',
            604_800
        );

        return (int) (\is_scalar($cacheTtlSeconds) ? $cacheTtlSeconds : 604_800);
    }

    /**
     * Seconds to wait on an uncached E-summary HTTP call before giving up.
     *
     * @return int Seconds from `config('pubmed.request_timeout_seconds')`
     */
    private function requestTimeoutSeconds(): int
    {
        $timeout = config(
            'pubmed.request_timeout_seconds',
            10
        );

        return (int) (\is_scalar($timeout) ? $timeout : 10);
    }

    /**
     * The configured E-utilities API key, or null when none is set.
     *
     * Null rather than an empty string so callers can omit the parameter
     * entirely; NCBI treats an empty `api_key` as a malformed one.
     *
     * @return string|null Trimmed key from `config('pubmed.api_key')`
     */
    private function apiKey(): ?string
    {
        $apiKey = config('pubmed.api_key');

        if (! \is_scalar($apiKey)) {
            return null;
        }

        $apiKey = \trim((string) $apiKey);

        return $apiKey === '' ? null : $apiKey;
    }

    /**
     * Requests per second NCBI permits this client.
     *
     * Public because it is the observable half of the `api_key` contract: the
     * key's effect is a raised ceiling, and a caller (or a test) has no other
     * way to see that it took effect short of provoking a block.
     *
     * @return int Ten with an API key configured, three without
     */
    public function maxRequestsPerSecond(): int
    {
        return $this->apiKey() === null
            ? self::RATE_LIMIT_WITHOUT_API_KEY
            : self::RATE_LIMIT_WITH_API_KEY;
    }

    /**
     * Block until the shared limiter has a slot, then consume it.
     *
     * `RateLimiter::attempt()` is deliberately not used: it does not wait, it
     * runs the callback or returns false. Used here that would DROP a request
     * rather than pace it, and a dropped request in the warming job is a
     * silently missing citation rather than a visible failure.
     *
     * @throws RuntimeException When no slot frees up within the bounded wait
     */
    private function awaitRateLimitSlot(): void
    {
        $maxRequestsPerSecond = $this->maxRequestsPerSecond();

        for ($wait = 0; $wait < self::RATE_LIMIT_MAX_WAITS; $wait++) {
            // Take the slot as soon as the window has room
            if (! RateLimiter::tooManyAttempts(self::RATE_LIMITER_KEY, $maxRequestsPerSecond)) {
                RateLimiter::hit(self::RATE_LIMITER_KEY, self::RATE_LIMIT_WINDOW_SECONDS);

                return;
            }

            // Wait exactly as long as the limiter says the window has left,
            // rather than polling at an interval unrelated to it
            Sleep::sleep(
                \max(
                    self::RATE_LIMIT_WINDOW_SECONDS,
                    RateLimiter::availableIn(self::RATE_LIMITER_KEY)
                )
            );
        }

        throw new RuntimeException(
            'PubMed E-utilities rate limit did not clear after '
            .self::RATE_LIMIT_MAX_WAITS.' waits at '.$maxRequestsPerSecond
            .' requests per second. No request was sent.'
        );
    }

    /**
     * Identification parameters NCBI expects on every E-utilities request.
     *
     * @return array<string, string> `tool`, `email`, and `api_key` when set
     *
     * @throws RuntimeException When no contact email is configured
     */
    private function identificationParameters(): array
    {
        $toolValue = config('pubmed.tool', 'uams-statamic');
        $tool = \is_scalar($toolValue) ? \trim((string) $toolValue) : '';

        $emailValue = config('pubmed.email');
        $email = \is_scalar($emailValue) ? \trim((string) $emailValue) : '';

        // Refuse rather than query anonymously, which is the behavior UAMS-Web/uams-statamic-archive#2409
        // exists to remove. Naming both the config key and the environment
        // variable, because the reader fixing this edits the latter.
        if ($email === '') {
            throw new RuntimeException(
                'No PubMed contact email is configured, so an E-utilities request '
                .'was refused rather than sent anonymously. Set `pubmed.email` '
                .'(environment variable `PUBMED_CONTACT_EMAIL`) to an address NCBI '
                .'can use to make contact before throttling or blocking this client.'
            );
        }

        $parameters = [
            'tool' => $tool === '' ? 'uams-statamic' : $tool,
            'email' => $email,
        ];

        // Omit `api_key` entirely when unconfigured rather than sending it empty
        $apiKey = $this->apiKey();

        if ($apiKey !== null) {
            $parameters['api_key'] = $apiKey;
        }

        return $parameters;
    }

    /**
     * Fetch one PMID summary row from NCBI E-utilities (uncached HTTP).
     *
     * @param  string  $pmid  PubMed identifier string
     * @return array<string, mixed>|false Decoded row array, or false on failure or missing record
     */
    private function fetchSummaryRowFromNetwork(
        string $pmid
    ): array|false {
        // Resolve identification BEFORE taking a limiter slot, so a
        // misconfigured client refuses without first consuming a slot that a
        // correctly configured worker could have used
        $parameters = [
            'db' => 'pubmed',
            'retmode' => 'json',
            'id' => $pmid,
        ] + $this->identificationParameters();

        // Wait for the shared limiter rather than pacing this call alone
        $this->awaitRateLimitSlot();

        // Fetch the response from the network (bounded so a hung connection
        // cannot block the out-of-band warming job/command indefinitely).
        // Passed as a query array rather than a concatenated URL so a future
        // edit cannot drop the identification parameters from one call site.
        $response = Http::timeout($this->requestTimeoutSeconds())->get(
            'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi',
            $parameters
        );

        // Return false if the response failed
        if ($response->failed()) {
            Log::error(
                'PubMedEsummaryService: Error fetching PubMed data for PMID: '.$pmid,
                [
                    'status' => $response->status(),
                    'reason' => $response->reason(),
                ]
            );

            return false;
        }

        // Get the data from the response
        $data = $response->json();

        // Return false if the decoded response is not an array
        if (! \is_array($data)) {
            return false;
        }

        // Get the pubmed data from the result
        $result = $data['result'] ?? null;
        $pubmedData = \is_array($result) ? ($result[$pmid] ?? null) : null;

        // Return false if the pubmed data is not set or is not an array
        if (
            ! $pubmedData
            || ! \is_array($pubmedData)
        ) {
            return false;
        }

        // Return the pubmed data
        /** @var array<string, mixed> $pubmedData */
        return $pubmedData;
    }
}
