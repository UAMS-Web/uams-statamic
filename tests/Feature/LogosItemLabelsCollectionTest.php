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
 * Coverage for UAMS-Web/uams-statamic-archive#2533: `logos_item_labels` was a flat taxonomy, and a flat term
 * exists on every site its taxonomy is enabled for, so the importer had to prefix
 * each title with its site name. `UAMS-Web/wordpress-importer#1215` settled that
 * labels import as entries in a site-scoped collection, following UAMS-Web/uams-statamic-archive#1863 and UAMS-Web/uams-statamic-archive#2532.
 *
 * These assert the shipped configuration: a structured, non-propagating collection
 * with its blueprint, the nine terms carried over as entries, both fields that
 * referenced the taxonomy now picking entries, and no taxonomy left to resolve the
 * old source. The label filter and site scoping at query time are covered by
 * `Scopes/LogoItemsWithLabelsScopeTest`.
 *
 * @command  php artisan test --compact tests/Feature/LogosItemLabelsCollectionTest.php
 */

/** The terms the taxonomy held, keyed by slug, with their plain titles. */
const LOGOS_ITEM_LABEL_TERMS = [
    'college-athletics-program' => 'College Athletics Program',
    'college-intramural-sports' => 'College Intramural Sports',
    'high-school-athletics-program' => 'High School Athletics Program',
    'organization' => 'Organization',
    'professional-organization' => 'Professional Organization',
    'recreational-club-or-organization' => 'Recreational Club or Organization',
    'sporting-event' => 'Sporting Event',
    'uams-college-of-medicine-basic-science-departments' => 'UAMS College of Medicine Basic Science Departments',
    'uams-college-of-medicine-clinical-departments' => 'UAMS College of Medicine Clinical Departments',
];

/**
 * Locate a field's config by handle anywhere in a blueprint or fieldset tree.
 *
 * @param  array<array-key, mixed>  $node  The parsed YAML node to search.
 * @param  string  $handle  The field handle wanted.
 * @return ?array<string, mixed> The field's `field` config, or null when absent.
 */
function logosItemLabelsFieldConfig(
    array $node,
    string $handle
): ?array {
    // Return this node's field config when it is the wanted handle
    if (($node['handle'] ?? null) === $handle && is_array($node['field'] ?? null)) {
        /** @var array<string, mixed> $field */
        $field = $node['field'];

        return $field;
    }

    // Walk nested tabs, sections and field lists
    foreach ($node as $value) {
        if (! is_array($value)) {
            continue;
        }

        $found = logosItemLabelsFieldConfig($value, $handle);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

it('is a structured, site-scoped collection with its blueprint, and the taxonomy is retired', function (): void {
    $this->requireContentCollection('logos_item_labels');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('logos_item_labels');

    expect($collection->hasStructure())->toBeTrue()
        // Site-scoped: an entry is authored on one site and never copied to the others.
        ->and($collection->propagate())->toBeFalse()
        // The same 31 sites the taxonomy was enabled on.
        ->and(mustBe(Illuminate\Support\Collection::class, $collection->sites(), 'logos_item_labels sites')->all())
        ->toHaveCount(31)
        ->toContain('default', 'news', 'tri')
        // Labels have no public page, as the terms had none.
        ->and($collection->route('default'))->toBeNull()
        ->and($collection->entryBlueprint('logos_item_label'))->not->toBeNull();

    // The flat taxonomy is gone, so nothing can resolve the old source.
    expect(Taxonomy::findByHandle('logos_item_labels'))->toBeNull();
});

it('carries every term over as a default-site entry with its slug and plain title', function (): void {
    $this->requireContentCollection('logos_item_labels');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('logos_item_labels');

    $titles = [];
    $results = mustBe(Builder::class, $collection->queryEntries(), 'the logos_item_labels entries query')->get();

    foreach (is_iterable($results) ? $results : [] as $entry) {
        expect($entry)->toBeInstanceOf(StatamicEntry::class);

        if ($entry instanceof StatamicEntry) {
            // The terms were site-agnostic seed data with no references, so they
            // land on `default`, as UAMS-Web/uams-statamic-archive#1863's terms did.
            expect($entry->locale())->toBe('default');
            $titles[mustBeString($entry->slug(), 'entry slug')] = $entry->get('title');
        }
    }

    ksort($titles);

    expect($titles)->toBe(LOGOS_ITEM_LABEL_TERMS);
});

it('points both fields that referenced the taxonomy at the collection', function (string $path, string $handle): void {
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(resource_path($path));
    $field = logosItemLabelsFieldConfig($parsed, $handle);

    expect($field)->not->toBeNull();

    if ($field !== null) {
        expect($field['type'] ?? null)->toBe('entries')
            ->and($field['collections'] ?? [])->toBe(['logos_item_labels'])
            // The retired taxonomy binding must be gone, not merely supplemented.
            ->and($field)->not->toHaveKey('taxonomies');
    }
})->with([
    'the item field' => ['blueprints/collections/logo_list_items/logo_list_item.yaml', 'logos_item_labels'],
    'the block filter' => ['fieldsets/uamsweb_block_logo_list.yaml', 'logo_list_filter_label'],
]);

it('moves the blueprint out of the taxonomies namespace', function (): void {
    expect(file_exists(resource_path('blueprints/collections/logos_item_labels/logos_item_label.yaml')))->toBeTrue()
        ->and(file_exists(resource_path('blueprints/taxonomies/logos_item_labels/logos_item_label.yaml')))->toBeFalse();
});

it('no longer attaches the retired taxonomy to logo list items', function (): void {
    $this->requireContentCollection('logo_list_items');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::findByHandle('logo_list_items');

    $handles = collect(mustBe(Illuminate\Support\Collection::class, $collection->taxonomies(), 'logo_list_items taxonomies')->all())
        ->map(fn (mixed $taxonomy): mixed => $taxonomy instanceof Statamic\Taxonomies\Taxonomy ? $taxonomy->handle() : $taxonomy)
        ->all();

    expect($handles)->not->toContain('logos_item_labels');
});
