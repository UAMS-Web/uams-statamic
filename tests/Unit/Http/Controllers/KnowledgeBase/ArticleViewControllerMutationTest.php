<?php

declare(strict_types=1);

use App\Http\Controllers\KnowledgeBase\ArticleViewController;
use App\Services\KnowledgeBase\ArticleEngagementRequestResolver;
use App\Services\KnowledgeBase\ArticleEngagementService;
use App\Services\KnowledgeBase\ArticleEngagementSession;
use App\Services\KnowledgeBase\CrawlerDetector;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

covers(ArticleViewController::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Narrow mutate coverage for App\Http\Controllers\KnowledgeBase\ArticleViewController.
 *
 * Store / session / crawler are mocked. The resolver is final, so EntryFacade is
 * stubbed and a real resolver instance gates the published-article check.
 *
 * @command  php artisan test --compact tests/Unit/Http/Controllers/KnowledgeBase/ArticleViewControllerMutationTest.php
 */

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
 * @return array{
 *     controller: ArticleViewController,
 *     store: ArticleEngagementService&MockInterface,
 *     session: ArticleEngagementSession&MockInterface,
 *     crawler: CrawlerDetector&MockInterface
 * }
 */
function articleViewControllerHarness(): array
{
    /** @var ArticleEngagementService&MockInterface $store */
    $store = Mockery::mock(ArticleEngagementService::class);
    /** @var ArticleEngagementSession&MockInterface $session */
    $session = Mockery::mock(ArticleEngagementSession::class);
    /** @var CrawlerDetector&MockInterface $crawler */
    $crawler = Mockery::mock(CrawlerDetector::class);
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $computed */
    $computed = Mockery::mock(KnowledgeBaseComputedFieldsService::class);

    return [
        'controller' => new ArticleViewController(
            $store,
            new ArticleEngagementRequestResolver($computed),
            $session,
            $crawler,
        ),
        'store' => $store,
        'session' => $session,
        'crawler' => $crawler,
    ];
}

/**
 * Stub EntryFacade::find() to return a published knowledge_bases article.
 */
function articleViewPublishedArticle(string $id): Entry
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('article');

    $article = Mockery::mock(Entry::class);
    $article->shouldReceive('collectionHandle')->andReturn('knowledge_bases');
    $article->shouldReceive('blueprint')->andReturn($blueprint);
    $article->shouldReceive('status')->andReturn('published');

    EntryFacade::shouldReceive('find')
        ->once()
        ->with($id)
        ->andReturn($article);

    return $article;
}

/**
 * @return array{views: int, likes: int, dislikes: int, net: int}
 */
function articleViewCounts(int $views = 3): array
{
    return [
        'views' => $views,
        'likes' => 1,
        'dislikes' => 0,
        'net' => 1,
    ];
}

it('returns 404 when the id is not a published article', function (): void {
    $harness = articleViewControllerHarness();
    EntryFacade::shouldReceive('find')
        ->once()
        ->with('missing')
        ->andReturnNull();
    $harness['store']->shouldReceive('incrementViews')->never();
    $harness['store']->shouldReceive('counts')->never();
    $harness['session']->shouldReceive('markViewed')->never();
    $harness['crawler']->shouldReceive('isBot')->never();

    $response = ($harness['controller'])(
        Request::create(
            '/!/kb/articles/missing/view',
            'POST',
        ),
        'missing',
    );

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['error' => 'Article not found.']);
});

it('increments and marks a fresh human view', function (): void {
    $harness = articleViewControllerHarness();
    articleViewPublishedArticle('article-1');
    $counts = articleViewCounts(4);

    $harness['crawler']->shouldReceive('isBot')
        ->once()
        ->with('Mozilla/5.0')
        ->andReturnFalse();
    $harness['session']->shouldReceive('hasViewed')
        ->once()
        ->with('article-1')
        ->andReturnFalse();
    $harness['store']->shouldReceive('incrementViews')
        ->once()
        ->with('article-1')
        ->andReturn($counts);
    $harness['session']->shouldReceive('markViewed')
        ->once()
        ->with('article-1');
    $harness['store']->shouldReceive('counts')->never();
    $harness['session']->shouldReceive('reactionFor')
        ->once()
        ->with('article-1')
        ->andReturnNull();

    $request = Request::create(
        '/!/kb/articles/article-1/view',
        'POST',
        server: ['HTTP_USER_AGENT' => 'Mozilla/5.0'],
    );

    $response = ($harness['controller'])(
        $request,
        'article-1',
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe([
            'views' => 4,
            'likes' => 1,
            'dislikes' => 0,
            'net' => 1,
            'reaction' => null,
        ]);
});

it('reads counts without incrementing for a bot', function (): void {
    $harness = articleViewControllerHarness();
    articleViewPublishedArticle('article-2');
    $counts = articleViewCounts(2);

    $harness['crawler']->shouldReceive('isBot')
        ->once()
        ->with('Googlebot/2.1')
        ->andReturnTrue();
    $harness['session']->shouldReceive('hasViewed')->never();
    $harness['store']->shouldReceive('incrementViews')->never();
    $harness['session']->shouldReceive('markViewed')->never();
    $harness['store']->shouldReceive('counts')
        ->once()
        ->with('article-2')
        ->andReturn($counts);
    $harness['session']->shouldReceive('reactionFor')
        ->once()
        ->with('article-2')
        ->andReturn('like');

    $request = Request::create(
        '/!/kb/articles/article-2/view',
        'POST',
        server: ['HTTP_USER_AGENT' => 'Googlebot/2.1'],
    );

    $response = ($harness['controller'])(
        $request,
        'article-2',
    );

    $payload = mustBeArray($response->getData(true), 'the view response payload');

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['views'])->toBe(2)
        ->and($payload['reaction'])->toBe('like');
});

it('reads counts without incrementing when the session already viewed', function (): void {
    $harness = articleViewControllerHarness();
    articleViewPublishedArticle('article-3');
    $counts = articleViewCounts(5);

    $harness['crawler']->shouldReceive('isBot')
        ->once()
        ->with('Mozilla/5.0')
        ->andReturnFalse();
    $harness['session']->shouldReceive('hasViewed')
        ->once()
        ->with('article-3')
        ->andReturnTrue();
    $harness['store']->shouldReceive('incrementViews')->never();
    $harness['session']->shouldReceive('markViewed')->never();
    $harness['store']->shouldReceive('counts')
        ->once()
        ->with('article-3')
        ->andReturn($counts);
    $harness['session']->shouldReceive('reactionFor')
        ->once()
        ->with('article-3')
        ->andReturnNull();

    $request = Request::create(
        '/!/kb/articles/article-3/view',
        'POST',
        server: ['HTTP_USER_AGENT' => 'Mozilla/5.0'],
    );

    $response = ($harness['controller'])(
        $request,
        'article-3',
    );

    $payload = mustBeArray($response->getData(true), 'the view response payload');

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['views'])->toBe(5);
});
