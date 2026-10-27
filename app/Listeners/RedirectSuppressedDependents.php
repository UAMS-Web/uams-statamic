<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\PublishDependencies\PublishDependencyResolver;
use App\Services\PublishDependencies\PublishDependencyResult;
use Illuminate\Http\Response;
use Statamic\Entries\Entry;
use Statamic\Events\ResponseCreated;

/**
 * Front-end redirect for a dependent entry suppressed because its declared
 * `publish_depends_on` source(s) have gone dark (UAMS-Web/uams-statamic-archive#1593), mirroring
 * {@see RedirectOrphanedDescendants}. `ResponseCreated` fires for every rendered
 * front-end response with the rendered data object; when that object is a
 * suppressed dependent, the successful page response is rewritten to a 302 toward
 * the collection mount root, else the site root.
 *
 * The dependent's own stored `published` / `status_and_visibility` is never
 * touched — the suppression is entirely render-time — so re-publishing a source
 * restores the page automatically on the next request. Control Panel live-preview
 * renders are skipped so an editor can still preview a dependent whose sources are
 * down.
 */
class RedirectSuppressedDependents
{
    public function __construct(
        private readonly PublishDependencyResolver $resolver
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
        // have down sources. Mirrors RedirectOrphanedDescendants.
        if (request()->isLivePreview()) {
            return;
        }

        // Only a rendered entry can be a suppressed dependent.
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

        // Not suppressed (or not opted in) → leave the page.
        $result = $this->resolver->resolve($data);
        if (! $result instanceof PublishDependencyResult) {
            return;
        }

        // Rewrite the successful page into a 302 to the resolved target.
        $response->setStatusCode(Response::HTTP_FOUND);
        $response->headers->set('Location', $result->redirectUrl);
        $response->setContent('');
    }
}
