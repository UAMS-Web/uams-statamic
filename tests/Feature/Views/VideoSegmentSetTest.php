<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Statamic\Facades\Antlers;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Fields;
use Statamic\Fields\Fieldset as FieldsetObject;
use Statamic\Fields\Validator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the Bard YouTube/Vimeo/Box segment sets (issue UAMS-Web/uams-statamic-archive#589): the thin
 * embed-only wrapper partials (resources/views/sets/_youtube|_vimeo|_box.antlers.html,
 * delegating to sets/video) and the conditional "Simple Embed" vs "Full Segment"
 * validation on uamsweb_set_video_{youtube,vimeo,box}. The validation mirrors
 * Statamic\Fieldtypes\Bard::setRules() (every set field runs through
 * Fields::validator() without server-side field conditions; the CP strips hidden
 * fields, so "Simple" omits the segment keys and "Full" includes them empty so
 * `required` fires). The partials have no PHP class; the validation drives the
 * fieldset YAML, so there is no app PHP mutable surface here.
 *
 * @command php artisan test --compact tests/Feature/Views/VideoSegmentSetTest.php
 */

/**
 * Resolve a required video-segment fieldset (throws rather than returning null).
 */
function videoSegmentFieldset(string $handle): FieldsetObject
{
    $fieldset = Fieldset::find($handle);

    if ($fieldset === null) {
        throw new RuntimeException("Fieldset {$handle} not found.");
    }

    return $fieldset;
}

it('renders a privacy-enhanced iframe from the youtube set url', function () {
    $out = (string) Antlers::parse('{{ partial:sets/youtube }}', [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ], true);

    expect($out)->toContain('<iframe');
    expect($out)->toContain('youtube-nocookie.com/embed/');
});

it('renders a Vimeo player iframe from the vimeo set url', function () {
    $out = (string) Antlers::parse('{{ partial:sets/vimeo }}', [
        'url' => 'https://vimeo.com/676247342',
    ], true);

    expect($out)->toContain('<iframe');
    expect($out)->toContain('player.vimeo.com');
});

it('renders native video from the box set url', function () {
    $out = (string) Antlers::parse('{{ partial:sets/box }}', [
        'url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
    ], true);

    expect($out)->toContain('<video');
    expect($out)->toContain('<source');
    expect($out)->toContain('type="video/mp4"');
    expect($out)->not->toContain('<iframe');
});

it('forwards the box set caption fields through to the video partial', function () {
    // sets/box delegates to sets/video; assert captions reach it on the box path
    // (an uploaded asset, same-origin, no crossorigin). (UAMS-Web/uams-statamic-archive#1048)
    $out = (string) Antlers::parse('{{ partial:sets/box }}', [
        'url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_file' => ['url' => '/assets/files/captions/example-en.vtt'],
    ], true);

    expect($out)->toContain('<track kind="captions"');
    expect($out)->toContain('src="/assets/files/captions/example-en.vtt"');
    expect($out)->not->toContain('crossorigin');
});

it('forwards a box set external caption URL and marks the player crossorigin', function () {
    $out = (string) Antlers::parse('{{ partial:sets/box }}', [
        'url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'caption_track_url' => 'https://app.box.com/shared/static/captions-en.vtt',
    ], true);

    expect($out)->toContain('<track kind="captions"');
    expect($out)->toContain('src="https://app.box.com/shared/static/captions-en.vtt"');
    expect($out)->toContain('crossorigin="anonymous"');
});

it('validates a box simple-embed set without segment metadata', function () {
    /** @var Fields $fields */
    $fields = videoSegmentFieldset('uamsweb_set_video_box')->fields()->addValues([
        'embed_mode' => 'simple',
        'url' => 'https://app.box.com/shared/static/1mmqmafq8s8mmnlml2h6c0z5yqm67ara.mp4',
        'aspect_ratio' => '16by9',
        'align' => 'aligncenter',
        // Box captions are now required (WCAG 1.2.2): pick a source + supply it.
        'caption_track_source' => 'upload',
        'caption_track_file' => ['captions/example-en.vtt'],
    ]);

    /** @var Validator $validator */
    $validator = $fields->validator();

    expect(throwableFrom(fn () => $validator->validate()))->toBeNull();
});

it('validates a youtube simple-embed set without segment metadata', function () {
    /** @var Fields $fields */
    $fields = videoSegmentFieldset('uamsweb_set_video_youtube')->fields()->addValues([
        'embed_mode' => 'simple',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'aspect_ratio' => '16by9',
        'align' => 'aligncenter',
    ]);

    /** @var Validator $validator */
    $validator = $fields->validator();

    expect(throwableFrom(fn () => $validator->validate()))->toBeNull();
});

it('requires the segment metadata for a youtube full-segment set', function () {
    /** @var Fields $fields */
    $fields = videoSegmentFieldset('uamsweb_set_video_youtube')->fields()->addValues([
        'embed_mode' => 'full',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'aspect_ratio' => '16by9',
        'align' => 'aligncenter',
        'title' => '',
        'transcript' => [],
        'about' => [],
        'language' => [],
        'geographic_location' => [],
        'geographic_location_content' => [],
    ]);

    /** @var Validator $validator */
    $validator = $fields->validator();

    $threw = false;
    $failed = [];

    try {
        $validator->validate();
    } catch (ValidationException $exception) {
        $threw = true;
        $failed = array_keys($exception->errors());
    }

    expect($threw)->toBeTrue();
    expect($failed)->toContain('title', 'transcript', 'about', 'language', 'geographic_location', 'geographic_location_content');
});
