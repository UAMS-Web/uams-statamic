<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Entry;
use Tests\Support\ContentSubmodule;
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

it('renders a cell entry link as the entry URL, never the statamic:// reference (#518)', function () {
    // The add-on's `sanitize_table_cell` resolves the reference from v0.2.0
    // (UAMS-Web/aether-tables#53); before that the sanitizer dropped the
    // unknown scheme and the link rendered with no target.
    if (! ContentSubmodule::isInitialized()) {
        ContentSubmodule::reportMissing('a published page to link to from a table cell');
    }

    // A published page with a URL: the resolver links only those
    $id = null;
    $url = null;
    /** @var iterable<Statamic\Entries\Entry> $candidates */
    $candidates = Entry::whereCollection('pages');
    foreach ($candidates as $candidate) {
        $candidateUrl = $candidate->url();
        $candidateId = $candidate->id();
        if ($candidate->published() && is_string($candidateUrl) && $candidateUrl !== '' && is_string($candidateId)) {
            $id = $candidateId;
            $url = $candidateUrl;
            break;
        }
    }

    if ($id === null || $url === null) {
        throw new RuntimeException('No published page with a URL to link to.');
    }

    $out = (string) Antlers::parse('{{ partial:sets/table }}', [
        'create_query' => 'manual',
        'first_row_headers' => false,
        'table_data' => [
            ['cells' => ['<a href="statamic://entry::'.$id.'">Read more</a>']],
        ],
    ], true);

    expect($out)->toContain('href="'.e($url).'"');
    expect($out)->toContain('Read more');
    expect($out)->not->toContain('statamic://');
});

it('renders nothing when table_data is empty', function () {
    $out = (string) Antlers::parse('{{ partial:sets/table }}', [
        'create_query' => 'manual',
        'first_row_headers' => true,
        'table_data' => [],
    ], true);

    expect($out)->not->toContain('<table');
});
