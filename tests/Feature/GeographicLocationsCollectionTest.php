<?php

declare(strict_types=1);

use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Fieldset;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Query\StatusQueryBuilder;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Symfony\Component\Yaml\Yaml;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2597: `geographic_locations` was a taxonomy on `default`, and is
 * now a structured collection on `default` only, picked across sites (the
 * ticket's decision A). A place means the same thing on every site, so there is
 * one tree, and the dateline and the JSON-LD `AdministrativeArea` read one entry
 * per place.
 *
 * Nothing here saves an entry: the cross-site and rendering cases build unsaved
 * entries in memory, so no write reaches the shared content mirror.
 *
 * @command  php artisan test --compact tests/Feature/GeographicLocationsCollectionTest.php
 */
const GEOGRAPHIC_LOCATION_CITY_TITLES = [
    'Batesville',
    'El Dorado',
    'Fayetteville',
    'Fort Smith',
    'Helena–West Helena',
    'Jonesboro',
    'Little Rock',
    'Lowell',
    'Magnolia',
    'Pine Bluff',
    'Springdale',
    'Texarkana',
];

afterEach(function (): void {
    Site::setCurrent('default');
});

/**
 * The `geographic_locations` entries on `default`, keyed by slug.
 *
 * @return array<string, StatamicEntry>
 */
function geographicLocationEntriesBySlug(): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'geographic_locations');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $entries */
    $entries = $query->get();

    return $entries->keyBy(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a geographic location slug'))->all();
}

/**
 * Every field definition that picks geographic locations, by where it is defined.
 *
 * @return array<string, Field>
 */
function geographicLocationPickers(): array
{
    $pickers = [];

    // The two creative-work fields, the person's work location, and the audience's area
    $fieldsets = [
        'uamsweb_creativework_location' => ['geographic_location', 'geographic_location_content'],
        'uamsweb_common_people' => ['work_location'],
    ];

    foreach ($fieldsets as $fieldsetHandle => $handles) {
        $fieldset = Fieldset::find($fieldsetHandle);

        foreach ($handles as $handle) {
            $pickers["{$fieldsetHandle}.{$handle}"] = mustBe(Field::class, $fieldset?->field($handle), "{$fieldsetHandle}.{$handle}");
        }
    }

    $audience = mustBe(Blueprint::class, BlueprintFacade::find('taxonomies.audiences.audience'), 'the audience blueprint');
    $pickers['audience.geographic_area'] = mustBe(Field::class, $audience->field('geographic_area'), 'audience.geographic_area');

    return $pickers;
}

it('is a structured collection on default only, with its blueprint, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('geographic_locations');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('geographic_locations');

    $blueprint = mustBe(Blueprint::class, $collection->entryBlueprint('geographic_location'), 'the geographic_location blueprint');

    expect($collection->hasStructure())->toBeTrue()
        ->and($collection->propagate())->toBeFalse()
        // Decision A: one tree, on one site. The pickers reach it from every site.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'geographic_locations sites')->all())
        ->toBe(['default'])
        // Places have no public page, as the terms had none.
        ->and($collection->route('default'))->toBeNull()
        // By fields only the committed file has: a fallback blueprint shares the handle and title.
        ->and($blueprint->hasField('dateline'))->toBeTrue()
        ->and($blueprint->hasField('schema_object'))->toBeTrue()
        // The tree carries the hierarchy now, so the term-era parent field is gone.
        ->and($blueprint->hasField('parent'))->toBeFalse()
        ->and(config('statamic.search.indexes.geographic_locations.searchables'))->toBe('collection:geographic_locations');

    expect(Taxonomy::findByHandle('geographic_locations'))->toBeNull()
        ->and(BlueprintFacade::in('taxonomies/geographic_locations')->count())->toBe(0);
});

it('holds the fourteen former terms as entries in the same three-level tree', function (): void {
    $this->requireContentCollection('geographic_locations');

    $entries = geographicLocationEntriesBySlug();

    expect($entries)->toHaveCount(14)
        ->and($entries['little-rock']->get('dateline'))->toBe('LITTLE ROCK, Ark.')
        ->and($entries['little-rock']->get('schema_object'))->toBe('b764e8a2-6abf-4118-be7e-8964f02668d5')
        ->and($entries['arkansas']->get('dateline'))->toBe('Arkansas');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('geographic_locations');
    $tree = mustBe(CollectionTree::class, mustBe(CollectionStructure::class, $collection->structure(), 'the structure')->in('default'), 'the default tree');

    /** @var list<array{entry: string, children?: list<array{entry: string, children?: mixed}>}> $root */
    $root = mustBeArray($tree->tree(), 'the default tree');

    $titleOf = fn (mixed $id): string => mustBeString(
        mustBe(StatamicEntry::class, Entry::find(mustBeString($id, 'a tree entry id')), 'a place entry')->get('title'),
        'a place title',
    );

    expect($root)->toHaveCount(1)
        ->and($titleOf($root[0]['entry']))->toBe('United States of America');

    $states = $root[0]['children'] ?? [];

    expect($states)->toHaveCount(1)
        ->and($titleOf($states[0]['entry']))->toBe('Arkansas');

    $cities = array_map(fn (mixed $node): string => $titleOf(mustBeArray($node, 'a city node')['entry'] ?? null), mustBeArray($states[0]['children'] ?? [], 'the cities'));

    expect($cities)->toBe(GEOGRAPHIC_LOCATION_CITY_TITLES);
});

