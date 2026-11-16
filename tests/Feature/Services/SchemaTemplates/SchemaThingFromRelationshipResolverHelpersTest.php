<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Contracts\Data\Augmented;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Data\AugmentedCollection;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Data;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Globals\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaThingFromRelationshipResolver::class);

/**
 * Pins the private helpers of {@see SchemaThingFromRelationshipResolver}
 * directly, through reflection, so each branch is asserted on its own rather
 * than through a whole relationship traversal.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverHelpersTest.php
 */

/**
 * Build the resolver over mocked collaborators.
 */
function thingHelpersResolver(): SchemaThingFromRelationshipResolver
{
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldIgnoreMissing();

    return new SchemaThingFromRelationshipResolver(
        Mockery::mock(SchemaOutputService::class),
        $cascade,
        new SchemaJsonLdAtIdDeduplication
    );
}

/**
 * Call a private resolver method.
 *
 * @param  string  $method  Method name.
 * @param  mixed  ...$args  Arguments.
 */
function thingHelpersCall(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaThingFromRelationshipResolver::class, $method))
        ->invoke(thingHelpersResolver(), ...$args);
}

/**
 * The composition profile bag on the current request.
 *
 * @return array<string, mixed>
 */
function thingHelpersProfileBag(): array
{
    $bag = request()->attributes->get(JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE);

    if (! \is_array($bag)) {
        return [];
    }

    /** @var array<string, mixed> $bag */
    return $bag;
}

/**
 * Replace the composition profile bag on the current request.
 */
function thingHelpersSetProfileBag(mixed $bag): void
{
    request()->attributes->set(JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE, $bag);
}

/**
 * Run a callback with the `request` binding removed, then restore it.
 */
function thingHelpersWithoutRequest(Closure $callback): void
{
    $app = app();
    $request = $app['request'];
    $app->offsetUnset('request');

    try {
        expect($app->bound('request'))->toBeFalse();
        $callback();
    } finally {
        $app->instance('request', $request);
    }
}

beforeEach(function (): void {
    thingHelpersSetProfileBag(null);
});

// ---
// Profiling: counters, timings and the slowest-builds list
// ---

it('adds an increment to the counter already in the profile bag', function (mixed $current, int $expected): void {
    thingHelpersSetProfileBag(['other' => 'kept', 'calls' => $current]);

    thingHelpersCall('recordProfileIncrement', 'calls', 2);

    expect(thingHelpersProfileBag())->toBe(['other' => 'kept', 'calls' => $expected]);
})->with([
    'integer' => [3, 5],
    'numeric string' => ['4', 6],
    'float, truncated' => [4.5, 6],
    'non-numeric string' => ['abc', 2],
    'non-scalar' => [[9], 2],
]);

it('starts a counter at its increment', function (): void {
    thingHelpersCall('recordProfileIncrement', 'calls', 3);

    expect(thingHelpersProfileBag())->toBe(['calls' => 3]);
});

it('replaces a profile bag that is not an array when counting', function (): void {
    thingHelpersSetProfileBag('broken');

    thingHelpersCall('recordProfileIncrement', 'calls', 1);

    expect(thingHelpersProfileBag())->toBe(['calls' => 1]);
});

it('adds milliseconds to the timing already in the profile bag', function (mixed $current, float $expected): void {
    thingHelpersSetProfileBag(['other' => 'kept', 'ms' => $current]);

    thingHelpersCall('recordProfileAddMs', 'ms', 1.5);

    expect(thingHelpersProfileBag())->toBe(['other' => 'kept', 'ms' => $expected]);
})->with([
    'float' => [2.25, 3.75],
    'numeric string' => ['1', 2.5],
    'non-numeric string' => ['abc', 1.5],
    'non-scalar' => [[9], 1.5],
]);

it('starts a timing at its milliseconds, replacing a bag that is not an array', function (): void {
    thingHelpersCall('recordProfileAddMs', 'ms', 1.5);
    expect(thingHelpersProfileBag())->toBe(['ms' => 1.5]);

    thingHelpersSetProfileBag(7);
    thingHelpersCall('recordProfileAddMs', 'ms', 0.5);
    expect(thingHelpersProfileBag())->toBe(['ms' => 0.5]);
});

it('records a collector sample as calls, elapsed milliseconds and a target count', function (): void {
    thingHelpersCall('recordProfileCollectorSample', 'secondary', ['a', 'b', 'c'], microtime(true) - 0.5);

    $bag = thingHelpersProfileBag();

    expect(array_keys($bag))->toBe([
        'relationship_collect_secondary_calls',
        'relationship_collect_secondary_ms',
        'relationship_targets_secondary',
    ])
        ->and($bag['relationship_collect_secondary_calls'])->toBe(1)
        ->and($bag['relationship_targets_secondary'])->toBe(3)
        ->and($bag['relationship_collect_secondary_ms'])->toBeFloat()->toBeGreaterThanOrEqual(500.0)->toBeLessThan(5000.0);
});

it('records no sample outside a request', function (string $method, array $args): void {
    thingHelpersWithoutRequest(function () use ($method, $args): void {
        thingHelpersCall($method, ...$args);
    });

    expect(thingHelpersProfileBag())->toBe([]);
})->with([
    'collector' => ['recordProfileCollectorSample', ['direct', [], 0.0]],
    'full build' => ['recordProfileFullBuildSample', ['direct', 'tpl', null, [], 0.0]],
]);

it('records a full build sample with its caller and target', function (): void {
    $entry = new EntryModel;
    $entry->id('entry-1');

    thingHelpersCall('recordProfileFullBuildSample', 'global', 'tpl-1', $entry, [
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => 'caller-tpl',
        SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY => 'founder',
    ], microtime(true) - 0.25);

    /** @var array{relationship_full_build_calls: mixed, relationship_full_build_ms: float, relationship_full_build_top: list<array<string, mixed>>} $bag */
    $bag = thingHelpersProfileBag();
    $sample = $bag['relationship_full_build_top'][0];

    expect($bag['relationship_full_build_calls'])->toBe(1)
        ->and($bag['relationship_full_build_ms'])->toBeGreaterThanOrEqual(250.0)->toBeLessThan(5000.0)
        ->and($bag['relationship_full_build_top'])->toHaveCount(1)
        ->and(array_keys($sample))->toBe(['ms', 'mode', 'schema_object', 'target_entity_type', 'target_id', 'caller_template_entry_id', 'caller_property_key'])
        ->and($sample['ms'])->toBe(round($bag['relationship_full_build_ms'], 2))
        ->and($sample['mode'])->toBe('global')
        ->and($sample['schema_object'])->toBe('tpl-1')
        ->and($sample['target_entity_type'])->toBe('entry')
        ->and($sample['target_id'])->toBe('entry-1')
        ->and($sample['caller_template_entry_id'])->toBe('caller-tpl')
        ->and($sample['caller_property_key'])->toBe('founder');
});

it('reads an integer entry id as a string profile id', function (): void {
    $entry = new EntryModel;
    $entry->id(5);

    expect(thingHelpersCall('profileEntityId', $entry))->toBe('5');
});

it('records null callers when the context names none', function (): void {
    thingHelpersCall('recordProfileFullBuildSample', 'direct', 'tpl', null, [
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => 5,
    ], microtime(true));

    /** @var list<array<string, mixed>> $top */
    $top = thingHelpersProfileBag()['relationship_full_build_top'];
    $sample = $top[0];

    expect($sample['caller_template_entry_id'])->toBeNull()
        ->and($sample['caller_property_key'])->toBeNull()
        ->and($sample['target_entity_type'])->toBe('unknown')
        ->and($sample['target_id'])->toBe('');
});

it('keeps the slowest full builds, slowest first, up to the configured limit', function (mixed $limit, int $kept): void {
    Config::set('schema_templates.json_ld_compose_profile.top_n', $limit);
    thingHelpersSetProfileBag(['relationship_full_build_top' => [
        ['ms' => 300.0, 'mode' => 'a'],
        ['ms' => '100', 'mode' => 'b'],
        ['ms' => 'x', 'mode' => 'c'],
        ['mode' => 'd'],
    ]]);

    // A build of about 200ms lands between the two timed samples
    thingHelpersCall('recordProfileFullBuildSample', 'new', 'tpl', null, [], microtime(true) - 0.2);

    /** @var list<array<string, mixed>> $top */
    $top = thingHelpersProfileBag()['relationship_full_build_top'];
    $modes = array_column($top, 'mode');

    // Unusable timings sort as zero, in their original order (usort is stable)
    expect($modes)->toBe(\array_slice(['a', 'new', 'b', 'c', 'd'], 0, $kept));
})->with([
    'one' => [1, 1],
    'two' => [2, 2],
    'numeric string' => ['3', 3],
    'zero is floored to one' => [0, 1],
]);

it('keeps all builds up to the limit and treats unusable entries as zero milliseconds', function (): void {
    Config::set('schema_templates.json_ld_compose_profile.top_n', 10);
    // A real zero sits between the missing and the non-numeric timing, so reading
    // those two as anything but zero moves one of them past it
    thingHelpersSetProfileBag(['relationship_full_build_top' => [
        ['mode' => 'd'],
        ['ms' => 0.0, 'mode' => 'z'],
        ['ms' => 'x', 'mode' => 'c'],
        ['ms' => 300.0, 'mode' => 'a'],
    ]]);

    thingHelpersCall('recordProfileFullBuildSample', 'new', 'tpl', null, [], microtime(true) - 0.2);

    /** @var list<array<string, mixed>> $top */
    $top = thingHelpersProfileBag()['relationship_full_build_top'];
    expect(array_column($top, 'mode'))->toBe(['a', 'new', 'd', 'z', 'c']);
});

it('reads a non-scalar or missing top limit as ten', function (): void {
    Config::set('schema_templates.json_ld_compose_profile.top_n', ['x']);
    $top = array_map(fn (int $i): array => ['ms' => 1000.0 + $i, 'mode' => 'm'.$i], range(1, 12));
    thingHelpersSetProfileBag(['relationship_full_build_top' => $top]);

    thingHelpersCall('recordProfileFullBuildSample', 'new', 'tpl', null, [], microtime(true));
    expect(thingHelpersProfileBag()['relationship_full_build_top'])->toHaveCount(10);

    /** @var array<string, mixed> $profile */
    $profile = config('schema_templates.json_ld_compose_profile');
    unset($profile['top_n']);
    Config::set('schema_templates.json_ld_compose_profile', $profile);
    thingHelpersSetProfileBag(['relationship_full_build_top' => $top]);

    thingHelpersCall('recordProfileFullBuildSample', 'new', 'tpl', null, [], microtime(true));
    expect(thingHelpersProfileBag()['relationship_full_build_top'])->toHaveCount(10);
});

