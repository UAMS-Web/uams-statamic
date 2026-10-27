<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope that restricts selection to entries in the
 * 'reusable_content' collection that use the
 * 'reusable_content_landing_page' blueprint.
 *
 * Apply this scope to any entries field or query where only
 * reusable content landing page entries should be selectable.
 */
class ReusableContentLandingPageOnly extends Scope
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
        // Apply filters to restrict query to reusable content landing
        // page entries only
        // ---
        /** @var Builder $query */
        $query = $query
            ->where(
                'collection',
                'reusable_content'
            );
        $query
            ->where(
                'blueprint',
                'reusable_content_landing_page'
            );
    }
}
