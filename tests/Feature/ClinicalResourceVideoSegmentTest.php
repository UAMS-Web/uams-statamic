<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Replicator;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — Video blueprint segment model (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| The `video` blueprint reuses the shared per-platform video segment
| fieldsets (uamsweb_video_segment_{youtube,vimeo,box}) — the same building
| blocks posts/video uses. Clinical resources show a single video, so the
| replicator is capped at one set and required when shown. Its handle is
| `segments`, the handle every sibling media blueprint uses and the one
| clinical_resources/_segments.antlers.html reads (UAMS-Web/uams-statamic-archive#2511). With the
| per-type split there is no `type` discriminator, so the replicator and
| `series` are always shown on the video blueprint (no `if` gate).
*/

function clinicalResourceVideoBlueprint(): Statamic\Fields\Blueprint
{
    /** @var Collection<string, Statamic\Fields\Blueprint> $blueprints */
    $blueprints = Blueprint::in('collections/clinical_resources');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprints->get('video');

    return $blueprint;
}

/**
 * Resolved field handles for one set of the clinical `segments` replicator.
 *
 * @return list<string>
 */
function clinicalVideoSetHandles(string $set): array
{
    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');
    /** @var Replicator $replicator */
    $replicator = $video->fieldtype();
    /** @var Fields $fields */
    $fields = $replicator->fields($set);
    /** @var list<string> $handles */
    $handles = $fields->all()->keys()->all();

    return $handles;
}

it('stores the video under `segments`, not under a `video` handle the show template never reads', function () {
    $blueprint = clinicalResourceVideoBlueprint();

    expect($blueprint->hasField('segments'))->toBeTrue();
    expect($blueprint->hasField('video'))->toBeFalse();
});

it('models the video as a single-segment replicator, always shown on the video blueprint', function () {
    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');

    expect($video->type())->toBe('replicator');
    expect($video->get('max_sets'))->toBe(1);
    expect($video->get('if'))->toBeNull();
    expect($video->get('validate'))->toBe(['required', 'sometimes']);
});

it('keeps the video series field always shown (no type gate)', function () {
    /** @var Field $series */
    $series = clinicalResourceVideoBlueprint()->field('series');

    expect($series->get('if'))->toBeNull();
});

it('binds the video series picker to the video_series taxonomy', function () {
    // `series` resolves from the shared uamsweb_common_video fieldset; pin the
    // taxonomy so a shared-fieldset change can't silently re-point it.
    /** @var Field $series */
    $series = clinicalResourceVideoBlueprint()->field('series');

    expect($series->get('taxonomies'))->toBe(['video_series']);
});

it('requires an aspect ratio on every segment set', function (string $set) {
    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');
    /** @var Replicator $replicator */
    $replicator = $video->fieldtype();
    /** @var Fields $fields */
    $fields = $replicator->fields($set);
    /** @var Field $aspect */
    $aspect = $fields->all()->get('aspect_ratio');

    expect($aspect->get('validate'))->toContain('required');
})->with(['youtube', 'vimeo', 'box']);

it('drops the old single-URL video fields', function () {
    $blueprint = clinicalResourceVideoBlueprint();

    expect($blueprint->hasField('aspect_ratio'))->toBeFalse();
    expect($blueprint->hasField('video_description'))->toBeFalse();
    expect($blueprint->hasField('video_transcript'))->toBeFalse();
});

it('offers YouTube, Vimeo, and Box segment sets in order', function () {
    $setHandles = [];

    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');
    /** @var array<array-key, array{sets?: array<string, mixed>}> $sets */
    $sets = $video->get('sets');

    foreach ($sets as $group) {
        $setHandles = array_merge($setHandles, array_keys($group['sets'] ?? []));
    }

    expect($setHandles)->toBe(['youtube', 'vimeo', 'box']);
});

it('composes each segment set from the shared per-platform fieldset', function (string $set) {
    $handles = clinicalVideoSetHandles($set);

    // url + aspect_ratio + the segment metadata (incl. the kept excerpt and
    // transcript) + creator.
    expect($handles)->toContain('url', 'aspect_ratio', 'excerpt', 'transcript', 'creator');

    // The Bard-inline-only controls were intentionally dropped.
    expect($handles)->not->toContain('embed_mode');
    expect($handles)->not->toContain('align');
    expect($handles)->not->toContain('caption');

    // digital_source_type resolves exactly once (no posts-style duplicate).
    expect(count(array_keys($handles, 'digital_source_type', true)))->toBe(1);
})->with(['youtube', 'vimeo', 'box']);

it('captures caption tracks only on the Box set, behind a required source selector', function () {
    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');
    /** @var Replicator $replicator */
    $replicator = $video->fieldtype();
    /** @var Fields $boxFields */
    $boxFields = $replicator->fields('box');
    /** @var Collection<string, Field> $fields */
    $fields = $boxFields->all();

    expect($fields->keys()->all())->toContain('caption_track_source', 'caption_track_file', 'caption_track_url');

    /** @var Field $captionSource */
    $captionSource = $fields->get('caption_track_source');
    expect($captionSource->type())->toBe('button_group');
    expect($captionSource->get('validate'))->toContain('required');

    // Upload vs URL are mutually exclusive — each gates on the selector and is
    // required when shown.
    /** @var Field $captionFile */
    $captionFile = $fields->get('caption_track_file');
    expect($captionFile->get('if'))->toBe(['caption_track_source' => 'equals upload']);
    expect($captionFile->get('validate'))->toBe(['required', 'sometimes']);
    /** @var Field $captionUrl */
    $captionUrl = $fields->get('caption_track_url');
    expect($captionUrl->get('if'))->toBe(['caption_track_source' => 'equals url']);
    expect($captionUrl->get('validate'))->toBe(['required', 'sometimes']);
});

it('disables autofocus on the segment title so adding a set does not steal focus', function () {
    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');
    /** @var Replicator $replicator */
    $replicator = $video->fieldtype();
    /** @var Fields $fields */
    $fields = $replicator->fields('youtube');
    /** @var Field $title */
    $title = $fields->all()->get('title');

    // The `title` handle gets implicit autofocus in Statamic's TextFieldtype;
    // a nested segment title is not the entry title, so opt out explicitly.
    expect($title->get('focus'))->toBeFalse();
});

it('omits caption tracks from the YouTube and Vimeo sets (captions are Box-only)', function (string $set) {
    $handles = clinicalVideoSetHandles($set);

    expect($handles)->not->toContain('caption_track_file');
    expect($handles)->not->toContain('caption_track_url');
    expect($handles)->not->toContain('caption_track_source');
})->with(['youtube', 'vimeo']);

it('keeps the per-platform URL validator on each segment set', function (string $set, string $rule) {
    /** @var Field $video */
    $video = clinicalResourceVideoBlueprint()->field('segments');
    /** @var Replicator $replicator */
    $replicator = $video->fieldtype();
    /** @var Fields $fields */
    $fields = $replicator->fields($set);
    /** @var Field $url */
    $url = $fields->all()->get('url');

    expect($url->get('validate'))->toContain('required', $rule);
})->with([
    ['youtube', 'youtube_url'],
    ['vimeo', 'vimeo_url'],
    ['box', 'box_shared_static_video_url'],
]);

it('lives only on the video blueprint', function (string $blueprint) {
    /** @var Collection<string, Statamic\Fields\Blueprint> $blueprints */
    $blueprints = Blueprint::in('collections/clinical_resources');
    /** @var Statamic\Fields\Blueprint $resolved */
    $resolved = $blueprints->get($blueprint);

    expect($resolved->hasField('video'))->toBeFalse();
})->with(['article', 'infographic', 'document']);