it('starts the slowest-builds list when the bag holds none or an unusable one', function (mixed $bag): void {
    thingHelpersSetProfileBag($bag);

    thingHelpersCall('recordProfileFullBuildSample', 'new', 'tpl', null, [], microtime(true));

    /** @var list<array<string, mixed>> $top */
    $top = thingHelpersProfileBag()['relationship_full_build_top'];
    expect(array_column($top, 'mode'))->toBe(['new']);
})->with([
    'not an array' => ['broken'],
    'list not an array' => [['relationship_full_build_top' => 'broken']],
]);

it('names the kind of a profiled entity', function (Closure $entity, string $expected): void {
    expect(thingHelpersCall('profileEntityType', $entity()))->toBe($expected);
})->with([
    'entry' => [fn (): EntryContract => Mockery::mock(EntryContract::class), 'entry'],
    'term' => [fn (): TermContract => Mockery::mock(TermContract::class), 'term'],
    'asset' => [fn (): Asset => new Asset, 'asset'],
    'global variables' => [fn (): Variables => new Variables, 'global'],
    'anything else' => [fn (): stdClass => new stdClass, 'unknown'],
]);

it('reads the id of a profiled entity', function (Closure $entity, string $expected): void {
    expect(thingHelpersCall('profileEntityId', $entity()))->toBe($expected);
})->with([
    'entry' => [fn (): EntryModel => tap(new EntryModel, fn (EntryModel $e): mixed => $e->id('entry-1')), 'entry-1'],
    'entry without an id' => [fn (): EntryModel => new EntryModel, ''],
    'taxonomy term' => [fn (): TaxonomyTerm => thingHelpersTaxonomyTerm('tags', 'one'), 'tags::one'],
    'localized term' => [fn (): LocalizedTerm => new LocalizedTerm(thingHelpersTaxonomyTerm('tags', 'two'), 'default'), 'tags::two'],
    'localized term without a term' => [fn (): LocalizedTerm => new LocalizedTerm(null, 'default'), ''],
    'asset' => [fn (): Asset => tap(new Asset, fn (Asset $a): mixed => $a->path('images/a.jpg')), 'images/a.jpg'],
    'asset without a path' => [fn (): Asset => new Asset, ''],
    'global variables' => [fn (): Variables => tap(new Variables, fn (Variables $v): mixed => $v->globalSet('settings')), 'settings'],
    'global variables without a set' => [fn (): Variables => new Variables, ''],
    'a test double' => [fn (): EntryModel => tap(Mockery::mock(EntryModel::class), fn ($m) => $m->shouldReceive('id')->andReturn('mocked')), ''],
    'anything else' => [fn (): stdClass => new stdClass, ''],
]);

// ---
// Canonical @id resolution
// ---

/**
 * A real, unsaved taxonomy term with a taxonomy handle and a slug.
 */
function thingHelpersTaxonomyTerm(string $taxonomy, string $slug): TaxonomyTerm
{
    $term = new TaxonomyTerm;
    $term->taxonomy($taxonomy);
    $term->slug($slug);

    return $term;
}

/**
 * The fragment @id the resolver builds for a root-relative term path.
 */
function thingHelpersTermAtId(string $path): string
{
    return SchemaJsonLdFragmentAtId::atIdFromRootRelativePath($path);
}

/**
 * Build a mocked entry for canonical @id resolution.
 *
 * @param  array<string, mixed>  $returns  Method return values.
 */
function thingHelpersEntry(array $returns): EntryModel
{
    $entry = Mockery::mock(EntryModel::class);
    foreach ($returns + ['absoluteUrl' => null, 'url' => null, 'slug' => null, 'collectionHandle' => 'pages'] as $method => $value) {
        $entry->shouldReceive($method)->andReturn($value);
    }

    return $entry;
}

it('extracts the path of a term URL for its canonical @id', function (string $url, ?string $expected): void {
    expect(thingHelpersCall('extractPathFromTermUrlForCanonicalId', $url))->toBe($expected);
})->with([
    'absolute' => [' https://example.test/topics/a?x=1 ', '/topics/a'],
    'absolute, upper-case scheme' => ['HTTPS://example.test/b', '/b'],
    'custom scheme' => ['git+ssh://example.test/c', '/c'],
    'absolute without a path' => ['https://example.test', '/'],
    'absolute that parse_url rejects' => ['https:///x', '/'],
    'protocol-relative' => ['//example.test/d', '/d'],
    'protocol-relative without a path' => ['//example.test', '/'],
    'root-relative' => ['/e/f', '/e/f'],
    'relative' => ['g/h', '/g/h'],
    'not a scheme' => ['1ab://x/y', '/1ab://x/y'],
    'blank' => ['   ', null],
]);

it('builds a term @id from its URL path, or none for a blank URL', function (): void {
    expect(thingHelpersCall('canonicalIdForTaxonomyTermUrl', 'https://example.test/topics/a'))->toBe(thingHelpersTermAtId('/topics/a'))
        ->and(thingHelpersCall('canonicalIdForTaxonomyTermUrl', ' '))->toBeNull();
});

it('uses an absolute URL string as its own @id', function (mixed $value, ?string $expected): void {
    expect(thingHelpersCall('resolveCanonicalIdForEntity', $value))->toBe($expected);
})->with([
    'padded URL' => [' https://example.test/a ', 'https://example.test/a'],
    'not a URL' => ['plain text', null],
    'empty' => ['', null],
    'null' => [null, null],
    'object' => [new stdClass, null],
]);

it('reads an entry @id from its absolute URL, then its URL', function (array $returns, ?string $expected): void {
    /** @var array<string, mixed> $returns */
    expect(thingHelpersCall('resolveCanonicalIdForEntity', thingHelpersEntry($returns)))->toBe($expected);
})->with([
    'absolute URL' => [['absoluteUrl' => 'https://example.test/a', 'url' => '/b'], 'https://example.test/a'],
    'URL' => [['absoluteUrl' => '', 'url' => '/b'], '/b'],
    'URL of a subject, before its slug' => [['absoluteUrl' => '', 'url' => '/b', 'collectionHandle' => 'subject_matter', 'slug' => 'heart'], '/b'],
    'no URL' => [['absoluteUrl' => '', 'url' => ''], null],
]);

it('gives routeless subjects and specializations an @id from their slug', function (string $collection, string $prefix): void {
    $entry = thingHelpersEntry(['collectionHandle' => $collection, 'slug' => 'heart']);

    expect(thingHelpersCall('resolveCanonicalIdForEntity', $entry))->toBe(thingHelpersTermAtId($prefix.'heart'));
})->with([
    'subject matter' => ['subject_matter', '/subject-matter/'],
    'clinical specialization' => ['clinical_specializations', '/clinical-specializations/'],
]);

it('gives a routeless entry no @id without a usable slug or a known collection', function (array $returns): void {
    /** @var array<string, mixed> $returns */
    expect(thingHelpersCall('resolveCanonicalIdForEntity', thingHelpersEntry($returns)))->toBeNull();
})->with([
    'subject with an empty slug' => [['collectionHandle' => 'subject_matter', 'slug' => '']],
    'subject with a non-string slug' => [['collectionHandle' => 'subject_matter', 'slug' => 5]],
    'specialization with an empty slug' => [['collectionHandle' => 'clinical_specializations', 'slug' => '']],
    'specialization with a non-string slug' => [['collectionHandle' => 'clinical_specializations', 'slug' => 5]],
    'other collection' => [['collectionHandle' => 'pages', 'slug' => 'heart']],
]);

it('reads an entry contract @id from its absolute URL, then its URL', function (array $returns, ?string $expected): void {
    $entry = Mockery::mock(EntryContract::class);
    foreach ($returns as $method => $value) {
        $entry->shouldReceive($method)->andReturn($value);
    }

    expect(thingHelpersCall('resolveCanonicalIdForEntity', $entry))->toBe($expected);
})->with([
    'absolute URL' => [['absoluteUrl' => 'https://example.test/a', 'url' => '/b'], 'https://example.test/a'],
    'URL' => [['absoluteUrl' => null, 'url' => '/b'], '/b'],
    'empty URLs' => [['absoluteUrl' => '', 'url' => ''], null],
]);

it('reads a term @id from its default-locale URL', function (mixed $url, ?string $expected): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('url')->andReturn($url);
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('inDefaultLocale')->andReturn($localized);

    expect(thingHelpersCall('resolveCanonicalIdForEntity', $term))->toBe($expected === null ? null : thingHelpersTermAtId($expected));
})->with([
    'URL' => ['/topics/a', '/topics/a'],
    'empty' => ['', null],
    'not a string' => [null, null],
]);

it('reads a localized term @id from its URL', function (mixed $url, ?string $expected): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('url')->andReturn($url);

    expect(thingHelpersCall('resolveCanonicalIdForEntity', $localized))->toBe($expected === null ? null : thingHelpersTermAtId($expected));
})->with([
    'URL' => ['https://example.test/topics/b', '/topics/b'],
    'empty' => ['', null],
    'not a string' => [null, null],
]);

it('reads an asset @id from its absolute URL, then its URL', function (mixed $absolute, string $url, ?string $expected): void {
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('absoluteUrl')->andReturn($absolute);
    $asset->shouldReceive('url')->andReturn($url);

    expect(thingHelpersCall('resolveCanonicalIdForEntity', $asset))->toBe($expected)
        ->and(thingHelpersCall('resolveAbsoluteAssetUrl', $asset))->toBe($expected);
})->with([
    'absolute URL' => ['https://example.test/a.jpg', '/a.jpg', 'https://example.test/a.jpg'],
    'URL' => ['', '/a.jpg', '/a.jpg'],
    'non-string absolute URL' => [null, '/a.jpg', '/a.jpg'],
    'no URL' => ['', '', null],
]);

