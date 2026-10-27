<?php

declare(strict_types=1);

use Carbon\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the UAMS-Web/uams-statamic-archive#919 provider-relationship output filter: wherever a provider
 * relationship renders, the `people` collection is narrowed to clinical-provider-
 * eligible entries via `clinical_provider_profile_eligible_query:is="true"`,
 * mirroring and extending the existing `searchable:is="true"` pattern in
 * resources/views/page_builder/_uamsweb_block_fad_providers.antlers.html.
 *
 * The FaD Providers block itself is still gated behind
 * `$fad_providers_implemented = false` pending the UAMS-Web/uams-statamic-archive#1242 facet contract, so the
 * filter's semantics are proven directly against the collection tag the block
 * uses. An isolated Stache (StacheIsolatedTestCase) holds synthetic `people`
 * entries so the computed eligibility flag is evaluated by the real query layer,
 * not an injected array. The block's structural use of the filter is asserted in
 * FadProvidersBlockGuardTest.
 *
 * @command php artisan test --compact tests/Feature/Views/FadProviderEligibilityFilterTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $collection = CollectionFacade::make('people');
    $collection->title('People');
    $collection->save();
});

/**
 * The minimum people field set where every clinical-provider eligibility base
 * rule passes (mirrors config/clinical_provider_profile.php `base`).
 *
 * @return array<string, mixed>
 */
function eligibilityBasePassFields(): array
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
 * Render the exact eligibility + searchable filter pair the providers block
 * applies to the `people` collection, emitting each match's title in brackets.
 */
function renderEligibleProviderTitles(): string
{
    return (string) Antlers::parse(
        '{{ collection:people clinical_provider_profile_eligible_query:is="true" searchable:is="true" }}[{{ title }}]{{ /collection:people }}',
        [],
        true,
    );
}

/**
 * Persist a synthetic, published `people` entry with the given slug and data.
 *
 * @param  array<string, mixed>  $data
 */
function saveEligibilityPerson(string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

it('renders an eligible, searchable provider', function (): void {
    saveEligibilityPerson('leonard-mccoy', [
        ...eligibilityBasePassFields(),
        'title' => 'Leonard McCoy',
        'credentials' => ['md'],
    ]);

    expect(renderEligibleProviderTitles())->toContain('[Leonard McCoy]');
});

it('excludes a person who fails clinical-provider eligibility (a resident)', function (): void {
    // An eligible-looking person except `resident_query` fails a base rule, so the
    // computed eligibility flag is false and the eligibility filter drops them —
    // even though they are searchable and carry an M.D. credential.
    saveEligibilityPerson('pavel-chekov', [
        ...eligibilityBasePassFields(),
        'resident_query' => true,
        'title' => 'Pavel Chekov',
        'credentials' => ['md'],
    ]);

    expect(renderEligibleProviderTitles())->not->toContain('[Pavel Chekov]');
});

it('excludes an eligible provider who is actively separated (not searchable)', function (): void {
    // Passes eligibility, but an active separation window makes `searchable` false,
    // so the searchable half of the block filter drops them.
    saveEligibilityPerson('hikaru-sulu', [
        ...eligibilityBasePassFields(),
        'title' => 'Hikaru Sulu',
        'credentials' => ['md'],
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    expect(renderEligibleProviderTitles())->not->toContain('[Hikaru Sulu]');
});

it('renders only the eligible, searchable provider from a mixed population', function (): void {
    // Eligible + searchable -> appears.
    saveEligibilityPerson('leonard-mccoy', [
        ...eligibilityBasePassFields(),
        'title' => 'Leonard McCoy',
        'credentials' => ['md'],
    ]);

    // Ineligible (resident) -> excluded.
    saveEligibilityPerson('pavel-chekov', [
        ...eligibilityBasePassFields(),
        'resident_query' => true,
        'title' => 'Pavel Chekov',
        'credentials' => ['md'],
    ]);

    // Eligible but actively separated -> excluded.
    saveEligibilityPerson('hikaru-sulu', [
        ...eligibilityBasePassFields(),
        'title' => 'Hikaru Sulu',
        'credentials' => ['md'],
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    $out = renderEligibleProviderTitles();

    expect($out)->toContain('[Leonard McCoy]')
        ->and($out)->not->toContain('[Pavel Chekov]')
        ->and($out)->not->toContain('[Hikaru Sulu]');
});
