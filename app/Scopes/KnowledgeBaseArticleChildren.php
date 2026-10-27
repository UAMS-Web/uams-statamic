<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope that restricts entries to immediate children of the current
 * entry in the 'knowledge_bases' collection with the 'article' blueprint.
 *
 * This scope filters entries to only those that are direct children of the
 * current entry (topic or knowledge_base), ensuring only immediate child
 * articles are returned. Used in Antlers templates to display articles
 * belonging to a specific topic or knowledge base.
 */
class KnowledgeBaseArticleChildren extends Scope
{
    /**
     * Apply the scope to the query builder.
     *
     * @param  Builder  $query  The query builder instance to modify
     * @param  array<string, mixed>  $values  Values passed from field configuration or Antlers template parameters
     */
    public function apply(
        $query,
        $values
    ): void {
        // ---
        // Apply filter to restrict query to article blueprint entries only
        //
        // Note: Collection is already specified in the Antlers tag, so we don't
        // set it here to avoid "collection clause must come before status clause"
        // error
        // ---
        $query->where(
            'blueprint',
            'article'
        );

        // ---
        // Get the parent entry ID from the values parameter (passed from
        // Antlers template, e.g., parent="{{ id }}")
        // ---
        $parentId = $values['parent'] ?? null;

        // Check if parent ID was provided
        if ($parentId !== null) {
            // Filter to immediate children of the parent entry
            $query->where(
                'parent',
                $parentId
            );
        }
    }
}
