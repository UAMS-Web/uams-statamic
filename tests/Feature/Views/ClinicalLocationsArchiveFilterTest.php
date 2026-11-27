<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Render coverage for the `clinical_locations` archive's search, region and type
 * filters, sort selector, result count and reset (UAMS-Web/uams-statamic#1579):
 * resources/views/clinical_locations/_listing.antlers.html and the
 * `clinical_locations_archive_filter` scope it runs.
 *
 * Built in an isolated Stache so the `collection:clinical_locations` tag and the
 * scope run against real entries. The query parameters reach the template through
 * the cascade `get` variable on a live request; here they are passed explicitly to
 * `Antlers::parse`.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalLocationsArchiveFilterTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // The two facet taxonomies and their terms.
    foreach (['regions' => 'Regions', 'location_types' => 'Location Types'] as $handle => $title) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($handle);
        $taxonomy->title($title);
        $taxonomy->save();
    }
    foreach ([
        ['regions', 'central-arkansas', 'Central Arkansas'],
        ['regions', 'northwest-arkansas', 'Northwest Arkansas'],
        ['location_types', 'hospital', 'Hospital'],
        ['location_types', 'pharmacy', 'Pharmacy'],
    ] as [$taxonomy, $slug, $title]) {
        /** @var Statamic\Taxonomies\Term $term */
        $term = Term::make();
        $term->taxonomy($taxonomy);
        $term->slug($slug);
        /** @var LocalizedTerm $localizedTerm */
        $localizedTerm = $term->inDefaultLocale();
        $localizedTerm->data(['title' => $title]);
        $term->saveQuietly();
    }

    // A structured locations collection, so a child location can inherit its region.
    $collection = CollectionFacade::make('clinical_locations');
    $collection->title('Clinical Locations');
    $collection->structureContents(['root' => false]);
    $collection->save();

    // A minimal location blueprint: the card fields the list renders plus the facets.
    $blueprint = Blueprint::make('location');
    $blueprint->setNamespace('collections.clinical_locations');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'regions', 'field' => ['type' => 'terms', 'taxonomies' => ['regions'], 'max_items' => 1]],
            ['handle' => 'location_types', 'field' => ['type' => 'terms', 'taxonomies' => ['location_types']]],
        ]]]]],
    ]);
    $blueprint->save();

    // Three standalone locations and one child that inherits Central Arkansas.
    foreach ([
        'main' => ['UAMS Medical Center', 'central-arkansas', ['hospital']],
        'pharmacy' => ['Outpatient Pharmacy', 'central-arkansas', ['pharmacy']],
        'fayetteville' => ['Northwest Clinic', 'northwest-arkansas', []],
        'child' => ['Medical Center Pharmacy Annex', null, ['pharmacy']],
    ] as $slug => [$title, $region, $types]) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection('clinical_locations');
        $entry->blueprint('location');
        $entry->id('loc-'.$slug);
        $entry->slug($slug);
        $entry->published(true);
        $entry->data(array_filter([
            'title' => $title,
            'regions' => $region,
            'location_types' => $types,
        ], static fn (mixed $value): bool => $value !== null && $value !== []));
        $entry->saveQuietly();
    }

    // Nest the child under the main campus so it has no region of its own.
    /** @var Collection $treeCollection */
    $treeCollection = CollectionFacade::find('clinical_locations');
    /** @var CollectionStructure $treeStructure */
    $treeStructure = $treeCollection->structure();
    /** @var Tree $tree */
    $tree = $treeStructure->makeTree('default', [
        ['entry' => 'loc-main', 'children' => [['entry' => 'loc-child']]],
        ['entry' => 'loc-pharmacy'],
        ['entry' => 'loc-fayetteville'],
    ]);
    $tree->save();
});

/**
 * Render the locations listing partial with the given `get` query params.
 *
 * @param  array<string, string>  $get
 */
function renderLocationsArchive(array $get = []): string
{
    return (string) Antlers::parse(
        '{{ partial:clinical_locations/listing }}',
        ['get' => $get, 'url' => '/health/locations'],
        true
    );
}

/**
 * The location titles in the order the listing renders them.
 *
 * @return list<string>
 */
function renderedLocationTitles(string $html): array
{
    // Read each card's name heading; a child's "Part of" subtitle is not one.
    preg_match_all('~<h2 class="card-title h5"[^>]*>([^<]*)</h2>~', $html, $matches);

    return $matches[1];
}

it('lists every location in title order with the full count and no filter state', function (): void {
    $out = renderLocationsArchive();

    expect(renderedLocationTitles($out))->toBe([
        'Medical Center Pharmacy Annex',
        'Northwest Clinic',
        'Outpatient Pharmacy',
        'UAMS Medical Center',
    ])
        ->and($out)->toContain('<span class="locations-result-count">4 locations</span>')
        ->and($out)->not->toContain('locations-selections')
        ->and($out)->toContain('<option value="title_asc" selected>');
});

