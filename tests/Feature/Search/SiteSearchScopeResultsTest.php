<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder as SearchQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * Which hits each `/search` scope returns (UAMS-Web/uams-statamic#413), through the
 * shared results listing and the real `search:results` tag.
 *
 * The index is stubbed to return every entry by reference, so the tag's own site,
 * status and scope conditions decide what is left. `SiteSearchScopeTest` covers the
 * choice itself.
 *
 * @command  php artisan test --compact tests/Feature/Search/SiteSearchScopeResultsTest.php
 */

/**
 * Save a published entry on a site.
 *
 * @param  string|null  $visibility  A `status_and_visibility` value, such as `private`.
 * @param  StatamicEntry|null  $origin  The entry this one localizes, which it inherits from.
 */
function scopedSearchEntry(string $site, string $slug, ?string $title, ?string $visibility = null, ?StatamicEntry $origin = null): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale($site);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(array_filter(['title' => $title, 'status_and_visibility' => $visibility]));

    if ($origin instanceof StatamicEntry) {
        $entry->origin($origin);
    }

    $entry->saveQuietly();

    return $entry;
}

/**
 * Make the search index return these entries by reference for any query.
 *
 * @param  list<StatamicEntry>  $entries
 */
function stubScopedSearchIndex(array $entries): void
{
    $ids = array_map(static fn (StatamicEntry $entry): string => mustBeString($entry->id(), 'id'), $entries);

    $index = Mockery::mock(Index::class);
    $index->shouldReceive('name')->andReturn('default');
    $index->shouldReceive('ensureExists')->andReturnSelf();
    $index->shouldReceive('extraAugmentedResultData')->andReturn([]);
    $index->shouldReceive('search')->andReturnUsing(function (string $query) use ($index, $ids): SearchQueryBuilder {
        $builder = new class($index, $ids) extends SearchQueryBuilder
        {
            /**
             * @param  list<string>  $ids
             */
            public function __construct(Index $index, private readonly array $ids)
            {
                parent::__construct($index);
            }

            /**
             * @return list<array{reference: string, search_score: int}>
             */
            public function getSearchResults(mixed $query): array
            {
                return array_map(static fn (string $id): array => ['reference' => 'entry::'.$id, 'search_score' => 1], $this->ids);
            }
        };
        $builder->query($query);

        return $builder;
    });
    Search::shouldReceive('index')->andReturn($index);
}

/**
 * Render the results listing as a reader on `second` sees it.
 *
 * @param  string|null  $searchSite  The partial's `search_site`, as the page passes it.
 * @param  array<string, string>  $params  Further partial parameters.
 */
function renderScopedResults(?string $searchSite, array $params = [], string $query = 'q=kirk'): string
{
    Site::setCurrent('second');
    app()->instance('request', Request::create('/second/search?'.$query));

    return (string) Antlers::parse(
        '{{ partial:search/results index="default" :search_site="search_site" :all_results_url="all_results_url" :wider_scope_url="wider_scope_url" :wider_scope_label="wider_scope_label" }}',
        $params + ['search_site' => $searchSite, 'all_results_url' => '', 'wider_scope_url' => '', 'wider_scope_label' => '']
    );
}

beforeEach(function (): void {
    Site::setSites([
        'default' => ['name' => 'Starfleet Academy', 'url' => '/', 'locale' => 'en_US'],
        'second' => ['name' => 'Second', 'url' => '/second/', 'locale' => 'en_US'],
    ]);

    $collection = Collection::make('people');
    $collection->routes('/people/{slug}');
    $collection->sites(['default', 'second']);
    $collection->save();
});

it('searches only the current site at the narrow scope', function (): void {
    stubScopedSearchIndex([
        scopedSearchEntry('default', 'james-kirk', 'James Kirk'),
        scopedSearchEntry('second', 'hikaru-sulu', 'Hikaru Sulu'),
    ]);

    expect(renderScopedResults('second'))
        ->toContain('Hikaru Sulu')
        ->not->toContain('James Kirk');
});

it('falls back to the current site when no scope is passed', function (): void {
    stubScopedSearchIndex([
        scopedSearchEntry('default', 'james-kirk', 'James Kirk'),
        scopedSearchEntry('second', 'hikaru-sulu', 'Hikaru Sulu'),
    ]);

    expect(renderScopedResults(null))
        ->toContain('Hikaru Sulu')
        ->not->toContain('James Kirk');
});

it('returns every site at the wide scope, each hit linking to its own site', function (): void {
    stubScopedSearchIndex([
        scopedSearchEntry('default', 'james-kirk', 'James Kirk'),
        scopedSearchEntry('second', 'hikaru-sulu', 'Hikaru Sulu'),
    ]);

    expect(renderScopedResults('*'))
        ->toContain('James Kirk')
        ->toContain('Hikaru Sulu')
        ->toContain('href="/people/james-kirk"')
        ->toContain('href="/second/people/hikaru-sulu"');
});

it('hides a private entry at the wide scope, and a localization that inherits it', function (): void {
    $private = scopedSearchEntry('default', 'janice-rand', 'Janice Rand', 'private');

    stubScopedSearchIndex([
        $private,
        scopedSearchEntry('second', 'janice-rand', null, null, $private),
        scopedSearchEntry('second', 'leonard-mccoy', 'Leonard McCoy', 'published'),
    ]);

    expect(renderScopedResults('*'))
        ->toContain('Leonard McCoy')
        ->not->toContain('Janice Rand');
});

it('offers the wider scope from the empty state only when given one', function (): void {
    stubScopedSearchIndex([]);

    $url = '/second/search?q=kirk&amp;index=default&amp;scope=all';

    expect(renderScopedResults('second', ['wider_scope_url' => $url, 'wider_scope_label' => 'all of Starfleet Academy']))
        ->toContain('<a href="'.$url.'">search all of Starfleet Academy</a>')
        ->and(renderScopedResults('*'))
        ->toContain('No results found')
        ->not->toContain('search all of');
});

it('keeps the scope and the sub-site path in the pagination links', function (): void {
    $entries = [];
    foreach (range(1, 11) as $number) {
        $entries[] = scopedSearchEntry('second', 'ensign-'.$number, 'Ensign '.$number);
    }

    stubScopedSearchIndex($entries);

    // A console run swaps in the `statamic/ssg` paginator, which writes the page number
    // into the path; a request writes `?page=2`. Either way the path and scope survive.
    expect(renderScopedResults('*', [], 'q=kirk&scope=all'))
        ->toMatch('#href="/second/search[^"]*[?&]scope=all[^"]*"[^>]*rel="next"#');
});
