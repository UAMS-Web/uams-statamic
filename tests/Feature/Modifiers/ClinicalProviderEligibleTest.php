<?php

declare(strict_types=1);

use App\Modifiers\ClinicalProviderEligible;
use Carbon\Carbon;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the UAMS-Web/uams-statamic-archive#919 `clinical_provider_eligible` Antlers modifier: the shared
 * output filter for authored provider relationships (`physician_locations`,
 * `clinical_providers`) that render as augmented `entries` lists rather than a
 * `{{ collection:people }}` tag. It keeps only `people` who are eligible for a
 * clinical provider profile AND currently searchable (not actively separated, not
 * hidden), mirroring the FaD Providers block's
 * `clinical_provider_profile_eligible_query:is="true" searchable:is="true"` pair.
 *
 * Real `people` entries are built in an isolated Stache so the modifier's
 * predicate runs against real entries and the filtered result iterates + augments
 * (`title` / `url`) exactly as it does at a live render site.
 *
 * @command php artisan test --compact tests/Feature/Modifiers/ClinicalProviderEligibleTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    /** @var Collection $people */
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();
});

/**
 * The minimum people field set where every clinical-provider eligibility base
 * rule passes (mirrors config/clinical_provider_profile.php `base`).
 *
 * @return array<string, mixed>
 */
function eligibleModifierBaseFields(): array
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
 * Persist a people entry with the given slug/title and field overrides.
 *
 * @param  array<string, mixed>  $fields
 */
