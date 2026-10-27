<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use App\Services\OnetWebServices\OnetOccupationOverviewService;
use Closure;
use Illuminate\Cache\RateLimiter;

/**
 * Bound the outbound request rate PER HOST while a scan runs (UAMS-Web/uams-statamic-archive#1766).
 *
 * A link inventory is lumpy: most of it points at a handful of hosts, so a scan with no bound
 * sends hundreds of requests to one site in seconds. That site then rate-limits us, and the
 * checker records a wall of `429`s and timeouts as broken links — **manufacturing exactly the
 * false positives the tool exists to eliminate**, and doing it worst on the sites we link to most.
 *
 * Built on `Illuminate\Cache\RateLimiter` rather than an in-process counter, reusing the pattern
 * in {@see OnetOccupationOverviewService}, because the counter has
 * to hold across processes: two workers scanning concurrently are two senders to the same host,
 * and a per-process limit would let each of them stay politely under a bound they jointly break.
 */
final class HostThrottle
{
    /**
     * @param  RateLimiter  $limiter  Backing limiter, sharing counters through its cache store.
     * @param  int  $maxPerSecond  Requests permitted to one host per second.
     * @param  Closure(int): void|null  $sleeper  Injected so a test can observe waiting without
     *                                            spending it. Defaults to real sleeping.
     */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxPerSecond = 4,
        private readonly ?Closure $sleeper = null,
    ) {}

    /**
     * The counter key for a URL's host.
     *
     * Pure and exposed so the keying is testable on its own: everything about this class depends
     * on two URLs on one host sharing a key, and two hosts not.
     */
    public static function keyFor(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        // A URL with no host cannot be attributed to one, so it gets its own bucket rather than
        // silently joining another host's. It will not be probed anyway — the guard refuses it.
        return 'link_health:outbound:v1:'.(\is_string($host) && $host !== '' ? strtolower($host) : '(none)');
    }

    /**
     * Block until this URL's host has a slot, then reserve one.
     *
     * Reserving AFTER waiting rather than before is what makes the bound hold: a hit recorded up
     * front would count a request that has not been sent yet, and two callers arriving together
     * would each reserve and then each proceed.
     */
    public function reserve(string $url): void
    {
        $key = self::keyFor($url);

        while ($this->limiter->tooManyAttempts($key, $this->maxPerSecond)) {
            $wait = $this->limiter->availableIn($key);

            // `availableIn` can report 0 while the window is still closing; sleeping a short
            // fixed interval then is what stops this becoming a busy loop.
            ($this->sleeper ?? static function (int $micro): void {
                usleep($micro);
            })(
                $wait > 0 ? $wait * 1_000_000 : 50_000
            );
        }

        $this->limiter->hit($key, 1);
    }
}
