<?php

declare(strict_types=1);

namespace App\Http\Controllers\KnowledgeBase;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Entries\Entry;

/**
 * Records a like / dislike reaction on a knowledge base article and returns the
 * current engagement snapshot (UAMS-Web/uams-statamic-archive#286).
 *
 * The rating UI posts here. The vote is resolved against the visitor's previous
 * reaction stored in the session, so re-sending the same reaction toggles it off,
 * a new reaction switches sides, and repeat-voting is prevented. Ratings are
 * accepted only for a published article whose knowledge base has ratings enabled,
 * and a detected bot's vote is ignored. The response mirrors the view endpoint so
 * the client updates the counts and active state from one shape.
 */
final class ArticleReactionController extends AbstractArticleEngagementController
{
    /**
     * Handle a reaction request for the article named by $id.
     *
     * @param  Request  $request  The incoming reaction request (expects a `reaction` of `like` or `dislike`)
     * @param  string  $id  The knowledge_bases article entry id
     * @return JsonResponse The engagement snapshot, or an error payload (404 unknown article, 403 ratings disabled, 422 invalid reaction)
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        // Reject anything that is not a published knowledge_bases article
        $article = $this->resolver->resolvePublishedArticle($id);
        if (! $article instanceof Entry) {
            return $this->notFound();
        }

        // Reject reactions on a knowledge base that has ratings turned off
        if (! $this->resolver->ratingEnabled($article)) {
            return response()->json(
                ['error' => 'Ratings are not enabled for this article.'],
                403
            );
        }

        // Require a recognized reaction handle
        $requested = $request->input('reaction');
        if (! is_string($requested) || ! $this->store->isReaction($requested)) {
            return response()->json(
                ['error' => 'Invalid reaction.'],
                422
            );
        }

        // A detected bot's vote is ignored: return the current state unchanged
        if ($this->crawler->isBot($request->userAgent())) {
            return $this->snapshotResponse($id, $this->store->counts($id));
        }

        // Apply the vote relative to this session's previous reaction (toggle off,
        // switch sides, or cast fresh) and persist the resulting reaction state.
        $previous = $this->session->reactionFor($id);
        $result = $this->store->applyReaction($id, $previous, $requested);
        $this->session->setReaction($id, $result['reaction']);

        // Return the snapshot reflecting the updated tallies + vote state
        return $this->snapshotResponse($id, $result['counts']);
    }
}
