<?php

declare(strict_types=1);

use Statamic\Entries\EntryCollection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2291. This file was gated to the serial tail on
// UAMS-Web/uams-statamic-archive#1300: a worker asserting before the shared Stache was fully warmed. Every worker
// has warmed its own cache from one snapshot since UAMS-Web/uams-statamic-archive#1590, so the race the gate named
// does not exist. Three parity-matched parallel bands with this file in them produced
// zero failures (macOS, 2026-09-16); runs refute and never establish, and
// tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#42: the People Showcase honors the block's `order_by` field by
 * mapping it to the `collection:people` `sort` modifier in the shared
 * `components/uamsweb_people` partial, and the partial's outer wrapper class is
 * overridable so the page-builder block can keep its `module-body` markup.
 *
 * No mutable PHP surface: the system under test is the Antlers partial
 * `components/uamsweb_people`, a template with no backing App\ PHP class. Both
 * tests self-skip without the content/ submodule (the `people` collection).
 *
 * @command  php artisan test --compact tests/Feature/PeopleShowcaseSortOrderTest.php
 */

/**
 * A people group with several `default`-site members whose last-name and
 * first-name orderings differ.
 *
 * UAMS-Web/uams-statamic-archive#1863 converted `people_groups` from a flat taxonomy to a structured
 * collection, so this is the group *entry's* slug; the block and the query below
 * both resolve it to an entry id, because the reference is now an entries field
 * rather than a taxonomy term.
 *
 * @var string
 */
const PEOPLE_SHOWCASE_SORT_ORDER_GROUP = 'executive-leadership-administration';

/**
 * The group entry's id, resolved from its slug so the test carries no generated
 * UUID.
 */
function peopleShowcaseSortOrderGroupId(): string
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();

    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'people_groups');

    /** @var EntryQueryBuilder $query */
    $query = $query->where('slug', PEOPLE_SHOWCASE_SORT_ORDER_GROUP);

    $group = $query->first();

    if (! $group instanceof Statamic\Entries\Entry) {
        throw new RuntimeException(
            'No `people_groups` entry with slug "'.PEOPLE_SHOWCASE_SORT_ORDER_GROUP.'".'
        );
    }

    return mustBeString($group->id(), 'the people group id');
}

/**
 * Resolve a collection that lives in the `content/` submodule, skipping the
 * test when it is absent (the Stache returns `null` without the submodule).
 *
 * Mirrors `Tests\Concerns\RequiresContentSubmodule::requireContentCollection()`:
 * the canonical environments (`ci.yml` phpunit job, `local-ci.mjs`, a normal
 * local checkout) all initialize `content/`, so this guard never trips there; a
 * bare worktree reports a clear `SKIP` instead of the `CollectionNotFoundException`
 * the `collection:people` tag would otherwise throw.
 *
 * @param  string  $handle  The collection handle (e.g. `people`, `posts`)
 */
function requirePeopleShowcaseSortOrderCollection(string $handle): void
{
    $collection = Collection::find($handle);

    if (! $collection) {
        ContentSubmodule::reportMissing("{$handle} collection");
    }
}

/**
 * Collapse a rendered or stored title to a comparable key, dropping the markup,
 * entities, `widont` non-breaking spaces, and credential punctuation that differ
 * between the two sources.
 *
 * @param  string  $value  Raw title (markup or stored string)
 * @return string Lowercased alphanumeric key
 */
function normalizePeopleShowcaseSortOrderTitle(string $value): string
{
    return \strtolower(
        (string) \preg_replace(
            '/[^a-z0-9]/i',
            '',
            \html_entity_decode(\strip_tags($value))
        )
    );
}

/**
 * Render the shared people component for a single group under the given
 * `order_by`, returning the rendered person titles in document order.
 *
 * @param  string  $orderBy  The block `order_by` option
 * @return array<int, string> Normalized person titles, in render order
 */