it('sets a node @id from the target, keeping the template @id when the target has none', function (): void {
    $node = ['@id' => 'template', '@type' => 'Thing'];

    expect(thingHelpersCall('mergeTemplateNodeWithTarget', $node, 'https://example.test/t'))->toBe(['@id' => 'https://example.test/t', '@type' => 'Thing'])
        ->and(thingHelpersCall('mergeTemplateNodeWithTarget', $node, null))->toBe($node);
});

// ---
// Entity normalizers
// ---

/**
 * A mocked entry in a collection.
 *
 * @param  string|null  $collection  Collection handle, or null for none.
 */
function thingHelpersEntryIn(?string $collection, string $id = 'e1'): EntryModel&MockInterface
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collection')->andReturn($collection === null
        ? null
        : tap(Mockery::mock(Collection::class), fn ($c) => $c->shouldReceive('handle')->andReturn($collection)));

    return $entry;
}

/**
 * A mocked taxonomy term in a taxonomy.
 */
function thingHelpersTermIn(string $taxonomy): TaxonomyTerm&MockInterface
{
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomy')->andReturn(tap(Mockery::mock(Taxonomy::class), fn ($t) => $t->shouldReceive('handle')->andReturn($taxonomy)));

    return $term;
}

/**
 * A mocked asset in a container.
 */
function thingHelpersAssetIn(string $container): Asset&MockInterface
{
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('container')->andReturn(tap(Mockery::mock(AssetContainer::class), fn ($c) => $c->shouldReceive('handle')->andReturn($container)));

    return $asset;
}

it('matches an entry against a piped collection scope', function (?string $scope, ?string $collection, bool $expected): void {
    expect(thingHelpersCall('entryMatchesCollection', thingHelpersEntryIn($collection), $scope))->toBe($expected);
})->with([
    'no scope' => [null, 'people', true],
    'empty scope' => ['', 'people', true],
    'matching' => ['people', 'people', true],
    'piped, padded' => ['pages | people', 'people', true],
    'other collection' => ['pages', 'people', false],
    'entry without a collection' => ['people', null, false],
    'entry without a collection, scope with an empty piece' => ['people|', null, false],
]);

it('does not match an entry contract that has no collection', function (): void {
    expect(thingHelpersCall('entryMatchesCollection', Mockery::mock(EntryContract::class), 'people'))->toBeFalse();
});

it('normalizes an item to an entry in scope', function (): void {
    $person = thingHelpersEntryIn('people', 'p1');
    Entry::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryModel => $id === 'p1' ? $person : null);

    expect(thingHelpersCall('normalizeToEntry', $person, 'people'))->toBe($person)
        ->and(thingHelpersCall('normalizeToEntry', new Value($person), null))->toBe($person)
        ->and(thingHelpersCall('normalizeToEntry', $person, 'pages'))->toBeNull()
        ->and(thingHelpersCall('normalizeToEntry', 'p1', 'people'))->toBe($person)
        ->and(thingHelpersCall('normalizeToEntry', 'p1', 'pages'))->toBeNull()
        ->and(thingHelpersCall('normalizeToEntry', 'missing', null))->toBeNull()
        ->and(thingHelpersCall('normalizeToEntry', ['id' => 'p1'], 'people'))->toBe($person)
        ->and(thingHelpersCall('normalizeToEntry', ['id' => 'p1'], 'pages'))->toBeNull()
        ->and(thingHelpersCall('normalizeToEntry', ['id' => 'missing'], null))->toBeNull();
});

it('looks up no entry for an unusable item', function (mixed $item): void {
    Entry::shouldReceive('find')->never();

    expect(thingHelpersCall('normalizeToEntry', $item, null))->toBeNull();
})->with([
    'empty string' => [''],
    'array with an empty id' => [['id' => '']],
    'array with a non-string id' => [['id' => 5]],
    'array without an id' => [['slug' => 'x']],
    'integer' => [5],
]);

it('matches a term against a taxonomy', function (?string $taxonomy, bool $expected): void {
    expect(thingHelpersCall('termMatchesTaxonomy', thingHelpersTermIn('tags'), $taxonomy))->toBe($expected);
})->with([
    'no taxonomy' => [null, true],
    'empty taxonomy' => ['', true],
    'matching' => ['tags', true],
    'other' => ['topics', false],
]);

it('finds a taxonomy term by id, unwrapping a localized result', function (): void {
    $term = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => match ($id) {
        'tags::a' => $term,
        'tags::b' => new LocalizedTerm($term, 'default'),
        'tags::c' => new LocalizedTerm(null, 'default'),
        default => null,
    });

    expect(thingHelpersCall('findTaxonomyTermById', 'tags::a'))->toBe($term)
        ->and(thingHelpersCall('findTaxonomyTermById', 'tags::b'))->toBe($term)
        ->and(thingHelpersCall('findTaxonomyTermById', 'tags::c'))->toBeNull()
        ->and(thingHelpersCall('findTaxonomyTermById', 'tags::d'))->toBeNull();
});

it('normalizes an item to a term in a taxonomy', function (): void {
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => \in_array($id, ['tags::one', 'topics::one'], true) && str_starts_with($id, 'tags') ? $tag : null);

    expect(thingHelpersCall('normalizeToTerm', $tag, 'tags'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToTerm', $tag, 'topics'))->toBeNull()
        ->and(thingHelpersCall('normalizeToTerm', new Value(new LocalizedTerm($tag, 'default')), 'tags'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToTerm', new LocalizedTerm($tag, 'default'), 'topics'))->toBeNull()
        ->and(thingHelpersCall('normalizeToTerm', new LocalizedTerm(null, 'default'), null))->toBeNull()
        ->and(thingHelpersCall('normalizeToTerm', ['slug' => 'one'], 'tags'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToTerm', 'one', 'tags'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToTerm', 'tags::one', null))->toBe($tag)
        ->and(thingHelpersCall('normalizeToTerm', 'one', 'topics'))->toBeNull();
});

it('tries each taxonomy of a piped scope in order, skipping blank pieces', function (): void {
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'tags::one' ? $tag : null);

    expect(thingHelpersCall('normalizeToTerm', 'one', 'topics | | tags'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToTerm', $tag, 'topics | | regions'))->toBeNull()
        ->and(thingHelpersCall('normalizeToTerm', 'one', 'topics|'))->toBeNull();
});

it('looks up no term for an unusable item', function (mixed $item, ?string $taxonomy): void {
    Term::shouldReceive('find')->never();

    expect(thingHelpersCall('normalizeToTerm', $item, $taxonomy))->toBeNull();
})->with([
    'slug without a taxonomy' => [['slug' => 'one'], null],
    'slug with an empty taxonomy' => [['slug' => 'one'], ''],
    'empty slug' => [['slug' => ''], 'tags'],
    'non-string slug' => [['slug' => 5], 'tags'],
    'bare slug without a taxonomy' => ['one', null],
    'bare slug with an empty taxonomy' => ['one', ''],
    'empty string' => ['', 'tags'],
    'integer' => [5, 'tags'],
]);

it('matches an asset against a container', function (?string $container, bool $expected): void {
    expect(thingHelpersCall('assetMatchesContainer', thingHelpersAssetIn('images'), $container))->toBe($expected);
})->with([
    'no container' => [null, true],
    'empty container' => ['', true],
    'matching' => ['images', true],
    'other' => ['files', false],
]);

it('resolves a qualified asset id from a stored path', function (): void {
    App\Models\Asset::query()->insert([
        ['container' => 'images', 'folder' => 'a', 'basename' => 'x.jpg', 'filename' => 'x', 'extension' => 'jpg', 'path' => 'a/x.jpg'],
        ['container' => '', 'folder' => 'a', 'basename' => 'y.jpg', 'filename' => 'y', 'extension' => 'jpg', 'path' => 'a/y.jpg'],
    ]);

    expect(thingHelpersCall('resolveQualifiedAssetIdFromPath', 'a/x.jpg'))->toBe('images::a/x.jpg')
        ->and(thingHelpersCall('resolveQualifiedAssetIdFromPath', 'a/y.jpg'))->toBeNull()
        ->and(thingHelpersCall('resolveQualifiedAssetIdFromPath', 'a/z.jpg'))->toBeNull();
});

it('normalizes an item to an asset in a container', function (): void {
    $image = thingHelpersAssetIn('images');
    App\Models\Asset::query()->insert([
        ['container' => 'images', 'folder' => 'a', 'basename' => 'x.jpg', 'filename' => 'x', 'extension' => 'jpg', 'path' => 'a/x.jpg'],
        // A metadata row whose asset no longer exists
        ['container' => 'images', 'folder' => 'a', 'basename' => 'stale.jpg', 'filename' => 'stale', 'extension' => 'jpg', 'path' => 'a/stale.jpg'],
    ]);
    Statamic\Facades\Asset::shouldReceive('find')->andReturnUsing(fn (string $id): ?Asset => \in_array($id, ['images::a/x.jpg', 'direct'], true) ? $image : null);

    expect(thingHelpersCall('normalizeToAsset', $image, 'images'))->toBe($image)
        ->and(thingHelpersCall('normalizeToAsset', new Value($image), null))->toBe($image)
        ->and(thingHelpersCall('normalizeToAsset', $image, 'files'))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', 'direct', 'images'))->toBe($image)
        ->and(thingHelpersCall('normalizeToAsset', 'direct', 'files'))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', 'a/x.jpg', 'images'))->toBe($image)
        ->and(thingHelpersCall('normalizeToAsset', 'a/x.jpg', 'files'))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', 'a/missing.jpg', null))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', 'a/stale.jpg', null))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', 'images::a/x.jpg-not', null))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', '', null))->toBeNull()
        ->and(thingHelpersCall('normalizeToAsset', 5, null))->toBeNull();
});

it('does not resolve a qualified id for an asset id that names its container', function (): void {
    App\Models\Asset::query()->insert(['container' => 'images', 'folder' => 'x', 'basename' => 'y', 'filename' => 'y', 'extension' => 'jpg', 'path' => 'x::y']);
    Statamic\Facades\Asset::shouldReceive('find')->once()->with('x::y')->andReturnNull();

    expect(thingHelpersCall('normalizeToAsset', 'x::y', null))->toBeNull();
});

it('reads no asset when the metadata table cannot be queried', function (): void {
    Schema::connection('asset_metadata')->drop('assets_meta');
    Statamic\Facades\Asset::shouldReceive('find')->once()->andReturnNull();

    expect(thingHelpersCall('normalizeToAsset', 'a/x.jpg', null))->toBeNull();
});

