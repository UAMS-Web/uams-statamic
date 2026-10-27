<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use App\Services\SchemaTemplates\SchemaJsonLdWarmUrlCollector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use Throwable;

use function Laravel\Prompts\spin;

/**
 * Console orchestrator that warms Statamic JSON-LD output cache by GETting live URLs.
 *
 * Operators run it via Artisan; the Control Panel warm action triggers the same command and
 * reads the persisted summary from `SchemaJsonLdOutputCache`. URL discovery is delegated to
 * `SchemaJsonLdWarmUrlCollector` using `config/schema_templates.php` → `json_ld_cache.warm`.
 */
#[Description('Warm JSON-LD output cache by requesting homepage and multisite-derived URLs')]
#[Signature('schema-templates:warm-json-ld-cache
                            {--site= : Limit to one site handle}')]
class SchemaTemplatesWarmJsonLdCache extends Command
{
    /**
     * Message shown while the Laravel Prompts spinner collects URLs across Statamic sites.
     */
    private const string SPIN_COLLECTING_MESSAGE = 'Collecting JSON-LD warm URLs...';

    /**
     * Symfony progress bar format including numeric progress, bar, percent, and message slot.
     */
    private const string PROGRESS_BAR_FORMAT = ' %current%/%max% [%bar%] %percent:3s%% %message%';

    /**
     * Label displayed in the progress bar message segment during HTTP warming.
     */
    private const string PROGRESS_BAR_MESSAGE = 'Requesting warm URLs';

    /**
     * Width used to blank a progress bar line when ANSI erase is unavailable.
     */
    private const int ERASE_LINE_FILL_WIDTH = 120;

    /**
     * User-Agent header sent with warmer HTTP requests for server logs.
     */
    private const string HTTP_USER_AGENT = 'UAMS-SchemaJsonLdWarmer/1.0';

    /**
     * Execute the warmer, persist CP-visible summary metadata, and optionally print completion copy.
     *
     * @return int Always `Command::SUCCESS`; misconfiguration yields an empty summary with warnings.
     */
    public function handle(): int
    {
        // Run multisite collection and HTTP warming according to CLI environment
        $summary = $this->runWarm();

        // Persist last-run stats for the JSON-LD cache utility in the Statamic CP
        app(SchemaJsonLdOutputCache::class)->storeLastWarmSummary(
            $summary
        );

        // Surface a whimsical completion line when not running quietly (e.g. `-q`)
        if (! $this->output->isQuiet()) {
            $this->components->info(
                $this->randomJsonLdWarmCompletionMessage()
            );
        }

        return self::SUCCESS;
    }

    /**
     * Whether to show Laravel Prompts spinner and Symfony progress bar on this invocation.
     *
     * Interactive TTY sessions get animated feedback; quiet or non-TTY runs (including
     * `Artisan::call` from HTTP) skip animation to avoid noisy or broken output.
     */
    private function shouldAnimateWarmProgress(): bool
    {
        // Quiet mode suppresses all progress UI
        if ($this->output->isQuiet()) {
            return false;
        }

        // STDOUT must exist and support TTY detection for a spinner or bar
        if (
            ! defined('STDOUT')
            || ! function_exists('stream_isatty')
        ) {
            return false;
        }

        // Treat PHP stream errors as non-interactive so output stays usable
        return @stream_isatty(STDOUT);
    }

