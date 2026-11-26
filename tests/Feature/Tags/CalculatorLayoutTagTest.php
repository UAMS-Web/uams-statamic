<?php

declare(strict_types=1);

use App\Tags\CalculatorLayout;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(CalculatorLayout::class);

/**
 * Tests the layout computation that splits a calculator's `parts` into LHS
 * (primary variable), RHS (referenced by the primary's formula), and other
 * (everything else). The tag reads raw parts off the entry by id so it
 * doesn't have to deal with augmented-value wrapping; tests stub `Entry::find`
 * to return a mock entry with a predetermined parts array.
 *
 * Mutation: 100% on App\Tags\CalculatorLayout (138 mutants), measured on macOS 26.5 (arm64) on 2026-11-25 with PHP 8.4.28 and Herd's Xdebug 3.5.3, without --covered-only, over
 * this file and CalculatorCaptionHtmlTest (UAMS-Web/uams-statamic#801). Nine equivalents are
 * annotated in the tag, each with its reason: the caption's initial label and its stand-in
 * for a handle that is not text, the preg_split limit and its failure fallback, the escape
 * of an identifier, the early return for an empty formula (two mutators), and the filter of
 * part handles (two mutators). The reflection test reads referencedHandlesForPrimary(),
 * whose duplicates, keys and non-handle identifiers do not reach index()'s output.
 *
 * @command  php artisan test tests/Feature/Tags/CalculatorLayoutTagTest.php
 */

/**
 * Build a tag instance bound to a mocked entry whose `get('parts')`
 * returns the given array.
 *
 * @param  list<array<string, mixed>>  $parts
 */
function calculatorLayoutMakeTag(string $entryId, array $parts): CalculatorLayout
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('parts')->andReturn($parts);
    EntryFacade::shouldReceive('find')->with($entryId)->andReturn($entry);

    $tag = new CalculatorLayout;
    $tag->setContext(['id' => $entryId]);
    $tag->setParameters([]);

    return $tag;
}

it('returns empty layout when id missing', function () {
    $tag = new CalculatorLayout;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe([
        'primary_handle' => '',
        'primary' => [],
        'referenced' => [],
        'other' => [],
        'caption_html' => '',
    ]);
});

it('returns empty layout when entry not found', function () {
    EntryFacade::shouldReceive('find')->with('missing')->andReturn(null);

    $tag = new CalculatorLayout;
    $tag->setContext(['id' => 'missing']);
    $tag->setParameters([]);

    expect($tag->index())->toBe([
        'primary_handle' => '',
        'primary' => [],
        'referenced' => [],
        'other' => [],
        'caption_html' => '',
    ]);
});

it('returns empty layout when parts empty', function () {
    $tag = calculatorLayoutMakeTag('e1', []);

    expect($tag->index())->toBe([
        'primary_handle' => '',
        'primary' => [],
        'referenced' => [],
        'other' => [],
        'caption_html' => '',
    ]);
});

it('infers implicit primary when only one formula', function () {
    // Single computed variable → no `primary: true` needed; tag infers it.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'result', 'kind' => 'output', 'formula' => ['code' => 'a + b']],
        ['handle' => 'a', 'kind' => 'input'],
        ['handle' => 'b', 'kind' => 'input'],
    ]);

    $result = $tag->index();

    expect($result['primary_handle'])->toBe('result');
    expect($result['primary'])->toHaveCount(1);
    expect(data_get($result, 'primary.0.handle'))->toBe('result');
    expect(array_column($result['referenced'], 'handle'))->toBe(['a', 'b']);
    expect($result['other'])->toBe([]);
});

it('lets an explicit primary override the implicit one', function () {
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'kg', 'kind' => 'bidirectional', 'primary' => true, 'formula' => ['code' => 'imperial * 0.4535']],
        ['handle' => 'imperial', 'kind' => 'bidirectional', 'formula' => ['code' => 'kg / 0.4535']],
    ]);

    $result = $tag->index();

    expect($result['primary_handle'])->toBe('kg');
    expect(array_column($result['primary'], 'handle'))->toBe(['kg']);
    expect(array_column($result['referenced'], 'handle'))->toBe(['imperial']);
});

