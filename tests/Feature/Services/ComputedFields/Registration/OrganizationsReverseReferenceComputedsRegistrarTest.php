<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\OrganizationsReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Reverse\OrganizationsReverseReferenceComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(OrganizationsReverseReferenceComputedsRegistrar::class);

/**
 * The computed handles the organizations registrar owns, in registration order.
 *
 * @var list<string>
 */
const ORGANIZATIONS_REVERSE_HANDLES = [
    'service_line_members',
    'academic_department_residents',
    'academic_department_fellows',
];

/**
 * @covers \App\Services\ComputedFields\Registration\OrganizationsReverseReferenceComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/OrganizationsReverseReferenceComputedsRegistrarTest.php
 */
it('registers all three reverse computeds exactly once on the organizations collection', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collection;
        }
    );

    app(OrganizationsReverseReferenceComputedsRegistrar::class)->register();

    expect($callCount)->toBe(count(ORGANIZATIONS_REVERSE_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(ORGANIZATIONS_REVERSE_HANDLES);

    foreach (ORGANIZATIONS_REVERSE_HANDLES as $handle) {
        expect($captured[$handle])->toBe('organizations');
    }
});

it('wires each computed handle to its owning resolver method', function (): void {
    // Distinct return instances catch a swapped binding (members ↔ residents ↔ fellows).
    $members = EntryCollection::make();
    $residents = EntryCollection::make();
    $fellows = EntryCollection::make();

    $values = Mockery::mock(OrganizationsReverseReferenceComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $values->shouldReceive('resolveServiceLineMembers')->with($entry)->andReturn($members);
    $values->shouldReceive('resolveAcademicDepartmentResidents')->with($entry)->andReturn($residents);
    $values->shouldReceive('resolveAcademicDepartmentFellows')->with($entry)->andReturn($fellows);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new OrganizationsReverseReferenceComputedsRegistrar($values))->register();

    expect(($captured['service_line_members'])($entry))->toBe($members)
        ->and(($captured['academic_department_residents'])($entry))->toBe($residents)
        ->and(($captured['academic_department_fellows'])($entry))->toBe($fellows);
});
