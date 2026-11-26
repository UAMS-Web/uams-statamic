<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Font Awesome loads as CSS with web fonts inside the theme stylesheet (#1546,
 * deciding #342), not as the SVG with JavaScript build.
 *
 * That build inserted an un-nonced `<style>` element at runtime, which an enforced
 * `style-src` blocks, leaving every icon at 0x0. The CSS has to arrive inside a
 * stylesheet the page already loads: on #342 one more stylesheet request in
 * `<head>` shifted the header on a throttled load.
 *
 * @command php artisan test --compact tests/Feature/Views/FontAwesomeWebFontLoadingTest.php
 */
const FONT_AWESOME_SVG_ENTRY = 'resources/js/uams_2020/uams-2020-fontawesome-entry.js';

const FONT_AWESOME_THEME_PARTIAL = 'scss/bootstrap/uams_2020/uams-supporting/fontawesome.scss';

it('no longer adds the SVG with JavaScript entry to the page', function (string $layout): void {
    $template = File::get(resource_path($layout));

    expect($template)->not->toContain('uams-2020-fontawesome-entry')
        ->and($template)->not->toContain('fontawesome-free/js/')
        // The theme stylesheet that now carries the icons is still on the page
        ->and($template)->toContain("merge_array(['resources/scss/bootstrap/uams_2020/app.scss'])");
})->with([
    'site layout' => ['views/layout.antlers.html'],
    'news release pdf layout' => ['views/pdf/news_release.antlers.html'],
]);

it('builds no Font Awesome script entry', function (): void {
    expect(File::exists(base_path(FONT_AWESOME_SVG_ENTRY)))->toBeFalse()
        ->and(File::get(base_path('vite.config.js')))->not->toContain('fontawesome-entry');
});

it('inlines the Font Awesome CSS into the theme stylesheet', function (): void {
    $partial = File::get(resource_path(FONT_AWESOME_THEME_PARTIAL));

    // ---
    // Without an extension Sass inlines a CSS file. With `.css`, or as a `url()`, it
    // writes a plain CSS `@import`, which the browser fetches as one more request.
    // ---
    expect($partial)->toContain('@import "@fortawesome/fontawesome-free/css/all";')
        ->and($partial)->toContain('@import "@fortawesome/fontawesome-free/css/v4-shims";')
        ->and($partial)->not->toMatch('/@import[^;]*fontawesome-free[^;]*(\.css|url\()/');

    // The partial is part of the theme bundle the layouts load
    expect(File::get(resource_path('scss/bootstrap/uams_2020/style.scss')))
        ->toContain('@import "uams-supporting/fontawesome";')
        ->and(File::get(resource_path('scss/bootstrap/uams_2020/app.scss')))
        ->toContain("@import 'style';");
});

it('ships the web fonts the inlined CSS points at', function (string $font): void {
    $css = File::get(base_path('node_modules/@fortawesome/fontawesome-free/css/all.css'));

    expect($css)->toContain('url("../webfonts/'.$font.'")')
        ->and(File::exists(base_path('node_modules/@fortawesome/fontawesome-free/webfonts/'.$font)))->toBeTrue();
})->with([
    'solid' => ['fa-solid-900.woff2'],
    'regular' => ['fa-regular-400.woff2'],
    'brands' => ['fa-brands-400.woff2'],
    'font awesome 4 compatibility' => ['fa-v4compatibility.woff2'],
]);
