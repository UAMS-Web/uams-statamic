<?php

declare(strict_types=1);

use App\Services\Locations\CensusCountyGeocoder;
use App\Services\Locations\CountyRegionMap;
use App\Services\Locations\LocationRegionResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Unit coverage for the coordinate -> region orchestration
 * (App\Services\Locations\LocationRegionResolver, UAMS-Web/uams-statamic-archive#962). The geocoder and the
 * county -> region map are mocked so this stays offline and focuses on the
 * Arkansas-only gating and county-to-region resolution.
 *
 * @command  php artisan test --compact tests/Unit/Services/Locations/LocationRegionResolverTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(LocationRegionResolver::class);

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

it('resolves an Arkansas county to its region slug', function (): void {
    $geocoder = Mockery::mock(CensusCountyGeocoder::class);
    $geocoder->shouldReceive('resolve')->once()->with(34.7465, -92.2896)
        ->andReturn(['geoId' => '05119', 'name' => 'Pulaski County']);

    $map = Mockery::mock(CountyRegionMap::class);
    $map->shouldReceive('regionSlugForCountyName')->once()->with('Pulaski County')
        ->andReturn('central-arkansas');

    $resolver = new LocationRegionResolver($geocoder, $map);

    expect($resolver->regionsForCoordinate(34.7465, -92.2896))->toBe(['central-arkansas']);
});

it('ignores a same-named county outside Arkansas without consulting the region map', function (): void {
    // Shelby County, TN (GEOID 47157) would name-collide with an AR county if the
    // state gate were missing; the resolver must reject it on the GEOID prefix.
    $geocoder = Mockery::mock(CensusCountyGeocoder::class);
    $geocoder->shouldReceive('resolve')->andReturn(['geoId' => '47157', 'name' => 'Shelby County']);

    $map = Mockery::mock(CountyRegionMap::class);
    $map->shouldNotReceive('regionSlugForCountyName');

    $resolver = new LocationRegionResolver($geocoder, $map);

    expect($resolver->regionsForCoordinate(35.1, -90.0))->toBe([]);
});

it('returns no region when the coordinate resolves to no county', function (): void {
    $geocoder = Mockery::mock(CensusCountyGeocoder::class);
    $geocoder->shouldReceive('resolve')->andReturn(null);

    $map = Mockery::mock(CountyRegionMap::class);
    $map->shouldNotReceive('regionSlugForCountyName');

    expect((new LocationRegionResolver($geocoder, $map))->regionsForCoordinate(0.0, 0.0))->toBe([]);
});

it('returns no region for an Arkansas county that maps to no region', function (): void {
    $geocoder = Mockery::mock(CensusCountyGeocoder::class);
    $geocoder->shouldReceive('resolve')->andReturn(['geoId' => '05999', 'name' => 'Nowhere County']);

    $map = Mockery::mock(CountyRegionMap::class);
    $map->shouldReceive('regionSlugForCountyName')->with('Nowhere County')->andReturn(null);

    expect((new LocationRegionResolver($geocoder, $map))->regionsForCoordinate(34.0, -92.0))->toBe([]);
});
