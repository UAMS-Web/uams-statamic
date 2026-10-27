<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaThingRefsResolver;
use App\Tags\SchemaThingRefs;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for the `schema_thing_refs` Antlers tag and
 * `SchemaThingRefsResolver` JSON-LD @id output.
 *
 * Mutation: 100% on App\Tags\SchemaThingRefs (35 mutants, --covered-only; every line covered).
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaThingRefsTest.php
 */

/**
 * Synthetic schema_objects entry id used with mocked `Entry::whereInId` (no
 * content dependency).
 */
const SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

it('entries mode outputs json with canonical at id for schema object entry', function () {
    // Build a schema_objects entry stand-in for batched repository loading.
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);

    // Stub the entry id returned to callers.
    $entry->shouldReceive('id')->andReturn(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    // Stub collection so ref resolution accepts this as a schema_objects entry.
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');

    // Stub Statamic so the resolver loads the mock entry via whereInId.
    EntryFacade::shouldReceive('whereInId')
        ->once()
        ->with([SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID])
        ->andReturn(EntryCollection::make([$entry]));

    // Stub canonical @id so output does not depend on real schema front matter.
    $canonicalAtId = 'https://example.test/#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID;

    // Replace SchemaOutputService in the container for this example.
    $schemaOutputService = Mockery::mock(SchemaOutputService::class);
    $schemaOutputService->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($entry)
        ->andReturn($canonicalAtId);
    app()->instance(SchemaOutputService::class, $schemaOutputService);

    // Render the tag with a context field holding the mocked entry id.
    $output = Antlers::parse(
        '{{ schema_thing_refs mode="entries" field="place_ids" }}',
        [
            'place_ids' => SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID,
        ],
        true
    );

    // Decode the JSON array string the tag returns.
    $decoded = json_decode((string) $output, true);

    // Assert the payload decoded to an array.
    expect($decoded)->toBeArray();

    // Assert at least one reference object exists.
    expect($decoded)->toHaveKey(0);

    // Assert the first element is an associative array (the ref object).
    expect(data_get($decoded, 0))->toBeArray();

    // Assert the ref includes a canonical @id key.
    expect(data_get($decoded, 0))->toHaveKey('@id');

    // Assert @id is a non-empty string suitable for JSON-LD.
    expect(data_get($decoded, '0.@id'))->toBeString();

    // Assert the canonical @id includes this entry id as the URI fragment.
    expect(data_get($decoded, '0.@id'))->toContain('#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);
});

it('entries mode pipe fallback uses second field', function () {
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);

    $entry->shouldReceive('id')->andReturn(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');

    EntryFacade::shouldReceive('whereInId')
        ->once()
        ->with([SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID])
        ->andReturn(EntryCollection::make([$entry]));

    $canonicalAtId = 'https://example.test/#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID;

    $schemaOutputService = Mockery::mock(SchemaOutputService::class);
    $schemaOutputService->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($entry)
        ->andReturn($canonicalAtId);
    app()->instance(SchemaOutputService::class, $schemaOutputService);

    $output = Antlers::parse(
        '{{ schema_thing_refs mode="entries" field="empty_ids|place_ids" }}',
        [
            'empty_ids' => [],
            'place_ids' => SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID,
        ],
        true
    );

    $decoded = json_decode((string) $output, true);

    expect($decoded)->toBeArray();

    expect($decoded)->toHaveKey(0);

    expect(data_get($decoded, '0.@id'))->toBeString();

    expect(data_get($decoded, '0.@id'))->toContain('#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);
});

it('entries mode returns empty json array when field missing', function () {
    // Parse the tag against empty context so the field is missing.
    $output = Antlers::parse(
        '{{ schema_thing_refs mode="entries" field="missing_field" }}',
        [],
        true
    );

    // Expect the literal empty array string after trim.
    expect(trim((string) $output))->toBe('[]');
});

it('terms mode returns empty json array when field missing', function () {
    // Parse the tag with default terms mode and no matching context key.
    $output = Antlers::parse(
        '{{ schema_thing_refs field="missing_geographic_field" }}',
        [],
        true
    );

    // Expect the literal empty array string after trim.
    expect(trim((string) $output))->toBe('[]');
});

it('global mode outputs json with canonical at id for schema object entry', function () {
    // Build variables that return a schema_objects entry id for country_of_origin.
    /** @var GlobalVariablesContract&MockInterface $variables */
    $variables = Mockery::mock(GlobalVariablesContract::class);

    // Stub the field read used by the resolver.
    $variables->shouldReceive('get')
        ->once()
        ->with('country_of_origin')
        ->andReturn(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    // Build a global set that resolves variables for the current site.
    /** @var GlobalSetContract&MockInterface $set */
    $set = Mockery::mock(GlobalSetContract::class);

    // Stub localized variables for inCurrentSite.
    $set->shouldReceive('inCurrentSite')->once()->andReturn($variables);

    // Stub GlobalSet facade to return the mock set.
    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    // Build a schema_objects entry stand-in for batched repository loading.
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);

    // Stub the entry id returned to callers.
    $entry->shouldReceive('id')->andReturn(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    // Stub collection so ref resolution accepts this as a schema_objects entry.
    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');

    // Stub Statamic so the resolver loads the mock entry via whereInId.
    EntryFacade::shouldReceive('whereInId')
        ->once()
        ->with([SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID])
        ->andReturn(EntryCollection::make([$entry]));

    // Stub canonical @id so output does not depend on real schema front matter.
    $canonicalAtId = 'https://example.test/#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID;

    // Replace SchemaOutputService in the container for this example.
    $schemaOutputService = Mockery::mock(SchemaOutputService::class);
    $schemaOutputService->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($entry)
        ->andReturn($canonicalAtId);
    app()->instance(SchemaOutputService::class, $schemaOutputService);

    // Render global mode with global handle and field (no Antlers context needed).
    $output = Antlers::parse(
        '{{ schema_thing_refs mode="global" global="uams_multisite_settings" field="country_of_origin" }}',
        [],
        true
    );

    // Decode the JSON array string the tag returns.
    $decoded = json_decode((string) $output, true);

    // Assert the payload decoded to an array with one ref.
    expect($decoded)->toBeArray();

    expect($decoded)->toHaveKey(0);

    expect(data_get($decoded, 0))->toHaveKey('@id');

    expect(data_get($decoded, '0.@id'))->toBeString();

    expect(data_get($decoded, '0.@id'))->toContain('#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);
});

it('global mode pipe fallback uses second field', function () {
    /** @var GlobalVariablesContract&MockInterface $variables */
    $variables = Mockery::mock(GlobalVariablesContract::class);

    $variables->shouldReceive('get')
        ->once()
        ->with('empty_country')
        ->andReturn(null);

    $variables->shouldReceive('get')
        ->once()
        ->with('country_of_origin')
        ->andReturn(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    /** @var GlobalSetContract&MockInterface $set */
    $set = Mockery::mock(GlobalSetContract::class);

    $set->shouldReceive('inCurrentSite')->once()->andReturn($variables);

    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);

    $entry->shouldReceive('id')->andReturn(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    $entry->shouldReceive('collectionHandle')->andReturn('schema_objects');

    EntryFacade::shouldReceive('whereInId')
        ->once()
        ->with([SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID])
        ->andReturn(EntryCollection::make([$entry]));

    $canonicalAtId = 'https://example.test/#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID;

    $schemaOutputService = Mockery::mock(SchemaOutputService::class);
    $schemaOutputService->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($entry)
        ->andReturn($canonicalAtId);
    app()->instance(SchemaOutputService::class, $schemaOutputService);

    $output = Antlers::parse(
        '{{ schema_thing_refs mode="global" global="uams_multisite_settings" field="empty_country|country_of_origin" }}',
        [],
        true
    );

    $decoded = json_decode((string) $output, true);

    expect($decoded)->toBeArray();

    expect($decoded)->toHaveKey(0);

    expect(data_get($decoded, '0.@id'))->toBeString();

    expect(data_get($decoded, '0.@id'))->toContain('#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);
});

it('global mode returns empty json array when global set missing', function () {
    // Stub GlobalSet facade so the handle does not resolve.
    GlobalSet::shouldReceive('findByHandle')
        ->once()
        ->with('missing_global')
        ->andReturn(null);

    // Render global mode against a nonexistent global handle.
    $output = Antlers::parse(
        '{{ schema_thing_refs mode="global" global="missing_global" field="country_of_origin" }}',
        [],
        true
    );

    expect(trim((string) $output))->toBe('[]');
});

/**
 * A linked place entry whose entries field holds the given schema_objects ids.
 *
 * @param  string  $field  The linked entry's entries field handle
 * @param  list<string>  $schemaObjectIds  The ids that field stores
 * @return EntryModel&MockInterface The place stand-in
 */
function schemaThingRefsLinkedPlace(string $field, array $schemaObjectIds): EntryModel
{
    /** @var EntryModel&MockInterface $place */
    $place = Mockery::mock(EntryModel::class);
    $place->shouldReceive('value')->with($field)->andReturn($schemaObjectIds);

    return $place;
}

/**
 * Stub the schema_objects entry load and its canonical @id for one id.
 *
 * @param  string  $schemaObjectId  The schema_objects entry id
 */
function schemaThingRefsStubSchemaObject(string $schemaObjectId): void
{
    /** @var EntryModel&MockInterface $schemaObject */
    $schemaObject = Mockery::mock(EntryModel::class);
    $schemaObject->shouldReceive('id')->andReturn($schemaObjectId);
    $schemaObject->shouldReceive('collectionHandle')->andReturn('schema_objects');

    EntryFacade::shouldReceive('whereInId')
        ->once()
        ->with([$schemaObjectId])
        ->andReturn(EntryCollection::make([$schemaObject]));

    $schemaOutputService = Mockery::mock(SchemaOutputService::class);
    $schemaOutputService->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($schemaObject)
        ->andReturn('https://example.test/#'.$schemaObjectId);
    app()->instance(SchemaOutputService::class, $schemaOutputService);
}

it('linked entries mode reads each linked entry\'s schema object (#2597)', function () {
    EntryFacade::shouldReceive('find')
        ->once()
        ->with('place-id')
        ->andReturn(schemaThingRefsLinkedPlace('schema_object', [SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID]));

    schemaThingRefsStubSchemaObject(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    $output = Antlers::parse(
        '{{ schema_thing_refs mode="linked_entries" field="geographic_location" }}',
        ['geographic_location' => ['place-id']],
        true
    );

    $decoded = json_decode((string) $output, true);

    expect(data_get($decoded, '0.@id'))->toBe('https://example.test/#'.SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);
});

it('linked entries mode honors the linked field and both limits', function () {
    // Only the first linked entry is read, and only its first id is kept.
    EntryFacade::shouldReceive('find')
        ->once()
        ->with('first-place')
        ->andReturn(schemaThingRefsLinkedPlace('area', [SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID, 'a-second-area']));

    schemaThingRefsStubSchemaObject(SCHEMA_THING_REFS_MOCK_SCHEMA_OBJECT_ENTRY_ID);

    $output = Antlers::parse(
        '{{ schema_thing_refs mode="linked_entries" field="places" linked_entries_field="area" linked_limit="1" entry_limit="1" }}',
        ['places' => ['first-place', 'second-place']],
        true
    );

    expect(json_decode((string) $output, true))->toHaveCount(1);
});

it('linked entries mode skips a linked id that resolves to nothing', function () {
    EntryFacade::shouldReceive('find')->once()->with('gone-place')->andReturn(null);

    $output = Antlers::parse(
        '{{ schema_thing_refs mode="linked_entries" field="geographic_location" }}',
        ['geographic_location' => ['gone-place']],
        true
    );

    expect(trim((string) $output))->toBe('[]');
});

it('linked entries mode returns empty json array when field missing', function () {
    $output = Antlers::parse(
        '{{ schema_thing_refs mode="linked_entries" field="missing_field" }}',
        [],
        true
    );

    expect(trim((string) $output))->toBe('[]');
});

/**
 * Bind a resolver double, returning it so a test can set expectations on it.
 *
 * @return SchemaThingRefsResolver&MockInterface
 */
function schemaThingRefsResolverDouble(): SchemaThingRefsResolver
{
    /** @var SchemaThingRefsResolver&MockInterface $resolver */
    $resolver = Mockery::mock(SchemaThingRefsResolver::class);
    app()->instance(SchemaThingRefsResolver::class, $resolver);

    return $resolver;
}

/**
 * Run the tag with the given parameters and context.
 *
 * @param  array<mixed>  $parameters
 * @param  array<mixed>  $context
 */
function schemaThingRefsTag(array $parameters, array $context = []): string
{
    $tag = new SchemaThingRefs;
    $tag->setContext($context);
    $tag->setParameters($parameters);

    return $tag->index();
}

it('returns an empty json array without resolving anything when no field handle is usable', function (mixed $field) {
    $resolver = schemaThingRefsResolverDouble();
    $resolver->shouldNotReceive('resolveFromTaxonomyTerms', 'resolveFromEntryEntriesField', 'resolveFromLinkedEntries', 'resolveFromGlobalEntriesField');

    expect(schemaThingRefsTag(['field' => $field]))->toBe('[]');
})->with([
    'missing' => [null],
    'blank' => ['  |  '],
    'not a string' => [['places']],
]);

it('returns an empty json array in global mode without a usable global handle', function (array $parameters) {
    $resolver = schemaThingRefsResolverDouble();
    $resolver->shouldNotReceive('resolveFromGlobalEntriesField');

    expect(schemaThingRefsTag(['mode' => 'global', 'field' => 'country_of_origin', ...$parameters]))->toBe('[]');
})->with([
    'no global' => [[]],
    'empty global' => [['global' => '']],
    'non-string global' => [['global' => 5]],
]);

it('hands the resolver every parameter with its defaults', function () {
    $resolver = schemaThingRefsResolverDouble();
    $resolver->shouldReceive('resolveFromTaxonomyTerms')->once()->withArgs(
        fn (array $context, array $params): bool => $context === ['id' => 'entry-1'] && $params === [
            'field' => 'geographic_location',
            'taxonomy' => 'geographic_locations',
            'term_entries_field' => 'schema_object',
            'term_limit' => null,
            'linked_entries_field' => 'schema_object',
            'linked_limit' => null,
            'entry_limit' => null,
            'global' => null,
            'site' => null,
        ]
    )->andReturn('["terms"]');

    expect(schemaThingRefsTag(['field' => 'geographic_location'], ['id' => 'entry-1']))->toBe('["terms"]');
});

it('unwraps Value-wrapped parameters before handing them on', function () {
    // Parameters::make() already unwraps a top-level Value, so the tag's own unwrap
    // is reached only by params built directly, as here.
    $resolver = schemaThingRefsResolverDouble();
    $resolver->shouldReceive('resolveFromGlobalEntriesField')->once()->withArgs(
        fn (array $params): bool => $params['field'] === 'country_of_origin'
            && $params['global'] === 'settings'
            && $params['site'] === 'health'
            && $params['entry_limit'] === 2
    )->andReturn('["global"]');

    $tag = new SchemaThingRefs;
    $tag->setContext([]);
    $tag->params = new Parameters([
        'mode' => new Value('global'),
        'field' => new Value('country_of_origin'),
        'global' => new Value('settings'),
        'site' => new Value('health'),
        'entry_limit' => new Value(2),
    ]);

    expect($tag->index())->toBe('["global"]');
});

it('normalizes the mode, and treats a non-string mode as terms', function (mixed $mode, string $method) {
    $resolver = schemaThingRefsResolverDouble();
    $resolver->shouldReceive($method)->once()->andReturn('["'.$method.'"]');

    expect(schemaThingRefsTag(['mode' => $mode, 'field' => 'places', 'global' => 'settings']))->toBe('["'.$method.'"]');
})->with([
    'entries, padded and capitalized' => ['  Entries ', 'resolveFromEntryEntriesField'],
    'linked entries' => ['LINKED_ENTRIES', 'resolveFromLinkedEntries'],
    'global' => ['Global', 'resolveFromGlobalEntriesField'],
    'unknown mode' => ['something-else', 'resolveFromTaxonomyTerms'],
    'non-string mode' => [5, 'resolveFromTaxonomyTerms'],
]);

it('passes the context only to the entry-reading modes', function () {
    $resolver = schemaThingRefsResolverDouble();
    $resolver->shouldReceive('resolveFromEntryEntriesField')->once()->withArgs(
        fn (array $context, array $params): bool => $context === ['id' => 'entry-2'] && $params['field'] === 'related_places'
    )->andReturn('["entries"]');
    $resolver->shouldReceive('resolveFromLinkedEntries')->once()->withArgs(
        fn (array $context, array $params): bool => $context === ['id' => 'entry-3'] && $params['linked_entries_field'] === 'places' && $params['linked_limit'] === '1'
    )->andReturn('["linked"]');

    expect(schemaThingRefsTag(['mode' => 'entries', 'field' => 'related_places'], ['id' => 'entry-2']))->toBe('["entries"]')
        ->and(schemaThingRefsTag(['mode' => 'linked_entries', 'field' => 'geo', 'linked_entries_field' => 'places', 'linked_limit' => '1'], ['id' => 'entry-3']))->toBe('["linked"]');
});
