<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Events\AssetSaved;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Regression tests for issue UAMS-Web/uams-statamic-archive#937: a single `AssetSaved` event must not rotate
 * the site-wide JSON-LD bust generation.
 *
 * The removed `BumpSchemaJsonLdCacheWhenAssetSaved` listener cold-busted JSON-LD
 * for every page on any asset save, so one import or CP upload exposed the slow
 * cold compose site-wide (~67s per page against a 60s `max_execution_time`,
 * i.e. a site-wide 504). Asset-driven staleness is now bounded by the cache
 * `ttl_seconds` instead.
 *
 * Mutation: 100% on App\Services\SchemaTemplates\SchemaJsonLdOutputCache (12 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Listeners/AssetSavedDoesNotBustSchemaJsonLdCacheTest.php
 */
it('does not rotate the bust generation when an asset is saved', function () {
    // Statamic core auto-discovers a queued listener on asset events (image
    // preset generation). Faking the queue keeps that unrelated listener
    // from serializing the Mockery asset (ReflectionMethod is unserializable)
    // without hiding the behavior under test: the bump used to happen
    // synchronously, so a synchronous listener would still run and trip the
    // assertion below.
    Queue::fake();

    // Reset cache keys used by the bust counter.
    Cache::flush();

    // Enable JSON-LD output caching for this request.
    config([
        'schema_templates.json_ld_cache.enabled' => true,
    ]);

    // Read the bust generation before the event.
    $jsonLdOutputCache = new SchemaJsonLdOutputCache;

    // Confirm bust generation starts at zero.
    expect($jsonLdOutputCache->currentBust())->toBe(0);

    // Dispatch AssetSaved through the event bus so any discovered listener runs.
    $asset = Mockery::mock(AssetContract::class);
    AssetSaved::dispatch($asset);

    // Confirm the bust generation did not rotate.
    expect($jsonLdOutputCache->currentBust())->toBe(0);
});
