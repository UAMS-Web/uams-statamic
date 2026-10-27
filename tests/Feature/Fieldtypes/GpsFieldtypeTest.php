<?php

declare(strict_types=1);

use App\Fieldtypes\Gps;
use App\Rules\Latitude;
use App\Rules\Longitude;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Explicitly define the target class for this file
covers(Gps::class);

/**
 * Behavioral and mutation coverage for the Gps fieldtype process, preload, and
 * config maps.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/GpsFieldtypeTest.php
 */

/**
 * Build a configured Gps fieldtype bound to a field with the given handle.
 *
 * @param  string  $handle  Field handle used when constructing the Field
 * @param  array<string, mixed>  $config  Extra field config merged over the type
 */
function gpsFieldtype(string $handle = 'map', array $config = []): Gps
{
    // Build a blueprint Field selecting the gps fieldtype
    $field = new Field($handle, array_merge(['type' => 'gps'], $config));

    // Resolve the auto-discovered Gps fieldtype from the Field
    /** @var Gps $fieldtype */
    $fieldtype = $field->fieldtype();

    // Bind the Field onto the fieldtype instance
    $fieldtype->setField($field);

    return $fieldtype;
}

it('processes a full coordinate into structured floats with an integer zoom', function () {
    $out = gpsFieldtype()->process(['lat' => '34.7492719', 'lng' => '-92.3198281', 'zoom' => '16']);

    expect($out)->toHaveKeys(['lat', 'lng', 'zoom'])
        ->and(data_get($out, 'lat'))->toEqualWithDelta(34.7492719, 1e-9)
        ->and(data_get($out, 'lng'))->toEqualWithDelta(-92.3198281, 1e-9)
        ->and(data_get($out, 'zoom'))->toBe(16);
});

it('omits zoom when it is not provided', function () {
    $out = gpsFieldtype()->process(['lat' => 34.75, 'lng' => -92.32]);

    expect($out)->toHaveKeys(['lat', 'lng'])
        ->and($out)->not->toHaveKey('zoom');
});

it('rounds latitude and longitude to seven decimal places', function () {
    $out = gpsFieldtype()->process(['lat' => 34.749271912345, 'lng' => -92.319828154321]);

    expect(data_get($out, 'lat'))->toEqualWithDelta(34.7492719, 1e-9)
        ->and(data_get($out, 'lng'))->toEqualWithDelta(-92.3198282, 1e-9);
});

it('returns null when no marker was placed', function () {
    expect(gpsFieldtype()->process(null))->toBeNull()
        ->and(gpsFieldtype()->process([]))->toBeNull()
        ->and(gpsFieldtype()->process('not a coordinate'))->toBeNull();
});

it('returns null when latitude or longitude is missing or non-numeric', function () {
    expect(gpsFieldtype()->process(['lat' => 34.75]))->toBeNull()
        ->and(gpsFieldtype()->process(['lat' => 'abc', 'lng' => 'def']))->toBeNull();
});

it('preProcesses the legacy comma-separated string into a coordinate array', function () {
    $out = gpsFieldtype()->preProcess('34.7492719, -92.3198281');

    expect($out)->toHaveKeys(['lat', 'lng'])
        ->and(data_get($out, 'lat'))->toEqualWithDelta(34.7492719, 1e-9)
        ->and(data_get($out, 'lng'))->toEqualWithDelta(-92.3198281, 1e-9);
});

it('augments the stored value into a normalized coordinate', function () {
    $out = gpsFieldtype()->augment(['lat' => 34.75, 'lng' => -92.32, 'zoom' => 14]);

    expect($out)->toHaveKeys(['lat', 'lng', 'zoom'])
        ->and(data_get($out, 'zoom'))->toBe(14);
});

it('reuses the Latitude and Longitude rules keyed by the field handle', function () {
    $rules = gpsFieldtype('map')->extraRules();

    expect($rules)->toHaveKeys(['map.lat', 'map.lng'])
        ->and($rules['map.lat'])->toContain('nullable', 'required_with:map.lng')
        ->and($rules['map.lng'])->toContain('nullable', 'required_with:map.lat')
        ->and(collect($rules['map.lat'])->contains(fn ($rule) => $rule instanceof Latitude))->toBeTrue()
        ->and(collect($rules['map.lng'])->contains(fn ($rule) => $rule instanceof Longitude))->toBeTrue();
});

it('rejects out-of-range coordinates via the reused range rules', function () {
    expect((new Latitude)->isValid(200))->toBeFalse()
        ->and((new Latitude)->isValid(34.75))->toBeTrue()
        ->and((new Longitude)->isValid(-200))->toBeFalse()
        ->and((new Longitude)->isValid(-92.32))->toBeTrue();
});