it('sends unreferenced parts to other', function () {
    // `c` is a sibling input not referenced by the primary's formula —
    // it should land in `other`, not `rhs`.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'result', 'kind' => 'output', 'formula' => ['code' => 'a + b']],
        ['handle' => 'a', 'kind' => 'input'],
        ['handle' => 'b', 'kind' => 'input'],
        ['handle' => 'c', 'kind' => 'input'],
    ]);

    $result = $tag->index();

    expect(array_column($result['referenced'], 'handle'))->toBe(['a', 'b']);
    expect(array_column($result['other'], 'handle'))->toBe(['c']);
});

it('does not match function names in the formula to part handles', function () {
    // `floor` is a mathjs function name; not a part handle. The tag
    // intersects identifier matches with actual part handles, so
    // unrelated tokens are filtered out.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'result', 'kind' => 'output', 'formula' => ['code' => 'floor(x / 2)']],
        ['handle' => 'x', 'kind' => 'input'],
    ]);

    $result = $tag->index();

    expect(array_column($result['referenced'], 'handle'))->toBe(['x']);
});

it('resolves the weight conversion shape correctly', function () {
    // Mirrors the migrated content/collections/calculators/.../weight-conversion.md
    // shape — composite imperial with splits, kg as primary.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'weight_kg', 'kind' => 'bidirectional', 'primary' => true, 'formula' => ['code' => 'weight_imperial * 0.453592']],
        [
            'handle' => 'weight_imperial',
            'kind' => 'bidirectional',
            'formula' => ['code' => 'weight_kg / 0.453592'],
            'split' => [
                ['handle' => 'weight_lb', 'strategy' => 'integer'],
                ['handle' => 'weight_oz', 'strategy' => 'fractional_times', 'multiplier' => 16],
            ],
        ],
    ]);

    $result = $tag->index();

    expect($result['primary_handle'])->toBe('weight_kg');
    expect(array_column($result['primary'], 'handle'))->toBe(['weight_kg']);
    expect(array_column($result['referenced'], 'handle'))->toBe(['weight_imperial']);
    expect($result['other'])->toBe([]);
});

it('falls back to the first part when no formula exists anywhere', function () {
    // Pathological case the save-time validator would normally block;
    // the tag falls back to the first part so a misconfigured entry
    // still renders something instead of a blank block.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'a', 'kind' => 'input'],
        ['handle' => 'b', 'kind' => 'input'],
    ]);

    $result = $tag->index();

    expect($result['primary_handle'])->toBe('a');
    expect(array_column($result['primary'], 'handle'))->toBe(['a']);
    expect($result['referenced'])->toBe([]);
    expect(array_column($result['other'], 'handle'))->toBe(['b']);
});

it('substitutes labels and operators in the caption html', function () {
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'result', 'label' => 'Result', 'kind' => 'output', 'formula' => ['code' => 'a * b + c']],
        ['handle' => 'a', 'label' => 'Apples', 'kind' => 'input'],
        ['handle' => 'b', 'label' => 'Boxes', 'kind' => 'input'],
        ['handle' => 'c', 'label' => 'Constant', 'kind' => 'input'],
    ]);

    expect($tag->index()['caption_html'])->toBe(
        '<strong>Apples</strong> × <strong>Boxes</strong> + <strong>Constant</strong> = <strong>Result</strong>'
    );
});

it('passes through function names unwrapped in the caption html', function () {
    // mathjs function names (floor, sqrt) don't match any part handle,
    // so they pass through the regex callback unchanged — not wrapped
    // in <strong>.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'lb', 'label' => 'Pounds', 'kind' => 'output', 'formula' => ['code' => 'floor(kg / 0.4535)']],
        ['handle' => 'kg', 'label' => 'Kilograms', 'kind' => 'input'],
    ]);

    expect($tag->index()['caption_html'])->toBe(
        'floor(<strong>Kilograms</strong> ÷ 0.4535) = <strong>Pounds</strong>'
    );
});

