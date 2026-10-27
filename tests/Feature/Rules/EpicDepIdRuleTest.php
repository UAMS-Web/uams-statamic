<?php

declare(strict_types=1);

use App\Rules\EpicDepId;
use Tests\TestCase;

uses(TestCase::class);

covers(EpicDepId::class);

/**
 * Behavior of the App\Rules\EpicDepId validation rule: accept integer-only
 * (digits-only) UAMS Health Epic Department (DEP) ID strings and reject any
 * value whose content contains non-digit characters (letters, punctuation,
 * surrounding spaces, or a trailing newline/carriage return). Leading zeros
 * are preserved (values are
 * strings, never coerced to int).
 *
 * Empty, null, and whitespace-only values are skipped by the format rule —
 * Laravel does not run non-implicit rules when `trim($value) === ''`, so these
 * are deferred to the `required` rule (which rejects whitespace-only input).
 * This mirrors every sibling identifier rule (e.g. RxCui). The dedicated
 * deferral test below proves empty/whitespace are rejected once composed with
 * `required`.
 *
 * The registered `epic_dep_id` string alias — which backs the `epic_dep_id`
 * identifier preset (UAMS-Web/uams-statamic-archive#960) — mirrors the object rule.
 *
 * @command  php artisan test --compact tests/Feature/Rules/EpicDepIdRuleTest.php
 */
it('accepts digit-only DEP IDs and rejects values with non-digit content', function (mixed $value, bool $shouldPass) {
    $validator = validator(['dep' => $value], ['dep' => [new EpicDepId]]);

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'single digit' => ['1', true],
    'multiple digits' => ['123', true],
    'long id' => ['123456789', true],
    'leading zeros preserved' => ['00123', true],
    'integer value coerced to string' => [123, true],
    'bare zero (falsy-skip path)' => ['0', true],
    'double zero (validated path)' => ['00', true],
    'leading space fails' => [' 123', false],
    'trailing space fails' => ['123 ', false],
    'internal space fails' => ['12 34', false],
    'trailing letter fails' => ['12a', false],
    'all letters fail' => ['abc', false],
    'hyphenated fails' => ['123-456', false],
    'negative sign fails' => ['-123', false],
    'plus sign fails' => ['+123', false],
    'decimal fails' => ['12.34', false],
    'scientific notation fails' => ['1e5', false],
    'trailing newline fails' => ["123\n", false],
    'trailing crlf fails' => ["123\r\n", false],
    'trailing carriage return fails' => ["123\r", false],
    'array value fails' => [['123'], false],
]);

it('defers empty, null, and whitespace-only values to the required rule', function (mixed $value) {
    // The format rule alone skips these (non-implicit rule + empty/blank input)...
    expect(validator(['dep' => $value], ['dep' => [new EpicDepId]])->passes())->toBeTrue();

    // ...but once composed with `required`, empty/whitespace are rejected.
    expect(validator(['dep' => $value], ['dep' => ['required', new EpicDepId]])->passes())->toBeFalse();
})->with([
    'empty string' => [''],
    'null' => [null],
    'whitespace only' => [' '],
]);

it('names the DEP ID constraint in the failure message', function () {
    $validator = validator(['dep' => '12a'], ['dep' => [new EpicDepId]]);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('dep'))->toBe(
        __('statamic::validation.epic_dep_id'),
    );
    expect($validator->errors()->first('dep'))->toContain('Epic Department');
});

it('mirrors the object rule through the registered epic_dep_id string alias', function (mixed $value, bool $shouldPass) {
    $validator = validator(['dep' => $value], ['dep' => ['epic_dep_id']]);

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'rejects non-digit value' => ['12a', false],
    'accepts a digit-only id' => ['10502', true],
]);
