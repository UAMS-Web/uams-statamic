<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\KnowledgeBase\KnowledgeBaseMountRedirector;
use Statamic\Tags\Tags;

/**
 * Provides the {{ knowledge_base_mount_redirect }} tag.
 *
 * Mirrors the project's {{ find_redirect_rule }} flow for computed knowledge
 * base mount redirects: when the current (404) URI is the old un-mounted URL of
 * a now-mounted knowledge base, the tag yields a single `target` — the current
 * mounted URL — so the wrapped `{{ redirect }}` tag can issue a permanent
 * redirect. When no mapping applies the tag renders nothing, leaving the rest
 * of the 404 page to display.
 *
 * @see KnowledgeBaseMountRedirector
 */
class KnowledgeBaseMountRedirect extends Tags
{
    /**
     * Create the tag.
     *
     * @param  KnowledgeBaseMountRedirector  $redirector  Resolves old-URL-to-mounted-URL targets
     */
    public function __construct(
        private readonly KnowledgeBaseMountRedirector $redirector
    ) {}

    /**
     * The {{ knowledge_base_mount_redirect }} tag.
     *
     * Resolves the mounted URL the current request should redirect to, if any.
     *
     * Returns:
     *   - Array: A single-element list `[['target' => <url>]]` when the current
     *     URL maps to a mounted knowledge base, so the pair tag's content
     *     renders once with `target` available; an empty array otherwise.
     *
     * @return array<int, array<string, string>> The redirect target, or an empty array
     */
    public function index(): array
    {
        // Resolve the mounted URL for the current request, if it is an old URL.
        $target = $this->redirector->target(request());

        // Yield the target for the wrapped content, or render nothing.
        return $target !== null
            ? [['target' => $target]]
            : [];
    }
}
