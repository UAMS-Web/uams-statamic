<?php

declare(strict_types=1);

use App\Support\People\Lenses\Gates\ClinicalProviderLensGate;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\Support\ClinicalProviderFixtures;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for {@see ClinicalProviderLensGate} — the clinical provider lens gate
 * (UAMS-Web/uams-statamic-archive#571 / UAMS-Web/uams-statamic-archive#1636). It admits a person to the `people/provider/show` lens exactly
 * when they are eligible for a UAMS Health clinical provider profile (UAMS-Web/uams-statamic-archive#919), so the
 * clinical lens is expressed as an ordinary gate with no special-cased branch.
 *
 * @covers \App\Support\People\Lenses\Gates\ClinicalProviderLensGate
 *
 * @command php artisan test --compact tests/Feature/Support/People/Lenses/ClinicalProviderLensGateTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();
});

/**
 * The minimum field set that satisfies every clinical-provider eligibility base
 * rule (mirrors `config/clinical_provider_profile.php`).
 *
 * @return array<string, mixed>
 */
function gateEligibleBaseFields(): array
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
 * @param  array<string, mixed>  $fields
 */
function makeGatePerson(string $slug, array $fields = []): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data([
        ...gateEligibleBaseFields(),
        'title' => 'Person '.$slug,
        'credentials' => ['md'],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/** The `health` site (asserted non-null for the gate's typed parameter). */
function clinicalGateSite(): Statamic\Sites\Site
{
    $site = mustBe(Statamic\Sites\Site::class, Site::get('health'), 'site');

    return $site;
}

it('admits an eligible provider to the clinical lens', function (): void {
    $person = makeGatePerson('leonard-mccoy');

    expect(app(ClinicalProviderLensGate::class)->passes($person, clinicalGateSite()))->toBeTrue();
});

it('rejects an ineligible person (a resident) from the clinical lens', function (): void {
    $person = makeGatePerson('pavel-chekov', ['resident_query' => true]);

    expect(app(ClinicalProviderLensGate::class)->passes($person, clinicalGateSite()))->toBeFalse();
});