    /**
     * Resolve scope, collect merged warm URLs, GET each unique URL, and assemble summary data.
     *
     * @return array<string, mixed> Payload stored for the CP and mirrored in warm metadata.
     */
    private function runWarm(): array
    {
        // Honor optional `--site` limit when building the Statamic site list
        $siteFilter = $this->resolveSiteFilter();

        // Enumerate sites to scan for homepage and globals-driven URLs
        $sites = $this->sitesToProcess($siteFilter);

        // Bail out early when no site handles match (invalid `--site` or empty install)
        if ($sites === []) {
            $this->warn('No Statamic sites matched the warm run (check --site handle).');

            return $this->emptyMultisiteSummary($siteFilter);
        }

        // Decide whether this environment should animate Prompts and the progress bar
        $animate = $this->shouldAnimateWarmProgress();

        // Collect per-site URL packets and merge into one deduped list (with optional spinner)
        $collected = $animate
            ? spin(
                callback: fn (): array => $this->collectMergedWarmUrlsForSites($sites),
                message: self::SPIN_COLLECTING_MESSAGE,
            )
            : $this->collectMergedWarmUrlsForSites($sites);

        // Issue GET requests for each merged URL, optionally advancing a progress bar
        $httpStats = $animate
            ? $this->requestWarmUrlsWithProgressBar($collected['merged_urls'])
            : $this->requestWarmUrlsPlain($collected['merged_urls']);

        // Normalize merged collector output and HTTP counters into the stored summary shape
        return $this->buildMultisiteSummary(
            $siteFilter,
            $collected,
            $httpStats
        );
    }

    /**
     * Collect URLs and audit ids from each site and merge them into one deduped URL list.
     *
     * @param  list<StatamicSite>  $sites  Statamic sites selected for this warm run.
     * @return array{
     *     merged_urls: list<string>,
     *     duplicate_urls_skipped: int,
     *     per_site_url_counts: array<string, int>,
     *     globals_site_handles_used: array<string, string|null>,
     *     slot_schema_object_ids: list<string>,
     *     people_entry_ids: list<string>,
     *     warnings: list<string>,
     *     sites: list<string>,
     * }
     */
    private function collectMergedWarmUrlsForSites(
        array $sites
    ): array {
        // Fresh collector instance per warm so internal caches do not leak across calls
        $collector = new SchemaJsonLdWarmUrlCollector;
        $perSiteUrlCounts = [];
        $perSiteGlobalsRow = [];
        $mergedUrls = [];
        $urlSeen = [];
        $duplicateUrlsSkipped = 0;
        $schemaIdsOrdered = [];
        $schemaSeen = [];
        $peopleIdsOrdered = [];
        $peopleSeen = [];
        $warnings = [];
        $siteHandles = [];

        // Walk each site and merge URLs, schema ids, people ids, and warnings
        foreach ($sites as $site) {
            /** @var string $siteHandle */
            $siteHandle = $site->handle();
            $siteHandles[] = $siteHandle;

            // Resolve homepage plus globals/taxonomy URLs for this site’s localization
            $packet = $collector->collectForSite($site);
            $perSiteUrlCounts[$siteHandle] = \count($packet['urls']);
            $perSiteGlobalsRow[$siteHandle] = $packet['globals_site_handle_used'];

            // Append unique URLs and count cross-site duplicates skipped during merge
            foreach ($packet['urls'] as $url) {
                if (isset($urlSeen[$url])) {
                    $duplicateUrlsSkipped++;

                    continue;
                }

                $urlSeen[$url] = true;
                $mergedUrls[] = $url;
            }

            // Track distinct schema object entry ids seen across sites (ordered first-seen)
            foreach ($packet['schema_object_entry_ids'] as $sid) {
                if (! isset($schemaSeen[$sid])) {
                    $schemaSeen[$sid] = true;
                    $schemaIdsOrdered[] = $sid;
                }
            }

            // Track distinct people entry ids seen across sites (ordered first-seen)
            foreach ($packet['people_entry_ids'] as $pid) {
                if (! isset($peopleSeen[$pid])) {
                    $peopleSeen[$pid] = true;
                    $peopleIdsOrdered[] = $pid;
                }
            }

            // Prefix collector warnings with the site handle for downstream display
            foreach ($packet['warnings'] as $warning) {
                $warnings[] = sprintf('[%s] %s', $siteHandle, $warning);
            }
        }

        return [
            'merged_urls' => $mergedUrls,
            'duplicate_urls_skipped' => $duplicateUrlsSkipped,
            'per_site_url_counts' => $perSiteUrlCounts,
            'globals_site_handles_used' => $perSiteGlobalsRow,
            'slot_schema_object_ids' => $schemaIdsOrdered,
            'people_entry_ids' => $peopleIdsOrdered,
            'warnings' => $warnings,
            'sites' => $siteHandles,
        ];
    }

