<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for resources/views/sets/_audio.antlers.html (Bard audio set): a Box.com
 * HTTPS direct MP3 yields native HTML5 audio with the Box MIME type and a download
 * fallback; figure_class is applied to the figure; and a local Statamic-style asset
 * renders primary + alternate sources with player attributes and fallback. Antlers
 * template partial — no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/AudioSetPartialTest.php
 */
it('renders native audio for a Box MP3 direct URL', function () {
    $out = (string) Antlers::parse('{{ partial:sets/audio }}', [
        'audio_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3',
        'align' => 'aligncenter',
    ], true);

    expect($out)->toContain('<audio');
    expect($out)->toContain('<source');
    expect($out)->toContain('type="audio/mp3"');
    expect($out)->toContain('controls');
    expect($out)->toContain('Download the audio file');
});

it('applies figure_class to the figure element', function () {
    $out = (string) Antlers::parse('{{ partial:sets/audio }}', [
        'audio_url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp3',
        'figure_class' => 'entry-box-audio',
    ], true);

    expect($out)->toContain('class="entry-box-audio"');
});

it('renders local asset sources with player attributes and a download fallback', function () {
    $out = (string) Antlers::parse('{{ partial:sets/audio }}', [
        'file' => 'https://example.test/assets/segment.mp3',
        'file_alternate' => 'https://example.test/assets/segment.ogg',
        'preload' => 'metadata',
        'loop' => true,
        'title' => 'Segment Title',
        'figure_class' => 'entry-segment-media entry-segment-audio',
        'media_player_class' => 'entry-segment-media-player',
        'align' => 'aligncenter',
    ], true);

    expect($out)->toContain('<audio');
    expect($out)->toContain('src="https://example.test/assets/segment.mp3"');
    expect($out)->toContain('type="audio/mpeg"');
    expect($out)->toContain('src="https://example.test/assets/segment.ogg"');
    expect($out)->toContain('type="audio/ogg"');
    expect($out)->toContain('preload="metadata"');
    expect($out)->toContain('loop');
    expect($out)->toContain('entry-segment-media-player-fallback');
    expect($out)->toContain('Download the audio file');
});
