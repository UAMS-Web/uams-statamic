<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests that the numeric form-field templates emit the configured `min` / `max` /
 * `step` HTML attributes so the browser enforces the bounds and stepper. The
 * custom `integer.antlers.html` overrides the default partial and previously
 * dropped these attributes (UAMS-Web/uams-statamic-archive#211); `float.antlers.html` delegates to
 * `default.antlers.html`, which already emits them (UAMS-Web/uams-statamic-archive#212) — guarded here so it
 * can't regress. Server-side `Integer::rules()` / `Floatval::rules()` remain the
 * enforcement authority; these attributes are a client-side convenience.
 *
 * @command php artisan test --compact tests/Feature/Views/FormsFieldsNumericConstraintsTest.php
 */

/**
 * Minimal renderable numeric-field context.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function numericFieldContext(array $overrides = []): array
{
    return [
        'handle' => 'quantity',
        'id' => 'form-quantity',
        'visibility' => 'visible',
        'value' => '',
        'validate' => [],
        'error' => null,
        'instructions' => null,
        'placeholder' => null,
        ...$overrides,
    ];
}

/**
 * Render a form-field template against the numeric context.
 *
 * @param  array<string, mixed>  $overrides
 */
function renderNumericField(string $fieldtype, array $overrides = []): string
{
    return (string) Antlers::parse(
        File::get(resource_path("views/vendor/statamic/forms/fields/{$fieldtype}.antlers.html")),
        numericFieldContext($overrides),
        true,
    );
}

it('emits min, max, and step on the integer field when all are configured', function () {
    $out = renderNumericField('integer', ['min' => 1, 'max' => 10, 'step' => 2]);

    expect($out)->toContain('type="number"');
    expect($out)->toContain('min="1"');
    expect($out)->toContain('max="10"');
    expect($out)->toContain('step="2"');
});

it('emits only min on the integer field when only min is configured', function () {
    $out = renderNumericField('integer', ['min' => 3]);

    expect($out)->toContain('min="3"');
    expect($out)->not->toContain('max=');
    expect($out)->not->toContain('step=');
});

it('emits only max on the integer field when only max is configured', function () {
    $out = renderNumericField('integer', ['max' => 7]);

    expect($out)->toContain('max="7"');
    expect($out)->not->toContain('min=');
    expect($out)->not->toContain('step=');
});

it('emits min="0"/max="0"/step="0" for zero-valued constraints (no 0-is-falsy drop)', function () {
    // A configured bound of 0 is meaningful (e.g. a non-negative quantity); the
    // `attribute:` modifier drops only null/false/empty-string, not 0.
    $out = renderNumericField('integer', ['min' => 0, 'max' => 0, 'step' => 0]);

    expect($out)->toContain('min="0"');
    expect($out)->toContain('max="0"');
    expect($out)->toContain('step="0"');
});

it('emits no min, max, or step on the integer field when none are configured', function () {
    $out = renderNumericField('integer');

    expect($out)->toContain('type="number"');
    expect($out)->not->toContain('min=');
    expect($out)->not->toContain('max=');
    expect($out)->not->toContain('step=');
});

it('preserves the integer calculation-engine read-only path alongside the new attributes', function () {
    $out = renderNumericField('integer', [
        'min' => 0,
        'max' => 100,
        'calculation_query' => true,
        'calculation_formula' => '1+1',
    ]);

    expect($out)->toContain('min="0"');
    expect($out)->toContain('max="100"');
    expect($out)->toContain('data-calculation-formula="1+1"');
    expect($out)->toContain('readonly');
});

it('emits min, max, and step on the float field via the default partial', function () {
    $out = renderNumericField('float', ['min' => 0.5, 'max' => 9.5, 'step' => 0.5]);

    expect($out)->toContain('min="0.5"');
    expect($out)->toContain('max="9.5"');
    expect($out)->toContain('step="0.5"');
});
