<?php

declare(strict_types=1);

use App\Scopes\PeopleInGroups;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(PeopleInGroups::class);

/**
 * @covers \App\Scopes\PeopleInGroups
 *
 * @command  php artisan test --compact tests/Unit/Scopes/PeopleInGroupsScopeTest.php
 */
it('applies no constraint when no groups are selected', function (): void {
    $scope = new PeopleInGroups;
    $query = Mockery::mock(Builder::class);
    $query->shouldNotReceive('where');
    $query->shouldNotReceive('whereJsonContains');

    $scope->apply(
        $query,
        [],
    );
});

it('ORs whereJsonContains for each selected group id', function (): void {
    $scope = new PeopleInGroups;
    $inner = Mockery::mock(Builder::class);
    $inner->shouldReceive('orWhereJsonContains')
        ->once()
        ->with(
            'people_groups',
            'g1',
        )
        ->andReturnSelf();
    $inner->shouldReceive('orWhereJsonContains')
        ->once()
        ->with(
            'people_groups',
            'g2',
        )
        ->andReturnSelf();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(Mockery::on(function (callable $callback) use ($inner): bool {
            $callback($inner);

            return true;
        }))
        ->andReturnSelf();

    $scope->apply(
        $query,
        ['groups' => ['g1', 'g2']],
    );
});
