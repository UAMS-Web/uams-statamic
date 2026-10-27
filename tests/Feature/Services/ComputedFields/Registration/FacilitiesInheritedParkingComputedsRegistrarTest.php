<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\FacilitiesInheritedParkingComputedsRegistrar;
use App\Services\Facilities\FacilityInheritedParkingService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\ComputedFields\Registration\FacilitiesInheritedParkingComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/FacilitiesInheritedParkingComputedsRegistrarTest.php
 */
it('registers the six parking computeds on both consumer collections', function () {
    $registrar = new FacilitiesInheritedParkingComputedsRegistrar(new FacilityInheritedParkingService);

    // Capture every registration keyed by "collection|handle".
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            $captured["{$collection}|{$handle}"] = $callback;
        }
    );

    $registrar->register();

    $handles = [
        'resolved_parking_facility',
        'resolved_parking_facility_name',
        'resolved_parking_instructions',
        'resolved_directions_from_parking',
        'resolved_parking_free',
        'resolved_parking_validated',
    ];

    foreach (['facilities', 'clinical_locations'] as $collection) {
        foreach ($handles as $handle) {
            expect($captured)->toHaveKey("{$collection}|{$handle}");
        }
    }

    // Each callback must delegate to the service and IGNORE its stored second argument:
    // a building whose own parking entity resolves drives all five values.
    $entity = regParkingEntry('pe', 'parking_facility', [
        'title' => 'Deck A',
        'parking_instructions' => 'Deck A, level 2',
        'directions_from_parking' => 'Left at the lobby',
        'parking_free_query' => true,
        'parking_validated_query' => false,
    ]);
    $building = regParkingEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => 'pe',
        // A building has no override toggles; the instructions/directions resolvers
        // read them and fall through to the resolved entity's values.
        'parking_instructions_inherit_query' => null,
        'directions_from_parking_inherit_query' => null,
    ]);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    $callback = fn (string $handle): callable => $captured["facilities|{$handle}"];

    expect(($callback('resolved_parking_facility'))($building, 'IGNORED'))->toBe('pe')
        ->and(($callback('resolved_parking_facility_name'))($building, 'IGNORED'))->toBe('Deck A')
        ->and(($callback('resolved_parking_instructions'))($building, 'IGNORED'))->toBe('Deck A, level 2')
        ->and(($callback('resolved_directions_from_parking'))($building, 'IGNORED'))->toBe('Left at the lobby')
        ->and(($callback('resolved_parking_free'))($building, 'IGNORED'))->toBeTrue()
        ->and(($callback('resolved_parking_validated'))($building, 'IGNORED'))->toBeFalse();

    // The facility callback must null-safe the id() lookup: an entry that resolves to
    // no parking returns null, not a fatal id() call on null.
    $noParking = regParkingEntry('np', 'building', ['parking_query' => false]);
    expect(($callback('resolved_parking_facility'))($noParking, null))->toBeNull();
    expect(($callback('resolved_parking_facility_name'))($noParking, null))->toBeNull();
});

/**
 * Build a parking entry mock with an id, blueprint handle, and stored values.
 *
 * @param  array<string, mixed>  $gets  Stored values keyed by field handle
 */
function regParkingEntry(
    string $id,
    string $blueprintHandle,
    array $gets
): MockInterface {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn($blueprintHandle);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('parent')->andReturn(null);

    foreach ($gets as $handle => $value) {
        $entry->shouldReceive('get')->with($handle)->andReturn($value);
    }

    return $entry;
}
