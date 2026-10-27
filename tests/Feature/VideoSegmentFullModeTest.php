<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#835: a full-mode Bard video set (youtube / vimeo / box) renders
 * transcript, creators, and VideoObject JSON-LD, honoring the author-entered
 * caption; simple embeds are unchanged.
 *
 * @command  php artisan test --compact tests/Feature/VideoSegmentFullModeTest.php
 */
it('renders transcript and valid VideoObject JSON-LD honoring the author caption', function (): void {
    $html = (string) Antlers::parse('{{ partial:sets/video_segment_full }}', [
        'title' => 'Fallback Title',
        'caption' => 'Author Caption',
        'excerpt' => 'A short summary.',
        'transcript' => 'The transcript text.',
        'url' => 'https://youtube.com/watch?v=abc',
        'creator' => null,
    ]);

    // Accessible transcript.
    expect($html)
        ->toContain('<details class="entry-segment-transcript">')
        ->toContain('The transcript text.');

    // The JSON-LD block parses and describes the video, with the author caption
    // as the name (not the title fallback).
    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);

    // The block must be present and carry valid JSON — an unmatched regex would
    // otherwise decode to null and read as a missing key rather than a missing block.
    expect(trim($m[1] ?? ''))->toBeJson();

    /** @var array<string, mixed> $schema */
    $schema = json_decode(trim($m[1] ?? ''), true);

    expect($schema['@type'])->toBe('VideoObject');
    expect($schema['name'])->toBe('Author Caption');
    expect($schema['embedUrl'])->toBe('https://youtube.com/watch?v=abc');
    expect($schema['transcript'])->toBe('The transcript text.');
});

it('gates the full-segment rendering on embed_mode in every platform wrapper', function (): void {
    foreach (['_youtube', '_vimeo', '_box'] as $wrapper) {
        $source = File::get(resource_path("views/sets/{$wrapper}.antlers.html"));

        expect($source)
            ->toContain("{{ if embed_mode == 'full' }}")
            ->toContain('partial:sets/video_segment_full')
            // The simple embed still renders unconditionally.
            ->toContain('partial:sets/video :video_url="url"');
    }
});

it('still emits parseable JSON-LD when the url is absent, omitting embedUrl', function (): void {
    // A modifier chain short-circuits on a null rather than running, so
    // `{{ url | to_json }}` renders nothing — leaving `"embedUrl": ` and a syntax
    // error that costs the whole block. The key is conditional for that reason
    // (UAMS-Web/uams-statamic-archive#1673). `url` is `required` in all three video sets, so this guards
    // hand-edited or imported data rather than anything the CP can save.
    $html = (string) Antlers::parse('{{ partial:sets/video_segment_full }}', [
        'title' => 'Fallback Title',
        'caption' => 'Author Caption',
        'excerpt' => 'A short summary.',
        'transcript' => 'The transcript text.',
        'url' => new Value(null),
        'creator' => null,
    ]);

    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);

    expect(trim($m[1] ?? ''))->toBeJson();

    /** @var array<string, mixed> $schema */
    $schema = json_decode(trim($m[1] ?? ''), true);

    expect($schema)->not->toHaveKey('embedUrl');
    expect($schema['@type'])->toBe('VideoObject');
    expect($schema['transcript'])->toBe('The transcript text.');
});
