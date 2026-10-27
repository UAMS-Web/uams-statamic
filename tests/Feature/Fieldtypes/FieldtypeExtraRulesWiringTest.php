<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1301: a fieldtype's own (field-level) validation rules must be returned from
 * rules() — which Statamic's Field::rules() merges into the field's OWN handle
 * bucket — NOT from extraRules(), which Statamic keys as a [fieldHandle => rules]
 * MAP. A flat list from extraRules() lands under numeric phantom fields ("0","1")
 * that never exist in the payload (so the rules never fire), and a rule
 * OBJECT/Closure there throws a TypeError in Field::addNullableRule().
 *
 * These tests drive the REAL Statamic\Fields\Field::rules() (the save path) plus
 * Laravel's Validator, proving the fixed fieldtypes attach their rules to the
 * handle, actually enforce, and emit no phantom numeric keys. Rendering rules
 * against hand-built fields has no mutable content surface.
 *
 * @command php artisan test --compact tests/Feature/Fieldtypes/FieldtypeExtraRulesWiringTest.php
 */

/**
 * The keys of a field's compiled rules that are NOT real string attributes —
 * i.e. numeric phantom keys (the UAMS-Web/uams-statamic-archive#1301 mis-key symptom).
 *
 * @param  array<array-key, mixed>  $rules
 * @return list<int|string>
 */
function phantomRuleKeys(array $rules): array
{
    return array_values(array_filter(
        array_keys($rules),
        static fn ($key): bool => is_int($key),
    ));
}

/**
 * Build a Field for the given fieldtype + config.
 *
 * @param  array<string, mixed>  $config
 */
function ruleField(string $handle, string $type, array $config = []): Field
{
    return new Field($handle, array_merge(['type' => $type], $config));
}

// ---------------------------------------------------------------------------
// Checkboxes — selection-count rules were dead AND threw (UAMS-Web/uams-statamic-archive#1301).
// ---------------------------------------------------------------------------

it('attaches checkboxes selection-count rules to the field handle with no phantom key (range)', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'range', 'min_selections' => 2, 'max_selections' => 4,
    ])->rules();

    expect(phantomRuleKeys($rules))->toBe([]);
    expect($rules)->toHaveKey('choices');
    // Two count closures (min + max) land on the handle.
    $closures = array_filter($rules['choices'], static fn ($r): bool => $r instanceof Closure);
    expect($closures)->toHaveCount(2);
});

it('does not throw when compiling rules for a limited checkboxes field', function () {
    // Before the fix, extraRules() fed a Closure into Field::addNullableRule() →
    // in_array('nullable', $closure) TypeError, 500ing every save.
    expect(throwableFrom(fn () => ruleField('choices', 'checkboxes', [
        'selection_limit' => 'exact', 'exact_selections' => 3,
    ])->rules()))->toBeNull();
});

it('enforces the checkboxes minimum selection count', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'range', 'min_selections' => 2, 'max_selections' => 4,
    ])->rules();

    expect(Validator::make(['choices' => ['a']], $rules)->passes())->toBeFalse();
    expect(Validator::make(['choices' => ['a', 'b']], $rules)->passes())->toBeTrue();
});

it('enforces the checkboxes maximum selection count', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'range', 'min_selections' => 2, 'max_selections' => 4,
    ])->rules();

    expect(Validator::make(['choices' => ['a', 'b', 'c', 'd', 'e']], $rules)->passes())->toBeFalse();
    expect(Validator::make(['choices' => ['a', 'b', 'c', 'd']], $rules)->passes())->toBeTrue();
});

it('enforces the checkboxes exact selection count', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'exact', 'exact_selections' => 3,
    ])->rules();

    expect(Validator::make(['choices' => ['a', 'b']], $rules)->passes())->toBeFalse();
    expect(Validator::make(['choices' => ['a', 'b', 'c']], $rules)->passes())->toBeTrue();
    expect(Validator::make(['choices' => ['a', 'b', 'c', 'd']], $rules)->passes())->toBeFalse();
});

