<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Carbon\Carbon;
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

covers(SchemaJsonLdOutputCache::class);

/**
 * Unit tests for JSON-LD output cache busting, last bump metadata, and cache key
 * material.
 *
 * Every mutant of the class is killed by this file alone, without
 * --covered-only (UAMS-Web/uams-statamic#320).
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

/**
 * Build a mocked entry with a fixed id and locale.
 *
 * @param  mixed  $id  Value id() returns.
 * @param  mixed  $locale  Value locale() returns.
 */
function outputCacheEntry(mixed $id, mixed $locale = 'fr'): EntryModel
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('locale')->andReturn($locale);

    return $entry;
}

/**
 * The current site's handle and language as strings.
 *
 * @return array{handle: string, lang: string}
 */
function outputCacheSite(): array
{
    /** @var Statamic\Sites\Site $site */
    $site = Site::current();
    $handle = $site->handle();
    $lang = $site->lang();

    return [
        'handle' => \is_string($handle) ? $handle : '',
        'lang' => \is_string($lang) ? $lang : '',
    ];
}

/**
 * The fingerprint the class computes for a normalized override map.
 *
 * @param  array<string, string>  $normalized  Expected normalized slot strings.
 */
function outputCacheOverrideFingerprint(array $normalized): string
{
    return hash('xxh128', json_encode($normalized, JSON_THROW_ON_ERROR));
}

it('reads the bust counter from the configured key and clamps it to a non-negative integer', function (mixed $stored, int $expected): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.bust_cache_key', 'custom-bust-key');
    Cache::forever('custom-bust-key', $stored);

    expect((new SchemaJsonLdOutputCache)->currentBust())->toBe($expected);
})->with([
    'integer' => [4, 4],
    'numeric string' => ['7', 7],
    'float string' => ['2.9', 2],
    'negative' => [-5, 0],
    'non-numeric' => ['abc', 0],
    'array' => [[3], 0],
]);

it('treats an unset bust counter as generation zero', function (): void {
    Cache::flush();

    expect((new SchemaJsonLdOutputCache)->currentBust())->toBe(0);
});

it('writes the bumped counter and a timestamped meta row under the configured keys', function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-01-02 03:04:05');
    Config::set('schema_templates.json_ld_cache.bust_cache_key', 'custom-bust-key');
    Config::set('schema_templates.json_ld_cache.last_bump_meta_key', 'custom-meta-key');
    Cache::forever('custom-bust-key', 2);

    (new SchemaJsonLdOutputCache)->bump();

    expect(Cache::get('custom-bust-key'))->toBe(3)
        ->and(Cache::get('custom-meta-key'))->toBe(['bust' => 3, 'at' => '2026-01-02T03:04:05+00:00']);

    // Forever: the rows outlive any finite TTL
    Carbon::setTestNow('2036-01-02 03:04:05');
    expect(Cache::get('custom-bust-key'))->toBe(3)
        ->and(Cache::get('custom-meta-key'))->toBeArray();

    Carbon::setTestNow();
});

it('normalizes the last bump meta row, or reads null when it is unusable', function (mixed $raw, ?array $expected): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.last_bump_meta_key', 'custom-meta-key');
    Cache::forever('custom-meta-key', $raw);

    expect((new SchemaJsonLdOutputCache)->lastBumpMeta())->toBe($expected);
})->with([
    'well formed' => [['bust' => 3, 'at' => 'then'], ['bust' => 3, 'at' => 'then']],
    'scalar strings' => [['bust' => '4', 'at' => 5], ['bust' => 4, 'at' => '5']],
    'non-scalar values' => [['bust' => [1], 'at' => ['x']], ['bust' => 0, 'at' => '']],
    'missing bust' => [['at' => 'then'], null],
    'missing at' => [['bust' => 3], null],
    'not an array' => ['row', null],
]);

it('reads null last bump meta when never bumped', function (): void {
    Cache::flush();

    expect((new SchemaJsonLdOutputCache)->lastBumpMeta())->toBeNull();
});

it('stores the warm summary forever under the configured key and reads it back', function (): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.last_warm_meta_key', 'custom-warm-key');
    $cache = new SchemaJsonLdOutputCache;

    expect($cache->lastWarmSummary())->toBeNull();

    $cache->storeLastWarmSummary(['urls' => 3, 'at' => 'then']);

    Carbon::setTestNow(now()->addYears(10));
    expect(Cache::get('custom-warm-key'))->toBe(['urls' => 3, 'at' => 'then'])
        ->and($cache->lastWarmSummary())->toBe(['urls' => 3, 'at' => 'then']);
    Carbon::setTestNow();

    Cache::forever('custom-warm-key', 'not a row');
    expect($cache->lastWarmSummary())->toBeNull();
});

it('assembles every cache key segment in order', function (): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.bust_cache_key', 'output-bust-key');
    Cache::forever('output-bust-key', 2);

    $key = (new SchemaJsonLdOutputCache)->cacheKey(outputCacheEntry('entry-abc'), [], 's1', 'w1', null, null);

    expect($key)->toBe(implode(':', [
        'schema_templates',
        'json_ld',
        'v2',
        outputCacheSite()['handle'],
        'fr',
        'entry-abc',
        'd72a49fc7ce0ff55fd8521c34f772020',
        '71328c4f5b87f22303ddb6a4291c3538',
    ]));
});

it('fingerprints the slot ids in their positional order', function (): void {
    Cache::flush();

    $key = (new SchemaJsonLdOutputCache)->cacheKey(outputCacheEntry('entry-abc'), [], null, null, 'p', 'c');

    expect(explode(':', (string) $key)[6])->toBe('2b4a5675b7b4a65a231ca0f1d03e049e');
});

