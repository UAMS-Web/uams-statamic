<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher
 *
 * Every collaborator is mocked: the entry query, the structure pages, the `uri` index, the
 * per-request URI caches and the collection that rebuilds the index. The listener that calls
 * this service is tests/Feature/Listeners/RefreshKnowledgeBaseMountUrisTest.php, which mocks
 * the service itself.
 *
 * @command  php artisan test --compact tests/Feature/Services/KnowledgeBase/KnowledgeBaseMountUriRefresherTest.php
 */

/**
 * A knowledge base root entry double.
 *
 * @param  list<string|null>|null  $pageIds  The structure page ids under it, or null for no page.
 */
function kbRoot(string $id, mixed $mount, ?array $pageIds, ?string $uri = null): Entry&MockInterface
{
    $root = Mockery::mock(Entry::class);
    $root->shouldReceive('id')->andReturn($id);
    $root->shouldReceive('get')->with(KnowledgeBaseEntry::MOUNT_FIELD)->andReturn($mount);
    $root->shouldReceive('uri')->andReturn($uri);

    if ($pageIds === null) {
        $root->shouldReceive('page')->andReturn(null);
    } else {
        $page = Mockery::mock(Page::class);
        $page->shouldReceive('flattenedPages')->andReturn(collect(array_map(fn (?string $pageId): array => ['id' => $pageId], $pageIds)));
        $root->shouldReceive('page')->andReturn($page);
    }

    return $root;
}

/**
 * Route the knowledge base root query to the given roots.
 *
 * @param  list<Entry>  $roots
 */
