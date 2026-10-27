<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for resources/views/sets/_table.antlers.html (Bard table set). Guards the
 * `{{ if $table_data }}` gate that decides whether any `<table>` is emitted: the
 * set's manual rows live under `table_data`; content keyed under the legacy `table`
 * handle leaves `table_data` empty and renders nothing (the bug fixed in UAMS-Web/uams-statamic-archive#934).
 * Antlers template partial — no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/TableSetPartialTest.php
 */
it('renders a populated table from table_data with the first row promoted to a header', function () {
    $out = (string) Antlers::parse('{{ partial:sets/table }}', [
        'create_query' => 'manual',
        'align' => 'alignwide',
        'striped' => true,
        'first_row_headers' => true,
        'first_column_headers' => false,
        'last_row_footers' => false,
        'table_data' => [
            ['cells' => ['Type of Program', 'Description', 'Start Date', 'Primary Clinical Site']],
            ['cells' => ['Internal Medicine', 'Three-year program; eight residents per year, 24 total', 'Jul 1, 2023', 'Washington Regional']],
        ],
    ], true);

    expect($out)->toContain('<table');
    expect($out)->toContain('<thead>');
    expect($out)->toContain('<th scope="col">Type of Program</th>');
    expect($out)->toContain('<tbody>');
    expect($out)->toContain('Internal Medicine');
});

it('renders nothing when table_data is empty', function () {
    $out = (string) Antlers::parse('{{ partial:sets/table }}', [
        'create_query' => 'manual',
        'first_row_headers' => true,
        'table_data' => [],
    ], true);

    expect($out)->not->toContain('<table');
});
