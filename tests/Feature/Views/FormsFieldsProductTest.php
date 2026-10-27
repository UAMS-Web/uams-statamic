<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render tests for the public-form `product` fieldtype template
 * (resources/views/vendor/statamic/forms/fields/product.antlers.html). The parent
 * loop passes the field config + the fieldtype's extraRenderableFieldData()
 * (formatted_price, product_options) down as the rendering scope, so rendering the
 * template directly against that shape is equivalent to the full form:create
 * pipeline. The data-product-* hooks are the contract product-pricing-engine.js
 * (and a future Total field, UAMS-Web/uams-statamic-archive#488) binds to. Rendering a template against a
 * hand-built scope has no mutable PHP surface.
 *
 * @command  php artisan test --compact tests/Feature/Views/FormsFieldsProductTest.php
 */

/**
 * Render the product fieldtype template against a renderable-field context.
 *
 * @param  array<string, mixed>  $context
 */
function renderProductField(array $context): string
{
    return (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/product.antlers.html')),
        $context,
        true,
    );
}

/**
 * Baseline single-product context (quantity enabled).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productSingleContext(array $overrides = []): array
{
    return [
        'handle' => 'cart_item',
        'id' => 'form-cart_item',
        'visibility' => 'visible',
        'validate' => [],
        'error' => null,
        'value' => null,
        'selection_type' => 'single',
        'price' => 11000,
        'disable_quantity' => false,
        'formatted_price' => '$11,000.00',
        ...$overrides,
    ];
}

/**
 * Baseline multiple-choice context.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productMultipleContext(array $overrides = []): array
{
    return [
        'handle' => 'cart_item',
        'id' => 'form-cart_item',
        'visibility' => 'visible',
        'validate' => [],
        'error' => null,
        'value' => null,
        'selection_type' => 'multiple',
        'product_options' => [
            ['key' => 'first', 'label' => 'First Choice', 'price' => 42.13, 'formatted_price' => '$42.13'],
            ['key' => 'second', 'label' => 'Second Choice', 'price' => 13.42, 'formatted_price' => '$13.42'],
        ],
        ...$overrides,
    ];
}

it('single renders price, quantity and pricing hooks', function () {
    $out = renderProductField(productSingleContext());

    expect($out)->toContain('data-product-field');
    expect($out)->toContain('data-product-mode="single"');
    expect($out)->toContain('data-product-handle="cart_item"');
    expect($out)->toContain('$11,000.00');
    expect($out)->toContain('data-product-single-price');
    expect($out)->toContain('data-price="11000"');
    expect($out)->toContain('name="cart_item[quantity]"');
    expect($out)->toContain('data-product-quantity');
    expect($out)->toContain('name="cart_item[line_total]"');
    expect($out)->toContain('data-product-line-total-input');
});

it('single with disabled quantity hides the number input', function () {
    $out = renderProductField(productSingleContext(['disable_quantity' => true]));

    expect($out)->toContain('type="hidden" name="cart_item[quantity]" value="1"');
    expect($out)->not->toContain('type="number"');
});

it('multiple renders priced options', function () {
    $out = renderProductField(productMultipleContext());

    expect($out)->toContain('data-product-mode="multiple"');
    expect($out)->toContain('name="cart_item[key]"');
    expect($out)->toContain('data-product-select');
    expect($out)->toContain('value="first"');
    expect($out)->toContain('data-price="42.13"');
    expect($out)->toContain('First Choice ($42.13)');
    expect($out)->toContain('Second Choice ($13.42)');
});

it('multiple marks the previously selected option', function () {
    $out = renderProductField(productMultipleContext([
        'value' => ['key' => 'second', 'quantity' => 2],
    ]));

    expect($out)->toMatch('/value="second"[^>]*selected/');
});
