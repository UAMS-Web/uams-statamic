<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class, RequiresContentSubmodule::class);

/**
 * @covers \App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService
 *
 * Bounded-query coverage for the UAMS-Web/uams-statamic-archive#886 N+1 fix: the topic-card subtopic/article
 * counts are precomputed (`direct_subtopic_count` / `direct_article_count`) from
 * the already-loaded structure instead of one `collection:knowledge_bases` query
 * per card. The counts must (a) issue NO Entry query — they read direct children
 * straight from the structure page — and (b) match the published-only default of
 * the collection tag they replace, filtered to the requested blueprint.
 *
 * Pure Mockery doubles, so no content/ submodule.
 *
 * @command  php artisan test --compact tests/Feature/Services/KnowledgeBase/KnowledgeBaseDirectChildCountTest.php
 */

/**
 * Flush the per-request Blink stores so memoized counts start cold.
 */
beforeEach(function (): void {
    Blink::flush();
});

/**
 * Build a child structure-page double whose entry() resolves to the given entry
 * (or null to model a dangling structure reference).
 */
function kbDirectChildPage(?object $entry): object
{
    $page = Mockery::mock();
    $page->shouldReceive('entry')->andReturn($entry);

    return $page;
}

/**
 * Build a child entry double with the given blueprint handle and status. The count
 * compares on status() (not the bare published() flag) to track the collection
 * tag's whereStatus('published') exactly on this dated collection, so 'scheduled'
 * and 'expired' children are excluded just as the replaced query excludes them.
 */
function kbDirectChildEntry(string $blueprint, string $status = 'published'): object
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint_ = Mockery::mock());
    $blueprint_->shouldReceive('handle')->andReturn($blueprint);
    $entry->shouldReceive('status')->andReturn($status);

    return $entry;
}

/**
 * Build a parent entry double whose structure page exposes the given direct child
 * pages in memory (page()->pages()->all()), with `page()` bounded to one call.
 *
 * @param  array<int, object>  $childPages  The direct child page doubles
 */
function kbParentWithDirectChildren(string $parentId, array $childPages): Entry
{
    $pages = Mockery::mock();
    $pages->shouldReceive('all')->andReturn(collect($childPages));

    $page = Mockery::mock();
    $page->shouldReceive('pages')->andReturn($pages);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($parentId);
    $entry->shouldReceive('page')->once()->andReturn($page);

    return $entry;
}

it('counts only published direct child topics, reading the structure with no query', function (): void {
    // A topic with a mix of children: 2 published topics, plus a draft topic, a
    // published article, and a dangling reference — only published topics count.
    // The dangling child sits BEFORE a countable topic so the walk must `continue`
    // past it (a `break` would stop early and miss the second topic, giving 1 not 2).
    $entry = kbParentWithDirectChildren('topic-1', [
        kbDirectChildPage(kbDirectChildEntry('topic')),
        kbDirectChildPage(null),                                 // dangling reference — skipped, not a stop
        kbDirectChildPage(kbDirectChildEntry('topic')),
        kbDirectChildPage(kbDirectChildEntry('topic', 'draft')),   // draft topic — excluded
        kbDirectChildPage(kbDirectChildEntry('article')),  // article — wrong blueprint
    ]);

    // The count is read from the structure; no Entry query may run.
    EntryFacade::shouldReceive('query')->never();

    // Exactly the two published topics are counted.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectSubtopicCount($entry))->toBe(2);
});

it('counts only published direct child articles, reading the structure with no query', function (): void {
    // A topic with 3 published articles, a draft article, and a published topic.
    $entry = kbParentWithDirectChildren('topic-2', [
        kbDirectChildPage(kbDirectChildEntry('article')),
        kbDirectChildPage(kbDirectChildEntry('article')),
        kbDirectChildPage(kbDirectChildEntry('article')),
        kbDirectChildPage(kbDirectChildEntry('article', 'draft')), // draft article — excluded
        kbDirectChildPage(kbDirectChildEntry('topic')),    // topic — wrong blueprint
    ]);

    // The count is read from the structure; no Entry query may run.
    EntryFacade::shouldReceive('query')->never();

    // Exactly the three published articles are counted.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectArticleCount($entry))->toBe(3);
});