it('adds no selection-count rule when the checkboxes field is unlimited', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [])->rules();

    expect(phantomRuleKeys($rules))->toBe([]);
    expect($rules['choices'])->not->toContain(fn ($r) => $r instanceof Closure);
    expect(Validator::make(['choices' => ['a', 'b', 'c', 'd', 'e', 'f']], $rules)->passes())->toBeTrue();
});

it('allows a blank OPTIONAL checkboxes field despite a minimum limit', function () {
    // UAMS-Web/uams-statamic-archive#1301 review: an empty selection is "unanswered", not "too few" — a range
    // limit must not make an OPTIONAL field un-blankable. The minimum only applies
    // once the user selects at least one option.
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'range', 'min_selections' => 2, 'max_selections' => 4,
    ])->rules();

    expect(Validator::make(['choices' => []], $rules)->passes())->toBeTrue();
    expect(Validator::make(['choices' => ['a']], $rules)->passes())->toBeFalse();
    expect(Validator::make(['choices' => ['a', 'b']], $rules)->passes())->toBeTrue();
});

it('allows a blank OPTIONAL checkboxes field despite an exact limit', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'exact', 'exact_selections' => 3,
    ])->rules();

    expect(Validator::make(['choices' => []], $rules)->passes())->toBeTrue();
    expect(Validator::make(['choices' => ['a']], $rules)->passes())->toBeFalse();
    expect(Validator::make(['choices' => ['a', 'b', 'c']], $rules)->passes())->toBeTrue();
});

it('still rejects a blank REQUIRED checkboxes field with a minimum limit', function () {
    // The blueprint `required` rule rejects empty; the count closure defers to it
    // rather than double-failing, so requiredness stays with the required rule.
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('choices', 'checkboxes', [
        'validate' => ['required'],
        'selection_limit' => 'range', 'min_selections' => 2, 'max_selections' => 4,
    ])->rules();

    expect(Validator::make(['choices' => []], $rules)->passes())->toBeFalse();
    expect(Validator::make(['choices' => ['a', 'b']], $rules)->passes())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Identifier — preset rule was live via rules() but duplicated under a dead
// phantom key via extraRules() (UAMS-Web/uams-statamic-archive#1301). The fix drops the phantom.
// ---------------------------------------------------------------------------

it('keys the identifier preset rule to the field handle with no phantom key', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('external_id', 'identifier', ['preset' => 'ahaid'])->rules();

    expect(phantomRuleKeys($rules))->toBe([]);
    expect($rules)->toHaveKey('external_id');
    expect($rules['external_id'])->toContain('ahaid');
});

it('keys no rule at all for an identifier without a preset', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('external_id', 'identifier', [])->rules();

    expect(phantomRuleKeys($rules))->toBe([]);
});

// ---------------------------------------------------------------------------
// The trait default no longer emits a flat list.
// ---------------------------------------------------------------------------

it('returns an empty extraRules() from a fieldtype using the validation-rules trait', function () {
    // Checkboxes uses FieldtypeValidationRules; its field-level rules now flow
    // through rules(), so the trait's extraRules() default must emit nothing.
    $field = ruleField('choices', 'checkboxes', [
        'selection_limit' => 'range', 'min_selections' => 2, 'max_selections' => 4,
    ]);

    /** @var Fieldtype $fieldtype */
    $fieldtype = $field->fieldtype();
    expect($fieldtype->extraRules())->toBe([]);
});

// ---------------------------------------------------------------------------
// A legit compound fieldtype (Gps) keeps its genuine dotted subfield MAP — the
// trait change must not perturb the correct pattern.
// ---------------------------------------------------------------------------

it('keeps a legit compound fieldtype (gps) keyed to real dotted subfield paths', function () {
    /** @var array<array-key, array<array-key, mixed>> $rules */
    $rules = ruleField('coords', 'gps', [])->rules();

    expect(phantomRuleKeys($rules))->toBe([]);
    expect(array_keys($rules))->toContain('coords', 'coords.lat', 'coords.lng');
});
