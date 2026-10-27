<?php

declare(strict_types=1);

use App\Tags\CalculatorLayout;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for stored XSS via the calculator caption.
 *
 * {@see CalculatorLayout::captionHtml()} renders an editor-authored formula into
 * `caption_html`, which the calculator partial prints with `{{ caption_html | raw }}`.
 * The formula source previously passed through unescaped except for operator and
 * identifier substitution, so non-identifier characters (`<`, `>`, `"`) survived
 * and could inject markup. This pins that every caption sink is escaped — formula
 * residue AND the part labels (the primary editor-controlled XSS surface) — while
 * labels and operators still render.
 *
 * Mutation: the htmlspecialchars escaping on every caption sink is pinned here —
 * formula residue ('escapes markup in the formula'), known-handle labels
 * (CalculatorLayout.php:171) and the trailing primary label (:186) via 'escapes
 * markup in part labels'; removing escaping from any one turns this file red. The
 * identifier-segment escape (:172) is an equivalent mutant — identifiers are
 * [a-zA-Z0-9_] by the split pattern, so they never contain escapable characters.
 *
 * @command  php artisan test --compact tests/Feature/Tags/CalculatorCaptionHtmlTest.php
 */

/**
 * Invoke the protected {@see CalculatorLayout::captionHtml()} via reflection and
 * return the rendered caption HTML for the `weight` primary handle.
 *
 * @param  list<array<string, mixed>>  $extraParts
 */
function calculatorCaptionHtml(string $formula, array $extraParts = []): string
{
    $parts = array_merge([
        ['handle' => 'weight', 'label' => 'Weight', 'formula' => ['code' => $formula]],
    ], $extraParts);

    $tag = app(CalculatorLayout::class);
    $method = new ReflectionMethod($tag, 'captionHtml');

    $html = $method->invoke($tag, $parts, 'weight');

    return \is_string($html) ? $html : '';
}

it('escapes markup in the formula', function () {
    $html = calculatorCaptionHtml('1<img src=x onerror=alert(1)>');

    // The injected element is neutralized: no raw `<img`, and the stray
    // angle brackets are escaped entities.
    expect($html)->not->toContain('<img');
    expect($html)->toContain('&lt;img');
    expect($html)->not->toContain('onerror=alert(1)>');
});

it('escapes attribute-breaking characters', function () {
    $html = calculatorCaptionHtml('weight="x"');

    expect($html)->not->toContain('"x"');
    expect($html)->toContain('&quot;');
});

it('still renders labels and operators for a benign formula', function () {
    // `weight` is a known handle -> bold label; `*` -> `×`; `2` passes through.
    $html = calculatorCaptionHtml('weight*2');

    expect($html)->toContain('<strong>Weight</strong>');
    expect($html)->toContain('×');
    expect($html)->toContain('= <strong>Weight</strong>');
    // The only literal markup is the `<strong>` wrappers we add ourselves:
    // one for the `weight` identifier, one for the trailing primary label.
    expect(substr_count($html, '<strong>'))->toBe(2);
});

it('passes unknown identifiers through', function () {
    // `floor` is a mathjs function, not a part handle — rendered as text.
    $html = calculatorCaptionHtml('floor(weight/2)', [
        ['handle' => 'weight', 'label' => 'Weight', 'formula' => ['code' => 'floor(weight/2)']],
    ]);

    expect($html)->toContain('floor(');
    expect($html)->toContain('÷');
});

it('escapes markup in part labels', function () {
    // The label is the primary editor-controlled stored-XSS sink (the formula is
    // constrained by the validator's identifier rules and the code fieldtype). Both
    // a referenced-part label and the trailing primary label must be escaped before
    // the `{{ caption_html | raw }}` sink; the formula-only cases above leave these
    // label branches green under mutation, so pin them explicitly here.
    $parts = [
        ['handle' => 'weight', 'label' => 'Ratio <foo>', 'formula' => ['code' => 'dose*2']],
        ['handle' => 'dose', 'label' => 'X & Y', 'formula' => ['code' => '']],
    ];

    $tag = app(CalculatorLayout::class);
    $method = new ReflectionMethod($tag, 'captionHtml');
    /** @var string $html */
    $html = $method->invoke($tag, $parts, 'weight');

    // Both labels are escaped; the only literal markup is the two <strong> wrappers.
    expect($html)->toContain('<strong>X &amp; Y</strong>');
    expect($html)->toContain('= <strong>Ratio &lt;foo&gt;</strong>');
    expect($html)->not->toContain('<foo>');
    expect(substr_count($html, '<strong>'))->toBe(2);
});
