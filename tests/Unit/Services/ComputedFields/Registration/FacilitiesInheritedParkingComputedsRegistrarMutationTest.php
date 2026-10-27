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

covers(FacilitiesInheritedParkingComputedsRegistrar::class);

/**
 * Narrow mutate coverage for FacilitiesInheritedParkingComputedsRegistrar.
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Registration/FacilitiesInheritedParkingComputedsRegistrarMutationTest.php
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

/**
 * Build a parking entry mock with an id, blueprint handle, and stored values.
 *
 * @param  array<string, mixed>  $gets  Stored values keyed by field handle
 */
function facParkMutEntry(
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

it('registers six parking computeds on both consumer collections', function (): void {
    $registrar = new FacilitiesInheritedParkingComputedsRegistrar(
        new FacilityInheritedParkingService
    );

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

    expect(count($captured))->toBe(12);
});

it('wires each parking computed through the real service', function (): void {
    $registrar = new FacilitiesInheritedParkingComputedsRegistrar(
        new FacilityInheritedParkingService
    );

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            if ($collection === 'facilities') {
                $captured[$handle] = $callback;
            }
        }
    );

    $registrar->register();

    $entity = facParkMutEntry('pe', 'parking_facility', [
        'title' => 'Deck A',
        'parking_instructions' => 'Deck A, level 2',
        'directions_from_parking' => 'Left at the lobby',
        'parking_free_query' => true,
        'parking_validated_query' => false,
    ]);
    $building = facParkMutEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => 'pe',
        'parking_instructions_inherit_query' => null,
        'directions_from_parking_inherit_query' => null,
    ]);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect(($captured['resolved_parking_facility'])($building, 'IGNORED'))->toBe('pe')
        ->and(($captured['resolved_parking_facility_name'])($building, 'IGNORED'))->toBe('Deck A')
        ->and(($captured['resolved_parking_instructions'])($building, 'IGNORED'))->toBe('Deck A, level 2')
        ->and(($captured['resolved_directions_from_parking'])($building, 'IGNORED'))->toBe('Left at the lobby')
        ->and(($captured['resolved_parking_free'])($building, 'IGNORED'))->toBeTrue()
        ->and(($captured['resolved_parking_validated'])($building, 'IGNORED'))->toBeFalse();

    $noParking = facParkMutEntry('np', 'building', ['parking_query' => false]);
    expect(($captured['resolved_parking_facility'])($noParking, null))->toBeNull();
    expect(($captured['resolved_parking_facility_name'])($noParking, null))->toBeNull();
});
