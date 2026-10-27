<?php

declare(strict_types=1);

use App\Console\Commands\AuditDepartmentPeopleRoleLinks;
use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use Illuminate\Testing\PendingCommand;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

use function Pest\Laravel\artisan;

/**
 * Mutation-facing coverage for `people:audit-department-role-links` (UAMS-Web/uams-statamic-archive#1557).
 *
 * The service that builds the report is mocked so each command branch (empty
 * config, table columns, leadership skip, derive-vs-curate threshold, percent
 * formatting) can be pinned with `expectsTable` / exact line output.
 *
 * @command  php artisan test --compact tests/Feature/Console/AuditDepartmentPeopleRoleLinksTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(AuditDepartmentPeopleRoleLinks::class);

/**
 * Bind a people-service stub that returns the given completeness report, then
 * return the pending artisan command for fluent assertions.
 *
 * @param  array<string, array{leadership: bool, link: string, rows: int, linked: int}>  $report
 */
function roleLinkAudit(array $report): PendingCommand
{
    // Stub the people service that builds the completeness report
    $people = Mockery::mock(DepartmentHubPeopleComputedValues::class);
    $people->shouldReceive('auditRoleLinkCompleteness')->andReturn($report);

    // Bind the stub into the container for the Artisan command
    app()->instance(DepartmentHubPeopleComputedValues::class, $people);

    // Start the pending artisan command for fluent assertions
    $pending = artisan('people:audit-department-role-links');

    // Narrow the pending command type for static analysis
    Assert::assertInstanceOf(PendingCommand::class, $pending);

    return $pending;
}

it('warns and exits zero when no role-set mapping is configured', function (): void {
    roleLinkAudit([])
        ->expectsOutputToContain(
            'No Departmental News Hub role-set mapping is configured (config/department_hub.php).'
        )
        ->doesntExpectOutputToContain(
            'Departmental News Hub — job_title org-link completeness (published people):'
        )
        ->assertExitCode(0);
});

it('prints the completeness table and member recommendations only', function (): void {
    roleLinkAudit([
        'academic_leadership_role' => [
            'leadership' => true,
            'link' => 'department',
            'rows' => 2,
            'linked' => 2,
        ],
        'faculty_role_title' => [
            'leadership' => false,
            'link' => 'department',
            'rows' => 10,
            'linked' => 8,
        ],
        'member_role_title' => [
            'leadership' => false,
            'link' => 'service_line',
            'rows' => 10,
            'linked' => 7,
        ],
    ])
        ->expectsOutputToContain(
            'Departmental News Hub — job_title org-link completeness (published people):'
        )
        ->expectsTable(
            ['Role set', 'Kind', 'Link', 'Rows', 'Linked', 'Linked %'],
            [
                ['academic_leadership_role', 'leadership', 'department', 2, 2, '100%'],
                ['faculty_role_title', 'member', 'department', 10, 8, '80%'],
                ['member_role_title', 'member', 'service_line', 10, 7, '70%'],
            ]
        )
        ->expectsOutputToContain(
            '  - faculty_role_title: derive the fuller roster from this set.'
        )
        ->expectsOutputToContain(
            '  - member_role_title: keep the fuller roster curated (link too sparse to derive).'
        )
        ->doesntExpectOutputToContain('academic_leadership_role: derive')
        ->doesntExpectOutputToContain('academic_leadership_role: keep')
        ->assertExitCode(0);
});

it('formats n/a when a set has zero rows and still recommends curated', function (): void {
    roleLinkAudit([
        'empty_member_set' => [
            'leadership' => false,
            'link' => 'department',
            'rows' => 0,
            'linked' => 0,
        ],
    ])
        ->expectsTable(
            ['Role set', 'Kind', 'Link', 'Rows', 'Linked', 'Linked %'],
            [
                ['empty_member_set', 'member', 'department', 0, 0, 'n/a'],
            ]
        )
        ->expectsOutputToContain(
            '  - empty_member_set: keep the fuller roster curated (link too sparse to derive).'
        )
        ->assertExitCode(0);
});

it('treats a single-row set as rate-eligible and rounds odd percentages', function (): void {
    roleLinkAudit([
        'single_row_set' => [
            'leadership' => false,
            'link' => 'department',
            'rows' => 1,
            'linked' => 1,
        ],
        // 2/3 → 66.6%: round=67, floor=66: kills RoundToFloor.
        'two_thirds_set' => [
            'leadership' => false,
            'link' => 'department',
            'rows' => 3,
            'linked' => 2,
        ],
        // 1/3 → 33.3%: round=33, ceil=34: kills RoundToCeil.
        'one_third_set' => [
            'leadership' => false,
            'link' => 'department',
            'rows' => 3,
            'linked' => 1,
        ],
    ])
        ->expectsTable(
            ['Role set', 'Kind', 'Link', 'Rows', 'Linked', 'Linked %'],
            [
                ['single_row_set', 'member', 'department', 1, 1, '100%'],
                ['two_thirds_set', 'member', 'department', 3, 2, '67%'],
                ['one_third_set', 'member', 'department', 3, 1, '33%'],
            ]
        )
        ->expectsOutputToContain(
            '  - single_row_set: derive the fuller roster from this set.'
        )
        ->expectsOutputToContain(
            '  - two_thirds_set: keep the fuller roster curated (link too sparse to derive).'
        )
        ->expectsOutputToContain(
            '  - one_third_set: keep the fuller roster curated (link too sparse to derive).'
        )
        ->assertExitCode(0);
});
