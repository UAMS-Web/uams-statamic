<?php

declare(strict_types=1);

use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Query\Builder;
use Symfony\Component\Yaml\Yaml;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1863: `people_groups` and `logos_item_categories` were flat
 * taxonomies, but the WordPress source they receive is hierarchical
 * (`uamspeople-categories` alone carries 9,261 assignments, department to
 * division to group). UAMS-Web/uams-statamic-archive#252 settled that hierarchy is delivered by native
 * structured collections, so both convert — following the `post_categories`
 * pattern (UAMS-Web/uams-statamic-archive#1433) — and `UAMS-Web/wordpress-importer#13` can then create
 * parented entries in each.
 *
 * These assert the shipped configuration and blueprints: that each is a
 * structured collection with a route, that the flat taxonomy is gone, and that
 * every blueprint field which pointed at a taxonomy now points at the
 * collection. The hierarchy and query behavior are covered by
 * `PeopleGroupsHierarchyTest`.
 *
 * @command  php artisan test --compact tests/Feature/PeopleGroupsCollectionTest.php
 */
/**
 * Every entry in a collection, as concrete entries.
 *
 * `queryEntries()->get()` is typed `mixed`, so the walk is narrowed once here
 * rather than at each call site.
 *
 * @param  string  $handle  The collection handle.
 * @return list<StatamicEntry> The collection's entries.
 */
function collectionEntries(string $handle): array
{
    $collection = Collection::findByHandle($handle);

    if ($collection === null) {
        throw new RuntimeException("Collection {$handle} should exist.");
    }

    $entries = [];

    $results = mustBe(Builder::class, $collection->queryEntries(), "the {$handle} entries query")->get();

    foreach (is_iterable($results) ? $results : [] as $entry) {
        if (! $entry instanceof StatamicEntry) {
            throw new RuntimeException("Collection {$handle} yielded a non-entry.");
        }

        $entries[] = $entry;
    }

    return $entries;
}

it('is a structured collection, not a taxonomy', function (string $handle): void {
    $collection = Collection::findByHandle($handle);

    expect($collection)->not->toBeNull("Collection {$handle} should exist.");

    if ($collection === null) {
        return;
    }

    // Structured — the whole point of the conversion: the importer needs to
    // create parented entries, which a flat taxonomy cannot express.
    expect($collection->hasStructure())->toBeTrue();

    // The flat taxonomy is gone, so nothing can resolve the old source.
    expect(Taxonomy::findByHandle($handle))->toBeNull();
})->with(['people_groups', 'logos_item_categories']);

it('carries a hierarchical route', function (string $handle, string $segment): void {
    $collection = Collection::findByHandle($handle);

    if ($collection === null) {
        throw new RuntimeException("Collection {$handle} should exist.");
    }

    $route = $collection->route('default');

    if (! is_string($route)) {
        throw new RuntimeException("Collection {$handle} declares no route.");
    }

    // `parent_uri` is what makes the route hierarchical: a nested entry's URL
    // is built from its ancestors rather than being flat.
    expect($route)->toContain('parent_uri');
    expect($route)->toContain($segment);
})->with([
    ['people_groups', 'group'],
    ['logos_item_categories', 'logo-category'],
]);

it('preserves the existing terms as entries', function (string $handle, int $expected, string $slug): void {
    $collection = Collection::findByHandle($handle);

    if ($collection === null) {
        throw new RuntimeException("Collection {$handle} should exist.");
    }

    $slugs = [];

    foreach (collectionEntries($handle) as $entry) {
        $slugs[] = mustBeString($entry->slug(), 'entry slug');
    }

    expect($slugs)->toHaveCount($expected);
    // Slugs are preserved, so nothing that referenced a term by slug is orphaned
    // by the conversion itself.
    expect($slugs)->toContain($slug);
})->with([
    ['people_groups', 8, 'executive-leadership'],
    ['logos_item_categories', 4, 'colleges'],
]);

it('points every blueprint field at the collection instead of the taxonomy', function (string $path, string $handle, string $collection): void {
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(resource_path($path));

    $field = findFieldConfig($parsed, $handle);

    if ($field === null) {
        throw new RuntimeException("No `{$handle}` field in {$path}.");
    }

    expect($field['type'] ?? null)->toBe('entries');
    expect($field['collections'] ?? [])->toContain($collection);
    // The retired taxonomy binding must be gone, not merely supplemented.
    expect($field)->not->toHaveKey('taxonomies');
})->with([
    ['blueprints/collections/people/person.yaml', 'people_groups', 'people_groups'],
    ['fieldsets/uamsweb_common_people_block_set.yaml', 'group_single', 'people_groups'],
    ['fieldsets/uamsweb_common_people_block_set.yaml', 'group_multiple', 'people_groups'],
    ['blueprints/collections/logo_list_items/logo_list_item.yaml', 'logos_item_categories', 'logos_item_categories'],
    ['fieldsets/uamsweb_block_logo_list.yaml', 'logo_list_filter_category', 'logos_item_categories'],
]);

it('sources each search index from the collection', function (string $handle): void {
    /** @var array<string, array<string, mixed>> $indexes */
    $indexes = config('statamic.search.indexes');

    expect($indexes)->toHaveKey($handle);
    expect($indexes[$handle]['searchables'] ?? null)->toBe('collection:'.$handle);
})->with(['people_groups', 'logos_item_categories']);

it('moves each blueprint out of the taxonomies namespace', function (string $handle, string $file): void {
    expect(file_exists(resource_path("blueprints/collections/{$handle}/{$file}")))->toBeTrue();
    expect(file_exists(resource_path("blueprints/taxonomies/{$handle}/{$file}")))->toBeFalse();
})->with([
    ['people_groups', 'people_group.yaml'],
    ['logos_item_categories', 'logos_item_category.yaml'],
]);

/**
 * Locate a field's config by handle anywhere in a blueprint or fieldset tree.
 *
 * Blueprints nest fields under tabs/sections while fieldsets list them flat, so
 * the search is a recursive walk rather than a fixed path.
 *
 * @param  array<array-key, mixed>  $node  The parsed YAML node to search.
 * @param  string  $handle  The field handle wanted.
 * @return ?array<string, mixed> The field's `field` config, or null when absent.
 */
function findFieldConfig(array $node, string $handle): ?array
{
    if (($node['handle'] ?? null) === $handle && is_array($node['field'] ?? null)) {
        /** @var array<string, mixed> $field */
        $field = $node['field'];

        return $field;
    }

    foreach ($node as $value) {
        if (! is_array($value)) {
            continue;
        }

        $found = findFieldConfig($value, $handle);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}
