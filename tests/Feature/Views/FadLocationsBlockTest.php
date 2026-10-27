<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the Find-a-Doc Locations page-builder block (UAMS-Web/uams-statamic-archive#63):
 * resources/views/page_builder/_uamsweb_block_fad_locations.antlers.html. The
 * block renders the clinical_locations collection as a card directory through the
 * shared `clinical_locations/_list_body`, with the region / location-type filter
 * UI gated pending the shared AJAX facet engine (UAMS-Web/uams-statamic-archive#1242) — each card still carries
 * the facet data-* that engine consumes.
 *
 * The structural assertions read the block source; the render assertions build an
 * isolated Stache (StacheIsolatedTestCase) with the collection + taxonomies so the
 * collection tag, the empty state, and the real term augmentation of the card
 * data-* all run against the real query layer rather than injected arrays.
 *
 * @command php artisan test --compact tests/Feature/Views/FadLocationsBlockTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->save();

    /** @var Statamic\Taxonomies\Taxonomy $regions */
    $regions = Taxonomy::make('regions');
    $regions->title('Regions');
    $regions->save();

    /** @var Statamic\Taxonomies\Taxonomy $locationTypes */
    $locationTypes = Taxonomy::make('location_types');
    $locationTypes->title('Location Types');
    $locationTypes->save();
});

/** Render the locations block with a valid block context (the validity gate satisfied). */
function fadLocationsRender(): string
{
    return (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_fad_locations }}', [
        'block_heading' => 'Our Locations',
        'block_description' => 'Find a UAMS Health location.',
        'background_color' => 'uams-blue',
        'more_query' => true,
        'more_heading' => 'More',
        'more_button_text' => 'See all locations',
        'more_button_url' => '/locations',
    ], true);
}

it('wires the block to the shared list body, gating the filter, with no ontology delegation', function (): void {
    $src = file_get_contents(
        __DIR__.'/../../../resources/views/page_builder/_uamsweb_block_fad_locations.antlers.html'
    );

    // The full block validity gate (not just a prefix) and the FaD wrapper are present.
    expect($src)->toContain('$block_valid = block_heading && block_description && background_color && more_query != null')
        ->and($src)->toContain('fad_block_class="location-list"');

    // The card directory renders through the shared list body, forwarding fad_facet.
    expect($src)->toContain('collection:clinical_locations')
        ->and($src)->toContain('partial:clinical_locations/list_body')
        ->and($src)->toContain('fad_facet="true"');

    // The filter UI is gated behind a disabled flag pending UAMS-Web/uams-statamic-archive#1242.
    expect($src)->toContain('{{ $fad_locations_filter_implemented = false }}')
        ->and($src)->toContain('{{ if $fad_locations_filter_implemented }}')
        ->and($src)->toContain('#1242');

    // It is bespoke now: it no longer delegates to the shared ontology partial.
    expect($src)->not->toContain('block_fad_ontology_list');
});

it('renders the empty state and NOT the gated filter when there are no locations', function (): void {
    $out = fadLocationsRender();

    // The block shell renders, with the empty state from the shared list body.
    expect($out)->toContain('location-list')
        ->and($out)->toContain('No locations found.')
        // The filter UI is gated off — no ajax-filter markup ships.
        ->and($out)->not->toContain('ajax-filter');
});

it('renders a location card carrying the facet data-id for each entry', function (): void {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug('enterprise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Enterprise Medical Center']);
    $entry->saveQuietly();

    $out = fadLocationsRender();

    // The card renders live with its facet data-id anchor (the region / location-
    // type facet attributes are added by UAMS-Web/uams-statamic-archive#1242 against real content).
    expect($out)->toContain('Enterprise Medical Center')
        ->and($out)->toContain('data-id="')
        ->and($out)->not->toContain('No locations found.');
});

