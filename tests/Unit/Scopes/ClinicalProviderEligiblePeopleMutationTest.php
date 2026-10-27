<?php

declare(strict_types=1);

use App\Scopes\ClinicalProviderEligiblePeople;
use App\Services\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Query\Builder;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ClinicalProviderEligiblePeople::class);

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
 * Narrow mutate coverage for App\Scopes\ClinicalProviderEligiblePeople.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ClinicalProviderEligiblePeopleMutationTest.php
 */
it('skips non-Entry rows from the people collection when resolving eligible ids', function (): void {
    // InstanceOfToTrue on ``$person instanceof Entry`` would call the predicate on
    // the stdClass and fail: the guard must keep apply() to Entry instances only.
    $eligible = Mockery::mock(Entry::class);
    $eligible->shouldReceive('id')->once()->andReturn('person-ok');

    $computed = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $computed->shouldReceive('computeClinicalProviderProfileEligibleQuery')
        ->once()
        ->with($eligible)
        ->andReturn(true);

    $entries = Mockery::mock(StacheEntryRepository::class);
    $entries->shouldReceive('whereCollection')
        ->once()
        ->with('people')
        ->andReturn(EntryCollection::make([
            new stdClass,
            $eligible,
        ]));

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', ['person-ok'])
        ->andReturnSelf();

    $scope = new ClinicalProviderEligiblePeople($computed, $entries);
    $scope->apply($query, []);
});
