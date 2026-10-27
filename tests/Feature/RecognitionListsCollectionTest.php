<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Config/shape + data-migration coverage for the UAMS-Web/uams-statamic-archive#1546 recognition-list re-model.
 *
 * The blueprint/config/search assertions read the app repo directly (always
 * present). The migrated-content assertions read the `content/` submodule and skip
 * when it is absent (no submodule, or the app PR's gitlink not yet bumped to the
 * content companion) — mirroring PostCategoriesCollectionConfigTest.
 *
 * @command  php artisan test --compact tests/Feature/RecognitionListsCollectionTest.php
 */

/** Skip a migrated-content assertion when the submodule/collection is absent. */
function recCollectionPathOrSkip(): string
{
    /** @var Store $store */
    $store = Stache::store('collections');
    $path = $store->directory().'recognition_lists.yaml';

    if (! File::exists($path)) {
        ContentSubmodule::reportMissing('migrated recognition_lists collection (post-gitlink-bump)');
    }

    return $path;
}

it('repoints people.recognition_lists from a replicator to a recognition_lists entries field', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/people')->get('person');
    /** @var Field $field */
    $field = $blueprint->field('recognition_lists');

    expect($field->type())->toBe('entries');
    expect($field->config()['collections'])->toBe(['recognition_lists']);
    expect($field->type())->not->toBe('replicator');
});

it('gives the list and category blueprints a members entries field into people', function () {
    /** @var Statamic\Fields\Blueprint $listBlueprint */
    $listBlueprint = Blueprint::in('collections/recognition_lists')->get('recognition_list');
    /** @var Field $listMembers */
    $listMembers = $listBlueprint->field('members');
    expect($listMembers->type())->toBe('entries');
    expect($listMembers->config()['collections'])->toBe(['people']);

    /** @var Statamic\Fields\Blueprint $categoryBlueprint */
    $categoryBlueprint = Blueprint::in('collections/recognition_lists')->get('recognition_list_category');
    /** @var Field $categoryMembers */
    $categoryMembers = $categoryBlueprint->field('members');
    expect($categoryMembers->type())->toBe('entries');
    expect($categoryMembers->config()['collections'])->toBe(['people']);
});

it('keeps the list blueprint carrying series, data_source, list_date and active', function () {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/recognition_lists')->get('recognition_list');

    /** @var Field $series */
    $series = $blueprint->field('series');
    expect($series->type())->toBe('terms_search_index');
    /** @var Field $dataSource */
    $dataSource = $blueprint->field('data_source');
    expect($dataSource->type())->toBe('terms_search_index');
    /** @var Field $listDate */
    $listDate = $blueprint->field('list_date');
    expect($listDate->type())->toBe('partial_date');
    /** @var Field $active */
    $active = $blueprint->field('active');
    expect($active->type())->toBe('toggle');
});

it('indexes the recognition_lists collection, not the retired taxonomy', function () {
    expect(config('statamic.search.indexes.recognition_lists.searchables'))->toBe('collection:recognition_lists');
});

it('defines a data-only structured recognition_lists collection (no route)', function () {
    $config = YAML::parse(File::get(recCollectionPathOrSkip()));

    // A list is a top-level entry; its categories are child entries → structured,
    // capped at 2 levels, multiple roots (no single fixed root).
    expect($config['structure'])->toBe(['root' => false, 'max_depth' => 2]);
    // Recognition lists are data-only relationship/filter targets — no public pages.
    expect($config)->not->toHaveKey('route');
    expect($config)->not->toHaveKey('template');
    expect($config['sites'])->toBe(['default']);
});

it('retires the flat recognition_lists taxonomy but keeps its classifying sub-taxonomies', function () {
    recCollectionPathOrSkip();

    /** @var Store $taxonomyStore */
    $taxonomyStore = Stache::store('taxonomies');
    $taxonomyDir = $taxonomyStore->directory();

    // Hard replace on the shared handle.
    expect(File::exists($taxonomyDir.'recognition_lists.yaml'))->toBeFalse();

    // The series / publication / data_source classification still rides on the list
    // entry via its terms fields, so those taxonomies must survive.
    expect(File::exists($taxonomyDir.'recognition_lists_series.yaml'))->toBeTrue();
    expect(File::exists($taxonomyDir.'recognition_lists_publications.yaml'))->toBeTrue();
    expect(File::exists($taxonomyDir.'recognition_lists_data_sources.yaml'))->toBeTrue();
});

