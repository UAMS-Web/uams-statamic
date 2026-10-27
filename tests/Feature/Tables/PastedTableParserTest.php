<?php

declare(strict_types=1);

use App\Services\Tables\PastedTableParser;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the pasted-table parser (UAMS-Web/uams-statamic-archive#226).
 *
 * Parses editor-pasted table data — an HTML `<table>`, Excel/Sheets TSV, or CSV —
 * into the `table` fieldtype shape (`['cells' => [...]]` rows), normalized to a
 * uniform column count. HTML cells are sanitized through the table-cell allowlist
 * so links survive and scripts are stripped.
 *
 * Mutation: the behavioral mutants are killed (delimiter choice, section
 * flattening incl. `tfoot`, cell/row trimming, multibyte handling, normalization).
 * The residual survivors are equivalent mutants — `strtolower()` on DOM node names
 * that libxml already lower-cases for HTML, the libxml error-state toggles and
 * cleanup calls, the always-set `ownerDocument` null-safe operator, the redundant
 * `(string)` cast on `fgetcsv` output, and the defensive `fopen`/`instanceof`
 * guards filtered by the empty-row/empty-cell checks — none of which change output.
 *
 * @command  php artisan test --compact tests/Feature/Tables/PastedTableParserTest.php
 */
/**
 * @return list<array{cells: list<string>}>
 */
function parsePastedTable(string $pasted): array
{
    return app(PastedTableParser::class)->parse($pasted);
}

// ---
// Delimited input (TSV / CSV)
// ---

it('parses tab-separated values (the Excel/Sheets clipboard format)', function (): void {
    expect(parsePastedTable("a\tb\tc\n1\t2\t3"))->toBe([
        ['cells' => ['a', 'b', 'c']],
        ['cells' => ['1', '2', '3']],
    ]);
});

it('parses comma-separated values', function (): void {
    expect(parsePastedTable("a,b,c\n1,2,3"))->toBe([
        ['cells' => ['a', 'b', 'c']],
        ['cells' => ['1', '2', '3']],
    ]);
});

it('keeps a quoted comma inside a single CSV cell', function (): void {
    expect(parsePastedTable("\"Smith, John\",CEO\nDoe,CFO"))->toBe([
        ['cells' => ['Smith, John', 'CEO']],
        ['cells' => ['Doe', 'CFO']],
    ]);
});

it('keeps a quoted multi-line CSV cell intact', function (): void {
    expect(parsePastedTable("\"line1\nline2\",x\na,b"))->toBe([
        ['cells' => ["line1\nline2", 'x']],
        ['cells' => ['a', 'b']],
    ]);
});

it('prefers tab over comma when both are present', function (): void {
    // A tab anywhere selects TSV, so the comma stays part of the cell text
    expect(parsePastedTable("a,1\tb"))->toBe([
        ['cells' => ['a,1', 'b']],
    ]);
});

it('parses a single-column paste', function (): void {
    expect(parsePastedTable("a\nb\nc"))->toBe([
        ['cells' => ['a']],
        ['cells' => ['b']],
        ['cells' => ['c']],
    ]);
});

it('trims surrounding whitespace from delimited cells', function (string $input): void {
    expect(parsePastedTable($input))->toBe([
        ['cells' => ['a', 'b']],
    ]);
})->with([
    'csv' => [' a , b '],
    'tsv' => [" a \t b "],
]);

// ---
// Normalization
// ---

it('pads ragged rows to the widest row', function (): void {
    expect(parsePastedTable("a\tb\tc\n1\t2"))->toBe([
        ['cells' => ['a', 'b', 'c']],
        ['cells' => ['1', '2', '']],
    ]);
});

it('skips blank lines', function (): void {
    expect(parsePastedTable("a,b\n\n1,2\n"))->toBe([
        ['cells' => ['a', 'b']],
        ['cells' => ['1', '2']],
    ]);
});

it('returns an empty array for empty or whitespace input', function (string $input): void {
    expect(parsePastedTable($input))->toBe([]);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'newlines' => ["\n\n"],
]);

// ---
// HTML table input
// ---

it('flattens an HTML table with head and body sections', function (): void {
    $html = '<table><thead><tr><th>Name</th><th>Role</th></tr></thead>'
        .'<tbody><tr><td>Jane</td><td>Chair</td></tr></tbody></table>';

    expect(parsePastedTable($html))->toBe([
        ['cells' => ['Name', 'Role']],
        ['cells' => ['Jane', 'Chair']],
    ]);
});

it('includes tfoot rows in document order', function (): void {
    $html = '<table><thead><tr><th>H</th></tr></thead>'
        .'<tbody><tr><td>B</td></tr></tbody>'
        .'<tfoot><tr><td>F</td></tr></tfoot></table>';

    expect(parsePastedTable($html))->toBe([
        ['cells' => ['H']],
        ['cells' => ['B']],
        ['cells' => ['F']],
    ]);
});

it('ignores whitespace text nodes between and inside HTML cells', function (): void {
    $html = "<table><tr>\n  <td>  a  </td>\n  <td>b</td>\n</tr></table>";

    // No phantom cells from the inter-cell whitespace; inner whitespace trimmed
    expect(parsePastedTable($html))->toBe([
        ['cells' => ['a', 'b']],
    ]);
});

it('preserves multibyte (UTF-8) characters in an HTML cell', function (): void {
    expect(parsePastedTable('<table><tr><td>café — über</td></tr></table>'))->toBe([
        ['cells' => ['café — über']],
    ]);
});

it('preserves a link in an HTML cell', function (): void {
    $html = '<table><tr><td><a href="https://uams.edu">UAMS</a></td><td>x</td></tr></table>';

    expect(parsePastedTable($html))->toBe([
        ['cells' => ['<a href="https://uams.edu">UAMS</a>', 'x']],
    ]);
});

it('strips a script from an HTML cell while keeping its text', function (): void {
    $html = '<table><tr><td>Chair<script>alert(1)</script></td><td>x</td></tr></table>';

    expect(parsePastedTable($html))->toBe([
        ['cells' => ['Chair', 'x']],
    ]);
});

it('reads th and td cells in the same row', function (): void {
    expect(parsePastedTable('<table><tr><th>H</th><td>D</td></tr></table>'))->toBe([
        ['cells' => ['H', 'D']],
    ]);
});

it('does not flatten a nested table into extra rows', function (): void {
    $html = '<table><tr><td>outer<table><tr><td>inner</td></tr></table></td><td>c2</td></tr></table>';

    // One outer row; the nested table's tags are stripped by the cell allowlist,
    // leaving its text, and its row is not promoted to a top-level row.
    expect(parsePastedTable($html))->toBe([
        ['cells' => ['outerinner', 'c2']],
    ]);
});

it('returns an empty array for a table with no rows', function (): void {
    expect(parsePastedTable('<table></table>'))->toBe([]);
});

it('uses only the first table when several are pasted', function (): void {
    $html = '<table><tr><td>first</td></tr></table><table><tr><td>second</td></tr></table>';

    expect(parsePastedTable($html))->toBe([
        ['cells' => ['first']],
    ]);
});

it('falls back to delimited parsing when cell text merely mentions a table tag', function (string $input, array $expected): void {
    // The `<table>` marker matches the HTML router but yields no rows, so the
    // paste must not be silently dropped — it is parsed as delimited instead.
    expect(parsePastedTable($input))->toBe($expected);
})->with([
    'csv' => [
        "tag,desc\n<table>,opens a table",
        [['cells' => ['tag', 'desc']], ['cells' => ['<table>', 'opens a table']]],
    ],
    'tsv' => [
        "a\t<table> ref\nb\tc",
        [['cells' => ['a', '<table> ref']], ['cells' => ['b', 'c']]],
    ],
]);
