<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseSubtreeArticleIds;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(KnowledgeBaseSubtreeArticleIds::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * The KB search subtree resolver (UAMS-Web/uams-statamic-archive#506) flattens a root's structure subtree to its
 * descendant ids, then narrows them to published articles with one bulk query.
 *
 * @command  php artisan test --compact tests/Feature/Services/KnowledgeBase/KnowledgeBaseSubtreeArticleIdsTest.php
 */

/**
 * Build a structure-page double that reports the given id.
 */
function kbSubtreePage(mixed $id): object
{
    $page = Mockery::mock();
    $page->shouldReceive('id')->andReturn($id);

    return $page;
}

/**
 * Stub Entry::find($rootId) with a root entry whose structure page flattens to the
 * given descendant pages (drives App\Support\ContentDescendantIds::forEntry).
 *
 * @param  array<int, object>  $descendantPages  The flattened descendant page doubles
 */
function kbStubRootWithDescendants(string $rootId, array $descendantPages): void
{
    // The root's structure page must be a real Page instance for forEntry's guard.
    $page = Mockery::mock(Page::class);
    $page->shouldReceive('flattenedPages')->andReturn(collect($descendantPages));

    // The root entry exposes that page and is a real Entry for the forEntry assert.
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('page')->andReturn($page);

    EntryFacade::shouldReceive('find')->with($rootId)->andReturn($root);
}

/**
 * Stub Entry::query() with a builder double asserting the bulk article query is
 * scoped to the knowledge_bases collection, the exact descendant ids, the article
 * blueprint, and published status — returning the given article entries.
 *
 * @param  list<string>  $expectedIds  The descendant ids the query must be scoped to
 * @param  array<int, object>  $articleEntries  The article entry doubles get() returns
 */
function kbStubArticleQuery(array $expectedIds, array $articleEntries): void
{
    $builder = Mockery::mock();
    $builder->shouldReceive('where')->with('collection', 'knowledge_bases')->once()->andReturnSelf();
    $builder->shouldReceive('whereIn')->with('id', $expectedIds)->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('blueprint', 'article')->once()->andReturnSelf();
    $builder->shouldReceive('whereStatus')->with('published')->once()->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn(collect($articleEntries));

    EntryFacade::shouldReceive('query')->once()->andReturn($builder);
}

/**
 * Build an article entry double that reports the given id.
 */
function kbArticleEntry(mixed $id): object
{
    $article = Mockery::mock(Entry::class);
    $article->shouldReceive('id')->andReturn($id);

    return $article;
}

it('returns the published article ids within the root subtree', function (): void {
    // The root flattens to three descendants (two topics, one article node).
    kbStubRootWithDescendants('kb-root', [
        kbSubtreePage('topic-1'),
        kbSubtreePage('topic-2'),
        kbSubtreePage('article-1'),
    ]);

    // The bulk query, scoped to every descendant id, returns the published articles.
    kbStubArticleQuery(
        expectedIds: ['topic-1', 'topic-2', 'article-1'],
        articleEntries: [kbArticleEntry('article-1'), kbArticleEntry('article-2')],
    );

    $ids = (new KnowledgeBaseSubtreeArticleIds)->forRoot('kb-root');

    expect($ids)->toBe(['article-1', 'article-2']);
});

it('drops article entries that report a non-string or empty id', function (): void {
    // A well-formed subtree with one descendant.
    kbStubRootWithDescendants('kb-root', [kbSubtreePage('article-1')]);

    // The query returns one real article plus one null id and one empty-string id.
    kbStubArticleQuery(
        expectedIds: ['article-1'],
        articleEntries: [kbArticleEntry('article-1'), kbArticleEntry(null), kbArticleEntry('')],
    );

    // Only the non-empty string id survives — the null and empty ids are filtered out.
    expect((new KnowledgeBaseSubtreeArticleIds)->forRoot('kb-root'))->toBe(['article-1']);
});

it('returns an empty list without querying when the root has no descendants', function (): void {
    // The root's flattened subtree is empty (a leaf or empty KB/topic).
    kbStubRootWithDescendants('kb-leaf', []);

    // No bulk query runs when there are no descendants to narrow.
    EntryFacade::shouldReceive('query')->never();

    expect((new KnowledgeBaseSubtreeArticleIds)->forRoot('kb-leaf'))->toBe([]);
});

it('returns an empty list without querying when the root cannot be resolved', function (): void {
    // Entry::find yields no entry for the id (a deleted or tampered root).
    EntryFacade::shouldReceive('find')->with('missing-root')->andReturn(null);

    // No bulk query runs without a resolved structure subtree.
    EntryFacade::shouldReceive('query')->never();

    expect((new KnowledgeBaseSubtreeArticleIds)->forRoot('missing-root'))->toBe([]);
});
