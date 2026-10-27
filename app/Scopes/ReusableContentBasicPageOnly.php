<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope that restricts selection to entries in the
 * 'reusable_content' collection that use the
 * 'reusable_content_basic_page' blueprint.
 *
 * Apply this scope to any entries field or query where only
 * reusable content basic page entries should be selectable.
 */
class ReusableContentBasicPageOnly extends Scope
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
        // ---
        // Apply filters to restrict query to reusable content basic
        // page entries only
        // ---
        /** @var Builder $query */
        $filtered = $query->where(
            'collection',
            'reusable_content'
        );
        /** @var Builder $filtered */
        $filtered->where(
            'blueprint',
            'reusable_content_basic_page'
        );
    }
}
