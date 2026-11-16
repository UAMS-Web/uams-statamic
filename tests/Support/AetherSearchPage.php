<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Facades\Search;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder as SearchQueryBuilder;

/**
 * Render the add-on's search page for a query string.
 *
 * The page reads the live request through the `aether_search` tag rather than a view's
 * `get`, so the query goes on the request.
 *
 * @param  array<array-key, mixed>  $query  The query string.
 * @return string The rendered page.
 */
function renderAetherSearchPage(array $query = [], string $path = '/search'): string
{
    app()->instance('request', Request::create($path, 'GET', $query));

    return view('aether-search::index')->render();
}

/**
 * Answer every search with no hits, so a page with a query renders its empty state
 * without an index build.
 */
function stubEmptySearchIndex(): void
{
    $index = Mockery::mock(Index::class);
    $index->shouldReceive('name')->andReturn('default');
    $index->shouldReceive('ensureExists')->andReturnSelf();
    $index->shouldReceive('extraAugmentedResultData')->andReturn([]);
    $index->shouldReceive('search')->andReturnUsing(function (string $query) use ($index): SearchQueryBuilder {
        $builder = new class($index) extends SearchQueryBuilder
        {
            /**
             * @return list<array{reference: string, search_score: int}>
             */
            public function getSearchResults(mixed $query): array
            {
                return [];
            }
        };
        $builder->query($query);

        return $builder;
    });

    Search::shouldReceive('index')->andReturn($index);
}
