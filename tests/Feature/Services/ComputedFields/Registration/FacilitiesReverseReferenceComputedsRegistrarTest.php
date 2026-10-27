<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\FacilitiesReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Reverse\FacilitiesReverseReferenceComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(FacilitiesReverseReferenceComputedsRegistrar::class);

/**
 * The computed handles the facilities registrar owns, in registration order.
 *
 * @var list<string>
 */
const FACILITIES_REVERSE_HANDLES = [
    'areas_served_for_parking',
    'contained_locations',
];

/**
 * @covers \App\Services\ComputedFields\Registration\FacilitiesReverseReferenceComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/FacilitiesReverseReferenceComputedsRegistrarTest.php
 */
it('registers both reverse computeds exactly once on the facilities collection', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collection;
        }
    );

    app(FacilitiesReverseReferenceComputedsRegistrar::class)->register();

    expect($callCount)->toBe(count(FACILITIES_REVERSE_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(FACILITIES_REVERSE_HANDLES);

    foreach (FACILITIES_REVERSE_HANDLES as $handle) {
        expect($captured[$handle])->toBe('facilities');
    }
});

it('wires each computed handle to its owning resolver method', function (): void {
    // Distinct return instances catch a swapped binding (parking ↔ containment).
    $parking = EntryCollection::make();
    $contained = EntryCollection::make();

    $values = Mockery::mock(FacilitiesReverseReferenceComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $values->shouldReceive('resolveAreasServedForParking')->with($entry)->andReturn($parking);
    $values->shouldReceive('resolveContainedLocations')->with($entry)->andReturn($contained);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new FacilitiesReverseReferenceComputedsRegistrar($values))->register();

    expect(($captured['areas_served_for_parking'])($entry))->toBe($parking)
        ->and(($captured['contained_locations'])($entry))->toBe($contained);
});
