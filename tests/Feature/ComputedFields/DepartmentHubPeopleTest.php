<?php

declare(strict_types=1);

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\artisan;

/**
 * Behavioral coverage for {@see DepartmentHubPeopleComputedValues} — the
 * Departmental News Hub people-zone derivation from `job_title` (UAMS-Web/uams-statamic-archive#1557).
 *
 * Fixtures live in an isolated Stache (real `people` / `organizations`, throwaway
 * disk). The org-role → role-set mapping is the shipped `config/department_hub.php`,
 * so the cases prove the derivation is driven by that config across the academic,
 * service-line, and institutional org kinds — and omitted for an unmapped one.
 *
 * @covers \App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues
 * @covers \App\Console\Commands\AuditDepartmentPeopleRoleLinks
 *
 * @command  php artisan test --compact tests/Feature/ComputedFields/DepartmentHubPeopleTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Resolve the people-zone service. */
function deptPeopleSvc(): DepartmentHubPeopleComputedValues
{
    return app(DepartmentHubPeopleComputedValues::class);
}

/** Create an `organizations` entry (default site) with the given id/role. */
function deptOrg(string $id, string $role): EntryObject
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('organizations');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('default');
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => 'Org '.$id, 'organization_role' => $role]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Create a `people` entry with the given `job_title` replicator rows.
 *
 * @param  array<int, array<string, mixed>>  $jobTitle  Replicator rows (each a `type` + link).
 */
function deptPerson(string $id, array $jobTitle, bool $published = true): void
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('people');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('default');
    /** @var EntryObject $entry */
    $entry = $entry->published($published);
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => 'Person '.$id, 'job_title' => $jobTitle]);
    $entry->saveQuietly();
}

/**
 * Map a resolved people value (a service `EntryCollection`, or a computed field's
 * augmented value) to entry ids (order-sensitive).
 *
 * @return array<int, string>
 */
function deptPeopleIds(mixed $people): array
{
    if (! is_iterable($people)) {
        return [];
    }

    $ids = [];

    foreach ($people as $entry) {
        if ($entry instanceof EntryObject && \is_string($entry->id())) {
            $ids[] = $entry->id();
        }
    }

    return $ids;
}

beforeEach(function (): void {
    foreach (['organizations', 'people'] as $handle) {
        /** @var Collection $collection */
        $collection = CollectionFacade::make($handle);
        /** @var Collection $collection */
        $collection = $collection->title(ucfirst($handle));
        /** @var Collection $collection */
        $collection = $collection->sites(['default']);
        $collection->save();
    }
});

it('derives an academic department leadership first, then faculty', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    deptPerson('prof', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);
    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    // A faculty member of a different department must not surface.
    deptPerson('other', [['type' => 'faculty_role_title', 'department' => ['org-cardio']]]);

    $people = deptPeopleSvc()->resolvePeople($org);

    // Leadership (chair) first, then faculty (prof); the other department excluded.
    expect(deptPeopleIds($people))->toBe(['chair', 'prof']);
});

it('derives a service line clinical leadership', function (): void {
    $org = deptOrg('org-heart', 'service_line');

    deptPerson('sl-lead', [['type' => 'clinical_leadership_role', 'service_line' => ['org-heart']]]);
    // An academic role naming the same id must not match a service-line org
    // (the mapping selects the clinical set + service_line link only).
    deptPerson('academic', [['type' => 'faculty_role_title', 'department' => ['org-heart']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['sl-lead']);
});

it('derives an institutional department leadership then general role', function (): void {
    $org = deptOrg('org-hr', 'department_institutional');

    deptPerson('inst-member', [['type' => 'institutional_role', 'department' => ['org-hr']]]);
    deptPerson('inst-lead', [['type' => 'institutional_leadership_role', 'department' => ['org-hr']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['inst-lead', 'inst-member']);
});

it('omits the people zone for an unmapped org role', function (): void {
    $org = deptOrg('org-brand', 'brand');

    deptPerson('someone', [['type' => 'academic_leadership_role', 'department' => ['org-brand']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe([]);
});

it('omits the people zone for an org with no role', function (): void {
    $org = deptOrg('org-none', '');

    deptPerson('someone', [['type' => 'academic_leadership_role', 'department' => ['org-none']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe([]);
});

it('resolveLeadership returns only the leadership spine, not faculty', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptPerson('prof', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolveLeadership($org)))->toBe(['chair']);
});

it('lists a person holding both leadership and faculty once, under leadership', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    // One person, two roles: chair AND faculty of the same department.
    deptPerson('dual', [
        ['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']],
        ['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']],
    ]);
    deptPerson('prof', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);

    // `dual` appears once, ahead of the plain faculty member.
    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['dual', 'prof']);
});

it('excludes draft people', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    deptPerson('published-chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptPerson('draft-chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]], published: false);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['published-chair']);
});

