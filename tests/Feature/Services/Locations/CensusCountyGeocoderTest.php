<?php

declare(strict_types=1);

use App\Services\Locations\CensusCountyGeocoder;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage for the Census county reverse-geocoder backing coordinate-derived
 * location regions (App\Services\Locations\CensusCountyGeocoder, UAMS-Web/uams-statamic-archive#962).
 *
 * Proves it: parses the county GEOID + name from a Census geographies response;
 * sends an identifying User-Agent with x=lng / y=lat; caches a resolved county
 * (so a repeated coordinate is not re-fetched) but does NOT cache a transient
 * failure (so a later save retries); routes through the SSRF egress guard; and
 * is a no-op when disabled. The external service and DNS are stubbed so the test
 * stays offline (Http::fake + OutboundUrlGuard::setResolver).
 *
 * @command  php artisan test --compact tests/Feature/Services/Locations/CensusCountyGeocoderTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    config()->set('maps.census.enabled', true);
    config()->set('maps.census.endpoint', 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates');
    config()->set('maps.census.user_agent', 'UAMS Census Test Agent');
    Cache::flush();
    OutboundUrlGuard::setResolver(fn (string $host): array => ['8.8.8.8']);
});

afterEach(function (): void {
    OutboundUrlGuard::setResolver(null);
    Cache::flush();
});

/**
 * Fake a Census geographies response returning a single county.
 */
function fakeCensusCounty(string $geoId, string $name): void
{
    Http::fake([
        'geocoding.geo.census.gov/*' => Http::response([
            'result' => [
                'geographies' => [
                    'Counties' => [
                        ['GEOID' => $geoId, 'NAME' => $name, 'STATE' => substr($geoId, 0, 2), 'COUNTY' => substr($geoId, 2)],
                    ],
                ],
            ],
        ], 200),
    ]);
}

it('reverse-geocodes a coordinate to its county with an identifying user-agent', function (): void {
    fakeCensusCounty('05119', 'Pulaski County');

    $county = (new CensusCountyGeocoder)->resolve(34.7465, -92.2896);

    expect($county)->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);

    Http::assertSent(function (Request $request): bool {
        return str_starts_with($request->url(), 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates')
            // x is the longitude (negative), y is the latitude (34.x) — never swapped.
            && str_contains($request->url(), 'x=-92')
            && str_contains($request->url(), 'y=34')
            && str_contains($request->url(), 'benchmark=Public_AR_Current')
            && str_contains($request->url(), 'format=json')
            && $request->header('User-Agent') === ['UAMS Census Test Agent'];
    });
});

it('caches a resolved county so a repeated coordinate is not re-fetched', function (): void {
    fakeCensusCounty('05119', 'Pulaski County');
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);
    expect($geocoder->resolve(34.7465, -92.2896))->toBe(['geoId' => '05119', 'name' => 'Pulaski County']);

    Http::assertSentCount(1);
});

it('returns null when the coordinate resolves to no county', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/*' => Http::response(['result' => ['geographies' => ['Counties' => []]]], 200),
    ]);

    expect((new CensusCountyGeocoder)->resolve(0.0, 0.0))->toBeNull();
});

it('does not cache a transient failure, so a later save retries', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/*' => Http::response('upstream error', 500),
    ]);
    $geocoder = new CensusCountyGeocoder;

    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull();
    expect($geocoder->resolve(34.7465, -92.2896))->toBeNull();

    Http::assertSentCount(2);
});

it('refuses to fetch when the configured endpoint targets an internal host (SSRF guard)', function (): void {
    config()->set('maps.census.endpoint', 'http://169.254.169.254/geocoder');
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull();
    Http::assertNothingSent();
});

it('is a no-op returning null when coordinate-derived regions are disabled', function (): void {
    config()->set('maps.census.enabled', false);
    Http::fake();

    expect((new CensusCountyGeocoder)->resolve(34.7465, -92.2896))->toBeNull();
    Http::assertNothingSent();
});