it('surfaces a config-driven, policy-compliant tile source to the component', function () {
    $meta = gpsFieldtype()->preload();

    expect($meta)->toHaveKeys(['tileUrl', 'tileAttribution', 'tileMaxZoom'])
        ->and($meta['tileUrl'])->toBe('https://tile.openstreetmap.org/{z}/{x}/{y}.png')
        ->and($meta['tileUrl'])->not->toContain('{s}')
        ->and($meta['tileAttribution'])->toContain('OpenStreetMap')
        ->and($meta['tileMaxZoom'])->toBe(19);
});

it('surfaces an aerial layer (Esri) with attribution for the street/aerial toggle', function () {
    $meta = gpsFieldtype()->preload();

    expect($meta)->toHaveKeys(['aerialUrl', 'aerialAttribution', 'aerialMaxZoom', 'aerialMaxNativeZoom'])
        ->and($meta['aerialUrl'])->toContain('arcgisonline.com')
        ->and($meta['aerialAttribution'])->toContain('Esri')
        ->and($meta['aerialMaxZoom'])->toBe(19)
        ->and($meta['aerialMaxNativeZoom'])->toBe(19);
});

it('falls back to the configured system default position when the field sets none', function () {
    config()->set('maps.default', ['latitude' => 12.34, 'longitude' => -56.78, 'zoom' => 9]);

    $meta = gpsFieldtype()->preload();

    expect($meta['defaultLatitude'])->toBe(12.34)
        ->and($meta['defaultLongitude'])->toBe(-56.78)
        ->and($meta['defaultZoom'])->toBe(9);
});

it('lets a per-field default override the configured system default position', function () {
    config()->set('maps.default', ['latitude' => 12.34, 'longitude' => -56.78, 'zoom' => 9]);

    $meta = gpsFieldtype('map', [
        'default_latitude' => 40.0,
        'default_longitude' => -75.0,
        'default_zoom' => 16,
    ])->preload();

    expect($meta['defaultLatitude'])->toBe(40.0)
        ->and($meta['defaultLongitude'])->toBe(-75.0)
        ->and($meta['defaultZoom'])->toBe(16);
});

it('uses the map-search icon and gps Vue component', function (): void {
    $fieldtype = gpsFieldtype();

    expect($fieldtype->icon())->toBe('map-search')
        ->and($fieldtype->component())->toBe('gps');
});

it('is special, selectable in collections, and not selectable in forms', function (): void {
    $fieldtype = gpsFieldtype();

    expect($fieldtype->categories())->toBe(['special'])
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeFalse()
        ->and($fieldtype->keywords())->toBe([
            'coordinates',
            'geo',
            'gps',
            'latitude',
            'location',
            'longitude',
            'map',
        ]);
});

it('exposes the GPS config section exactly', function (): void {
    $method = new ReflectionMethod(Gps::class, 'configFieldItems');
    /** @var list<array<string, mixed>> $items */
    $items = $method->invoke(gpsFieldtype());

    expect($items)->toBe([
        [
            'display' => __('statamic::fieldtypes.gps.config.section_heading'),
            'fields' => [
                'default_latitude' => [
                    'display' => __('statamic::fieldtypes.gps.config.default_latitude_display'),
                    'type' => 'text',
                    'instructions' => __('statamic::fieldtypes.gps.config.default_latitude_instructions'),
                ],
                'default_longitude' => [
                    'display' => __('statamic::fieldtypes.gps.config.default_longitude_display'),
                    'type' => 'text',
                    'instructions' => __('statamic::fieldtypes.gps.config.default_longitude_instructions'),
                ],
                'default_zoom' => [
                    'display' => __('statamic::fieldtypes.gps.config.default_zoom_display'),
                    'type' => 'integer',
                    'instructions' => __('statamic::fieldtypes.gps.config.default_zoom_instructions'),
                ],
                'enable_address_search' => [
                    'display' => __('statamic::fieldtypes.gps.config.enable_address_search_display'),
                    'type' => 'toggle',
                    'instructions' => __('statamic::fieldtypes.gps.config.enable_address_search_instructions'),
                    'default' => false,
                ],
            ],
        ],
    ]);
});

