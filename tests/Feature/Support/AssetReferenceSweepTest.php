<?php

declare(strict_types=1);

use Statamic\Events\AssetDeleted;
use Statamic\Listeners\UpdateAssetReferences;
use Tests\Support\AssetReferenceSweep;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Pins the asset reference sweep switch the `serial` asset tests use (UAMS-Web/uams-statamic-archive#2500), and
 * that those tests clear no Stache store.
 *
 * The helper must restore the sweep's listeners even when a delete throws: a sweep
 * left off would silently change every later test in the process that deletes or
 * moves an asset. And the two files that once cleared the Stache must not start
 * again: UAMS-Web/uams-statamic-archive#2632 probed what a later test could see with no clear and found nothing, so
 * a clear only costs the next test a re-warm, which for a bare `Stache::clear()` UAMS-Web/uams-statamic-archive#2500
 * measured band-wide.
 *
 * @command  php artisan test --compact tests/Feature/Support/AssetReferenceSweepTest.php
 */

/**
 * The three `serial` files whose `afterEach` deletes run without the sweep (UAMS-Web/uams-statamic-archive#2500).
 */
const ASSET_REFERENCE_SWEEP_FILES = [
    'tests/Feature/Fieldtypes/AssetsMinimumDimensionsEnforcementTest.php',
    'tests/Feature/Scopes/AssetsByFileTypeScopeTest.php',
    'tests/Feature/Fieldtypes/LinkFilesAssetOptionTest.php',
];

/**
 * The two of those that cleared Stache stores after each test until UAMS-Web/uams-statamic-archive#2632.
 */
const ASSET_REFERENCE_FORMERLY_CLEARING_FILES = [
    'tests/Feature/Fieldtypes/AssetsMinimumDimensionsEnforcementTest.php',
    'tests/Feature/Scopes/AssetsByFileTypeScopeTest.php',
];

/**
 * Whether the sweep's `AssetDeleted` handler is currently bound.
 */
function assetReferenceSweepIsBound(): bool
{
    $listeners = app('events')->getRawListeners()[AssetDeleted::class] ?? [];

    return \is_array($listeners)
        && \in_array([UpdateAssetReferences::class, 'handleDeleted'], $listeners, true);
}

it('unbinds the sweep only while the clean-up runs', function (): void {
    // Control: the sweep is bound before and after, so the probe can see it
    expect(assetReferenceSweepIsBound())->toBeTrue();

    $boundDuring = null;

    AssetReferenceSweep::withoutSweep(function () use (&$boundDuring): void {
        $boundDuring = assetReferenceSweepIsBound();
    });

    expect($boundDuring)->toBeFalse()
        ->and(assetReferenceSweepIsBound())->toBeTrue();
});

it('binds the sweep again when the clean-up throws', function (): void {
    try {
        AssetReferenceSweep::withoutSweep(function (): void {
            throw new RuntimeException('a delete failed');
        });
    } catch (RuntimeException) {
        // The failure itself is not under test
    }

    expect(assetReferenceSweepIsBound())->toBeTrue();
});

it('runs every serial asset file\'s afterEach deletes without the sweep', function (string $path): void {
    $source = (string) file_get_contents(base_path($path));

    expect($source)->toContain('AssetReferenceSweep::withoutSweep(');
})->with(ASSET_REFERENCE_SWEEP_FILES);

it('clears no Stache store', function (string $path): void {
    $source = (string) file_get_contents(base_path($path));

    // Code, not the comment that explains why the clear is gone
    expect(preg_match('/^\s*Stache::clear\(\);/m', $source))->toBe(0)
        ->and(preg_match('/Stache::store\([^)]*\)[^;]*->clear\(\);/', $source))->toBe(0);
})->with(ASSET_REFERENCE_FORMERLY_CLEARING_FILES);
