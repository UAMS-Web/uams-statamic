<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2291. This file was gated to the serial tail on
// "the shared, paratest-non-isolated compiled-view cache" (the UAMS-Web/uams-statamic-archive#1300 / UAMS-Web/uams-statamic-archive#1310 / UAMS-Web/uams-statamic-archive#1447 /
// UAMS-Web/uams-statamic-archive#1452 render-flake class). That directory is per worker under Laravel's `TestViews`
// -- `storage/framework/views/test_<token>`, the one UAMS-Web/uams-statamic-archive#2442 patched for concurrent bands
// -- so the hazard the gate named does not exist. Three parity-matched parallel bands
// with this file in them produced zero failures (macOS, 2026-09-16); runs refute and
// never establish, and tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Escaping coverage for the shared page-builder block header (UAMS-Web/uams-statamic-archive#1675).
 *
 * `page_builder/components/_block_header` renders the `heading` every page-builder
 * block and every people lens passes it. Antlers is raw by default, so an
 * editor- or importer-controlled heading was emitted as live HTML — the people
 * lenses pass a person's name (`About {name}`), which the WordPress importer
 * writes verbatim from raw YAML that bypasses CP validation.
 *
 * @command  php artisan test --compact tests/Feature/Views/BlockHeaderEscapingTest.php
 */

/**
 * Render the block header partial with the given Antlers scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderBlockHeader(array $scope): string
{
    return (string) Antlers::parse('{{ partial:page_builder/components/block_header }}', $scope);
}

it('escapes markup in the default heading', function (): void {
    $html = renderBlockHeader(['heading' => 'About Dr. McCoy<svg/onload=alert(1)></svg>']);

    expect($html)->not->toContain('<svg/onload')
        ->and($html)->not->toContain('</svg>')
        ->and($html)->toContain('McCoy');
});

// `widont` replaces only the LAST space, so a payload containing no space passed
// through it completely intact. This is the case that made the sink exploitable.
it('escapes a space-free payload that widont leaves untouched', function (): void {
    $html = renderBlockHeader(['heading' => '<svg/onload=alert(1)>']);

    expect($html)->not->toContain('<svg')
        ->and($html)->not->toContain('onload=');
});

it('escapes the heading in the screen-reader-only branch', function (): void {
    // `block:hide_block_heading` routes the heading to an `sr-only` <h2>; that branch
    // is a sink too, and is the one a reader is least likely to notice.
    $html = renderBlockHeader([
        'heading' => 'About Dr. McCoy<img src=x onerror=alert(1)>',
        'block' => ['hide_block_heading' => true],
    ]);

    expect($html)->not->toContain('<img')
        ->and($html)->toContain('sr-only')
        ->and($html)->toContain('McCoy');
});

// Escaping must not damage legitimate headings — `strip_tags` was chosen over
// `sanitize` precisely so ampersands and apostrophes survive unencoded.
it('leaves legitimate heading punctuation untouched', function (): void {
    $html = renderBlockHeader(['heading' => "Ophthalmology & Otolaryngology's Team"]);

    expect($html)->toContain('&')
        ->and($html)->toContain("Otolaryngology's")
        ->and($html)->not->toContain('&amp;amp;');
});

// The description branch renders Bard output, whose markup is the point. It must
// stay unescaped, or every page-builder block's rich text turns into plain text.
it('does not escape the Bard description', function (): void {
    $html = renderBlockHeader([
        'heading' => 'A heading',
        'description' => '<p>A <strong>rich</strong> description.</p>',
    ]);

    expect($html)->toContain('<strong>rich</strong>');
});
