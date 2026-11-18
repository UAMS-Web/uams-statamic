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

it('allows four requests a second per host when no bound is given', function (): void {
    $key = HostThrottle::keyFor('https://example.com/a');
    $limiter = Mockery::mock(RateLimiter::class);
    $limiter->shouldReceive('tooManyAttempts')->once()->with($key, 4)->andReturnFalse();
    $limiter->shouldReceive('hit')->once()->with($key, 1);

    (new HostThrottle($limiter, sleeper: static function (int $micro): void {}))->reserve('https://example.com/a');
});

it('sleeps for the seconds the limiter reports, and a short fixed interval when it reports none', function (): void {
    // `availableIn()` reporting 0 while the window is still closing is what the 50ms floor is
    // for; a real limiter cannot be held in that state, so the limiter is a mock.
    $key = HostThrottle::keyFor('https://example.com/a');
    $limiter = Mockery::mock(RateLimiter::class);
    $limiter->shouldReceive('tooManyAttempts')->with($key, 2)->andReturn(true, true, true, false);
    $limiter->shouldReceive('availableIn')->with($key)->andReturn(2, 1, 0);
    // Reserves one slot that expires after a second.
    $limiter->shouldReceive('hit')->once()->with($key, 1);

    $slept = [];
    $throttle = new HostThrottle($limiter, 2, static function (int $micro) use (&$slept): void {
        $slept[] = $micro;
    });

    $throttle->reserve('https://example.com/a');

    expect($slept)->toBe([2_000_000, 1_000_000, 50_000]);
});

it('sleeps for real when no sleeper is injected', function (): void {
    // The one test that spends the wait, and only the 50ms floor of it: it is what shows the
    // default sleeper sleeps at all.
    $key = HostThrottle::keyFor('https://example.com/a');
    $limiter = Mockery::mock(RateLimiter::class);
    $limiter->shouldReceive('tooManyAttempts')->with($key, 1)->andReturn(true, false);
    $limiter->shouldReceive('availableIn')->with($key)->andReturn(0);
    $limiter->shouldReceive('hit')->once()->with($key, 1);

    $started = hrtime(true);
    (new HostThrottle($limiter, 1))->reserve('https://example.com/a');
    $elapsedMicro = intdiv(hrtime(true) - $started, 1_000);

    expect($elapsedMicro)->toBeGreaterThanOrEqual(50_000);
});
