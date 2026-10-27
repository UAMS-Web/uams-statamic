<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the per-fieldtype `visibility: read_only` contract (UAMS-Web/uams-statamic-archive#726) documented
 * in resources/views/vendor/statamic/forms/fields/README.md. Each Bootstrap
 * fieldtype override must emit a non-editing attribute when its blueprint
 * visibility is read_only: text-shaped inputs / textareas get `readonly` +
 * `aria-readonly="true"`; selects / radios / checkboxes / files / toggles get
 * `disabled`. (`visibility: hidden`, in the parent wrapper, is covered by
 * FormsFieldsVisibilityHiddenTest.) Rendering templates against hand-built scopes
 * has no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/FormsFieldsVisibilityReadOnlyTest.php
 */

/**
 * Minimal renderable-field context with sensible defaults.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function formFieldContext(array $overrides = []): array
{
    return [
        'handle' => 'sample',
        'id' => 'form-sample',
        'type' => 'text',
        'visibility' => 'visible',
        'value' => '',
        'validate' => [],
        'error' => null,
        'instructions' => null,
        'placeholder' => null,
        'options' => ['yes' => 'Yes', 'no' => 'No'],
        ...$overrides,
    ];
}

/**
 * Render an individual fieldtype template against the shared context.
 *
 * @param  array<string, mixed>  $overrides
 */
function renderFormFieldTemplate(string $fieldtype, array $overrides = []): string
{
    return (string) Antlers::parse(
        File::get(resource_path("views/vendor/statamic/forms/fields/{$fieldtype}.antlers.html")),
        formFieldContext($overrides),
        true,
    );
}

/**
 * Render the option template (read-only) for the given input type.
 */
function renderReadOnlyOptionTemplate(string $inputType): string
{
    return (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/option.antlers.html')),
        [
            'handle' => 'rate_option',
            'id' => 'form-rate_option',
            'visibility' => 'read_only',
            'validate' => [],
            'error' => null,
            'value' => null,
            'input_type' => $inputType,
            'option_choices' => [
                ['key' => 'a', 'label' => 'A', 'price' => 25, 'formatted_price' => '$25.00'],
                ['key' => 'b', 'label' => 'B', 'price' => 50, 'formatted_price' => '$50.00'],
            ],
        ],
        true,
    );
}

it('renders a visible text field without a readonly attribute', function () {
    $out = renderFormFieldTemplate('text');

    expect($out)->toContain('<input');
    expect($out)->not->toContain('readonly');
    expect($out)->not->toContain('aria-readonly');
});

it('renders a read_only text field with the readonly attribute', function () {
    $out = renderFormFieldTemplate('text', ['visibility' => 'read_only']);

    expect($out)->toContain('readonly');
    expect($out)->toContain('aria-readonly="true"');
});

it('renders the shared default partial read_only with the readonly attribute', function () {
    // default.antlers.html backs email/url/phone/float/time/single-date.
    $out = renderFormFieldTemplate('default', ['visibility' => 'read_only']);

    expect($out)->toContain('readonly');
    expect($out)->toContain('aria-readonly="true"');
});

it('renders a read_only textarea with the readonly attribute', function () {
    $out = renderFormFieldTemplate('textarea', ['visibility' => 'read_only']);

    expect($out)->toContain('<textarea');
    expect($out)->toContain('readonly');
    expect($out)->toContain('aria-readonly="true"');
});

it('renders a read_only integer with the readonly attribute', function () {
    $out = renderFormFieldTemplate('integer', ['visibility' => 'read_only']);

    expect($out)->toContain('type="number"');
    expect($out)->toContain('readonly');
    expect($out)->toContain('aria-readonly="true"');
});

it('renders a visible select without a disabled attribute', function () {
    $out = renderFormFieldTemplate('select');

    expect($out)->toContain('<select');
    expect($out)->not->toContain('disabled');
});

it('renders a read_only select with the disabled attribute', function () {
    $out = renderFormFieldTemplate('select', ['visibility' => 'read_only']);

    expect($out)->toContain('<select');
    expect($out)->toContain('disabled');
});

it('disables every option of a read_only radio', function () {
    $out = renderFormFieldTemplate('radio', ['visibility' => 'read_only']);

    expect(substr_count($out, 'type="radio"'))->toBe(2);
    expect(substr_count($out, 'disabled'))->toBe(2);
});

it('disables every option of a read_only likert scale', function () {
    // Likert reads the list-shaped `options` from LikertScale::extraRenderableFieldData(),
    // not the `value => label` map the shared radio context supplies.
    $out = (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/likert_scale.antlers.html')),
        [
            'handle' => 'rating',
            'id' => 'form-rating',
            'display' => 'Rating',
            'visibility' => 'read_only',
            'validate' => [],
            'error' => null,
            'value' => '',
            'enable_multiple_rows' => false,
            'options' => [
                ['value' => 1, 'label' => 'Low'],
                ['value' => 2, 'label' => 'High'],
            ],
        ],
        true,
    );

    expect(substr_count($out, 'type="radio"'))->toBe(2);
    expect(substr_count($out, 'disabled'))->toBe(2);
});

it('disables the select and makes the quantity readonly for a read_only multiple product', function () {
    $out = (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/product.antlers.html')),
        [
            'handle' => 'order_item',
            'id' => 'form-order_item',
            'visibility' => 'read_only',
            'validate' => [],
            'error' => null,
            'value' => null,
            'selection_type' => 'multiple',
            'product_options' => [
                ['key' => 'a', 'label' => 'A', 'price' => 5, 'formatted_price' => '$5.00'],
            ],
        ],
        true,
    );

    expect($out)->toContain('<select');
    expect($out)->toContain('disabled');
    expect($out)->toContain('readonly');
    expect($out)->toContain('aria-readonly="true"');
});

it('makes the quantity readonly for a read_only single product', function () {
    $out = (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields/product.antlers.html')),
        [
            'handle' => 'order_item',
            'id' => 'form-order_item',
            'visibility' => 'read_only',
            'validate' => [],
            'error' => null,
            'value' => null,
            'selection_type' => 'single',
            'price' => 10,
            'disable_quantity' => false,
            'formatted_price' => '$10.00',
        ],
        true,
    );

    expect($out)->toContain('readonly');
    expect($out)->toContain('aria-readonly="true"');
});

it('disables every option of read_only checkboxes but not the hidden array input', function () {
    $out = renderFormFieldTemplate('checkboxes', ['visibility' => 'read_only']);

    expect(substr_count($out, 'type="checkbox"'))->toBe(2);
    expect(substr_count($out, 'disabled'))->toBe(2);
    expect($out)->toContain('type="hidden"');
});

it('disables the dropdown for a read_only option select', function () {
    $out = renderReadOnlyOptionTemplate('select');

    expect($out)->toContain('<select');
    expect($out)->toContain('disabled');
});

it('disables every option for a read_only option radio', function () {
    $out = renderReadOnlyOptionTemplate('radio');

    expect(substr_count($out, 'type="radio"'))->toBe(2);
    expect(substr_count($out, 'disabled'))->toBe(2);
});

it('disables every option for a read_only option checkbox', function () {
    $out = renderReadOnlyOptionTemplate('checkbox');

    expect(substr_count($out, 'type="checkbox"'))->toBe(2);
    expect(substr_count($out, 'disabled'))->toBe(2);
});

it('disables only the visible checkbox of a read_only toggle', function () {
    $out = renderFormFieldTemplate('toggle', ['visibility' => 'read_only']);

    expect($out)->toContain('type="checkbox"');
    expect(substr_count($out, 'disabled'))->toBe(1);
});

it('renders a read_only files input with the disabled attribute', function () {
    $out = renderFormFieldTemplate('files', ['visibility' => 'read_only']);

    expect($out)->toContain('type="file"');
    expect($out)->toContain('disabled');
});

it('does not double-emit readonly when calculation_query is also set', function () {
    // A calculated field already emits readonly/aria-readonly from the
    // calculation_query block; read_only must not emit it a second time.
    $out = renderFormFieldTemplate('text', ['visibility' => 'read_only', 'calculation_query' => true]);

    // Standalone `readonly` occurrences (total minus the ones inside aria-readonly).
    expect(substr_count($out, 'readonly') - substr_count($out, 'aria-readonly'))->toBe(1);
    expect(substr_count($out, 'aria-readonly="true"'))->toBe(1);
});

it('does not emit readonly/disabled when the visibility key is omitted', function (string $fieldtype) {
    $context = formFieldContext();
    unset($context['visibility']);

    $out = (string) Antlers::parse(
        File::get(resource_path("views/vendor/statamic/forms/fields/{$fieldtype}.antlers.html")),
        $context,
        true,
    );

    expect($out)->not->toContain('readonly');
    expect($out)->not->toContain('disabled');
})->with(['text', 'textarea', 'select', 'integer']);

it('does not emit readonly/disabled when visibility is explicitly visible', function (string $fieldtype) {
    $out = renderFormFieldTemplate($fieldtype, ['visibility' => 'visible']);

    expect($out)->not->toContain('readonly');
    expect($out)->not->toContain('disabled');
})->with(['text', 'textarea', 'select', 'integer']);
