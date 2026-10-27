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
| Clinical Resource — normalized handles & relationships (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| The split to per-type blueprints normalized the WordPress-era field handles
| (aoe -> clinical_expertise, text -> article, syndicated -> syndication_query,
| …) and retired the `type` discriminator entirely (the blueprint *is* the
| type). The six FaD relationships now come from the shared
| `uamsweb_clinical_resource_related` fieldset imported into every blueprint.
| These tests pin the new handles on the right blueprints, confirm the old
| ones are gone everywhere, and verify the relationship overrides.
*/

/**
 * @return list<string>
 */
function clinicalBlueprintNames(): array
{
    return ['article', 'infographic', 'document', 'video'];
}

it('imports the six FaD relationships into every blueprint as entries fields', function (string $handle) {
    foreach (clinicalBlueprintNames() as $name) {
        $blueprint = mustBe(Statamic\Fields\Blueprint::class, Blueprint::in('collections/clinical_resources')->get($name), 'blueprint');
        $field = mustBe(Field::class, $blueprint->field($handle), 'field');

        expect($field->type())->toBe('entries', "'{$handle}' should be an entries field on {$name}");
    }
})->with([
    'clinical_providers',
    'clinical_locations',
    'clinical_expertise',
    'clinical_conditions',
    'clinical_services',
    'clinical_resources',
]);

it('points each relationship at its intended target collection', function (string $handle, array $collections) {
    // Pin the target collection of every FaD relationship, not just providers —
    // these come from shared `uamsweb_common_fad.*` presets, so a preset
    // regression could silently re-point a clinical picker at the wrong entries.
    $blueprint = mustBe(Statamic\Fields\Blueprint::class, Blueprint::in('collections/clinical_resources')->get('article'), 'blueprint');
    $field = mustBe(Field::class, $blueprint->field($handle), 'field');
    expect($field->get('collections'))
        ->toBe($collections);
})->with([
    ['clinical_providers', ['people']],
    ['clinical_locations', ['clinical_locations']],
    ['clinical_expertise', ['clinical_expertise']],
    ['clinical_conditions', ['clinical_conditions']],
    ['clinical_services', ['clinical_services']],
    ['clinical_resources', ['clinical_resources']],
]);

it('excludes the current entry from its own related-resources picker', function () {
    $blueprint = mustBe(Statamic\Fields\Blueprint::class, Blueprint::in('collections/clinical_resources')->get('article'), 'blueprint');
    $field = mustBe(Field::class, $blueprint->field('clinical_resources'), 'field');
    expect($field->get('query_scopes'))
        ->toBe(['exclude_self']);
});
