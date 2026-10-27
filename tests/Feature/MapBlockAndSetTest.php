<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the map Page Builder block (UAMS-Web/uams-statamic-archive#987) and Bard set (UAMS-Web/uams-statamic-archive#988): both are
 * thin editor entry points that feed a `gps` coordinate to the shared map viewer
 * partial (UAMS-Web/uams-statamic-archive#986) — no duplicated map markup.
 *
 * @command  php artisan test --compact tests/Feature/MapBlockAndSetTest.php
 */

/**
 * Render a set/block partial with a coordinate + the real viewer config.
 *
 * @param  array<string, mixed>|null  $coordinate
 */
function renderMapSet(string $partial, ?array $coordinate): string
{
    return (string) Antlers::parse("{{ partial:{$partial} }}", [
        'map' => $coordinate,
        'caption' => 'Main Campus',
        'height' => 400,
        'config' => ['maps' => ['viewer' => config('maps.viewer')]],
    ]);
}

it('renders the map Bard set through the shared viewer', function (): void {
    $html = renderMapSet('sets/map', ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16]);

    expect($html)
        ->toContain('data-map-viewer')
        ->toContain('data-lat="34.7492719"')
        ->toContain('data-lng="-92.3198281"')
        // Caption becomes the map's accessible name, and height is passed through.
        ->toContain('aria-label="Map of Main Campus"')
        ->toContain('height: 400px;');
});

it('renders nothing for an empty map Bard set', function (): void {
    expect(trim(renderMapSet('sets/map', null)))->toBe('');
});

it('renders the map Page Builder block through the shared viewer', function (): void {
    $html = renderMapSet('page_builder/uamsweb_block_map', ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16]);

    expect($html)
        ->toContain('data-map-viewer')
        ->toContain('data-lat="34.7492719"');
});

it('renders nothing for an empty map Page Builder block', function (): void {
    expect(trim(renderMapSet('page_builder/uamsweb_block_map', null)))->toBe('');
});

it('registers the map block and Bard set against the shared fieldset', function (): void {
    // Shared fields: a gps coordinate plus presentation options.
    /** @var array<string, mixed> $fieldset */
    $fieldset = Yaml::parseFile(resource_path('fieldsets/uamsweb_common_map.yaml'));
    /** @var list<array<string, mixed>> $fields */
    $fields = $fieldset['fields'] ?? [];
    $handles = collect($fields)->pluck('handle')->all();
    expect($handles)->toContain('map', 'caption', 'height', 'zoom');

    $mapField = collect($fields)->firstWhere('handle', 'map');
    /** @var array{field: array{type: string}} $mapField */
    expect($mapField['field']['type'])->toBe('gps');

    // Page Builder block, importing the shared fieldset.
    $pageBuilder = File::get(resource_path('fieldsets/page_builder.yaml'));
    expect($pageBuilder)
        ->toContain('uamsweb_block_map:')
        ->toContain('import: uamsweb_common_map');

    // Bard set on the main content Bard, importing the shared fieldset.
    $common = File::get(resource_path('fieldsets/uamsweb_common.yaml'));
    expect($common)->toMatch('/\n\s+map:\n\s+display: Map\n/');
});
