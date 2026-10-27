<?php

declare(strict_types=1);

use App\Services\Tables\TableCellSanitizer;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the table-cell sanitizer (UAMS-Web/uams-statamic-archive#180).
 *
 * Table cells store raw HTML — imported WordPress tables carry inline `<a>` links
 * and emphasis — and the render partial historically emitted that value
 * unescaped (`{{ value }}`), a stored-XSS surface. The `sanitize_table_cell`
 * modifier now renders each cell through {@see TableCellSanitizer}, which keeps an
 * inline allowlist (links + emphasis) and strips everything else.
 *
 * Three layers are pinned: the service (the security logic), the Antlers modifier
 * wiring, and the real `resources/views/sets/_table.antlers.html` partial.
 *
 * Mutation: every behavioral mutant is dead (the allowlist, frame-target and
 * scheme restrictions are all killed). The residual survivors are equivalent
 * mutants no input can distinguish — the per-process purifier memoization, the
 * empty-cell short-circuit (`purify('')` returns `''` anyway), the redundant
 * `(string)` cast (`purify()` coerces the scalar regardless), and the production
 * serializer-cache config (output-invariant) — so the service is left clean
 * rather than annotated.
 *
 * @command  php artisan test --compact tests/Feature/Tables/TableCellSanitizerTest.php
 */
function sanitizeTableCell(mixed $value): string
{
    return app(TableCellSanitizer::class)->sanitize($value);
}

// ---
// Service: allowed inline markup survives
// ---

it('keeps a safe link', function (): void {
    expect(sanitizeTableCell('<a href="https://uams.edu">UAMS</a>'))
        ->toBe('<a href="https://uams.edu">UAMS</a>');
});

it('keeps a mailto link', function (): void {
    expect(sanitizeTableCell('<a href="mailto:a@b.com">mail</a>'))
        ->toBe('<a href="mailto:a@b.com">mail</a>');
});

it('keeps basic emphasis and inline tags', function (string $html): void {
    expect(sanitizeTableCell($html))->toBe($html);
})->with([
    '<strong>bold</strong>',
    '<em>emphasis</em>',
    '<b>b</b>',
    '<i>i</i>',
    '<u>u</u>',
    '<sub>2</sub>',
    '<sup>2</sup>',
    '<code>code</code>',
    '<abbr title="t">A</abbr>',
    'before<br />after',
]);

// ---
// Service: dangerous markup is neutralized
// ---

it('strips a script tag but keeps its sibling text', function (): void {
    expect(sanitizeTableCell('<script>alert(1)</script>safe'))->toBe('safe');
});

it('drops an img with an onerror handler entirely', function (): void {
    expect(sanitizeTableCell('<img src=x onerror=alert(1)>'))->toBe('');
});

it('strips an event-handler attribute from an allowed tag', function (): void {
    expect(sanitizeTableCell('<a href="https://x.com" onclick="evil()">y</a>'))
        ->toBe('<a href="https://x.com">y</a>');
});

it('removes a javascript: scheme from a link', function (): void {
    $out = sanitizeTableCell('<a href="javascript:alert(1)">x</a>');

    expect($out)->not->toContain('javascript:')
        ->and($out)->toBe('<a>x</a>');
});

it('removes a data: scheme from a link', function (): void {
    $out = sanitizeTableCell('<a href="data:text/html,<script>alert(1)</script>">d</a>');

    expect($out)->not->toContain('data:')
        ->and($out)->not->toContain('<script>');
});

it('strips a scheme outside the allowlist that HTMLPurifier would otherwise permit', function (): void {
    // `ftp:` is allowed by HTMLPurifier's defaults but excluded from our
    // narrower allowlist (http/https/mailto/tel) — proving the allowlist applies.
    $out = sanitizeTableCell('<a href="ftp://files.example.com/x">f</a>');

    expect($out)->not->toContain('ftp:')
        ->and($out)->toBe('<a>f</a>');
});

it('unwraps a disallowed block element to its text', function (): void {
    expect(sanitizeTableCell('<div class="x" style="color:red">block</div>'))->toBe('block');
});

// ---
// Service: link target/rel hardening
// ---

it('keeps target=_blank and adds rel noopener noreferrer', function (): void {
    $out = sanitizeTableCell('<a href="https://x.com" target="_blank">ext</a>');

    expect($out)->toContain('target="_blank"')
        ->and($out)->toContain('noopener')
        ->and($out)->toContain('noreferrer');
});

it('rejects a non-blank frame target', function (): void {
    $out = sanitizeTableCell('<a href="https://x.com" target="_top">x</a>');

    expect($out)->not->toContain('target=');
});

// ---
// Service: plain text and non-string inputs
// ---

it('passes plain text through unchanged', function (): void {
    expect(sanitizeTableCell('plain text'))->toBe('plain text');
});

it('entity-encodes bare ampersands and angle brackets', function (): void {
    expect(sanitizeTableCell('Smith & Co < Ltd'))->toBe('Smith &amp; Co &lt; Ltd');
});

it('returns an empty string for null, array and empty input', function (mixed $value): void {
    expect(sanitizeTableCell($value))->toBe('');
})->with([
    'null' => [null],
    'array' => [['x']],
    'empty' => [''],
]);

it('coerces a numeric cell to its string form', function (): void {
    expect(sanitizeTableCell(42))->toBe('42');
});

it('is idempotent', function (string $html): void {
    $once = sanitizeTableCell($html);

    expect(sanitizeTableCell($once))->toBe($once);
})->with([
    '<a href="https://x.com" target="_blank">ext</a>',
    '<script>alert(1)</script>safe',
    'Smith & Co < Ltd',
    '<strong>bold</strong> & <em>plain</em>',
]);

// ---
// Antlers modifier wiring
// ---

it('is registered as the sanitize_table_cell Antlers modifier', function (): void {
    $out = (string) Antlers::parse(
        '{{ value | sanitize_table_cell }}',
        ['value' => '<a href="https://x.com" onclick="evil()">y</a><script>alert(1)</script>']
    );

    expect($out)->toBe('<a href="https://x.com">y</a>');
});

// ---
// Real render partial (resources/views/sets/_table.antlers.html)
// ---

it('sanitizes every cell position in the table set partial', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:sets/table }}',
        [
            'create_query' => 'manual',
            'caption' => 'Officers',
            'first_row_headers' => true,
            'first_column_headers' => true,
            'last_row_footers' => true,
            'striped' => false,
            'fixed_width_table_cells' => false,
            'align' => '',
            'table_data' => [
                ['cells' => ['<a href="https://uams.edu">Name</a>', '<script>alert(1)</script>Role']],
                ['cells' => ['<a href="javascript:alert(1)">Body</a>', '<strong>ok</strong>']],
                ['cells' => ['Foot<img src=x onerror=alert(1)>', 'End']],
            ],
        ]
    );

    // Header link survives (thead <th scope="col">)
    expect($html)->toContain('<a href="https://uams.edu">Name</a>')
        // Header script is stripped, sibling text remains
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('Role')
        // Body emphasis survives, javascript: scheme stripped
        ->and($html)->toContain('<strong>ok</strong>')
        ->and($html)->not->toContain('javascript:')
        // Footer img with onerror is dropped, sibling text remains
        ->and($html)->not->toContain('onerror')
        ->and($html)->toContain('Foot');
});
