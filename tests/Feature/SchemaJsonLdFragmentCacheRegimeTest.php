<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdFragmentCache;
use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset;
use Tests\Support\SchemaFixtureStacheTestCase;

/**
 * Guards the JSON-LD cache regime the `SchemaJsonLd*` suites run under (UAMS-Web/uams-statamic-archive#1842).
 *
 * Those suites need the **page output cache** off and used to get it by clearing
 * `json_ld_cache.enabled` — the master switch — which took the cross-request
 * **fragment** cache down with it. 19 of the 20 profiled that regime, so UAMS-Web/uams-statamic-archive#1797,
 * UAMS-Web/uams-statamic-archive#1800 and UAMS-Web/uams-statamic-archive#1801 each measured the compose path with production's single largest
 * optimization switched off. {@see useProductionJsonLdFragmentCacheRegime()} now
 * sets the two switches independently; this suite is what stops that silently
 * regressing.
 *
 * Three properties are pinned here:
 *
 *  1. Under the regime the fragment cache **writes** and the page output cache does
 *     **not** — the split is observed at the cache store, not asserted from config.
 *  2. A fragment written by one test is **unreachable** from the next, by bust
 *     rotation rather than by a flush (the entry is still physically present under
 *     its old key — a `Cache::flush()` would have taken the worker's Stache with it).
 *  3. The emitted JSON-LD is **byte-identical** with fragments on and off within one
 *     generation, so the regime change is a cost change and never an output change.
 *     Byte-identity holds *within* a generation, which is the property per-test
 *     rotation guarantees — reuse across generations shifts `@id` suffix allocation
 *     (see {@see useProductionJsonLdFragmentCacheRegime()}), and deleting the rotation
 *     is what turns test 3 here red.
 *
 * Runs on {@see SchemaFixtureStacheTestCase}'s hermetic Star-Trek fixture graph, so
 * it needs no `content/` submodule. That graph carries a global `accountable_person`
 * (Spock), which is exactly the global-set-sourced, `merge_source_context="false"`
 * target shape the fragment cache exists to cache.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdFragmentCacheRegimeTest.php
 */
uses(SchemaFixtureStacheTestCase::class, MockeryPHPUnitIntegration::class);

/** Canonical `@id` of the fixture person page the graph is composed for. */
const SCHEMA_LD_REGIME_PERSON_URL = 'http://uams-statamic.test/people/james-kirk';

/**
 * Cache key under which the first isolation test parks the fragment key it seeded, so
 * the second can prove that key is no longer reachable.
 *
 * The carrier is the cache store itself rather than a per-process static, because the
 * two tests are not guaranteed to share a process — `pest --update-shards` runs the
 * suite functionally, one process per test. Keeping the carrier where the fragment and
 * the bust counter already live means all three always share a fate: every gate path
 * runs `test:seed-stache`, and `CreatesApplication` wipes the whole default cache
 * directory at each process's first boot, so a carrier never outlives the run that
 * wrote it. The name is test-scoped and never read by app code.
 *
 * One residual, deliberate: running *only* the second test with `--filter` in a tree
 * that was never seeded can satisfy the `is_string()` guard from a previous process's
 * carrier. The three assertions that follow still exercise a real rotation property,
 * and deleting the rotation reds this test either way — the generation counter lives in
 * that same store, so a surviving carrier was minted at the same generation the test
 * then derives. That is a property of filtering half of a paired test, not of the
 * carrier.
 */
const SCHEMA_LD_REGIME_SEEDED_KEY_CARRIER = 'tests:schema-ld-regime:seeded-fragment-key';

/**
 * Record every cache key written while `$work` runs.
 *
 * Uses Laravel's own `KeyWritten` event rather than inspecting the store, so the
 * assertion holds whatever driver the worker is configured with.
 *
 * @param  callable():mixed  $work  Operation whose cache writes are observed
 * @return array{result: mixed, keys: list<string>} The return value and the keys written
 */
function schemaLdRegimeRecordingCacheWrites(callable $work): array
{
    /** @var list<string> $keys */
    $keys = [];

    Event::listen(
        KeyWritten::class,
        function (KeyWritten $event) use (&$keys): void {
            $keys[] = $event->key;
        }
    );

    $result = $work();

    Event::forget(KeyWritten::class);

    return [
        'result' => $result,
        'keys' => $keys,
    ];
}

/**
 * The fragment key for a fixed template/target pair under the current bust generation.
 *
 * Both isolation tests derive their key this way, so the only thing that can differ
 * between them is the generation — which is exactly the property under test.
 *
 * @param  SchemaJsonLdFragmentCache  $cache  Cache whose key derivation is exercised
 * @return string Non-null key; the target mock carries a stable id, so one always exists
 */
function schemaLdRegimeFragmentKey(SchemaJsonLdFragmentCache $cache): string
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('8f5b7c31-0b8e-4f0a-9a3d-2c6f1b4e7d90');

    $key = $cache->keyFor('regime-template', $entry);

    // Narrow by control flow: keyFor() only returns null for a target with no stable
    // identity, which the mock above is not.
    if (! \is_string($key)) {
        Assert::fail('Expected a fragment key for a target carrying a stable id.');
    }

    return $key;
}

