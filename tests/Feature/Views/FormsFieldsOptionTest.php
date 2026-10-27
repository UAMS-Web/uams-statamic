<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render tests for the public-form `option` fieldtype template
 * (resources/views/vendor/statamic/forms/fields/option.antlers.html). The parent
 * loop in fields.antlers.html passes the field config + the fieldtype's
 * extraRenderableFieldData() (option_choices) down as the rendering scope, so
 * rendering the template directly against that shape is equivalent to the full
 * form:create pipeline. The data-option-* hooks are the contract
 * option-pricing-engine.js binds to; data-product-line-total-input is what a
 * future Total field (UAMS-Web/uams-statamic-archive#488) sums. Rendering a template against a hand-built scope
 * has no mutable PHP surface.
 *
 * @command  php artisan test --compact tests/Feature/Views/FormsFieldsOptionTest.php
 */

/**
 * Render the option fieldtype template against a renderable-field context.
 *
 * @param  array<string, mixed>  $context
 */
function renderOptionField(array $context): string
{
    return (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/option.antlers.html')),
        $context,
        true,
    );
}

/**
 * Baseline option context for the given input type.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function optionFieldContext(string $inputType, array $overrides = []): array
{
    return [
        'handle' => 'rate_option',
        'id' => 'form-rate_option',
        'visibility' => 'visible',
        'validate' => [],
        'error' => null,
        'value' => null,
        'input_type' => $inputType,
        'option_choices' => [
            ['key' => 'first', 'label' => 'First Choice', 'price' => 25, 'formatted_price' => '$25.00'],
            ['key' => 'second', 'label' => 'Second Choice', 'price' => 150, 'formatted_price' => '$150.00'],
        ],
        ...$overrides,
    ];
}

it('renders a priced select with pricing hooks', function () {
    $out = renderOptionField(optionFieldContext('select'));

    expect($out)->toContain('data-option-field');
    expect($out)->toContain('data-option-mode="select"');
    expect($out)->toContain('data-option-handle="rate_option"');
    expect($out)->toContain('name="rate_option[key]"');
    expect($out)->toContain('data-option-select');
    expect($out)->toContain('value="first"');
    expect($out)->toContain('data-price="25"');
    expect($out)->toContain('First Choice ($25.00)');
    expect($out)->toContain('name="rate_option[line_total]"');
    expect($out)->toContain('data-option-line-total-input');
    expect($out)->toContain('data-product-line-total-input');
});

it('renders one radio input per option', function () {
    $out = renderOptionField(optionFieldContext('radio'));

    expect($out)->toContain('data-option-mode="radio"');
    expect(substr_count($out, 'type="radio"'))->toBe(2);
    expect($out)->toContain('name="rate_option[key]"');
    expect($out)->toContain('data-option-input');
    expect($out)->toContain('data-price="150"');
});

it('renders array-named checkbox inputs', function () {
    $out = renderOptionField(optionFieldContext('checkbox'));

    expect($out)->toContain('data-option-mode="checkbox"');
    expect(substr_count($out, 'type="checkbox"'))->toBe(2);
    expect($out)->toContain('name="rate_option[keys][]"');
});

it('marks the previously selected select option', function () {
    $out = renderOptionField(optionFieldContext('select', [
        'value' => ['key' => 'second', 'line_total' => 150],
    ]));

    expect($out)->toMatch('/value="second"[^>]*selected/');
});

it('marks the previously selected checkbox options', function () {
    $out = renderOptionField(optionFieldContext('checkbox', [
        'value' => ['keys' => ['second'], 'line_total' => 150],
    ]));

    expect($out)->toMatch('/value="second"[^>]*checked/');
    expect($out)->not->toMatch('/value="first"[^>]*checked/');
});
