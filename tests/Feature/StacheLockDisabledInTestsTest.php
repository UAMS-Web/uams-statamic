<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Statamic\Facades\Stache;
use Statamic\Http\Middleware\StacheLock;
use Symfony\Component\Lock\LockInterface;
use Tests\TestCase;

/**
 * The Stache warming lock is off in the test environment (UAMS-Web/uams-statamic-archive#1689).
 *
 * `Error404PageTest > it renders the improved 404 body end to end` failed about
 * one parallel run in three with `Expected response status code [404] but
 * received 503`. The 503 comes from `Statamic\Http\Middleware\StacheLock`, which
 * waits `statamic.stache.lock.timeout` seconds for the `stache-warming` lock and
 * then gives up. That lock is a Symfony `FlockStore` over
 * `storage/statamic/stache-locks` — a single directory that, unlike the Laravel
 * cache, is NOT tokenized per worker — and `Stache::warm()` holds it for the
 * whole warm. So one worker warming blocks every other worker's front-end
 * request, and any test that makes a real HTTP request can 503.
 *
 * These cases pin both halves: that the middleware really does 503 on a lock it
 * cannot take, and that the suite's configuration means it never reaches that
 * code. Without the second, the first is just a description of the bug.
 *
 * @command php artisan test --compact tests/Feature/StacheLockDisabledInTestsTest.php
 */
uses(TestCase::class);

/**
 * Send a front-end request through the Stache-lock middleware with a lock that
 * always refuses — the state a sibling worker's warm puts every other worker in.
 *
 * `StacheLock::handle()` is untyped upstream, so its return is narrowed here
 * rather than at each assertion.
 *
 * @return Response What the middleware produced: the wrapped response, or its
 *                  own give-up response.
 */
function stacheLockResponseUnderARefusedLock(): Response
{
    // The middleware polls with `sleep(1)` until the timeout, so a case that
    // expects a pass-through would burn the full 30 seconds before going red if
    // the pass-through ever regressed. Zero makes that failure immediate, and
    // costs no coverage: when the lock is disabled the timeout is never read.
    config()->set('statamic.stache.lock.timeout', 0);

    $lock = Mockery::mock(LockInterface::class);
    $lock->shouldReceive('acquire')->andReturnFalse();

    Stache::shouldReceive('lock')->with('stache-warming')->andReturn($lock);

    $response = (new StacheLock)->handle(
        Request::create('/this-page-does-not-exist'),
        fn (): Response => new Response('rendered', 404)
    );

    if (! $response instanceof Response) {
        throw new RuntimeException('The Stache-lock middleware returned no response.');
    }

    return $response;
}

it('disables the shared Stache lock for the test suite', function (): void {
    expect(config('statamic.stache.lock.enabled'))->toBeFalse();
});

/**
 * `phpunit.xml` sets `STATAMIC_STACHE_LOCK_ENABLED` in every test process, so
 * `env(..., true)` never reaches its default branch under test — and that
 * default is the entire basis for the claim that production is unchanged.
 * Flipping it to `false` would leave the whole suite green while every
 * concurrent cold request warmed the Stache instead of waiting on one warm. So
 * pin the *file* default by evaluating the config with the variable removed,
 * the way `GitAutomationConfigTest` pins `statamic.git.automatic`.
 */
it('leaves the Stache lock on by default, independent of the test override', function (): void {
    $key = 'STATAMIC_STACHE_LOCK_ENABLED';
    $previous = getenv($key);
    $hadEnv = array_key_exists($key, $_ENV);
    $hadServer = array_key_exists($key, $_SERVER);

    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);

    try {
        /** @var array{lock: array{enabled: mixed}} $stache */
        $stache = require config_path('statamic/stache.php');

        expect($stache['lock']['enabled'])->toBeTrue();
    } finally {
        if ($previous !== false) {
            putenv("{$key}={$previous}");
        }
        if ($hadEnv) {
            $_ENV[$key] = $previous;
        }
        if ($hadServer) {
            $_SERVER[$key] = $previous;
        }
    }
});

it('serves a front-end request even when the Stache lock cannot be taken', function (): void {
    $response = stacheLockResponseUnderARefusedLock();

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBe('rendered');
});

// The negative: with the lock enabled, that very same refusing lock produces
// the 503 that broke `Error404PageTest`. Timeout 0 makes the middleware give up
// on its first pass instead of sleeping through the configured 30 seconds.
it('would 503 that request if the lock were still enabled', function (): void {
    config()->set('statamic.stache.lock.enabled', true);
    config()->set('statamic.stache.lock.timeout', 0);

    $response = stacheLockResponseUnderARefusedLock();

    expect($response->getStatusCode())->toBe(503)
        ->and($response->headers->get('Retry-After'))->toBe('1');
});