it('normalizes an item to global variables', function (): void {
    $variables = (new Variables)->globalSet('settings');
    $set = Mockery::mock(GlobalSet::class);
    $set->shouldReceive('inCurrentSite')->andReturn($variables);
    $empty = Mockery::mock(GlobalSet::class);
    $empty->shouldReceive('inCurrentSite')->andReturnNull();
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->andReturnUsing(fn (string $handle): mixed => match ($handle) {
        'settings' => $set,
        'empty' => $empty,
        default => null,
    });

    expect(thingHelpersCall('normalizeToGlobalVariables', $variables, 'other'))->toBe($variables)
        ->and(thingHelpersCall('normalizeToGlobalVariables', 'ignored', 'settings'))->toBe($variables)
        ->and(thingHelpersCall('normalizeToGlobalVariables', 'settings', ''))->toBe($variables)
        ->and(thingHelpersCall('normalizeToGlobalVariables', 'settings', null))->toBe($variables)
        ->and(thingHelpersCall('normalizeToGlobalVariables', 'missing', null))->toBeNull()
        ->and(thingHelpersCall('normalizeToGlobalVariables', 'empty', null))->toBeNull();
});

it('looks up no global set without a handle', function (mixed $item, ?string $handle): void {
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->never();

    expect(thingHelpersCall('normalizeToGlobalVariables', $item, $handle))->toBeNull();
})->with([
    'nothing' => [null, null],
    'empty item' => ['', null],
    'empty both' => ['', ''],
    'integer item' => [5, null],
]);

it('normalizes an item by context type', function (): void {
    $variables = (new Variables)->globalSet('settings');

    expect(thingHelpersCall('normalizeToEntityByContextType', $variables, 'globals', null))->toBe($variables)
        ->and(thingHelpersCall('normalizeToEntityByContextType', $variables, 'other', null))->toBeNull();
});

it('normalizes an item to whichever entity it names', function (): void {
    $person = thingHelpersEntryIn('people', 'p1');
    $image = thingHelpersAssetIn('images');
    $tag = thingHelpersTermIn('tags');
    Entry::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryModel => $id === 'p1' ? $person : null);
    Statamic\Facades\Asset::shouldReceive('find')->andReturnUsing(fn (string $id): ?Asset => $id === 'images::i1' ? $image : null);
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'tags::t1' ? $tag : null);

    $variables = (new Variables)->globalSet('settings');
    $localized = new LocalizedTerm($tag, 'default');

    expect(thingHelpersCall('normalizeToAnySupportedEntity', $person, 'pages'))->toBe($person)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', $tag, 'pages'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', $localized, 'pages'))->toBe($localized)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', $image, 'pages'))->toBe($image)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', $variables, 'pages'))->toBe($variables)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', 'p1'))->toBe($person)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', 'images::i1'))->toBe($image)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', 'tags::t1'))->toBe($tag)
        ->and(thingHelpersCall('normalizeToAnySupportedEntity', 'nothing::here'))->toBeNull();
});

it('unwraps the taxonomy term behind a target', function (): void {
    $tag = thingHelpersTermIn('tags');

    expect(thingHelpersCall('unwrapTaxonomyTermFromTargetEntity', $tag))->toBe($tag)
        ->and(thingHelpersCall('unwrapTaxonomyTermFromTargetEntity', new LocalizedTerm($tag, 'default')))->toBe($tag)
        ->and(thingHelpersCall('unwrapTaxonomyTermFromTargetEntity', new LocalizedTerm(null, 'default')))->toBeNull()
        ->and(thingHelpersCall('unwrapTaxonomyTermFromTargetEntity', 'x'))->toBeNull();
});

// ---
// Context entity
// ---

/**
 * Bind a request for a URL whose route carries a `data` parameter.
 */
function thingHelpersBindRequest(mixed $routeData, string $url = 'https://example.test/page'): void
{
    $request = Request::create($url);
    $route = new Route('GET', '/page', []);
    $route->bind($request);
    if ($routeData !== null) {
        /** @var object|string $routeData */
        $route->setParameter('data', $routeData);
    }
    $request->setRouteResolver(fn (): Route => $route);
    app()->instance('request', $request);
}

it('resolves the context entity from the route', function (Closure $routeData, Closure $expected): void {
    $data = $routeData();
    thingHelpersBindRequest($data);
    Data::shouldReceive('findByRequestUrl')->never();

    expect(thingHelpersCall('resolveContextEntityFromCurrentRequest'))->toBe($expected($data));
})->with([
    'entry' => [fn (): EntryModel => thingHelpersEntryIn('pages'), fn (mixed $d): mixed => $d],
    'taxonomy term' => [fn (): TaxonomyTerm => thingHelpersTermIn('tags'), fn (mixed $d): mixed => $d],
    'localized term' => [fn (): LocalizedTerm => new LocalizedTerm(thingHelpersTermIn('tags'), 'default'), fn (LocalizedTerm $d): mixed => $d->term()],
    'localized term without a term' => [fn (): LocalizedTerm => new LocalizedTerm(null, 'default'), fn (): mixed => null],
]);

it('resolves the context entity from the request URL when the route has none', function (Closure $resolved, Closure $expected): void {
    thingHelpersBindRequest(null, 'https://example.test/about');
    $data = $resolved();
    $page = thingHelpersEntryIn('pages', 'page-entry');
    Data::shouldReceive('findByRequestUrl')->once()->with('https://example.test/about')->andReturn($data);
    Data::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'page-entry' ? $page : null);

    expect(thingHelpersCall('resolveContextEntityFromCurrentRequest'))->toBe($expected($data, $page));
})->with([
    'entry' => [fn (): EntryModel => thingHelpersEntryIn('pages'), fn (mixed $d): mixed => $d],
    'taxonomy term' => [fn (): TaxonomyTerm => thingHelpersTermIn('tags'), fn (mixed $d): mixed => $d],
    'localized term' => [fn (): LocalizedTerm => new LocalizedTerm(thingHelpersTermIn('tags'), 'default'), fn (LocalizedTerm $d): mixed => $d->term()],
    'localized term without a term' => [fn (): LocalizedTerm => new LocalizedTerm(null, 'default'), fn (): mixed => null],
    'structure page' => [fn (): Page => tap(Mockery::mock(Page::class), fn ($p) => $p->shouldReceive('id')->andReturn('page-entry')), fn (mixed $d, EntryModel $page): mixed => $page],
    'structure page whose entry is missing' => [fn (): Page => tap(Mockery::mock(Page::class), fn ($p) => $p->shouldReceive('id')->andReturn('gone')), fn (): mixed => null],
    'structure page with a non-scalar id' => [fn (): Page => tap(Mockery::mock(Page::class), fn ($p) => $p->shouldReceive('id')->andReturn(['x'])), fn (): mixed => null],
    'nothing' => [fn (): mixed => null, fn (): mixed => null],
]);

it('resolves the context entity from the context id', function (Closure $found, Closure $expected): void {
    $data = $found();
    Data::shouldReceive('find')->once()->with('ctx-id')->andReturn($data);
    Data::shouldReceive('findByRequestUrl')->never();

    expect(thingHelpersCall('resolveContextEntity', ['id' => 'ctx-id']))->toBe($expected($data));
})->with([
    'entry' => [fn (): EntryModel => thingHelpersEntryIn('pages'), fn (mixed $d): mixed => $d],
    'taxonomy term' => [fn (): TaxonomyTerm => thingHelpersTermIn('tags'), fn (mixed $d): mixed => $d],
    'localized term' => [fn (): LocalizedTerm => new LocalizedTerm(thingHelpersTermIn('tags'), 'default'), fn (LocalizedTerm $d): mixed => $d->term()],
    'localized term without a term' => [fn (): LocalizedTerm => new LocalizedTerm(null, 'default'), fn (): mixed => null],
]);

it('falls back to the request when the context id finds nothing usable', function (): void {
    $routed = thingHelpersEntryIn('pages', 'routed');
    thingHelpersBindRequest($routed);
    Data::shouldReceive('find')->once()->with('ctx-id')->andReturn((new Variables)->globalSet('settings'));

    expect(thingHelpersCall('resolveContextEntity', ['id' => 'ctx-id']))->toBe($routed);
});

it('uses the context entry, then the request, when the context has no usable id', function (array $context, bool $usesEntry): void {
    $routed = thingHelpersEntryIn('pages', 'routed');
    $entry = thingHelpersEntryIn('pages', 'ctx');
    thingHelpersBindRequest($routed);
    Data::shouldReceive('find')->never();

    $context = array_map(fn (mixed $v): mixed => $v === 'ENTRY' ? $entry : $v, $context);

    expect(thingHelpersCall('resolveContextEntity', $context))->toBe($usesEntry ? $entry : $routed);
})->with([
    'no id, an entry' => [['entry' => 'ENTRY'], true],
    'empty id, an entry' => [['id' => '', 'entry' => 'ENTRY'], true],
    'non-string id, an entry' => [['id' => 5, 'entry' => 'ENTRY'], true],
    'no id, an entry that is not one' => [['entry' => 'not an entry'], false],
    'nothing' => [[], false],
]);

it('treats a target as the context entity when it is the same instance or has the same id', function (Closure $target, Closure $context, bool $expected): void {
    $shared = thingHelpersEntryIn('pages', 'same');

    expect(thingHelpersCall('targetEntityIsContextEntity', $target($shared), $context($shared)))->toBe($expected);
})->with([
    'same instance' => [fn (EntryModel $s): mixed => $s, fn (EntryModel $s): mixed => $s, true],
    'same instance with an empty id' => [fn (): mixed => $GLOBALS['thingEmptyIdEntry'] ??= thingHelpersEntryIn('pages', ''), fn (): mixed => $GLOBALS['thingEmptyIdEntry'], true],
    'same id' => [fn (): mixed => thingHelpersEntryIn('pages', 'x'), fn (): mixed => thingHelpersEntryIn('people', 'x'), true],
    'different id' => [fn (): mixed => thingHelpersEntryIn('pages', 'x'), fn (): mixed => thingHelpersEntryIn('pages', 'y'), false],
    'empty ids' => [fn (): mixed => thingHelpersEntryIn('pages', ''), fn (): mixed => thingHelpersEntryIn('pages', ''), false],
    'no context' => [fn (EntryModel $s): mixed => $s, fn (): mixed => null, false],
    'target not an object' => [fn (): mixed => 'same', fn (EntryModel $s): mixed => $s, false],
    'target without an id' => [fn (): mixed => new stdClass, fn (EntryModel $s): mixed => $s, false],
    'context without an id' => [fn (EntryModel $s): mixed => $s, fn (): mixed => Mockery::mock(TermContract::class), false],
]);

