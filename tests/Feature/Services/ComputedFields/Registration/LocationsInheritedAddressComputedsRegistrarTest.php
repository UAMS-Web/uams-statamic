<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\LocationsInheritedAddressComputedsRegistrar;
use App\Services\Facilities\FacilityInheritedAddressService;
use App\Services\Locations\LocationInheritedAddressService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\ComputedFields\Registration\LocationsInheritedAddressComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/LocationsInheritedAddressComputedsRegistrarTest.php
 */

/**
 * A standalone location whose stored address carries both components.
 */
function registrarLocationEntry(): Entry
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('location');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('loc1');
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('parent')->andReturn(null);
    $entry->shouldReceive('get')->with('parent_address_override_query')->andReturn(null);
    $entry->shouldReceive('get')->with('facility_query')->andReturn(null);
    $entry->shouldReceive('get')->with('facility')->andReturn(null);
    $entry->shouldReceive('get')->with('facility_level')->andReturn(null);
    $entry->shouldReceive('get')->with('suite')->andReturn(null);
    $entry->shouldReceive('get')->with('map')->andReturn(['lat' => 34.75, 'lng' => -92.32]);
    $entry->shouldReceive('get')->with('regions')->andReturn(['central-arkansas']);
    $entry->shouldReceive('get')->with('address')->andReturn([
        'street_address' => '99 Elm St.',
        'city' => 'Little Rock',
        'state_province_region_american' => 'AR',
        'postal_code' => '72205',
        'preset' => 'american',
    ]);

    return $entry;
}

it('registers every effective address computed on the clinical_locations collection', function () {
    // The services are final, so use real instances and assert delegation through behavior.
    $registrar = new LocationsInheritedAddressComputedsRegistrar(
        new LocationInheritedAddressService(new FacilityInheritedAddressService)
    );

    $captured = [];
    Collection::shouldReceive('computed')
        ->times(11)
        ->withArgs(function ($collection, $key, $callback) use (&$captured): bool {
            $captured[is_string($key) ? $key : ''] = $callback;

            // The handles deliberately match the ones on `facilities`, so a template reading
            // them need not know which collection it is looking at.
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

    $entry = registrarLocationEntry();

    // The stored argument must be IGNORED by both — the service's resolution wins, so a
    // non-null stored value cannot leak through (kills a `return $_stored` closure).
    /** @var callable $street */
    $street = $captured['resolved_street_address'];
    /** @var callable $city */
    $city = $captured['resolved_city'];
    /** @var callable $state */
    $state = $captured['resolved_state'];
    /** @var callable $postal */
    $postal = $captured['resolved_postal_code'];

    // Each must reach a DIFFERENT resolver. A copy-paste registration wires two handles to
    // one component and no per-handle assertion can detect it; four distinct values can.
    expect($street($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe('99 Elm St.')
        ->and($city($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe('Little Rock')
        ->and($state($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe('AR')
        ->and($postal($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe('72205');

    /** @var callable $facility */
    $facility = $captured['resolved_facility'];
    /** @var callable $facilityName */
    $facilityName = $captured['resolved_facility_name'];
    /** @var callable $level */
    $level = $captured['resolved_facility_level'];
    /** @var callable $suite */
    $suite = $captured['resolved_suite'];
    /** @var callable $map */
    $map = $captured['resolved_map'];
    /** @var callable $regions */
    $regions = $captured['resolved_regions'];

    // A standalone location is contained in nothing and has no unit, so those four resolve
    // to null whatever is stored; the map and the regions are its own (UAMS-Web/uams-statamic-archive#2608).
    expect($facility($entry, 'STORED-SHOULD-BE-IGNORED'))->toBeNull()
        ->and($facilityName($entry, 'STORED-SHOULD-BE-IGNORED'))->toBeNull()
        ->and($level($entry, 'STORED-SHOULD-BE-IGNORED'))->toBeNull()
        ->and($suite($entry, 'STORED-SHOULD-BE-IGNORED'))->toBeNull()
        ->and($map($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe(['lat' => 34.75, 'lng' => -92.32])
        ->and($regions($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe(['central-arkansas']);
});
