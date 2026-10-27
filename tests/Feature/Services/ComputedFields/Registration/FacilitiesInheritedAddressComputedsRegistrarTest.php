<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\FacilitiesInheritedAddressComputedsRegistrar;
use App\Services\Facilities\FacilityInheritedAddressService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(FacilitiesInheritedAddressComputedsRegistrar::class);

/**
 * @covers \App\Services\ComputedFields\Registration\FacilitiesInheritedAddressComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/FacilitiesInheritedAddressComputedsRegistrarTest.php
 */
it('registers the resolved_street_address computed on the facilities collection', function () {
    // The service is final, so use a real instance and assert delegation through behavior.
    $registrar = new FacilitiesInheritedAddressComputedsRegistrar(new FacilityInheritedAddressService);

    $captured = null;
    Collection::shouldReceive('computed')
        ->once()
        ->withArgs(function ($collection, $key, $callback) use (&$captured): bool {
            $captured = $callback;

            return $collection === 'facilities'
                && $key === 'resolved_street_address'
                && \is_callable($callback);
        });

    $registrar->register();

    // The registered callback must delegate to the service: a building entry with its
    // own address resolves to that address through the captured closure.
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('building');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('streetaddress')->andReturn('99 Elm St.');

    expect($captured)->toBeCallable();

    // The second (stored) argument must be IGNORED — the service's own resolution wins,
    // so a non-null stored value cannot leak through (kills a `return $_stored` closure).
    /** @var callable $captured */
    expect(($captured)($entry, 'STORED-SHOULD-BE-IGNORED'))->toBe('99 Elm St.');
});
