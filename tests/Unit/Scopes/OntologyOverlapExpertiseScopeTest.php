<?php

declare(strict_types=1);

use App\Scopes\OntologyOverlapExpertise;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(OntologyOverlapExpertise::class);

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
 * Narrow mutate coverage for App\Scopes\OntologyOverlapExpertise.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/OntologyOverlapExpertiseScopeTest.php
 */
it('returns the query unchanged when expertise is missing or unusable', function (mixed $expertise): void {
    $scope = new OntologyOverlapExpertise;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('whereJsonOverlaps')->never();

    $values = $expertise === '__absent__' ? [] : ['expertise' => $expertise];

    expect($scope->apply($query, $values))->toBe($query);
})->with([
    'absent' => ['__absent__'],
    // `explode(',', '')` returns `['']`, so without the empty-segment filter this would
    // apply `whereJsonOverlaps('expertise', [''])` and match nothing: the bug that left
    // the Providers block empty whenever the editor selected no Areas of Expertise (UAMS-Web/uams-statamic-archive#1242).
    'empty string' => [''],
    'empty array' => [[]],
    'non-array non-string' => [12],
]);

it('drops empty segments but keeps real ids in a mixed string param', function (): void {
    $scope = new OntologyOverlapExpertise;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('whereJsonOverlaps')
        ->once()
        ->with(
            'expertise',
            ['exp-a', 'exp-b'],
        )
        ->andReturnSelf();

    expect($scope->apply($query, ['expertise' => 'exp-a,,exp-b']))->toBe($query);
});

it('adds a json overlap condition for a string expertise id', function (): void {
    $scope = new OntologyOverlapExpertise;
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('whereJsonOverlaps')
        ->once()
        ->with(
            'expertise',
            ['exp-a'],
        )
        ->andReturnSelf();

    expect($scope->apply($query, ['expertise' => 'exp-a']))->toBe($query);
});

it('adds a json overlap condition for an array of expertise ids', function (): void {
    $scope = new OntologyOverlapExpertise;
    $ids = ['exp-a', 'exp-b'];
    /** @var Builder&MockInterface $query */
    $query = Mockery::mock();
    $query->shouldReceive('whereJsonOverlaps')
        ->once()
        ->with(
            'expertise',
            $ids,
        )
        ->andReturnSelf();

    expect($scope->apply($query, ['expertise' => $ids]))->toBe($query);
});
