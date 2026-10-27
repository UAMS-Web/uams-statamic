<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope that restricts selection to entries in the
 * 'knowledge_bases' collection that use the 'topic' blueprint.
 *
 * Apply this scope to any entries field or query where only topics should be
 * selectable from the knowledge base collection.
 */
class KnowledgeBaseTopicOnly extends Scope
{
    /**
     * Apply the scope to the query builder.
     *
     * @param  Builder  $query  The query builder instance to modify
     * @param  array<string, mixed>  $values  Values passed from field configuration
     */
    public function apply(
        $query,
        $values
    ): void {
        // Apply filters to restrict query to knowledge base topic entries only
        /** @var Builder $query */
        $query->where(
            'collection',
            'knowledge_bases'
        );

        $query->where(
            'blueprint',
            'topic'
        );
    }
}