it('reads the source site handle from an entry or a term', function (Closure $entity, ?string $expected): void {
    expect(thingHelpersCall('resolveSourceSiteHandle', $entity()))->toBe($expected);
})->with([
    'entry' => [fn (): EntryContract => thingHelpersSited(EntryModel::class, 'site', 'health'), 'health'],
    'entry with an empty handle' => [fn (): EntryContract => thingHelpersSited(EntryModel::class, 'site', ''), null],
    'entry with a non-string handle' => [fn (): EntryContract => thingHelpersSited(EntryModel::class, 'site', 5), null],
    'entry without a site' => [fn (): EntryContract => tap(Mockery::mock(EntryModel::class), fn ($e) => $e->shouldReceive('site')->andReturnNull()), null],
    'entry contract without site()' => [fn (): EntryContract => Mockery::mock(EntryContract::class), null],
    'term' => [fn (): TermContract => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(thingHelpersSited(LocalizedTerm::class, 'site', 'research'))), 'research'],
    'term with an empty handle' => [fn (): TermContract => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(thingHelpersSited(LocalizedTerm::class, 'site', ''))), null],
    'term with a non-string handle' => [fn (): TermContract => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(thingHelpersSited(LocalizedTerm::class, 'site', 5))), null],
    'term whose localization has no site' => [fn (): TermContract => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(tap(Mockery::mock(LocalizedTerm::class), fn ($l) => $l->shouldReceive('site')->andReturnNull()))), null],
    'term without a localization' => [fn (): TermContract => tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturnNull()), null],
    'term contract without inDefaultLocale()' => [fn (): TermContract => Mockery::mock(TermContract::class), null],
]);

/**
 * A mock whose site accessor returns a site with the given handle.
 *
 * @template T of object
 *
 * @param  class-string<T>  $class  Class to mock.
 * @return T&MockInterface
 */
function thingHelpersSited(string $class, string $method, mixed $handle): object
{
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('handle')->andReturn($handle);

    /** @var T&MockInterface $mock */
    $mock = Mockery::mock($class);
    $mock->shouldReceive($method)->andReturn($site);

    return $mock;
}

// ---
// Template context and template id
// ---

/**
 * Build the resolver over the given collaborators.
 */
function thingHelpersResolverWith(?SchemaOutputService $output = null, ?SchemaTemplatesCascadeService $cascade = null): SchemaThingFromRelationshipResolver
{
    if ($cascade === null) {
        $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
        $cascade->shouldIgnoreMissing();
    }

    return new SchemaThingFromRelationshipResolver(
        $output ?? Mockery::mock(SchemaOutputService::class),
        $cascade,
        new SchemaJsonLdAtIdDeduplication
    );
}

/**
 * Call a private method on a given resolver.
 */
function thingHelpersCallOn(SchemaThingFromRelationshipResolver $resolver, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaThingFromRelationshipResolver::class, $method))->invoke($resolver, ...$args);
}

it('builds an entry template context from the flattened schema context plus the entry', function (): void {
    $entry = thingHelpersEntryIn('people');
    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->once()->with($entry)->andReturn(['title' => 'T', 'entry' => 'replaced']);

    expect(thingHelpersCallOn(thingHelpersResolverWith($output), 'buildTemplateContextForEntity', $entry))->toBe(['title' => 'T', 'entry' => $entry]);
});

it('builds a term template context from its default-locale data plus the term', function (): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('data')->andReturn(collect(['title' => 'T', 'term' => 'replaced']));
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('inDefaultLocale')->andReturn($localized);

    $missing = Mockery::mock(TaxonomyTerm::class);
    $missing->shouldReceive('inDefaultLocale')->andReturnNull();

    expect(thingHelpersCall('buildTemplateContextForEntity', $term))->toBe(['title' => 'T', 'term' => $localized])
        ->and(thingHelpersCall('buildTemplateContextForEntity', $missing))->toBe(['term' => null])
        ->and(thingHelpersCall('buildTemplateContextForEntity', $localized))->toBe(['title' => 'T', 'term' => $localized]);
});

it('builds a global variables template context from its values and the set', function (): void {
    // The values are read through data(), and `global` is assigned last
    $variables = tap(new Variables, fn (Variables $v): mixed => $v->globalSet('settings'));
    $variables->set('phone', '555');

    expect(thingHelpersCall('buildTemplateContextForEntity', $variables))->toBe(['phone' => '555', 'global' => $variables]);
});

it('builds no template context for an unsupported entity', function (): void {
    expect(thingHelpersCall('buildTemplateContextForEntity', 'x'))->toBe([])
        ->and(thingHelpersCall('buildTemplateContextForEntity', null))->toBe([]);
});

it('evaluates a target template against the target entry or term, or the page context', function (Closure $target, Closure $expected): void {
    $context = thingHelpersEntryIn('pages', 'page');
    $value = $target();

    expect(thingHelpersCall('resolveTemplateContextEntityForTarget', $value, $context))->toBe($expected($value, $context));
})->with([
    'asset' => [fn (): Asset => new Asset, fn (): mixed => null],
    'entry' => [fn (): EntryModel => thingHelpersEntryIn('people'), fn (mixed $t): mixed => $t],
    'taxonomy term' => [fn (): TaxonomyTerm => thingHelpersTermIn('tags'), fn (mixed $t): mixed => $t],
    'localized term' => [fn (): LocalizedTerm => new LocalizedTerm(thingHelpersTermIn('tags'), 'default'), fn (LocalizedTerm $t): mixed => $t->term()],
    'localized term without a term' => [fn (): LocalizedTerm => new LocalizedTerm(null, 'default'), fn (mixed $t, mixed $c): mixed => $c],
    'URL string' => [fn (): string => 'https://example.test/x', fn (mixed $t, mixed $c): mixed => $c],
]);

it('resolves a target template id from the cascade, main content first', function (array $resolved, ?string $expected): void {
    $entry = thingHelpersEntryIn('people');
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->once()->with($entry)->andReturn($resolved + [
        'enabled' => true,
        'main_content_entry' => null,
        'subject_entry' => null,
        'webpage_entry' => null,
        'website_entry' => null,
    ]);

    expect(thingHelpersCallOn(thingHelpersResolverWith(null, $cascade), 'resolveTemplateIdForTarget', $entry))->toBe($expected);
})->with([
    'main content' => [['main_content_entry' => 'm', 'subject_entry' => 's', 'webpage_entry' => 'p', 'website_entry' => 'w'], 'm'],
    'subject' => [['main_content_entry' => '', 'subject_entry' => 's', 'webpage_entry' => 'p', 'website_entry' => 'w'], 's'],
    'webpage' => [['subject_entry' => '', 'webpage_entry' => 'p', 'website_entry' => 'w'], 'p'],
    'website' => [['webpage_entry' => 5, 'website_entry' => 'w'], 'w'],
    'none' => [[], null],
]);

it('resolves no template id for a target without an entry or term', function (): void {
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->never();

    expect(thingHelpersCallOn(thingHelpersResolverWith(null, $cascade), 'resolveTemplateIdForTarget', new Asset))->toBeNull();
});

// ---
// Source context and asset context
// ---

it('merges the source entry or term and its site into a target context', function (): void {
    $entry = thingHelpersSited(EntryModel::class, 'site', 'health');
    $term = tap(Mockery::mock(TaxonomyTerm::class), fn ($t) => $t->shouldReceive('inDefaultLocale')->andReturn(thingHelpersSited(LocalizedTerm::class, 'site', 'research')));
    $withoutSite = tap(Mockery::mock(EntryModel::class), fn ($e) => $e->shouldReceive('site')->andReturnNull());

    expect(thingHelpersCall('mergeSourceContextIntoTemplateContext', ['title' => 'T', 'source_site' => 'kept?'], null))->toBe(['title' => 'T', 'source_site' => 'kept?'])
        ->and(thingHelpersCall('mergeSourceContextIntoTemplateContext', ['title' => 'T', 'source_site' => 'replaced'], $entry))->toBe(['title' => 'T', 'source_site' => 'health', 'source_entry' => $entry])
        ->and(thingHelpersCall('mergeSourceContextIntoTemplateContext', ['title' => 'T'], $term))->toBe(['title' => 'T', 'source_term' => $term, 'source_site' => 'research'])
        ->and(thingHelpersCall('mergeSourceContextIntoTemplateContext', ['title' => 'T'], $withoutSite))->toBe(['title' => 'T', 'source_entry' => $withoutSite]);
});

it('prepares a target build context: target fields, then the source context, then row overrides', function (): void {
    $target = thingHelpersEntryIn('people', 'target');
    $source = thingHelpersSited(EntryModel::class, 'site', 'health');
    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturn(['title' => 'T', 'source_site' => 'own']);
    $resolver = thingHelpersResolverWith($output);

    expect(thingHelpersCallOn($resolver, 'prepareTargetTemplateContextForBuild', $target, ['title' => 'Row'], true, $source))->toBe([
        'context' => ['title' => 'Row', 'source_site' => 'health', 'entry' => $target, 'source_entry' => $source],
        'entity' => $target,
    ])->and(thingHelpersCallOn($resolver, 'prepareTargetTemplateContextForBuild', $target, [], false, $source))->toBe([
        'context' => ['title' => 'T', 'source_site' => 'own', 'entry' => $target],
        'entity' => $target,
    ]);
});

/**
 * A mocked asset with fixed data, augmented values and metadata.
 *
 * @param  array<string, mixed>  $returns  Method return values; a Throwable is thrown instead.
 */
function thingHelpersAsset(array $returns): Asset
{
    $asset = Mockery::mock(Asset::class);
    foreach ($returns as $method => $value) {
        $expectation = $asset->shouldReceive($method);
        $value instanceof Throwable ? $expectation->andThrow($value) : $expectation->andReturn($value);
    }

    return $asset;
}

