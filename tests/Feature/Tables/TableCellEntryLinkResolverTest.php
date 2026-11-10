<?php

declare(strict_types=1);

use App\Services\Tables\TableCellEntryLinkResolver;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Entry references in table cells render as the entry's URL (UAMS-Web/uams-statamic#582).
 *
 * The importer writes a cell's internal link as `statamic://entry::<id>`, which the
 * cell sanitizer's scheme list strips. {@see TableCellEntryLinkResolver} resolves the
 * reference first, through the `sanitize_table_cell` modifier, so only rendered output
 * changes. Every case renders through the modifier or the real table partial, so
 * removing the resolver from the render path turns the resolution cases red.
 *
 * @command php artisan test --compact tests/Feature/Tables/TableCellEntryLinkResolverTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->routes('/{slug}');
    $pages->save();
});

/**
 * Persist a page entry and return it.
 */
function tableLinkPage(string $id, string $slug, bool $published = true): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->id($id);
    $entry->collection('pages');
    $entry->slug($slug);
    $entry->published($published);
    $entry->data(['title' => ucfirst($slug)]);
    $entry->save();

    return $entry;
}

/**
 * Render one cell through the `sanitize_table_cell` modifier.
 */
function renderTableCell(string $cell): string
{
    return (string) Antlers::parse('{{ value | sanitize_table_cell }}', ['value' => $cell]);
}

it('renders an entry reference as the entry URL', function (): void {
    tableLinkPage('kirk-id', 'kirk');

    expect(renderTableCell('<a href="statamic://entry::kirk-id">Kirk</a>'))
        ->toBe('<a href="/kirk">Kirk</a>');
});

it('keeps the query string and fragment, including an encoded ampersand', function (string $suffix): void {
    tableLinkPage('spock-id', 'spock');

    expect(renderTableCell('<a href="statamic://entry::spock-id'.$suffix.'">Spock</a>'))
        ->toBe('<a href="/spock'.$suffix.'">Spock</a>');
})->with([
    'query and fragment' => '?a=1&amp;b=2#f',
    'fragment only' => '#bridge',
    'query only' => '?deck=5',
]);

it('resolves a single-quoted reference', function (): void {
    tableLinkPage('uhura-id', 'uhura');

    expect(renderTableCell("<a href='statamic://entry::uhura-id'>Uhura</a>"))
        ->toBe('<a href="/uhura">Uhura</a>');
});

it('renders a reference to a missing or unpublished entry as text without an href', function (string $id): void {
    tableLinkPage('chekov-id', 'chekov', published: false);

    $html = renderTableCell('<a href="statamic://entry::'.$id.'#f">Crew</a>');

    expect($html)->toBe('<a>Crew</a>')
        ->and($html)->not->toContain('statamic://');
})->with([
    'missing' => 'no-such-entry',
    'unpublished' => 'chekov-id',
]);

it('still strips javascript and data links', function (string $href): void {
    expect(renderTableCell('<a href="'.$href.'">x</a>'))->toBe('<a>x</a>');
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
]);

it('leaves the stored cell holding the reference after rendering', function (): void {
    tableLinkPage('sulu-id', 'sulu');
    $cell = '<a href="statamic://entry::sulu-id">Sulu</a>';

    /** @var EntryItem $host */
    $host = Entry::make();
    $host->id('scotty-id');
    $host->collection('pages');
    $host->slug('scotty');
    $host->data(['title' => 'Scotty', 'table_data' => [['cells' => [$cell]]]]);
    $host->save();

    $html = (string) Antlers::parse('{{ partial:sets/table }}', [
        'create_query' => 'manual',
        'caption' => 'Crew',
        'first_row_headers' => false,
        'first_column_headers' => false,
        'last_row_footers' => false,
        'striped' => false,
        'fixed_width_table_cells' => false,
        'align' => '',
        'table_data' => $host->get('table_data'),
    ]);

    /** @var EntryItem $reloaded */
    $reloaded = Entry::find('scotty-id');

    expect($html)->toContain('<a href="/sulu">Sulu</a>')
        ->and($reloaded->get('table_data'))->toBe([['cells' => [$cell]]]);
});

it('passes non-string and reference-free values through unchanged', function (mixed $value): void {
    expect(app(TableCellEntryLinkResolver::class)->resolve($value))->toBe($value);
})->with([
    'null' => [null],
    'int' => [42],
    'plain link' => ['<a href="https://uams.edu">UAMS</a>'],
]);