    /**
     * GET each merged URL once without progress UI (quiet, CI, or non-TTY runs).
     *
     * @param  list<string>  $mergedUrls  Absolute URLs to request for cache warming.
     * @return array{requested: int, failures: int} Counts of attempts and non-success outcomes.
     */
    private function requestWarmUrlsPlain(
        array $mergedUrls
    ): array {
        $http = $this->httpClient();
        $failures = 0;
        $requested = 0;

        // Hit each URL sequentially with the shared HTTP client configuration
        foreach ($mergedUrls as $url) {
            $requested++;

            if ($this->warmRequestFailed($http, $url)) {
                $failures++;
            }
        }

        return [
            'requested' => $requested,
            'failures' => $failures,
        ];
    }

    /**
     * GET each merged URL while advancing a Symfony progress bar on decorated output.
     *
     * @param  list<string>  $mergedUrls  Absolute URLs to request for cache warming.
     * @return array{requested: int, failures: int} Counts of attempts and non-success outcomes.
     */
    private function requestWarmUrlsWithProgressBar(
        array $mergedUrls
    ): array {
        $max = \count($mergedUrls);

        // Nothing to draw when the merged list is empty
        if ($max === 0) {
            return [
                'requested' => 0,
                'failures' => 0,
            ];
        }

        $http = $this->httpClient();
        $failures = 0;
        $requested = 0;

        // Show conventional numeric, bar, percent, and message columns during HTTP warming
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat(self::PROGRESS_BAR_FORMAT);
        $bar->setMessage(self::PROGRESS_BAR_MESSAGE);
        $bar->start();

        foreach ($mergedUrls as $url) {
            $requested++;

            if ($this->warmRequestFailed($http, $url)) {
                $failures++;
            }

            $bar->advance();
        }

        $bar->finish();

        // Clear the finished bar row so the following INFO line does not stack under it
        $this->eraseProgressBarLine();

        return [
            'requested' => $requested,
            'failures' => $failures,
        ];
    }

