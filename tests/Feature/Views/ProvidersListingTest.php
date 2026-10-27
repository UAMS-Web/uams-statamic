<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the UAMS-Web/uams-statamic-archive#1070 clinical-provider directory listing rendered at the
 * `people` mount (`/providers`). `people/provider/index` serves at the mount and delegates
 * to the `people/provider/_listing` partial, which lists the eligible, searchable provider
 * subset via UAMS-Web/uams-statamic-archive#919's computed-tag conditions
 * (`clinical_provider_profile_eligible_query:is="true" searchable:is="true"`) — the
 * same pair the Find-a-Doc Providers block uses.
 *
 * Real `people` entries are built in an isolated Stache so the collection tag filters
 * against real entries, exactly as at the live mount URL.
 *
 * @command php artisan test --compact tests/Feature/Views/ProvidersListingTest.php
 */
uses(StacheIsolatedTestCase::class);

afterEach(function (): void {
    // Reset the current site so a case that switches to `health` (the mount site)
    // cannot leak that context into a later test in this process.
    Site::setCurrent('default');
});

beforeEach(function (): void {
    $peopleCollection = CollectionFacade::make('people');
    $peopleCollection->title('People');
    // The live directory mounts on the `health` site (`/health/providers`) while the
    // `people` entries live only on `default`, so the collection must be enabled on
    // both to reproduce the cross-site render (UAMS-Web/uams-statamic-archive#1635).
    $peopleCollection->sites(['default', 'health']);
    $peopleCollection->save();

    // The UAMS-Web/uams-statamic-archive#570 directory filter chrome loops these option-source collections and
    // taxonomies for its facet <select>s; they must exist (entries optional — an
    // empty collection just renders the "Any …" default) or the partial errors.
    foreach (['clinical_expertise', 'clinical_conditions', 'clinical_services', 'clinical_locations', 'patient_types'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->save();
    }

    foreach (['regions'] as $handle) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($handle);
        $taxonomy->title($handle);
        $taxonomy->save();
    }
    // Every eligible provider links a published location since UAMS-Web/uams-statamic-archive#3123, and the card
    // plucks those locations. Without an `entries` field declaring them the IDs stay
    // raw strings that `pluck` cannot read; the real person.yaml declares the field.
    $blueprint = Blueprint::make('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'clinical_locations', 'field' => ['type' => 'entries', 'collections' => ['clinical_locations']]],
        ]]]]],
    ]);
    $blueprint->save();
});

/**
 * The minimum people field set where every clinical-provider eligibility base rule
 * passes (mirrors config/clinical_provider_profile.php `base`).
 *
 * @return array<string, mixed>
 */
function s1070EligibleBaseFields(): array
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
 * Persist an eligible-by-default people entry; overrides make it ineligible or
 * not searchable.
 *
 * @param  array<string, mixed>  $fields
 */
