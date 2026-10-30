<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdFragmentCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaJsonLdFragmentCache::class);

/**
 * Unit tests for JSON-LD fragment cache key generation, generation-scoped
 * busting, node storage round trips, and the enablement switches.
 *
 * Every mutant of the class is killed by this file alone, without
 * --covered-only (UAMS-Web/uams-statamic#320).
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

/**
 * Build the fragment cache over a fresh output cache.
 */
function fragmentCache(): SchemaJsonLdFragmentCache
{
    return new SchemaJsonLdFragmentCache(new SchemaJsonLdOutputCache);
}

/**
 * The current site's handle as a string.
 */
function fragmentSiteHandle(): string
{
    /** @var Statamic\Sites\Site $site */
    $site = Site::current();
    $handle = $site->handle();

    return \is_string($handle) ? $handle : '';
}

/**
 * Build a mocked taxonomy term with a fixed id.
 *
 * @param  mixed  $id  Value id() returns.
 */
function fragmentTerm(mixed $id): TaxonomyTerm
{
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('id')->andReturn($id);

    return $term;
}

it('assembles every fragment key segment in order', function (): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.bust_cache_key', 'fragment-bust-key');
    Cache::forever('fragment-bust-key', 3);

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('e1');

    expect(fragmentCache()->keyFor('template-1', $entry))->toBe(implode(':', [
        'schema_templates',
        'json_ld_fragment',
        'v3',
        fragmentSiteHandle(),
        'template-1',
        'entry-e1',
    ]))->and(fragmentSiteHandle())->not->toBe('');
});

it('identifies taxonomy terms and localized terms by the term id', function (): void {
    Cache::flush();

    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('term')->andReturn(fragmentTerm('tags::one'));
    $localized->shouldNotReceive('id');

    expect(fragmentCache()->keyFor('t', fragmentTerm('tags::two')))->toEndWith(':t:term-tags::two')
        ->and(fragmentCache()->keyFor('t', $localized))->toEndWith(':t:term-tags::one');
});

it('refuses targets whose id is unusable', function (mixed $target): void {
    Cache::flush();

    expect(fragmentCache()->keyFor('t', $target))->toBeNull();
})->with([
    'entry with an empty id' => function (): EntryModel {
        $entry = Mockery::mock(EntryModel::class);
        $entry->shouldReceive('id')->andReturn('');

        return $entry;
    },
    'entry with a non-string id' => function (): EntryModel {
        $entry = Mockery::mock(EntryModel::class);
        $entry->shouldReceive('id')->andReturn(5);

        return $entry;
    },
    'term with an empty id' => fn (): TaxonomyTerm => fragmentTerm(''),
    'term with a non-string id' => fn (): TaxonomyTerm => fragmentTerm(5),
    'localized term with a non-string term id' => function (): LocalizedTerm {
        $localized = Mockery::mock(LocalizedTerm::class);
        $localized->shouldReceive('term')->andReturn(fragmentTerm(5));

        return $localized;
    },
    'localized term without a taxonomy term' => function (): LocalizedTerm {
        $localized = Mockery::mock(LocalizedTerm::class);
        $localized->shouldReceive('term')->andReturn(null);

        return $localized;
    },
    'an object of another kind' => fn (): stdClass => new stdClass,
]);

it('identifies an entry known only by its contract', function (): void {
    Cache::flush();

    $entry = Mockery::mock(EntryContract::class);
    $entry->shouldReceive('id')->andReturn('e2');

    expect(fragmentCache()->keyFor('t', $entry))->toEndWith(':t:entry-e2');
});

it('reads only well-formed nodes back', function (mixed $raw, ?array $expected): void {
    Cache::flush();
    Cache::forever('fragment-key', $raw);

    expect(fragmentCache()->get('fragment-key'))->toBe($expected);
})->with([
    'node' => [['@type' => 'Person', 'name' => 'A'], ['@type' => 'Person', 'name' => 'A']],
    'array without @type' => [['name' => 'A'], null],
    'string' => ['Person', null],
]);

it('stores a node for the configured TTL, floored at 60 seconds', function (mixed $ttl, int $seconds): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.ttl_seconds', $ttl);
    $now = now();
    $this->travelTo($now);

    fragmentCache()->put('fragment-key', ['@type' => 'Person']);

    // The file store expires an item once its lifetime has fully elapsed
    $this->travelTo($now->copy()->addSeconds($seconds - 1));
    expect(fragmentCache()->get('fragment-key'))->toBe(['@type' => 'Person']);

    $this->travelTo($now->copy()->addSeconds($seconds));
    expect(fragmentCache()->get('fragment-key'))->toBeNull();
})->with([
    'configured' => [7200, 7200],
    'numeric string' => ['120', 120],
    'below the floor' => [10, 60],
    'non-numeric' => ['soon', 3600],
]);

it('stores a node for an hour when no TTL is configured', function (): void {
    Cache::flush();
    /** @var array<string, mixed> $settings */
    $settings = config('schema_templates.json_ld_cache');
    unset($settings['ttl_seconds']);
    Config::set('schema_templates.json_ld_cache', $settings);
    $now = now();
    $this->travelTo($now);

    fragmentCache()->put('fragment-key', ['@type' => 'Person']);

    $this->travelTo($now->copy()->addSeconds(3599));
    expect(fragmentCache()->get('fragment-key'))->toBe(['@type' => 'Person']);

    $this->travelTo($now->copy()->addSeconds(3600));
    expect(fragmentCache()->get('fragment-key'))->toBeNull();
});

it('reads non-boolean switch values as booleans', function (): void {
    Config::set('schema_templates.json_ld_cache.enabled', 1);
    Config::set('schema_templates.json_ld_cache.fragments_enabled', 1);

    expect(fragmentCache()->enabled())->toBeTrue();

    Config::set('schema_templates.json_ld_cache.fragments_enabled', 0);

    expect(fragmentCache()->enabled())->toBeFalse();
});

it('uses an empty site segment when the site handle is not scalar', function (): void {
    Cache::flush();
    $site = Mockery::mock(Statamic\Sites\Site::class);
    $site->shouldReceive('handle')->andReturn(null);
    Site::shouldReceive('current')->andReturn($site);

    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn('e1');

    expect(fragmentCache()->keyFor('t', $entry))->toBe('schema_templates:json_ld_fragment:v0::t:entry-e1');
});

it('enables fragments by default when neither switch is configured', function (): void {
    /** @var array<string, mixed> $settings */
    $settings = config('schema_templates.json_ld_cache');
    unset($settings['enabled'], $settings['fragments_enabled']);
    Config::set('schema_templates.json_ld_cache', $settings);

    expect(fragmentCache()->enabled())->toBeTrue();
});