it('populates the location-type and region filter selects from the taxonomies, escaping option labels', function (): void {
    /** @var Statamic\Taxonomies\Term $hospital */
    $hospital = Term::make();
    $hospital->taxonomy('location_types');
    $hospital->slug('hospital');
    $hospital->dataForLocale($hospital->defaultLocale(), ['title' => 'Hospital']);
    $hospital->saveQuietly();

    /** @var Statamic\Taxonomies\Term $centralArkansas */
    $centralArkansas = Term::make();
    $centralArkansas->taxonomy('regions');
    $centralArkansas->slug('central-arkansas');
    $centralArkansas->dataForLocale($centralArkansas->defaultLocale(), ['title' => 'Central Arkansas']);
    $centralArkansas->saveQuietly();

    // A hostile term title proves the option labels are escaped (they are editor-controlled).
    /** @var Statamic\Taxonomies\Term $evil */
    $evil = Term::make();
    $evil->taxonomy('regions');
    $evil->slug('evil');
    $evil->dataForLocale($evil->defaultLocale(), ['title' => '<script>alert(1)</script>']);
    $evil->saveQuietly();

    // The filter partial is gated in the block, so render it directly with the
    // locations key to assert the contract the UAMS-Web/uams-statamic-archive#1242 engine reads.
    $out = (string) Antlers::parse(
        '{{ partial:page_builder/components/block_fad_filter fad_filter_collection="clinical_locations" fad_filter_title_plural="Locations" }}',
        [],
        true,
    );

    expect($out)->toContain('<option value="hospital">Hospital</option>')
        ->and($out)->toContain('<option value="central-arkansas">Central Arkansas</option>')
        // The location-type + region selects show for the locations block.
        ->and($out)->toContain('name="location_type"')
        ->and($out)->toContain('name="clinical_locations_region"')
        // Editor-controlled option labels are encoded (Antlers is raw by default).
        ->and($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->toContain('&lt;script&gt;');
});

it('renders the "Part of {parent}" subtitle only for a child location, from the real structure tree (#1465)', function (): void {
    // Make the collection structured so entries carry a tree parent — the AC asks
    // for the subtitle "from the structure tree", which an injected array cannot prove.
    /** @var Collection $structuredLocations */
    $structuredLocations = CollectionFacade::find('clinical_locations');
    /** @var Collection $structuredLocations */
    $structuredLocations = $structuredLocations->structureContents(['root' => false]);
    $structuredLocations->save();

    /** @var Statamic\Entries\Entry $parentLocation */
    $parentLocation = Entry::make();
    /** @var Statamic\Entries\Entry $parentLocation */
    $parentLocation = $parentLocation->id('loc-parent');
    /** @var Statamic\Entries\Entry $parentLocation */
    $parentLocation = $parentLocation->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $parentLocation */
    $parentLocation = $parentLocation->slug('uams-medical-center');
    /** @var Statamic\Entries\Entry $parentLocation */
    $parentLocation = $parentLocation->published(true);
    /** @var Statamic\Entries\Entry $parentLocation */
    $parentLocation = $parentLocation->data(['title' => 'UAMS Medical Center', 'generic_name' => 'UAMS Medical Center']);
    $parentLocation->saveQuietly();

    /** @var Statamic\Entries\Entry $childLocation */
    $childLocation = Entry::make();
    /** @var Statamic\Entries\Entry $childLocation */
    $childLocation = $childLocation->id('loc-child');
    /** @var Statamic\Entries\Entry $childLocation */
    $childLocation = $childLocation->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $childLocation */
    $childLocation = $childLocation->slug('cancer-institute');
    /** @var Statamic\Entries\Entry $childLocation */
    $childLocation = $childLocation->published(true);
    /** @var Statamic\Entries\Entry $childLocation */
    $childLocation = $childLocation->data(['title' => 'Cancer Institute']);
    $childLocation->saveQuietly();

    /** @var Collection $treeCollection */
    $treeCollection = CollectionFacade::find('clinical_locations');
    /** @var CollectionStructure $treeStructure */
    $treeStructure = $treeCollection->structure();
    /** @var Tree $tree */
    $tree = $treeStructure->makeTree('default', [
        ['entry' => 'loc-parent', 'children' => [['entry' => 'loc-child']]],
    ]);
    $tree->save();

    // Data-layer sanity (mirrors PostCategoryArchiveTest): the child has a tree
    // parent, the top-level location does not.
    /** @var Statamic\Entries\Entry $foundChild */
    $foundChild = Entry::find('loc-child');
    /** @var Statamic\Entries\Entry|null $foundChildParent */
    $foundChildParent = $foundChild->parent();
    expect($foundChildParent?->id())->toBe('loc-parent');

    /** @var Statamic\Entries\Entry $foundParent */
    $foundParent = Entry::find('loc-parent');
    /** @var Statamic\Entries\Entry|null $foundParentParent */
    $foundParentParent = $foundParent->parent();
    expect($foundParentParent)->toBeNull();

    // Render the real listing (collection tag → shared list body → card); the card
    // reads the structure-tree parent computeds from the loop's augmented entries.
    $out = (string) Antlers::parse('{{ partial:clinical_locations/listing }}', [], true);

    // Both cards render their own names, and exactly one card (the child) carries the
    // subtitle; the top-level parent has none.
    expect($out)->toContain('Cancer Institute')
        ->and($out)->toContain('UAMS Medical Center')
        ->and(substr_count($out, 'card-parent'))->toBe(1);

    // Isolate the child's subtitle block and assert it names the PARENT (resolved from
    // the structure tree by the computed) — NOT the child's own name. The distinct
    // child/parent names make a self-vs-parent regression in the computed fail here
    // (the parent's name also appears in its own <h2>, so asserting on the whole page
    // would be false-green).
    preg_match('#<p class="card-parent">.*?</p>#s', $out, $subtitleMatch);
    $subtitle = $subtitleMatch[0] ?? '';

    expect($subtitle)->toContain('Part of')
        ->and($subtitle)->toContain('UAMS Medical Center')
        ->and($subtitle)->not->toContain('Cancer Institute');
});

it('suppresses the block entirely when a required field (background_color) is missing', function (): void {
    // The validity gate requires background_color + more_query too, not just the
    // heading/description — omitting one must render nothing.
    $out = (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_fad_locations }}', [
        'block_heading' => 'Our Locations',
        'block_description' => 'Find a UAMS Health location.',
        // background_color intentionally omitted -> $block_valid is false
        'more_query' => true,
        'more_heading' => 'More',
        'more_button_text' => 'See all locations',
        'more_button_url' => '/locations',
    ], true);

    expect($out)->not->toContain('location-list')
        ->and($out)->not->toContain('No locations found.');
});
