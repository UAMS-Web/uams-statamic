<?php

declare(strict_types=1);

use App\Tags\CalculatorLayout;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Tests the layout computation that splits a calculator's `parts` into LHS
 * (primary variable), RHS (referenced by the primary's formula), and other
 * (everything else). The tag reads raw parts off the entry by id so it
 * doesn't have to deal with augmented-value wrapping; tests stub `Entry::find`
 * to return a mock entry with a predetermined parts array.
 *
 * Mutation: 100% on App\Tags\CalculatorLayout (144 mutants, --covered-only).
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
