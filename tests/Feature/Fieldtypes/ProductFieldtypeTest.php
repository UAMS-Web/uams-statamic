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
 * Mutation: 100% on App\Fieldtypes\Product (208 mutants, --covered-only).
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
