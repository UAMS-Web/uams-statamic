<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The `clinical_locations` per-child address and parking override (UAMS-Web/uams-statamic-archive#1269).
 *
 * The visibility LOGIC is JavaScript and is covered by
 * `resources/js/conditions/parentOverride/evaluateParentOverride.test.js`. What can only be
 * checked here is the wiring: that the toggles exist and that every field which used to be
 * hard-suppressed on a child now routes through the custom condition instead. A YAML edit that
 * dropped one would leave the JavaScript passing and that field suppressed forever.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/ClinicalLocationParentOverrideTest.php
 */
function locationBlueprintField(string $handle): Field
{
    $blueprint = mustBe(
        Blueprint::class,
        BlueprintFacade::find('collections/clinical_locations/location'),
        'the clinical_locations location blueprint'
    );

    $fields = $blueprint->fields()->all();

    expect($fields->has($handle))->toBeTrue("expected {$handle} on the blueprint");

    return mustBe(Field::class, $fields->get($handle), "the {$handle} field");
}

/**
 * One field's `if` conditions, narrowed to a string-keyed map for analysis.
 *
 * @return array<string, string>
 */
function locationFieldConditions(Field $field): array
{
    $conditions = $field->config()['if'] ?? null;

    if (! is_array($conditions)) {
        return [];
    }

    $narrowed = [];

    foreach ($conditions as $key => $value) {
        $narrowed[(string) $key] = is_string($value) ? $value : '';
    }

    return $narrowed;
}

it('offers both override toggles, and only to a location that has a parent', function () {
    foreach (['parent_address_override_query', 'parent_parking_override_query'] as $handle) {
        $field = locationBlueprintField($handle);

        // Shown only on a child: a top-level location has nothing to inherit from, so the
        // question does not arise for it.
        expect(locationFieldConditions($field))->toBe(['parent' => 'not empty'])
            // Defaulting off is what preserves today's behavior for every existing child.
            ->and($field->config()['default'] ?? null)->toBeFalse();
    }
});

it('routes every address field through the override condition rather than hiding it', function () {
    // Before UAMS-Web/uams-statamic-archive#1269 each of these carried `parent: 'equals empty'`, which suppressed it on any
    // child outright. Every field on the tab is named so one added later cannot quietly keep
    // the old hard suppression.
    // Since UAMS-Web/uams-statamic-archive#2608 each field gates on its own group's role, matching Find-a-Doc's groups.
    $groupByHandle = [
        'facility_query' => 'facility',
        'facility' => 'facility',
        'facility_level' => 'unit',
        'suite' => 'unit',
        'address' => 'street',
        'regions' => 'street',
        'map' => 'map',
    ];

    foreach ($groupByHandle as $handle => $group) {
        $conditions = locationFieldConditions(locationBlueprintField($handle));

        expect($conditions['parent_address_override_query'] ?? null)
            ->toBe("custom parentOverride:{$group}", "{$handle} must gate on the {$group} group")
            ->and($conditions)->not->toHaveKey('parent', "{$handle} must not still hard-suppress on parent");
    }
});

it('leaves containment to the group roles except on the facility relationship (#2608)', function () {
    // A flat `facility_query` key would AND the parent's unanswered question back in, and a
    // child inheriting its facility could never show its own floor, suite, street or pin.
    foreach (['facility_level', 'suite', 'address', 'regions', 'map'] as $handle) {
        expect(locationFieldConditions(locationBlueprintField($handle)))->not->toHaveKey('facility_query', $handle);
    }

    // The facility itself is still asked only once the location answers that it is contained.
    expect(locationFieldConditions(locationBlueprintField('facility'))['facility_query'] ?? null)->toBe('equals true');
});

it('offers one group toggle per address group, only under the master on a child (#2608)', function () {
    foreach (['street', 'facility', 'unit', 'map'] as $group) {
        $field = locationBlueprintField("parent_address_override_{$group}_query");

        expect(locationFieldConditions($field))->toBe([
            'parent' => 'not empty',
            'parent_address_override_query' => 'equals true',
        ])
            // Off by default, so a child that turns the master on overrides nothing until asked.
            ->and($field->config()['default'] ?? null)->toBeFalse();
    }
});

it('routes the parking question through the parking override', function () {
    $conditions = locationFieldConditions(locationBlueprintField('parking_query'));

    expect($conditions['parent_parking_override_query'] ?? null)
        ->toBe('custom parentOverride:parking')
        ->and($conditions)->not->toHaveKey('parent');
});

it('leaves the parking leaf fields on their own condition, untouched by this change', function () {
    // These were never gated on `parent`; they resolve false for a child because the facility
    // and parking questions are unanswered. UAMS-Web/uams-statamic-archive#1269 changes the evaluator, not these conditions.
    expect(locationFieldConditions(locationBlueprintField('parking_instructions')))
        ->toBe(['parking_instructions_inherit_query' => 'custom parkingVisible:instructions']);
});
