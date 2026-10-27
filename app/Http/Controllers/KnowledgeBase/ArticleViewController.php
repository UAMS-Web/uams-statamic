<?php

declare(strict_types=1);

namespace App\Http\Controllers\KnowledgeBase;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Entries\Entry;

/**
 * Records a knowledge base article view and returns the current engagement snapshot
 * (UAMS-Web/uams-statamic-archive#287).
 *
 * The article page fires this once on load via an explicit AJAX request rather than
 * counting in the render, so the tally survives page static-caching and is not
 * inflated by crawlers. The view is counted at most once per session (per-session
 * throttle) and never for a detected bot; either way the response carries the
 * current tallies plus the visitor's reaction so the client can hydrate the rating
 * UI's counts and active state.
 */
final class ArticleViewController extends AbstractArticleEngagementController
{
    /**
     * Handle a view beacon for the article named by $id.
     *
     * @param  Request  $request  The incoming view request
     * @param  string  $id  The knowledge_bases article entry id
     * @return JsonResponse The engagement snapshot (404 when the id is not a published article)
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        // Reject anything that is not a published knowledge_bases article
        $article = $this->resolver->resolvePublishedArticle($id);
        if (! $article instanceof Entry) {
            return $this->notFound();
        }

        // Count the view only for a human that has not already been counted this
        // session; a bot or a repeat view just reads the current tallies.
        if (! $this->crawler->isBot($request->userAgent()) && ! $this->session->hasViewed($id)) {
            $counts = $this->store->incrementViews($id);
            $this->session->markViewed($id);
        } else {
            $counts = $this->store->counts($id);
        }

        // Return the snapshot so the client can hydrate counts + vote state
        return $this->snapshotResponse($id, $counts);
    }
}
