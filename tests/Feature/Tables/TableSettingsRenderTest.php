<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the Table set's setting wiring in the render partial (UAMS-Web/uams-statamic-archive#231).
 *
 * `resources/views/sets/_table.antlers.html` resolves the style and feature
 * toggles (from the Bard set or the linked table entry) and emits them as
 * classes on the `<table>` and `data-table-*` attributes the front-end engine
 * consumes. This pins which markup each setting produces, including the
 * `table-striped` placement fix (it now sits on the `<table>`, where Bootstrap's
 * zebra-striping actually applies, not the responsive wrapper).
 *
 * No mutable PHP surface: the system under test is the Antlers partial; the JS
 * behavior is covered by `resources/js/table-features-engine.test.js`.
 *
 * @param  array<string, mixed>  $settings
 *
 * @command  php artisan test --compact tests/Feature/Tables/TableSettingsRenderTest.php
 */
function renderTableSet(array $settings): string
{
    return (string) Antlers::parse(
        '{{ partial:sets/table }}',
        array_merge(
            [
                'create_query' => 'manual',
                'caption' => 'Demo',
                'first_row_headers' => false,
                'first_column_headers' => false,
                'last_row_footers' => false,
                'align' => '',
                'table_data' => [['cells' => ['A', 'B']], ['cells' => ['1', '2']]],
            ],
            $settings
        )
    );
}

/** Extract the opening `<table ...>` tag from rendered output. */
function tableTag(string $html): string
{
    expect($html)->toMatch('/<table\b[^>]*>/s');
    preg_match('/<table\b[^>]*>/s', $html, $matches);

    return (string) preg_replace('/\s+/', ' ', $matches[0] ?? '');
}

// ---
// Feature data-attributes
// ---

it('emits no feature attributes when every feature is off', function (): void {
    $tag = tableTag(renderTableSet([]));

    expect($tag)->not->toContain('data-table-features')
        ->and($tag)->not->toContain('data-table-sort')
        ->and($tag)->not->toContain('data-table-search')
        ->and($tag)->not->toContain('data-table-paginate')
        ->and($tag)->not->toContain('data-table-info');
});

it('emits every feature attribute when all are on', function (): void {
    $tag = tableTag(renderTableSet([
        'first_row_headers' => true,
        'sorting_enable' => true,
        'search_enable' => true,
        'pagination_enable' => true,
        'pagination_count' => 25,
        'pagination_change_enable' => true,
        'info_enable' => true,
    ]));

    expect($tag)->toContain('data-table-features')
        ->and($tag)->toContain('data-table-sort')
        ->and($tag)->toContain('data-table-search')
        ->and($tag)->toContain('data-table-paginate')
        ->and($tag)->toContain('data-table-page-length="25"')
        ->and($tag)->toContain('data-table-page-length-adjustable')
        ->and($tag)->toContain('data-table-info');
});

it('defaults the page length to 10 when pagination is on without a count', function (): void {
    expect(tableTag(renderTableSet(['pagination_enable' => true])))
        ->toContain('data-table-page-length="10"');
});

it('does not emit the sort attribute without a header row', function (): void {
    $tag = tableTag(renderTableSet([
        'first_row_headers' => false,
        'sorting_enable' => true,
    ]));

    // The table is still interactive (sorting requested) but sort needs headers
    expect($tag)->toContain('data-table-features')
        ->and($tag)->not->toContain('data-table-sort');
});

// ---
// Style classes
// ---

it('places table-striped on the table element, not the wrapper', function (): void {
    $html = renderTableSet(['striped' => true]);

    // The fix: striping is on <table>, where Bootstrap applies it
    expect(tableTag($html))->toContain('table-striped');
    // ...and not on the responsive wrapper div
    expect($html)->not->toMatch('/<div\s+class="[^"]*table-responsive[^"]*table-striped/s');
});

it('adds table-hover only when row hover highlighting is on', function (): void {
    expect(tableTag(renderTableSet(['row_hover_highlighting' => true])))->toContain('table-hover');
    expect(tableTag(renderTableSet([])))->not->toContain('table-hover');
});

// ---
// Horizontal scroll wrapper
// ---

it('keeps the responsive wrapper by default and when scrolling is on', function (mixed $value): void {
    expect(renderTableSet($value === 'unset' ? [] : ['horizontal_scroll_enable' => $value]))
        ->toContain('table-responsive');
})->with([
    'unset' => ['unset'],
    'true' => [true],
]);