it('escapes labels in the caption html', function () {
    // Labels with HTML-special characters must be escaped before being
    // wrapped in <strong>, since the partial emits caption_html via raw.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'r', 'label' => 'Ratio <foo>', 'kind' => 'output', 'formula' => ['code' => 'x']],
        ['handle' => 'x', 'label' => 'X & Y', 'kind' => 'input'],
    ]);

    expect($tag->index()['caption_html'])->toBe(
        '<strong>X &amp; Y</strong> = <strong>Ratio &lt;foo&gt;</strong>'
    );
});

it('tolerates a bare string formula in the caption html', function () {
    // The code fieldtype flattens to a bare string when mode_selectable
    // is false. Caption rendering must accept both shapes the same way
    // the partial does.
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'r', 'label' => 'Result', 'kind' => 'output', 'formula' => 'x + y'],
        ['handle' => 'x', 'label' => 'X', 'kind' => 'input'],
        ['handle' => 'y', 'label' => 'Y', 'kind' => 'input'],
    ]);

    expect($tag->index()['caption_html'])->toBe(
        '<strong>X</strong> + <strong>Y</strong> = <strong>Result</strong>'
    );
});

it('returns empty caption html when no formula exists', function () {
    $tag = calculatorLayoutMakeTag('e1', [
        ['handle' => 'a', 'label' => 'A', 'kind' => 'input'],
    ]);

    expect($tag->index()['caption_html'])->toBe('');
});

/**
 * The layout of a calculator whose raw `parts` are the given value.
 *
 * @return array<string, mixed>
 */
function calculatorLayoutOf(mixed $parts): array
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('parts')->andReturn($parts);
    EntryFacade::shouldReceive('find')->with('e1')->andReturn($entry);

    $tag = new CalculatorLayout;
    $tag->setContext(['id' => 'e1']);
    $tag->setParameters([]);

    return $tag->index();
}

/**
 * The handles of a list of parts, keeping a part with no handle as null.
 *
 * @return list<mixed>
 */
function calculatorLayoutHandles(mixed $parts): array
{
    return array_values(array_map(fn (mixed $part): mixed => is_array($part) ? ($part['handle'] ?? null) : null, is_array($parts) ? $parts : []));
}

it('returns the empty layout when the parts are not a list', function (): void {
    expect(calculatorLayoutOf('a + b'))->toBe([
        'primary_handle' => '',
        'primary' => [],
        'referenced' => [],
        'other' => [],
        'caption_html' => '',
    ]);
});

it('falls back to the first part of parts keyed by name', function (): void {
    $result = calculatorLayoutOf([
        'first' => ['handle' => 'a', 'kind' => 'input'],
        'second' => ['handle' => 'b', 'kind' => 'input'],
    ]);

    expect($result['primary_handle'])->toBe('a')
        ->and(calculatorLayoutHandles($result['other']))->toBe(['b']);
});

it('takes the explicit primary wherever it sits, over the sole formula', function (): void {
    $result = calculatorLayoutOf([
        ['handle' => 'a', 'kind' => 'input'],
        ['handle' => 'total', 'kind' => 'output', 'formula' => ['code' => 'a * 2']],
        ['handle' => 'shown', 'kind' => 'output', 'primary' => true],
    ]);

    expect($result['primary_handle'])->toBe('shown')
        ->and($result['referenced'])->toBe([])
        ->and(calculatorLayoutHandles($result['other']))->toBe(['a', 'total'])
        ->and($result['caption_html'])->toBe('');
});

