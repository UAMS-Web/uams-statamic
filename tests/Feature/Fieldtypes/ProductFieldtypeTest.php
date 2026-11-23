<?php

declare(strict_types=1);

use App\Fieldtypes\Product;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for App\Fieldtypes\Product — the Gravity Forms product-field analog.
 *
 * Covers the server-side `process()` normalization (authoritative line-total
 * recomputation for single and multiple modes), the `extraRenderableFieldData()`
 * currency formatting consumed by `product.antlers.html`, and the enable flags
 * that make the fieldtype selectable in form blueprints.
 *
 * Mutation: 100% on App\Fieldtypes\Product (205 mutants, without --covered-only, Windows 11, #956).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ProductFieldtypeTest.php
 */

/**
 * Build a Product fieldtype bound to a field with the given config.
 *
 * @param  array<string, mixed>  $config
 */
function productFieldtype(array $config): Product
{
    $field = new Field('order_item', ['type' => 'product', ...$config]);

    /** @var Product $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    return $fieldtype;
}

it('is selectable in forms and uses the product component', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    expect($fieldtype->selectableInForms())->toBeTrue();
    expect($fieldtype->component())->toBe('product');
});

it('computes the single line total from quantity', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 19.99]);

    $out = $fieldtype->process(['quantity' => '3', 'line_total' => '0']);

    expect(data_get($out, 'selection_type'))->toBe('single');
    expect(data_get($out, 'quantity'))->toBe(3);
    expect(data_get($out, 'price'))->toBe(19.99);
    expect(data_get($out, 'line_total'))->toBe(59.97);
});

it('recomputes and ignores a tampered single line total', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    // A tampered hidden line_total must not survive — the server recomputes.
    $out = $fieldtype->process(['quantity' => '2', 'line_total' => '999999']);

    expect(data_get($out, 'line_total'))->toBe(20.0);
});

it('orders one when the single quantity field is disabled', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'single',
        'price' => 5,
        'disable_quantity' => true,
    ]);

    $out = $fieldtype->process(['quantity' => '0']);

    expect(data_get($out, 'quantity'))->toBe(1);
    expect(data_get($out, 'line_total'))->toBe(5.0);
});

it('returns null for a single product when nothing ordered', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    expect($fieldtype->process(['quantity' => '0']))->toBeNull();
    expect($fieldtype->process([]))->toBeNull();
    expect($fieldtype->process(null))->toBeNull();
});

it('supports negative single prices', function () {
    // Gravity Forms permits negative product prices (credits / discounts) —
    // see the IDHI e-Link quote form.
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => -2500]);

    $out = $fieldtype->process(['quantity' => '2']);

    expect(data_get($out, 'line_total'))->toBe(-5000.0);
});

it('uses the selected option price for multiple products', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [
            ['key' => 'first', 'label' => 'First Choice', 'price' => 42.13],
            ['key' => 'second', 'label' => 'Second Choice', 'price' => 13.42],
        ],
    ]);

    $out = $fieldtype->process(['key' => 'second', 'quantity' => '2']);

    expect(data_get($out, 'selection_type'))->toBe('multiple');
    expect(data_get($out, 'key'))->toBe('second');
    expect(data_get($out, 'label'))->toBe('Second Choice');
    expect(data_get($out, 'price'))->toBe(13.42);
    expect(data_get($out, 'line_total'))->toBe(26.84);
});

it('defaults a missing multiple quantity to one', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => 'a', 'price' => 9.99]],
    ]);

    $out = $fieldtype->process(['key' => 'a']);

    expect(data_get($out, 'quantity'))->toBe(1);
    expect(data_get($out, 'line_total'))->toBe(9.99);
});

it('returns null for no or unknown multiple selection', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => 'a', 'price' => 5]],
    ]);

    expect($fieldtype->process(['key' => '', 'quantity' => '1']))->toBeNull();
    expect($fieldtype->process(['key' => 'ghost', 'quantity' => '1']))->toBeNull();
});

it('formats the single price in extra renderable field data', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 11000]);

    $data = $fieldtype->extraRenderableFieldData();

    expect(data_get($data, 'formatted_price'))->toBe('$11,000.00');
});

it('formats the negative price in extra renderable field data', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => -2500]);

    $data = $fieldtype->extraRenderableFieldData();

    expect(data_get($data, 'formatted_price'))->toBe('-$2,500.00');
});

it('maps options with a label fallback in extra renderable field data', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [
            ['key' => 'with_label', 'label' => 'Pretty Label', 'price' => 42.13],
            ['key' => 'no_label', 'price' => 0.42],
        ],
    ]);

    $data = $fieldtype->extraRenderableFieldData();

    expect(data_get($data, 'product_options.0.label'))->toBe('Pretty Label');
    expect(data_get($data, 'product_options.0.formatted_price'))->toBe('$42.13');
    expect(data_get($data, 'product_options.0.price'))->toBe(42.13);

    // Missing label falls back to the option key — matching how the
    // wordpress-importer maps GF product choices (text → key, no label).
    expect(data_get($data, 'product_options.1.label'))->toBe('no_label');
    expect(data_get($data, 'product_options.1.formatted_price'))->toBe('$0.42');
});

it('is selectable in collection blueprints and uses the shopping-store icon', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    expect($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->icon())->toBe('shopping-store');
});

it('defines the product options config fields', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    /** @var array<int, array<string, mixed>> $items */
    $items = (fn (): array => $this->configFieldItems())->call($fieldtype);

    expect($items)->toBe([
        [
            'display' => __('Product Options'),
            'fields' => [
                'selection_type' => [
                    'display' => __('Selection Type'),
                    'type' => 'radio',
                    'instructions' => __('Select the type of field from the available form fields.'),
                    'options' => [
                        'single' => __('Single Product'),
                        'multiple' => __('Multiple Product Choices'),
                    ],
                    'default' => 'single',
                    'validate' => ['required'],
                ],
                'price' => [
                    'display' => __('Base Price'),
                    'type' => 'currency',
                    'instructions' => 'Enter the base price for this product.',
                    'if' => ['selection_type' => 'single'],
                    'validate' => ['required_if:selection_type,single', 'exclude_unless:selection_type,single'],
                ],
                'disable_quantity' => [
                    'display' => __('Disable Quantity Field'),
                    'type' => 'toggle',
                    'if' => ['selection_type' => 'single'],
                    'default' => false,
                    'validate' => ['required_if:selection_type,single', 'exclude_unless:selection_type,single'],
                ],
                'options' => [
                    'display' => __('Product Choices'),
                    'type' => 'grid',
                    'mode' => 'stacked',
                    'add_row' => 'Add Product',
                    'fields' => [
                        ['handle' => 'key', 'field' => ['type' => 'text', 'display' => 'Key', 'validate' => ['required']]],
                        ['handle' => 'label', 'field' => ['type' => 'text', 'display' => 'Label (Optional)']],
                        ['handle' => 'price', 'field' => [
                            'type' => 'currency',
                            'display' => 'Base Price',
                            'instructions' => 'Enter the base price for this product.',
                            'validate' => ['required'],
                        ]],
                    ],
                    'if' => ['selection_type' => 'multiple'],
                    'validate' => ['required_if:selection_type,multiple', 'exclude_unless:selection_type,multiple'],
                ],
            ],
        ],
    ]);
});