it('excludes a scheduled (future-dated published) child, matching whereStatus(published)', function (): void {
    // A future-dated published topic: its published() flag is true, but its status
    // is 'scheduled' because knowledge_bases is a dated collection. The collection
    // tag the count replaces uses whereStatus('published') and excludes it, so the
    // count must compare on status() — not the bare published() flag — and exclude it too.
    $scheduled = Mockery::mock(Entry::class);
    $scheduled->shouldReceive('blueprint')->andReturn($scheduledBlueprint = Mockery::mock());
    $scheduledBlueprint->shouldReceive('handle')->andReturn('topic');
    $scheduled->shouldReceive('status')->andReturn('scheduled');
    // published() true would wrongly count it if the gate read the flag instead of status().
    $scheduled->shouldReceive('published')->andReturn(true);

    $entry = kbParentWithDirectChildren('topic-scheduled', [
        kbDirectChildPage(kbDirectChildEntry('topic')),  // published — counted
        kbDirectChildPage($scheduled),                   // scheduled — excluded
    ]);

    EntryFacade::shouldReceive('query')->never();

    // Only the published topic is counted; the scheduled child is excluded.
    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectSubtopicCount($entry))->toBe(1);
});

it('returns zero when the entry has no direct children', function (): void {
    // A leaf topic whose structure page has no children.
    $entry = kbParentWithDirectChildren('topic-leaf', []);

    EntryFacade::shouldReceive('query')->never();

    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectSubtopicCount($entry))->toBe(0);
});

it('returns zero when the entry has no structure page', function (): void {
    // An entry without a structure page (no children to count).
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('topic-no-page');
    $entry->shouldReceive('page')->andReturn(null);

    EntryFacade::shouldReceive('query')->never();

    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectArticleCount($entry))->toBe(0);
});

it('returns zero without walking the structure when the entry has no string id', function (): void {
    // A not-yet-saved entry has no string id and therefore no stable cache key.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn(null);
    $entry->shouldReceive('page')->never();

    EntryFacade::shouldReceive('query')->never();

    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectSubtopicCount($entry))->toBe(0);
});

it('memoizes the direct-child count so the structure is walked only once', function (): void {
    // The structure page is exposed only once (page() bounded to one call); two
    // reads of the count must share the one cached structure walk.
    $entry = kbParentWithDirectChildren('topic-memo', [
        kbDirectChildPage(kbDirectChildEntry('topic')),
    ]);

    EntryFacade::shouldReceive('query')->never();

    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectSubtopicCount($entry))->toBe(1);
    expect($service->resolveDirectSubtopicCount($entry))->toBe(1);
});

it('keeps subtopic and article counts in independent memo slots', function (): void {
    // One published topic and one published article under the same parent: the two
    // counts must not collide on the same cache key (the key includes the blueprint).
    $pages = Mockery::mock();
    $pages->shouldReceive('all')->andReturn(collect([
        kbDirectChildPage(kbDirectChildEntry('topic')),
        kbDirectChildPage(kbDirectChildEntry('article')),
    ]));
    $page = Mockery::mock();
    $page->shouldReceive('pages')->andReturn($pages);

    // The two counts walk the structure once each (one memo slot per blueprint).
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('topic-mixed');
    $entry->shouldReceive('page')->twice()->andReturn($page);

    EntryFacade::shouldReceive('query')->never();

    $service = new KnowledgeBaseComputedFieldsService;
    expect($service->resolveDirectSubtopicCount($entry))->toBe(1);
    expect($service->resolveDirectArticleCount($entry))->toBe(1);
});

