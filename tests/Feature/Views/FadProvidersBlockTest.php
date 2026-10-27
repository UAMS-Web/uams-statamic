<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the Find-a-Doc Providers page-builder block after UAMS-Web/uams-statamic-archive#1242 turned it on:
 * resources/views/page_builder/_uamsweb_block_fad_providers.antlers.html. The block was
 * gated behind `$fad_providers_implemented = false` pending the reader-facing facet
 * contract (its card body UAMS-Web/uams-statamic-archive#64 and eligibility filter UAMS-Web/uams-statamic-archive#919 already built); UAMS-Web/uams-statamic-archive#1242 removes
 * that guard and adds the opt-in `enable_filtering` faceting.
 *
 * The structural assertions read the block source; the render assertions build an
 * isolated Stache (StacheIsolatedTestCase) with the collections + taxonomies so the
 * `collection:people` query, the eligibility/searchable conditions, the filter selects,
 * and — critically — the real augmentation of each card's facet `data-*` slugs (entries,
 * terms, and the computed `regions` aggregated from the provider's location) all run
 * against the real query layer rather than injected arrays. Companion coverage: the card
 * partial in FadProviderCardTest, the eligibility filter in FadProviderEligibilityFilterTest,
 * and the client-side engine in resources/js/fad-filter.test.js.
 *
 * @command php artisan test --compact tests/Feature/Views/FadProvidersBlockTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    $expertiseCollection = CollectionFacade::make('clinical_expertise');
    $expertiseCollection->title('Areas of Expertise');
    $expertiseCollection->save();

    $locationsCollection = CollectionFacade::make('clinical_locations');
    $locationsCollection->title('Clinical Locations');
    $locationsCollection->save();

    /** @var Statamic\Taxonomies\Taxonomy $regions */
    $regions = Taxonomy::make('regions');
    $regions->title('Regions');
    $regions->save();

    // A structured collection on `default` since UAMS-Web/uams-statamic-archive#2596, which the filter's
    // `{{ collection:clinical_specializations }}` loop reads.
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();

    // A minimal `people` blueprint so the facet relations augment to entry/term objects
    // (the real person.yaml declares these; a bare collection leaves them raw strings, and
    // `pluck:'slug'` needs augmented objects). The eligibility/searchable/regions computeds
    // are supplied by the ComputedPropertiesProvider regardless of the blueprint.
    $blueprint = Blueprint::make('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'expertise', 'field' => ['type' => 'entries', 'collections' => ['clinical_expertise']]],
            // Declared for the same reason as `expertise`: the real person.yaml carries it as
            // an `entries` field, and without it here the relation stays a raw id string that
            // `pluck` cannot read. The provider card lists these locations (UAMS-Web/uams-statamic-archive#1225), so an
            // omission makes the fixture disagree with production about the item shape.
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
            ['handle' => 'clinical_specializations', 'field' => ['type' => 'entries', 'collections' => ['clinical_specializations']]],
            ['handle' => 'regions', 'field' => ['type' => 'terms', 'taxonomies' => ['regions'], 'visibility' => 'computed']],
        ]]]]],
    ]);
    $blueprint->save();
});

/**
 * The minimum people field set where every clinical-provider eligibility base rule
 * passes (mirrors config/clinical_provider_profile.php `base`, as in
 * FadProviderEligibilityFilterTest).
 *
 * @return array<string, mixed>
 */
function providerBaseFields(): array
{
    return [
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'clinical_locations' => [ClinicalProviderFixtures::publishedLocationId()],
    ];
}

/**
 * Create one eligible, searchable provider carrying all three facets: an Areas of
 * Expertise entry, a Clinical Specialization entry, and a Region derived from a linked
 * location (the computed `regions` path).
 */