it('preloads the resolved field config for the control panel', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    expect($fieldtype->preload())->toBe(['config' => $fieldtype->config()])
        ->and(data_get($fieldtype->preload(), 'config.price'))->toBe(10);
});

it('formats a missing base price as zero', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single']);

    expect(data_get($fieldtype->extraRenderableFieldData(), 'formatted_price'))->toBe('$0.00');
});

it('formats zero, a fraction below zero, a numeric string and a non-numeric price', function (mixed $price, string $expected) {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => $price]);

    expect(data_get($fieldtype->extraRenderableFieldData(), 'formatted_price'))->toBe($expected);
})->with([
    'zero' => [0, '$0.00'],
    'fraction below zero' => [-0.5, '-$0.50'],
    'numeric string' => ['42.13', '$42.13'],
    'not numeric' => ['abc', '$0.00'],
]);

it('maps option rows with missing, string and non-numeric values', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [
            ['key' => 'string_price', 'price' => '13.42'],
            ['key' => 'no_price'],
            ['key' => 'bad_price', 'price' => 'abc'],
            ['label' => 'No Key', 'price' => 1],
            ['price' => 2],
        ],
    ]);

    expect(data_get($fieldtype->extraRenderableFieldData(), 'product_options'))->toBe([
        ['key' => 'string_price', 'label' => 'string_price', 'price' => 13.42, 'formatted_price' => '$13.42'],
        ['key' => 'no_price', 'label' => 'no_price', 'price' => 0.0, 'formatted_price' => '$0.00'],
        ['key' => 'bad_price', 'label' => 'bad_price', 'price' => 0.0, 'formatted_price' => '$0.00'],
        ['key' => '', 'label' => 'No Key', 'price' => 1.0, 'formatted_price' => '$1.00'],
        ['key' => '', 'label' => '', 'price' => 2.0, 'formatted_price' => '$2.00'],
    ]);
});

