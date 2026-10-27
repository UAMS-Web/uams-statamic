<?php

declare(strict_types=1);

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use App\Services\ComputedFields\Registration\DepartmentHubPeopleComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * @covers \App\Services\ComputedFields\Registration\DepartmentHubPeopleComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/DepartmentHubPeopleComputedsRegistrarTest.php
 */
it('registers the leadership and people computeds on organizations, bound to their resolvers', function (): void {
    $organization = Mockery::mock(Entry::class);
    $leadership = EntryCollection::make([Mockery::mock(Entry::class)]);
    $people = EntryCollection::make([Mockery::mock(Entry::class), Mockery::mock(Entry::class)]);

    $values = Mockery::mock(DepartmentHubPeopleComputedValues::class);
    $values->shouldReceive('resolveLeadership')->once()->with($organization)->andReturn($leadership);
    $values->shouldReceive('resolvePeople')->once()->with($organization)->andReturn($people);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = [$collection, $callback];
        }
    );

    (new DepartmentHubPeopleComputedsRegistrar($values))->register();

    expect(array_keys($captured))->toBe(['department_leadership', 'department_people'])
        ->and(array_column($captured, 0))->toBe(['organizations', 'organizations'])
        ->and(($captured['department_leadership'][1])($organization))->toBe($leadership)
        ->and(($captured['department_people'][1])($organization))->toBe($people);
});
