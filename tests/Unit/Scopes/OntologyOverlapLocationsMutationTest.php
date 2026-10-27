<?php

declare(strict_types=1);

use App\Scopes\OntologyOverlapLocations;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(OntologyOverlapLocations::class);

/**
 * Narrow mutate coverage for App\Scopes\OntologyOverlapLocations.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/OntologyOverlapLocationsMutationTest.php
 */

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

it('returns the query unchanged when locations is missing or unusable', function (mixed $locations): void {
    $scope = new OntologyOverlapLocations;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')->never();

    $values = $locations === '__absent__' ? [] : ['locations' => $locations];

    expect($scope->apply($query, $values))->toBe($query);
})->with([
    'absent' => ['__absent__'],
    'empty string' => [''],
    'empty array' => [[]],
    'non-array non-string' => [12],
]);

it('drops empty segments but keeps real ids in a mixed string param', function (): void {
    $scope = new OntologyOverlapLocations;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')
        ->once()
        ->with(
            'clinical_locations',
            ['loc-a', 'loc-b'],
        )
        ->andReturnSelf();

    expect($scope->apply($query, ['locations' => 'loc-a,,loc-b']))->toBe($query);
});

it('adds a json overlap condition for a string location id', function (): void {
    $scope = new OntologyOverlapLocations;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')
        ->once()
        ->with(
            'clinical_locations',
            ['loc-a'],
        )
        ->andReturnSelf();

    expect($scope->apply($query, ['locations' => 'loc-a']))->toBe($query);
});

it('adds a json overlap condition for an array of location ids', function (): void {
    $scope = new OntologyOverlapLocations;
    $ids = ['loc-a', 'loc-b'];
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')
        ->once()
        ->with(
            'clinical_locations',
            $ids,
        )
        ->andReturnSelf();

    expect($scope->apply($query, ['locations' => $ids]))->toBe($query);
});
