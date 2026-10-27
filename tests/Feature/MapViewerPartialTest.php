<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\View\Cascade;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render the `partials/map` partial with the given parameters and coordinate.
 *
 * Injects the real `maps.viewer` config the way the cascade would in a
 * front-end render, so the partial reads actual config defaults (or whatever a
 * test has overridden) rather than a hand-built fixture.
 *
 * @param  array<string, float|int>|null  $coordinate  The augmented `gps` value.
 */
function renderMapPartial(string $template, ?array $coordinate): string
{
    return (string) Antlers::parse($template, [
        'coordinate' => $coordinate,
        'config' => ['maps' => ['viewer' => config('maps.viewer')]],
    ]);
}

it('renders an interactive map for a populated coordinate', function () {
    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    // The interactive map container, carrying the coordinate and provider config
    // the front-end JS reads from data attributes.
    expect($html)
        ->toContain('data-map-viewer')
        ->toContain('data-lat="34.7492719"')
        ->toContain('data-lng="-92.3198281"')
        ->toContain('data-zoom="16"')
        ->toContain('data-provider="xyz"')
        ->toContain('data-tile-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"')
        ->toContain('data-max-zoom="19"');
});

it('names the figure after the label for assistive technology', function () {
    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    expect($html)->toContain('<figure class="map-viewer" aria-label="Map of UAMS Medical Center"');
});

it('falls back to a generic accessible name when no label is given', function () {
    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    // Named "Map" — no invented placeholder like "this location".
    expect($html)->toContain('aria-label="Map"');
    expect($html)->not->toContain('Map of');
});

it('does not bake in an external link', function () {
    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    expect($html)->not->toContain('<a ');
    expect($html)->not->toContain('openstreetmap.org/?mlat');
    expect($html)->not->toContain('google.com/maps');
});

it('frames the configured default zoom when the coordinate omits its own', function () {
    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281],
    );

    /** @var int $defaultZoom */
    $defaultZoom = config('maps.viewer.default_zoom');
    expect($html)->toContain('data-zoom="'.$defaultZoom.'"');
});

it('renders nothing for a null coordinate', function () {
    $html = renderMapPartial('{{ partial:map :coordinate="coordinate" }}', null);

    expect(trim($html))->toBe('');
});

it('renders nothing (no map, no leaked key) when no tile provider is configured', function () {
    config(['maps.viewer.tile_url' => '']);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    expect(trim($html))->toBe('');
});

it('exposes maps.viewer to front-end templates through the cascade allowlist', function () {
    /** @var array{maps?: array{viewer?: array{tile_url?: string}}} $config */
    $config = Cascade::config();

    expect($config['maps']['viewer']['tile_url'] ?? null)
        ->toBe(config('maps.viewer.tile_url'));
});

it('renders a no-JS static-map fallback when static_url is configured', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/map?center={lat},{lng}&zoom={zoom}&size=600x360']);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    // The fallback lives in <noscript>, is decorative (alt=""), and carries the
    // coordinate + framed zoom substituted into the configured template.
    expect($html)
        ->toContain('<noscript>')
        ->toContain('class="map-viewer__static"')
        ->toContain('alt=""')
        ->toContain('center=34.7492719,-92.3198281')
        ->toContain('zoom=16');
});

it('places the static fallback image inside <noscript> so JS users never fetch it', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/map?center={lat},{lng}&zoom={zoom}']);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    // The only <img> is the one wrapped by <noscript> — no eager image for JS users.
    expect($html)
        ->toMatch('/<noscript>\s*<img\b[^>]*class="map-viewer__static"/')
        ->and(substr_count($html, '<img'))->toBe(1);
});

it('keeps the figure aria-label as the single accessible name (decorative static image)', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/map?center={lat},{lng}&zoom={zoom}']);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    // The static image is alt="" so it is not announced; the <figure> names the map.
    expect($html)
        ->toContain('aria-label="Map of UAMS Medical Center"')
        ->toContain('alt=""');
});

it('frames the configured default zoom into the static URL when the coordinate omits its own', function () {
    config(['maps.viewer.static_url' => 'https://static.example.test/map?center={lat},{lng}&zoom={zoom}']);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281],
    );

    /** @var int $defaultZoom */
    $defaultZoom = config('maps.viewer.default_zoom');
    expect($html)->toContain('zoom='.$defaultZoom);
});

it('renders no static image, no broken markup, and no leaked key when static_url is unconfigured', function () {
    // The shipped default is empty — the fallback is off until a source is configured.
    expect(config('maps.viewer.static_url'))->toBe('');

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" label="UAMS Medical Center" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    expect($html)->not->toContain('<noscript>');
    expect($html)->not->toContain('map-viewer__static');
    expect($html)->not->toContain('<img');
});

it('escapes a hostile static_url template into the src (no attribute breakout)', function () {
    // The template is operator-controlled, but it is still sanitized into the attribute, so a
    // future refactor that dropped `| sanitize` from the partial would be caught here.
    config(['maps.viewer.static_url' => 'https://s.test/?c={lat},{lng}"><script>alert(1)</script>']);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('renders nothing at all when static_url is set but no tile provider is configured', function () {
    config([
        'maps.viewer.tile_url' => '',
        'maps.viewer.static_url' => 'https://static.example.test/map?center={lat},{lng}&zoom={zoom}',
    ]);

    $html = renderMapPartial(
        '{{ partial:map :coordinate="coordinate" }}',
        ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    );

    // The whole partial is gated on a tile provider, so no figure and no fallback.
    expect(trim($html))->toBe('');
});