it('drops the responsive wrapper when horizontal scrolling is off', function (): void {
    expect(renderTableSet(['horizontal_scroll_enable' => false]))
        ->not->toContain('table-responsive');
});

// ---
// Collection mode resolves settings from the linked table entry
// ---

it('resolves the feature settings from the linked table entry', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:sets/table }}',
        [
            'create_query' => 'collection',
            'heading_query' => false,
            'description_query' => false,
            'align' => '',
            // The `table_entry` field is `max_items: 1`, so it augments to a
            // single entry whose `$table_*` assignments leak to the parent scope.
            'table_entry' => [
                'id' => 'tbl-1',
                'published' => true,
                'title' => 'Officers',
                'description' => '',
                'caption' => 'Officers',
                'first_row_headers' => true,
                'first_column_headers' => false,
                'last_row_footers' => false,
                'table_data' => [['cells' => ['Name', 'Role']], ['cells' => ['Jane', 'Chair']]],
                'striped' => true,
                'row_hover_highlighting' => true,
                'sorting_enable' => true,
                'search_enable' => true,
                'pagination_enable' => true,
                'pagination_count' => 5,
                'info_enable' => true,
                'horizontal_scroll_enable' => false,
            ],
        ]
    );

    $tag = tableTag($html);

    // Settings came from the entry, not the (empty) Bard-set scope
    expect($tag)->toContain('table-striped')
        ->and($tag)->toContain('table-hover')
        ->and($tag)->toContain('data-table-sort')
        ->and($tag)->toContain('data-table-search')
        ->and($tag)->toContain('data-table-page-length="5"')
        ->and($tag)->toContain('data-table-info')
        // horizontal scroll off on the entry -> no responsive wrapper
        ->and($html)->not->toContain('table-responsive')
        // the entry's data still renders
        ->and($html)->toContain('Jane');
});

// ---
// Column filter dropdowns (UAMS-Web/uams-statamic-archive#243)
// ---

it('emits the column-filter attributes when enabled with specific columns', function (): void {
    $tag = tableTag(renderTableSet([
        'column_filter_enable' => true,
        'column_filter_columns' => 'A,C',
    ]));

    expect($tag)->toContain('data-table-features')
        ->and($tag)->toContain('data-table-filter')
        ->and($tag)->toContain('data-table-filter-columns="A,C"');
});

it('emits data-table-filter without a columns attribute when no columns are set', function (): void {
    $tag = tableTag(renderTableSet([
        'column_filter_enable' => true,
        'column_filter_columns' => '',
    ]));

    expect($tag)->toContain('data-table-filter')
        ->and($tag)->not->toContain('data-table-filter-columns');
});

it('does not emit the column-filter attribute when disabled', function (): void {
    $tag = tableTag(renderTableSet(['column_filter_enable' => false]));

    expect($tag)->not->toContain('data-table-filter');
});

// ---
// Responsive stacked layout (UAMS-Web/uams-statamic-archive#243)
// ---

it('emits the responsive-stack attributes at the chosen breakpoint', function (): void {
    $tag = tableTag(renderTableSet([
        'responsive_stack_enable' => true,
        'responsive_stack_breakpoint' => 'tablet',
    ]));

    expect($tag)->toContain('data-table-features')
        ->and($tag)->toContain('data-table-responsive-stack')
        ->and($tag)->toContain('data-table-responsive-breakpoint="tablet"');
});

it('defaults the responsive-stack breakpoint to phone', function (): void {
    $tag = tableTag(renderTableSet(['responsive_stack_enable' => true]));

    expect($tag)->toContain('data-table-responsive-breakpoint="phone"');
});

it('does not emit the responsive-stack attribute when disabled', function (): void {
    $tag = tableTag(renderTableSet(['responsive_stack_enable' => false]));

    expect($tag)->not->toContain('data-table-responsive-stack');
});

it('entity-encodes an injection-y filter-columns value in the attribute', function (): void {
    $tag = tableTag(renderTableSet([
        'column_filter_enable' => true,
        'column_filter_columns' => 'A"><script>',
    ]));

    // Editor-controlled free text is HTML-encoded, so it cannot break out of the
    // attribute (defense-in-depth against a stored-XSS via the Bard set).
    expect($tag)->not->toContain('"><script>')
        ->and($tag)->toContain('&quot;');
});
