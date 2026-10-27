<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService
 *
 * Bounded-query coverage for the UAMS-Web/uams-statamic-archive#866 perf refactor: descendant-article
 * detection must issue a single bulk query over the topic's flattened structure
 * subtree instead of one child query per descendant node, and the ancestor walk
 * must be memoized per entry id. Pure Mockery doubles, so no content/ submodule.
 *
 * The bulk-query double pins the collection/id/blueprint constraints with
 * argument matchers, so a mutant that drops or changes any clause fails.
 *
 * @command  php artisan test --compact tests/Feature/Services/KnowledgeBase/KnowledgeBaseDescendantArticlesQueryTest.php
 */

/**
 * Flush the per-request Blink stores so memoized computeds start cold.
 */
beforeEach(function (): void {
    Blink::flush();
});

/**
 * Build a structure-page double that reports the given (possibly non-scalar) id.
 */
function kbDescendantPage(mixed $id): object
{
    // A page double exposing only the id() the descendant walk reads.
    $page = Mockery::mock();
    $page->shouldReceive('id')->andReturn($id);

    return $page;
}

/**
 * Stub a topic entry whose structure page flattens to the given descendant pages.
 *
 * @param  array<int, object>  $descendantPages  The flattened descendant page doubles
 */
function kbStubTopicWithDescendants(string $topicId, array $descendantPages): void
{
    // The topic's structure page returns the flattened descendant subtree.
    $page = Mockery::mock();
    $page->shouldReceive('flattenedPages')->andReturn(collect($descendantPages));

    // The topic entry exposes that page.
    $topic = Mockery::mock(Entry::class);
    $topic->shouldReceive('page')->andReturn($page);

    // Entry::find($topicId) resolves the topic entry.
    EntryFacade::shouldReceive('find')->with($topicId)->andReturn($topic);
}

/**
 * Stub Entry::query() with a builder double that asserts the bulk article query is
 * scoped to the knowledge_bases collection, the exact descendant ids, the article
 * blueprint, and the published status — then returns the given match count. Bounds
 * query() to one call so the per-descendant recursion cannot be reintroduced.
 *
 * @param  list<string>  $expectedIds  The descendant ids the query must be scoped to
 */
function kbExpectArticleQuery(array $expectedIds, int $count): void
{
    // A builder double pinning every clause the empty-suppression depends on.
    $builder = Mockery::mock();
    $builder->shouldReceive('where')->with('collection', 'knowledge_bases')->once()->andReturnSelf();
    $builder->shouldReceive('whereIn')->with('id', $expectedIds)->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('blueprint', 'article')->once()->andReturnSelf();
    $builder->shouldReceive('whereStatus')->with('published')->once()->andReturnSelf();
    $builder->shouldReceive('count')->andReturn($count);

    // Entry::query() yields the builder exactly once (bounded query count).
    EntryFacade::shouldReceive('query')->once()->andReturn($builder);
}

it('issues a single bulk query scoped to every descendant of a deep subtree', function (): void {
    // A 50-node flattened subtree stands in for a deep topic index.
    $scalarIds = array_map(fn (int $i): string => "descendant-{$i}", range(1, 50));
    $descendants = array_map(fn (string $id): object => kbDescendantPage($id), $scalarIds);

    // A non-scalar id is dropped, never reaching the query.
    $descendants[] = kbDescendantPage(['unexpected' => 'array']);
    kbStubTopicWithDescendants('topic-deep', $descendants);

    // Exactly one query, scoped to the 50 scalar ids, regardless of subtree size.
    kbExpectArticleQuery(expectedIds: $scalarIds, count: 1);

    // The topic reports descendant articles from the single bulk query.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->topicHasDescendantArticles('topic-deep'))->toBeTrue();
});

it('reports no descendant articles when the bulk query matches none', function (): void {
    // A subtree of three descendant pages, none of them articles.
    kbStubTopicWithDescendants('topic-empty', [
        kbDescendantPage('child-1'),
        kbDescendantPage('child-2'),
        kbDescendantPage('child-3'),
    ]);

    // The single article query, scoped to those ids, returns zero matches.
    kbExpectArticleQuery(expectedIds: ['child-1', 'child-2', 'child-3'], count: 0);

    // The topic has no descendant articles.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->topicHasDescendantArticles('topic-empty'))->toBeFalse();
});

