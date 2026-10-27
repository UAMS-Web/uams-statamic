<?php

declare(strict_types=1);

use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Query\StatusQueryBuilder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2598: `patient_types` was a taxonomy enabled on `default` and
 * `health`, and is now a structured collection on `default` only, picked across
 * sites (the ticket's decision A). A patient type means the same thing on every
 * site, so there is one list, and the Find-a-Doc filters on `health` read it from
 * `default` and keep matching on the same slugs.
 *
 * Nothing here saves an entry: the cross-site case builds an unsaved person in
 * memory, so no write reaches the shared content mirror.
 *
 * @command  php artisan test --compact tests/Feature/PatientTypesCollectionTest.php
 */
const PATIENT_TYPES_SLUGS = ['adults', 'children-and-adolescents'];

afterEach(function (): void {
    Site::setCurrent('default');
});

/**
 * The `patient_types` entries on `default`, keyed by slug.
 *
 * @return array<string, StatamicEntry>
 */
function patientTypeEntriesBySlug(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'patient_types');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    return $entries->keyBy(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a patient type slug'))->all();
}

it('is a structured collection on default only, with its blueprint, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('patient_types');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('patient_types');

    expect($collection->hasStructure())->toBeTrue()
        ->and($collection->propagate())->toBeFalse()
        // Decision A: one list, on one site. The picker reaches it from every site.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'patient_types sites')->all())
        ->toBe(['default'])
        // Patient types have no public page, as the terms had none.
        ->and($collection->route('default'))->toBeNull()
        // By fields only the committed file has: with no file, Statamic builds a
        // fallback under the same handle AND the same title, so neither proves it.
        ->and(mustBe(Blueprint::class, $collection->entryBlueprint('patient_type'), 'the patient_type blueprint')->hasField('same_as'))
        ->toBeTrue()
        ->and(mustBe(Blueprint::class, $collection->entryBlueprint('patient_type'), 'the patient_type blueprint')->hasField('alternate_name'))
        ->toBeTrue()
        ->and(config('statamic.search.indexes.patient_types.searchables'))->toBe('collection:patient_types');

    expect(Taxonomy::findByHandle('patient_types'))->toBeNull()
        ->and(BlueprintFacade::in('taxonomies/patient_types')->count())->toBe(0);
});

it('holds the two former terms as entries, with their fields and in tree order', function (): void {
    $this->requireContentCollection('patient_types');

    $entries = patientTypeEntriesBySlug();

    expect(array_keys($entries))->toEqualCanonicalizing(PATIENT_TYPES_SLUGS)
        ->and($entries['adults']->get('title'))->toBe('Adults')
        ->and($entries['children-and-adolescents']->get('title'))->toBe('Children and Adolescents')
        ->and(array_column(mustBeArray($entries['children-and-adolescents']->get('alternate_name'), 'alternate_name'), 'text'))
        // All five alternate names survive the move; three named, to keep the list readable.
        ->toHaveCount(5)->toContain('minors', 'juveniles', 'adolescents')
        ->and(array_column(mustBeArray($entries['adults']->get('same_as'), 'same_as'), 'url'))
        ->toBe(['https://www.wikidata.org/wiki/Q9584157']);

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('patient_types');
    $tree = mustBe(CollectionStructure::class, $collection->structure(), 'the patient_types structure')->in('default');

    expect(array_column(mustBeArray(mustBe(CollectionTree::class, $tree, 'the default tree')->tree(), 'the default tree'), 'entry'))
        ->toBe([$entries['adults']->id(), $entries['children-and-adolescents']->id()]);
});

it('stores every person\'s patient types as ids of entries in the collection', function (): void {
    $this->requireContentCollection('patient_types');

    $ids = array_values(array_map(fn (StatamicEntry $entry): string => mustBeString($entry->id(), 'a patient type id'), patientTypeEntriesBySlug()));

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $people */
    $people = $query->get();

    $stored = $people
        ->flatMap(fn (StatamicEntry $person): array => mustBeArray($person->get('patient_types') ?? [], 'a person\'s patient_types'))
        ->values();

    // A left-over term slug (`adults`) would resolve to nothing through an entries field.
    expect($stored)->not->toBeEmpty()
        ->and($stored->diff($ids)->all())->toBe([]);
});

it('gives the people picker the collection, reachable from every site', function (): void {
    $field = mustBe(
        Field::class,
        mustBe(Blueprint::class, Collection::findByHandle('people')?->entryBlueprint('person'), 'the person blueprint')->field('patient_types'),
        'the patient_types field',
    );

    expect($field->type())->toBe('entries')
        ->and($field->get('collections'))->toBe(['patient_types'])
        ->and($field->get('select_across_sites'))->toBeTrue()
        ->and($field->get('taxonomies'))->toBeNull();
});

it('resolves a patient type chosen for a person on another site', function (): void {
    $this->requireContentCollection('patient_types');

    $adults = patientTypeEntriesBySlug()['adults'];

    // Unsaved, so nothing is written: a person authored on `health` pointing at the
    // one `default` entry, which is the reference decision A relies on.
    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale('health');
    $person->slug('zz-patient-types-cross-site');
    $person->data(['title' => 'Christine Chapel', 'patient_types' => [$adults->id()]]);

    /** @var Value $value */
    $value = $person->augmentedValue('patient_types');
    /** @var Illuminate\Support\Collection<int, StatamicEntry> $resolved */
    $resolved = mustBe(StatusQueryBuilder::class, $value->value(), 'the patient_types relation')->get();

    expect($resolved->map(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a patient type slug'))->all())->toBe(['adults']);
});

it('lists the same filter options, by slug, on the health site as on default', function (string $site): void {
    $this->requireContentCollection('patient_types');

    Site::setCurrent($site);

    // The option loop both filter bars share (UAMS-Web/uams-statamic-archive#2598): read from `default`, so the
    // `health` Find-a-Doc filters list the one set of slugs the cards carry.
    $out = (string) Antlers::parse(
        '{{ collection:patient_types site="default" sort="title:asc" }}<option value="{{ slug }}">{{ title | sanitize }}</option>{{ /collection:patient_types }}',
        [],
        true,
    );

    expect($out)->toBe('<option value="adults">Adults</option><option value="children-and-adolescents">Children and Adolescents</option>');
})->with(['default', 'health']);

it('reads the patient-type options through that loop in both filter bars', function (string $path): void {
    $source = (string) file_get_contents(resource_path($path));

    expect($source)->toContain('{{ collection:patient_types site="default" sort="title:asc" }}')
        ->and($source)->not->toContain('taxonomy:patient_types');
})->with([
    'provider directory' => ['views/people/provider/_directory_filters.antlers.html'],
    'area of expertise section' => ['views/clinical_expertise/_section_filter.antlers.html'],
]);