it('renders the region and type options from their taxonomies', function (): void {
    $out = renderLocationsArchive();

    expect($out)->toContain('<option value="central-arkansas">Central Arkansas</option>')
        ->and($out)->toContain('<option value="northwest-arkansas">Northwest Arkansas</option>')
        ->and($out)->toContain('<option value="hospital">Hospital</option>')
        ->and($out)->toContain('<option value="pharmacy">Pharmacy</option>');
});

it('reverses the order for the Z to A sort and ignores an unknown sort key', function (): void {
    expect(renderedLocationTitles(renderLocationsArchive(['sort' => 'title_desc'])))->toBe([
        'UAMS Medical Center',
        'Outpatient Pharmacy',
        'Northwest Clinic',
        'Medical Center Pharmacy Annex',
    ])
        ->and(renderLocationsArchive(['sort' => 'title_desc']))->toContain('<option value="title_desc" selected>')
        ->and(renderedLocationTitles(renderLocationsArchive(['sort' => 'date:desc'])))->toBe([
            'Medical Center Pharmacy Annex',
            'Northwest Clinic',
            'Outpatient Pharmacy',
            'UAMS Medical Center',
        ]);
});

it('narrows by a case-insensitive title search and counts the matches', function (): void {
    $out = renderLocationsArchive(['q' => 'PHARMACY']);

    expect(renderedLocationTitles($out))->toBe(['Medical Center Pharmacy Annex', 'Outpatient Pharmacy'])
        ->and($out)->toContain('<span class="locations-result-count">2 locations</span>')
        ->and($out)->toContain('value="PHARMACY"')
        ->and($out)->toContain('<li class="locations-selection">Search: PHARMACY</li>');
});

it('narrows by region, including a child that inherits its region', function (): void {
    $out = renderLocationsArchive(['region' => 'central-arkansas']);

    expect(renderedLocationTitles($out))->toBe([
        'Medical Center Pharmacy Annex',
        'Outpatient Pharmacy',
        'UAMS Medical Center',
    ])
        ->and($out)->toContain('<span class="locations-result-count">3 locations</span>')
        ->and($out)->toContain('<option value="central-arkansas" selected>')
        ->and($out)->toContain('<li class="locations-selection">Region: Central Arkansas</li>');
});

it('narrows by location type', function (): void {
    $out = renderLocationsArchive(['type' => 'hospital']);

    expect(renderedLocationTitles($out))->toBe(['UAMS Medical Center'])
        ->and($out)->toContain('<span class="locations-result-count">1 location</span>')
        ->and($out)->toContain('<li class="locations-selection">Location type: Hospital</li>');
});

it('combines a search term with a region and a type', function (): void {
    $out = renderLocationsArchive(['q' => 'pharmacy', 'region' => 'central-arkansas', 'type' => 'pharmacy']);

    expect(renderedLocationTitles($out))->toBe(['Medical Center Pharmacy Annex', 'Outpatient Pharmacy'])
        ->and($out)->toContain('<span class="locations-result-count">2 locations</span>');

    // The same term in another region matches nothing.
    $none = renderLocationsArchive(['q' => 'pharmacy', 'region' => 'northwest-arkansas']);
    expect(renderedLocationTitles($none))->toBe([]);
});

it('shows the filtered empty state with a way back to the full list', function (): void {
    $out = renderLocationsArchive(['q' => 'no such location']);

    expect(renderedLocationTitles($out))->toBe([])
        ->and($out)->toContain('<span class="locations-result-count">0 locations</span>')
        ->and($out)->toContain('No locations match these filters.')
        ->and($out)->toContain('<a href="/health/locations" class="locations-filter-reset">Show all locations</a>')
        ->and($out)->not->toContain('No locations found.');
});

it('resets to the unfiltered archive root, and blank parameters filter nothing', function (): void {
    $filtered = renderLocationsArchive(['q' => 'pharmacy', 'region' => 'central-arkansas']);

    // Reset is a link to the archive root, which carries no filter.
    expect($filtered)->toContain('<a href="/health/locations" class="btn btn-outline-primary locations-filter-reset">Reset</a>')
        ->and($filtered)->toContain('action="/health/locations"');

    // What the root renders, and what a form submitted with every field blank renders.
    foreach ([[], ['q' => '', 'region' => '', 'type' => '', 'sort' => 'title_asc']] as $get) {
        $out = renderLocationsArchive($get);
        expect(renderedLocationTitles($out))->toHaveCount(4)
            ->and($out)->toContain('4 locations')
            ->and($out)->not->toContain('locations-selections');
    }
});

it('escapes every value reflected from the query string', function (): void {
    $out = renderLocationsArchive(['q' => '<script>alert(1)</script>']);

    expect($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->toContain('&lt;script&gt;');
});