it('skips the bulk query entirely when the topic has no descendants', function (): void {
    // The topic's flattened subtree is empty.
    kbStubTopicWithDescendants('topic-leaf', []);

    // No query should run when there is nothing to inspect.
    EntryFacade::shouldReceive('query')->never();

    // A leaf topic reports no descendant articles without querying.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->topicHasDescendantArticles('topic-leaf'))->toBeFalse();
});

it('memoizes the descendant-article result across repeated lookups', function (): void {
    // A single-descendant subtree carrying an article.
    kbStubTopicWithDescendants('topic-memo', [kbDescendantPage('article-1')]);

    // The bulk query may run only once even though the flag is read twice.
    kbExpectArticleQuery(expectedIds: ['article-1'], count: 1);

    // Two lookups share the one cached result.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->topicHasDescendantArticles('topic-memo'))->toBeTrue();
    expect($service->topicHasDescendantArticles('topic-memo'))->toBeTrue();
});

it('returns false when the topic entry cannot be resolved', function (): void {
    // Entry::find yields no entry for the id.
    EntryFacade::shouldReceive('find')->with('missing-topic')->andReturn(null);

    // No descendant query runs without a resolved structure page.
    EntryFacade::shouldReceive('query')->never();

    // The flag resolves false rather than erroring on the missing entry.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->topicHasDescendantArticles('missing-topic'))->toBeFalse();
});

it('skips a dangling parent reference without dereferencing null', function (): void {
    // A topic whose parent page resolves to no entry (a dangling reference).
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('id')->andReturn('topic-dangling');

    // The topic's page is not root and exposes a parent page.
    $page = Mockery::mock();
    $entry->shouldReceive('page')->andReturn($page);
    $page->shouldReceive('isRoot')->andReturn(false);

    // The parent page exists but resolves to a null entry, then ends the walk.
    $parentPage = Mockery::mock();
    $page->shouldReceive('parent')->andReturn($parentPage);
    $parentPage->shouldReceive('id')->andReturn('dangling-id');
    $parentPage->shouldReceive('isRoot')->andReturn(true);
    EntryFacade::shouldReceive('find')->with('dangling-id')->andReturn(null);

    // The walk returns null instead of fatally calling blueprint() on null.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->findKnowledgeBaseAncestor($entry))->toBeNull();
});

it('memoizes the ancestor walk so repeated lookups resolve it once', function (): void {
    // A knowledge_base ancestor entry one level above the topic.
    $ancestor = Mockery::mock(Entry::class);
    $ancestor->shouldReceive('blueprint')->andReturn($ancestorBlueprint = Mockery::mock());
    $ancestorBlueprint->shouldReceive('handle')->andReturn('knowledge_base');

    // A topic whose parent page references that ancestor.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('id')->andReturn('topic-ancestor-memo');
    $page = Mockery::mock();
    $entry->shouldReceive('page')->andReturn($page);
    $page->shouldReceive('isRoot')->andReturn(false);
    $parentPage = Mockery::mock();
    $page->shouldReceive('parent')->andReturn($parentPage);
    $parentPage->shouldReceive('id')->andReturn('kb-root');

    // The ancestor entry is resolved only once despite two lookups.
    EntryFacade::shouldReceive('find')->with('kb-root')->once()->andReturn($ancestor);

    // Both lookups return the cached ancestor.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->findKnowledgeBaseAncestor($entry))->toBe($ancestor);
    expect($service->findKnowledgeBaseAncestor($entry))->toBe($ancestor);
});