it('matches a scalar-stored link the same as an array-stored one', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    // The CP stores a max_items:1 link as a scalar; the importer as an array.
    deptPerson('scalar-link', [['type' => 'academic_leadership_role', 'department' => 'org-anesthesiology']]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['scalar-link']);
});

it('registers department_leadership and department_people computeds on organizations', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptPerson('prof', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);

    $leadershipValue = $org->augmentedValue('department_leadership');
    $peopleValue = $org->augmentedValue('department_people');
    Assert::assertInstanceOf(Value::class, $leadershipValue);
    Assert::assertInstanceOf(Value::class, $peopleValue);

    expect(deptPeopleIds($leadershipValue->value()))->toBe(['chair'])
        ->and(deptPeopleIds($peopleValue->value()))->toBe(['chair', 'prof']);
});

it('audits role-link completeness per role set', function (): void {
    // Two academic-leadership rows (both linked), and two faculty rows (one linked).
    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptPerson('vice-chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptPerson('linked-prof', [['type' => 'faculty_role_title', 'department' => ['org-anesthesiology']]]);
    deptPerson('unlinked-prof', [['type' => 'faculty_role_title', 'department' => []]]);

    $report = deptPeopleSvc()->auditRoleLinkCompleteness();

    expect($report['academic_leadership_role']['rows'])->toBe(2)
        ->and($report['academic_leadership_role']['linked'])->toBe(2)
        ->and($report['academic_leadership_role']['leadership'])->toBeTrue()
        ->and($report['faculty_role_title']['rows'])->toBe(2)
        ->and($report['faculty_role_title']['linked'])->toBe(1)
        ->and($report['faculty_role_title']['leadership'])->toBeFalse();
});

it('the audit command reports completeness and exits zero', function (): void {
    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);

    $command = artisan('people:audit-department-role-links');
    Assert::assertInstanceOf(PendingCommand::class, $command);

    $command
        ->expectsOutputToContain('job_title org-link completeness')
        ->assertExitCode(0);
});

/**
 * Save an `organizations` entry that carries `job_title` rows of its own, so a
 * scan that forgot to scope to `people` would pick it up.
 *
 * @param  array<int, array<string, mixed>>  $jobTitle  Replicator rows.
 */
function deptNonPersonWithRoles(string $id, array $jobTitle): void
{
    /** @var EntryObject $entry */
    $entry = Entry::make();
    /** @var EntryObject $entry */
    $entry = $entry->collection('organizations');
    /** @var EntryObject $entry */
    $entry = $entry->id($id);
    /** @var EntryObject $entry */
    $entry = $entry->slug($id);
    /** @var EntryObject $entry */
    $entry = $entry->locale('default');
    /** @var EntryObject $entry */
    $entry = $entry->published(true);
    /** @var EntryObject $entry */
    $entry = $entry->data(['title' => 'Not a person '.$id, 'job_title' => $jobTitle]);
    $entry->saveQuietly();
}

it('seeds a zero audit row for every mapped set when there is no people collection', function (): void {
    $people = CollectionFacade::findByHandle('people');
    Assert::assertInstanceOf(Collection::class, $people);
    $people->delete();

    $report = deptPeopleSvc()->auditRoleLinkCompleteness();

    expect($report)->toHaveKeys([
        'academic_leadership_role',
        'faculty_role_title',
        'clinical_leadership_role',
        'institutional_leadership_role',
        'institutional_role',
    ])
        ->and($report['academic_leadership_role'])->toBe(['link' => 'department', 'leadership' => true, 'rows' => 0, 'linked' => 0])
        ->and($report['clinical_leadership_role'])->toBe(['link' => 'service_line', 'leadership' => true, 'rows' => 0, 'linked' => 0]);
});

