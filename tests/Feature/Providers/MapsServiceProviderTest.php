<?php

declare(strict_types=1);

use App\Providers\MapsServiceProvider;
use App\Services\Locations\CountyRegionMap;
use App\Support\Http\OutboundUrlEgressGuard;
use Tests\TestCase;
use UAMSWeb\Aether\Fieldtypes\Contracts\EgressGuard;
use UAMSWeb\Aether\Fieldtypes\Http\Controllers\NominatimSearchController;

uses(TestCase::class);

covers(MapsServiceProvider::class);

/**
 * `MapsServiceProvider` memoizes the county-to-region map for the process and binds the
 * egress guard the shared fieldtype add-on's GPS address search fetches through
 * (UAMS-Web/uams-statamic#871, UAMS-Web/uams-statamic#1256, UAMS-Web/uams-statamic-archive#962).
 *
 * @command  php artisan test --compact tests/Feature/Providers/MapsServiceProviderTest.php
 */
it('shares one county-to-region map across every resolution', function (): void {
    $first = $this->app->make(CountyRegionMap::class);

    expect($this->app->isShared(CountyRegionMap::class))->toBeTrue()
        ->and($this->app->make(CountyRegionMap::class))->toBe($first);
});

it('binds the add-on egress guard to the site guard adapter', function (): void {
    expect($this->app->make(EgressGuard::class)::class)->toBe(OutboundUrlEgressGuard::class);
});

it('leaves the GPS address-search route to the add-on alone', function (): void {
    $route = app('router')->getRoutes()->getByName('statamic.cp.gps.nominatim-search')
        ?? throw new RuntimeException('The GPS address-search route is not registered');

    // Routes at one method and URI replace each other, so a site route at this path would
    // take over the add-on's; the action name says whose route answers.
    expect($route->uri())->toBe(config()->string('statamic.cp.route').'/gps/nominatim-search')
        ->and($route->methods())->toBe(['GET', 'HEAD'])
        ->and($route->getActionName())->toBe(NominatimSearchController::class);
});