it('memoizes a null ancestor so a no-ancestor entry is walked only once', function (): void {
    // The parent resolves to a non-knowledge_base entry, so no ancestor exists.
    $parentEntry = Mockery::mock(Entry::class);
    $parentEntry->shouldReceive('blueprint')->andReturn($parentBlueprint = Mockery::mock());
    $parentBlueprint->shouldReceive('handle')->andReturn('topic');

    // A topic one level below that non-ancestor parent, then the root.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('id')->andReturn('orphan-topic');
    $page = Mockery::mock();
    $entry->shouldReceive('page')->andReturn($page);
    $page->shouldReceive('isRoot')->andReturn(false);
    $parentPage = Mockery::mock();
    $page->shouldReceive('parent')->andReturn($parentPage);
    $parentPage->shouldReceive('id')->andReturn('non-kb-parent');
    $parentPage->shouldReceive('isRoot')->andReturn(true);

    // The parent entry is resolved only once even though the null result is read twice.
    EntryFacade::shouldReceive('find')->with('non-kb-parent')->once()->andReturn($parentEntry);

    // Both lookups return the cached null without re-walking the tree.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->findKnowledgeBaseAncestor($entry))->toBeNull();
    expect($service->findKnowledgeBaseAncestor($entry))->toBeNull();
});

it('continues walking past a dangling parent to find a higher ancestor', function (): void {
    // The grandparent is the knowledge_base ancestor.
    $ancestor = Mockery::mock(Entry::class);
    $ancestor->shouldReceive('blueprint')->andReturn($ancestorBlueprint = Mockery::mock());
    $ancestorBlueprint->shouldReceive('handle')->andReturn('knowledge_base');

    // A topic whose immediate parent reference is dangling (resolves to null).
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('id')->andReturn('topic-midwalk');
    $page = Mockery::mock();
    $entry->shouldReceive('page')->andReturn($page);
    $page->shouldReceive('isRoot')->andReturn(false);

    // The dangling parent page resolves to no entry, so the walk must ascend past it.
    $danglingParentPage = Mockery::mock();
    $page->shouldReceive('parent')->andReturn($danglingParentPage);
    $danglingParentPage->shouldReceive('id')->andReturn('dangling-parent');
    $danglingParentPage->shouldReceive('isRoot')->andReturn(false);
    EntryFacade::shouldReceive('find')->with('dangling-parent')->andReturn(null);

    // The grandparent page resolves to the knowledge_base ancestor.
    $grandparentPage = Mockery::mock();
    $danglingParentPage->shouldReceive('parent')->andReturn($grandparentPage);
    $grandparentPage->shouldReceive('id')->andReturn('kb-grandparent');
    EntryFacade::shouldReceive('find')->with('kb-grandparent')->andReturn($ancestor);

    // Walking continues past the dangling parent and returns the grandparent ancestor;
    // a break instead of continue would stop at the dangling parent and miss it.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->findKnowledgeBaseAncestor($entry))->toBe($ancestor);
});

it('reports displayable subtopics when a direct child topic has descendant articles', function (): void {
    // A partial mock so the per-child descendant-article check can be stubbed.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // A knowledge_base entry with one direct child topic.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('knowledge_base');
    $entry->shouldReceive('id')->andReturn('kb-1');

    // The direct-child-topics query returns a single child topic.
    $childTopic = Mockery::mock(Entry::class);
    $childTopic->shouldReceive('id')->andReturn('child-topic-1');
    $builder = Mockery::mock();
    $builder->shouldReceive('where')->with('collection', 'knowledge_bases')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('parent', 'kb-1')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('blueprint', 'topic')->once()->andReturnSelf();
    $builder->shouldReceive('whereStatus')->with('published')->once()->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect([$childTopic]));
    EntryFacade::shouldReceive('query')->andReturn($builder);

    // That child topic has descendant articles.
    $service->shouldReceive('topicHasDescendantArticles')->with('child-topic-1')->andReturn(true);

    // The entry therefore has at least one displayable subtopic.
    expect($service->entryHasDisplayableSubtopics($entry))->toBeTrue();
});

it('reports no displayable subtopics when no direct child topic has articles', function (): void {
    // A partial mock so the per-child descendant-article check can be stubbed.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // A topic entry with one direct child topic.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('topic');
    $entry->shouldReceive('id')->andReturn('topic-1');

    // The direct-child-topics query returns a single child topic.
    $childTopic = Mockery::mock(Entry::class);
    $childTopic->shouldReceive('id')->andReturn('child-topic-1');
    $builder = Mockery::mock();
    $builder->shouldReceive('where')->with('collection', 'knowledge_bases')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('parent', 'topic-1')->once()->andReturnSelf();
    $builder->shouldReceive('where')->with('blueprint', 'topic')->once()->andReturnSelf();
    $builder->shouldReceive('whereStatus')->with('published')->once()->andReturnSelf();
    $builder->shouldReceive('get')->andReturn(collect([$childTopic]));
    EntryFacade::shouldReceive('query')->andReturn($builder);

    // The child topic has no descendant articles.
    $service->shouldReceive('topicHasDescendantArticles')->with('child-topic-1')->andReturn(false);

    // The entry has no displayable subtopics.
    expect($service->entryHasDisplayableSubtopics($entry))->toBeFalse();
});