it('stores the full single selection with the field handle and display', function () {
    $field = new Field('order_item', ['type' => 'product', 'display' => 'Order Item', 'selection_type' => 'single', 'price' => '1.234']);

    /** @var Product $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    expect($fieldtype->process(['quantity' => '1']))->toBe([
        'selection_type' => 'single',
        'key' => 'order_item',
        'label' => 'Order Item',
        'quantity' => 1,
        'price' => 1.234,
        'line_total' => 1.23,
    ]);
});

it('treats a single quantity that is negative or not numeric as not ordered', function (mixed $quantity) {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 10]);

    expect($fieldtype->process(['quantity' => $quantity]))->toBeNull();
})->with([
    'negative' => ['-2'],
    'not numeric' => ['abc'],
]);

it('prices a single product at zero when its price is missing or not numeric', function (mixed $price) {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => $price]);

    $out = $fieldtype->process(['quantity' => '2']);

    expect(data_get($out, 'price'))->toBe(0.0)
        ->and(data_get($out, 'line_total'))->toBe(0.0);
})->with([
    'missing' => [null],
    'not numeric' => ['abc'],
]);

it('reads the submitted quantity when the disable-quantity setting is null', function () {
    $fieldtype = productFieldtype(['selection_type' => 'single', 'price' => 5, 'disable_quantity' => null]);

    expect(data_get($fieldtype->process(['quantity' => '3']), 'quantity'))->toBe(3);
});

it('falls back to a single product with no price and no handle when no field is bound', function () {
    $fieldtype = new Product;

    expect($fieldtype->process(['quantity' => '2']))->toBe([
        'selection_type' => 'single',
        'key' => null,
        'label' => null,
        'quantity' => 2,
        'price' => 0.0,
        'line_total' => 0.0,
    ]);
});

it('stores the full multiple selection with a string price and three-decimal rounding', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => 'a', 'label' => 'Choice A', 'price' => '1.234']],
    ]);

    expect($fieldtype->process(['key' => 'a', 'quantity' => '1']))->toBe([
        'selection_type' => 'multiple',
        'key' => 'a',
        'label' => 'Choice A',
        'quantity' => 1,
        'price' => 1.234,
        'line_total' => 1.23,
    ]);
});

it('labels a multiple selection with its key when the option has no label', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => 'a', 'price' => 2]],
    ]);

    $out = $fieldtype->process(['key' => 'a', 'quantity' => '2']);

    expect(data_get($out, 'label'))->toBe('a')
        ->and(data_get($out, 'quantity'))->toBe(2);
});

it('prices a multiple option at zero when its price is missing or not numeric', function (array $option) {
    $fieldtype = productFieldtype(['selection_type' => 'multiple', 'options' => [$option]]);

    $out = $fieldtype->process(['key' => 'a', 'quantity' => '2']);

    expect(data_get($out, 'price'))->toBe(0.0)
        ->and(data_get($out, 'line_total'))->toBe(0.0);
})->with([
    'missing' => [['key' => 'a']],
    'not numeric' => [['key' => 'a', 'price' => 'abc']],
]);

it('treats a multiple quantity that is zero, negative or not numeric as not ordered', function (mixed $quantity) {
    $fieldtype = productFieldtype(['selection_type' => 'multiple', 'options' => [['key' => 'a', 'price' => 5]]]);

    expect($fieldtype->process(['key' => 'a', 'quantity' => $quantity]))->toBeNull();
})->with([
    'zero' => ['0'],
    'negative' => ['-2'],
    'not numeric' => ['abc'],
]);

it('orders nothing for an empty or missing key even when an option has an empty key', function (array $data) {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => '', 'price' => 5]],
    ]);

    expect($fieldtype->process($data))->toBeNull();
})->with([
    'empty key' => [['key' => '', 'quantity' => '1']],
    'missing key' => [['quantity' => '1']],
]);

it('matches an integer key against a string option key', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => '5', 'price' => 5]],
    ]);

    expect(data_get($fieldtype->process(['key' => 5, 'quantity' => '1']), 'key'))->toBe('5');
});

it('reads a non-scalar key as the empty key', function () {
    $fieldtype = productFieldtype([
        'selection_type' => 'multiple',
        'options' => [['key' => '', 'label' => 'Empty Key', 'price' => 5]],
    ]);

    expect(data_get($fieldtype->process(['key' => ['x'], 'quantity' => '1']), 'label'))->toBe('Empty Key');
});