function makeEligibleProvider(): void
{
    /** @var Statamic\Entries\Entry $expertise */
    $expertise = Entry::make();
    $expertise->collection('clinical_expertise');
    $expertise->slug('cardiology');
    $expertise->published(true);
    $expertise->data(['title' => 'Cardiology']);
    $expertise->saveQuietly();

    /** @var Statamic\Entries\Entry $specialization */
    $specialization = Entry::make();
    $specialization->collection('clinical_specializations');
    $specialization->locale('default');
    $specialization->slug('general-surgery');
    $specialization->published(true);
    $specialization->data(['title' => 'General Surgery']);
    $specialization->saveQuietly();

    /** @var Statamic\Taxonomies\Term $region */
    $region = Term::make();
    $region->taxonomy('regions');
    $region->slug('central-arkansas');
    $region->dataForLocale($region->defaultLocale(), ['title' => 'Central Arkansas']);
    $region->saveQuietly();

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->slug('main-campus');
    $location->published(true);
    $location->data(['title' => 'Main Campus', 'regions' => 'central-arkansas']);
    $location->saveQuietly();

    /** @var Statamic\Entries\Entry $provider */
    $provider = Entry::make();
    $provider->collection('people');
    $provider->slug('leonard-mccoy');
    $provider->published(true);
    $provider->data([
        ...providerBaseFields(),
        'title' => 'Leonard McCoy',
        'credentials' => ['md'],
        'expertise' => [$expertise->id()],
        'clinical_specializations' => [$specialization->id()],
        'clinical_locations' => [$location->id()],
    ]);
    $provider->saveQuietly();
}

/**
 * Render the providers block with a valid block context (the validity gate satisfied).
 *
 * @param  array<string, mixed>  $overrides  Block-scope overrides (e.g. `enable_filtering`).
 */
function fadProvidersBlockRender(array $overrides = []): string
{
    return (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_fad_providers }}', array_merge([
        'block_heading' => 'Our Providers',
        'block_description' => 'Find a UAMS Health provider.',
        'background_color' => 'uams-blue',
        'more_query' => true,
        'more_heading' => 'More',
        'more_button_text' => 'See all providers',
        'more_button_url' => '/providers',
        // No editor pre-filter selected (empty entries field) — the block shows every
        // eligible provider via the ontology-overlap scope's pass-through.
        'expertise' => [],
    ], $overrides), true);
}

