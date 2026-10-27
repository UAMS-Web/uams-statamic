<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
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
| Clinical Resource — Article blueprint syndication gating (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| clinical_resources is split into per-type blueprints, so "is this an
| article?" is the blueprint identity, not a `type` radio. Visibility now
| keys purely on the Syndication answer via two custom JS conditions
| (resources/js/conditions/{articleVisible,notSyndicated}.js, unit-covered
| by their *.test.js): the Article body shows unless the resource is
| NCI-syndicated; the authorship/origin/location fields stay hidden until
| the syndication question is explicitly answered "no". These tests pin the
| blueprint wiring those conditions hang on.
*/

function clinicalArticleBlueprint(): Statamic\Fields\Blueprint
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_resources')->get('article');

    return $blueprint;
}

it('gates the Article body via the custom articleVisible condition', function () {
    /** @var Field $field */
    $field = clinicalArticleBlueprint()->field('article');

    expect($field->get('if'))
        ->toBe(['syndication_query' => 'custom articleVisible']);
});

it('hides the authorship and origin fields until syndication is answered (custom notSyndicated)', function (string $handle) {
    // notSyndicated is strict: it shows the field only once syndication_query
    // is an explicit `false`, so an unanswered (null) question keeps these
    // hidden — a built-in `equals false` would wrongly match null too.
    /** @var Field $field */
    $field = clinicalArticleBlueprint()->field($handle);

    expect($field->get('if'))
        ->toHaveKey('syndication_query', 'custom notSyndicated');
})->with([
    'hide_creator_query',
    'creator',
    'digital_source_type',
    'geographic_location',
    'geographic_location_content',
]);

it('defaults the article geographic location to little-rock, single-item and required', function () {
    // UAMS is in Little Rock; the "where produced" default and single-item cap
    // must not drift.
    /** @var Field $field */
    $field = clinicalArticleBlueprint()->field('geographic_location');

    expect($field->get('default'))->toBe(['little-rock']);
    expect($field->get('max_items'))->toBe(1);
    expect($field->get('validate'))->toContain('required');
});

it('keeps the creator field gated on BOTH the hide toggle and the syndication answer', function () {
    // The creator picker ANDs its base hide_creator_query gate with the
    // notSyndicated gate in one `if` block — losing either would leak the
    // field into a state it should not appear in.
    /** @var Field $field */
    $field = clinicalArticleBlueprint()->field('creator');

    expect($field->get('if'))->toBe([
        'hide_creator_query' => 'equals false',
        'syndication_query' => 'custom notSyndicated',
    ]);
});

it('pairs every custom-gated required field with `sometimes` so hidden fields stay savable', function () {
    // Server-side validation does not understand the custom JS conditions, so a
    // field that is `required` and hidden by `custom articleVisible`/`notSyndicated`
    // would make the entry unsavable unless it also carries `sometimes` (which
    // skips validation when the field is absent from the payload).
    $violations = [];

    /** @var Collection<string, Field> $fields */
    $fields = clinicalArticleBlueprint()->fields()->all();

    foreach ($fields as $handle => $field) {
        /** @var array<string, mixed> $conditions */
        $conditions = $field->get('if') ?? [];
        $customGated = collect($conditions)
            ->contains(fn ($value): bool => is_string($value) && str_starts_with($value, 'custom '));
        /** @var array<int, string> $validate */
        $validate = $field->get('validate') ?? [];

        if ($customGated && in_array('required', $validate, true) && ! in_array('sometimes', $validate, true)) {
            $violations[] = $handle;
        }
    }

    expect($violations)->toBe([]);
});
