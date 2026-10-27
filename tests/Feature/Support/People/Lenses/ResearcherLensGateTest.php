<?php

declare(strict_types=1);

use App\Support\People\Lenses\Gates\ResearcherLensGate;
use App\Support\People\ResearcherProfileEligibility;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Sites\Site;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Coverage for {@see ResearcherLensGate} — the researcher people lens gate (UAMS-Web/uams-statamic-archive#1667).
 * It admits a person to the `people/researcher/show` lens exactly when they have a
 * public research identity ({@see ResearcherProfileEligibility}),
 * regardless of the requesting site: the signal is person-level because researchers
 * are scattered across sites and the org-to-people bridge has no research role.
 *
 * @covers \App\Support\People\Lenses\Gates\ResearcherLensGate
 *
 * @command php artisan test --compact tests/Feature/Support/People/Lenses/ResearcherLensGateTest.php
 */

/**
 * A `people` entry whose `get()` returns the given field values (null otherwise).
 *
 * @param  array<string, mixed>  $values
 */
function researcherGatePerson(array $values = []): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return $entry;
}

/** A Site mock exposing the given handle. */
function researcherGateSite(string $handle): Site
{
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('handle')->andReturn($handle);

    return $site;
}

it('admits a researcher to the researcher lens on any site', function (string $siteHandle): void {
    $person = researcherGatePerson(['orcid_id' => '0000-0002-1825-0097']);

    expect(app(ResearcherLensGate::class)->passes($person, researcherGateSite($siteHandle)))->toBeTrue();
})->with([
    'default',
    'health',
    'medicine_anesthesiology',
    'research',
]);

it('rejects a non-researcher from the researcher lens on any site', function (string $siteHandle): void {
    $person = researcherGatePerson(['title' => 'James Kirk']);

    expect(app(ResearcherLensGate::class)->passes($person, researcherGateSite($siteHandle)))->toBeFalse();
})->with([
    'default',
    'health',
    'medicine_anesthesiology',
    'research',
]);