it('takes the sole formula as the primary when it is not the first part', function (): void {
    $result = calculatorLayoutOf([
        ['handle' => 'a', 'label' => 'A', 'kind' => 'input', 'formula' => ['mode' => 'javascript']],
        ['handle' => 'b', 'label' => 'B', 'kind' => 'input', 'formula' => '   '],
        ['handle' => 'total', 'label' => 'Total', 'kind' => 'output', 'formula' => ['code' => ' a / b ']],
    ]);

    // A formula with no code, and one that is only whitespace, are not formulas
    expect($result['primary_handle'])->toBe('total')
        ->and(calculatorLayoutHandles($result['referenced']))->toBe(['a', 'b'])
        ->and($result['caption_html'])->toBe('<strong>A</strong> ÷ <strong>B</strong> = <strong>Total</strong>');
});

it('names no primary when the part it picks has no text handle', function (array $parts): void {
    expect(calculatorLayoutOf($parts)['primary_handle'])->toBe('');
})->with([
    'the explicit primary' => [[['handle' => 7, 'primary' => true], ['handle' => 'b', 'formula' => 'x']]],
    'the sole formula' => [[['handle' => 'a'], ['handle' => 7, 'formula' => 'a']]],
    'the first part' => [[['handle' => 7], ['handle' => 'b']]],
]);

it('lays out a primary that has no handle', function (): void {
    $result = calculatorLayoutOf([
        ['kind' => 'output', 'formula' => 'b * 2'],
        ['handle' => 'b', 'label' => 'B', 'kind' => 'input'],
    ]);

    // The part with no handle is the primary, and the caption leaves it out
    expect($result['primary_handle'])->toBe('')
        ->and(calculatorLayoutHandles($result['primary']))->toBe([null])
        ->and(calculatorLayoutHandles($result['referenced']))->toBe(['b'])
        ->and($result['other'])->toBe([])
        ->and($result['caption_html'])->toBe('');
});

it('references nothing when no part carries the primary handle', function (): void {
    // The first part's handle is not text, so the primary handle is '' and no part has it
    $result = calculatorLayoutOf([
        ['handle' => 7],
        ['handle' => 'here', 'kind' => 'input'],
        ['handle' => 'was', 'kind' => 'input'],
    ]);

    expect($result['primary_handle'])->toBe('')
        ->and($result['referenced'])->toBe([])
        ->and(calculatorLayoutHandles($result['other']))->toBe([7, 'here', 'was'])
        ->and($result['caption_html'])->toBe('');
});

it('builds the caption past a part with no handle', function (): void {
    expect(calculatorLayoutOf([
        ['kind' => 'note'],
        ['handle' => 'r', 'label' => 'R', 'primary' => true, 'formula' => 'x + 1'],
        ['handle' => 'x', 'label' => 'X'],
    ])['caption_html'])->toBe('<strong>X</strong> + 1 = <strong>R</strong>');
});

it('reads the first part that carries the primary handle', function (): void {
    $result = calculatorLayoutOf([
        ['handle' => 'r', 'primary' => true, 'formula' => 'a'],
        ['handle' => 'r', 'formula' => 'b'],
        ['handle' => 'a'],
        ['handle' => 'b'],
    ]);

    expect(calculatorLayoutHandles($result['referenced']))->toBe(['a'])
        ->and(calculatorLayoutHandles($result['other']))->toBe(['b']);
});

it('matches handles strictly when sorting parts', function (): void {
    // A handle that is not text is never one of the formula's identifiers
    $result = calculatorLayoutOf([
        ['handle' => 'r', 'formula' => 'a'],
        ['handle' => 'a'],
        ['handle' => true],
    ]);

    expect(calculatorLayoutHandles($result['referenced']))->toBe(['a'])
        ->and(calculatorLayoutHandles($result['other']))->toBe([true]);
});

it('lists each part the primary formula names once, in the formula\'s order', function (): void {
    $method = new ReflectionMethod(CalculatorLayout::class, 'referencedHandlesForPrimary');

    expect($method->invoke(new CalculatorLayout, [
        ['handle' => 'r', 'formula' => 'floor(b + a + b)'],
        ['handle' => 'a'],
        ['handle' => 'b'],
    ], 'r'))->toBe(['b', 'a']);
});