/**
 * Render the fixture person page's JSON-LD `@graph` through the production compose
 * path and return the raw script-tag JSON.
 *
 * @return string The raw JSON body of the `ld+json` script tag — trimmed, never decoded and
 *                re-encoded, so the comparisons above are byte comparisons of what ships
 */
function schemaLdRegimeRenderGraphJson(): string
{
    // Bind a request URL so the service resolves the entity via findByRequestUrl.
    app()->instance(
        'request',
        Request::create(SCHEMA_LD_REGIME_PERSON_URL, 'GET')
    );

    // Stub asset lookups so rendering needs no Eloquent asset metadata tables.
    Asset::shouldReceive('find')->andReturn(null);

    $scriptTag = app(SchemaOutputService::class)->outputForCurrentContext();

    $matched = preg_match(
        '/<script[^>]*type=[\'"]application\/ld\+json[\'"][^>]*>(.*?)<\/script>/is',
        $scriptTag,
        $matches
    );

    Assert::assertSame(1, $matched, 'Expected a JSON-LD script tag in the rendered output.');
    Assert::assertArrayHasKey(1, $matches);

    return trim($matches[1]);
}

it('writes fragment keys but no page output key under the regime', function (): void {
    useProductionJsonLdFragmentCacheRegime();

    ['keys' => $keys] = schemaLdRegimeRecordingCacheWrites(
        fn (): string => schemaLdRegimeRenderGraphJson()
    );

    // The fragment cache is live: this is the write the master switch used to suppress.
    $fragmentKeys = array_filter(
        $keys,
        fn (string $key): bool => str_contains($key, 'json_ld_fragment')
    );
    expect($fragmentKeys)->not->toBeEmpty();

    // The page output cache stays off: its keys share the `json_ld:` prefix, which the
    // trailing colon keeps distinct from `json_ld_fragment:`.
    $outputKeys = array_filter(
        $keys,
        fn (string $key): bool => str_contains($key, 'schema_templates:json_ld:')
    );
    expect($outputKeys)->toBeEmpty();
});

it('writes no fragment key when only the fragment switch is off', function (): void {
    useProductionJsonLdFragmentCacheRegime();
    config()->set('schema_templates.json_ld_cache.fragments_enabled', false);

    ['keys' => $keys] = schemaLdRegimeRecordingCacheWrites(
        fn (): string => schemaLdRegimeRenderGraphJson()
    );

    $fragmentKeys = array_filter(
        $keys,
        fn (string $key): bool => str_contains($key, 'json_ld_fragment')
    );

    expect($fragmentKeys)->toBeEmpty();
});

it('emits byte-identical JSON-LD with the fragment cache off and on', function (): void {
    // The pre-#1842 suite regime: page output off, fragments off.
    useProductionJsonLdFragmentCacheRegime();
    config()->set('schema_templates.json_ld_cache.fragments_enabled', false);
    $withoutFragments = schemaLdRegimeRenderGraphJson();

    // Production's regime: page output off, fragments on.
    useProductionJsonLdFragmentCacheRegime();
    $withFragments = schemaLdRegimeRenderGraphJson();

    // Guard against a vacuous pass: an empty or absent graph would compare equal.
    expect($withoutFragments)->toContain('"@graph"');

    expect($withFragments)->toBe($withoutFragments);
});

it('seeds a fragment whose key the next test must not reach', function (): void {
    useProductionJsonLdFragmentCacheRegime();

    $cache = app(SchemaJsonLdFragmentCache::class);
    $key = schemaLdRegimeFragmentKey($cache);

    $cache->put(
        $key,
        [
            '@type' => 'Person',
            'name' => 'Stale fragment seeded by the previous test',
        ]
    );

    // Hand the key to the next test so it can prove the rotation, not a flush.
    Cache::forever(SCHEMA_LD_REGIME_SEEDED_KEY_CARRIER, $key);

    expect($cache->get($key))->not->toBeNull();
});

it('cannot reach the fragment the previous test seeded', function (): void {
    $seeded = Cache::get(SCHEMA_LD_REGIME_SEEDED_KEY_CARRIER);

    // Fails loudly if the seeding test above did not run, rather than passing vacuously.
    if (! \is_string($seeded)) {
        Assert::fail('Expected the previous test to have seeded a fragment key.');
    }

    useProductionJsonLdFragmentCacheRegime();

    $cache = app(SchemaJsonLdFragmentCache::class);
    $key = schemaLdRegimeFragmentKey($cache);

    // The seeded entry is still physically in the store: isolation comes from rotating
    // the bust generation, not from flushing a store the Stache index shares.
    expect($cache->get($seeded))->not->toBeNull();

    // Same template and target, new generation — so the stale fragment is unreachable.
    expect($key)->not->toBe($seeded);
    expect($cache->get($key))->toBeNull();
});