it('builds an asset template context from its data, augmented values and metadata', function (): void {
    $augmented = Mockery::mock(Augmented::class);
    // The unusable keys come first, so each is skipped rather than ending the merge
    $augmented->shouldReceive('all')->andReturn(new AugmentedCollection([
        5 => 'dropped',
        '' => 'dropped',
        'alt' => new Value('Alt text'),
        'credit' => 'Photographer',
    ]));

    $asset = thingHelpersAsset([
        'data' => collect(['alt' => 'raw', 'caption' => 'Cap']),
        'augmented' => $augmented,
        'size' => 10,
        'lastModified' => 'then',
        'width' => 3,
        'height' => 4,
        'mimeType' => 'image/png',
        'duration' => new RuntimeException('not audio'),
        'bitrate' => null,
        'absoluteUrl' => 'https://example.test/a.png',
    ]);

    expect(thingHelpersCall('buildTemplateContextForAsset', $asset))->toBe([
        'alt' => 'Alt text',
        'caption' => 'Cap',
        'credit' => 'Photographer',
        'size' => 10,
        'last_modified' => 'then',
        'width' => 3,
        'height' => 4,
        'mime_type' => 'image/png',
        'bitrate' => null,
        'asset' => $asset,
        'absolute_url' => 'https://example.test/a.png',
        'url' => null,
        'permalink' => 'https://example.test/a.png',
    ]);
});

it('builds an asset context without a permalink when it has no absolute URL, and survives unreadable data', function (mixed $absolute): void {
    $asset = thingHelpersAsset([
        'data' => new RuntimeException('no data'),
        'augmented' => new RuntimeException('no augmentation'),
        'size' => 1,
        'lastModified' => 1,
        'width' => 1,
        'height' => 1,
        'mimeType' => 1,
        'duration' => 1,
        'bitrate' => 1,
        'absoluteUrl' => $absolute,
    ]);

    /** @var array<string, mixed> $context */
    $context = thingHelpersCall('buildTemplateContextForAsset', $asset);

    expect(array_keys($context))->toBe(['size', 'last_modified', 'width', 'height', 'mime_type', 'duration', 'bitrate', 'asset', 'absolute_url', 'url', 'permalink'])
        ->and($context['permalink'])->toBeNull();
})->with([
    'empty' => [''],
    'null' => [null],
]);

it('merges augmented asset values given as an array, or keeps the context when they cannot be read', function (): void {
    $asArray = Mockery::mock(Augmented::class);
    $asArray->shouldReceive('all')->andReturn(['credit' => new Value('P')]);
    $failing = Mockery::mock(Augmented::class);
    $failing->shouldReceive('all')->andThrow(new RuntimeException('broken'));
    $scalar = Mockery::mock(Augmented::class);
    $scalar->shouldReceive('all')->andReturn('nothing');
    $object = Mockery::mock(Augmented::class);
    $object->shouldReceive('all')->andReturn(new stdClass);

    expect(thingHelpersCall('mergeAssetAugmentedFieldsIntoContext', thingHelpersAsset(['augmented' => $asArray]), ['a' => 1]))->toBe(['a' => 1, 'credit' => 'P'])
        ->and(thingHelpersCall('mergeAssetAugmentedFieldsIntoContext', thingHelpersAsset(['augmented' => $failing]), ['a' => 1]))->toBe(['a' => 1])
        ->and(thingHelpersCall('mergeAssetAugmentedFieldsIntoContext', thingHelpersAsset(['augmented' => $scalar]), ['a' => 1]))->toBe(['a' => 1])
        ->and(thingHelpersCall('mergeAssetAugmentedFieldsIntoContext', thingHelpersAsset(['augmented' => $object]), ['a' => 1]))->toBe(['a' => 1]);
});

it('builds an asset entity template context through the asset builder', function (): void {
    $asset = thingHelpersAsset([
        'data' => collect(['caption' => 'Cap']),
        'augmented' => new RuntimeException('none'),
        'size' => 1, 'lastModified' => 1, 'width' => 1, 'height' => 1, 'mimeType' => 1, 'duration' => 1, 'bitrate' => 1,
        'absoluteUrl' => 'https://example.test/x',
    ]);

    /** @var array<string, mixed> $context */
    $context = thingHelpersCall('buildTemplateContextForEntity', $asset);

    expect($context['caption'])->toBe('Cap');
});

// ---
// Direct mode collection
// ---

/**
 * Collect direct-mode targets through a resolver over mocked collaborators.
 *
 * @param  array<string, mixed>  $context  Antlers context.
 * @param  array<string, mixed>  $params  Tag parameters.
 * @return array<int, mixed>
 */
function thingHelpersDirect(array $context, array $params, EntryContract|TermContract|null $contextEntity = null): array
{
    /** @var array<int, mixed> $targets */
    $targets = thingHelpersCall('collectTargetsForDirectMode', $context, $params, $contextEntity);

    return $targets;
}

/**
 * A real, unsaved entry with an id and stored field data.
 *
 * @param  array<string, mixed>  $data  Stored field data.
 */
function thingHelpersRealEntry(string $id, array $data): EntryModel
{
    $entry = new EntryModel;
    $entry->id($id);
    $entry->collection('pages');
    $entry->data($data);

    return $entry;
}

it('collects nothing in direct mode without a current field', function (): void {
    expect(thingHelpersDirect(['about' => ['x']], []))->toBe([]);
});

it('collects direct targets from the context field, capped at the current limit', function (mixed $limit, int $count): void {
    $first = thingHelpersTermIn('tags');
    $second = thingHelpersEntryIn('people');

    $targets = thingHelpersDirect(['about' => [$first, $second], 'other' => ['x']], ['current_field' => 'missing|about', 'current_limit' => $limit]);

    expect($targets)->toBe(\array_slice([$first, $second], 0, $count));
})->with([
    'no limit' => [null, 2],
    'one' => [1, 1],
    'digit string' => ['1', 1],
    'zero is no limit' => [0, 2],
]);

it('normalizes bare direct items in the direct scope', function (mixed $scope, bool $resolves): void {
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'tags::one' ? $tag : null);
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();

    expect(thingHelpersDirect(['about' => ['one']], ['current_field' => 'about', 'direct_scope' => $scope]))->toBe($resolves ? [$tag] : []);
})->with([
    'padded scope' => [' tags ', true],
    'no scope' => [null, false],
    'blank scope' => ['  ', false],
    'non-string scope' => [5, false],
]);

it('keeps unresolved replicator rows that carry their own template context', function (): void {
    $person = thingHelpersEntryIn('people');
    $rows = [
        ['type' => 'clip', 'value' => ['url' => 'https://example.test/v']],
        ['type' => 'people', 'value' => ['people' => [$person]]],
    ];

    /** @var list<array{entity: mixed, template_context: array<string, mixed>}> $rowOnly */
    $rowOnly = thingHelpersDirect(['segments' => $rows], ['current_field' => 'segments', 'current_field_replicator' => 'true']);
    /** @var list<array{entity: mixed, template_context: array<string, mixed>}> $related */
    $related = thingHelpersDirect(['segments' => $rows], ['current_field' => 'segments', 'current_field_replicator' => true, 'current_field_replicator_relationship_field' => 'people']);

    expect($rowOnly)->toHaveCount(2)
        ->and($rowOnly[0]['entity'])->toBe('https://example.test/v')
        ->and($rowOnly[0]['template_context']['replicator_row_type'])->toBe('clip')
        ->and($rowOnly[1]['entity'])->toBeNull()
        ->and($related)->toHaveCount(1)
        ->and($related[0]['entity'])->toBe($person)
        ->and($related[0]['template_context']['replicator_row_position'])->toBe(2);
});

it('drops direct items that resolve to nothing and carry no template context', function (): void {
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    Term::shouldReceive('find')->andReturnNull();

    expect(thingHelpersCall('collectDirectTargetsFromExpandedItems', ['missing', ['entity' => 'gone'], ['entity' => 'gone', 'template_context' => 'x']], null))->toBe([]);
});

it('wraps a resolved direct item with its template context, or passes it bare without one', function (): void {
    $person = thingHelpersEntryIn('people');

    expect(thingHelpersCall('collectDirectTargetsFromExpandedItems', [
        ['entity' => $person, 'template_context' => ['k' => 'v']],
        ['entity' => $person, 'template_context' => []],
        $person,
    ], null))->toBe([
        ['entity' => $person, 'template_context' => ['k' => 'v']],
        $person,
        $person,
    ]);
});

it('reads the direct field from the stored entry the context names, not the context bag', function (array $anchor, bool $authoritative): void {
    $stored = thingHelpersTermIn('tags');
    $ambient = thingHelpersTermIn('topics');
    $entry = thingHelpersRealEntry('e1', ['about' => [$stored]]);

    $context = ['about' => [$ambient]] + array_map(fn (mixed $v): mixed => $v === 'ENTRY' ? $entry : $v, $anchor);

    expect(thingHelpersDirect($context, ['current_field' => 'about'], $entry))->toBe($authoritative ? [$stored] : [$ambient]);
})->with([
    'context id' => [['id' => 'e1'], true],
    'context entry' => [['entry' => 'ENTRY'], true],
    'empty context id, context entry' => [['id' => '', 'entry' => 'ENTRY'], true],
    'other context id' => [['id' => 'e2'], false],
    'no anchor' => [[], false],
]);

it('keeps an empty stored entry field authoritative over the context bag', function (): void {
    $entry = thingHelpersRealEntry('e1', []);

    expect(thingHelpersDirect(['id' => 'e1', 'about' => [thingHelpersTermIn('tags')]], ['current_field' => 'about'], $entry))->toBe([]);
});

