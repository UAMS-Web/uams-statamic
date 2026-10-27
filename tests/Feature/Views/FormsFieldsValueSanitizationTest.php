<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the reflected-XSS hardening in resources/views/vendor/statamic/forms/fields/*.antlers.html (issue UAMS-Web/uams-statamic-archive#881).
 *
 * Antlers does not HTML-escape `{{ }}` output by default. On a validation-failure re-render,
 * Statamic's RendersForms::getRenderableField() repopulates each field's `value` from `old()` —
 * the raw submitted input — so any field view that echoes `value` into an attribute or element
 * body without `| sanitize` reflects attacker-controlled markup. These tests render each affected
 * view directly with a breakout payload and assert the output is HTML-entity-encoded.
 *
 * @command php artisan test --compact tests/Feature/Views/FormsFieldsValueSanitizationTest.php
 */

/**
 * A value that breaks out of a `value="…"` attribute (or a <textarea> body) into script.
 */
function formFieldXssPayload(): string
{
    return '"><img src=x onerror=alert(1)>';
}

/**
 * Render a single form-field view partial against the given scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderFormFieldView(string $view, array $scope): string
{
    return (string) Antlers::parse(
        File::get(resource_path("views/vendor/statamic/forms/fields/{$view}.antlers.html")),
        $scope,
        true,
    );
}

/**
 * Assert a rendered field view escaped the breakout payload rather than reflecting it raw.
 */
function expectFieldValueEscaped(string $out): void
{
    expect($out)
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
}

it('escapes the reflected value in single-value field views', function (string $view): void {
    $out = renderFormFieldView($view, [
        'value' => formFieldXssPayload(),
        'handle' => 'sample',
        'id' => 'form-sample',
    ]);

    expectFieldValueEscaped($out);
})->with(['default', 'text', 'hidden', 'integer', 'textarea']);

it('escapes the reflected value in composite/subfield field views', function (string $view, array $scope): void {
    $out = renderFormFieldView($view, array_merge([
        'handle' => 'sample',
        'id' => 'form-sample',
    ], $scope));

    expectFieldValueEscaped($out);
})->with([
    // address — every free-text subfield; `international` preset renders the
    // state input as free-form text (the other presets use a code <select>).
    'address' => ['address', [
        'preset' => 'international',
        'value' => [
            'street_address' => '"><img src=x onerror=alert(1)>',
            'address_line_2' => '"><img src=x onerror=alert(1)>',
            'city' => '"><img src=x onerror=alert(1)>',
            'state_province_region' => '"><img src=x onerror=alert(1)>',
            'postal_code' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    // name — all five subfields (prefix/middle/suffix are off by default).
    'name' => ['name', [
        'prefix_include' => true,
        'middle_name_include' => true,
        'suffix_include' => true,
        'value' => [
            'prefix' => '"><img src=x onerror=alert(1)>',
            'first_name' => '"><img src=x onerror=alert(1)>',
            'middle_name' => '"><img src=x onerror=alert(1)>',
            'last_name' => '"><img src=x onerror=alert(1)>',
            'suffix' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    'text_phonetic_pair' => ['text_phonetic_pair', [
        'value' => [
            'text' => '"><img src=x onerror=alert(1)>',
            'phonetic_text' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    '_person_name_pair' => ['_person_name_pair', [
        'subfield' => 'first_name',
        'label' => 'First Name',
        'autocomplete' => 'given-name',
        'pair_value' => [
            'text' => '"><img src=x onerror=alert(1)>',
            'phonetic_text' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    // date — range mode echoes both bounds; single mode delegates to `default`.
    'date' => ['date', [
        'mode' => 'range',
        'value' => [
            'start' => '"><img src=x onerror=alert(1)>',
            'end' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    'partial_date' => ['partial_date', [
        'included_parts' => 'year_month_day',
        'value' => [
            'year' => '"><img src=x onerror=alert(1)>',
            'day' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    // product — quantity input + the (server-recomputed) line_total reflected from old().
    'product' => ['product', [
        'selection_type' => 'single',
        'value' => [
            'quantity' => '"><img src=x onerror=alert(1)>',
            'line_total' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
    'option' => ['option', [
        'input_type' => 'select',
        'value' => [
            'line_total' => '"><img src=x onerror=alert(1)>',
        ],
    ]],
]);

it('round-trips legitimate special characters as HTML entities', function (): void {
    $out = renderFormFieldView('text', [
        'value' => "O'Brien & Sons",
        'handle' => 'sample',
        'id' => 'form-sample',
    ]);

    // Displayed verbatim to the user, but stored in the attribute as entities — not executable.
    expect($out)->toContain('O&#039;Brien &amp; Sons');
    expect($out)->not->toContain('O\'Brien & Sons');
});

/**
 * A payload that breaks out of a double-quoted attribute (`"`), a single-quoted
 * attribute (`'`, e.g. Alpine `x-data='…'`), and the element body (`<`). Any
 * unescaped reflection of it yields a live `<img src=x onerror=alert(1)>` element.
 */
function formFieldBreakoutPayload(): string
{
    return '"\'><img src=x onerror=alert(1)>';
}

dataset('all form field views', function () {
    // Dataset closures resolve before the app boots, so compute the path from __DIR__
    // (tests/Feature/Views -> repo root) rather than the resource_path() helper.
    $dir = dirname(__DIR__, 3).'/resources/views/vendor/statamic/forms/fields';

    return array_map('basename', glob($dir.'/*.antlers.html') ?: []);
});

it('reflects no breakout payload as live HTML in any field view', function (string $file): void {
    // Completeness guard: the per-view tests above are an enumerated allow-list, so a
    // view they forget (the `list` x-data sink was exactly this) stays green. This renders
    // EVERY field view with a breakout payload and fails if any reflects it as live HTML.
    $payload = formFieldBreakoutPayload();
    $template = File::get(resource_path("views/vendor/statamic/forms/fields/{$file}"));

    // Provide the payload as a scalar and under every subfield key / index a composite
    // view might echo, so each view reflects it somewhere it can.
    $arrayValue = array_fill_keys([
        'street_address', 'address_line_2', 'city', 'state_province_region', 'postal_code', 'country',
        'prefix', 'first_name', 'middle_name', 'last_name', 'suffix',
        'text', 'phonetic_text', 'start', 'end', 'year', 'month', 'day', 'quantity', 'line_total', 'key',
    ], $payload);
    $arrayValue[0] = $payload;

    $scopeBase = [
        'handle' => 'sample',
        'id' => 'form-sample',
        'preset' => 'international',
        'mode' => 'range',
        'included_parts' => 'year_month_day',
        'selection_type' => 'single',
        'prefix_include' => true,
        'middle_name_include' => true,
        'suffix_include' => true,
        'subfield' => 'first_name',
        'label' => 'Label',
        'autocomplete' => 'name',
        'pair_value' => ['text' => $payload, 'phonetic_text' => $payload],
    ];

    foreach ([$payload, $arrayValue] as $value) {
        try {
            $out = (string) Antlers::parse($template, array_merge($scopeBase, ['value' => $value]), true);
        } catch (Throwable) {
            // View needs a value shape we did not supply; the explicit per-view tests cover it.
            continue;
        }

        expect($out)->not->toContain('<img src=x onerror=alert(1)>');
    }
})->with('all form field views');