    /**
     * Return true when a warm GET should count toward failure totals.
     *
     * @param  PendingRequest  $http  HTTP client with warmer timeouts and headers.
     * @param  string  $url  Absolute front URL to GET.
     * @return bool True for transport errors or HTTP responses Laravel treats as unsuccessful.
     */
    private function warmRequestFailed(
        PendingRequest $http,
        string $url
    ): bool {
        try {
            return ! $http->get($url)->successful();
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Remove the completed progress bar row so subsequent console output starts clean.
     */
    private function eraseProgressBarLine(): void
    {
        // Prefer ANSI erase-to-end-of-line when the output supports decoration
        if ($this->output->isDecorated()) {
            $this->output->write("\x1b[2K\r");

            return;
        }

        // Fall back to overwriting with spaces when ANSI sequences are unavailable
        $this->output->write(
            "\r".str_repeat(
                ' ',
                self::ERASE_LINE_FILL_WIDTH
            )."\r"
        );
    }

    /**
     * Combine collector output and HTTP stats into the summary array stored for the CP.
     *
     * @param  string|null  $siteFilter  `--site` handle when set, or null for all sites.
     * @param  array{
     *     merged_urls: list<string>,
     *     duplicate_urls_skipped: int,
     *     per_site_url_counts: array<string, int>,
     *     globals_site_handles_used: array<string, string|null>,
     *     slot_schema_object_ids: list<string>,
     *     people_entry_ids: list<string>,
     *     warnings: list<string>,
     *     sites: list<string>,
     * }  $collected  Merged URL list and per-site metadata from `collectMergedWarmUrlsForSites`.
     * @param  array{requested: int, failures: int}  $httpStats  Result counts from plain or
     *                                                           progress-bar HTTP passes.
     * @return array<string, mixed> Summary persisted via `SchemaJsonLdOutputCache`.
     */
    private function buildMultisiteSummary(
        ?string $siteFilter,
        array $collected,
        array $httpStats
    ): array {
        return [
            'at' => now()->toIso8601String(),
            'mode' => 'multisite',
            'requested' => $httpStats['requested'],
            'skipped_no_url' => 0,
            'failures' => $httpStats['failures'],
            'duplicate_urls_skipped' => $collected['duplicate_urls_skipped'],
            'urls_merged_count' => \count($collected['merged_urls']),
            'per_site_url_counts' => $collected['per_site_url_counts'],
            'globals_site_handles_used' => $collected['globals_site_handles_used'],
            'slot_schema_object_ids' => $collected['slot_schema_object_ids'],
            'people_entry_ids' => $collected['people_entry_ids'],
            'warnings' => $collected['warnings'],
            'sites' => $collected['sites'],
            'site' => $siteFilter,
            'warm_config_snapshot' => $this->buildWarmConfigSnapshot(),
        ];
    }

    /**
     * Build the multisite summary payload used when no Statamic sites match the filter.
     *
     * @param  string|null  $siteFilter  `--site` value when provided, or null for all sites.
     * @return array<string, mixed> Zeroed counters and a human-readable warning entry.
     */
    private function emptyMultisiteSummary(
        ?string $siteFilter
    ): array {
        return [
            'at' => now()->toIso8601String(),
            'mode' => 'multisite',
            'requested' => 0,
            'skipped_no_url' => 0,
            'failures' => 0,
            'duplicate_urls_skipped' => 0,
            'urls_merged_count' => 0,
            'per_site_url_counts' => [],
            'slot_schema_object_ids' => [],
            'people_entry_ids' => [],
            'warnings' => ['No sites to process.'],
            'sites' => [],
            'site' => $siteFilter,
            'warm_config_snapshot' => $this->buildWarmConfigSnapshot(),
        ];
    }

    /**
     * Choose a random whimsical completion line after a successful warm run.
     *
     * @return string Single-line message for `components->info` output.
     */
    private function randomJsonLdWarmCompletionMessage(): string
    {
        $messages = [
            'The JSON-LD cache has been wrapped in a warm blanket of relevance and tucked in with @context. Sweet dreams, little triples.',
            "You fed the cache a hearty bowl of linked data and gave it a good polish. It's warm, satisfied, and slightly cocky.",
            'Cache warmed. Ego inflated. Your JSON-LD is now ready to flex on unstructured peasants.',
            'The JSON-LD has been spa-treated: exfoliated, massaged, and warmed. It feels prettier than your frontend.',
            "The JSON-LD cache has been meticulously pre-warmed and buffed to perfection. It's now radiating structured confidence.",
            'Schemas gently toasted, cache properly primed. Warm, crisp, and ready to dominate the SERPs.',
            'Semantic polish applied. Buffing complete. The JSON-LD Stache is gleaming, warm, and quietly judging your other caches.',
            "You gave the cache a hearty serving of linked data and a thorough polish. It's warm, satisfied, and mildly arrogant.",
            'You have gently heated the JSON-LD oven and pulled out a perfect batch. Golden brown, fully cached, and ready for action.',
        ];

        // Pick one completion line uniformly from the catalog
        return $messages[
            random_int(
                0,
                \count($messages) - 1
            )
        ];
    }

    /**
     * Snapshot warm-related config keys for CP diagnostics (counts and fallback handles).
     *
     * @return array{
     *     homepage_collection_handle: string,
     *     homepage_uri_candidates: list<string>,
     *     global_fallback_site_handle: string|null,
     *     global_entry_field_reference_total_count: int,
     *     global_taxonomy_traversal_rule_count: int,
     * }
     */
    private function buildWarmConfigSnapshot(): array
    {
        // Load the `json_ld_cache.warm` slice used by `SchemaJsonLdWarmUrlCollector`
        $warm = config(
            'schema_templates.json_ld_cache.warm',
            []
        );

        $warm = \is_array($warm) ? $warm : [];

        $entryTotal = 0;

        // Sum dotted global entry references configured per entry collection
        if (
            isset($warm['global_entry_fields_by_collection'])
            && \is_array($warm['global_entry_fields_by_collection'])
        ) {
            foreach ($warm['global_entry_fields_by_collection'] as $refs) {
                if (\is_array($refs)) {
                    $entryTotal += \count($this->sanitizeStringList($refs));
                }
            }
        }

        $taxonomyRuleCount = 0;

        // Count taxonomy traversal rules when the array is present
        if (
            isset($warm['global_taxonomy_entry_traversals'])
            && \is_array($warm['global_taxonomy_entry_traversals'])
        ) {
            $taxonomyRuleCount = \count($warm['global_taxonomy_entry_traversals']);
        }

        $fallback = $warm['global_fallback_site_handle'] ?? $warm['multisite_global_fallback_site_handle'] ?? null;

        return [
            'homepage_collection_handle' => isset($warm['homepage_collection_handle']) && \is_string($warm['homepage_collection_handle'])
                ? $warm['homepage_collection_handle']
                : 'pages',
            'homepage_uri_candidates' => isset($warm['homepage_uri_candidates']) && \is_array($warm['homepage_uri_candidates'])
                ? $this->stringListPreserveEmptyIncluded($warm['homepage_uri_candidates'])
                : ['/'],
            'global_fallback_site_handle' => \is_string($fallback) && $fallback !== ''
                ? $fallback
                : null,
            'global_entry_field_reference_total_count' => $entryTotal,
            'global_taxonomy_traversal_rule_count' => $taxonomyRuleCount,
        ];
    }

    /**
     * HTTP client configured for long-running front requests during JSON-LD warming.
     *
     * @return PendingRequest Client with timeout, connect timeout, and warmer User-Agent.
     */
    private function httpClient(): PendingRequest
    {
        return Http::timeout(120)
            ->connectTimeout(15)
            ->withHeaders([
                'User-Agent' => self::HTTP_USER_AGENT,
            ]);
    }

    /**
     * Normalize the optional `--site` Artisan option to a handle or null.
     *
     * @return string|null Non-empty site handle, or null when warming every site.
     */
    private function resolveSiteFilter(): ?string
    {
        // Read raw `--site` option from the Artisan input definition
        $siteFilter = $this->option('site');

        // Treat empty or non-string values as warm-all-sites
        $siteFilter = \is_string($siteFilter) && $siteFilter !== ''
            ? $siteFilter
            : null;

        return $siteFilter;
    }

    /**
     * Build the list of Statamic sites to include for this warm invocation.
     *
     * @param  string|null  $siteFilter  When set, restrict to that site handle.
     * @return list<StatamicSite> Sites whose URLs should be collected.
     */
    private function sitesToProcess(
        ?string $siteFilter
    ): array {
        /** @var iterable<mixed> $all */
        $all = Site::all();
        $out = [];

        // Keep Statamic site instances only and honor optional handle filtering
        foreach ($all as $site) {
            if (! $site instanceof StatamicSite) {
                continue;
            }

            if (
                $siteFilter !== null
                && $site->handle() !== $siteFilter
            ) {
                continue;
            }

            $out[] = $site;
        }

        return $out;
    }

    /**
     * Filter config handle lists to non-empty strings only.
     *
     * @param  array<int|string, mixed>  $items  Raw string handles from warm config arrays.
     * @return list<string> Non-empty strings preserved in input order.
     */
    private function sanitizeStringList(
        array $items
    ): array {
        $out = [];

        // Copy only non-empty strings so dotted global handle lists stay normalized
        foreach ($items as $item) {
            if (\is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Preserve every string entry (including empty string) when reading URI candidate lists.
     *
     * @param  array<int|string, mixed>  $items  Raw warm config values for URI candidates.
     * @return list<string> Strings only; defaults to root `/` when nothing remains.
     */
    private function stringListPreserveEmptyIncluded(
        array $items
    ): array {
        $out = [];

        // Preserve empty-string URI roots when configured that way in YAML
        foreach ($items as $item) {
            if (\is_string($item)) {
                $out[] = $item;
            }
        }

        return $out === []
            ? ['/']
            : $out;
    }
}
