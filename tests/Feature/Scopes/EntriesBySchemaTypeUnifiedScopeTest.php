<?php

declare(strict_types=1);

use App\Scopes\EntriesBySchemaType;
use Illuminate\Support\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Real-entry behavior of the unified `EntriesBySchemaType` scope (UAMS-Web/uams-statamic-archive#1408) against
 * an isolated Stache — not a mocked builder.
 *
 * One scope now serves both surfaces via `Str::snake` handles under the single
 * `entries_by_schema_type` prefix:
 *
 *   - `schema_objects` entries: the flat `schema->type` leaf, expanded to the
 *     type + its Schema.org descendants from the enum.
 *   - clinical collections: the chained `schema_type->selections` path, matched
 *     on the leaf type (the path already carries every ancestor).
 *
 * It also fixes a real silent no-op: the former slug handles
 * (`administrativearea`) never matched the snake blueprint handle
 * (`administrative_area`), so that picker showed ALL entries.
 *
 * @covers \App\Scopes\EntriesBySchemaType
 *
 * @command  php artisan test --compact tests/Feature/Scopes/EntriesBySchemaTypeUnifiedScopeTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Apply the scope to a query over the given collection and return matching slugs.
 *
 * @return array<int, string>
 */
function schemaTypeScopeSlugs(string $collection, string $handle): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', $collection);

    (new EntriesBySchemaType)->apply($query, ['queryScopes' => [$handle]]);

    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();

    return $entries
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string $slug */
            $slug = $entry->slug();

            return $slug;
        })
        ->sort()
        ->values()
        ->all();
}

/**
 * Persist a test collection. The fluent setters mutate in place and return an
 * untyped `$this`, so they are called as statements on the concrete
 * {@see Statamic\Entries\Collection} that `Collection::make()` yields.
 */
function makeSchemaScopeCollection(string $handle, string $title): void
{
    $collection = CollectionFacade::make($handle);
    $collection->title($title);
    $collection->save();
}

/**
 * Persist a test entry. `Entry::make()` returns the contract, so narrow to the
 * concrete {@see Statamic\Entries\Entry} once and set fields in place.
 *
 * @param  array<string, mixed>  $data
 */
function makeSchemaScopeEntry(string $collection, string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->data($data);
    $entry->save();
}

beforeEach(function (): void {
    makeSchemaScopeCollection('schema_objects', 'Schema Objects');
    makeSchemaScopeCollection('clinical_conditions', 'Clinical Conditions');

    // schema_objects: the flat resolved leaf on `schema->type`.
    makeSchemaScopeEntry('schema_objects', 'an-area', ['title' => 'Area', 'schema' => ['type' => 'AdministrativeArea']]);
    makeSchemaScopeEntry('schema_objects', 'a-city', ['title' => 'City', 'schema' => ['type' => 'City']]); // descendant of AdministrativeArea
    makeSchemaScopeEntry('schema_objects', 'a-person', ['title' => 'Person', 'schema' => ['type' => 'Person']]);

    // clinical: the chained `schema_type->selections` path (root … leaf).
    makeSchemaScopeEntry('clinical_conditions', 'a-therapy', ['title' => 'Therapy', 'schema_type' => ['selections' => ['MedicalEntity', 'MedicalTherapy']]]);
    makeSchemaScopeEntry('clinical_conditions', 'a-condition', ['title' => 'Condition', 'schema_type' => ['selections' => ['MedicalEntity', 'MedicalCondition']]]);
});

it('resolves the multi-word administrative_area handle and filters to type plus descendants', function (): void {
    // The exact former no-op: this handle matched no slug alias and showed everything.
    expect(schemaTypeScopeSlugs('schema_objects', 'entries_by_schema_type_administrative_area'))
        ->toBe(['a-city', 'an-area']);
});

it('filters schema_objects to a single-word leaf type', function (): void {
    expect(schemaTypeScopeSlugs('schema_objects', 'entries_by_schema_type_person'))
        ->toBe(['a-person']);
});

it('filters a clinical collection through the chained selections path', function (): void {
    expect(schemaTypeScopeSlugs('clinical_conditions', 'entries_by_schema_type_medical_therapy'))
        ->toBe(['a-therapy']);
});

it('leaves the query unfiltered for the root Thing type', function (): void {
    expect(schemaTypeScopeSlugs('schema_objects', 'entries_by_schema_type_thing'))
        ->toBe(['a-city', 'a-person', 'an-area']);
});
