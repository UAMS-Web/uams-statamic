<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — per-type blueprint media-field validation (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| clinical_resources is split into one blueprint per resource type. For the
| media types (infographic / document / video) the attached media is the
| primary content and the rich text is a required *introduction*. These tests
| pin the load-bearing validation: the required media/transcript fields and
| their asset container.
*/

it('requires the infographic image and transcript, stored in the images container', function () {
    // The image is the infographic blueprint's primary content and the transcript
    // is its accessibility equivalent — both stay required; the image lives in the
    // `images` asset container (not a documents/files container).
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_resources')->get('infographic');

    /** @var Field $infographic */
    $infographic = $bp->field('infographic');
    expect($infographic->get('validate'))->toContain('required');
    expect($infographic->get('container'))->toBe('images');

    /** @var Field $transcript */
    $transcript = $bp->field('infographic_transcript');
    expect($transcript->get('validate'))->toContain('required');
});

it('requires the media intro field on every media blueprint', function (string $blueprint, string $handle) {
    // Each WordPress *_descr intro was `required`; the media intro fields keep
    // that (the video `intro` re-acquired it to match infographic/document).
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_resources')->get($blueprint);
    /** @var Field $field */
    $field = $bp->field($handle);

    expect($field->get('validate'))->toContain('required');
})->with([
    ['infographic', 'infographic_description'],
    ['document', 'documents_description'],
    ['video', 'intro'],
]);