function kbRootsQuery(array $roots): void
{
    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->shouldReceive('where')->with('collection', 'knowledge_bases')->andReturnSelf();
    $query->shouldReceive('where')->with('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT)->andReturnSelf();
    $query->shouldReceive('get')->andReturn(collect($roots));

    EntryFacade::shouldReceive('query')->andReturn($query);
}

/**
 * Serve the stored `uri` index values by entry id.
 *
 * @param  array<string, string|null>  $uris
 */
function kbIndexedUris(array $uris): void
{
    $index = Mockery::mock();
    $index->shouldReceive('get')->andReturnUsing(fn (string $id): ?string => $uris[$id] ?? null);

    $store = Mockery::mock();
    $store->shouldReceive('index')->with('uri')->andReturn($index);

    $entries = Mockery::mock();
    $entries->shouldReceive('store')->with('knowledge_bases')->andReturn($store);

    Stache::shouldReceive('store')->with('entries')->andReturn($entries);
}

/**
 * Expect both per-request URI caches to be flushed the given number of times.
 */
function kbExpectUriCacheFlushes(int $times): void
{
    foreach (['entry-uris', 'structure-uris'] as $name) {
        $store = Mockery::mock();
        $store->shouldReceive('flush')->times($times);
        Blink::shouldReceive('store')->with($name)->andReturn($store);
    }
}

/**
 * The knowledge_bases collection double, which rebuilds the uri index.
 */
function kbCollection(): StatamicCollection&MockInterface
{
    $collection = Mockery::mock(StatamicCollection::class);
    Collection::shouldReceive('findByHandle')->with('knowledge_bases')->andReturn($collection);

    return $collection;
}

it('rebuilds the uri index for a mounted subtree whose root uri drifted', function (): void {
    kbExpectUriCacheFlushes(1);
    kbRootsQuery([kbRoot('kb-1', 'page-9', ['kb-1', null, 'topic-1', ''], '/health/kb')]);
    kbIndexedUris(['kb-1' => '/kb']);

    // Pages with no id are dropped, and the ids are re-indexed into a list.
    kbCollection()->shouldReceive('updateEntryUris')->once()->with(['kb-1', 'topic-1']);

    (new KnowledgeBaseMountUriRefresher)->refreshAll();
});

it('leaves a subtree alone when its indexed root uri is current', function (): void {
    kbExpectUriCacheFlushes(1);
    kbRootsQuery([kbRoot('kb-1', 'page-9', ['kb-1', 'topic-1'], '/health/kb')]);
    kbIndexedUris(['kb-1' => '/health/kb']);

    kbCollection()->shouldReceive('updateEntryUris')->never();

    (new KnowledgeBaseMountUriRefresher)->refreshAll();
});

it('skips a root that is not mounted, and one with no structure page', function (mixed $mount, ?array $pageIds): void {
    /** @var list<string|null>|null $typedPageIds */
    $typedPageIds = $pageIds;

    kbExpectUriCacheFlushes(1);
    $root = kbRoot('kb-1', $mount, $typedPageIds, '/health/kb');
    kbRootsQuery([$root]);
    kbIndexedUris(['kb-1' => '/kb']);

    if ($mount === null || $mount === '') {
        $root->shouldReceive('page')->never();
    }

    kbCollection()->shouldReceive('updateEntryUris')->never();

    (new KnowledgeBaseMountUriRefresher)->refreshAll();
})->with([
    'not mounted' => [null, ['kb-1']],
    'mounted to nothing' => ['', ['kb-1']],
    'no structure page' => ['page-9', null],
    'a page with no ids' => ['page-9', [null]],
]);

it('re-stamps every mounted root that drifted, not just the first', function (): void {
    kbExpectUriCacheFlushes(1);
    kbRootsQuery([
        kbRoot('kb-1', 'page-9', ['kb-1'], '/a/kb-1'),
        kbRoot('kb-2', 'page-8', ['kb-2', 'topic-2'], '/b/kb-2'),
    ]);
    kbIndexedUris(['kb-1' => '/kb-1', 'kb-2' => '/kb-2']);

    $collection = kbCollection();
    $collection->shouldReceive('updateEntryUris')->once()->with(['kb-1']);
    $collection->shouldReceive('updateEntryUris')->once()->with(['kb-2', 'topic-2']);

    (new KnowledgeBaseMountUriRefresher)->refreshAll();
});

it('does not refresh again from inside its own refresh', function (): void {
    kbExpectUriCacheFlushes(1);
    kbRootsQuery([kbRoot('kb-1', 'page-9', ['kb-1'], '/health/kb')]);
    kbIndexedUris(['kb-1' => '/kb']);

    $refresher = new KnowledgeBaseMountUriRefresher;

    // Rebuilding the index can fire listeners that call back into the refresher.
    kbCollection()->shouldReceive('updateEntryUris')->once()->andReturnUsing(function () use ($refresher): void {
        $refresher->refreshAll();
    });

    $refresher->refreshAll();
});

it('clears its guard after a failed refresh, so the next one runs', function (): void {
    kbExpectUriCacheFlushes(2);
    kbRootsQuery([kbRoot('kb-1', 'page-9', ['kb-1'], '/health/kb')]);
    kbIndexedUris(['kb-1' => '/kb']);

    $collection = kbCollection();
    $collection->shouldReceive('updateEntryUris')->once()->andThrow(new RuntimeException('index locked'));
    $refresher = new KnowledgeBaseMountUriRefresher;

    expect(fn () => $refresher->refreshAll())->toThrow(RuntimeException::class, 'index locked');

    $collection->shouldReceive('updateEntryUris')->once()->with(['kb-1']);

    $refresher->refreshAll();
});

it('tolerates a missing knowledge_bases collection', function (): void {
    kbExpectUriCacheFlushes(2);
    kbRootsQuery([kbRoot('kb-1', 'page-9', ['kb-1'], '/health/kb')]);
    kbIndexedUris(['kb-1' => '/kb']);
    Collection::shouldReceive('findByHandle')->with('knowledge_bases')->andReturn(null);

    $refresher = new KnowledgeBaseMountUriRefresher;
    $refresher->refreshAll();
    $refresher->restampSubtree(kbRoot('kb-1', 'page-9', ['kb-1']));
});

it('re-stamps a whole subtree on request, after flushing the uri caches', function (): void {
    kbExpectUriCacheFlushes(1);
    kbCollection()->shouldReceive('updateEntryUris')->once()->with(['kb-1', 'topic-1']);

    (new KnowledgeBaseMountUriRefresher)->restampSubtree(kbRoot('kb-1', 'page-9', ['kb-1', null, 'topic-1']));
});

it('does nothing on request for a root with no subtree', function (?array $pageIds): void {
    kbExpectUriCacheFlushes(0);
    kbCollection()->shouldReceive('updateEntryUris')->never();

    /** @var list<string|null>|null $typedPageIds */
    $typedPageIds = $pageIds;

    (new KnowledgeBaseMountUriRefresher)->restampSubtree(kbRoot('kb-1', 'page-9', $typedPageIds));
})->with(['no structure page' => [null], 'no page ids' => [[null, '']]]);
