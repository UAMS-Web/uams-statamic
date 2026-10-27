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

/*
|--------------------------------------------------------------------------
| Caption track → dedicated `captions` asset container (UAMS-Web/uams-statamic-archive#1085)
|--------------------------------------------------------------------------
|
| UAMS-Web/uams-statamic-archive#1048 added the shared `caption_track` WebVTT upload field on
| uamsweb_common_video but parked it on the general-purpose `files`
| container. UAMS-Web/uams-statamic-archive#1085 repoints it at a dedicated `.vtt`-only `captions`
| container. These tests pin that the field — and every blueprint that
| imports it — resolves to `captions`, not `files`.
*/

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

it('points the shared caption_track upload field at the captions container', function (): void {
    // Resolve the source field directly from the common video fieldset
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_common_video');
    /** @var Field $field */
    $field = $fieldset->field('caption_track');

    // The upload field is an assets field bound to the dedicated captions container
    expect($field)->not->toBeNull()
        ->and($field->type())->toBe('assets')
        ->and($field->get('container'))->toBe('captions');
});

it('resolves caption_track to the captions container through the box video segment set', function (): void {
    // Resolve through a real blueprint so the fieldset import chain flattens
    /** @var Statamic\Fields\Blueprint $video */
    $video = Blueprint::in('collections/posts')->get('video');

    // The box segment imports uamsweb_video_caption → caption_track_file
    /** @var Field $segments */
    $segments = $video->field('segments');
    /** @var Replicator $replicator */
    $replicator = $segments->fieldtype();
    /** @var Fields $boxFieldset */
    $boxFieldset = $replicator->fields('box');
    /** @var Collection<string, Field> $boxFields */
    $boxFields = $boxFieldset->all();

    // The imported upload field inherits the repointed container
    /** @var Field $captionField */
    $captionField = $boxFields->get('caption_track_file');

    expect($boxFields->has('caption_track_file'))->toBeTrue()
        ->and($captionField->get('container'))->toBe('captions');
});

it('no longer references the general files container for caption uploads', function (): void {
    // Guard against a silent revert: the source field must not fall back to `files`
    /** @var Statamic\Fields\Fieldset $fieldset */
    $fieldset = Fieldset::find('uamsweb_common_video');
    /** @var Field $field */
    $field = $fieldset->field('caption_track');

    expect($field->get('container'))->not->toBe('files');
});
