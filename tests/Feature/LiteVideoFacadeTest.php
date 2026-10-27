<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#224: a YouTube video renders as a lite facade (thumbnail + an
 * accessible play trigger) with a <noscript> iframe fallback; Vimeo/Box are
 * unchanged. The facade behavior is covered by resources/js/lite-video.test.js.
 *
 * @command  php artisan test --compact tests/Feature/LiteVideoFacadeTest.php
 */
it('renders a YouTube video as an accessible lite facade', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:sets/video :video_url="video_url" title="Demo" }}',
        ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    );

    expect($html)
        ->toContain('lite-video embed-responsive-item')
        ->toContain('x-data="liteVideo"')
        ->toContain('data-video-url="https://www.youtube.com/watch?v=dQw4w9WgXcQ"')
        ->toContain('data-embed-url=')
        // Accessible, keyboard-operable play trigger (a real button).
        ->toContain('<button')
        ->toContain('class="lite-video__trigger"')
        ->toContain('@click="load()"')
        ->toContain('aria-label="Play video: Demo"')
        // ---
        // The player src is rendered HERE, not bound by Alpine (UAMS-Web/uams-statamic-archive#2245). The value is
        // asserted rather than its presence: `data-embed-url=` above is a presence
        // check, and a presence check is exactly what let UAMS-Web/uams-statamic-archive#2245 ship — the markup was
        // right and the element received nothing. Under `@alpinejs/csp` an expression
        // on an iframe is refused outright, so `:src` never reaches it.
        // ---
        ->toContain('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1"')
        ->not->toContain(':src="iframeSrc"')
        ->not->toContain('x-ref="player"')
        // The wrapper takes focus when the player itself cannot.
        ->toContain('tabindex="-1"')
        // Works without JS.
        ->toContain('<noscript>');
});

it('joins autoplay with an ampersand when the embed url already has a query', function (): void {
    // `embed_url` emits `?start=` for a timestamped url, so the autoplay join has two
    // branches. This is the one the fixture above does not reach.
    $html = (string) Antlers::parse(
        '{{ partial:sets/video :video_url="video_url" }}',
        ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30'],
    );

    expect($html)->toContain('start=30&autoplay=1')
        ->not->toContain('start=30?autoplay=1');
});

it('leaves a Vimeo video as a plain iframe embed', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:sets/video :video_url="video_url" }}',
        ['video_url' => 'https://vimeo.com/12345'],
    );

    expect($html)->not->toContain('lite-video');
    expect($html)->not->toContain('x-data="liteVideo"');
    expect($html)->toContain('<iframe');
});

it('registers the lite-video component before Alpine starts', function (): void {
    $siteJs = File::get(resource_path('js/site.js'));

    expect($siteJs)
        ->toContain("import { registerLiteVideo } from './lite-video.js'")
        ->toContain('registerLiteVideo(Alpine)');

    expect(strpos($siteJs, 'registerLiteVideo(Alpine)'))
        ->toBeLessThan((int) strrpos($siteJs, 'Alpine.start()'));
});
