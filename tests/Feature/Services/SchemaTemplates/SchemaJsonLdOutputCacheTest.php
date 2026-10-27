<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryModel;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for JSON-LD output cache busting, last bump metadata, and cache key
 * material.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaJsonLdOutputCache,
 * 76 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaJsonLdOutputCacheTest.php
 */
it('increments the generation counter when bumped', function () {
    // Reset the cache store so bust and metadata keys start clean
    Cache::flush();

    // Instantiate the cache helper under test
    $cache = new SchemaJsonLdOutputCache;

    // Check the bust counter starts at zero before any bump
    expect($cache->currentBust())->toBe(0);

    // Bump the bust counter and persist last bump metadata for the CP utility
    $cache->bump();

    // Check the bust counter reflects the incremented generation
    expect($cache->currentBust())->toBe(1);

    // Read the last bump metadata blob written alongside the bump
    $meta = $cache->lastBumpMeta();

    // Assert last bump metadata includes the new bust value and a timestamp
    expect($meta)->toBeArray();
    expect(data_get($meta, 'bust'))->toBe(1);
    expect(data_get($meta, 'at'))->not->toBeEmpty();
});

it('changes the cache key after a bump', function () {
    // Reset the cache store so bust generation starts at zero
    Cache::flush();

    // Build a mocked entry that supplies stable id and locale for key material
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('entry-abc');
    $entry->shouldReceive('locale')->andReturn('default');

    // Instantiate the cache helper under test
    $cache = new SchemaJsonLdOutputCache;

    // Compute a deterministic cache key before bumping the generation
    $first = $cache->cacheKey(
        $entry,
        [],
        's1',
        'w1',
        null,
        null
    );

    // Bump the bust counter so subsequent keys use a new generation segment
    $cache->bump();

    // Compute the cache key again with the same slot inputs after the bump
    $second = $cache->cacheKey(
        $entry,
        [],
        's1',
        'w1',
        null,
        null
    );

    // Check the key string changed across the bump boundary
    expect($second)->not->toBe($first);

    // Check the post-bump key advertises the incremented generation marker
    expect($second)->toContain(':v1:');
});

it('refuses to key an entity that has no persisted id', function () {
    // Reset the cache store so bust generation starts at zero
    Cache::flush();

    // An entity whose id() is empty has no stable identity to key on
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('');
    $entry->shouldReceive('locale')->andReturn('default');

    $cache = new SchemaJsonLdOutputCache;

    expect($cache->cacheKey($entry, [], 's1', 'w1', null, null))->toBeNull();
});

it('rests on a premise that still holds: PHP recycles a freed object hash', function () {
    // ---
    // The premise behind the guard below, asserted rather than assumed. The old
    // `resolveContextEntityId()` fell back to `spl_object_hash()`, which is a
    // memory address: free an object and the next allocation is handed the same
    // string. Two unrelated id-less entities in one process therefore computed
    // the SAME cache key, and the second was served the first's JSON-LD — the
    // reason four Feature/Views and Feature/Http tests failed only under
    // `--parallel`, where distribution decides which fixtures share a process.
    //
    // A plain class rather than a Mockery double on purpose: Mockery retains
    // every mock in its container until `Mockery::close()`, so `unset()` does
    // not free one and the hash is never recycled. An earlier draft of this
    // test used a mock and its own control caught it.
    // ---
    $recycled = new class
    {
        public int $x = 1;
    };

    $firstHash = spl_object_hash($recycled);
    unset($recycled);

    $replacement = new class
    {
        public int $x = 1;
    };

    expect(spl_object_hash($replacement))->toBe(
        $firstHash,
        'PHP no longer recycles object hashes; the #1909 hazard may have changed shape'
    );
});

it('yields no key for any id-less entity, so two cannot collide (#1909)', function () {
    Cache::flush();

    $cache = new SchemaJsonLdOutputCache;

    // Two distinct entities, both without a persisted id. Whatever object
    // hashes they happen to get — recycled or not — neither can be keyed, so
    // there is nothing for one to collide with in the other's slot.
    $first = Mockery::mock(EntryModel::class);
    $first->shouldReceive('id')->andReturn('');
    $first->shouldReceive('locale')->andReturn('default');

    $second = Mockery::mock(EntryModel::class);
    $second->shouldReceive('id')->andReturn('');
    $second->shouldReceive('locale')->andReturn('default');

    expect($cache->cacheKey($first, [], 's1', 'w1', null, null))->toBeNull();
    expect($cache->cacheKey($second, [], 's1', 'w1', null, null))->toBeNull();

    // Positive control: a real id still keys, so the fix declines the unsafe
    // case rather than disabling the cache outright.
    $identified = Mockery::mock(EntryModel::class);
    $identified->shouldReceive('id')->andReturn('entry-abc');
    $identified->shouldReceive('locale')->andReturn('default');

    expect($cache->cacheKey($identified, [], 's1', 'w1', null, null))
        ->toBeString()
        ->toContain('entry-abc');
});
