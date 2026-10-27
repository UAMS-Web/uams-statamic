<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Tests\Support\ThrowawayRoot;

/**
 * Pin the ONE ordering property `Tests\Support\BrowserWriteIsolation` rests on:
 * isolation is still installed while a browser test's own `afterEach` runs (UAMS-Web/uams-statamic-archive#1920).
 *
 * Every CP smoke deletes its collection, blueprint and user in `afterEach`. Those
 * deletes are only safe while the throwaway roots are still in place — once the
 * real directories are back, the same calls are aimed at tracked source, and they
 * are one colliding handle away from removing a committed file.
 *
 * The isolation's `end()` therefore lives in `Tests\TestCase::tearDown()` rather
 * than beside its `begin()` in `tests/Pest.php`. Pest chains a `uses()`-level hook
 * AHEAD of the file-level one in BOTH directions, so a `uses(...)->afterEach()`
 * teardown runs FIRST — the opposite of what the design needs, and the opposite of
 * what this file's first draft assumed. That was caught in review, not by a test,
 * because nothing was red: no smoke handle collides with a real one today, so the
 * misaimed deletes are silent no-ops.
 *
 * This file exists so the next such inversion is not silent. It asserts from
 * inside an `afterEach` — the exact position whose safety is in question — and
 * needs no browser, so it costs the suite milliseconds.
 *
 * Deliberately NOT a `tests/Feature` test: the property is a Pest hook-chaining
 * order that only exists for files under `tests/Browser`, where the `uses()`
 * registration in `tests/Pest.php` applies. Asserting it anywhere else would
 * exercise a different chain and prove nothing.
 *
 * @command  vendor/bin/pest tests/Browser/BrowserWriteIsolationOrderingTest.php
 */

/**
 * Whether blueprint writes are currently redirected into the throwaway root.
 *
 * Separators are normalized so the check reads the same on Windows, where
 * `Blueprint::directory()` and `storage_path()` can disagree on slash direction.
 *
 * The throwaway root is resolved through {@see ThrowawayRoot} — the same helper
 * `Tests\Support\BrowserWriteIsolation` builds it from — rather than hard-coded
 * to the shared parent. A bare `framework/testing/browser-writes` prefix would
 * also match a *sibling* run's subdirectory, so this asserts isolation to this
 * run rather than merely to the shared tree — and it keeps the file clear of the
 * parallel-safety guard's Rule B.
 */
function browserWritesAreIsolated(): bool
{
    $directory = str_replace('\\', '/', Blueprint::directory());
    $throwaway = str_replace('\\', '/', storage_path('framework/testing/browser-writes/'.ThrowawayRoot::segment()));

    return str_starts_with($directory, $throwaway);
}

afterEach(function (): void {
    if (! browserWritesAreIsolated()) {
        $directory = str_replace('\\', '/', Blueprint::directory());

        throw new RuntimeException(
            'Browser write isolation was released before this afterEach ran: the blueprint '
            ."directory is already back at \"{$directory}\". Every CP smoke deletes its "
            .'collection, blueprint and user here, so those deletes are now aimed at tracked '
            .'source. Restore BrowserWriteIsolation::end() to Tests\\TestCase::tearDown() — a '
            .'uses()->afterEach() in tests/Pest.php runs BEFORE this closure, not after it.'
        );
    }
});

it('is still isolated when a browser test reaches its own afterEach', function (): void {
    // The assertion that matters runs in the afterEach above, which is the
    // position under test. This body pins the precondition, so a failure in the
    // hook cannot be confused with isolation never having been armed at all.
    expect(browserWritesAreIsolated())->toBeTrue();
});