function renderPeopleShowcaseSortOrder(string $orderBy): array
{
    $html = (string) Antlers::parse(
        '{{ partial:components/uamsweb_people order_by="'.$orderBy.'" group_single="'.peopleShowcaseSortOrderGroupId().'" }}',
        [],
        true
    );

    \preg_match_all(
        '/uamspeople-box-title[^>]*>(.*?)<\/h3>/s',
        $html,
        $matches
    );

    return \array_map(
        fn (string $raw): string => normalizePeopleShowcaseSortOrderTitle($raw),
        $matches[1]
    );
}

/**
 * The group's members in a given collection-sort order, as normalized titles —
 * the expected render order for the matching `order_by`.
 *
 * @param  string  $field  The entry field to sort on
 * @return array<int, string> Normalized person titles, in sorted order
 */
function expectedPeopleShowcaseSortOrder(string $field): array
{
    // `Entry::query()` is typed as the contract `QueryBuilder` but is bound at
    // runtime to the concrete `EntryQueryBuilder` (which carries `where()`,
    // `whereTaxonomy()`, `orderBy()`, and an `EntryCollection`-returning
    // `get()`). The builder methods return `mixed`, so narrow each step.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();

    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'people');

    /** @var EntryQueryBuilder $query */
    $query = $query->where('published', true);

    /** @var EntryQueryBuilder $query */
    // `people_groups` is an entries reference now (UAMS-Web/uams-statamic-archive#1863), not a taxonomy, so
    // the membership test is a JSON-contains over the stored group ids.
    $query = $query->whereJsonContains('people_groups', peopleShowcaseSortOrderGroupId());

    /** @var EntryQueryBuilder $query */
    $query = $query->orderBy($field, 'asc');

    /** @var EntryCollection $entries */
    $entries = $query->get();

    $titles = [];

    foreach ($entries as $entry) {
        /** @var Statamic\Entries\Entry $entry */
        $title = $entry->get('title');
        $titles[] = normalizePeopleShowcaseSortOrderTitle(\is_string($title) ? $title : '');
    }

    return $titles;
}

beforeEach(function (): void {
    // Skip the whole suite when the `people` collection is missing — without it
    // the `collection:people` tag inside `components/uamsweb_people` throws a
    // `CollectionNotFoundException`, so the guard must run before any render.
    requirePeopleShowcaseSortOrderCollection('people');
});

/**
 * Each `order_by` option drives the collection sort: `sorttext` orders by
 * `sort_text_alpha`, `title` orders by the entry title, and the two differ.
 */
it('order_by drives the collection sort', function (): void {
    $bySortText = renderPeopleShowcaseSortOrder('sorttext');
    $byTitle = renderPeopleShowcaseSortOrder('title');

    // Sanity: the group is populated (the entries reference resolves).
    expect(\count($bySortText))->toBeGreaterThan(1);

    // Each option matches the equivalent collection-query ordering.
    expect($bySortText)->toBe(expectedPeopleShowcaseSortOrder('sort_text_alpha'));
    expect($byTitle)->toBe(expectedPeopleShowcaseSortOrder('title'));

    // The option actually changes the order (first-name vs last-name sort).
    expect($bySortText)->not->toBe($byTitle);
});

/**
 * The outer wrapper class defaults to `span-md` and is overridable by the caller
 * (the page-builder block passes `module-body`).
 */
it('wrapper class is overridable', function (): void {
    $default = (string) Antlers::parse(
        '{{ partial:components/uamsweb_people group_single="'.peopleShowcaseSortOrderGroupId().'" }}',
        [],
        true
    );

    expect($default)->toContain('<div class="span-md">');
    expect($default)->not->toContain('<div class="module-body">');

    $overridden = (string) Antlers::parse(
        '{{ partial:components/uamsweb_people group_single="'.peopleShowcaseSortOrderGroupId().'" people_wrapper_class="module-body" }}',
        [],
        true
    );

    expect($overridden)->toContain('<div class="module-body">');
    expect($overridden)->not->toContain('<div class="span-md">');
});
