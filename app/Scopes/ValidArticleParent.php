<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope for filtering entries to only those with the 'topic' blueprint.
 *
 * This scope is used in the 'parent' field of the 'article' blueprint to ensure
 * that only entries with the 'topic' blueprint are selectable as parents in the
 * Statamic control panel. It restricts the query to entries in the
 * 'knowledge_bases' collection with the 'topic' blueprint, enforcing the
 * hierarchical structure where articles must be children of topics.
 */
class ValidArticleParent extends Scope
{
    /**
     * Apply the scope to the query builder.
     *
     * @param  Builder  $query  The query builder instance to modify
     * @param  array<string, mixed>  $values  An array of values passed to the scope, typically from field configuration
     */
    public function apply(
        $query,
        $values
    ): void {
        // ---
        // Restrict the query to entries with the 'topic' blueprint. This ensures that
        // only valid parent entries are selectable for 'article' entries.
        // ---
        $query->where(
            'blueprint',
            'topic'
        );
    }
}
