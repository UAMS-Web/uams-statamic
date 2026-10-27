<?php

declare(strict_types=1);

use App\Services\ComputedFields\Reverse\FacilitiesReverseReferenceComputedValues;
use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(FacilitiesReverseReferenceComputedValues::class);

/*
|--------------------------------------------------------------------------
| FacilitiesReverseReferenceComputedValues (UAMS-Web/uams-statamic-archive#1372)
|--------------------------------------------------------------------------
|
| The `facilities` reverse-reference computeds are thin wiring over the shared
| ReverseEntryReferenceResolver (whose real-Stache behavior — scalar/array/multi
| storage matching — is proved in ReverseEntryReferenceResolverTest). These mock
| the resolver to pin the exact (collections, field, id) each reverse resolves,
| which is what encodes the direct-only parking semantics (the raw
| `parking_facility` field, not an inheritance walk) and the containment reverse.
|
| @covers \App\Services\ComputedFields\Reverse\FacilitiesReverseReferenceComputedValues
|
| @command php artisan test --compact tests/Feature/Services/ComputedFields/Reverse/FacilitiesReverseReferenceComputedValuesTest.php
*/

function facReverseEntry(?string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

it('resolves areas served for parking from the direct parking_facility field across locations and facilities', function (): void {
    $expected = EntryCollection::make();
    $entry = facReverseEntry('fac-1');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with(['clinical_locations', 'facilities'], 'parking_facility', 'fac-1')
        ->andReturn($expected);

    expect((new FacilitiesReverseReferenceComputedValues($resolver))->resolveAreasServedForParking($entry))
        ->toBe($expected);
});

it('resolves contained locations from the clinical_locations containment field', function (): void {
    $expected = EntryCollection::make();
    $entry = facReverseEntry('fac-2');

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('clinical_locations', 'facility', 'fac-2')
        ->andReturn($expected);

    expect((new FacilitiesReverseReferenceComputedValues($resolver))->resolveContainedLocations($entry))
        ->toBe($expected);
});

it('passes the entry id straight through, including a null id for an unsaved facility', function (): void {
    // The resolver owns the id-less short-circuit; the service must forward whatever
    // id() returns without pre-filtering, so an unsaved facility still delegates.
    $parking = EntryCollection::make();
    $contained = EntryCollection::make();
    $entry = facReverseEntry(null);

    $resolver = Mockery::mock(ReverseEntryReferenceResolver::class);
    $resolver->shouldReceive('resolve')->once()->with(['clinical_locations', 'facilities'], 'parking_facility', null)->andReturn($parking);
    $resolver->shouldReceive('resolve')->once()->with('clinical_locations', 'facility', null)->andReturn($contained);

    $service = new FacilitiesReverseReferenceComputedValues($resolver);

    expect($service->resolveAreasServedForParking($entry))->toBe($parking)
        ->and($service->resolveContainedLocations($entry))->toBe($contained);
});
