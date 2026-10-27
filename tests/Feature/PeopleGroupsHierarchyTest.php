<?php

declare(strict_types=1);

use App\Scopes\LogoItemsInCategories;
use App\Scopes\PeopleInGroups;
use App\Support\Entries\EntryReferenceIds;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Query\Builder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Page;
use Statamic\Structures\Pages;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1863's hierarchy and query behavior: a parent group with
 * children resolves and renders, and the two scopes that replace the retired
 * `:taxonomy:` filters return the right members.
 *
 * Both collections are exercised, since the ticket converts both. Tree mechanics
 * are proven hermetically ({@see StacheIsolatedTestCase}) on the
 * `PostCategoryArchiveTest` pattern the `post_categories` conversion (UAMS-Web/uams-statamic-archive#1434)
 * established, so nothing depends on the shipped `content/` tree — which carries
 * the eight and four migrated entries flat, because the source terms record no
 * parenting to preserve.
 *
 * @command  php artisan test --compact tests/Feature/PeopleGroupsHierarchyTest.php
 */

/**
 * The collection with this handle, failing by name when it is missing.
 *
 * @param  string  $handle  The collection handle.
 * @return StatamicCollection The collection.
 */
function requireCollection(string $handle): StatamicCollection
{
    $collection = Collection::find($handle);

    if (! $collection instanceof StatamicCollection) {
        throw new RuntimeException("Collection {$handle} should exist.");
    }

    return $collection;
}

/**
 * The entry with this id, failing by name when it is missing.
 *
 * @param  string  $id  The entry id.
 * @return StatamicEntry The entry.
 */
function requireEntry(string $id): StatamicEntry
{
    $entry = Entry::find($id);

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException("Entry {$id} should exist.");
    }

    return $entry;
}

/**
 * The sorted titles of the entries a query returns.
 *
 * `Builder::get()` is typed `mixed`, so the walk is narrowed once here.
 *
 * @param  mixed  $entries  The query result.
 * @return array<int, string> Titles, sorted.
 */
function sortedTitles(mixed $entries): array
{
    $titles = [];

    foreach (is_iterable($entries) ? $entries : [] as $entry) {
        if ($entry instanceof StatamicEntry) {
            $titles[] = mustBeString($entry->get('title'), 'entry title');
        }
    }

    sort($titles);

    return $titles;
}

/**
 * Build a parent group with two child groups, plus three people filed under
 * them through the `people_groups` entries reference.
 */
