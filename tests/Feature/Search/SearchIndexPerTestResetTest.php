<?php

declare(strict_types=1);

// cspell:ignore quadrotriticale Tribble

use App\Search\Loupe\SiteFilteredLoupeIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Search;
use Tests\TestCase;

/**
 * Each test starts with no search index, whatever the previous test in the process left (#572).
 *
 * The per-process index directory used to outlive every test, so a test that saved or deleted
 * entries left `default` built from its own Stache, and a later real search read that. The
 * `default` index is a Loupe database since #646, and it lives under the same per-process
 * directory, so the reset covers it too.
 *
 * The two tests below run in order in one process, as Pest runs a file. The first leaves a
 * `default` index holding a document no content has; the second boots after it and searches the
 * real index. Run alone, the second passes trivially, so it is the pair that carries the
 * regression.
 *
 * @command  php artisan test --compact tests/Feature/Search/SearchIndexPerTestResetTest.php
 */
uses(TestCase::class);

function perTestResetDefaultIndex(): SiteFilteredLoupeIndex
{
    return mustBe(SiteFilteredLoupeIndex::class, Search::index('default'), 'the default search index');
}

/**
 * The raw hits the default index returns for a query, without loading their entries.
 *
 * @return Collection<array-key, mixed>
 */
function perTestResetHits(SiteFilteredLoupeIndex $index, string $query): Collection
{
    $builder = $index->search($query);
    $builder->withoutData();

    return mustBe(Collection::class, $builder->get(), 'the search hits');
}

it('leaves a default index behind holding a document no content has, as an earlier test could', function (): void {
    $index = perTestResetDefaultIndex();

    $index->client()->addDocuments([['id' => 'entry::not-in-any-content', 'title' => 'Tribble quadrotriticale']]);

    expect(File::exists($index->path()))->toBeTrue()
        ->and(perTestResetHits($index, 'quadrotriticale'))->toHaveCount(1);
});

it('starts with no index and searches one built from the booted content', function (): void {
    $index = perTestResetDefaultIndex();

    // Nothing is on disk before this test touches the index: the previous test's database is gone
    expect(File::exists($index->path()))->toBeFalse();

    $index->ensureExists();

    expect(perTestResetHits($index, 'quadrotriticale'))->toBeEmpty()
        ->and(perTestResetHits($index, 'uams'))->not->toBeEmpty();
});
