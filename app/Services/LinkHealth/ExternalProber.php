<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Probe external targets over HTTP, through the guard, once per unique URL (UAMS-Web/uams-statamic-archive#1766).
 *
 * **Every request goes through {@see OutboundUrlGuard::fetch()}, and that is the point of the
 * class rather than a detail of it.** A link checker driven by content-supplied URLs is an SSRF
 * primitive: it reaches private address space, cloud metadata endpoints and internal services,
 * and a naive redirect-follower reaches them on hop three even when hop one looked public. This
 * repository has closed that three times (UAMS-Web/uams-statamic-archive#925, UAMS-Web/uams-statamic-archive#927, UAMS-Web/uams-statamic-archive#1013); the guard pre-validates the host,
 * pins the connection to a vetted public IP, and re-validates and re-pins EVERY hop rather than
 * delegating redirects to the HTTP client.
 *
 * A guard refusal is {@see ProbeOutcome::Refused} and never `Broken`. Collapsing the two would
 * show an editor a broken link they cannot fix while concealing that nothing was ever reached.
 *
 * **Intranet hosts are the one exception to the guard, and only here (UAMS-Web/uams-statamic-archive#1768).** Hosts named in
 * `link_health.intranet_hosts` are passed to the guard as private-network hosts, so a link to
 * `inside.uams.edu` is actually fetched on the network where it resolves to RFC1918 space. When
 * such a host never answers at all — refused, or no response — the outcome is
 * {@see ProbeOutcome::Unverifiable} with the reason kept, because that is a fact about where the
 * scan ran. Once the listed host HAS answered, a failure is judged as for any other host: a
 * failing status is `Broken`, and a redirect that is then refused or dies keeps that outcome.
 */
final class ExternalProber
{
    /**
     * Statuses where a `HEAD` rejection is not evidence the target is broken.
     *
     * Plenty of servers refuse `HEAD` outright or answer it differently from `GET`, so treating
     * these as broken manufactures false positives on sites that are fine. The retry is a second
     * guarded request, not a relaxation of the guard.
     *
     * @var list<int>
     */
    private const array RETRY_WITH_GET = [400, 403, 405, 406, 501];

    /*
     * The timeouts and the redirect cap below are bounds on a real transport. Every test
     * fakes the transport, so a 10s timeout is indistinguishable from 11s and a 5-hop cap
     * from a 4-hop one. Killing these needs a live slow server and a real redirect chain,
     * which is an integration concern rather than a gap in this suite.
     */

    /**
     * Seconds a request may take in total before it is abandoned.
     */
    // A transport bound no faked request can observe; see the note above.
    // @pest-mutate-ignore: IncrementInteger, DecrementInteger
    private const int TIMEOUT_SECONDS = 10;

    /**
     * Seconds a connection may take to open before it is abandoned.
     */
    // A transport bound no faked request can observe; see the note above.
    // @pest-mutate-ignore: IncrementInteger, DecrementInteger
    private const int CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * Redirect hops followed before a request is abandoned.
     */
    // A transport bound no faked request can observe; see the note above.
    // @pest-mutate-ignore: IncrementInteger, DecrementInteger
    private const int MAX_REDIRECTS = 5;

    /**
     * Outcomes already determined this run, keyed by URL.
     *
     * @var array<string, ProbeOutcome>
     */
    private array $seen = [];

    /**
     * Why a URL could not be reached, keyed by URL — the guard's refusal or the transport error.
     *
     * @var array<string, string>
     */
    private array $reasons = [];

    /**
     * Exact host names the guard may reach in private-network space (UAMS-Web/uams-statamic-archive#1768).
     *
     * @var list<string>
     */
    private readonly array $intranetHosts;

    /**
     * Guarded requests issued this run, across every URL and both verbs.
     */
    private int $attempts = 0;

    /**
     * @param  list<string>|null  $intranetHosts  Intranet hosts to allow past the guard's private
     *                                            address refusal; null reads
     *                                            `link_health.intranet_hosts`.
     */
    public function __construct(?array $intranetHosts = null)
    {
        $configured = $intranetHosts ?? config('link_health.intranet_hosts', []);

        // `array_values()` re-indexes for the `list<string>` type larastan holds the property to.
        // The hosts are only ever read by a `foreach` membership test, so a keyed array gives
        // the same answer and no input separates the two.
        // @pest-mutate-ignore: UnwrapArrayValues
        $this->intranetHosts = \is_array($configured)
            ? array_values(array_filter($configured, \is_string(...)))
            : [];
    }

    /**
     * Probe one URL, reusing an outcome already determined for it in this run.
     *
     * The cache is what makes "probed once, fanned out to every occurrence" true: a URL appearing
     * in twelve entries costs one request, and the other eleven are answered from here.
     */
    public function probe(string $url): ProbeOutcome
    {
        return $this->seen[$url] ??= $this->determine($url);
    }

    /**
     * Probe every distinct URL once and return the outcome for each.
     *
     * @param  list<string>  $urls  URLs, which may repeat.
     * @return array<string, ProbeOutcome> Outcome keyed by URL.
     */
    public function probeAll(array $urls): array
    {
        $outcomes = [];

        // `probe()` already caches per URL, so de-duplicating here saves a loop iteration and
        // never a request. Dropping it leaves every outcome and every request count identical,
        // so no input distinguishes the two programs.
        // @pest-mutate-ignore: UnwrapArrayUnique
        foreach (array_unique($urls) as $url) {
            $outcomes[$url] = $this->probe($url);
        }

        return $outcomes;
    }

    /**
     * How many distinct URLs this prober has actually determined an outcome for.
     *
     * This is the number of DISTINCT URLs probed, which equals the number of requests only while
     * the cache above is in place — it counts cache entries, not transport calls, so it cannot
     * detect a missing cache. A test asserting the fan-out asserts against the transport instead.
     */
    public function probedCount(): int
    {
        return \count($this->seen);
    }

    /**
     * Guarded requests issued this run, across every URL and both verbs.
     *
     * Distinct from `probedCount()`, and the distinction is the point: this counts attempts, so
     * it sees a retry that should not have happened and a cache that stopped working. A guard
     * refusal never reaches the transport, so it is the only observable for "a refusal is
     * terminal" — `Http::assertSentCount()` reads zero either way.
     */
    public function attemptCount(): int
    {
        return $this->attempts;
    }

    /**
     * Why a probed URL could not be reached, or null when it answered or was never probed.
     *
     * The guard's refusal wording or the transport's error, verbatim, so a report can tell an
     * editor why a link was not checked rather than only that it was not.
     */
    public function reasonFor(string $url): ?string
    {
        return $this->reasons[$url] ?? null;
    }

    /**
     * Whether a URL's host is one of the configured intranet hosts, by the guard's own rule.
     */
    public function isIntranetUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return \is_string($host) && OutboundUrlGuard::isListedHost($host, $this->intranetHosts);
    }

    /**
     * Issue the request(s) for one URL and classify the result.
     */
    private function determine(string $url): ProbeOutcome
    {
        $attempt = $this->decisiveAttempt($url);

        if ($attempt->reason !== null) {
            $this->reasons[$url] = $attempt->reason;
        }

        // A configured intranet host that never answered is unverifiable, not refused and not
        // broken: whether it answers depends on the network the scan ran on. If ANY hop answered,
        // the host was reached, so a later failure — a redirect loop, a refused or dead redirect
        // target — keeps its own outcome rather than hiding behind "could not check".
        if (
            ($attempt->outcome === ProbeOutcome::Refused || $attempt->outcome === ProbeOutcome::Unreachable)
            && ! $attempt->answered
            && $this->isIntranetUrl($url)
        ) {
            return ProbeOutcome::Unverifiable;
        }

        return $attempt->outcome;
    }

    /**
     * The attempt whose result decides a URL's outcome: the `HEAD`, or the `GET` retrying it.
     */
    private function decisiveAttempt(string $url): ProbeAttempt
    {
        $head = $this->attempt('HEAD', $url);

        // A guard refusal is terminal and is NOT retried with GET: the host is not one this
        // install may reach, and a second request would be the same refusal. `attemptCount()`
        // is what makes that assertable, since a refused attempt never reaches the transport.
        if ($head->outcome === ProbeOutcome::Refused) {
            return $head;
        }

        // Retry with GET where a HEAD rejection says nothing about the target, and on a transport
        // failure, which may be the server refusing the verb rather than being down.
        // `RETRY_WITH_GET` holds only ints and the needle is int|null, so loose and strict
        // comparison admit exactly the same statuses and no input separates them.
        // @pest-mutate-ignore: TrueToFalse
        if ($head->status === null || \in_array($head->status, self::RETRY_WITH_GET, true)) {
            return $this->attempt('GET', $url);
        }

        return $head;
    }

    /**
     * One guarded request, reduced to an outcome and the status it saw.
     */
    private function attempt(string $method, string $url): ProbeAttempt
    {
        $this->attempts++;

        // Set by the response middleware below the moment any hop answers. A connection that is
        // refused or fails never produces a response, so it never sets this.
        $answered = false;
        $markAnswered = static function (ResponseInterface $response) use (&$answered): ResponseInterface {
            $answered = true;

            return $response;
        };

        try {
            $response = OutboundUrlGuard::fetch(
                $method,
                $url,
                static fn (PendingRequest $request): PendingRequest => $request
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->withResponseMiddleware($markAnswered),
                self::MAX_REDIRECTS,
                $this->intranetHosts,
            );
        } catch (Throwable $throwable) {
            // The guard throws `RuntimeException` for a refusal — a non-public host, a
            // non-http(s) scheme, or a redirect hop that pivoted into private space. Anything
            // else reaching here is a transport failure.
            return new ProbeAttempt(
                $this->isRefusal($throwable) ? ProbeOutcome::Refused : ProbeOutcome::Unreachable,
                null,
                $throwable->getMessage(),
                $answered,
            );
        }

        return new ProbeAttempt(
            $response->successful() ? ProbeOutcome::Healthy : ProbeOutcome::Broken,
            $response->status(),
            null,
            // `answered` is read only for a refused or unreachable attempt, and an
            // attempt that got a response is neither, so this mutant is equivalent
            // @pest-mutate-ignore: TrueToFalse
            true,
        );
    }

    /**
     * Whether a thrown error is the guard refusing, rather than the network failing.
     *
     * Matched on the guard's own refusal wording rather than on the exception class, because it
     * throws `RuntimeException` for both refusals and its redirect-limit abort, and only the
     * former means "this install may not reach that host".
     */
    private function isRefusal(Throwable $error): bool
    {
        return str_starts_with($error->getMessage(), 'Refusing ');
    }
}
