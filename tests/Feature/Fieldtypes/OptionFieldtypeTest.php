<?php

declare(strict_types=1);

use App\Fieldtypes\Option;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for App\Fieldtypes\Option — the Gravity Forms option-field analog.
 *
 * Covers the server-side `process()` normalization (authoritative line-total
 * recomputation for single drop-down / radio and multiple checkbox modes), the
 * `extraRenderableFieldData()` currency formatting consumed by
 * `option.antlers.html`, and the enable flags that make the fieldtype
 * selectable in form blueprints.
 *
 * Mutation: 100% on App\Fieldtypes\Option (178 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/OptionFieldtypeTest.php
 */

/**
 * Build an Option fieldtype bound to a field with the given config.
 *
 * @param  array<string, mixed>  $config
 */
function optionFieldtypeForConfig(array $config): Option
{
    $field = new Field('rate_option', ['type' => 'option', ...$config]);

    /** @var Option $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    return $fieldtype;
}

it('is selectable in forms and uses the option component', function () {
    $fieldtype = optionFieldtypeForConfig(['input_type' => 'select', 'options' => [['key' => 'a', 'price' => 10]]]);

    expect($fieldtype->selectableInForms())->toBeTrue();
    expect($fieldtype->component())->toBe('option');
});

it('uses the selected option price when processing a single selection', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'select',
        'options' => [
            ['key' => 'first', 'label' => 'First Choice', 'price' => 42.13],
            ['key' => 'second', 'label' => 'Second Choice', 'price' => 13.42],
        ],
    ]);

    $out = $fieldtype->process(['key' => 'second', 'line_total' => '0']);

    expect(data_get($out, 'input_type'))->toBe('select');
    expect(data_get($out, 'key'))->toBe('second');
    expect(data_get($out, 'label'))->toBe('Second Choice');
    expect(data_get($out, 'price'))->toBe(13.42);
    expect(data_get($out, 'line_total'))->toBe(13.42);
});

it('recomputes and ignores a tampered line total for a single selection', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'radio',
        'options' => [['key' => 'a', 'price' => 25]],
    ]);

    // A tampered hidden line_total must not survive — the server recomputes.
    $out = $fieldtype->process(['key' => 'a', 'line_total' => '999999']);

    expect(data_get($out, 'input_type'))->toBe('radio');
    expect(data_get($out, 'line_total'))->toBe(25.0);
});

it('returns null for no or unknown single selection', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'select',
        'options' => [['key' => 'a', 'price' => 5]],
    ]);

    expect($fieldtype->process(['key' => '']))->toBeNull();
    expect($fieldtype->process(['key' => 'ghost']))->toBeNull();
    expect($fieldtype->process([]))->toBeNull();
    expect($fieldtype->process(null))->toBeNull();
});

it('supports negative prices for a single selection', function () {
    // Gravity Forms permits negative option prices (credits / discounts).
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'select',
        'options' => [['key' => 'credit', 'price' => -150]],
    ]);

    $out = $fieldtype->process(['key' => 'credit']);

    expect(data_get($out, 'line_total'))->toBe(-150.0);
});

it('sums selected checkbox prices when processing multiple selections', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'checkbox',
        'options' => [
            ['key' => 'a', 'label' => 'A', 'price' => 25],
            ['key' => 'b', 'label' => 'B', 'price' => 50],
            ['key' => 'c', 'label' => 'C', 'price' => 150],
        ],
    ]);

    $out = $fieldtype->process(['keys' => ['a', 'c'], 'line_total' => '0']);

    expect(data_get($out, 'input_type'))->toBe('checkbox');
    expect(data_get($out, 'keys'))->toBe(['a', 'c']);
    expect(data_get($out, 'line_total'))->toBe(175.0);
    expect(data_get($out, 'selections.0.label'))->toBe('A');
    expect(data_get($out, 'selections.1.price'))->toBe(150.0);
});

it('skips unknown keys when processing multiple selections', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'checkbox',
        'options' => [['key' => 'a', 'price' => 25]],
    ]);

    // A tampered key is ignored; the known one still contributes.
    $out = $fieldtype->process(['keys' => ['a', 'ghost']]);

    expect(data_get($out, 'keys'))->toBe(['a']);
    expect(data_get($out, 'line_total'))->toBe(25.0);
});

it('returns null when nothing is selected for multiple selections', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'checkbox',
        'options' => [['key' => 'a', 'price' => 25]],
    ]);

    expect($fieldtype->process(['keys' => []]))->toBeNull();
    expect($fieldtype->process(['keys' => ['ghost']]))->toBeNull();
    expect($fieldtype->process(['keys' => 'a']))->toBeNull();
});

it('maps options with a label fallback in extra renderable field data', function () {
    $fieldtype = optionFieldtypeForConfig([
        'input_type' => 'select',
        'options' => [
            ['key' => 'with_label', 'label' => 'Pretty Label', 'price' => 42.13],
            ['key' => 'no_label', 'price' => -25],
        ],
    ]);

    $options = $fieldtype->extraRenderableFieldData()['option_choices'];

    expect(data_get($options, '0.label'))->toBe('Pretty Label');
    expect(data_get($options, '0.formatted_price'))->toBe('$42.13');
    expect(data_get($options, '0.price'))->toBe(42.13);

    // Missing label falls back to the option key; negative prices keep the
    // sign outside the currency symbol.
    expect(data_get($options, '1.label'))->toBe('no_label');
    expect(data_get($options, '1.formatted_price'))->toBe('-$25.00');
});