it('wires the block: guard removed, enable_filtering conditional, facet contract, no theme stub', function (): void {
    $src = file_get_contents(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_fad_providers.antlers.html');

    // The disabled implementation guard is gone — the block renders live now.
    expect($src)->not->toContain('fad_providers_implemented');

    // The theme's aggregate id element + hidden `_ids` stub are dropped.
    expect($src)->not->toContain('provider_ids')
        ->and($src)->not->toContain('data-postids');

    // Reader filtering is gated per-block: the enabled branch mounts the fadFilter Alpine
    // root + filter partial + facet cards; the disabled branch a plain list.
    expect($src)->toContain('{{ if enable_filtering }}')
        ->and($src)->toContain('x-data="fadFilter"')
        ->and($src)->toContain('partial:page_builder/components/block_fad_filter')
        ->and($src)->toContain('fad_facet="true"');

    // The UAMS-Web/uams-statamic-archive#919 eligibility + searchable output filter is retained on the query.
    expect($src)->toContain('searchable:is="true"')
        ->and($src)->toContain('clinical_provider_profile_eligible_query:is="true"');
});

it('renders the static list with no filter UI or facet markup when filtering is disabled', function (): void {
    makeEligibleProvider();

    // `enable_filtering` unset -> false.
    $out = fadProvidersBlockRender();

    // The eligible provider's card renders — asserted via the per-card "View Profile"
    // CTA, since the visible name is a person-name computed that is empty in isolation.
    expect($out)->toContain('provider-list')
        ->and($out)->toContain('card-list-doctors')
        ->and($out)->toContain('>View Profile</a>')
        // ...but with no filter UI, no Alpine root, and no facet data-* on the card.
        ->and($out)->not->toContain('ajax-filter')
        ->and($out)->not->toContain('x-data="fadFilter"')
        ->and($out)->not->toContain('data-fad-card');
});

it('renders the filter UI and per-card facet slugs (real augmentation) when filtering is enabled', function (): void {
    makeEligibleProvider();

    $out = fadProvidersBlockRender(['enable_filtering' => true]);

    // The Alpine root, filter partial, empty state, and live region.
    expect($out)->toContain('x-data="fadFilter"')
        ->and($out)->toContain('class="ajax-filter"')
        ->and($out)->toContain('ajax-filter-message')
        ->and($out)->toContain('aria-live="polite"')
        // The expertise select is populated from the clinical_expertise collection.
        ->and($out)->toContain('<option value="cardiology">Cardiology</option>');

    // The card carries the facet contract with real, augmented slugs: the expertise entry,
    // the specialization term, and the computed region aggregated from the linked location.
    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-expertise="cardiology"')
        ->and($out)->toContain('data-specialty="general-surgery"')
        ->and($out)->toContain('data-region="central-arkansas"');
});

it('namespaces the filter control ids + query namespace per block instance (#1481)', function (): void {
    makeEligibleProvider();

    // Two filtering blocks with distinct page-builder ids → distinct module-<id>
    // namespaces (page_builder_block_auto_id reads block.id ?? id ?? index).
    $blockA = fadProvidersBlockRender(['enable_filtering' => true, 'id' => 'aaa']);
    $blockB = fadProvidersBlockRender(['enable_filtering' => true, 'id' => 'bbb']);

    // Each Alpine root carries its own URL-query namespace, and every control id /
    // `for` target is prefixed with it (unique per instance, valid HTML, label-linked).
    expect($blockA)
        ->toContain('data-fad-namespace="module-aaa"')
        ->toContain('id="module-aaa-provider-title-ajax-filter"')
        ->toContain('id="module-aaa-provider_expertise"')
        ->toContain('for="module-aaa-provider_expertise"')
        ->toContain('id="module-aaa-clinical_providers_region"')
        ->toContain('id="module-aaa-clinical_providers_clear"')
        // ...and none of the other instance's ids.
        ->and($blockA)->not->toContain('module-bbb');

    expect($blockB)
        ->toContain('data-fad-namespace="module-bbb"')
        ->toContain('id="module-bbb-provider_expertise"')
        ->and($blockB)->not->toContain('module-aaa');

    // The facet hooks stay bare (engine scopes them to each block's own $root).
    expect($blockA)
        ->toContain('data-fad-facet="expertise"')
        ->toContain('x-model="filters.expertise"');
});

it('suppresses the block entirely when a required field (background_color) is missing', function (): void {
    makeEligibleProvider();

    // The validity gate requires background_color too — omitting it must render nothing.
    $out = fadProvidersBlockRender(['background_color' => null]);

    expect($out)->not->toContain('provider-list')
        ->and($out)->not->toContain('Leonard McCoy');
});

// ---
// UAMS-Web/uams-statamic-archive#1166: the empty-provider render guard. The section heading is emitted by the
// `uamsweb_block_fad` wrapper *above* the card loop, so the block hoists the eligible/
// published `collection:people` query above the wrapper and gates the whole section on
// its `no_results`. When nothing publishable resolves, the section — heading, wrapper,
// AND cards — never reaches the server-rendered HTML (no stray markup, no JS-hidden
// flash). `provider-list` is the wrapper's root class and the heading renders inside it,
// so asserting its absence proves the heading is suppressed too. The block partial is the
// single surface the page-builder renders wherever a providers block is placed (Area of
// Expertise and clinical_locations page templates included), so this partial-level
// coverage guards every placement.
// ---

it('suppresses the whole section (heading, wrapper, cards) when no published provider resolves (#1166)', function (): void {
    // No provider created — the eligible/published set is empty. The block-validity gate
    // is satisfied (all required fields present), so only the UAMS-Web/uams-statamic-archive#1166 no_results guard can
    // be keeping the section out.
    $out = fadProvidersBlockRender();

    expect($out)->not->toContain('provider-list')
        ->and($out)->not->toContain('card-list-doctors')
        ->and($out)->not->toContain('>View Profile</a>')
        // ...and the reader-facing filter UI never mounts on an absent section either.
        ->and($out)->not->toContain('x-data="fadFilter"');

    // The guard holds identically when reader filtering is enabled.
    $filtered = fadProvidersBlockRender(['enable_filtering' => true]);

    expect($filtered)->not->toContain('provider-list')
        ->and($filtered)->not->toContain('x-data="fadFilter"')
        ->and($filtered)->not->toContain('class="ajax-filter"');
});

it('suppresses the section when every assigned provider is draft/unpublished (#1166)', function (): void {
    // One otherwise-eligible provider, but unpublished — the collection tag returns only
    // published entries, so the resolved set is empty and the section must not render.
    /** @var Statamic\Entries\Entry $draftDoctor */
    $draftDoctor = Entry::make();
    $draftDoctor->collection('people');
    $draftDoctor->slug('draft-doctor');
    $draftDoctor->published(false);
    $draftDoctor->data([
        ...providerBaseFields(),
        'title' => 'Draft Doctor',
        'credentials' => ['md'],
    ]);
    $draftDoctor->saveQuietly();

    $out = fadProvidersBlockRender();

    expect($out)->not->toContain('provider-list')
        ->and($out)->not->toContain('card-list-doctors')
        ->and($out)->not->toContain('>View Profile</a>');
});

it('renders the section server-side when at least one published provider resolves (#1166)', function (): void {
    makeEligibleProvider();

    $out = fadProvidersBlockRender();

    // The counterpart to the suppression cases: a publishable provider brings the whole
    // section back — wrapper root, the heading (`module-title`, in _block_header), card
    // list, and the card's CTA all present. Asserting `module-title` here is what makes its
    // absence in the suppression tests a real discriminator (a rendered-but-empty section
    // would still carry the heading).
    expect($out)->toContain('provider-list')
        ->and($out)->toContain('module-title')
        ->and($out)->toContain('card-list-doctors')
        ->and($out)->toContain('>View Profile</a>');
});

it('suppresses the section when the only provider is published but ineligible (#1166)', function (): void {
    // A published provider that fails clinical-provider eligibility (a resident) is excluded
    // by the block query's `clinical_provider_profile_eligible_query:is` filter, so the
    // resolved set is empty and the whole section must be suppressed. This guards the
    // "eligible" half of the acceptance criterion at the block level: the guard's `no_results`
    // must reflect the UAMS-Web/uams-statamic-archive#919 eligibility/`searchable`-narrowed set, not the raw published
    // count — a draft/absent provider alone never exercises the computed filters.
    /** @var Statamic\Entries\Entry $residentDoc */
    $residentDoc = Entry::make();
    $residentDoc->collection('people');
    $residentDoc->slug('resident-doc');
    $residentDoc->published(true);
    $residentDoc->data([
        ...providerBaseFields(),
        'resident_query' => true, // fails the eligibility base rule — not a clinical provider
        'title' => 'Resident Doc',
        'credentials' => ['md'],
    ]);
    $residentDoc->saveQuietly();

    $out = fadProvidersBlockRender();

    expect($out)->not->toContain('provider-list')
        ->and($out)->not->toContain('module-title')
        ->and($out)->not->toContain('card-list-doctors')
        ->and($out)->not->toContain('>View Profile</a>');
});

it('renders only the published cards when providers are mixed published/unpublished (#1166)', function (): void {
    makeEligibleProvider(); // Leonard McCoy — published, eligible.

    // A second, otherwise-eligible provider left unpublished.
    /** @var Statamic\Entries\Entry $draftDoctor */
    $draftDoctor = Entry::make();
    $draftDoctor->collection('people');
    $draftDoctor->slug('draft-doctor');
    $draftDoctor->published(false);
    $draftDoctor->data([
        ...providerBaseFields(),
        'title' => 'Draft Doctor',
        'credentials' => ['md'],
    ]);
    $draftDoctor->saveQuietly();

    $out = fadProvidersBlockRender();

    // The section renders, with exactly one card (the published provider) — the draft one
    // is filtered out by publish status, not merely hidden.
    expect($out)->toContain('provider-list')
        ->and(substr_count($out, '>View Profile</a>'))->toBe(1);
});