function makeProviderPerson(string $slug, string $title, array $fields = []): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data([
        ...eligibleModifierBaseFields(),
        'title' => $title,
        'credentials' => ['md'],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Apply the modifier to a list passed in context and emit each survivor's title.
 *
 * @param  array<int, mixed>  $providers
 */
function renderThroughEligibleModifier(array $providers): string
{
    return (string) Antlers::parse(
        '{{ eligible = list | clinical_provider_eligible }}{{ eligible }}[{{ title }}]{{ /eligible }}',
        ['list' => $providers],
        true,
    );
}

/**
 * Register the facet taxonomies the routed clinical-expertise section filter bar
 * reads via `{{ taxonomy:… }}` tags (UAMS-Web/uams-statamic-archive#1439), so the now-richer `providers` /
 * `locations` section renders in an isolated Stache. Registered as one set
 * (mirroring ClinicalExpertiseSectionFilterTest) to keep the routed-section
 * render sites from drifting apart. No terms are required — the tag only needs
 * each taxonomy to exist; an empty term list renders an empty facet control.
 * `patient_types` and `clinical_specializations` are structured collections since
 * UAMS-Web/uams-statamic-archive#2598 and UAMS-Web/uams-statamic-archive#2596, so they are registered as collections: `{{ collection:... }}`
 * throws for a missing collection.
 */
function registerExpertiseFacetTaxonomies(): void
{
    foreach ([
        'regions' => 'Regions',
        'location_types' => 'Location Types',
    ] as $handle => $title) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($handle);
        $taxonomy->title($title);
        $taxonomy->save();
    }

    /** @var Collection $patientTypes */
    $patientTypes = CollectionFacade::make('patient_types');
    $patientTypes->title('Patient Types');
    $patientTypes->save();

    /** @var Collection $specializations */
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();
}

it('keeps an eligible, searchable provider and renders its title', function (): void {
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');

    expect(renderThroughEligibleModifier([$mccoy]))->toContain('[Leonard McCoy]');
});

it('drops a person who fails clinical-provider eligibility (a resident)', function (): void {
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    expect(renderThroughEligibleModifier([$chekov]))->not->toContain('[Pavel Chekov]');
});

it('drops an eligible provider who is actively separated (not searchable)', function (): void {
    $sulu = makeProviderPerson('hikaru-sulu', 'Hikaru Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    expect(renderThroughEligibleModifier([$sulu]))->not->toContain('[Hikaru Sulu]');
});

it('drops a person hidden from the provider list (not searchable)', function (): void {
    $uhura = makeProviderPerson('nyota-uhura', 'Nyota Uhura', [
        'hidden_from_provider_list_query' => true,
    ]);

    expect(renderThroughEligibleModifier([$uhura]))->not->toContain('[Nyota Uhura]');
});

it('keeps only the eligible, searchable providers from a mixed population', function (): void {
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);
    $sulu = makeProviderPerson('hikaru-sulu', 'Hikaru Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    $out = renderThroughEligibleModifier([$mccoy, $chekov, $sulu]);

    expect($out)->toContain('[Leonard McCoy]')
        ->and($out)->not->toContain('[Pavel Chekov]')
        ->and($out)->not->toContain('[Hikaru Sulu]');
});

it('drops non-entry items without error', function (): void {
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');

    // A stray non-entry value (e.g. a dangling id or malformed relationship item)
    // must be skipped, not fatal.
    $out = renderThroughEligibleModifier([$mccoy, 'not-an-entry-id', null]);

    expect($out)->toContain('[Leonard McCoy]');
});

it('renders through the shared related-list partial, suppressing the heading when no provider is eligible', function (): void {
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);
    $sulu = makeProviderPerson('hikaru-sulu', 'Hikaru Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    // The §1b/§1d call-site pattern: the provider relationship is filtered before
    // the entity-agnostic list partial, so an all-ineligible list renders nothing
    // (no heading, no empty <ul>).
    $out = (string) Antlers::parse(
        '{{ partial:clinical_resources/related_list :entries="list | clinical_provider_eligible" heading="Providers at This Location" }}',
        ['list' => [$chekov, $sulu]],
        true,
    );

    expect($out)->not->toContain('Providers at This Location')
        ->and(trim($out))->toBe('');
});

it('renders the eligible provider through the shared related-list partial with its heading', function (): void {
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse(
        '{{ partial:clinical_resources/related_list :entries="list | clinical_provider_eligible" heading="Providers at This Location" }}',
        ['list' => [$mccoy, $chekov]],
        true,
    );

    expect($out)->toContain('Providers at This Location')
        ->and($out)->toContain('Leonard McCoy')
        ->and($out)->not->toContain('Pavel Chekov');
});

it('filters a resolved entry collection to eligible providers', function (): void {
    // One augmented shape: a resolved EntryCollection (NormalizeList's Collection branch).
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->whereIn('id', [$mccoy->id(), $chekov->id()]);
    $collection = $query->get();

    $out = (string) Antlers::parse(
        '{{ eligible = list | clinical_provider_eligible }}{{ eligible }}[{{ title }}]{{ /eligible }}',
        ['list' => $collection],
        true,
    );

    expect($out)->toContain('[Leonard McCoy]')
        ->and($out)->not->toContain('[Pavel Chekov]');
});

it('resolves an unresolved query builder input (the lazy augmented relationship shape)', function (): void {
    // The shape a live `entries` relationship augments to is an UNRESOLVED query
    // builder; NormalizeList resolves it via ->get() before the predicate filters.
    // Call the modifier directly so the builder is not pre-resolved by Antlers first.
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    /** @var EntryQueryBuilder $builder */
    $builder = Entry::query();
    $builder->whereIn('id', [$mccoy->id(), $chekov->id()]);

    $eligible = app(ClinicalProviderEligible::class)->index($builder, [], []);

    $titles = array_map(static fn (EntryInstance $e): mixed => $e->get('title'), $eligible);

    expect($titles)->toContain('Leonard McCoy')
        ->and($titles)->not->toContain('Pavel Chekov');
});

it('renders an assigned filtered list through the shared related-list partial', function (): void {
    // The location / clinical-resource call-site pattern: filter once into a variable,
    // then pass the variable to the entity-agnostic list partial.
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse(
        '{{ eligible_providers = list | clinical_provider_eligible }}{{ partial:clinical_resources/related_list :entries="eligible_providers" heading="Related Providers" }}',
        ['list' => [$mccoy, $chekov]],
        true,
    );

    expect($out)->toContain('Related Providers')
        ->and($out)->toContain('Leonard McCoy')
        ->and($out)->not->toContain('Pavel Chekov');
});

it('resolves related ids to entries and keeps only the eligible provider', function (): void {
    // Some render contexts pass the relationship as bare entry ids; the modifier
    // resolves each id before applying the predicate.
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = renderThroughEligibleModifier([$mccoy->id(), $chekov->id()]);

    expect($out)->toContain('[Leonard McCoy]')
        ->and($out)->not->toContain('[Pavel Chekov]');
});

it('filters the AoE overview provider list to eligible providers through the modifier', function (): void {
    // End-to-end at the §1c render site: the clinical_expertise `_related` overview
    // assigns `clinical_providers | clinical_provider_eligible` to a variable and
    // drives the gate + list from it, so only eligible providers render.
    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse(
        '{{ partial:clinical_expertise/related }}',
        ['clinical_providers' => [$mccoy->id(), $chekov->id()]],
        true,
    );

    expect($out)->toContain('Related Clinical Providers')
        ->and($out)->toContain('Leonard McCoy')
        ->and($out)->not->toContain('Pavel Chekov');
});

it('suppresses the AoE provider section entirely when no provider is eligible', function (): void {
    // Raw providers exist but none are eligible, so the section (and its heading) is
    // suppressed rather than rendering an empty list.
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse(
        '{{ partial:clinical_expertise/related }}',
        ['clinical_providers' => [$chekov->id()]],
        true,
    );

    expect($out)->not->toContain('Related Clinical Providers')
        ->and($out)->not->toContain('Pavel Chekov');
});

it('filters the routed AoE providers section to eligible providers', function (): void {
    // The routed /{entry}/providers subpage (section.antlers.html) is a distinct live
    // surface from the overview; render it directly with section == 'providers'.
    /** @var Collection $expertise */
    $expertise = CollectionFacade::make('clinical_expertise');
    $expertise->title('Clinical Expertise');
    $expertise->save();

    // The providers section renders the UAMS-Web/uams-statamic-archive#1439 facet bar, whose controls read the
    // facet taxonomies; register the full set so the now-richer routed section
    // renders in this isolated Stache.
    registerExpertiseFacetTaxonomies();

    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy');
    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse('{{ partial:clinical_expertise/section }}', [
        'section' => 'providers',
        'title' => 'Cardiology',
        'clinical_providers' => [$mccoy->id(), $chekov->id()],
    ], true);

    expect($out)->toContain('Leonard McCoy')
        ->and($out)->not->toContain('Pavel Chekov');
});

it('shows the routed AoE providers-section empty state when no provider is eligible', function (): void {
    // Raw providers exist but none are eligible; the routed section shows its empty
    // state (the route's 404 gate keys off raw presence, so the page is reachable).
    /** @var Collection $expertise */
    $expertise = CollectionFacade::make('clinical_expertise');
    $expertise->title('Clinical Expertise');
    $expertise->save();

    $chekov = makeProviderPerson('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse('{{ partial:clinical_expertise/section }}', [
        'section' => 'providers',
        'title' => 'Cardiology',
        'clinical_providers' => [$chekov->id()],
    ], true);

    expect($out)->toContain('No related clinical providers are available.')
        ->and($out)->not->toContain('Pavel Chekov');
});

it('emits each eligible provider\'s facet slugs as data-* on the routed section list item', function (): void {
    // UAMS-Web/uams-statamic-archive#1439: the routed providers section emits each surviving provider's clinical-title,
    // region, and descendant-Areas-of-Expertise slugs as `data-*` attributes for the
    // client-side `fadFilter` engine. Assert them against the REAL section render so
    // blanking a slug (which silently kills the client-side filter) fails here — the
    // in-DOM `<li>` emission is otherwise unasserted against the shipped template.
    //
    // `regions` is the computed aggregation from the provider's linked location (not a
    // stored field), so the region facet exercises that path (mirrors FadProvidersBlockTest
    // for the shared `_fad_provider_card` contract this section reuses).
    /** @var Collection $expertise */
    $expertise = CollectionFacade::make('clinical_expertise');
    $expertise->title('Clinical Expertise');
    $expertise->save();
    /** @var Collection $locations */
    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->save();
    registerExpertiseFacetTaxonomies();

    // A people blueprint so the provider's facet fields augment to sluggable objects
    // (`pluck:'slug'` needs augmented term/entry objects, not raw ids/strings); `regions`
    // is `visibility: computed` — supplied by the ComputedPropertiesProvider from the
    // provider's locations regardless of any stored value.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'expertise', 'field' => ['type' => 'entries', 'collections' => ['clinical_expertise']]],
            ['handle' => 'clinical_specializations', 'field' => ['type' => 'entries', 'collections' => ['clinical_specializations']]],
            ['handle' => 'regions', 'field' => ['type' => 'terms', 'taxonomies' => ['regions'], 'visibility' => 'computed']],
        ]]]]],
    ]);
    $blueprint->save();

    /** @var EntryInstance $cardiology */
    $cardiology = Entry::make();
    $cardiology->collection('clinical_expertise');
    $cardiology->slug('cardiology');
    $cardiology->published(true);
    $cardiology->data(['title' => 'Cardiology']);
    $cardiology->saveQuietly();

    /** @var EntryInstance $specialization */
    $specialization = Entry::make();
    $specialization->collection('clinical_specializations');
    $specialization->locale('default');
    $specialization->slug('general-surgery');
    $specialization->published(true);
    $specialization->data(['title' => 'General Surgery']);
    $specialization->saveQuietly();

    /** @var Statamic\Taxonomies\Term $regionTerm */
    $regionTerm = Term::make();
    $regionTerm->taxonomy('regions');
    $regionTerm->slug('central-arkansas');
    $regionTerm->dataForLocale($regionTerm->defaultLocale(), ['title' => 'Central Arkansas']);
    $regionTerm->save();

    /** @var EntryInstance $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->slug('main-campus');
    $location->published(true);
    $location->data(['title' => 'Main Campus', 'regions' => 'central-arkansas']);
    $location->saveQuietly();

    $mccoy = makeProviderPerson('leonard-mccoy', 'Leonard McCoy', [
        'expertise' => [$cardiology->id()],
        'clinical_specializations' => [$specialization->id()],
        'clinical_locations' => [$location->id()],
    ]);

    $out = (string) Antlers::parse('{{ partial:clinical_expertise/section }}', [
        'section' => 'providers',
        'title' => 'Cardiology',
        'clinical_providers' => [$mccoy->id()],
    ], true);

    expect($out)->toContain('data-specialty="general-surgery"')
        ->and($out)->toContain('data-region="central-arkansas"')
        ->and($out)->toContain('data-expertise="cardiology"');
});