it('fingerprints normalized tag overrides', function (array $tagParams, array $normalized): void {
    /** @var array{subject?: mixed, website?: mixed, webpage?: mixed, main_content?: mixed} $tagParams */
    /** @var array<string, string> $normalized */
    Cache::flush();

    $key = (new SchemaJsonLdOutputCache)->cacheKey(outputCacheEntry('entry-abc'), $tagParams, null, null, null, null);

    expect(explode(':', (string) $key)[7])->toBe(outputCacheOverrideFingerprint($normalized));
})->with([
    'scalars' => [
        ['subject' => 'a', 'website' => 5, 'webpage' => '', 'main_content' => 'm'],
        ['subject' => 'a', 'website' => '5', 'webpage' => '', 'main_content' => 'm'],
    ],
    'arrays take their first element' => [
        ['subject' => ['s', 'z'], 'website' => [0], 'webpage' => ['0'], 'main_content' => [true]],
        ['subject' => 's', 'website' => '0', 'webpage' => '0', 'main_content' => '1'],
    ],
    'unusable first elements' => [
        ['subject' => [false, 'x'], 'website' => [null, 'x'], 'webpage' => ['', 'x'], 'main_content' => [['x']]],
        ['subject' => '', 'website' => '', 'webpage' => '', 'main_content' => ''],
    ],
    'null, empty and non-scalar values' => [
        ['subject' => null, 'website' => '', 'webpage' => new stdClass, 'main_content' => []],
        ['subject' => '', 'website' => '', 'webpage' => '', 'main_content' => ''],
    ],
    'false and zero are kept' => [
        ['subject' => false, 'website' => 0, 'webpage' => '0', 'main_content' => 0.5],
        ['subject' => '', 'website' => '0', 'webpage' => '0', 'main_content' => '0.5'],
    ],
]);

it('takes the locale segment from the entry, the localized term, or the site', function (): void {
    Cache::flush();
    $cache = new SchemaJsonLdOutputCache;

    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('id')->andReturn('tags::one');
    $localized->shouldReceive('locale')->andReturn('es');

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('id')->andReturn('tags::two');
    $term->shouldNotReceive('locale');

    expect(explode(':', (string) $cache->cacheKey(outputCacheEntry('e1', 'de'), [], null, null, null, null))[4])->toBe('de')
        ->and(explode(':', (string) $cache->cacheKey(outputCacheEntry('e1', null), [], null, null, null, null))[4])->toBe('')
        ->and(explode(':', (string) $cache->cacheKey($localized, [], null, null, null, null))[4])->toBe('es')
        ->and(explode(':', (string) $cache->cacheKey($term, [], null, null, null, null))[4])->toBe(outputCacheSite()['lang'])
        ->and(outputCacheSite()['lang'])->not->toBe('');
});

it('reads a null locale segment for a localized term without a scalar locale', function (): void {
    Cache::flush();

    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('id')->andReturn('tags::one');
    $localized->shouldReceive('locale')->andReturn(null);

    expect(explode(':', (string) (new SchemaJsonLdOutputCache)->cacheKey($localized, [], null, null, null, null))[4])->toBe('');
});

it('refuses to key an entity whose id is unusable', function (EntryContract $entity): void {
    Cache::flush();

    expect((new SchemaJsonLdOutputCache)->cacheKey($entity, [], 's1', null, null, null))->toBeNull();
})->with([
    'non-string id' => fn (): EntryModel => outputCacheEntry(5),
    'id() throws' => function (): EntryModel {
        $entry = Mockery::mock(EntryModel::class);
        $entry->shouldReceive('id')->andThrow(new RuntimeException('no id'));
        $entry->shouldReceive('locale')->andReturn('fr');

        return $entry;
    },
    'no id() method' => fn (): EntryContract => Mockery::mock(EntryContract::class),
]);

it('uses empty site and locale segments when the site reports non-scalar values', function (): void {
    Cache::flush();
    $site = Mockery::mock(Statamic\Sites\Site::class);
    $site->shouldReceive('handle')->andReturn(null);
    $site->shouldReceive('lang')->andReturn(null);
    Site::shouldReceive('current')->andReturn($site);

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('id')->andReturn('tags::two');

    $segments = explode(':', (string) (new SchemaJsonLdOutputCache)->cacheKey($term, [], null, null, null, null));

    expect($segments[3])->toBe('')
        ->and($segments[4])->toBe('');
});

it('stringifies a scalar locale and site language', function (): void {
    Cache::flush();
    $site = Mockery::mock(Statamic\Sites\Site::class);
    $site->shouldReceive('handle')->andReturn('default');
    $site->shouldReceive('lang')->andReturn(7);
    Site::shouldReceive('current')->andReturn($site);
    $cache = new SchemaJsonLdOutputCache;

    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('id')->andReturn('tags::one');
    $localized->shouldReceive('locale')->andReturn(6);

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('id')->andReturn('tags::two');

    expect(explode(':', (string) $cache->cacheKey(outputCacheEntry('e1', 5), [], null, null, null, null))[4])->toBe('5')
        ->and(explode(':', (string) $cache->cacheKey($localized, [], null, null, null, null))[4])->toBe('6')
        ->and(explode(':', (string) $cache->cacheKey($term, [], null, null, null, null))[4])->toBe('7');
});

it('stringifies a scalar configured key and reads a non-scalar one as empty', function (mixed $configured, string $key): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.bust_cache_key', $configured);
    Cache::forever($key, 6);

    expect((new SchemaJsonLdOutputCache)->currentBust())->toBe(6);
})->with([
    'integer' => [5, '5'],
    'array' => [['x'], ''],
]);
