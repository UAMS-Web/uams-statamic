<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Search;
use Statamic\Search\Comb\Index;
use Statamic\Search\QueryBuilder;
use Tests\TestCase;

/**
 * Each test starts with no search index, whatever the previous test in the process left (#572).
 *
 * The per-process index directory used to outlive every test, so a test that saved or deleted
 * entries left `default` built from its own Stache, and a later real search read that. A delete
 * that emptied the index left `[]`, on which Comb throws `Empty haystack`.
 *
 * The two tests below run in order in one process, as Pest runs a file. The first leaves the
 * empty index; the second boots after it and searches the real index. Run alone, the second
 * passes trivially, so it is the pair that carries the regression.
 *
 * @command  php artisan test --compact tests/Feature/Search/SearchIndexPerTestResetTest.php
 */
uses(TestCase::class);

function perTestResetDefaultIndex(): Index
{
    return mustBe(Index::class, Search::index('default'), 'the default search index');
}

function perTestResetDefaultIndexPath(): string
{
    return mustBeString(perTestResetDefaultIndex()->path(), 'the default search index path');
}

it('leaves an empty default index behind, as an earlier test that deleted entries could', function (): void {
    $path = perTestResetDefaultIndexPath();

    File::ensureDirectoryExists(dirname($path));
    File::put($path, '[]');

    expect(File::get($path))->toBe('[]');
});

it('starts with no index and searches one built from the booted content', function (): void {
    $index = perTestResetDefaultIndex();

    // Searched before anything else touches the index: with the previous test's `[]` still in
    // place, this is the call that throws `Empty haystack`.
    $built = mustBe(Index::class, $index->ensureExists(), 'the built default index');
    $results = mustBe(QueryBuilder::class, $built->search('uams'), 'the search query')->get();

    /** @var array<string, mixed> $builtDocuments */
    $builtDocuments = json_decode(File::get(perTestResetDefaultIndexPath()), true, flags: JSON_THROW_ON_ERROR);
    $builtCount = count($builtDocuments);

    // The same index a fresh build from this test's content produces, not a partial one.
    $index->update();

    /** @var array<string, mixed> $fresh */
    $fresh = json_decode(File::get(perTestResetDefaultIndexPath()), true, flags: JSON_THROW_ON_ERROR);

    expect($builtCount)->toBeGreaterThan(0)
        ->and($builtCount)->toBe(count($fresh))
        ->and($results)->toBeInstanceOf(Collection::class);
});
