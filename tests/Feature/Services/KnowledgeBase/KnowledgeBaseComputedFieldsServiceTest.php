<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The per-request memoization and structure-walk edges of
 * {@see KnowledgeBaseComputedFieldsService} that the feature tests naming it do not
 * reach: the Blink key each memoized answer is stored under, an entry with no id or
 * no structure page, and a page whose parent is missing.
 *
 * Pure Mockery doubles, so no content/ submodule.
 *
 * @command  php artisan test --compact tests/Feature/Services/KnowledgeBase/KnowledgeBaseComputedFieldsServiceTest.php
 */
beforeEach(function (): void {
    Blink::flush();
});

/**
 * An entry double in a structure, with the given id and structure page.
 */
function kbServiceEntry(?string $id, ?object $page): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('page')->andReturn($page);

    return $entry;
}

it('memoizes the ancestor under the entry\'s own key and reuses it', function (): void {
    $cached = Mockery::mock(Entry::class);
    Blink::put('computed_kb_ancestor_article-1', $cached);

    // The cached ancestor answers without walking the structure.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('hasStructure')->andReturn(true);
    $entry->shouldReceive('id')->andReturn('article-1');
    $entry->shouldReceive('page')->never();

    expect((new KnowledgeBaseComputedFieldsService)->findKnowledgeBaseAncestor($entry))->toBe($cached);
});

it('walks an entry with no id directly, never through the memo', function (): void {
    // A value under the key an id-less entry would share must not answer for it.
    Blink::put('computed_kb_ancestor_', Mockery::mock(Entry::class));

    expect((new KnowledgeBaseComputedFieldsService)->findKnowledgeBaseAncestor(kbServiceEntry(null, null)))->toBeNull();
});

it('finds no ancestor for an entry with no structure page', function (): void {
    expect((new KnowledgeBaseComputedFieldsService)->findKnowledgeBaseAncestor(kbServiceEntry('article-2', null)))->toBeNull()
        ->and(Blink::get('computed_kb_ancestor_article-2'))->toBeNull();
});

it('stops the walk and finds no ancestor when a page has no parent', function (): void {
    $page = Mockery::mock();
    $page->shouldReceive('isRoot')->andReturn(false);
    $page->shouldReceive('parent')->once()->andReturn(null);

    EntryFacade::shouldReceive('find')->never();

    expect((new KnowledgeBaseComputedFieldsService)->findKnowledgeBaseAncestor(kbServiceEntry('article-3', $page)))->toBeNull();
});

it('reads a memoized descendant-article answer under the topic\'s own key, as a boolean', function (mixed $cached, bool $expected): void {
    Blink::put('computed_kb_topic_has_descendant_articles_topic-1', $cached);

    // The cached answer is used, so the topic is never looked up.
    EntryFacade::shouldReceive('find')->never();

    expect((new KnowledgeBaseComputedFieldsService)->topicHasDescendantArticles('topic-1'))->toBe($expected);
})->with([
    'truthy' => [1, true],
    'falsy' => [0, false],
]);

it('reads a memoized direct-child count under the entry and blueprint key, and a non-int as none', function (string $blueprint, mixed $cached, int $expected): void {
    Blink::put(sprintf('computed_kb_direct_%s_count_topic-2', $blueprint), $cached);

    // The cached count is used, so the structure page is never read.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('topic-2');
    $entry->shouldReceive('page')->never();

    $service = new KnowledgeBaseComputedFieldsService;
    $count = $blueprint === 'topic' ? $service->resolveDirectSubtopicCount($entry) : $service->resolveDirectArticleCount($entry);

    expect($count)->toBe($expected);
})->with([
    'subtopics' => ['topic', 3, 3],
    'articles' => ['article', 4, 4],
    'a non-int subtopic count' => ['topic', 'three', 0],
    'a non-int article count' => ['article', 'four', 0],
]);
