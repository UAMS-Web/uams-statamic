<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Tests\Support\StacheIsolatedTestCase;

/**
 * A Stache-isolated test must not empty the worker's SHARED cache store (UAMS-Web/uams-statamic-archive#1994).
 *
 * `ProviderApiTest` called `Cache::flush()` in its `beforeEach`. That erased the
 * seeded Stache index, and what rebuilt in its place was starved rather than
 * absent: measured on `entries::pages`, a directory holding 82 files rebuilt as
 * **1**, and a one-entry list is truthy, so it was cached as authoritative and
 * never re-read. Every later serial test ran against a Stache holding roughly one
 * entry per collection while the band reported green — tests needing *any* entry
 * passed, tests needing a *specific* one skipped through the UAMS-Web/uams-statamic-archive#1738 discriminator.
 *
 * The incomplete rebuild is UAMS-Web/uams-statamic-archive#2037. This pins the trigger.
 *
 * @command php artisan test --compact tests/Feature/Stache/SharedStacheIndexSurvivesIsolatedTestsTest.php
 */
uses(StacheIsolatedTestCase::class)->group('serial');

/** Line terminators spelled out: `\R` also matches the bare byte 0x85, a UTF-8 continuation byte (UAMS-Web/uams-statamic-archive#2005). */
const SHARED_STACHE_LINE_SPLIT = '/\r\n|\n|\r/';

it('leaves the shared cache store intact when an isolated test flushes its own', function (): void {
    $sentinel = 'stache-isolation-sentinel-'.getmypid();

    // Straight to the worker's shared file store — where the seeded Stache index
    // lives — bypassing whatever the default happens to be.
    Cache::store('file')->forever($sentinel, 'intact');

    $this->flushIsolatedCache();

    expect(Cache::store('file')->get($sentinel))->toBe(
        'intact',
        'An isolated test emptied the shared cache store, which erases the seeded Stache index (#1994).'
    );

    Cache::store('file')->forget($sentinel);
});

it('still empties the isolated store it is meant to reach', function (): void {
    // Bounds the helper on the other side: scoping it must not make it a no-op.
    // Addressed by store name, because the DEFAULT store is deliberately left
    // alone — a bare `Cache::put()` here would land in the file store and prove
    // nothing.
    Cache::store('stache_isolated')->put('isolated-probe', 'present');

    expect(Cache::store('stache_isolated')->get('isolated-probe'))->toBe('present');

    $this->flushIsolatedCache();

    expect(Cache::store('stache_isolated')->get('isolated-probe'))->toBeNull();
});

it('has no serial Stache-isolated suite calling a bare cache flush', function (): void {
    // The recurrence guard, scoped to what this defect owns: suites that are BOTH
    // Stache-isolated AND in the `serial` group. Those share one cache store with
    // every other serial test, so a bare flush there erases the seeded index for
    // everything that follows — invisible in the flushing suite's own run, because
    // it only harms whatever executes next.
    //
    // Isolated suites OUTSIDE the serial group are deliberately not flagged: they
    // run in the parallel band, where config/cache.php tokenizes the cache path per
    // paratest worker, so a flush starves only that worker. That is the UAMS-Web/uams-statamic-archive#1747 shape
    // and belongs to UAMS-Web/uams-statamic-archive#2037. Several such suites exist and flush legitimately.
    //
    // Source-level, because no behavioral assertion inside one suite can observe
    // another suite's flush.
    $needle = 'Cache::'.'flush()';   // split so this line cannot match itself
    $offenders = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        $isolated = str_contains($source, 'StacheIsolatedTestCase') || str_contains($source, 'SchemaFixtureStacheTestCase');

        if (! $isolated || ! str_contains($source, "group('serial')")) {
            continue;
        }

        foreach (preg_split(SHARED_STACHE_LINE_SPLIT, $source) ?: [] as $number => $line) {
            $trimmed = ltrim($line);

            // Several files discuss the call without making it.
            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_contains($trimmed, $needle)) {
                $offenders[] = str_replace(base_path().\DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'Use $this->flushIsolatedCache() instead — a bare flush empties the shared Stache index (#1994).'
    );
});
