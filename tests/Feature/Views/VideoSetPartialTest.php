<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for resources/views/sets/_video.antlers.html (Bard video set): YouTube
 * yields a privacy-enhanced (youtube-nocookie) responsive iframe with the
 * host-specific allow list and security attributes; Vimeo yields its own allow
 * list and player host; figure_class is applied; a hidden caption renders sr-only;
 * and a Box MP4 yields native video instead of an iframe. Antlers template partial
 * — no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/VideoSetPartialTest.php
 */
it('renders a privacy-enhanced responsive YouTube iframe with security attributes', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ], true);

    expect($out)->toContain('<iframe');
    // YouTube renders the lite-video facade (UAMS-Web/uams-statamic-archive#224), whose wrapper is
    // `class="lite-video embed-responsive-item"`, so assert the responsive
    // class token rather than an exact `class="embed-responsive-item"` string.
    expect($out)->toContain('embed-responsive-item');
    expect($out)->toContain('embed-responsive');
    expect($out)->toContain('frameborder="0"');
    expect($out)->toContain('referrerpolicy="strict-origin-when-cross-origin"');
    expect($out)->toContain('allowfullscreen');
    expect($out)->toContain('accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    expect($out)->toContain('youtube-nocookie.com/embed/');
});

it('renders a Vimeo iframe with Vimeo-specific allow permissions', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://vimeo.com/676247342',
    ], true);

    expect($out)->toContain('<iframe');
    expect($out)->toContain('autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture; web-share');
    expect($out)->toContain('player.vimeo.com');
});

it('applies figure_class to the figure element', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'figure_class' => 'entry-segment-video',
    ], true);

    expect($out)->toContain('class="entry-segment-video"');
});

it('hides the caption visually with sr-only when caption_hide is true', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'caption' => 'Segment title for assistive tech',
        'caption_hide' => true,
    ], true);

    // typography/caption runs the text through widont (may add a non-breaking
    // space between the last two words), so check the phrase and last word apart.
    expect($out)->toContain('Segment title for assistive');
    expect($out)->toContain('tech');
    expect($out)->toContain('<figcaption');
    expect($out)->toContain('sr-only');
});

it('renders native video for a Box MP4 direct URL instead of an iframe', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
    ], true);

    expect($out)->toContain('<video');
    expect($out)->toContain('<source');
    expect($out)->toContain('type="video/mp4"');
    expect($out)->not->toContain('<iframe');
    expect($out)->toContain('Download the video file');
});

it('renders a captions track from an uploaded asset (same-origin, no crossorigin)', function () {
    // The partial reads only `caption_track:url`; existing templates (e.g.
    // {{ portrait:url }}) confirm a max_files:1 assets field augments to a single
    // object exposing `url`, so this array stand-in exercises the same render path.
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track' => ['url' => '/assets/files/captions/example-en.vtt'],
    ], true);

    expect($out)->toContain('<video');
    expect($out)->toContain('<track kind="captions"');
    expect($out)->toContain('srclang="en"');
    expect($out)->toContain('label="English"');
    expect($out)->toContain('src="/assets/files/captions/example-en.vtt"');
    // Same-origin asset: the player must NOT be marked crossorigin (that would
    // make the Box <source> CORS-checked and could break playback).
    expect($out)->not->toContain('crossorigin');
});

it('renders a captions track from an external URL and marks the player crossorigin', function () {
    // No uploaded asset, but an external (e.g. Box) caption URL is provided.
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_url' => 'https://app.box.com/shared/static/captions-en.vtt',
    ], true);

    expect($out)->toContain('<track kind="captions"');
    expect($out)->toContain('src="https://app.box.com/shared/static/captions-en.vtt"');
    // Cross-origin <track> only loads when the player is crossorigin.
    expect($out)->toContain('crossorigin="anonymous"');
});

it('prefers the uploaded asset over the external URL when both are set', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track' => ['url' => '/assets/files/captions/example-en.vtt'],
        'caption_track_url' => 'https://app.box.com/shared/static/captions-en.vtt',
    ], true);

    expect($out)->toContain('src="/assets/files/captions/example-en.vtt"');
    expect($out)->not->toContain('captions-en.vtt');
    // Asset path is same-origin, so no crossorigin.
    expect($out)->not->toContain('crossorigin');
});

it('omits the captions track on a Box video when neither caption source is set', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
    ], true);

    expect($out)->toContain('<video');
    expect($out)->not->toContain('<track');
    expect($out)->not->toContain('crossorigin');
});

it('ignores a whitespace-only caption URL — no track and no spurious crossorigin', function () {
    // A blank-looking URL is truthy in Antlers; trimming it keeps a whitespace-only
    // value from rendering a broken <track src="   "> and needlessly marking the
    // player crossorigin (which would CORS-check the Box <source>). (UAMS-Web/uams-statamic-archive#1048)
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_url' => '   ',
    ], true);

    expect($out)->toContain('<video');
    expect($out)->not->toContain('<track');
    expect($out)->not->toContain('crossorigin');
});

it('trims surrounding whitespace from a caption URL', function () {
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_url' => '  https://app.box.com/shared/static/captions-en.vtt  ',
    ], true);

    expect($out)->toContain('src="https://app.box.com/shared/static/captions-en.vtt"');
    expect($out)->toContain('crossorigin="anonymous"');
});

it('never renders a track for a third-party iframe even when a caption source is set', function () {
    // YouTube/Vimeo embeds are iframes; a caption <track> cannot be injected into
    // a third-party player, so the partial must not emit one (UAMS-Web/uams-statamic-archive#1048).
    $out = (string) Antlers::parse('{{ partial:sets/video }}', [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'caption_track' => ['url' => '/assets/files/captions/example-en.vtt'],
        'caption_track_url' => 'https://app.box.com/shared/static/captions-en.vtt',
    ], true);

    expect($out)->toContain('<iframe');
    expect($out)->not->toContain('<track');
});
