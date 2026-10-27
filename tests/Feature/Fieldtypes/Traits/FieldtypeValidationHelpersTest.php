<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\FieldtypeValidationHelpers;
use Tests\Support\Doubles\FieldtypeValidationHelpersHarness;
use Tests\TestCase;

covers(FieldtypeValidationHelpers::class);

uses(TestCase::class);

/**
 * Unit tests for the FieldtypeValidationHelpers trait (and the SometimesValidationRules
 * trait it composes): exploding blueprint `validate` lists, detecting the `required`
 * flag/rule, and `sometimes`-first ordering for keyed and nested rule sets.
 *
 * Mutation: 100% on App\Fieldtypes\Traits\FieldtypeValidationHelpers (5 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/FieldtypeValidationHelpersTest.php
 */
function fieldtypeValidationHelpersHarness(): FieldtypeValidationHelpersHarness
{
    return new FieldtypeValidationHelpersHarness;
}

it('explodes statamic validation rules from an array', function () {
    $h = fieldtypeValidationHelpersHarness();

    expect($h->explodeStatamicValidationRules(['required', 'sometimes']))->toBe(['required', 'sometimes']);
});

it('explodes statamic validation rules from a pipe string', function () {
    $h = fieldtypeValidationHelpersHarness();

    expect($h->explodeStatamicValidationRules('required|sometimes'))->toBe(['required', 'sometimes']);
});

it('explodes the blueprint validate rules from config', function () {
    $h = fieldtypeValidationHelpersHarness();

    expect($h->explodedBlueprintValidateRulesFromConfig(['validate' => ['required', 'sometimes']]))
        ->toBe(['required', 'sometimes']);
});

it('marks the field required from the required flag', function () {
    expect(fieldtypeValidationHelpersHarness()->blueprintConfigMarksFieldRequired(['required' => true]))->toBeTrue();
});

it('marks the field required from a validate required list', function () {
    expect(fieldtypeValidationHelpersHarness()->blueprintConfigMarksFieldRequired(['validate' => ['required']]))->toBeTrue();
});

it('marks the field required from a validate required string', function () {
    expect(fieldtypeValidationHelpersHarness()->blueprintConfigMarksFieldRequired(['validate' => 'required']))->toBeTrue();
});

it('does not mark the field required when neither is present', function () {
    expect(fieldtypeValidationHelpersHarness()->blueprintConfigMarksFieldRequired([]))->toBeFalse();
});

it('does not mark the field required for sometimes only', function () {
    expect(fieldtypeValidationHelpersHarness()->blueprintConfigMarksFieldRequired(['validate' => ['sometimes']]))->toBeFalse();
});

it('reports whether an exploded validate rule list contains a rule', function () {
    $h = fieldtypeValidationHelpersHarness();

    expect($h->explodedValidateRulesContain(['required', 'sometimes'], 'sometimes'))->toBeTrue();
    expect($h->explodedValidateRulesContain(['required'], 'sometimes'))->toBeFalse();
});

it('skips conditionally prepending when sometimes is absent', function () {
    $h = fieldtypeValidationHelpersHarness();
    $extra = ['a.b' => ['required', 'string']];

    expect($h->conditionallyPrependSometimesToKeyedRuleSets($extra, ['required']))->toBe($extra);
});

it('conditionally prepends sometimes when it is present', function () {
    $h = fieldtypeValidationHelpersHarness();
    $extra = ['a.b' => ['required', 'string']];

    $out = $h->conditionallyPrependSometimesToKeyedRuleSets($extra, ['required', 'sometimes']);

    // The rule set is keyed by the literal dotted key 'a.b'; pass data_get() an
    // array of segments so it does not explode the dot into nested traversal.
    expect(data_get($out, ['a.b', 0]))->toBe('sometimes');
    expect(data_get($out, ['a.b']))->toContain('required');
});

it('orders sometimes first when normalizing blueprint validate', function () {
    $h = fieldtypeValidationHelpersHarness();

    expect($h->normalizeBlueprintValidateForSometimes(['required', 'sometimes', 'min:1']))
        ->toBe(['sometimes', 'required', 'min:1']);
});

it('prepends sometimes to keyed rule sets', function () {
    $h = fieldtypeValidationHelpersHarness();

    $out = $h->prependSometimesToKeyedRuleSets(['x' => ['required', 'string']]);

    expect(data_get($out, 'x'))->toBe(['sometimes', 'required', 'string']);
});