it('migrates each term to a list entry preserving its classification', function () {
    recCollectionPathOrSkip();

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'recognition_lists');
    /** @var EntryQueryBuilder $query */
    $query = $query->where('slug', 'best-doctors-in-america-2020');
    /** @var Statamic\Entries\Entry $list */
    $list = $query->first();

    expect($list)->not->toBeNull();
    expect($list->value('blueprint'))->toBe('recognition_list');
    expect($list->get('list_date'))->toBe('2020-03-01');
    expect($list->get('series'))->toBe('arkansas-times-best-doctors-in-america');
    expect($list->get('data_source'))->toBe('recognition_lists_data_sources::best-doctors-inc');
    // Legacy hidden keys are preserved (zero data loss) even though not surfaced.
    expect($list->get('publication'))->toBe('arkansas-times');
});

it('nests a migrated category as a child entry under its list', function () {
    recCollectionPathOrSkip();

    /** @var EntryQueryBuilder $listQuery */
    $listQuery = Entry::query();
    /** @var EntryQueryBuilder $listQuery */
    $listQuery = $listQuery->where('collection', 'recognition_lists');
    /** @var EntryQueryBuilder $listQuery */
    $listQuery = $listQuery->where('slug', 'best-doctors-in-america-2020');
    /** @var Statamic\Entries\Entry|null $listEntry */
    $listEntry = $listQuery->first();
    $listId = $listEntry?->id();

    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = Entry::query();
    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = $categoryQuery->where('collection', 'recognition_lists');
    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = $categoryQuery->where('slug', 'best-doctors-in-america-2020-orthopaedic-surgery');
    /** @var Statamic\Entries\Entry $category */
    $category = $categoryQuery->first();

    expect($category)->not->toBeNull();
    expect($category->value('blueprint'))->toBe('recognition_list_category');

    // The structure tree records the list as the category's structural parent.
    /** @var Store $treeStore */
    $treeStore = Stache::store('collection-trees');
    $treePath = $treeStore->directory().'default/recognition_lists.yaml';
    /** @var array<int, array<string, mixed>> $tree */
    $tree = YAML::parse(File::get($treePath))['tree'];
    $listNode = collect($tree)->firstWhere('entry', $listId);
    expect($listNode)->not->toBeNull();
    /** @var array<int, array<string, mixed>> $children */
    $children = $listNode['children'] ?? [];
    expect(collect($children)->pluck('entry'))->toContain($category->id());
});

it('rewrites the provider replicator into queryable top-level relationships', function () {
    recCollectionPathOrSkip();

    /** @var EntryQueryBuilder $personQuery */
    $personQuery = Entry::query();
    /** @var EntryQueryBuilder $personQuery */
    $personQuery = $personQuery->where('collection', 'people');
    /** @var EntryQueryBuilder $personQuery */
    $personQuery = $personQuery->where('slug', 'c-lowry-barnes');
    /** @var Statamic\Entries\Entry $person */
    $person = $personQuery->first();
    expect($person)->not->toBeNull();

    /** @var array<int, string> $refs */
    $refs = $person->get('recognition_lists');
    expect($refs)->not->toBeEmpty();

    // Every reference is a flat recognition_lists entry id (not a replicator map):
    // a map would not resolve to a recognition_lists entry below.
    foreach ($refs as $ref) {
        /** @var Statamic\Entries\Entry|null $referenced */
        $referenced = Entry::find($ref);
        expect($referenced?->collectionHandle())->toBe('recognition_lists');
    }

    // Membership is queryable via whereJsonOverlaps — the foundation UAMS-Web/uams-statamic-archive#1176 rides on.
    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = Entry::query();
    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = $categoryQuery->where('collection', 'recognition_lists');
    /** @var EntryQueryBuilder $categoryQuery */
    $categoryQuery = $categoryQuery->where('slug', 'best-doctors-in-america-2020-orthopaedic-surgery');
    /** @var Statamic\Entries\Entry|null $categoryEntry */
    $categoryEntry = $categoryQuery->first();
    $categoryId = $categoryEntry?->id();

    /** @var EntryQueryBuilder $peopleQuery */
    $peopleQuery = Entry::query();
    /** @var EntryQueryBuilder $peopleQuery */
    $peopleQuery = $peopleQuery->where('collection', 'people');
    /** @var EntryQueryBuilder $peopleQuery */
    $peopleQuery = $peopleQuery->whereJsonOverlaps('recognition_lists', [$categoryId]);
    /** @var Collection<int, Statamic\Entries\Entry> $hits */
    $hits = $peopleQuery->get();
    expect($hits->map(fn (Statamic\Entries\Entry $entry) => $entry->slug()))->toContain('c-lowry-barnes');
});
