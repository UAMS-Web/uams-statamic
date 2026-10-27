<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Services\KnowledgeBase\KnowledgeBaseSubtreeArticleIds;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope that restricts a `knowledge_bases` search to the published
 * `article` entries within one knowledge-base subtree.
 *
 * Applied by the in-context KB search bar (UAMS-Web/uams-statamic-archive#506) through the shared
 * `{{ search:results }}` plumbing: the bar submits the current entry's id as
 * `scope_root` (a `knowledge_base` root for whole-KB search, a `topic` for
 * topic-scoped search), and this scope intersects the search results with that
 * subtree's published article ids. Topic/root hits are excluded — only terminal
 * article content is reachable, matching the empty-entry redirect (UAMS-Web/uams-statamic-archive#215/UAMS-Web/uams-statamic-archive#216).
 *
 * The subtree is resolved off the collection structure (an id-set intersection),
 * never a `blueprint` filter on the search builder — an augmented result's
 * `blueprint` is a Blueprint object, not the handle string, so only `id`
 * comparisons are reliable against supplemented search results.
 */
class KnowledgeBaseSubtreeArticles extends Scope
{
    /**
     * @param  KnowledgeBaseSubtreeArticleIds  $subtreeArticleIds  Resolves the published article ids within a subtree root
     */
    public function __construct(
        private readonly KnowledgeBaseSubtreeArticleIds $subtreeArticleIds
    ) {}

    /**
     * Apply the scope to the query builder.
     *
     * @param  mixed  $query  The search query builder (or any value when not bound to a query)
     * @param  mixed  $values  Tag parameters passed from `{{ search:results }}`; carries `scope_root`
     */
    public function apply(
        mixed $query,
        mixed $values
    ): void {
        // Stop when the scope is not applied to a Statamic query builder
        if (! $query instanceof Builder) {
            return;
        }

        // Read the subtree root entry id submitted by the KB search bar. `$values`
        // is a tag Parameters bag (ArrayAccess) at render time, or a plain array
        // when a config/field scope applies it.
        $scopeRoot = null;

        if (is_array($values) || $values instanceof \ArrayAccess) {
            $scopeRoot = $values['scope_root'] ?? null;
        }

        // Normalize the root to a non-empty string entry id
        $rootId = is_string($scopeRoot) ? $scopeRoot : '';

        // A scoped KB search with no resolvable root must constrain to nothing —
        // never fall back to the whole index (a blank/tampered id-set is empty,
        // not unbounded).
        if ($rootId === '') {
            $query->whereIn(
                'id',
                []
            );

            return;
        }

        // Resolve the published article ids within the subtree rooted at the entry
        $articleIds = $this->subtreeArticleIds->forRoot($rootId);

        // Intersect the search results with the subtree's published article ids;
        // an empty set correctly yields no results for an empty subtree.
        $query->whereIn(
            'id',
            $articleIds
        );
    }
}
