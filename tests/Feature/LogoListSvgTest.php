<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#72: SVG logo assets render via a single <img> (no
 * <picture>/srcset/Glide), while raster assets keep the responsive <picture>
 * path.
 *
 * @command  php artisan test --compact tests/Feature/LogoListSvgTest.php
 */
it('branches the logo item on an SVG extension', function (): void {
    $partial = File::get(resource_path('views/page_builder/_uamsweb_block_logo_list_item.antlers.html'));

    expect($partial)
        // Detect SVG by extension and render a plain, server-unresized <img>.
        ->toContain("logo_list_item:logo_image:extension == 'svg'")
        ->toContain('<img class="item-img-svg" src="{{ logo_list_item:logo_image:url }}"')
        // The raster path (responsive <picture> + Glide) is preserved for non-SVG.
        ->toContain('<picture>')
        ->toContain('glide :src="logo_list_item:logo_image:url"');
});

it('renders the SVG straight from its url with no Glide rendition', function (): void {
    $partial = File::get(resource_path('views/page_builder/_uamsweb_block_logo_list_item.antlers.html'));

    // The SVG <img> points at the asset url directly (no glide()), keeps the alt,
    // and takes its width from the layout.
    expect($partial)->toContain(
        '<img class="item-img-svg" src="{{ logo_list_item:logo_image:url }}" alt="{{ logo_list_item:logo_title }} logo" width="{{ $logo_list_image_width_lg }}" />'
    );
});

it('parses without an unclosed control structure', function (): void {
    $partial = File::get(resource_path('views/page_builder/_uamsweb_block_logo_list_item.antlers.html'));

    // A syntax error (e.g. an unbalanced if) would throw during parse.
    //
    // Parsed TRUSTED, as production does. `Antlers::parse()` defaults to `$trusted = false`,
    // which renders inside Antlers' user-content sandbox — a different regime from the one
    // `Engine::get()` uses via `RuntimeParser::parseView()`, so a syntax check run untrusted
    // is not checking the syntax production will actually parse (UAMS-Web/uams-statamic-archive#2040).
    expect(throwableFrom(fn (): string => (string) Antlers::parse($partial, [], true)))->toBeNull();
});
