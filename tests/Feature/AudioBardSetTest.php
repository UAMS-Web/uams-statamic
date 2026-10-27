<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#588: a Bard "Audio" set that mirrors the Audio Post segment
 * fields and renders through `sets/_audio`, plus the segment-usage safeguard
 * (explicit title/caption still win).
 *
 * @command  php artisan test --compact tests/Feature/AudioBardSetTest.php
 */

/**
 * Render the audio set partial with the given scope.
 *
 * @param  array<string, mixed>  $data
 */
function renderAudioSet(array $data): string
{
    return (string) Antlers::parse('{{ partial:sets/audio }}', $data);
}

it('registers the Audio Bard set against the shared segment fieldset', function (): void {
    // The set fieldset imports the Audio Post segment fields (no drift).
    /** @var array{fields: list<array<string, mixed>>} $fieldset */
    $fieldset = Yaml::parseFile(resource_path('fieldsets/uamsweb_set_audio.yaml'));
    expect(collect($fieldset['fields'])->pluck('import')->all())
        ->toContain('uamsweb_audio_segment_local_file');

    // The set is registered on the content Bard.
    $common = File::get(resource_path('fieldsets/uamsweb_common.yaml'));
    expect($common)->toMatch('/\n\s+audio:\n\s+display: Audio\n/');
});

it('renders an audio player with MP3 + OGG sources and the override title/caption', function (): void {
    $html = renderAudioSet([
        'file' => '/audio/ep.mp3',
        'file_alternate' => '/audio/ep.ogg',
        'preload' => 'metadata',
        'loop' => false,
        'title_override' => 'Episode 5',
        'excerpt_override' => 'A great episode.',
    ]);

    expect($html)
        ->toContain('<audio')
        ->toContain('<source src="/audio/ep.mp3" type="audio/mpeg">')
        ->toContain('<source src="/audio/ep.ogg" type="audio/ogg">')
        // Bard-set overrides fill the audio title + visible caption.
        ->toContain('title="Episode 5"')
        ->toContain('A great episode.');
});

it('keeps an explicit segment title/caption over the override fallbacks', function (): void {
    $html = renderAudioSet([
        'file' => '/audio/seg.mp3',
        'title' => 'Segment Title',
        'caption' => 'Audio: Segment Title',
        'caption_hide' => true,
        'title_override' => 'IGNORED',
        'excerpt_override' => 'IGNORED',
    ]);

    expect($html)
        ->toContain('title="Segment Title"')
        ->toContain('Audio: Segment Title');
    expect($html)->not->toContain('IGNORED');
});