it('pins the full preload meta shape from maps config', function (): void {
    // Seed maps config with fully typed tile, aerial, and nominatim values
    config()->set('maps', [
        'default' => ['latitude' => 12.34, 'longitude' => -56.78, 'zoom' => 9],
        'tiles' => [
            'url' => 'https://tile.example/{z}/{x}/{y}.png',
            'attribution' => 'Example Tiles',
            'max_zoom' => 18,
        ],
        'aerial' => [
            'url' => 'https://aerial.example/{z}/{x}/{y}',
            'attribution' => 'Example Aerial',
            'max_zoom' => 17,
            'max_native_zoom' => 16,
        ],
        'nominatim' => ['min_query_length' => 4],
    ]);

    // Assert preload mirrors every maps config key into the Vue meta shape
    expect(gpsFieldtype('map', ['enable_address_search' => true])->preload())->toBe([
        'defaultLatitude' => 12.34,
        'defaultLongitude' => -56.78,
        'defaultZoom' => 9,
        'tileUrl' => 'https://tile.example/{z}/{x}/{y}.png',
        'tileAttribution' => 'Example Tiles',
        'tileMaxZoom' => 18,
        'aerialUrl' => 'https://aerial.example/{z}/{x}/{y}',
        'aerialAttribution' => 'Example Aerial',
        'aerialMaxZoom' => 17,
        'aerialMaxNativeZoom' => 16,
        'addressSearchEnabled' => true,
        'addressSearchMinLength' => 4,
    ]);
});

it('falls back when preload config values are non-numeric or non-scalar', function (): void {
    // Seed maps config with non-numeric and non-scalar values
    config()->set('maps', [
        'default' => ['latitude' => 'x', 'longitude' => null, 'zoom' => []],
        'tiles' => [
            'url' => ['not' => 'scalar'],
            'attribution' => ['nope'],
            'max_zoom' => 'nope',
        ],
        'aerial' => [
            'url' => ['bad'],
            'attribution' => ['bad'],
            'max_zoom' => 'nope',
            'max_native_zoom' => 'nope',
        ],
        'nominatim' => ['min_query_length' => 'nope'],
    ]);

    // Assert preload falls back to empty strings and zero numeric defaults
    expect(gpsFieldtype()->preload())->toBe([
        'defaultLatitude' => 0.0,
        'defaultLongitude' => 0.0,
        'defaultZoom' => 0,
        'tileUrl' => '',
        'tileAttribution' => '',
        'tileMaxZoom' => 0,
        'aerialUrl' => '',
        'aerialAttribution' => '',
        'aerialMaxZoom' => 0,
        'aerialMaxNativeZoom' => 0,
        'addressSearchEnabled' => false,
        'addressSearchMinLength' => 3,
    ]);
});

it('casts preload config values given as other scalar types', function (): void {
    // Seed maps config with numeric strings and non-string scalars
    config()->set('maps', [
        'default' => ['latitude' => '12.34', 'longitude' => '-56.78', 'zoom' => '9'],
        'tiles' => [
            'url' => 101,
            'attribution' => 102,
            'max_zoom' => '18',
        ],
        'aerial' => [
            'url' => 103,
            'attribution' => 104,
            'max_zoom' => '17',
            'max_native_zoom' => '16',
        ],
        'nominatim' => ['min_query_length' => '4'],
    ]);

    // Assert every value reaches the component as the declared type
    expect(gpsFieldtype('map', ['enable_address_search' => 1])->preload())->toBe([
        'defaultLatitude' => 12.34,
        'defaultLongitude' => -56.78,
        'defaultZoom' => 9,
        'tileUrl' => '101',
        'tileAttribution' => '102',
        'tileMaxZoom' => 18,
        'aerialUrl' => '103',
        'aerialAttribution' => '104',
        'aerialMaxZoom' => 17,
        'aerialMaxNativeZoom' => 16,
        'addressSearchEnabled' => true,
        'addressSearchMinLength' => 4,
    ]);
});

it('keeps address search off when the field sets it to null', function (): void {
    // Assert a null field value falls back to disabled rather than to the blueprint default
    expect(gpsFieldtype('map', ['enable_address_search' => null])->preload()['addressSearchEnabled'])->toBeFalse();
});

it('clamps address-search minimum query length to at least one', function (): void {
    config()->set('maps.nominatim.min_query_length', 0);

    expect(gpsFieldtype()->preload()['addressSearchMinLength'])->toBe(1);
});

it('returns null for non-array non-string process input', function (): void {
    expect(gpsFieldtype()->process(123))->toBeNull()
        ->and(gpsFieldtype()->process(false))->toBeNull();
});

it('trims legacy coordinate strings before splitting', function (): void {
    $out = gpsFieldtype()->preProcess('  34.7492719 , -92.3198281  ');

    expect($out)->toHaveKeys(['lat', 'lng'])
        ->and(data_get($out, 'lat'))->toEqualWithDelta(34.7492719, 1e-9)
        ->and(data_get($out, 'lng'))->toEqualWithDelta(-92.3198281, 1e-9);
});
