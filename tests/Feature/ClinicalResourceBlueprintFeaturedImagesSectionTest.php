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
| Clinical Resource — Featured Image and Cover Image section (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| Every per-type blueprint imports the shared `uamsweb_post_cover_image`
| fieldset. These tests pin the load-bearing field config: the featured
| image's 16:9 dimension validator, and the global cover_image_query default
| of `false` (an entry no longer auto-promotes its featured image to cover).
*/

/**
 * @return list<string>
 */
function clinicalBlueprints(): array
{
    return ['article', 'infographic', 'document', 'video'];
}

it('preserves the 16:9 dimension validator carried by the featured image', function (string $blueprint) {
    /** @var Statamic\Fields\Blueprint $resolvedBlueprint */
    $resolvedBlueprint = Blueprint::in('collections/clinical_resources')->get($blueprint);
    /** @var Field $field */
    $field = $resolvedBlueprint->field('featured_image_16_9');

    expect($field->get('validate'))
        ->toContain('dimensions:min_width=1200,min_height=675');
})->with(clinicalBlueprints());

it('defaults cover_image_query to false so the featured image is not auto-promoted', function (string $blueprint) {
    /** @var Statamic\Fields\Blueprint $resolvedBlueprint */
    $resolvedBlueprint = Blueprint::in('collections/clinical_resources')->get($blueprint);
    /** @var Field $field */
    $field = $resolvedBlueprint->field('cover_image_query');

    expect($field->get('default'))
        ->toBeFalse();
})->with(clinicalBlueprints());
