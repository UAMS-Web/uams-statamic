<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for UAMS-Web/uams-statamic-archive#1354: the UAMS Gallery page-builder block's column-sizing logic.
 *
 * The block's variable section (which runs on every render, before the
 * `$block_valid` guard) formerly did `$gallery_col_sm += 6` on the class-prefix
 * string `'col-sm-'` — an `ANTLR_003` numeric-type fault that crashed every
 * render — and then reused the same variable as a `12 / N` divisor, two
 * mutually exclusive representations. The fix splits them: `$gallery_col_*`
 * holds the Bootstrap class string, `$gallery_cols_*` the numeric
 * columns-per-row the `gallery_image_picture` partial needs for its srcset math.
 *
 * This renders the actual column-mapping block extracted from the partial (so a
 * regression in the real file is caught) for every selectable column count, and
 * pins the class + columns-per-row it produces. The no-throw guarantee for the
 * full partial is covered hermetically by PageBuilderSetPartialsRenderTest.
 *
 * @command  php artisan test --compact tests/Feature/Views/GalleryBlockColumnsTest.php
 */
function galleryBlockPartial(): string
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../../resources/views/page_builder/_uamsweb_block_gallery.antlers.html');

    return (string) file_get_contents($path);
}

/** The `{{ if $gallery_columns … }} … {{ /if }}` column-mapping block, verbatim from the partial. */
function galleryColumnLogic(): string
{
    $source = galleryBlockPartial();
    $start = strpos($source, '{{ if $gallery_columns');
    /** @var int<0, max> $start */
    $end = strpos($source, '{{ /if }}', $start);
    /** @var int<0, max> $end */
    expect($start)->not->toBeFalse('column-mapping if-block not found in the partial');
    expect($end)->not->toBeFalse('column-mapping {{ /if }} not found in the partial');

    return substr($source, $start, $end - $start + strlen('{{ /if }}'));
}

/**
 * Render the real column-mapping block for a column selection and return the
 * resolved [class, cols-per-row] per breakpoint. The value is bound through
 * `block:columns` (not string-interpolated) so its true type is preserved —
 * the radio's YAML-integer keys reach the partial as integers, and the loose
 * `==` branch selector must match them identically to their string form. A
 * `null` selection exercises the `?? '3'` default (field absent).
 *
 * @param  int|string|null  $columns  The selected column count, in its stored type.
 * @return array{sm: array{class: string, cols: string}, md: array{class: string, cols: string}, lg: array{class: string, cols: string}}
 */
function resolveGalleryColumns(int|string|null $columns): array
{
    $template = "{{ \$gallery_columns = block:columns ?? '3' }}\n"
        .galleryColumnLogic()."\n"
        .'{{ $gallery_col_sm }}|{{ $gallery_col_md }}|{{ $gallery_col_lg }}'
        .'|{{ $gallery_cols_sm }}|{{ $gallery_cols_md }}|{{ $gallery_cols_lg }}';

    $data = $columns === null ? [] : ['block' => ['columns' => $columns]];

    [$sm, $md, $lg, $colsSm, $colsMd, $colsLg] = explode('|', trim((string) Antlers::parse($template, $data, true)));

    return [
        'sm' => ['class' => $sm, 'cols' => $colsSm],
        'md' => ['class' => $md, 'cols' => $colsMd],
        'lg' => ['class' => $lg, 'cols' => $colsLg],
    ];
}

it('renders the correct column classes and columns-per-row for each selection', function (
    int|string $columns,
    array $expected,
): void {
    expect(resolveGalleryColumns($columns))->toBe($expected);
})->with(function (): array {
    // Expected [class, cols-per-row] per breakpoint, keyed by column count.
    $expected = [
        // 2-up everywhere.
        '2' => [
            'sm' => ['class' => 'col-sm-6', 'cols' => '2'],
            'md' => ['class' => 'col-md-6', 'cols' => '2'],
            'lg' => ['class' => 'col-lg-6', 'cols' => '2'],
        ],
        // 2-up through md, 3-up at lg.
        '3' => [
            'sm' => ['class' => 'col-sm-6', 'cols' => '2'],
            'md' => ['class' => 'col-md-6', 'cols' => '2'],
            'lg' => ['class' => 'col-lg-4', 'cols' => '3'],
        ],
        // 2-up through md, 4-up at lg.
        '4' => [
            'sm' => ['class' => 'col-sm-6', 'cols' => '2'],
            'md' => ['class' => 'col-md-6', 'cols' => '2'],
            'lg' => ['class' => 'col-lg-3', 'cols' => '4'],
        ],
        // 2-up on sm, 3-up on md, 6-up at lg.
        '6' => [
            'sm' => ['class' => 'col-sm-6', 'cols' => '2'],
            'md' => ['class' => 'col-md-4', 'cols' => '3'],
            'lg' => ['class' => 'col-lg-2', 'cols' => '6'],
        ],
    ];

    // Cover both the string form (imported/stored values arrive as strings) and
    // the integer form (the radio's YAML-integer keys). The loose `==` selector
    // must treat them identically, so a future strict-equality regression that
    // rendered an int-valued 6-column gallery as 3-up is caught either way.
    $cases = [];
    foreach ($expected as $key => $breakpoints) {
        $cases["{$key} columns (string)"] = [$key, $breakpoints];
        $cases["{$key} columns (int)"] = [(int) $key, $breakpoints];
    }

    return $cases;
});

it('falls back to the 3-column layout for an unexpected or missing selection', function (int|string|null $columns): void {
    // The field default is '3'; any value outside 2/3/4/6 — and an absent field
    // (the `?? '3'` default) — lands in the else branch.
    expect(resolveGalleryColumns($columns))->toBe([
        'sm' => ['class' => 'col-sm-6', 'cols' => '2'],
        'md' => ['class' => 'col-md-6', 'cols' => '2'],
        'lg' => ['class' => 'col-lg-4', 'cols' => '3'],
    ]);
})->with([
    'out-of-range string' => '5',
    'out-of-range int' => 5,
    'field absent (null)' => null,
]);

it('no longer contains the double-dollar variable typo', function (): void {
    // The `$$gallery_col_*` half-refactor (UAMS-Web/uams-statamic-archive#1354) is gone.
    expect(galleryBlockPartial())->not->toContain('$$');
});

it('wires the class strings into the grid and the counts into the picture partial', function (): void {
    $source = galleryBlockPartial();

    // The Bootstrap class strings feed the `| classes` array …
    expect($source)
        ->toContain('$gallery_col_sm')
        ->toContain('$gallery_col_md')
        ->toContain('$gallery_col_lg')
        // … and the numeric columns-per-row feed the picture partial's srcset math.
        ->toContain(':cols_sm="$gallery_cols_sm"')
        ->toContain(':cols_md="$gallery_cols_md"')
        ->toContain(':cols_lg="$gallery_cols_lg"')
        // The old string-arithmetic divisor form is gone.
        ->and($source)->not->toContain('12 / $gallery_col');
});
