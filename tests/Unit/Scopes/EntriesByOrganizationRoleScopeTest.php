<?php

declare(strict_types=1);

use App\Scopes\EntriesByOrganizationRole;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\EntriesByOrganizationRole
 *
 * Mutation: 97.14% on App\Scopes\EntriesByOrganizationRole (34/35 killed, --covered-only),
 * re-measured under Pest 5 after UAMS-Web/uams-statamic-archive#1759 retired this file's placeholder assertions.
 *
 * The lone survivor is an EQUIVALENT mutant and is an accepted, permanent gap: line 178's
 * `\in_array($suffix, self::ROLES, true)` flipped to loose. `$suffix` is always a string
 * (substr of a value the loop already guarded with is_string), and every ROLES entry is a
 * non-numeric string — and PHP 8 compares two strings numerically only when BOTH are
 * numeric. Loose and strict therefore agree on every reachable input, so no test can kill
 * it. Do not "fix" this by contriving one; re-measuring will keep reporting 34/35.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/EntriesByOrganizationRoleScopeTest.php
 */
it('skips the filter when no recognized handle is present', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'other_scope',
            ],
        ]
    );
});

it('filters by a single role', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', ['service_line'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_service_line',
            ],
        ]
    );
});

it('expands the academic unit group', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', [
            'department_academic',
            'division_academic',
            'program_academic',
            'program_academic_degree',
        ])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_academic_unit',
            ],
        ]
    );
});

it('expands the clinical unit group', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', [
            'department_clinical',
            'service_line',
        ])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_clinical_unit',
            ],
        ]
    );
});

it('expands the department unit group', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', [
            'college',
            'institute',
            'department_academic',
            'division_academic',
            'department_clinical',
            'department_institutional',
            'service_line',
        ])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_department_unit',
            ],
        ]
    );
});

it('unions multiple handles without duplicates', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    // The group and the bare role both contribute `service_line`; it is
    // de-duplicated, and the group's members come first.
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', [
            'department_clinical',
            'service_line',
        ])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_clinical_unit',
                'entries_by_organization_role_service_line',
            ],
        ]
    );
});

it('lower-cases the handle suffix before matching a role', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', ['service_line'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_SERVICE_LINE',
            ],
        ]
    );
});

it('skips a non-string handle and still reads the ones after it', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    // A leading non-string entry must be skipped (continue), not abort the loop
    // (break), so the valid handle after it is still resolved.
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', ['service_line'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                123,
                'entries_by_organization_role_service_line',
            ],
        ]
    );
});

it('skips a non-prefixed handle and still reads the ones after it', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    // A string handle that is not one of ours must be skipped (continue), not
    // abort the loop (break), so a valid handle after it is still resolved.
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', ['service_line'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'some_other_scope',
                'entries_by_organization_role_service_line',
            ],
        ]
    );
});

it('reindexes to a list after de-duplicating a mid-sequence role', function () {
    $scope = new EntriesByOrganizationRole;
    $query = Mockery::mock(Builder::class);
    // clinical_unit (department_clinical, service_line) + department_clinical +
    // campus yields [dept_clinical, service_line, dept_clinical, campus]; the
    // duplicate is mid-sequence, so array_unique leaves a hole that must be
    // reindexed to a 0-based list.
    $query->shouldReceive('whereIn')
        ->once()
        ->with('organization_role', ['department_clinical', 'service_line', 'campus'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_organization_role_clinical_unit',
                'entries_by_organization_role_department_clinical',
                'entries_by_organization_role_campus',
            ],
        ]
    );
});

it('has aliases covering every role and group', function () {
    $aliases = EntriesByOrganizationRole::aliases();

    foreach (EntriesByOrganizationRole::ROLES as $role) {
        expect($aliases)->toContain('entries_by_organization_role_'.$role);
    }

    foreach (array_keys(EntriesByOrganizationRole::ROLE_GROUPS) as $group) {
        expect($aliases)->toContain('entries_by_organization_role_'.$group);
    }
});
