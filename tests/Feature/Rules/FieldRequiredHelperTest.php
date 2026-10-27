<?php

declare(strict_types=1);

use App\Rules\FieldRequiredHelper;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of App\Rules\FieldRequiredHelper's "is field required?" helpers shared
 * by the Replicator rules: configHasRequiredRule detects any required* prefix in a
 * field's validate config, and fieldIsRequiredForRequest reports whether a field is
 * required for the current request.
 *
 * It also covers sometimesConditionMet, which reads a field's conditions with
 * ConditionalLogicEvaluator (UAMS-Web/uams-statamic-archive#2890): every form Statamic saves, every condition key,
 * and the prefix that scopes a nested replicator's condition to its own set. The
 * Replicator rule tests (ReplicatorMinSetsTest, ReplicatorRequireAtLeastOneEnabledSetTest)
 * exercise it through the rules as well.
 *
 * @command  php artisan test --compact tests/Feature/Rules/FieldRequiredHelperTest.php
 */
it('detects whether a validate config contains a required rule', function (array $validate, bool $expected) {
    expect(FieldRequiredHelper::configHasRequiredRule(['validate' => $validate]))->toBe($expected);
})->with([
    'required' => [['required'], true],
    'required_if' => [['required_if:other,value'], true],
    'required_unless' => [['required_unless:other,value'], true],
    'required_if_accepted' => [['required_if_accepted:other'], true],
    'required_if_declined' => [['required_if_declined:other'], true],
    'required_with' => [['required_with:foo'], true],
    'required_with_all' => [['required_with_all:foo,bar'], true],
    'required_without' => [['required_without:foo'], true],
    'required_without_all' => [['required_without_all:foo,bar'], true],
    'required_array' => [['required_array:keys'], true],
    'sometimes then required' => [['sometimes', 'required'], true],
    'nullable only' => [['nullable'], false],
    'min only' => [['min:1'], false],
    'empty rules' => [[], false],
]);

it('reports a field required when it has a required rule and is empty', function () {
    expect(FieldRequiredHelper::fieldIsRequiredForRequest(
        ['validate' => ['required']],
        ['blocks' => []],
        'blocks'
    ))->toBeTrue();
});

it('reports a field not required when it has no required rule', function () {
    expect(FieldRequiredHelper::fieldIsRequiredForRequest(
        ['validate' => ['nullable']],
        ['blocks' => []],
        'blocks'
    ))->toBeFalse();
});

it('reads every condition form Statamic saves on a field (#2890)', function (mixed $if, mixed $value, bool $expected) {
    // The rows of UAMS-Web/uams-statamic-archive#2890's table. The first four were read correctly before; the last four
    // were compared as literal strings and failed.
    expect(FieldRequiredHelper::sometimesConditionMet(['if' => ['f' => $if]], ['f' => $value], 'target'))
        ->toBe($expected);
})->with([
    'equals, matching' => ['equals a', 'a', true],
    'equals, not matching' => ['equals a', 'b', false],
    'not empty' => ['not empty', 'x', true],
    'bare value' => ['a', 'a', true],
    'list of conditions on one field' => [['equals a', 'not empty'], 'a', true],
    'numeric range' => [['>= 18', '< 65'], '40', true],
    'contains' => ['contains a', 'xay', true],
    'not' => ['not b', 'a', true],
    'list of conditions, one failing' => [['equals a', 'equals b'], 'a', false],
    'numeric range, outside' => [['>= 18', '< 65'], '70', false],
]);

it('gates the check by each condition key the way it gates visibility (#2890)', function (string $key, string $f, string $g, bool $expected) {
    $config = [$key => ['f' => 'equals a', 'g' => 'equals b']];

    expect(FieldRequiredHelper::sometimesConditionMet($config, ['f' => $f, 'g' => $g], 'target'))->toBe($expected);
})->with([
    'if, both met' => ['if', 'a', 'b', true],
    'if, one met' => ['if', 'a', 'x', false],
    'if_any, one met' => ['if_any', 'a', 'x', true],
    'if_any, none met' => ['if_any', 'x', 'x', false],
    'show_when, both met' => ['show_when', 'a', 'b', true],
    'show_when, one met' => ['show_when', 'a', 'x', false],
    'show_when_any, one met' => ['show_when_any', 'a', 'x', true],
    'show_when_any, none met' => ['show_when_any', 'x', 'x', false],
    'unless, both met' => ['unless', 'a', 'b', false],
    'unless, one met' => ['unless', 'a', 'x', true],
    'unless_any, one met' => ['unless_any', 'a', 'x', false],
    'unless_any, none met' => ['unless_any', 'x', 'x', true],
    'hide_when, both met' => ['hide_when', 'a', 'b', false],
    'hide_when, none met' => ['hide_when', 'x', 'x', true],
]);

it('reads a nested replicator\'s condition against its own set\'s values', function () {
    // The attribute sits in set 0 of `content`; `f` there is `a`, while a top-level `f` is `b`.
    $data = ['f' => 'b', 'content' => [['f' => 'a', 'blocks' => []]]];

    expect(FieldRequiredHelper::sometimesConditionMet(['if' => ['f' => 'equals a']], $data, 'content.0.blocks'))->toBeTrue()
        ->and(FieldRequiredHelper::sometimesConditionMet(['if' => ['f' => 'equals b']], $data, 'content.0.blocks'))->toBeFalse();
});

it('treats a field with no condition keys as unconditioned', function () {
    expect(FieldRequiredHelper::sometimesConditionMet(['validate' => ['sometimes']], [], 'target'))->toBeTrue();
});

it('does not require a sometimes replicator hidden by an empty group (#2895)', function (string $key, bool $required) {
    // The gate reads an empty group as the public page does, so a field the page hides is
    // not required, and a field it shows still is.
    $config = ['validate' => ['sometimes', 'required'], $key => []];

    expect(FieldRequiredHelper::fieldIsRequiredForRequest($config, ['f' => 'a', 'blocks' => []], 'blocks'))->toBe($required);
})->with([
    'if_any hides it' => ['if_any', false],
    'unless hides it' => ['unless', false],
    'if shows it' => ['if', true],
    'unless_any shows it' => ['unless_any', true],
]);

it('requires a sometimes replicator behind a list-valued condition only while it is met', function () {
    // The defect's effect: a list-valued condition read as unmet made the field look not
    // required, so ReplicatorMinSets and ReplicatorRequireAtLeastOneEnabledSet skipped it.
    $config = ['validate' => ['sometimes', 'required'], 'if' => ['f' => ['equals a', 'not empty']]];

    expect(FieldRequiredHelper::fieldIsRequiredForRequest($config, ['f' => 'a', 'blocks' => []], 'blocks'))->toBeTrue()
        ->and(FieldRequiredHelper::fieldIsRequiredForRequest($config, ['f' => 'b', 'blocks' => []], 'blocks'))->toBeFalse();
});

it('judges the required rule against an empty value, whatever the field holds now', function () {
    // The question is whether the field may be left empty, so a filled field with a
    // required rule is still reported required.
    expect(FieldRequiredHelper::fieldIsRequiredForRequest(
        ['validate' => ['required']],
        ['blocks' => [['type' => 'text', 'enabled' => true]]],
        'blocks'
    ))->toBeTrue();
});

it('keeps only the required rules, each once', function (array|string $validate, array $expected) {
    expect(FieldRequiredHelper::getRequiredRulesOnly(['validate' => $validate]))->toBe($expected);
})->with([
    'required among other rules' => ['nullable|required|max:5', ['required']],
    'a required rule with parameters' => [['nullable', 'required_if:other,1', 'max:5'], ['required_if:other,1']],
    'a rule several prefixes match' => [['required_with_all:a,b'], ['required_with_all:a,b']],
    'two required rules' => [['required_without:a', 'min:1', 'required_array:keys'], ['required_without:a', 'required_array:keys']],
    'no required rule' => [['sometimes', 'min:1'], []],
]);

it('detects the sometimes rule only as the literal rule string', function (array|string $validate, bool $expected) {
    expect(FieldRequiredHelper::validationHasSometimes(['validate' => $validate]))->toBe($expected);
})->with([
    'array config' => [['sometimes', 'required'], true],
    'string config' => ['required| sometimes', true],
    'absent' => [['required'], false],
    'a different case' => [['Sometimes'], false],
    'a boolean entry' => [[true], false],
]);

it('reads the validate config as a list of rules: split and trimmed from a string, kept from a list, empty otherwise', function (mixed $validate, array $expected) {
    expect(FieldRequiredHelper::getValidateRules(['validate' => $validate]))->toBe($expected);
})->with([
    'a string' => ['required | max:5', ['required', 'max:5']],
    'a list' => [['required', 'max:5'], ['required', 'max:5']],
    'a number' => [5, []],
    'null' => [null, []],
    'a boolean' => [true, []],
]);

it('reads no rules when the field has no validate config', function () {
    expect(FieldRequiredHelper::getValidateRules([]))->toBe([]);
});
