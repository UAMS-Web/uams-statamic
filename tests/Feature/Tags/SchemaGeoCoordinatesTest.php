<?php

declare(strict_types=1);

use App\Tags\SchemaGeoCoordinates;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `schema_geo_coordinates` Antlers tag.
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaGeoCoordinatesTest.php
 */
it('exposes schema_geo_coordinates as the tag handle', function (): void {
    expect(SchemaGeoCoordinates::handle())->toBe('schema_geo_coordinates');
});

it('emits a GeoCoordinates fragment from explicit latitude and longitude', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates latitude="34.7492719" longitude="-92.3198281" }}
ANTLERS;

    $out = trim((string) Antlers::parse($template, [], true));

    expect(json_decode($out, true))->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 34.7492719,
        'longitude' => -92.3198281,
    ]);
});

it('emits latitude and longitude as JSON numbers, not strings', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates latitude="34.7492719" longitude="-92.3198281" }}
ANTLERS;

    $out = trim((string) Antlers::parse($template, [], true));

    // The serialized values must be bare numbers (no surrounding quotes).
    expect($out)->toContain('"latitude":34.7492719');
    expect($out)->toContain('"longitude":-92.3198281');
});

it('emits a GeoCoordinates fragment from a bound Gps coordinate array', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates :coordinate="map" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        ['map' => ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16]],
        true
    ));

    expect(json_decode($out, true))->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 34.7492719,
        'longitude' => -92.3198281,
    ]);
});

it('ignores the Gps zoom member', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates :coordinate="map" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        ['map' => ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16]],
        true
    ));

    expect(json_decode($out, true))->not->toHaveKey('zoom');
});

it('reads the schema.org latitude and longitude keys from a coordinate array', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates :coordinate="map" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        ['map' => ['latitude' => 34.7492719, 'longitude' => -92.3198281]],
        true
    ));

    expect(json_decode($out, true))->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 34.7492719,
        'longitude' => -92.3198281,
    ]);
});

it('lets an explicit latitude parameter override the bound coordinate', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates :coordinate="map" latitude="40.5" }}
ANTLERS;

    $out = trim((string) Antlers::parse(
        $template,
        ['map' => ['lat' => 34.7492719, 'lng' => -92.3198281]],
        true
    ));

    expect(json_decode($out, true))->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 40.5,
        'longitude' => -92.3198281,
    ]);
});

it('includes elevation only when an explicit numeric value is passed', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates latitude="34.7492719" longitude="-92.3198281" elevation="130.63" }}
ANTLERS;

    $out = trim((string) Antlers::parse($template, [], true));

    expect(json_decode($out, true))->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 34.7492719,
        'longitude' => -92.3198281,
        'elevation' => 130.63,
    ]);
});

it('emits nothing when latitude is missing', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates longitude="-92.3198281" }}
ANTLERS;

    expect(trim((string) Antlers::parse($template, [], true)))->toBe('');
});

it('emits nothing when longitude is missing', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates latitude="34.7492719" }}
ANTLERS;

    expect(trim((string) Antlers::parse($template, [], true)))->toBe('');
});

it('emits nothing for a non-numeric coordinate component', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates latitude="north" longitude="-92.3198281" }}
ANTLERS;

    expect(trim((string) Antlers::parse($template, [], true)))->toBe('');
});

it('emits nothing when there is no coordinate input at all', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates }}
ANTLERS;

    expect(trim((string) Antlers::parse($template, [], true)))->toBe('');
});

it('emits nothing when the bound coordinate is an empty array', function (): void {
    $template = <<<'ANTLERS'
{{ schema_geo_coordinates :coordinate="map" }}
ANTLERS;

    expect(trim((string) Antlers::parse($template, ['map' => []], true)))->toBe('');
});
