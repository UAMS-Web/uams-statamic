<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\ConfigCache;
use Tests\Support\Doubles\ConfigCacheHarness;
use Tests\TestCase;

covers(ConfigCache::class);

uses(TestCase::class);

/**
 * Behavior of the ConfigCache trait (app/Fieldtypes/Traits/ConfigCache.php): the
 * first getCachedConfig() reads config() and every later call returns that copy
 * until clearConfigCache(); a null config caches as an empty array; and a
 * config() that re-enters getCachedConfig() gets an empty array instead of
 * recursing.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/ConfigCacheTest.php
 */

/** A fresh {@see ConfigCacheHarness}. */
function configCacheHarness(): ConfigCacheHarness
{
    return new ConfigCacheHarness;
}

it('reads config once and serves the cached copy afterwards', function (): void {
    $harness = configCacheHarness();

    expect($harness->cached())->toBe(['a' => 1]);

    $harness->config = ['a' => 2];

    expect($harness->cached())->toBe(['a' => 1])
        ->and($harness->reads)->toBe(1);
});

it('re-reads config after the cache is cleared', function (): void {
    $harness = configCacheHarness();
    $harness->cached();
    $harness->config = ['a' => 2];

    $harness->clearConfigCache();

    expect($harness->cached())->toBe(['a' => 2])
        ->and($harness->reads)->toBe(2);
});

it('caches a null config as an empty array', function (): void {
    $harness = configCacheHarness();
    $harness->config = null;

    expect($harness->cached())->toBe([])
        ->and($harness->cached())->toBe([])
        ->and($harness->reads)->toBe(1);
});

it('answers a re-entrant read with an empty array instead of recursing', function (): void {
    $harness = configCacheHarness();
    $harness->reenter = true;

    expect($harness->cached())->toBe(['a' => 1])
        ->and($harness->nested)->toBe([])
        ->and($harness->reads)->toBe(1);
});

it('lifts the recursion guard once the outer read finishes', function (): void {
    $harness = configCacheHarness();
    $harness->reenter = true;
    $harness->cached();
    $harness->reenter = false;
    $harness->config = ['a' => 2];

    $harness->clearConfigCache();

    expect($harness->cached())->toBe(['a' => 2]);
});
