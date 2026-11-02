<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1663 — `composer.json` must disable Composer's process timeout.
 *
 * `composer stan` runs `phpstan analyse` as a Composer *script*, so it inherits
 * Composer's `process-timeout` (default 300s). PHPStan over `app/` plus `tests/`
 * (extended at UAMS-Web/uams-statamic-archive#1344) exceeds that cap on a typical machine, and because the
 * local-CI runner chain-gates `phpunit` on `larastan`, the timeout does not just
 * mislabel `larastan` — it silently *skips* `phpunit`, the load-bearing job.
 * A job that can silently not run is a real hole. Disabling the cap in the `config` block
 * applies it uniformly wherever `composer stan` runs (shell, `local-ci.mjs`).
 *
 * No mutable App\ PHP surface under test — this asserts a `composer.json`
 * invariant, so there is no class to target for mutation testing. It is a
 * regression guard: a `composer update`/normalize or a manual edit that drops
 * the key would reinstate the cap and re-open the silent-skip hole.
 */
it('disables the Composer process timeout so composer stan is never capped', function (): void {
    $composerPath = base_path('composer.json');

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode((string) file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);

    // Guard against a vacuous pass if the file shape ever changes. The
    // `is_array()` narrowing keeps the offset access type-honest at level 10.
    expect($composer)->toBeArray();
    $config = is_array($composer) ? ($composer['config'] ?? null) : null;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('process-timeout');

    // `0` is Composer's sentinel for "no timeout"; a finite value would still
    // cap the run. The pinned fix disables it entirely.
    $processTimeout = is_array($config) ? ($config['process-timeout'] ?? null) : null;
    expect($processTimeout)->toBe(0);
});
