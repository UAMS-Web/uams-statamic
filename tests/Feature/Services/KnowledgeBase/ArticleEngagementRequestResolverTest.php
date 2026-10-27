<?php

declare(strict_types=1);

use App\Services\KnowledgeBase\ArticleEngagementRequestResolver;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

covers(ArticleEngagementRequestResolver::class);

/**
 * The trust boundary for the public engagement endpoints: which ids are accepted as
 * a published knowledge_bases article, and whether ratings are enabled (UAMS-Web/uams-statamic-archive#286, UAMS-Web/uams-statamic-archive#287).
 *
 * Mutation: tracked under App\Services\KnowledgeBase\ArticleEngagementRequestResolver
 * (--covered-only).
 *
 * @command php artisan test --compact tests/Feature/Services/KnowledgeBase/ArticleEngagementRequestResolverTest.php
 */
beforeEach(function (): void {
    static $padded = false;
    if (! $padded) {
        if (runningUnderMutate()) {
            usleep(20_000_000);
            $padded = true;
        }
    }

    // A knowledge_bases collection with article + topic blueprints, plus a foreign
    // collection to prove the collection gate.
    foreach (['article', 'topic'] as $handle) {
        $blueprint = Blueprint::make($handle);
        $blueprint->setNamespace('collections.knowledge_bases');
        $blueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
        $blueprint->save();
    }
    $knowledgeBases = CollectionFacade::make('knowledge_bases');
    $knowledgeBases->title('Knowledge Bases');
    $knowledgeBases->save();

    // A foreign collection with its own valid blueprint to prove the collection gate.
    $postBlueprint = Blueprint::make('post');
    $postBlueprint->setNamespace('collections.posts');
    $postBlueprint->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
    $postBlueprint->save();
    $posts = CollectionFacade::make('posts');
    $posts->title('Posts');
    $posts->save();

    $this->resolver = app(ArticleEngagementRequestResolver::class);
});

/**
 * Persist an entry with the given collection / blueprint / published state.
 */
function makeResolverEntry(string $collection, string $blueprint, string $slug, bool $published = true): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->slug($slug);
    $entry->published($published);
    $entry->data(['title' => ucfirst($slug)]);
    $entry->saveQuietly();

    return $entry;
}

it('resolves a published knowledge_bases article', function (): void {
    $article = makeResolverEntry('knowledge_bases', 'article', 'a');
    /** @var ArticleEngagementRequestResolver $resolver */
    $resolver = $this->resolver;
    /** @var string $id */
    $id = $article->id();

    expect($resolver->resolvePublishedArticle($id)?->id())->toBe($id);
});

it('rejects a draft article', function (): void {
    $article = makeResolverEntry('knowledge_bases', 'article', 'draft', published: false);
    /** @var ArticleEngagementRequestResolver $resolver */
    $resolver = $this->resolver;
    /** @var string $id */
    $id = $article->id();

    expect($resolver->resolvePublishedArticle($id))->toBeNull();
});

it('rejects a non-article blueprint', function (): void {
    $topic = makeResolverEntry('knowledge_bases', 'topic', 't');
    /** @var ArticleEngagementRequestResolver $resolver */
    $resolver = $this->resolver;
    /** @var string $id */
    $id = $topic->id();

    expect($resolver->resolvePublishedArticle($id))->toBeNull();
});

it('rejects an entry from another collection', function (): void {
    $post = makeResolverEntry('posts', 'post', 'p');
    /** @var ArticleEngagementRequestResolver $resolver */
    $resolver = $this->resolver;
    /** @var string $id */
    $id = $post->id();

    expect($resolver->resolvePublishedArticle($id))->toBeNull();
});

it('rejects an unknown, empty, or null id', function (): void {
    /** @var ArticleEngagementRequestResolver $resolver */
    $resolver = $this->resolver;

    expect($resolver->resolvePublishedArticle('does-not-exist'))->toBeNull()
        ->and($resolver->resolvePublishedArticle(''))->toBeNull()
        ->and($resolver->resolvePublishedArticle(null))->toBeNull();
});

it('reports ratings enabled from the ancestor toggle', function (): void {
    $article = Mockery::mock(EntryInstance::class);

    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $service->shouldReceive('getKnowledgeBaseAncestorValue')
        ->once()
        ->with($article, 'article_rating_query', false)
        ->andReturn(true);

    expect((new ArticleEngagementRequestResolver($service))->ratingEnabled($article))->toBeTrue();
});

it('reports ratings disabled when the ancestor toggle is not exactly true', function (): void {
    $article = Mockery::mock(EntryInstance::class);

    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $service->shouldReceive('getKnowledgeBaseAncestorValue')
        ->with($article, 'article_rating_query', false)
        ->andReturn(false);

    expect((new ArticleEngagementRequestResolver($service))->ratingEnabled($article))->toBeFalse();
});
