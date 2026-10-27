<?php

declare(strict_types=1);

use App\Rules\BaseRules\BaseValidationRule;

covers(BaseValidationRule::class);

/**
 * Behavior of the shared `BaseValidationRule` contract, through a minimal
 * concrete subclass that records what it is handed and fails on the literal
 * string `bad`: empty values are skipped before the subclass runs, an array fails
 * with the default message without reaching the subclass, any other value reaches
 * the subclass as a string, and `isValid()` reports the subclass verdict without
 * calling the caller's fail closure, agreeing with `validate()` on every value,
 * including an array that preparation rejects (UAMS-Web/uams-statamic-archive#2946).
 *
 * @command  php artisan test --compact tests/Unit/Rules/BaseValidationRuleTest.php
 */
function baseValidationRuleProbe(): BaseValidationRule
{
    return new class extends BaseValidationRule
    {
        /**
         * Values the subclass received, in call order.
         *
         * @var list<array{attribute: string, value: string}>
         */
        public array $received = [];

        protected function validateValue(string $attribute, string $value, Closure $fail): void
        {
            $this->received[] = ['attribute' => $attribute, 'value' => $value];

            if ($value === 'bad') {
                $fail('The probe rejected bad.');
            }
        }
    };
}

/**
 * Read the values a probe rule's subclass hook received.
 *
 * @return list<array{attribute: string, value: string}>
 */
function baseValidationRuleReceived(BaseValidationRule $rule): array
{
    /** @var list<array{attribute: string, value: string}> $received */
    $received = (new ReflectionProperty($rule, 'received'))->getValue($rule);

    return $received;
}

it('skips an empty value without calling the subclass or failing', function (mixed $value): void {
    $rule = baseValidationRuleProbe();
    $messages = [];

    $rule->validate('field', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([])
        ->and(baseValidationRuleReceived($rule))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'string zero' => ['0'],
    'integer zero' => [0],
    'float zero' => [0.0],
    'false' => [false],
    'empty array' => [[]],
]);

it('fails a non-empty array with the default message and does not call the subclass', function (array $value): void {
    $rule = baseValidationRuleProbe();
    $messages = [];

    $rule->validate('field', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe(['The value must be a string, not an array.'])
        ->and(baseValidationRuleReceived($rule))->toBe([]);
})->with([
    'list' => [['a']],
    'map' => [['key' => 'bad']],
]);

it('hands any other value to the subclass as a string', function (mixed $value, string $expected): void {
    $rule = baseValidationRuleProbe();
    $messages = [];

    $rule->validate('field', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe([])
        ->and(baseValidationRuleReceived($rule))->toBe([['attribute' => 'field', 'value' => $expected]]);
})->with([
    'string' => ['hello', 'hello'],
    'whitespace string' => [' ', ' '],
    'integer' => [42, '42'],
    'negative integer' => [-7, '-7'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
]);

it('passes the subclass failure through to the caller', function (): void {
    $rule = baseValidationRuleProbe();
    $messages = [];

    $rule->validate('field', 'bad', function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($messages)->toBe(['The probe rejected bad.'])
        ->and(baseValidationRuleReceived($rule))->toBe([['attribute' => 'field', 'value' => 'bad']]);
});

it('reports an empty value as valid without calling the subclass', function (mixed $value): void {
    $rule = baseValidationRuleProbe();

    expect($rule->isValid($value))->toBeTrue()
        ->and(baseValidationRuleReceived($rule))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'string zero' => ['0'],
    'false' => [false],
]);

it('reports the subclass verdict for a non-empty value', function (mixed $value, bool $expected, string $received): void {
    $rule = baseValidationRuleProbe();

    expect($rule->isValid($value))->toBe($expected)
        ->and(baseValidationRuleReceived($rule))->toBe([['attribute' => '', 'value' => $received]]);
})->with([
    'accepted string' => ['good', true, 'good'],
    'rejected string' => ['bad', false, 'bad'],
    'accepted integer' => [7, true, '7'],
]);

it('reports a non-empty array as invalid without calling the subclass', function (array $value): void {
    $rule = baseValidationRuleProbe();

    expect($rule->isValid($value))->toBeFalse()
        ->and(baseValidationRuleReceived($rule))->toBe([]);
})->with([
    'list' => [['a']],
    'map' => [['key' => 'good']],
]);

it('agrees with validate() on whether a value passes', function (mixed $value, bool $expected): void {
    $messages = [];
    baseValidationRuleProbe()->validate('field', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    // Both verdicts are pinned to the expectation, so a dataset row cannot pass by
    // the two methods agreeing on the wrong answer.
    expect($messages === [])->toBe($expected)
        ->and(baseValidationRuleProbe()->isValid($value))->toBe($expected);
})->with([
    'list array' => [['a'], false],
    'map array' => [['key' => 'good'], false],
    'integer' => [42, true],
    'float' => [1.5, true],
    'true' => [true, true],
    'accepted string' => ['good', true],
    'rejected string' => ['bad', false],
    'null' => [null, true],
    'empty string' => ['', true],
]);
