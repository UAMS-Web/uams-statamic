<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdFragmentCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryModel;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for JSON-LD fragment cache key generation, generation-scoped
 * busting, node storage round trips, and the enablement switches.
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaJsonLdFragmentCache,
 * 62 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaJsonLdFragmentCacheTest.php
 */
it('embeds the bust generation in fragment keys and rotates them on a bump', function () {
    // Reset the cache store so the bust generation starts at zero.
    Cache::flush();

    // Build the output cache that supplies the bust generation.
    $outputCache = new SchemaJsonLdOutputCache;

    // Instantiate the fragment cache under test.
    $cache = new SchemaJsonLdFragmentCache($outputCache);

    // Build a mocked entry that supplies a stable uuid id for key material.
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('0e3f9a52-1f6c-4d2a-9b8e-7c5d4a3b2f10');

    // Compute a fragment key before bumping the generation.
    $first = $cache->keyFor(
        'template-1',
        $entry
    );

    // Check the pre-bump key advertises the zero generation marker.
    expect($first)->toBeString();
    expect($first)->toContain(':v0:');

    // Check the key identifies the target entry by its id segment.
    expect($first)->toContain('entry-0e3f9a52-1f6c-4d2a-9b8e-7c5d4a3b2f10');

    // Bump the bust counter so subsequent keys use a new generation segment.
    $outputCache->bump();

    // Compute the fragment key again with the same inputs after the bump.
    $second = $cache->keyFor(
        'template-1',
        $entry
    );

    // Check the key string changed across the bump boundary.
    expect($second)->not->toBe($first);

    // Check the post-bump key advertises the incremented generation marker.
    expect($second)->toContain(':v1:');
});

it('returns null for targets without a stable identity', function () {
    // Reset the cache store so the bust generation starts clean.
    Cache::flush();

    // Instantiate the fragment cache under test.
    $cache = new SchemaJsonLdFragmentCache(new SchemaJsonLdOutputCache);

    // Check a plain string target (e.g. a raw URL) is not cacheable.
    expect($cache->keyFor(
        'template-1',
        'https://example.test/person'
    ))->toBeNull();

    // Check a null target is not cacheable.
    expect($cache->keyFor(
        'template-1',
        null
    ))->toBeNull();
});

it('misses absent keys, round-trips stored nodes, and skips nodes without a type', function () {
    // Reset the cache store so reads start from an empty store.
    Cache::flush();

    // Instantiate the fragment cache under test.
    $cache = new SchemaJsonLdFragmentCache(new SchemaJsonLdOutputCache);

    // Check a never-written key reads as a miss.
    expect($cache->get('schema_templates:json_ld_fragment:missing'))->toBeNull();

    // Store a well-formed node under a fragment key.
    $node = [
        '@id' => 'x',
        '@type' => 'Person',
    ];
    $cache->put(
        'schema_templates:json_ld_fragment:roundtrip',
        $node
    );

    // Check the stored node round-trips unchanged.
    $roundTripped = $cache->get('schema_templates:json_ld_fragment:roundtrip');
    expect($roundTripped)->toBeArray();
    expect($roundTripped)->toHaveCount(2);
    expect(data_get($roundTripped, '@id'))->toBe('x');
    expect(data_get($roundTripped, '@type'))->toBe('Person');

    // Attempt to store a malformed node missing the @type marker.
    $cache->put(
        'schema_templates:json_ld_fragment:malformed',
        ['@id' => 'x']
    );

    // Check the malformed node was skipped and reads as a miss.
    expect($cache->get('schema_templates:json_ld_fragment:malformed'))->toBeNull();
});

it('requires both cache switches before fragment caching is enabled', function () {
    // Reset the cache store so config-driven checks start clean.
    Cache::flush();

    // Instantiate the fragment cache under test.
    $cache = new SchemaJsonLdFragmentCache(new SchemaJsonLdOutputCache);

    // Disable the JSON-LD output cache while fragments stay on.
    Config::set('schema_templates.json_ld_cache.enabled', false);
    Config::set('schema_templates.json_ld_cache.fragments_enabled', true);

    // Check fragments are disabled when the output cache is off.
    expect($cache->enabled())->toBeFalse();

    // Enable the output cache but disable the fragment switch.
    Config::set('schema_templates.json_ld_cache.enabled', true);
    Config::set('schema_templates.json_ld_cache.fragments_enabled', false);

    // Check fragments are disabled when the fragment switch is off.
    expect($cache->enabled())->toBeFalse();

    // Enable both switches together.
    Config::set('schema_templates.json_ld_cache.enabled', true);
    Config::set('schema_templates.json_ld_cache.fragments_enabled', true);

    // Check fragments are enabled only when both switches are on.
    expect($cache->enabled())->toBeTrue();
});
