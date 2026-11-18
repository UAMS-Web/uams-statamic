<?php

declare(strict_types=1);

use App\Http\Controllers\CP\Gps\NominatimSearchController;
use App\Providers\MapsServiceProvider;
use App\Services\Locations\CountyRegionMap;
use Tests\TestCase;

uses(TestCase::class);

covers(MapsServiceProvider::class);

/**
 * `MapsServiceProvider` memoizes the county-to-region map for the process and adds
 * the Control Panel route behind the GPS picker's address search
 * (UAMS-Web/uams-statamic#871, UAMS-Web/uams-statamic-archive#962).
 *
 * @command  php artisan test --compact tests/Feature/Providers/MapsServiceProviderTest.php
 */
it('shares one county-to-region map across every resolution', function (): void {
    $first = $this->app->make(CountyRegionMap::class);

    expect($this->app->isShared(CountyRegionMap::class))->toBeTrue()
        ->and($this->app->make(CountyRegionMap::class))->toBe($first);
});

it('adds the Control Panel route behind the GPS address search', function (): void {
    $route = app('router')->getRoutes()->getByName('statamic.cp.gps.nominatim-search')
        ?? throw new RuntimeException('The GPS address-search route is not registered');

    expect($route->uri())->toBe(config()->string('statamic.cp.route').'/gps/nominatim-search')
        ->and($route->methods())->toBe(['GET', 'HEAD'])
        ->and($route->getActionName())->toBe(NominatimSearchController::class);
});
