<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the Area of Expertise routed section subpage faceted filtering (UAMS-Web/uams-statamic-archive#1439):
 * resources/views/clinical_expertise/_section_filter.antlers.html (the facet controls)
 * and the `data-fad-card` / `data-<facet>` slug emission on each list item in
 * resources/views/clinical_expertise/section.antlers.html.
 *
 * The `_section_filter` partial is rendered in isolation (its `show_*` flags, option
 * population + escaping); the item slug emission is asserted against real augmentation
 * by looping the AoE's augmented relationship field, the same real-query approach as
 * FadProvidersBlockTest. The shared `fadFilter` client engine is covered in
 * resources/js/fad-filter.test.js.
 *
 * NOT covered here — the descendant-Areas-of-Expertise control: it reuses the
 * codebase's established structure-children query (`:parent:is="load"`, byte-identical
 * to the specialties section and the KB `_topic_list`), which the Stache-fake does not
 * resolve — the exact limitation the resolver abstracts behind its mockable
 * `hasPublishedExpertiseChildren` seam. Its positive render is proven by that shared,
 * shipped query in production, not reproducible in an isolated Stache.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalExpertiseSectionFilterTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_expertise');
    $collection->title('Areas of Expertise');
    $collection->structureContents(['root' => false]);
    $collection->save();

    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_locations');
    $collection->title('Clinical Locations');
    $collection->save();

    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_resources');
    $collection->title('Clinical Resources');
    $collection->save();

    /** @var Collection $collection */
    $collection = CollectionFacade::make('people');
    $collection->title('People');
    $collection->save();

    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('regions');
    $taxonomy->title('Regions');
    $taxonomy->save();

    // `clinical_specializations` is a structured collection on `default` since UAMS-Web/uams-statamic-archive#2596,
    // so it is registered as one: `{{ collection:clinical_specializations }}` throws for
    // a missing collection.
    /** @var Collection $collection */
    $collection = CollectionFacade::make('clinical_specializations');
    $collection->title('Clinical Specializations');
    $collection->sites(['default']);
    $collection->structureContents(['root' => false]);
    $collection->save();

    // Optional provider / location facets (UAMS-Web/uams-statamic-archive#1490). Patient types are a structured
    // collection since UAMS-Web/uams-statamic-archive#2598; location types are still a taxonomy.
    /** @var Collection $collection */
    $collection = CollectionFacade::make('patient_types');
    $collection->title('Patient Types');
    $collection->structureContents(['root' => false]);
    $collection->save();

    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('location_types');
    $taxonomy->title('Location Types');
    $taxonomy->save();

    // Minimal blueprints so relationship/term fields augment to objects (`pluck:'slug'`
    // needs augmented objects, not raw ids/strings).
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('clinical_expertise');
    $blueprint->setNamespace('collections.clinical_expertise');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'clinical_providers', 'field' => ['type' => 'entries', 'collections' => ['people']]],
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
            ['handle' => 'clinical_resources', 'field' => ['type' => 'entries', 'collections' => ['clinical_resources']]],
        ]]]]],
    ]);
    $blueprint->save();

    // The resource type facet reads each resource's blueprint handle; `video` is one type.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('video');
    $blueprint->setNamespace('collections.clinical_resources');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $blueprint->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('location');
    $blueprint->setNamespace('collections.clinical_locations');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'expertise', 'field' => ['type' => 'entries', 'collections' => ['clinical_expertise']]],
            ['handle' => 'regions', 'field' => ['type' => 'terms', 'taxonomies' => ['regions']]],
            // UAMS-Web/uams-statamic-archive#1490: the optional Location Type facet reads the location's `location_types` terms.
            ['handle' => 'location_types', 'field' => ['type' => 'terms', 'taxonomies' => ['location_types']]],
        ]]]]],
    ]);
    $blueprint->save();

    // UAMS-Web/uams-statamic-archive#1490: a provider (person) carries the optional facet fields — patient types (entries, UAMS-Web/uams-statamic-archive#2598),
    // gender (a `radio` whose option key IS the stored value), and languages (a `multiple`
    // select augmenting to `{value,label}` rows, so the card `pluck:'value'`s the codes).
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'patient_types', 'field' => ['type' => 'entries', 'collections' => ['patient_types'], 'select_across_sites' => true]],
            // UAMS-Web/uams-statamic-archive#2962: the clinical title options come from the providers' own specializations.
            ['handle' => 'clinical_specializations', 'field' => ['type' => 'entries', 'collections' => ['clinical_specializations'], 'select_across_sites' => true]],
            ['handle' => 'gender', 'field' => ['type' => 'radio', 'options' => [
                ['key' => 'Female', 'value' => null],
                ['key' => 'Male', 'value' => null],
                ['key' => 'Other', 'value' => null],
            ]]],
            ['handle' => 'language', 'field' => ['type' => 'select', 'multiple' => true, 'options' => ['en' => 'English', 'es' => 'Spanish']]],
        ]]]]],
    ]);
    $blueprint->save();
});

