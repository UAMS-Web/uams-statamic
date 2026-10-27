<?php

declare(strict_types=1);

namespace App\Modifiers;

use Illuminate\Database\Eloquent\Model;
use Statamic\Facades\Compare;
use Statamic\Modifiers\Modifier;
use Statamic\Query\Builder;

/**
 * Returns a count safe for link/list values that may be a single object.
 *
 * When the value is an array or Countable (e.g. Collection), returns its count.
 * When the value is a single object (e.g. Statamic ArrayableLink), returns 1 so
 * templates can treat "one link" the same as "array of one link" without
 * triggering count() type errors.
 *
 * Use in Antlers wherever a field may be either multiple items or a single
 * link/asset (e.g. items, link_list, url, image).
 *
 * Every current caller passes a replicator set, an assets value, or a link
 * list. A relation gate uses the core `| count`, which counts the published
 * entries of an `entries` relation (UAMS-Web/uams-statamic-archive#1693, UAMS-Web/uams-statamic-archive#2422).
 */
class SafeCount extends Modifier
{
    /**
     * Return a count safe for non-array, non-Countable values.
     *
     * @param  mixed  $value  The value to count (array, Countable, or single object)
     * @param  array<int, string>  $params  Any parameters (unused)
     * @param  array<string, mixed>  $context  Contextual values (unused)
     * @return int Count of items, or 1 for a single object
     */
    public function index(
        $value,
        array $params,
        array $context
    ): int {
        // Check if the value is a Query Builder
        if (Compare::isQueryBuilder($value)) {
            /** @var Builder|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<Model> $value */
            // Return the count of the value
            $count = $value->count();

            return \is_numeric($count) ? (int) $count : 0;
        }

        // Check if the value is an array or Countable
        if (
            is_countable($value)
        ) {
            // Return the count of the value
            return \count($value);
        }

        // Return 1 for a single object
        return 1;
    }
}
