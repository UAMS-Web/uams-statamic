<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\LocationsInheritedAddressComputedsRegistrar;
use App\Services\Facilities\FacilityInheritedAddressService;
use App\Services\Locations\LocationInheritedAddressService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(LocationsInheritedAddressComputedsRegistrar::class);

/**
 * Pad Pest's mutant-child timeout when this file is the ``--mutate`` suite.
 *
 * Timeout is ``initialDuration + max(5, 0.2 * initial)``. TestCase boot alone is
 * ~9s; without padding, covering re-runs tip over and score as timeout kills
 * (false 100% with ``0 tested``).
 */
beforeEach(function (): void {
    if (runningUnderMutate()) {
        usleep(12_000_000);
    }
});

/**
 * @covers \App\Services\ComputedFields\Registration\LocationsInheritedAddressComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Registration/LocationsInheritedAddressComputedsRegistrarMutationTest.php
 */
it('registers the eleven effective address computeds on clinical_locations', function (): void {
    $registrar = new LocationsInheritedAddressComputedsRegistrar(
        new LocationInheritedAddressService(new FacilityInheritedAddressService)
    );

    $captured = [];
    Collection::shouldReceive('computed')
        ->times(11)
        ->withArgs(function ($collection, $key, $callback) use (&$captured): bool {
            $captured[is_string($key) ? $key : ''] = $callback;

            return $collection === 'clinical_locations' && \is_callable($callback);
        });

    $registrar->register();

    expect(array_keys($captured))->toBe([
        'resolved_street_address',
        'resolved_city',
        'resolved_state',
        'resolved_postal_code',
        'resolved_address_line_2',
        'resolved_facility',
        'resolved_facility_name',
        'resolved_facility_level',
        'resolved_suite',
        'resolved_map',
        'resolved_regions',
    ]);
});