it('audits only published people, not drafts or entries in other collections', function (): void {
    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptPerson('draft-chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]], published: false);
    deptNonPersonWithRoles('org-with-roles', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);

    $report = deptPeopleSvc()->auditRoleLinkCompleteness();

    expect($report['academic_leadership_role']['rows'])->toBe(1)
        ->and($report['academic_leadership_role']['linked'])->toBe(1);
});

it('does not count an empty or non-string organization id as a link', function (): void {
    deptPerson('blank-link', [['type' => 'academic_leadership_role', 'department' => ['']]]);
    deptPerson('int-link', [['type' => 'academic_leadership_role', 'department' => [5]]]);

    $report = deptPeopleSvc()->auditRoleLinkCompleteness();

    expect($report['academic_leadership_role']['rows'])->toBe(2)
        ->and($report['academic_leadership_role']['linked'])->toBe(0);
});

it('reports the audit leadership flag as a boolean, defaulting to false', function (): void {
    config(['department_hub.people_role_map' => [
        'custom_role' => [
            ['set' => 'custom_leadership', 'link' => 'department', 'leadership' => 1],
            ['set' => 'custom_member', 'link' => 'department'],
        ],
    ]]);

    $report = deptPeopleSvc()->auditRoleLinkCompleteness();

    expect($report['custom_leadership']['leadership'])->toBeTrue()
        ->and($report['custom_member']['leadership'])->toBeFalse();
});

it('returns no people for an organization that has no id yet', function (): void {
    /** @var EntryObject $org */
    $org = Entry::make();
    /** @var EntryObject $org */
    $org = $org->collection('organizations');
    /** @var EntryObject $org */
    $org = $org->data(['title' => 'Unsaved org', 'organization_role' => 'department_academic']);

    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);

    expect($org->id())->toBeNull()
        ->and(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe([]);
});

it('treats an empty organization role as unmapped even when the map has an empty key', function (): void {
    config(['department_hub.people_role_map' => [
        '' => [['set' => 'academic_leadership_role', 'link' => 'department', 'leadership' => true]],
    ]]);

    $org = deptOrg('org-blank-role', '');
    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-blank-role']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe([]);
});

it('treats a descriptor with no leadership flag as a non-leadership set', function (): void {
    config(['department_hub.people_role_map' => [
        'custom_role' => [['set' => 'academic_leadership_role', 'link' => 'department']],
    ]]);

    $org = deptOrg('org-custom', 'custom_role');
    deptPerson('member', [['type' => 'academic_leadership_role', 'department' => ['org-custom']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolveLeadership($org)))->toBe([])
        ->and(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['member']);
});

it('finds a leadership set listed after a non-leadership one', function (): void {
    config(['department_hub.people_role_map' => [
        'custom_role' => [
            ['set' => 'faculty_role_title', 'link' => 'department', 'leadership' => false],
            ['set' => 'academic_leadership_role', 'link' => 'department', 'leadership' => true],
        ],
    ]]);

    $org = deptOrg('org-custom', 'custom_role');
    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-custom']]]);
    deptPerson('prof', [['type' => 'faculty_role_title', 'department' => ['org-custom']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolveLeadership($org)))->toBe(['chair']);
});

it('derives people from the people collection only', function (): void {
    $org = deptOrg('org-anesthesiology', 'department_academic');

    deptPerson('chair', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);
    deptNonPersonWithRoles('org-with-roles', [['type' => 'academic_leadership_role', 'department' => ['org-anesthesiology']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['chair']);
});

it('matches an organization id exactly, not by loose numeric comparison', function (): void {
    // '100' == '1e2' under PHP's loose comparison of numeric strings.
    $org = deptOrg('100', 'department_academic');

    deptPerson('look-alike', [['type' => 'academic_leadership_role', 'department' => ['1e2']]]);
    deptPerson('exact', [['type' => 'academic_leadership_role', 'department' => ['100']]]);

    expect(deptPeopleIds(deptPeopleSvc()->resolvePeople($org)))->toBe(['exact']);
});
