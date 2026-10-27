<?php

declare(strict_types=1);

use App\Services\LinkHealth\HostThrottle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Coverage for {@see HostThrottle} — bounding the outbound rate per host (UAMS-Web/uams-statamic-archive#1766).
 *
 * The sleeper is injected, so waiting is OBSERVED rather than spent: a test that proved the bound
 * by actually sleeping would take as long as the bound it was testing, and would then be the
 * slowest thing in the suite for the least reason.
 *
 * @command  php artisan test --compact tests/Feature/Services/LinkHealth/HostThrottleTest.php
 */
uses(TestCase::class);

function htLimiter(): RateLimiter
{
    return new RateLimiter(Cache::store('array'));
}

it('gives two URLs on one host the same key, and two hosts different keys', function (): void {
    // Everything this class does rests on exactly this.
    expect(HostThrottle::keyFor('https://example.com/a'))
        ->toBe(HostThrottle::keyFor('https://example.com/b'))
        ->and(HostThrottle::keyFor('https://example.com/a'))
        ->not->toBe(HostThrottle::keyFor('https://other.example/a'));
});

it('folds host case, so one host is not two buckets', function (): void {
    expect(HostThrottle::keyFor('https://EXAMPLE.com/a'))
        ->toBe(HostThrottle::keyFor('https://example.com/a'));
});

it('gives a hostless URL its own bucket rather than joining another host', function (): void {
    // It will be refused by the guard anyway; what matters is that it does not consume a real
    // host's allowance on the way to being refused.
    expect(HostThrottle::keyFor('not-a-url'))->toBe('link_health:outbound:v1:(none)');
});

it('does not wait while a host is under its bound', function (): void {
    $slept = [];
    $throttle = new HostThrottle(htLimiter(), 3, function (int $micro) use (&$slept): void {
        $slept[] = $micro;
    });

    $throttle->reserve('https://example.com/a');
    $throttle->reserve('https://example.com/b');

    expect($slept)->toBe([]);
});

it('waits once the host reaches its bound', function (): void {
    $slept = [];
    $limiter = htLimiter();
    $throttle = new HostThrottle($limiter, 2, function (int $micro) use (&$slept, $limiter): void {
        $slept[] = $micro;
        // Stand in for the window closing, so the loop can terminate.
        $limiter->clear(HostThrottle::keyFor('https://example.com/a'));
    });

    $throttle->reserve('https://example.com/a');
    $throttle->reserve('https://example.com/a');
    $throttle->reserve('https://example.com/a');

    expect($slept)->not->toBe([]);
});

// The bound is PER HOST, so a busy host must not throttle an idle one — otherwise one popular
// domain would slow the entire scan.
it('does not let one host consume another host allowance', function (): void {
    $slept = [];
    $throttle = new HostThrottle(htLimiter(), 2, function (int $micro) use (&$slept): void {
        $slept[] = $micro;
    });

    $throttle->reserve('https://busy.example/a');
    $throttle->reserve('https://busy.example/b');
    $throttle->reserve('https://idle.example/a');

    expect($slept)->toBe([]);
});
