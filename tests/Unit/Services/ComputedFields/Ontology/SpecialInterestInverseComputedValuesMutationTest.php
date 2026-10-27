<?php

declare(strict_types=1);

use App\Services\ComputedFields\Ontology\SpecialInterestInverseComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SpecialInterestInverseComputedValues::class);

/**
 * Narrow mutate coverage for SpecialInterestInverseComputedValues.
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Ontology/SpecialInterestInverseComputedValuesMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        Blink::flush();

        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }

    Blink::flush();
});

/**
 * A Condition/Service entry mock exposing its id and its own collection.
 */
function specialInterestCtEntry(
    mixed $id,
    mixed $collection = 'clinical_conditions',
): Entry {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);

    return $entry;
}

/**
 * A resolved referencing entry mock exposing only its id.
 */
function specialInterestRelatedEntry(string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * @return array<int|string, mixed>
 */
function specialInterestInverseIds(EntryCollection $set): array
{
    return $set->map(function (mixed $entry): mixed {
        /** @var Entry $entry */
        return $entry->id();
    })->all();
}

it('resolves published providers whose conditions field names a Condition entry', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'people')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('special_interest_clinical_conditions', 'cond-1')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([specialInterestRelatedEntry('person-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    expect(specialInterestInverseIds(
        (new SpecialInterestInverseComputedValues)->resolveSpecialInterestProviders(
            specialInterestCtEntry('cond-1', 'clinical_conditions'),
        ),
    ))->toBe(['person-a']);
});

it('resolves published locations whose services field names a Service entry', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'clinical_locations')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('special_interest_clinical_services', 'svc-1')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([specialInterestRelatedEntry('loc-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    expect(specialInterestInverseIds(
        (new SpecialInterestInverseComputedValues)->resolveSpecialInterestLocations(
            specialInterestCtEntry('svc-1', 'clinical_services'),
        ),
    ))->toBe(['loc-a']);
});

it('chooses the queried field by the C/T entry collection, not the source collection', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'people')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('special_interest_clinical_services', 'svc-2')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([specialInterestRelatedEntry('person-b')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    expect(specialInterestInverseIds(
        (new SpecialInterestInverseComputedValues)->resolveSpecialInterestProviders(
            specialInterestCtEntry('svc-2', 'clinical_services'),
        ),
    ))->toBe(['person-b']);
});

it('memoizes the inverse set per entry per request', function (): void {
    $query = Mockery::mock();
    $query->shouldReceive('where')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->andReturnSelf();
    $query->shouldReceive('whereStatus')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([specialInterestRelatedEntry('person-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = specialInterestCtEntry('cond-memo', 'clinical_conditions');
    $service = new SpecialInterestInverseComputedValues;

    expect(specialInterestInverseIds($service->resolveSpecialInterestProviders($entry)))->toBe(['person-a'])
        ->and(specialInterestInverseIds($service->resolveSpecialInterestProviders($entry)))->toBe(['person-a']);
});

it('returns an empty inverse for an unsaved or empty-string C/T id', function (): void {
    EntryFacade::shouldReceive('query')->never();
    $service = new SpecialInterestInverseComputedValues;

    expect(specialInterestInverseIds($service->resolveSpecialInterestProviders(specialInterestCtEntry(null))))->toBe([])
        ->and(specialInterestInverseIds($service->resolveSpecialInterestLocations(specialInterestCtEntry(''))))->toBe([]);
});

it('does not memoize under a non-string id that would break the Blink key', function (): void {
    // RemoveEarlyReturn on the non-string arm would fall into sprintf(%s) with an array.
    EntryFacade::shouldReceive('query')->never();

    expect(specialInterestInverseIds(
        (new SpecialInterestInverseComputedValues)->resolveSpecialInterestProviders(
            specialInterestCtEntry(['unstable-id']),
        ),
    ))->toBe([]);
});

it('returns an empty inverse for an unmapped or non-string collection handle', function (): void {
    EntryFacade::shouldReceive('query')->never();
    $service = new SpecialInterestInverseComputedValues;

    expect(specialInterestInverseIds(
        $service->resolveSpecialInterestProviders(specialInterestCtEntry('exp-1', 'clinical_expertise')),
    ))->toBe([])
        ->and(specialInterestInverseIds(
            $service->resolveSpecialInterestProviders(specialInterestCtEntry('exp-2', ['not-a-string'])),
        ))->toBe([]);
});
