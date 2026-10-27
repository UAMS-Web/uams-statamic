<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the video-caption styling (UAMS-Web/uams-statamic-archive#82) and the RSS Feed block styling
 * (UAMS-Web/uams-statamic-archive#290): the templates carry the expected classes and the SCSS modules that
 * style them exist and are wired in. Compilation is verified by `npm run build`.
 *
 * @command  php artisan test --compact tests/Feature/MediaSetStylesTest.php
 */
it('gives the video Bard set caption a dedicated, styled class', function (): void {
    $set = File::get(resource_path('views/sets/_video.antlers.html'));
    expect($set)->toContain('class="embed-video-caption"');

    $scss = File::get(resource_path('scss/bootstrap/uams_2020/uams-supporting/embed-video.scss'));
    expect($scss)->toContain('.embed-video-caption');
});

it('adds an RSS Feed SCSS module and imports it', function (): void {
    $module = resource_path('scss/bootstrap/uams_2020/uams-supporting/uams-modules/rss-feed.scss');
    expect(File::exists($module))->toBeTrue();

    $scss = File::get($module);
    expect($scss)
        // Styles the block's semantic classes.
        ->toContain('.rss-feed')
        ->toContain('.feed-list')
        ->toContain('.feed-item')
        ->toContain('.item-title')
        ->toContain('.item-excerpt');

    // Imported into the module aggregator (not ad-hoc CSS).
    $aggregator = File::get(resource_path('scss/bootstrap/uams_2020/uams-supporting/uams-modules.scss'));
    expect($aggregator)->toContain('@import "uams-modules/rss-feed"');
});

/**
 * UAMS-Web/uams-statamic-archive#1341 — the ~53 KB WordPress `block-library` stylesheet (loaded sitewide) was
 * ~90% dead. Its live subset — the `wp-block-file` rules the File set renders and the
 * `wp-block-embed` spacing the Audio/Video sets render — was extracted verbatim into a
 * first-party remnant (`wp-blocks.scss`), loaded in the same cascade slot the original
 * occupied (before the `uams_2020` theme bundle) so `buttons.scss`'s outline-button
 * restyle + focus ring still win. The dead file + its references were dropped. These
 * pin the extraction and the load-order invariant; SCSS compilation is verified by
 * `npm run build`.
 */
it('extracts the live wp-block-file rules to the first-party remnant and drops the dead block-library file', function (): void {
    // The File set still renders the classes the extracted CSS targets.
    $set = File::get(resource_path('views/sets/_file.antlers.html'));
    expect($set)->toContain('wp-block-file')
        ->toContain('wp-block-file__button');

    // All FIVE live wp-block-file rules are extracted verbatim, each pinned by a
    // distinctive selector/declaration so dropping any one (a visual-parity regression —
    // the very thing UAMS-Web/uams-statamic-archive#1341 guards) turns the suite red.
    $scss = File::get(resource_path('scss/bootstrap/uams_2020/wp-blocks.scss'));
    expect($scss)
        // 1. base spacing
        ->toContain('.wp-block-file {')
        ->toContain('margin-bottom: 1.5em')
        // 2. the download button (background + radius + padding)
        ->toContain('.wp-block-file .wp-block-file__button')
        ->toContain('background: #32373c')
        ->toContain('border-radius: 2em')
        ->toContain('padding: 0.5em 1em')
        // 3. the anchor text-decoration (selector with a space-brace isolates it from the states)
        ->toContain('.wp-block-file a.wp-block-file__button {')
        ->toContain('text-decoration: none')
        // 4. the interaction-state block (hover/focus/active/visited)
        ->toContain('.wp-block-file a.wp-block-file__button:hover')
        ->toContain('opacity: 0.85')
        ->toContain('box-shadow: none')
        // 5. the sibling-spacing rule
        ->toContain('.wp-block-file * + .wp-block-file__button')
        ->toContain('margin-left: 0.75em');

    // The vendored block-library stylesheet is gone; both consumers now load the remnant
    // instead (absence of the old ref AND presence of the new one).
    expect(File::exists(resource_path('css/bootstrap/uams_2020/block-library/style.min.css')))->toBeFalse();
    expect(File::get(base_path('vite.config.js')))->not->toContain('block-library')->toContain('uams_2020/wp-blocks.scss');
    expect(File::get(resource_path('views/layout.antlers.html')))->not->toContain('block-library')->toContain('uams_2020/wp-blocks.scss');
    expect(File::get(resource_path('views/pdf/news_release.antlers.html')))->not->toContain('block-library')->toContain('uams_2020/wp-blocks.scss');
});

it('keeps the wp-block-embed spacing for the Audio and Video sets in the remnant', function (): void {
    // Both embed-based sets still render the wp-block-embed container class.
    expect(File::get(resource_path('views/sets/_audio.antlers.html')))->toContain('wp-block-embed');
    expect(File::get(resource_path('views/sets/_video.antlers.html')))->toContain('wp-block-embed');

    // The one live embed rule (base spacing) is in the remnant.
    expect(File::get(resource_path('scss/bootstrap/uams_2020/wp-blocks.scss')))
        ->toContain('.wp-block-embed {')
        ->toContain('margin-bottom: 1em');
});

it('loads the remnant before the uams_2020 theme bundle so the button restyle still wins', function (): void {
    // Cascade parity: the remnant must load ahead of `app.scss` (which pulls in
    // `buttons.scss`, whose outline-button restyle + focus ring override the remnant's
    // download-button rules) — exactly the slot the original block-library CSS held.
    // Both entry documents that loaded block-library must preserve that order.
    foreach (['views/layout.antlers.html', 'views/pdf/news_release.antlers.html'] as $template) {
        $src = File::get(resource_path($template));
        $remnantPos = strpos($src, 'uams_2020/wp-blocks.scss');
        /** @var int<0, max> $themePos */
        $themePos = strpos($src, 'uams_2020/app.scss');

        expect($remnantPos)->not->toBeFalse("$template must load the remnant")
            ->and($themePos)->not->toBeFalse("$template must load app.scss")
            ->and($remnantPos)->toBeLessThan($themePos, "$template must load the remnant before app.scss");
    }
});
