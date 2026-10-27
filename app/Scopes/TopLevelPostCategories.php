<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `post_categories` query to top-level categories —
 * the roots of the structure, with no parent.
 *
 * Backs the browse-all `/category` index (UAMS-Web/uams-statamic-archive#1435), which lists the top of the
 * category tree; drilling into subcategories continues through each archive's
 * child list (UAMS-Web/uams-statamic-archive#1434) rather than rendering the whole tree on one page. A
 * top-level category has no `parent`, so the roots are exactly the parent-less
 * entries.
 */
class TopLevelPostCategories extends Scope
{
    /**
     * Constrain the query to categories that have no parent (structure roots).
     *
     * @param  Builder  $query  Entry query builder for the `post_categories` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters (unused; the scope is single-purpose)
     */
    public function apply(
        $query,
        $values
    ): void {
        $query->whereNull('parent');
    }
}