/**
 * Save a published `patient_types` entry on the default site, as the collection holds them (UAMS-Web/uams-statamic-archive#2598).
 */
function makePatientType(string $slug, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('patient_types');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Render the section filter partial with the given params.
 *
 * @param  array<string, mixed>  $context
 */
function renderSectionFilter(string $params, array $context = []): string
{
    return (string) Antlers::parse('{{ partial:clinical_expertise/section_filter '.$params.' }}', $context, true);
}

it('renders the providers filter bar with the clinical-title + region controls, populated + escaped, plus reset', function (): void {
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('regions');
    $term->slug('central-arkansas');
    /** @var LocalizedTerm $localizedTerm */
    $localizedTerm = $term->inDefaultLocale();
    $localizedTerm->data(['title' => 'Central Arkansas']);
    $term->saveQuietly();

    /** @var Statamic\Entries\Entry $specialization */
    $specialization = Entry::make();
    $specialization->collection('clinical_specializations');
    $specialization->locale('default');
    $specialization->slug('general-surgery');
    $specialization->published(true);
    $specialization->data(['title' => 'General Surgery']);
    $specialization->saveQuietly();

    // UAMS-Web/uams-statamic-archive#2962: a specialization no listed provider carries is not offered.
    /** @var Statamic\Entries\Entry $unused */
    $unused = Entry::make();
    $unused->collection('clinical_specializations');
    $unused->locale('default');
    $unused->slug('unused-specialty');
    $unused->published(true);
    $unused->data(['title' => 'Unused Specialty']);
    $unused->saveQuietly();

    /** @var Statamic\Entries\Entry $provider */
    $provider = Entry::make();
    $provider->collection('people');
    $provider->blueprint('person');
    $provider->locale('default');
    $provider->slug('filter-bar-provider');
    $provider->data(['title' => 'Filter Bar Provider', 'clinical_specializations' => [$specialization->id()]]);

    // `show_expertise` is on too — it must not error even though the structure-children
    // control does not resolve in an isolated Stache (see the file docblock).
    $out = renderSectionFilter(
        'show_specialty="true" show_region="true" show_expertise="true" filter_noun_plural="providers" :providers="providers"',
        ['providers' => [$provider]]
    );

    expect($out)->toContain('data-fad-facet="specialty"')
        ->and($out)->toContain('data-fad-facet="region"')
        ->and($out)->toContain('<option value="general-surgery">General Surgery</option>')
        ->and($out)->not->toContain('unused-specialty')
        ->and($out)->toContain('<option value="central-arkansas">Central Arkansas</option>')
        ->and($out)->toContain('@click="reset()"');
});

it('renders the resources filter bar with only the resource-type control', function (): void {
    $out = renderSectionFilter('show_type="true" filter_noun_plural="resources"');

    expect($out)->toContain('data-fad-facet="type"')
        ->and($out)->toContain('<option value="video">Video</option>')
        ->and($out)->not->toContain('data-fad-facet="region"')
        ->and($out)->not->toContain('data-fad-facet="specialty"')
        ->and($out)->not->toContain('data-fad-facet="expertise"');
});

it('escapes taxonomy option labels in the filter bar (no raw HTML)', function (): void {
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('regions');
    $term->slug('xss-region');
    /** @var LocalizedTerm $localizedTerm */
    $localizedTerm = $term->inDefaultLocale();
    $localizedTerm->data(['title' => '<script>alert(1)</script>']);
    $term->saveQuietly();

    $out = renderSectionFilter('show_region="true" filter_noun_plural="providers"');

    expect($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->toContain('&lt;script&gt;');
});

it('emits data-fad-card + real facet slugs on each location list item (via the augmented relationship)', function (): void {
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = Entry::make();
    $cardiology->collection('clinical_expertise');
    $cardiology->blueprint('clinical_expertise');
    $cardiology->slug('cardiology');
    $cardiology->published(true);
    $cardiology->data(['title' => 'Cardiology']);
    $cardiology->saveQuietly();

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('regions');
    $term->slug('central-arkansas');
    /** @var LocalizedTerm $localizedTerm */
    $localizedTerm = $term->inDefaultLocale();
    $localizedTerm->data(['title' => 'Central Arkansas']);
    $term->saveQuietly();

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->slug('main-campus');
    $location->published(true);
    $location->data([
        'title' => 'Main Campus',
        'regions' => ['central-arkansas'],
        'expertise' => [$cardiology->id()],
    ]);
    $location->saveQuietly();

    /** @var Statamic\Entries\Entry $aoe */
    $aoe = Entry::make();
    $aoe->collection('clinical_expertise');
    $aoe->blueprint('clinical_expertise');
    $aoe->slug('cardiology-aoe');
    $aoe->published(true);
    $aoe->data(['title' => 'Cardiology', 'clinical_locations' => [$location->id()]]);
    $aoe->saveQuietly();

    // The section loops the augmented relationship field; render that fragment with the
    // AoE's augmented `clinical_locations` (real location entries). The region comes from
    // the `resolved_regions` computed, so a child inheriting its parent's region is faceted
    // the same way (UAMS-Web/uams-statamic-archive#2608).
    $template = <<<'ANTLERS'
    {{ clinical_locations }}<li data-fad-card data-region="{{ resolved_regions | join:' ' | sanitize }}" data-expertise="{{ expertise | pluck:'slug' | join:' ' }}"><a href="{{ url }}">{{ title }}</a></li>{{ /clinical_locations }}
    ANTLERS;

    /** @var Value $augmentedLocations */
    $augmentedLocations = $aoe->augmentedValue('clinical_locations');
    $out = (string) Antlers::parse($template, [
        'clinical_locations' => $augmentedLocations->value(),
    ], true);

    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-region="central-arkansas"')
        ->and($out)->toContain('data-expertise="cardiology"');
});

it('emits data-type (the resource blueprint) on each clinical-resource list item', function (): void {
    /** @var Statamic\Entries\Entry $resource */
    $resource = Entry::make();
    $resource->collection('clinical_resources');
    $resource->blueprint('video');
    $resource->slug('a-video');
    $resource->published(true);
    $resource->data(['title' => 'A Video']);
    $resource->saveQuietly();

    /** @var Statamic\Entries\Entry $aoe */
    $aoe = Entry::make();
    $aoe->collection('clinical_expertise');
    $aoe->blueprint('clinical_expertise');
    $aoe->slug('cardiology-aoe');
    $aoe->published(true);
    $aoe->data(['title' => 'Cardiology', 'clinical_resources' => [$resource->id()]]);
    $aoe->saveQuietly();

    $template = <<<'ANTLERS'
    {{ clinical_resources }}<li data-fad-card data-type="{{ blueprint:handle }}"><a href="{{ url }}">{{ title }}</a></li>{{ /clinical_resources }}
    ANTLERS;

    /** @var Value $augmentedResources */
    $augmentedResources = $aoe->augmentedValue('clinical_resources');
    $out = (string) Antlers::parse($template, [
        'clinical_resources' => $augmentedResources->value(),
    ], true);

    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-type="video"');
});

// -----------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1490 — free-text search + optional select facets on the section filters.
// -----------------------------------------------------------------------------

it('renders the providers optional facets (patient type, gender, language) plus a free-text search input', function (): void {
    makePatientType('children', 'Children');

    // Seed a provider language so the language facet's dynamic option set is non-empty
    // (the tag aggregates the distinct provider languages present).
    /** @var Statamic\Entries\Entry $langSeed */
    $langSeed = Entry::make();
    $langSeed->collection('people');
    $langSeed->blueprint('person');
    $langSeed->slug('lang-seed');
    $langSeed->published(true);
    $langSeed->data(['title' => 'Seed', 'language' => ['en']]);
    $langSeed->saveQuietly();

    $out = renderSectionFilter('show_search="true" show_patient_types="true" show_gender="true" show_language="true" filter_noun_plural="providers"');

    expect($out)
        // Free-text search input the engine detects via `[data-fad-search]`.
        ->toContain('data-fad-search')
        // Patient Type — collection-backed (UAMS-Web/uams-statamic-archive#2598), escaped label.
        ->and($out)->toContain('data-fad-facet="patient_types"')
        ->and($out)->toContain('<option value="children">Children</option>')
        // Gender — the controlled lowercased tokens the card emits.
        ->and($out)->toContain('data-fad-facet="gender"')
        ->and($out)->toContain('<option value="female">Female</option>')
        ->and($out)->toContain('<option value="male">Male</option>')
        // Language — distinct-provider-language options via the shared tag.
        ->and($out)->toContain('data-fad-facet="language"')
        ->and($out)->toContain('<option value="en">English</option>');
});

it('renders the locations optional location-type facet plus a free-text search input', function (): void {
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('location_types');
    $term->slug('hospital');
    /** @var LocalizedTerm $localizedTerm */
    $localizedTerm = $term->inDefaultLocale();
    $localizedTerm->data(['title' => 'Hospital']);
    $term->saveQuietly();

    $out = renderSectionFilter('show_search="true" show_region="true" show_location_type="true" filter_noun_plural="locations"');

    expect($out)
        ->toContain('data-fad-search')
        ->and($out)->toContain('data-fad-facet="location_type"')
        ->and($out)->toContain('<option value="hospital">Hospital</option>')
        // The provider-only facets must not leak onto the locations bar.
        ->and($out)->not->toContain('data-fad-facet="gender"')
        ->and($out)->not->toContain('data-fad-facet="patient_types"');
});

it('renders a free-text search input on the resources filter bar', function (): void {
    $out = renderSectionFilter('show_search="true" show_type="true" filter_noun_plural="resources"');

    expect($out)
        ->toContain('data-fad-search')
        ->and($out)->toContain('data-fad-facet="type"')
        ->and($out)->not->toContain('data-fad-facet="gender"');
});

it('escapes a malicious patient-type option label (no raw HTML)', function (): void {
    makePatientType('xss', '<script>alert(1)</script>');

    $out = renderSectionFilter('show_patient_types="true" filter_noun_plural="providers"');

    expect($out)->not->toContain('<script>alert(1)</script>')
        ->and($out)->toContain('&lt;script&gt;');
});

it('emits data-name + data-patient_types + data-gender + data-language on each provider list item (real augmentation)', function (): void {
    $children = makePatientType('children', 'Children');

    /** @var Statamic\Entries\Entry $provider */
    $provider = Entry::make();
    $provider->collection('people');
    $provider->blueprint('person');
    $provider->slug('dr-mccoy');
    $provider->published(true);
    $provider->data([
        'title' => 'Leonard McCoy',
        'patient_types' => [$children->id()],
        'gender' => 'Female',
        'language' => ['en', 'es'],
    ]);
    $provider->saveQuietly();

    /** @var Statamic\Entries\Entry $aoe */
    $aoe = Entry::make();
    $aoe->collection('clinical_expertise');
    $aoe->blueprint('clinical_expertise');
    $aoe->slug('cardiology-aoe');
    $aoe->published(true);
    $aoe->data(['title' => 'Cardiology', 'clinical_providers' => [$provider->id()]]);
    $aoe->saveQuietly();

    // Render the provider <li> fragment exactly as section.antlers.html emits it, over the
    // AoE's augmented `clinical_providers` — real people entries, so the facet slugs/tokens
    // resolve through Statamic's own augmentation (the false-green trap the ticket flags).
    $template = <<<'ANTLERS'
    {{ clinical_providers }}<li data-fad-card data-name="{{ title | lower | strip_tags | sanitize }}" data-patient_types="{{ patient_types | pluck:'slug' | join:' ' }}" data-gender="{{ gender | lower | sanitize }}" data-language="{{ language | pluck:'value' | join:' ' | sanitize }}"><a href="{{ url }}">{{ title }}</a></li>{{ /clinical_providers }}
    ANTLERS;

    /** @var Value $augmentedProviders */
    $augmentedProviders = $aoe->augmentedValue('clinical_providers');
    $out = (string) Antlers::parse($template, [
        'clinical_providers' => $augmentedProviders->value(),
    ], true);

    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-name="leonard mccoy"')
        ->and($out)->toContain('data-patient_types="children"')
        ->and($out)->toContain('data-gender="female"')
        ->and($out)->toContain('data-language="en es"');
});

it('emits data-name + data-location_type on each location list item (real augmentation)', function (): void {
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('location_types');
    $term->slug('hospital');
    /** @var LocalizedTerm $localizedTerm */
    $localizedTerm = $term->inDefaultLocale();
    $localizedTerm->data(['title' => 'Hospital']);
    $term->saveQuietly();

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->slug('main-campus');
    $location->published(true);
    $location->data([
        'title' => 'Main Campus',
        'location_types' => ['hospital'],
    ]);
    $location->saveQuietly();

    /** @var Statamic\Entries\Entry $aoe */
    $aoe = Entry::make();
    $aoe->collection('clinical_expertise');
    $aoe->blueprint('clinical_expertise');
    $aoe->slug('cardiology-aoe');
    $aoe->published(true);
    $aoe->data(['title' => 'Cardiology', 'clinical_locations' => [$location->id()]]);
    $aoe->saveQuietly();

    $template = <<<'ANTLERS'
    {{ clinical_locations }}<li data-fad-card data-name="{{ title | lower | strip_tags | sanitize }}" data-location_type="{{ location_types | pluck:'slug' | join:' ' }}"><a href="{{ url }}">{{ title }}</a></li>{{ /clinical_locations }}
    ANTLERS;

    /** @var Value $augmentedLocations */
    $augmentedLocations = $aoe->augmentedValue('clinical_locations');
    $out = (string) Antlers::parse($template, [
        'clinical_locations' => $augmentedLocations->value(),
    ], true);

    expect($out)->toContain('data-name="main campus"')
        ->and($out)->toContain('data-location_type="hospital"');
});

it('uses the shipped section template item data-* expressions (guards the heredoc copies against drift)', function (): void {
    // The data-* emission tests above assert augmentation over hand-copied `<li>` fragments; this
    // pins the SHIPPED template to those exact expressions, so a drift there (e.g. the `language`
    // select's `pluck:'value'` slipping to `pluck:'slug'`, which would silently break the facet)
    // turns a test red rather than staying green against the duplicated copy.
    $section = File::get(resource_path('views/clinical_expertise/section.antlers.html'));

    expect($section)
        // Providers list item (UAMS-Web/uams-statamic-archive#1490 optional facets).
        ->toContain('data-name="{{ title | lower | strip_tags | sanitize }}"')
        ->toContain("data-patient_types=\"{{ patient_types | pluck:'slug' | join:' ' }}\"")
        ->toContain('data-gender="{{ gender | lower | sanitize }}"')
        ->toContain("data-language=\"{{ language | pluck:'value' | join:' ' | sanitize }}\"")
        // Locations list item.
        ->toContain("data-location_type=\"{{ location_types | pluck:'slug' | join:' ' }}\"");
});
