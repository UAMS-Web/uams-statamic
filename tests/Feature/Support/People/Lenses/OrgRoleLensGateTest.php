<?php

declare(strict_types=1);

use App\Support\People\Lenses\Gates\AcademicLensGate;
use App\Support\People\Lenses\Gates\InstitutionalLensGate;
use App\Support\People\Lenses\Gates\OrgRoleLensGate;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Coverage for the org-derived people lens gates (UAMS-Web/uams-statamic-archive#1636): {@see AcademicLensGate}
 * and {@see InstitutionalLensGate} over {@see OrgRoleLensGate}.
 *
 * The org + people are real fixtures in an isolated Stache (so `resolvePeople`
 * scans real `job_title` links), while the site→org bridge global is mocked
 * (mirroring DepartmentHubResolverTest). Together they prove the gate admits a
 * person only on a hub site whose organization has the lens's role AND only when
 * the person is in that org's derived roster.
 *
 * @covers \App\Support\People\Lenses\Gates\OrgRoleLensGate
 * @covers \App\Support\People\Lenses\Gates\AcademicLensGate
 * @covers \App\Support\People\Lenses\Gates\InstitutionalLensGate
 *
 * @command php artisan test --compact tests/Feature/Support/People/Lenses/OrgRoleLensGateTest.php
 */
uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

// Institutional already scored narrow yes; Academic has a dedicated unit file.
covers(InstitutionalLensGate::class);

beforeEach(function (): void {
    // resolvePeople memoizes per (org, role); flush so each case is independent.
    Blink::flush();

    foreach (['organizations', 'people'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title(ucfirst($handle));
        $collection->save();
    }
});

/** Create an `organizations` entry with the given id + role. */
function orgGateOrg(string $id, string $role): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('organizations');
    $entry->id($id);
    $entry->slug($id);
    $entry->locale('default');
    $entry->published(true);
    $entry->data(['title' => 'Org '.$id, 'organization_role' => $role]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Create a published `people` entry with the given `job_title` replicator rows.
 *
 * @param  array<int, array<string, mixed>>  $jobTitle
 */
function orgGatePerson(string $id, array $jobTitle): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->id($id);
    $entry->slug($id);
    $entry->locale('default');
    $entry->published(true);
    $entry->data(['title' => 'Person '.$id, 'job_title' => $jobTitle]);
    $entry->saveQuietly();

    return $entry;
}

/** Mock the multisite global so `$siteHandle` resolves to `$orgId` (or no hub org when null). */
function orgGateMockGlobal(string $siteHandle, ?string $orgId): void
{
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->with('department_organization')->andReturn($orgId === null ? null : [$orgId]);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->with($siteHandle)->andReturn($variables);

    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);
}

/** A Site mock exposing the given handle. */
function orgGateSite(string $handle): SiteObject
{
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn($handle);

    return $site;
}

it('admits a person in the org roster to the academic lens on an academic hub site', function (): void {
    orgGateOrg('org-anesthesiology', 'department_academic');
    $person = orgGatePerson('jane-doe', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);
    orgGateMockGlobal('medicine_anesthesiology', 'org-anesthesiology');

    expect(app(AcademicLensGate::class)->passes($person, orgGateSite('medicine_anesthesiology')))->toBeTrue();
});

it('admits a division_academic hub to the academic lens (both mapped roles)', function (): void {
    // `config/department_hub.php` maps BOTH `department_academic` and `division_academic`
    // to the faculty/leadership role sets, so a division hub is a legitimate academic-lens
    // host. Pins the second role so dropping it from AcademicLensGate::orgRoles() fails.
    orgGateOrg('org-div', 'division_academic');
    $person = orgGatePerson('div-faculty', [['type' => 'faculty_role_title', 'department' => ['org-div']]]);
    orgGateMockGlobal('medicine_interventional_pain_medicine', 'org-div');

    expect(app(AcademicLensGate::class)->passes($person, orgGateSite('medicine_interventional_pain_medicine')))->toBeTrue();
});

it('rejects a person who is not in the org roster', function (): void {
    orgGateOrg('org-anesthesiology', 'department_academic');
    $stranger = orgGatePerson('stranger', [['type' => 'faculty_role_title', 'department' => ['some-other-org']]]);
    orgGateMockGlobal('medicine_anesthesiology', 'org-anesthesiology');

    expect(app(AcademicLensGate::class)->passes($stranger, orgGateSite('medicine_anesthesiology')))->toBeFalse();
});

it('rejects the academic lens on a site that is not a hub', function (): void {
    orgGateOrg('org-anesthesiology', 'department_academic');
    $person = orgGatePerson('jane-doe', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);
    orgGateMockGlobal('plain_site', null);

    expect(app(AcademicLensGate::class)->passes($person, orgGateSite('plain_site')))->toBeFalse();
});

it('keeps the academic and institutional lenses disjoint by org role', function (): void {
    orgGateOrg('org-hr', 'department_institutional');
    $person = orgGatePerson('bob', [['type' => 'institutional_role', 'department' => ['org-hr']]]);
    orgGateMockGlobal('human_resources', 'org-hr');

    // The academic lens must not claim an institutional-org site; the institutional lens does.
    expect(app(AcademicLensGate::class)->passes($person, orgGateSite('human_resources')))->toBeFalse()
        ->and(app(InstitutionalLensGate::class)->passes($person, orgGateSite('human_resources')))->toBeTrue();
});
