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

/*
|--------------------------------------------------------------------------
| SpecialInterestInverseComputedValues (UAMS-Web/uams-statamic-archive#1064 C — one-sided computed inverse)
|--------------------------------------------------------------------------
|
| Special Interest is authored on ONE side (Provider/Location) as two typed fields;
| the C/T inverse is a pure derivation — every published people / clinical_locations
| entry whose matching typed field contains this C/T id. The field is chosen by the
| C/T entry's own collection: a Condition is matched against
| special_interest_clinical_conditions, a Service against
| special_interest_clinical_services. Blink-memoized per entry per request, no stored
| sync (the recorded UAMS-Web/uams-statamic-archive#1064 decision). These mock the query to prove the exact
| whereJsonContains lookup, the per-collection field targeting, memoization, and the
| empty inverse for an unsaved or unmapped C/T entry.
|
| @covers \App\Services\ComputedFields\Ontology\SpecialInterestInverseComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Ontology/SpecialInterestInverseComputedValuesTest.php
*/

beforeEach(function () {
    Blink::flush();
});

/** A Condition/Service entry mock exposing its id and its own collection. */
function inverseCtEntry(?string $id, string $collection = 'clinical_conditions'): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);

    return $entry;
}

/** A resolved referencing entry mock exposing only its id. */
function inverseRelatedEntry(string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * The ids of a resolved inverse set, in order.
 *
 * @return array<int|string, mixed>
 */
function inverseIds(EntryCollection $set): array
{
    return $set->map(function (mixed $entry): mixed {
        /** @var Entry $entry */
        return $entry->id();
    })->all();
}

it('resolves published providers (people) whose conditions field names a Condition entry', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'people')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('special_interest_clinical_conditions', 'cond-1')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([inverseRelatedEntry('person-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = inverseCtEntry('cond-1', 'clinical_conditions');

    expect(inverseIds(app(SpecialInterestInverseComputedValues::class)->resolveSpecialInterestProviders($entry)))
        ->toBe(['person-a']);
});

it('resolves published locations (clinical_locations) whose services field names a Service entry', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'clinical_locations')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('special_interest_clinical_services', 'svc-1')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([inverseRelatedEntry('loc-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = inverseCtEntry('svc-1', 'clinical_services');

    expect(inverseIds(app(SpecialInterestInverseComputedValues::class)->resolveSpecialInterestLocations($entry)))
        ->toBe(['loc-a']);
});

it('chooses the queried field by the C/T entry collection, not the source collection', function () {
    // A Service entry resolving its PROVIDER inverse still matches the services field —
    // proving the field is keyed on the C/T entry's collection, independent of the
    // people/clinical_locations source being queried.
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'people')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->once()->with('special_interest_clinical_services', 'svc-2')->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([inverseRelatedEntry('person-b')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = inverseCtEntry('svc-2', 'clinical_services');

    expect(inverseIds(app(SpecialInterestInverseComputedValues::class)->resolveSpecialInterestProviders($entry)))
        ->toBe(['person-b']);
});

it('memoizes the inverse set per entry per request (one query for repeated reads)', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')->andReturnSelf();
    $query->shouldReceive('whereJsonContains')->andReturnSelf();
    $query->shouldReceive('whereStatus')->andReturnSelf();
    // A single get() across two reads asserts the Blink memoization.
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([inverseRelatedEntry('person-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = inverseCtEntry('cond-memo', 'clinical_conditions');
    $service = app(SpecialInterestInverseComputedValues::class);

    expect(inverseIds($service->resolveSpecialInterestProviders($entry)))->toBe(['person-a'])
        ->and(inverseIds($service->resolveSpecialInterestProviders($entry)))->toBe(['person-a']);
});

it('returns an empty inverse (and issues no query) for an unsaved C/T entry with no id', function () {
    // A not-yet-saved Condition/Service has no id to be referenced by.
    EntryFacade::shouldReceive('query')->never();

    $entry = inverseCtEntry(null);
    $service = app(SpecialInterestInverseComputedValues::class);

    expect(inverseIds($service->resolveSpecialInterestProviders($entry)))->toBe([])
        ->and(inverseIds($service->resolveSpecialInterestLocations($entry)))->toBe([]);
});

it('returns an empty inverse (and issues no query) for an entry in an unmapped collection', function () {
    // Defensive: only clinical_conditions/clinical_services map to a typed field. An
    // entry in any other collection has no field to match, so no query is issued.
    EntryFacade::shouldReceive('query')->never();

    $entry = inverseCtEntry('exp-1', 'clinical_expertise');
    $service = app(SpecialInterestInverseComputedValues::class);

    expect(inverseIds($service->resolveSpecialInterestProviders($entry)))->toBe([]);
});