it('reads the context bag when the anchor or the context entity is a test double, or a handle names an Antlers form', function (Closure $build): void {
    /** @var array{0: array<string, mixed>, 1: array<string, mixed>, 2: EntryContract|TermContract|null, 3: mixed} $built */
    $built = $build();
    [$context, $params, $entity, $expected] = $built;

    expect(thingHelpersDirect($context, $params, $entity))->toBe($expected);
})->with([
    'context entry is a double' => [function (): array {
        $ambient = thingHelpersTermIn('topics');
        $double = thingHelpersEntryIn('pages', 'e1');

        return [['entry' => $double, 'about' => [$ambient]], ['current_field' => 'about'], thingHelpersRealEntry('e1', ['about' => [thingHelpersTermIn('tags')]]), [$ambient]];
    }],
    'context entity is a double' => [function (): array {
        $ambient = thingHelpersTermIn('topics');

        return [['id' => 'e1', 'about' => [$ambient]], ['current_field' => 'about'], thingHelpersEntryIn('pages', 'e1'), [$ambient]];
    }],
    'replicator_row handle' => [function (): array {
        $ambient = thingHelpersTermIn('topics');

        return [['id' => 'e1', 'replicator_row' => ['about' => [$ambient]]], ['current_field' => 'missing|replicator_row.about'], thingHelpersRealEntry('e1', ['about' => [thingHelpersTermIn('tags')]]), [$ambient]];
    }],
    'source_entry handle' => [function (): array {
        $fromSource = thingHelpersTermIn('topics');
        $source = tap(Mockery::mock(EntryModel::class), fn ($e) => $e->shouldReceive('value')->andReturnUsing(fn (string $h): mixed => $h === 'about' ? [$fromSource] : null));

        return [['id' => 'e1', 'source_entry' => $source], ['current_field' => 'source_entry.about'], thingHelpersRealEntry('e1', ['about' => [thingHelpersTermIn('tags')]]), [$fromSource]];
    }],
]);

it('falls back to the source entry field when the context resolves no targets', function (): void {
    $first = thingHelpersTermIn('tags');
    $second = thingHelpersTermIn('tags');
    $source = Mockery::mock(EntryModel::class);
    $source->shouldReceive('value')->andReturnUsing(fn (string $h): mixed => $h === 'about' ? [$first, $second] : null);

    expect(thingHelpersDirect(['about' => collect(), 'source_entry' => $source], ['current_field' => 'about']))->toBe([$first, $second])
        ->and(thingHelpersDirect(['source_entry' => $source], ['current_field' => 'about', 'current_limit' => 1]))->toBe([$first])
        ->and(thingHelpersDirect(['source_entry' => $source], ['current_field' => 'source_entry.missing']))->toBe([])
        ->and(thingHelpersDirect(['source_entry' => 'not an entry'], ['current_field' => 'about']))->toBe([]);
});

it('falls back to replicator rows on the source entry field', function (): void {
    $person = thingHelpersEntryIn('people');
    $source = Mockery::mock(EntryModel::class);
    $source->shouldReceive('value')->andReturnUsing(fn (string $h): mixed => $h === 'segments' ? [['type' => 'p', 'value' => ['people' => [$person]]]] : null);

    /** @var list<array{entity: mixed, template_context: array<string, mixed>}> $targets */
    $targets = thingHelpersDirect(['source_entry' => $source], ['current_field' => 'segments', 'current_field_replicator' => true, 'current_field_replicator_relationship_field' => 'people']);

    expect($targets)->toHaveCount(1)->and($targets[0]['entity'])->toBe($person);
});

// ---
// Secondary mode collection
// ---

/**
 * Collect secondary-mode targets.
 *
 * @param  array<string, mixed>  $context  Antlers context.
 * @param  array<string, mixed>  $params  Tag parameters.
 * @return array<int, mixed>
 */
function thingHelpersSecondary(array $context, array $params): array
{
    /** @var array<int, mixed> $targets */
    $targets = thingHelpersCall('collectTargetsForSecondaryMode', $context, $params);

    return $targets;
}

/**
 * A mocked entry in a collection whose fields return fixed values.
 *
 * @param  array<string, mixed>  $values  Field values by handle.
 */
function thingHelpersEntryWith(string $collection, array $values, string $id = 'e'): EntryModel
{
    $entry = thingHelpersEntryIn($collection, $id);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $h): mixed => $values[$h] ?? null);

    return $entry;
}

it('collects nothing in secondary mode without a usable configuration', function (array $params): void {
    /** @var array<string, mixed> $params */
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->never();

    expect(thingHelpersSecondary(['locations' => [thingHelpersEntryWith('locations', ['org' => [thingHelpersTermIn('tags')]])]], $params))->toBe([]);
})->with([
    'no secondary context' => [['current_field' => 'locations', 'secondary_field' => 'org']],
    'unknown secondary context' => [['secondary_context' => 'pages', 'current_field' => 'locations', 'secondary_field' => 'org']],
    'no secondary field' => [['secondary_context' => 'entries', 'current_field' => 'locations']],
    'no current field' => [['secondary_context' => 'entries', 'secondary_field' => 'org']],
    'global handle without a global field' => [['secondary_context' => 'entries', 'secondary_field' => 'org', 'current_field' => 'locations', 'global_handle' => 'settings']],
]);

it('collects the secondary field of each entity the current field links, capped at both limits', function (array $limits, int $first, int $second): void {
    /** @var array<string, mixed> $limits */
    $targets = [[thingHelpersTermIn('a'), thingHelpersTermIn('b')], [thingHelpersTermIn('c'), thingHelpersTermIn('d')]];
    $locations = [
        thingHelpersEntryWith('locations', ['org' => $targets[0]], 'l1'),
        'not an entity',
        thingHelpersEntryWith('locations', ['org' => $targets[1]], 'l2'),
    ];

    $collected = thingHelpersSecondary(['locations' => $locations], $limits + [
        'secondary_context' => ' Entries ',
        'current_field' => 'missing|locations',
        'secondary_field' => 'none|org',
    ]);

    $expected = array_merge(\array_slice($targets[0], 0, $second), $first > 2 ? \array_slice($targets[1], 0, $second) : []);
    expect($collected)->toBe($expected);
})->with([
    'no limits' => [[], 3, 2],
    'one secondary target each' => [['secondary_limit' => 1], 3, 1],
    'first two current items' => [['current_limit' => '2'], 2, 2],
]);

it('keeps only the secondary entities in the secondary scope', function (): void {
    $tag = thingHelpersTermIn('tags');
    $kept = thingHelpersEntryWith('locations', ['org' => [$tag]], 'l1');
    $other = thingHelpersEntryWith('pages', ['org' => [thingHelpersTermIn('tags')]], 'p1');

    expect(thingHelpersSecondary(['locations' => [$other, $kept]], [
        'secondary_context' => 'entries',
        'secondary_scope' => ' locations ',
        'current_field' => 'locations',
        'secondary_field' => 'org',
    ]))->toBe([$tag]);
});

it('normalizes secondary targets in the nested scope, falling back to the secondary scope', function (array $scopes, bool $resolves): void {
    /** @var array<string, mixed> $scopes */
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'tags::one' ? $tag : null);
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    $location = thingHelpersEntryWith('locations', ['org' => ['one']], 'l1');
    Entry::shouldReceive('find')->andReturnNull();

    $collected = thingHelpersSecondary(['locations' => [$location]], $scopes + [
        'secondary_context' => 'entries',
        'current_field' => 'locations',
        'secondary_field' => 'org',
    ]);

    expect($collected)->toBe($resolves ? [$tag] : []);
})->with([
    'nested scope' => [['nested_scope' => ' tags '], true],
    'blank nested scope falls back to no secondary scope' => [['nested_scope' => ' '], false],
    'no scope' => [[], false],
    'non-string nested scope' => [['nested_scope' => 5], false],
]);

it('falls back from a blank nested scope to the secondary scope', function (): void {
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'tags::one' ? $tag : null);
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomy')->andReturn(tap(Mockery::mock(Taxonomy::class), fn ($t) => $t->shouldReceive('handle')->andReturn('tags')));
    $term->shouldReceive('inDefaultLocale')->andReturn(tap(Mockery::mock(LocalizedTerm::class), fn ($l) => $l->shouldReceive('value')->andReturnUsing(fn (string $h): mixed => $h === 'related' ? ['one'] : null)));

    expect(thingHelpersSecondary(['terms' => [$term]], [
        'secondary_context' => 'terms',
        'secondary_scope' => 'tags',
        'nested_scope' => ' ',
        'current_field' => 'terms',
        'secondary_field' => 'related',
    ]))->toBe([$tag]);
});

it('reads the first hop from a global set field', function (): void {
    $tag = thingHelpersTermIn('tags');
    $location = thingHelpersEntryWith('locations', ['org' => [$tag]], 'l1');
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->andReturnUsing(fn (string $h): mixed => $h === 'locations' ? [$location] : null);
    $set = Mockery::mock(GlobalSet::class);
    $set->shouldReceive('inCurrentSite')->andReturn($variables);
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->with('settings')->andReturn($set);

    expect(thingHelpersSecondary([], [
        'secondary_context' => 'entries',
        'global_handle' => ' settings ',
        'global_field' => 'locations',
        'secondary_field' => 'org',
    ]))->toBe([$tag]);
});

it('collects nothing when the global set for the first hop is missing', function (): void {
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->once()->andReturnNull();

    expect(thingHelpersSecondary(['locations' => ['x']], [
        'secondary_context' => 'entries',
        'global_handle' => 'gone',
        'global_field' => 'locations',
        'current_field' => 'locations',
        'secondary_field' => 'org',
    ]))->toBe([]);
});

it('reads the first hop from the context when the global handle is blank or not a string', function (mixed $handle): void {
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->never();
    $tag = thingHelpersTermIn('tags');

    expect(thingHelpersSecondary(['locations' => [thingHelpersEntryWith('locations', ['org' => [$tag]])]], [
        'secondary_context' => 'entries',
        'global_handle' => $handle,
        'global_field' => 'unused',
        'current_field' => 'locations',
        'secondary_field' => 'org',
    ]))->toBe([$tag]);
})->with([
    'blank' => ['  '],
    'integer' => [5],
]);

it('expands replicator rows on both hops, keeping each target row context', function (): void {
    $person = thingHelpersEntryIn('people', 'p1');
    $location = thingHelpersEntryWith('locations', ['staff' => [['type' => 'member', 'value' => ['who' => [$person, 'unresolvable']]]]], 'l1');
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    Term::shouldReceive('find')->andReturnNull();

    /** @var list<array{entity: mixed, template_context: array<string, mixed>}> $collected */
    $collected = thingHelpersSecondary(['places' => [['type' => 'place', 'value' => ['location' => [$location]]], ['type' => 'place', 'value' => []]]], [
        'secondary_context' => 'entries',
        'current_field' => 'places',
        'current_field_replicator' => true,
        'current_field_replicator_relationship_field' => 'location',
        'secondary_field' => 'staff',
        'secondary_field_replicator' => 'yes',
        'secondary_field_replicator_relationship_field' => 'who',
    ]);

    expect($collected)->toHaveCount(1)
        ->and($collected[0]['entity'])->toBe($person)
        ->and($collected[0]['template_context']['replicator_row_type'])->toBe('member');
});

