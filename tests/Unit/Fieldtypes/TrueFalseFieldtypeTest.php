<?php

declare(strict_types=1);

use App\Fieldtypes\TrueFalse;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Behavior of the TrueFalse fieldtype (app/Fieldtypes/TrueFalse.php): two fixed
 * boolean options with optional custom labels and reversed order, values
 * normalized to a boolean or null on save and on display, and a config panel
 * that offers labels, clearable, default and order but no options list.
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/TrueFalseFieldtypeTest.php
 */
uses(TestCase::class);

/**
 * A TrueFalse fieldtype bound to a field with the given config.
 *
 * @param  array<string, mixed>  $config
 */
function makeTrueFalse(array $config = []): TrueFalse
{
    $fieldtype = new TrueFalse;
    $fieldtype->setField(new Field('is_enabled', array_merge(['type' => 'true_false'], $config)));

    return $fieldtype;
}

/**
 * The fieldtype's options, read through its protected getOptions().
 *
 * @param  array<string, mixed>  $config
 */
function trueFalseOptions(array $config = []): mixed
{
    return (new ReflectionMethod(TrueFalse::class, 'getOptions'))->invoke(makeTrueFalse($config));
}

it('normalizes a value to a boolean or null on save and on display', function (mixed $input, ?bool $expected): void {
    $fieldtype = makeTrueFalse();

    expect($fieldtype->process($input))->toBe($expected)
        ->and($fieldtype->preProcess($input))->toBe($expected);
})->with([
    'null stays null (cleared)' => [null, null],
    'boolean true' => [true, true],
    'string true' => ['true', true],
    'boolean false' => [false, false],
    'string false' => ['false', false],
    'truthy string' => ['yes', true],
    'truthy integer' => [1, true],
    'falsy integer' => [0, false],
    'falsy empty string' => ['', false],
    'falsy string zero' => ['0', false],
]);

it('offers True then False as boolean options by default', function (): void {
    expect(trueFalseOptions())->toBe([
        ['value' => true, 'label' => 'True'],
        ['value' => false, 'label' => 'False'],
    ]);
});

it('uses custom labels when they are set', function (): void {
    expect(trueFalseOptions(['label_true' => 'Yes', 'label_false' => 'No']))->toBe([
        ['value' => true, 'label' => 'Yes'],
        ['value' => false, 'label' => 'No'],
    ]);
});

it('accepts a scalar or Stringable label and casts it to a string', function (): void {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'Off';
        }
    };

    expect(trueFalseOptions(['label_true' => 7, 'label_false' => $stringable]))->toBe([
        ['value' => true, 'label' => '7'],
        ['value' => false, 'label' => 'Off'],
    ])->and(trueFalseOptions(['label_true' => $stringable, 'label_false' => 0.5]))->toBe([
        ['value' => true, 'label' => 'Off'],
        ['value' => false, 'label' => '0.5'],
    ]);
});

it('keeps the default order when the fieldtype has no field', function (): void {
    $options = (new ReflectionMethod(TrueFalse::class, 'getOptions'))->invoke(new TrueFalse);

    expect($options)->toBe([
        ['value' => true, 'label' => 'True'],
        ['value' => false, 'label' => 'False'],
    ]);
});

it('falls back to the default label for an empty or non-stringable custom label', function (mixed $label): void {
    expect(trueFalseOptions(['label_true' => $label, 'label_false' => $label]))->toBe([
        ['value' => true, 'label' => 'True'],
        ['value' => false, 'label' => 'False'],
    ]);
})->with([
    'empty string' => [''],
    'string zero' => ['0'],
    'null' => [null],
    'array' => [['Yes']],
    'plain object' => [new stdClass],
]);

it('lists False first when reverse order is enabled', function (): void {
    expect(trueFalseOptions(['reverse_order' => true, 'label_true' => 'On', 'label_false' => 'Off']))->toBe([
        ['value' => false, 'label' => 'Off'],
        ['value' => true, 'label' => 'On'],
    ]);
});

it('keeps the default order when reverse order is disabled', function (): void {
    expect(trueFalseOptions(['reverse_order' => false]))->toBe([
        ['value' => true, 'label' => 'True'],
        ['value' => false, 'label' => 'False'],
    ]);
});

it('exposes its component, title and icon', function (): void {
    $fieldtype = new TrueFalse;

    expect($fieldtype->component())->toBe('true_false')
        ->and($fieldtype->title())->toBe('True/False')
        ->and($fieldtype->icon())->toBe('fieldtype-toggle');
});

it('configures labels, clearable, default and order, and no options list', function (): void {
    $items = (new ReflectionMethod(TrueFalse::class, 'configFieldItems'))->invoke(new TrueFalse);

    expect($items)->toBe([
        [
            'display' => __('Options'),
            'fields' => [
                'label_true' => [
                    'display' => 'Custom Label for True Option',
                    'instructions' => 'Override the default label for the "True" option.',
                    'type' => 'text',
                    'default' => null,
                ],
                'label_false' => [
                    'display' => 'Custom Label for False Option',
                    'instructions' => 'Override the default label for the "False" option.',
                    'type' => 'text',
                    'default' => null,
                ],
                'clearable' => [
                    'display' => __('Clearable'),
                    'instructions' => __('statamic::fieldtypes.select.config.clearable'),
                    'type' => 'toggle',
                    'default' => false,
                ],
                'default' => [
                    'display' => __('Default Value'),
                    'instructions' => __('statamic::messages.fields_default_instructions'),
                    'type' => 'toggle',
                    'default' => false,
                ],
                'reverse_order' => [
                    'display' => 'Reverse Order',
                    'instructions' => 'Reverse the order of the options (False/True instead of True/False).',
                    'type' => 'toggle',
                    'default' => false,
                ],
            ],
        ],
    ]);
});
