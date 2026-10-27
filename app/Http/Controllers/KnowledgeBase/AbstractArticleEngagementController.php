<?php

declare(strict_types=1);

namespace App\Http\Controllers\KnowledgeBase;

use App\Services\KnowledgeBase\ArticleEngagementRequestResolver;
use App\Services\KnowledgeBase\ArticleEngagementService;
use App\Services\KnowledgeBase\ArticleEngagementSession;
use App\Services\KnowledgeBase\CrawlerDetector;
use Illuminate\Http\JsonResponse;

/**
 * Shared plumbing for the knowledge base article engagement endpoints (view UAMS-Web/uams-statamic-archive#287,
 * react UAMS-Web/uams-statamic-archive#286): the injected store / resolver / session / crawler collaborators and
 * the common JSON snapshot both endpoints return.
 */
abstract class AbstractArticleEngagementController
{
    /**
     * @param  ArticleEngagementService  $store  The sideband counter store
     * @param  ArticleEngagementRequestResolver  $resolver  Validates the target article and its rating gate
     * @param  ArticleEngagementSession  $session  Per-session view / reaction record
     * @param  CrawlerDetector  $crawler  Best-effort bot filter
     */
    public function __construct(
        protected readonly ArticleEngagementService $store,
        protected readonly ArticleEngagementRequestResolver $resolver,
        protected readonly ArticleEngagementSession $session,
        protected readonly CrawlerDetector $crawler,
    ) {}

    /**
     * Build the uniform engagement snapshot both endpoints return: the current
     * tallies plus this session's reaction (so the client can reflect the visitor's
     * current vote state without the server embedding per-user state in a cacheable
     * page).
     *
     * @param  string  $entryId  The resolved article entry id
     * @param  array{views: int, likes: int, dislikes: int, net: int}  $counts  The current tallies
     * @return JsonResponse The snapshot as JSON
     */
    protected function snapshotResponse(string $entryId, array $counts): JsonResponse
    {
        return response()->json([
            'views' => $counts['views'],
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'net' => $counts['net'],
            'reaction' => $this->session->reactionFor($entryId),
        ]);
    }

    /**
     * The response for a request whose id does not name a published article.
     *
     * @return JsonResponse A 404 error payload
     */
    protected function notFound(): JsonResponse
    {
        return response()->json(
            ['error' => 'Article not found.'],
            404
        );
    }
}