// ---
// Tertiary mode collection
// ---

/**
 * Collect tertiary-mode targets.
 *
 * @param  array<string, mixed>  $context  Antlers context.
 * @param  array<string, mixed>  $params  Tag parameters.
 * @return array<int, mixed>
 */
function thingHelpersTertiary(array $context, array $params): array
{
    /** @var array<int, mixed> $targets */
    $targets = thingHelpersCall('collectTargetsForTertiaryMode', $context, $params);

    return $targets;
}

/**
 * A mocked taxonomy term in a taxonomy whose default-locale fields return fixed values.
 *
 * @param  array<string, mixed>  $values  Field values by handle.
 */
function thingHelpersTermWith(string $taxonomy, array $values): TaxonomyTerm
{
    $term = thingHelpersTermIn($taxonomy);
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('value')->andReturnUsing(fn (string $h): mixed => $values[$h] ?? null);
    $term->shouldReceive('inDefaultLocale')->andReturn($localized);

    return $term;
}

it('collects nothing in tertiary mode without a usable configuration', function (array $params): void {
    /** @var array<string, mixed> $params */
    expect(thingHelpersTertiary(['segments' => ['x']], $params))->toBe([]);
})->with([
    'no secondary context' => [['current_field' => 'segments', 'secondary_field' => 'series', 'tertiary_field' => 'about']],
    'no current field' => [['secondary_context' => 'entries', 'secondary_field' => 'series', 'tertiary_field' => 'about']],
    'no secondary field' => [['secondary_context' => 'entries', 'current_field' => 'segments', 'tertiary_field' => 'about']],
    'no tertiary field' => [['secondary_context' => 'entries', 'current_field' => 'segments', 'secondary_field' => 'series']],
]);

it('walks entity, term and field hops in order, capped at the first two limits', function (array $limits, array $expected): void {
    /** @var array<string, mixed> $limits */
    /** @var list<string> $expected */
    $about = ['a1' => thingHelpersEntryIn('subject_matter', 'a1'), 'a2' => thingHelpersEntryIn('subject_matter', 'a2'), 'a3' => thingHelpersEntryIn('subject_matter', 'a3')];
    $seriesOne = thingHelpersTermWith('series', ['about' => [$about['a1']]]);
    $seriesTwo = thingHelpersTermWith('series', ['about' => [$about['a2']]]);
    $seriesThree = thingHelpersTermWith('series', ['none' => null, 'about' => [$about['a3'], 'unresolvable']]);
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    Term::shouldReceive('find')->andReturnNull();

    $segments = [
        thingHelpersEntryWith('segments', ['series' => [$seriesOne, 'not a term', $seriesTwo]], 's1'),
        'not an entity',
        thingHelpersEntryWith('segments', ['series' => [$seriesThree]], 's2'),
    ];

    $collected = thingHelpersTertiary(['segments' => $segments], $limits + [
        'secondary_context' => 'entries',
        'current_field' => 'segments',
        'secondary_field' => 'series',
        'tertiary_field' => 'none|about',
    ]);

    expect($collected)->toBe(array_map(fn (string $k): mixed => $about[$k], $expected));
})->with([
    'no limits' => [[], ['a1', 'a2', 'a3']],
    'one series per segment' => [['secondary_limit' => 1], ['a1', 'a3']],
    'first segment only' => [['current_limit' => 1], ['a1', 'a2']],
    'first two items, the second not an entity' => [['current_limit' => '2'], ['a1', 'a2']],
]);

it('scopes each hop: secondary entities, middle terms and innermost items', function (): void {
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => match ($id) {
        'series::health' => thingHelpersTermWith('series', ['about' => ['one']]),
        'tags::one' => $tag,
        default => null,
    });
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();

    $inScope = thingHelpersEntryWith('segments', ['series' => ['health']], 's1');
    $outOfScope = thingHelpersEntryWith('pages', ['series' => ['health']], 'p1');

    $params = [
        'secondary_context' => 'entries',
        'secondary_scope' => ' segments ',
        'intermediate_scope' => ' series ',
        'nested_scope' => ' tags ',
        'current_field' => 'segments',
        'secondary_field' => 'series',
        'tertiary_field' => 'about',
    ];

    expect(thingHelpersTertiary(['segments' => [$outOfScope, $inScope]], $params))->toBe([$tag])
        ->and(thingHelpersTertiary(['segments' => [$inScope]], ['nested_scope' => ' '] + $params))->toBe([]);
});

it('falls back from a blank nested scope to the secondary scope in tertiary mode', function (): void {
    $tag = thingHelpersTermIn('tags');
    Term::shouldReceive('find')->andReturnUsing(fn (string $id): mixed => $id === 'tags::one' ? $tag : null);
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    $middle = thingHelpersTermWith('series', ['about' => ['one']]);
    $term = thingHelpersTermWith('tags', ['series' => [$middle]]);

    expect(thingHelpersTertiary(['terms' => [$term]], [
        'secondary_context' => 'terms',
        'secondary_scope' => 'tags',
        'nested_scope' => '',
        'current_field' => 'terms',
        'secondary_field' => 'series',
        'tertiary_field' => 'about',
    ]))->toBe([$tag]);
});

it('walks global variables or a scoped asset as the secondary entity in tertiary mode', function (string $context, ?string $scope): void {
    $person = thingHelpersEntryIn('people', 'p1');
    $series = thingHelpersTermWith('series', ['about' => [$person]]);
    if ($context === 'globals') {
        $secondary = Mockery::mock(Variables::class);
        $secondary->shouldReceive('get')->andReturnUsing(fn (string $h): mixed => $h === 'series' ? [$series] : null);
    } else {
        $secondary = thingHelpersAssetIn('images');
        $secondary->shouldReceive('data')->andReturn(collect(['series' => [$series]]));
    }

    expect(thingHelpersTertiary(['links' => [$secondary]], [
        'secondary_context' => $context,
        'secondary_scope' => $scope,
        'current_field' => 'links',
        'secondary_field' => 'series',
        'tertiary_field' => 'about',
    ]))->toBe([$person]);
})->with([
    'globals' => ['globals', null],
    'assets in a padded container scope' => ['assets', ' images '],
]);

it('reads a scoped asset as the secondary entity in secondary mode, trimming the scope', function (): void {
    $tag = thingHelpersTermIn('tags');
    $asset = thingHelpersAssetIn('images');
    $asset->shouldReceive('data')->andReturn(collect(['topics' => [$tag]]));

    expect(thingHelpersSecondary(['links' => [$asset]], [
        'secondary_context' => 'assets',
        'secondary_scope' => ' images ',
        'current_field' => 'links',
        'secondary_field' => 'topics',
    ]))->toBe([$tag]);
});

it('expands replicator rows on all three hops in tertiary mode', function (): void {
    $person = thingHelpersEntryIn('people', 'p1');
    $series = thingHelpersTermWith('series', ['hosts' => [['type' => 'host', 'value' => ['who' => [$person]]]]]);
    $segment = thingHelpersEntryWith('segments', ['series_rows' => [['type' => 'link', 'value' => ['series' => [$series]]]]], 's1');

    /** @var list<array{entity: mixed, template_context: array<string, mixed>}> $collected */
    $collected = thingHelpersTertiary(['rows' => [['type' => 'row', 'value' => ['segment' => [$segment]]]]], [
        'secondary_context' => 'entries',
        'current_field' => 'rows',
        'current_field_replicator' => true,
        'current_field_replicator_relationship_field' => 'segment',
        'secondary_field' => 'series_rows',
        'secondary_field_replicator' => true,
        'secondary_field_replicator_relationship_field' => 'series',
        'tertiary_field' => 'hosts',
        'tertiary_field_replicator' => true,
        'tertiary_field_replicator_relationship_field' => 'who',
    ]);

    expect($collected)->toHaveCount(1)
        ->and($collected[0]['entity'])->toBe($person)
        ->and($collected[0]['template_context']['replicator_row_type'])->toBe('host');
});

// ---
// Global mode collection
// ---

/**
 * Register a global set whose current-site variables hold the given values.
 *
 * @param  array<string, mixed>  $values  Variable values by handle.
 */
function thingHelpersGlobalSet(string $handle, array $values): void
{
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->andReturnUsing(fn (string $h): mixed => $values[$h] ?? null);
    $set = Mockery::mock(GlobalSet::class);
    $set->shouldReceive('inCurrentSite')->andReturn($variables);
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->with($handle)->andReturn($set);
}

it('collects nothing in global mode without a usable global set or field', function (array $params): void {
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->never();

    expect(thingHelpersCall('collectTargetsForGlobalMode', $params))->toBe([]);
})->with([
    'no handle' => [['global_field' => 'founder']],
    'blank handle' => [['global_handle' => '  ', 'global_field' => 'founder']],
    'non-string handle' => [['global_handle' => 5, 'global_field' => 'founder']],
    'no field' => [['global_handle' => 'settings']],
]);

it('collects nothing in global mode when the set is missing', function (): void {
    Statamic\Facades\GlobalSet::shouldReceive('findByHandle')->once()->with('gone')->andReturnNull();

    expect(thingHelpersCall('collectTargetsForGlobalMode', ['global_handle' => ' gone ', 'global_field' => 'founder']))->toBe([]);
});

it('collects the global field targets that resolve, capped at the current limit', function (mixed $limit, int $count): void {
    $first = thingHelpersEntryIn('people', 'p1');
    $second = thingHelpersEntryIn('people', 'p2');
    thingHelpersGlobalSet('settings', ['founder' => [$first, 'unresolvable', $second]]);
    Entry::shouldReceive('find')->andReturnNull();
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();
    Term::shouldReceive('find')->andReturnNull();

    $collected = thingHelpersCall('collectTargetsForGlobalMode', ['global_handle' => ' settings ', 'global_field' => 'missing|founder', 'current_limit' => $limit]);

    expect($collected)->toBe(\array_slice([$first, $second], 0, $count));
})->with([
    'no limit' => [null, 2],
    'first item only' => [1, 1],
    'first two items' => ['2', 1],
]);
