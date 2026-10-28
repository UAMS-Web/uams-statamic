<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\ProcessesInputValue;
use Statamic\Fields\Field;
use Tests\Support\Doubles\ProcessesInputValueHarness;
use Tests\TestCase;

covers(ProcessesInputValue::class);

uses(TestCase::class);

/**
 * Behavior of the ProcessesInputValue trait (app/Fieldtypes/Traits/ProcessesInputValue.php):
 * a `pattern` config becomes a regex rule with its slashes trimmed; normalizing and
 * display formatting pass the value through; and process() and
 * preProcessValidatable() hand the value to the input-mask storage when the
 * field's (default-merged) config enables a mask, and to normalizeValue()
 * otherwise.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/ProcessesInputValueTest.php
 */

/**
 * A harness bound to a field with the given config.
 *
 * @param  array<string, mixed>  $config
 */
function processesInputValueHarness(array $config = []): ProcessesInputValueHarness
{
    $fieldtype = new ProcessesInputValueHarness;
    $fieldtype->setField(new Field('amount', array_merge(['type' => 'text'], $config)));

    return $fieldtype;
}

it('turns a pattern into a regex rule with its delimiters trimmed', function (mixed $pattern, array $expected): void {
    expect(processesInputValueHarness()->getInputValidationRules('x', ['pattern' => $pattern]))->toBe($expected);
})->with([
    'bare pattern' => ['^[0-9]+$', ['regex:/^[0-9]+$/']],
    'delimited pattern' => ['/^[a-z]+$/', ['regex:/^[a-z]+$/']],
    'numeric pattern' => [42, ['regex:/42/']],
    'non-scalar pattern' => [['x'], ['regex://']],
]);

it('adds no rule without a pattern', function (array $config): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(processesInputValueHarness()->getInputValidationRules('x', $typedConfig))->toBe([]);
})->with([
    'absent' => [[]],
    'empty string' => [['pattern' => '']],
    'null' => [['pattern' => null]],
]);

it('passes a value through unchanged when normalizing or formatting for display', function (): void {
    $fieldtype = processesInputValueHarness();
    $value = ['a' => 1];

    expect($fieldtype->passThrough($value, ['x' => 1]))->toBe($value)
        ->and($fieldtype->formatValueForDisplay($value, ['x' => 1]))->toBe($value);
});

it('normalizes the value with the default-merged config when no mask is enabled', function (): void {
    $fieldtype = processesInputValueHarness();

    expect($fieldtype->process('42'))->toBe(['normalized' => '42', 'defaulted' => true])
        ->and($fieldtype->preProcessValidatable('42'))->toBe(['normalized' => '42', 'defaulted' => true]);
});

it('stores the configured half of a masked value when a mask is enabled', function (): void {
    $fieldtype = processesInputValueHarness([
        'input_mask' => true,
        'input_mask_class' => 'MaskedNumber',
        'input_mask_store_unmasked' => true,
    ]);
    $value = ['masked' => '1,234.50', 'unmasked' => '1234.5'];

    expect($fieldtype->process($value))->toBe(1234.5)
        ->and($fieldtype->preProcessValidatable($value))->toBe(1234.5);
});