function makeS1070Person(string $slug, string $title, array $fields = []): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data([
        ...s1070EligibleBaseFields(),
        'title' => $title,
        'credentials' => ['md'],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

it('lists only eligible, searchable providers via the collection conditions', function (): void {
    $mccoy = makeS1070Person('leonard-mccoy', 'Leonard McCoy');
    makeS1070Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);
    makeS1070Person('hikaru-sulu', 'Hikaru Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);
    makeS1070Person('nyota-uhura', 'Nyota Uhura', ['hidden_from_provider_list_query' => true]);

    // The exact condition pair people/provider/_listing uses.
    $out = (string) Antlers::parse(
        '{{ collection:people clinical_provider_profile_eligible_query:is="true" searchable:is="true" sort="title" }}[{{ title }}]{{ /collection:people }}',
        [],
        true,
    );

    expect($out)->toContain('[Leonard McCoy]');      // eligible + searchable
    expect($out)->not->toContain('[Pavel Chekov]');  // resident → ineligible
    expect($out)->not->toContain('[Hikaru Sulu]');   // actively separated → not searchable
    expect($out)->not->toContain('[Nyota Uhura]');   // hidden → not searchable

    expect($mccoy->id())->not->toBeNull();
});

it('renders one provider card per eligible provider in the listing partial', function (): void {
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');
    makeS1070Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    // Exactly one provider card renders (the shared card emits one "View Profile"
    // CTA per provider); the ineligible resident is filtered out, and the empty
    // state is absent.
    expect(substr_count($out, 'View Profile'))->toBe(1)
        ->and($out)->not->toContain('No clinical providers are available.');
});

it('shows the empty state when no provider is eligible', function (): void {
    makeS1070Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    expect($out)->toContain('No clinical providers are available.')
        ->and(substr_count($out, 'View Profile'))->toBe(0);
});

it('loads the Find-a-Doc SCSS for the people/provider/index provider directory', function (): void {
    // The directory renders the shared `_fad_provider_card` + `.card-list-doctors`
    // grid, whose styling lives in `uamswp_find_a_doc/app.scss`. `layout.antlers.html`
    // gates that bundle on the template being a Find-a-Doc/People template. UAMS-Web/uams-statamic-archive#568
    // dropped `people/index` from the gate when the old stub was inert; UAMS-Web/uams-statamic-archive#1070 revives
    // it as a real card-rendering template — re-homed to `people/provider/index` by
    // UAMS-Web/uams-statamic-archive#571 — so it must be back in the gate or the directory renders unstyled.
    $layout = File::get(resource_path('views/layout.antlers.html'));
    $fadScssGate = Str::of($layout)
        ->after('UAMSWP Find-a-Doc and People SCSS')
        ->before('uamswp_find_a_doc/app.scss')
        ->toString();

    expect($fadScssGate)
        ->toContain("'people/provider/index'")
        ->toContain("'people/show'");
});

it('wraps the directory in the fadProviderDirectory root and renders the WP-parity filter chrome (#570)', function (): void {
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    // The Alpine root + client-side pagination hook.
    expect($out)->toContain('x-data="fadProviderDirectory"')
        ->and($out)->toContain('data-fad-page-size="12"')
        // Name search + A–Z bar + name sort chrome.
        ->and($out)->toContain('data-fad-search')
        ->and($out)->toContain('data-fad-alpha')
        ->and($out)->toContain('data-fad-alpha-letter="M"')
        ->and($out)->toContain('data-fad-sort')
        // Every WP-parity facet select is present (specialty/ratings deliberately absent).
        ->and($out)->toContain('data-fad-facet="expertise"')
        ->and($out)->toContain('data-fad-facet="conditions"')
        ->and($out)->toContain('data-fad-facet="treatments"')
        ->and($out)->toContain('data-fad-facet="patient_types"')
        ->and($out)->toContain('data-fad-facet="gender"')
        ->and($out)->toContain('data-fad-facet="language"')
        ->and($out)->toContain('data-fad-facet="location"')
        ->and($out)->toContain('data-fad-facet="region"')
        ->and($out)->toContain('data-fad-facet="primary_care"')
        ->and($out)->not->toContain('data-fad-facet="specialty"')
        // Reset control.
        ->and($out)->toContain('@click="reset()"');
});

it('emits the directory facet contract on each provider card, narrowing data the engine reads (#570)', function (): void {
    // A single eligible provider whose scalar facet fields the card turns into the
    // data-* slugs the fadProviderDirectory engine narrows on (facet narrowing at the
    // server-contract level; the client narrowing logic is covered by fad-filter.test.js).
    makeS1070Person('leonard-mccoy', 'Leonard McCoy', [
        'gender' => 'Female',
        // The augmented `{value,label}` select-row shape the card plucks the code from.
        'language' => [['value' => 'en', 'label' => 'English']],
        'primary_care_query' => true,
        'person_name' => [
            'last_name' => ['text' => 'McCoy'],
            'first_name' => ['text' => 'Leonard'],
        ],
    ]);

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    // The scalar and name-derived facet slugs (the entry-relationship slugs and the
    // searchable computed `data-name` are covered precisely in FadProviderCardTest).
    expect($out)->toContain('data-fad-card')
        ->and($out)->toContain('data-gender="female"')
        ->and($out)->toContain('data-primary_care="yes"')
        ->and($out)->toContain('data-language="en"')
        ->and($out)->toContain('data-alpha="M"')
        ->and($out)->toContain('data-sort-name="mccoy leonard"');
});

it('renders the result count, client-side empty state, and pager scaffolding (#570)', function (): void {
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    // Live result count (server total as the no-JS fallback), the filtered-to-zero
    // empty state, and the pager bound to the engine's page state.
    expect($out)->toContain('x-text="visibleCount"')
        ->and($out)->toContain('No providers match the selected filters.')
        ->and($out)->toContain('x-show="visibleCount === 0"')
        ->and($out)->toContain('x-show="totalPages > 1"')
        ->and($out)->toContain('number in pageWindow')
        // The pre-#570 static 911-friendly grid class and CTA still render.
        ->and($out)->toContain('card-list-doctors')
        ->and($out)->toContain('View Profile');
});

it('keeps the server-side empty state and no filter chrome when no provider is eligible (#570)', function (): void {
    makeS1070Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    // Zero eligible providers → the original server empty state, and none of the
    // facet chrome (it lives in the else branch).
    expect($out)->toContain('No clinical providers are available.')
        ->and($out)->not->toContain('x-data="fadProviderDirectory"')
        ->and($out)->not->toContain('data-fad-search');
});

it('surfaces the default-site providers when the directory renders on the health mount site (#1635)', function (): void {
    // The mount page lives on the `health` site (`/health/providers`), so at the live
    // URL `Site::current()` is `health`. The `people` entries live ONLY on `default`.
    // Statamic's `{{ collection }}` tag defaults `site` to the current site when the
    // install is multi-site (Tags/Collection/Entries: `where('site', current)`), so an
    // unqualified `{{ collection:people }}` renders EMPTY on the health mount — the
    // pre-existing bug this pins. The listing must query the canonical `default` site
    // explicitly so the directory surfaces providers on the mount, not just when a
    // test happens to render in the default-site context (the prior false-green).
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');
    makeS1070Person('pavel-chekov', 'Pavel Chekov', ['resident_query' => true]);

    Site::setCurrent('health');

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    expect(substr_count($out, 'View Profile'))->toBe(1)
        ->and($out)->toContain('data-sort-name')
        ->and($out)->not->toContain('No clinical providers are available.');
});

it('points each directory card at the clinical lens URL, not the base people permalink (#571)', function (): void {
    // The card CTA must lead to `/health/provider/{slug}` — the clinical lens — rather
    // than the provider's own `/people/{slug}` permalink. The URL comes from the lens
    // registry via `{{ people_lens_url }}`, so the route stays defined in one place.
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    Site::setCurrent('health');

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    expect($out)->toContain('href="/health/provider/leonard-mccoy"')
        ->and($out)->not->toContain('href="/people/leonard-mccoy"');
});

it('falls the card back to the entry permalink where the clinical lens is not active', function (): void {
    // On the canonical site the clinical lens has no route, so the tag yields nothing
    // and the card must degrade to the person's own URL rather than emit a dead link.
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    Site::setCurrent('default');

    $out = (string) Antlers::parse('{{ partial:people/provider/listing }}', [], true);

    // The card still renders its CTA; it just carries the entry's own permalink
    // (empty in this isolated Stache, which configures no route) instead of a lens
    // URL that would 404 on this site.
    expect($out)->not->toContain('/health/provider/leonard-mccoy')
        ->and($out)->not->toContain('/provider/leonard-mccoy')
        ->and(substr_count($out, 'View Profile'))->toBe(1);
});

it('drops a provider from the eligible listing when their only location is unpublished (#3123)', function (): void {
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    $template = '{{ collection:people clinical_provider_profile_eligible_query:is="true" }}[{{ title }}]{{ /collection:people }}';

    expect((string) Antlers::parse($template, [], true))->toContain('[Leonard McCoy]');

    /** @var EntryInstance $location */
    $location = Entry::find(ClinicalProviderFixtures::publishedLocationId());
    $location->published(false);
    $location->save();

    expect((string) Antlers::parse($template, [], true))->not->toContain('[Leonard McCoy]');
});

it('drops a provider from the eligible listing when their only location is deleted (#3123)', function (): void {
    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    $template = '{{ collection:people clinical_provider_profile_eligible_query:is="true" }}[{{ title }}]{{ /collection:people }}';

    expect((string) Antlers::parse($template, [], true))->toContain('[Leonard McCoy]');

    /** @var EntryInstance $location */
    $location = Entry::find(ClinicalProviderFixtures::publishedLocationId());
    $location->delete();

    expect((string) Antlers::parse($template, [], true))->not->toContain('[Leonard McCoy]');
});

it('adds a provider to the eligible listing when their only location is published (#3123)', function (): void {
    /** @var EntryInstance $location */
    $location = Entry::find(ClinicalProviderFixtures::publishedLocationId());
    $location->published(false);
    $location->save();

    makeS1070Person('leonard-mccoy', 'Leonard McCoy');

    $template = '{{ collection:people clinical_provider_profile_eligible_query:is="true" }}[{{ title }}]{{ /collection:people }}';

    expect((string) Antlers::parse($template, [], true))->not->toContain('[Leonard McCoy]');

    $location->published(true);
    $location->save();

    expect((string) Antlers::parse($template, [], true))->toContain('[Leonard McCoy]');
});
