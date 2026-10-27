<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
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
| Shared per-platform video segment fieldsets (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| uamsweb_video_segment_{youtube,vimeo,box} compose the common atoms
| (uamsweb_common_video url + aspect_ratio, uamsweb_post_video_segment,
| uamsweb_post_creator_video) into a lean primary-content segment — no
| embed_mode / align / caption. Both posts/video `segments` and
| clinical_resources `video` reference them, so their sets resolve identically.
*/

/**
 * Resolved field handles for one set of a replicator field.
 *
 * @return list<string>
 */
function replicatorSetHandles(Statamic\Fields\Blueprint $blueprint, string $field, string $set): array
{
    /** @var Field $blueprintField */
    $blueprintField = $blueprint->field($field);

    /** @var Replicator $fieldtype */
    $fieldtype = $blueprintField->fieldtype();

    /** @var Fields $fields */
    $fields = $fieldtype->fields($set);

    /** @var list<string> $handles */
    $handles = $fields->all()->keys()->all();

    return $handles;
}

/**
 * The `video` blueprint of a collection, narrowed to the concrete Blueprint.
 */
function videoBlueprint(string $collection): Statamic\Fields\Blueprint
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in("collections/{$collection}")->get('video');

    return $blueprint;
}

/**
 * Top-level set handles declared by a replicator field (across set groups).
 *
 * @return list<string>
 */
function replicatorSetNames(Statamic\Fields\Blueprint $blueprint, string $field): array
{
    $names = [];

    /** @var Field $blueprintField */
    $blueprintField = $blueprint->field($field);

    /** @var array<string, array{sets?: array<string, mixed>}> $groups */
    $groups = $blueprintField->get('sets');

    foreach ($groups as $group) {
        $names = array_merge($names, array_keys($group['sets'] ?? []));
    }

    return $names;
}

it('registers the three per-platform video segment fieldsets', function (string $handle) {
    expect(Fieldset::find($handle))->not->toBeNull();
})->with([
    'uamsweb_video_segment_youtube',
    'uamsweb_video_segment_vimeo',
    'uamsweb_video_segment_box',
]);

it('composes each fieldset from url, aspect_ratio, segment metadata, and creator', function (string $handle, string $rule) {
    // Resolve through a real blueprint so nested imports flatten.
    $clinical = videoBlueprint('clinical_resources');
    $set = str_replace('uamsweb_video_segment_', '', $handle);

    /** @var Field $videoField */
    $videoField = $clinical->field('segments');

    /** @var Replicator $videoFieldtype */
    $videoFieldtype = $videoField->fieldtype();

    /** @var Fields $fields */
    $fields = $videoFieldtype->fields($set);

    /** @var Collection<string, Field> $setFields */
    $setFields = $fields->all();
    $handles = $setFields->keys()->all();

    expect($handles)->toContain('url', 'aspect_ratio', 'transcript', 'creator');

    /** @var Field $urlField */
    $urlField = $setFields->get('url');
    expect($urlField->get('validate'))->toContain('required', $rule);

    // aspect_ratio carries the shared default and is required.
    /** @var Field $aspectRatioField */
    $aspectRatioField = $setFields->get('aspect_ratio');
    expect($aspectRatioField->defaultValue())->toBe('16by9');
    expect($aspectRatioField->get('validate'))->toContain('required');
})->with([
    ['uamsweb_video_segment_youtube', 'youtube_url'],
    ['uamsweb_video_segment_vimeo', 'vimeo_url'],
    ['uamsweb_video_segment_box', 'box_shared_static_video_url'],
]);

it('adds aspect_ratio to the posts/video segment sets', function () {
    expect(replicatorSetHandles(videoBlueprint('posts'), 'segments', 'youtube'))
        ->toContain('aspect_ratio');
});

it('adds a Box segment set to posts/video', function () {
    expect(replicatorSetNames(videoBlueprint('posts'), 'segments'))
        ->toBe(['youtube', 'vimeo', 'box']);
});

it('drops the duplicate digital_source_type from posts/video segments', function () {
    $handles = replicatorSetHandles(videoBlueprint('posts'), 'segments', 'youtube');

    expect(count(array_keys($handles, 'digital_source_type', true)))->toBe(1);
});

it('resolves posts and clinical video sets to identical fields (DRY)', function (string $set) {
    $posts = replicatorSetHandles(videoBlueprint('posts'), 'segments', $set);
    $clinical = replicatorSetHandles(videoBlueprint('clinical_resources'), 'segments', $set);

    expect($clinical)->toBe($posts);
})->with(['youtube', 'vimeo', 'box']);
