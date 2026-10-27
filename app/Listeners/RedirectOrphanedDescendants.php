<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use App\Services\OrphanedAncestors\OrphanedAncestorResult;
use Illuminate\Http\Response;
use Statamic\Entries\Entry;
use Statamic\Events\ResponseCreated;

/**
 * Universal front-end redirect for a published entry orphaned by an unpublished
 * structural ancestor (UAMS-Web/uams-statamic-archive#1429), replacing the per-template `{{ %redirect }}` of
 * UAMS-Web/uams-statamic-archive#217. `ResponseCreated` fires for every rendered front-end response with the
 * rendered data object; when that object is an entry in a handled (routable +
 * hierarchical, not opted-out) collection with an unpublished ancestor, the
 * successful page response is rewritten to a 302 toward the nearest live
 * ancestor, else a published mount root, else the site root.
 *
 * Control Panel live-preview renders are skipped so an editor can still preview
 * an entry sitting under an unpublished ancestor. The nearest-live-ancestor and
 * published-mount targets never themselves redirect (no chain); the site-root
 * terminal fallback is best-effort.
 */
class RedirectOrphanedDescendants
{
    public function __construct(
        private readonly OrphanedAncestorResolver $resolver
    ) {}

    /**
     * Handle the ResponseCreated event.
     *
     * @param  ResponseCreated  $event  The response + rendered data object
     */
    public function handle(
        ResponseCreated $event
    ): void {
        // Never rewrite a Control Panel live-preview render: the editor is
        // intentionally viewing the entry they are editing, which may legitimately
        // sit under an unpublished ancestor. This mirrors DataResponse::handleDraft,
        // which uses the same signal to let preview show content that would not
        // render for a live visitor.
        if (request()->isLivePreview()) {
            return;
        }

        // Only a rendered entry can be orphaned by an ancestor.
        $data = $event->data;
        if (! $data instanceof Entry) {
            return;
        }

        // Only rewrite a normal successful page response — never an existing
        // redirect, a protected/login response, or an error.
        $response = $event->response;
        if (! $response->isSuccessful()) {
            return;
        }

        // No unpublished ancestor (or an unhandled collection) → leave the page.
        $result = $this->resolver->resolve($data);
        if (! $result instanceof OrphanedAncestorResult) {
            return;
        }

        // Rewrite the successful page into a 302 to the resolved live target.
        $response->setStatusCode(Response::HTTP_FOUND);
        $response->headers->set('Location', $result->redirectUrl);
        $response->setContent('');
    }
}