it('stores every geographic location reference as an id in the collection', function (): void {
    $this->requireContentCollection('geographic_locations');

    $ids = array_values(array_map(fn (StatamicEntry $entry): string => mustBeString($entry->id(), 'a place id'), geographicLocationEntriesBySlug()));

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');

    /** @var Illuminate\Support\Collection<int, StatamicEntry> $people */
    $people = $query->get();

    $stored = $people
        ->flatMap(fn (StatamicEntry $person): array => mustBeArray($person->get('work_location') ?? [], 'a person\'s work_location'))
        ->values();

    // A left-over term slug (`little-rock`) would resolve to nothing through an entries field.
    expect($stored)->not->toBeEmpty()
        ->and($stored->diff($ids)->all())->toBe([]);
});

it('gives every picker the collection, reachable from every site, searched by its index', function (): void {
    foreach (geographicLocationPickers() as $name => $field) {
        expect($field->type())->toBe('entries', $name)
            ->and($field->get('collections'))->toBe(['geographic_locations'])
            ->and($field->get('select_across_sites'))->toBeTrue()
            ->and($field->get('search_index'))->toBe('geographic_locations')
            ->and($field->get('taxonomies'))->toBeNull();
    }

    // The five role sets each carry their own work-location picker, nested in replicator sets
    // the fieldset API does not reach, so read every definition from the file.
    $pickers = [];
    $walk = function (mixed $node) use (&$walk, &$pickers): void {
        if (! \is_array($node)) {
            return;
        }

        $places = \in_array('geographic_locations', (array) ($node['collections'] ?? []), true)
            || \in_array('geographic_locations', (array) ($node['taxonomies'] ?? []), true);

        if ($places) {
            $pickers[] = $node;
        }

        foreach ($node as $child) {
            $walk($child);
        }
    };
    $walk(Yaml::parseFile(resource_path('fieldsets/uamsweb_common_people.yaml')));

    expect($pickers)->toHaveCount(6);

    foreach ($pickers as $picker) {
        expect($picker['type'] ?? null)->toBe('entries')
            ->and($picker['select_across_sites'] ?? null)->toBeTrue()
            ->and($picker)->not->toHaveKey('taxonomies');
    }
});

it('resolves a place chosen for a person on another site', function (): void {
    $this->requireContentCollection('geographic_locations');

    $littleRock = geographicLocationEntriesBySlug()['little-rock'];

    // Unsaved: a person authored on `health` pointing at the one `default` place.
    /** @var StatamicEntry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale('health');
    $person->slug('zz-geographic-locations-cross-site');
    $person->data(['title' => 'Hikaru Sulu', 'work_location' => [$littleRock->id()]]);

    /** @var Value $value */
    $value = $person->augmentedValue('work_location');
    /** @var Illuminate\Support\Collection<int, StatamicEntry> $resolved */
    $resolved = mustBe(StatusQueryBuilder::class, $value->value(), 'the work_location relation')->get();

    expect($resolved->map(fn (StatamicEntry $entry): string => mustBeString($entry->slug(), 'a place slug'))->all())->toBe(['little-rock']);
});

it('renders a news release dateline from its place entry', function (): void {
    $this->requireContentCollection('geographic_locations');

    $littleRock = geographicLocationEntriesBySlug()['little-rock'];

    // Unsaved: a news release on `health` whose place is the one `default` entry.
    /** @var StatamicEntry $release */
    $release = Entry::make();
    $release->collection('posts');
    $release->blueprint('news_release');
    $release->locale('health');
    $release->slug('zz-geographic-locations-dateline');
    $release->data(['title' => 'Dateline probe', 'geographic_location' => [$littleRock->id()]]);

    $out = (string) Antlers::parse(
        '{{ dateline location_field="geographic_location" date="Sept. 28, 2026" }}',
        ['geographic_location' => $release->augmentedValue('geographic_location')],
        true,
    );

    expect($out)->toBe('LITTLE ROCK, Ark. (Sept. 28, 2026)');
});

it('emits the place\'s AdministrativeArea reference through linked entries', function (): void {
    $this->requireContentCollection('geographic_locations');

    $littleRock = geographicLocationEntriesBySlug()['little-rock'];

    $out = (string) Antlers::parse(
        '{{ schema_thing_refs mode="linked_entries" field="geographic_location" linked_entries_field="schema_object" }}',
        ['geographic_location' => [$littleRock->id()]],
        true,
    );

    expect($out)->toContain('b764e8a2-6abf-4118-be7e-8964f02668d5');
});

it('reads places through linked entries in every schema-object template, never taxonomy terms', function (): void {
    $this->requireContentCollection('geographic_locations');

    $linked = 0;

    // Scan every committed schema-object template, so a template added later is covered too
    foreach (glob(base_path('content/collections/schema_objects/default/*.md')) ?: [] as $path) {
        $source = (string) file_get_contents($path);

        expect($source)->not->toContain('taxonomy="geographic_locations"');

        $linked += substr_count($source, 'mode="linked_entries"');
    }

    // The six place references across the four templates that emit them
    expect($linked)->toBe(6);
});
