<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Invoke the {{ static_map_url }} tag with the given literal parameter string.
 */
function renderStaticMapUrlTag(string $params): string
{
    return (string) Antlers::parse('{{ static_map_url '.$params.' }}', []);
}

it('substitutes the coordinate and zoom into the configured template', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}']);

    expect(renderStaticMapUrlTag('lat="34.7492719" lng="-92.3198281" zoom="16"'))
        ->toBe('https://static.example.test/?c=34.7492719,-92.3198281&z=16');
});

it('returns empty when static_url is unconfigured', function () {
    config(['maps.viewer.static_url' => '']);

    expect(renderStaticMapUrlTag('lat="34.7" lng="-92.3" zoom="16"'))->toBe('');
});

it('returns empty when static_url is whitespace only', function () {
    config(['maps.viewer.static_url' => '   ']);

    expect(renderStaticMapUrlTag('lat="34.7" lng="-92.3" zoom="16"'))->toBe('');
});

it('returns empty for a non-numeric latitude', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}']);

    expect(renderStaticMapUrlTag('lat="not-a-number" lng="-92.3" zoom="16"'))->toBe('');
});

it('returns empty for a non-numeric longitude', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}']);

    expect(renderStaticMapUrlTag('lat="34.7" lng="" zoom="16"'))->toBe('');
});

it('falls back to the configured default zoom when no zoom is given', function () {
    config([
        'maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}',
        'maps.viewer.default_zoom' => 13,
    ]);

    expect(renderStaticMapUrlTag('lat="34.7" lng="-92.3"'))
        ->toBe('https://static.example.test/?c=34.7,-92.3&z=13');
});

it('canonicalizes an exponent-form coordinate into a plain decimal', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}']);

    // is_numeric accepts exponent / stray-whitespace numeric strings; both coordinates must
    // carry a plain number, never "3.4e1" / "-9.23e1".
    expect(renderStaticMapUrlTag('lat="3.4e1" lng="-9.23e1" zoom="16"'))
        ->toBe('https://static.example.test/?c=34,-92.3&z=16');
});

it('coerces a fractional zoom to an integer', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}']);

    expect(renderStaticMapUrlTag('lat="34.7" lng="-92.3" zoom="15.8"'))
        ->toBe('https://static.example.test/?c=34.7,-92.3&z=15');
});

it('falls back to a safe street-level zoom when neither a zoom nor a numeric default exists', function () {
    config([
        'maps.viewer.static_url' => 'https://static.example.test/?c={lat},{lng}&z={zoom}',
        'maps.viewer.default_zoom' => null,
    ]);

    // A misconfigured (non-numeric) default must not collapse to zoom 0 (the whole world).
    expect(renderStaticMapUrlTag('lat="34.7" lng="-92.3"'))
        ->toBe('https://static.example.test/?c=34.7,-92.3&z=16');
});
