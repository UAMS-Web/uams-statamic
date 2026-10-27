<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1090 — the Clinical Resource video segment path
 * (clinical_resources/_segments.antlers.html with media_type=video) must render
 * the Box caption <track> it forwards to sets/video. VideoSetPartialTest already
 * covers sets/video directly; this exercises it *through the clinical segments
 * wrapper*, previously unrendered by any test (ClinicalResourcesTemplatesTest only
 * string-matches the show template source). The load-bearing wiring is the
 * caption_track_file -> caption_track REMAP the wrapper performs (a segment field
 * named differently from the sets/video param), so the first test fails if that
 * remap is dropped. The external caption_track_url path also reaches the player via
 * segment-scope inheritance (same field/param name) — its explicit forward is
 * defensive, and the second test asserts that render behavior. Hermetic Antlers
 * render — no DB/content.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalResourceVideoCaptionRenderTest.php
 */

/**
 * Render the clinical segments partial for a single video segment scope.
 *
 * @param  array<string, mixed>  $segment
 */
function renderClinicalVideoSegment(array $segment): string
{
    return (string) Antlers::parse('{{ partial:clinical_resources/segments }}', [
        'media_type' => 'video',
        'segments' => [$segment],
    ], true);
}

it('forwards a Box segment uploaded caption track into a rendered captions <track>', function () {
    $out = renderClinicalVideoSegment([
        'title' => 'Diabetes',
        'url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_file' => ['url' => '/assets/files/captions/diabetes-en.vtt'],
    ]);

    expect($out)
        // The wrapper actually rendered the segment (guards against an empty render).
        ->toContain('Diabetes')
        // Box direct URL → native <video>, and the forwarded caption track renders.
        ->toContain('<video')
        ->toContain('<track kind="captions"')
        ->toContain('srclang="en"')
        ->toContain('label="English"')
        ->toContain('src="/assets/files/captions/diabetes-en.vtt"');
    // An uploaded asset is same-origin, so the player must not be CORS-marked.
    expect($out)->not->toContain('crossorigin');
});

it('forwards an external Box caption URL and marks the player crossorigin', function () {
    $out = renderClinicalVideoSegment([
        'title' => 'Diabetes',
        'url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_url' => 'https://app.box.com/shared/static/diabetes-en.vtt',
    ]);

    expect($out)
        ->toContain('<track kind="captions"')
        ->toContain('src="https://app.box.com/shared/static/diabetes-en.vtt"')
        // A cross-origin <track> only loads when the player is crossorigin.
        ->toContain('crossorigin="anonymous"');
});

it('emits no caption track for a YouTube segment (captions are platform-provided)', function () {
    $out = renderClinicalVideoSegment([
        'title' => 'Diabetes',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        // Even with a caption source present, a third-party iframe cannot carry a <track>.
        'caption_track_file' => ['url' => '/assets/files/captions/diabetes-en.vtt'],
    ]);

    expect($out)->toContain('<iframe');
    expect($out)->not->toContain('<track');
});