it('replaces the per-card count queries in the topic list with the precomputed computeds', function (): void {
    // The topic-list partial that renders each topic card's footer counts.
    $template = File::get(resource_path('views/knowledge_bases/_topic_list.antlers.html'));

    // The per-card subtopic/article count queries must be gone (they caused the N+1).
    expect($template)->not->toContain('blueprint:is="topic" :parent:is="id" as="topic_children"');
    expect($template)->not->toContain('blueprint:is="article" :parent:is="id" as="article_children"');

    // The footer now reads the precomputed counts instead.
    expect($template)->toContain('direct_subtopic_count');
    expect($template)->toContain('direct_article_count');

    // The single topics-loop query that drives the list is unchanged.
    expect($template)->toContain('blueprint:is="topic" :parent:is="id" as="topics"');
});

it('matches the old per-card published parent query count on real content', function (): void {
    // Parity on real data: the new in-memory counts must equal the queries the old
    // template ran — collection:knowledge_bases blueprint:is="topic"|"article"
    // :parent:is="id" (published by the collection tag default). This exercises the
    // REAL Page::entry() / Tree::entry() resolution path (not a mock), proving both
    // the count parity and that the structure walk resolves entries on real content.
    $this->requireContentCollection('knowledge_bases');

    // Real published topics whose direct-child counts we compare.
    // `Entry::query()` is typed as the contract `QueryBuilder`; narrow each fluent
    // hop to the concrete `EntryQueryBuilder` (whose builder methods return mixed).
    /** @var EntryQueryBuilder $query */
    $query = EntryFacade::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', 'knowledge_bases');
    /** @var EntryQueryBuilder $query */
    $query = $query->where('blueprint', 'topic');
    /** @var EntryQueryBuilder $query */
    $query = $query->whereStatus('published');
    /** @var Collection<int, Entry> $topics */
    $topics = $query->get();
    $topics = $topics->take(25);

    // Skip when no published topic exists — an editorial fact, not a defect.
    $this->skipWithoutQualifyingContentEntry($topics->first(), 'published knowledge_bases topics');

    $service = new KnowledgeBaseComputedFieldsService;

    $topics->each(function (Entry $topic) use ($service): void {
        $id = $topic->id();

        // The query the old template ran per card (published default), by blueprint.
        /** @var EntryQueryBuilder $subtopicQuery */
        $subtopicQuery = EntryFacade::query();
        /** @var EntryQueryBuilder $subtopicQuery */
        $subtopicQuery = $subtopicQuery->where('collection', 'knowledge_bases');
        /** @var EntryQueryBuilder $subtopicQuery */
        $subtopicQuery = $subtopicQuery->where('parent', $id);
        /** @var EntryQueryBuilder $subtopicQuery */
        $subtopicQuery = $subtopicQuery->where('blueprint', 'topic');
        /** @var EntryQueryBuilder $subtopicQuery */
        $subtopicQuery = $subtopicQuery->whereStatus('published');
        /** @var int $expectedSubtopics */
        $expectedSubtopics = $subtopicQuery->count();

        /** @var EntryQueryBuilder $articleQuery */
        $articleQuery = EntryFacade::query();
        /** @var EntryQueryBuilder $articleQuery */
        $articleQuery = $articleQuery->where('collection', 'knowledge_bases');
        /** @var EntryQueryBuilder $articleQuery */
        $articleQuery = $articleQuery->where('parent', $id);
        /** @var EntryQueryBuilder $articleQuery */
        $articleQuery = $articleQuery->where('blueprint', 'article');
        /** @var EntryQueryBuilder $articleQuery */
        $articleQuery = $articleQuery->whereStatus('published');
        /** @var int $expectedArticles */
        $expectedArticles = $articleQuery->count();

        // The new precomputed counts must equal the queries they replaced.
        expect($service->resolveDirectSubtopicCount($topic))->toBe($expectedSubtopics);
        expect($service->resolveDirectArticleCount($topic))->toBe($expectedArticles);
    });
});