function seedGroupHierarchy(): void
{
    /** @var StatamicCollection $groups */
    $groups = Collection::make('people_groups');
    $groups->routes("{{ depth == 1 ?= 'group' }}/{{ parent_uri }}/{{ slug }}");
    $groups->structureContents(['root' => false]);
    $groups->save();

    foreach ([
        ['pg-parent', 'executive-leadership', 'Executive Leadership', null],
        ['pg-deans', 'executive-leadership-deans', 'Executive Leadership — Deans', 'pg-parent'],
        ['pg-chancellor', 'executive-leadership-chancellor', 'Executive Leadership — Chancellor', 'pg-parent'],
    ] as [$id, $slug, $title, $parent]) {
        /** @var StatamicEntry $entry */
        $entry = Entry::make();
        $entry->collection('people_groups');
        $entry->id($id);
        $entry->slug($slug);
        $entry->data($parent === null ? ['title' => $title] : ['title' => $title, 'parent' => $parent]);
        $entry->save();
    }

    /** @var StatamicCollection $people */
    $people = Collection::make('people');
    $people->save();

    foreach ([
        ['person-kirk', 'james-kirk', 'James Kirk', ['pg-deans']],
        ['person-spock', 'spock', 'Spock', ['pg-chancellor']],
        ['person-uhura', 'nyota-uhura', 'Nyota Uhura', ['pg-deans', 'pg-chancellor']],
        ['person-scott', 'montgomery-scott', 'Montgomery Scott', []],
    ] as [$id, $slug, $title, $groupIds]) {
        /** @var StatamicEntry $person */
        $person = Entry::make();
        $person->collection('people');
        $person->id($id);
        $person->slug($slug);
        $person->data(['title' => $title, 'people_groups' => $groupIds]);
        $person->save();
    }

    /** @var StatamicCollection $structured */
    $structured = Collection::find('people_groups');
    /** @var CollectionStructure $structure */
    $structure = $structured->structure();
    /** @var Tree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => 'pg-parent', 'children' => [
            ['entry' => 'pg-chancellor'],
            ['entry' => 'pg-deans'],
        ]],
    ]);
    $tree->save();
}

/**
 * The direct child page ids of a group in the collection structure.
 *
 * @param  string  $parentId  The parent group's entry id.
 * @return array<int, string> The child entry ids, sorted.
 */
function groupChildIds(string $parentId): array
{
    /** @var StatamicCollection $groups */
    $groups = Collection::find('people_groups');
    /** @var CollectionStructure $structure */
    $structure = $groups->structure();
    /** @var Tree $tree */
    $tree = $structure->in('default');
    /** @var Illuminate\Support\Collection<int, Page> $flattened */
    $flattened = $tree->flattenedPages();

    $page = collect($flattened)->first(fn (Page $p): bool => $p->id() === $parentId);

    if ($page === null) {
        return [];
    }

    /** @var Pages $childPages */
    $childPages = $page->pages();

    $ids = [];

    $all = $childPages->all();

    foreach (is_iterable($all) ? $all : [] as $child) {
        if ($child instanceof Page) {
            $ids[] = mustBeString($child->id(), 'child page id');
        }
    }

    sort($ids);

    return $ids;
}

/**
 * The titles a scope returns for a `people` query.
 *
 * @param  array<string, mixed>  $values  The scope's tag parameters.
 * @return array<int, string> Matching people's titles, sorted.
 */
function peopleMatching(array $values): array
{
    $query = mustBe(Builder::class, requireCollection('people')->queryEntries(), 'people entries query');

    (new PeopleInGroups)->apply($query, $values);

    return sortedTitles($query->get());
}

// ---------------------------------------------------------------------------
// Hierarchy — a parent with children resolves
// ---------------------------------------------------------------------------

it('surfaces a group\'s immediate child groups from the structure', function () {
    seedGroupHierarchy();

    expect(groupChildIds('pg-parent'))->toBe(['pg-chancellor', 'pg-deans']);
});

it('shows no child groups for a leaf group', function () {
    seedGroupHierarchy();

    expect(groupChildIds('pg-deans'))->toBe([]);
});

it('builds a nested group URL from its parent chain', function () {
    seedGroupHierarchy();

    $uri = mustBeString(requireEntry('pg-deans')->uri(), 'the child group URI');

    // The hierarchical route is what makes the collection a hierarchy on the
    // front end, not just in the tree — `parent_uri` puts the child under its
    // parent rather than at the top level.
    expect($uri)->toContain('executive-leadership');
    expect($uri)->toEndWith('executive-leadership-deans');
});

// ---------------------------------------------------------------------------
// Hierarchy — a parent with children renders
// ---------------------------------------------------------------------------

it('wires each archive to its collection\'s structure children', function (string $template, string $collection) {
    // The `:parent:is="id"` query resolves children off the same structure the
    // tests above read directly. It is asserted against the template rather than
    // rendered, because the in-memory Stache does not resolve `:parent:is` — the
    // same split `PostCategoryArchiveTest` documents, which leaves the rendered
    // `:parent:is` path to a disk-loaded render test.
    $source = (string) file_get_contents(resource_path("views/{$template}/show.antlers.html"));

    expect($source)->toContain(':parent:is="id"');
    expect($source)->toContain("collection:{$collection}");
})->with([
    ['people_groups', 'people_groups'],
    ['logos_item_categories', 'logos_item_categories'],
]);

it('renders the people filed under a group', function () {
    seedGroupHierarchy();

    $html = (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/people_groups/show.antlers.html')),
        mustBeArray(requireEntry('pg-deans')->toAugmentedArray(), 'the group entry'),
        true,
    );

    expect($html)->toContain('James Kirk');
    expect($html)->toContain('Nyota Uhura');
    // Filed only under the sibling group.
    expect($html)->not->toContain('Spock');
});

// ---------------------------------------------------------------------------
// The scope replacing the retired `:taxonomy:people_groups` filter
// ---------------------------------------------------------------------------

it('matches the people in a single group', function () {
    seedGroupHierarchy();

    expect(peopleMatching(['groups' => 'pg-deans']))->toBe(['James Kirk', 'Nyota Uhura']);
});

it('matches the people in any of several groups', function () {
    seedGroupHierarchy();

    // `any` semantics, matching what the `:taxonomy:` parameter defaulted to.
    expect(peopleMatching(['groups' => ['pg-deans', 'pg-chancellor']]))
        ->toBe(['James Kirk', 'Nyota Uhura', 'Spock']);
});

it('applies no constraint when no group is selected', function () {
    seedGroupHierarchy();

    // Parity with the retired `:taxonomy:people_groups` parameter, which resolved
    // to `void` and was skipped — an unfiltered People block listed EVERY person,
    // including the one in no group at all. Matching nothing would look safer
    // while silently emptying every unfiltered block on the site.
    $everyone = ['James Kirk', 'Montgomery Scott', 'Nyota Uhura', 'Spock'];

    expect(peopleMatching(['groups' => null]))->toBe($everyone);
    expect(peopleMatching(['groups' => []]))->toBe($everyone);
    expect(peopleMatching([]))->toBe($everyone);
});

it('resolves augmented entry objects, not just raw ids', function () {
    seedGroupHierarchy();

    // The ordinary Antlers path augments an `entries` field to Entry objects.
    // Filtering those as strings would silently match nobody, which reads as a
    // content problem rather than a code one.
    expect(peopleMatching(['groups' => [Entry::find('pg-deans')]]))
        ->toBe(['James Kirk', 'Nyota Uhura']);
});

it('refuses an augmented entries field instead of silently matching nothing', function () {
    seedGroupHierarchy();

    // `Entries::augment()` returns a StatusQueryBuilder, NOT a list of entries, so
    // a `:param="field"` binding hands the scope a builder. Returning no ids for
    // it would empty the block silently — which reads as missing content rather
    // than as a bug, and is exactly how the Logo List binding shipped broken.
    // Templates pass `| raw`; forgetting must fail loudly.
    $builder = mustBe(Builder::class, requireCollection('people_groups')->queryEntries(), 'group entries query')
        ->whereIn('id', ['pg-deans']);

    expect(fn () => EntryReferenceIds::from($builder))
        ->toThrow(RuntimeException::class, 'arrived augmented');
});

// ---------------------------------------------------------------------------
// The logo-category scope's three-way logic
// ---------------------------------------------------------------------------

/**
 * Seed logo categories and items, then return the titles a logic mode matches.
 *
 * @param  array<string, mixed>  $values  The scope's tag parameters.
 * @return array<int, string> Matching item titles, sorted.
 */
function logoItemsMatching(array $values): array
{
    if (Collection::find('logo_list_items') === null) {
        /** @var StatamicCollection $categories */
        $categories = Collection::make('logos_item_categories');
        $categories->structureContents(['root' => false]);
        $categories->save();

        /** @var StatamicCollection $items */
        $items = Collection::make('logo_list_items');
        $items->save();

        foreach ([
            ['li-a', 'alpha', 'Alpha', ['lc-colleges']],
            ['li-b', 'beta', 'Beta', ['lc-institutes']],
            ['li-c', 'gamma', 'Gamma', ['lc-colleges', 'lc-institutes']],
        ] as [$id, $slug, $title, $categoryIds]) {
            /** @var StatamicEntry $item */
            $item = Entry::make();
            $item->collection('logo_list_items');
            $item->id($id);
            $item->slug($slug);
            $item->data(['title' => $title, 'logos_item_categories' => $categoryIds]);
            $item->save();
        }
    }

    $query = mustBe(Builder::class, requireCollection('logo_list_items')->queryEntries(), 'logo item entries query');

    (new LogoItemsInCategories)->apply($query, $values);

    return sortedTitles($query->get());
}

it('matches items in any selected category', function () {
    expect(logoItemsMatching(['categories' => ['lc-colleges', 'lc-institutes'], 'logic' => 'any']))
        ->toBe(['Alpha', 'Beta', 'Gamma']);
});

it('matches only items in every selected category under all logic', function () {
    expect(logoItemsMatching(['categories' => ['lc-colleges', 'lc-institutes'], 'logic' => 'all']))
        ->toBe(['Gamma']);
});

it('excludes items in a selected category under not logic', function () {
    expect(logoItemsMatching(['categories' => ['lc-colleges'], 'logic' => 'not']))
        ->toBe(['Beta']);
});

it('defaults to any logic when none is given', function () {
    expect(logoItemsMatching(['categories' => ['lc-institutes']]))->toBe(['Beta', 'Gamma']);
});

it('applies no constraint when no category is selected', function () {
    // Same parity point as the people scope above.
    expect(logoItemsMatching(['categories' => [], 'logic' => 'any']))
        ->toBe(['Alpha', 'Beta', 'Gamma']);
});