it('reports no displayable subtopics for a non-knowledge_base, non-topic entry', function (): void {
    // An article entry never has displayable subtopics and must not query.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('article');
    EntryFacade::shouldReceive('query')->never();

    // The blueprint guard short-circuits before any query runs.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->entryHasDisplayableSubtopics($entry))->toBeFalse();
});

/**
 * UAMS-Web/uams-statamic-archive#215: resolveHasDescendantArticles drives empty-suppression for BOTH the
 * structural knowledge_base root and topic entries — a knowledge_base with no
 * published descendant article must report false so its template redirects. The
 * blueprint guard widened from topic-only to {knowledge_base, topic}; these pin
 * each branch so a mutant dropping either handle, or letting an article through,
 * fails. The subtree walk itself is pinned by the topicHasDescendantArticles
 * tests above, so these isolate the guard with a partial mock.
 */
it('reports descendant articles for a knowledge_base root via the shared subtree walk', function (): void {
    // A partial mock isolates the blueprint guard from the subtree query.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // A knowledge_base root entry with a resolvable id.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('knowledge_base');
    $entry->shouldReceive('id')->andReturn('kb-root');

    // The root delegates to the shared subtree walk, which finds a published article.
    $service->shouldReceive('topicHasDescendantArticles')->with('kb-root')->once()->andReturn(true);

    // A knowledge_base root with a published descendant article is non-empty.
    expect($service->resolveHasDescendantArticles($entry))->toBeTrue();
});

it('reports no descendant articles for an empty knowledge_base root', function (): void {
    // A partial mock isolates the blueprint guard from the subtree query.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // A knowledge_base root entry with a resolvable id.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('knowledge_base');
    $entry->shouldReceive('id')->andReturn('kb-empty');

    // The shared subtree walk finds no published descendant article.
    $service->shouldReceive('topicHasDescendantArticles')->with('kb-empty')->once()->andReturn(false);

    // An empty knowledge_base root reports no descendant articles, so its template redirects.
    expect($service->resolveHasDescendantArticles($entry))->toBeFalse();
});

it('still resolves descendant articles for a topic entry after the guard widened', function (): void {
    // A partial mock isolates the blueprint guard from the subtree query.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // A topic entry with a resolvable id.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('topic');
    $entry->shouldReceive('id')->andReturn('topic-x');

    // The topic branch must keep delegating to the subtree walk after the guard widened.
    $service->shouldReceive('topicHasDescendantArticles')->with('topic-x')->once()->andReturn(true);

    // A topic with a published descendant article remains non-empty.
    expect($service->resolveHasDescendantArticles($entry))->toBeTrue();
});

it('short-circuits an article entry without walking its subtree', function (): void {
    // A partial mock so any stray delegation would be observable.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // An article entry is never a structural root or topic.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('article');

    // The blueprint guard short-circuits before any subtree walk.
    $service->shouldReceive('topicHasDescendantArticles')->never();

    // An article entry never reports descendant articles.
    expect($service->resolveHasDescendantArticles($entry))->toBeFalse();
});

it('returns false for a knowledge_base root without a string id', function (): void {
    // A partial mock so a stray delegation would be observable.
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // A not-yet-saved knowledge_base root has no string id (CP store-time computeds).
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint = Mockery::mock());
    $blueprint->shouldReceive('handle')->andReturn('knowledge_base');
    $entry->shouldReceive('id')->andReturn(null);

    // No subtree walk runs without a stable id to key it.
    $service->shouldReceive('topicHasDescendantArticles')->never();

    // The flag resolves false rather than passing a null id to the string-typed walk.
    expect($service->resolveHasDescendantArticles($entry))->toBeFalse();
});
