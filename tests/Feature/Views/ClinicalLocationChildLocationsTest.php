<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Render coverage for "Additional Clinics Within" on the `clinical_locations` profile
 * (UAMS-Web/uams-statamic#1578), in
 * {@see clinical_locations/_child_locations.antlers.html}.
 *
 * Built in an isolated Stache with a structured locations collection, so the
 * `collection:clinical_locations` tag reads real tree parents. The tree: a campus with
 * three published location children (one hidden from the location list), an
 * unpublished child and a page on another blueprint; and a standalone clinic.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalLocationChildLocationsTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    // A structured locations collection with a location and a basic page blueprint.
    $collection = CollectionFacade::make('clinical_locations');
    $collection->title('Clinical Locations');
    $collection->structureContents(['root' => false]);
    $collection->save();
    foreach (['location', 'basic_page'] as $handle) {
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.clinical_locations');
        $blueprint->setContents([
            'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'hidden_query', 'field' => ['type' => 'toggle']],
            ]]]]],
        ]);
        $blueprint->save();
    }

    // The entries, by id: blueprint, title, published, hidden from the location list.
    foreach ([
        'campus' => ['location', 'Main Campus', true, false],
        'radiology' => ['location', 'Radiology Clinic', true, false],
        'cardiology' => ['location', 'Cardiology Clinic', true, false],
        'storage' => ['location', 'Records Storage', true, true],
        'draft' => ['location', 'Draft Clinic', false, false],
        'visitors' => ['basic_page', 'Visitor Guide', true, false],
        'standalone' => ['location', 'Standalone Clinic', true, false],
    ] as $id => [$blueprint, $title, $published, $hidden]) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->id($id);
        $entry->collection('clinical_locations');
        $entry->blueprint($blueprint);
        $entry->slug($id);
        $entry->published($published);
        $entry->data(['title' => $title, 'hidden_query' => $hidden]);
        $entry->saveQuietly();
    }

    /** @var Collection $treeCollection */
    $treeCollection = CollectionFacade::find('clinical_locations');
    /** @var CollectionStructure $treeStructure */
    $treeStructure = $treeCollection->structure();
    /** @var Tree $tree */
    $tree = $treeStructure->makeTree('default', [
        ['entry' => 'campus', 'children' => [
            ['entry' => 'radiology'],
            ['entry' => 'cardiology'],
            ['entry' => 'storage'],
            ['entry' => 'draft'],
            ['entry' => 'visitors'],
        ]],
        ['entry' => 'standalone'],
    ]);
    $tree->save();

    // Saving a tree does not refresh the Stache's `parent` index in an isolated Stache, so
    // rebuild it; on disk-loaded content the index is built from the saved tree.
    /** @var AggregateStore $entries */
    $entries = Stache::store('entries');
    /** @var Store $locations */
    $locations = $entries->store('clinical_locations');
    /** @var Index $parentIndex */
    $parentIndex = $locations->index('parent');
    $parentIndex->update();
});

/**
 * Render the child-locations partial in the scope of the given location.
 */
function renderChildLocations(string $id, string $title): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/child_locations }}', ['id' => $id, 'title' => $title], true);
}

it('lists the published location children as cards in title order', function (): void {
    $html = renderChildLocations('campus', 'Main Campus');
    preg_match_all('~<h2 class="card-title h5"[^>]*>([^<]*)</h2>~', $html, $matches);

    expect($html)->toContain('<section class="location-list location-child-locations" id="sub-clinics"')
        ->and($html)->toContain('<h2 id="sub-location-title" data-jump-link="Clinics Within This Location">Additional Clinics Within Main Campus</h2>')
        ->and($matches[1])->toBe(['Cardiology Clinic', 'Radiology Clinic'])
        ->and($html)->toContain('aria-label="View location: Radiology Clinic"');
});

it('leaves out a hidden child, an unpublished child and a non-location page', function (): void {
    $html = renderChildLocations('campus', 'Main Campus');

    expect($html)->not->toContain('Records Storage')
        ->and($html)->not->toContain('Draft Clinic')
        ->and($html)->not->toContain('Visitor Guide');
});

it('renders no list for a location with no children', function (): void {
    $html = renderChildLocations('standalone', 'Standalone Clinic');

    expect($html)->not->toContain('sub-clinics')
        ->and($html)->not->toContain('Additional Clinics Within');
});
