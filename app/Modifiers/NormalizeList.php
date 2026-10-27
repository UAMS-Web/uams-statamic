<?php

declare(strict_types=1);

namespace App\Modifiers;

use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as DatabaseQueryBuilder;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: turn one list-like value into a simple indexed array.
 *
 * Statamic `entries` and similar fields often augment to a
 * {@see Collection} or a lazy query ({@see StatamicQueryBuilder} or Laravel
 * builders), not a PHP `array`. A common Antlers mistake is to use
 * `is_array` and otherwise wrap in `[$value]`, which nests the whole
 * collection or builder. Loops then iterate the wrong thing and
 * `title` (and similar) appears empty.
 *
 * Behavior: null, false, and `''` become `[]`. Query builders are
 * resolved with {@code ->get()}. {@see Collection} values and plain arrays
 * become a flat list of items; any query builders inside an array are
 * expanded. Any other single value becomes a one-element array.
 */
class NormalizeList extends Modifier
{
    /**
     * Normalize the value to a list array.
     *
     * @param  mixed  $value  Empty, scalar, array, collection, or query builder
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused
     * @return array<int, mixed> Normalized list of items
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // ---
        // Treat empty sentinels as an empty list
        // ---

        // Check if the value is empty, false, or an empty string
        if (
            in_array($value, [null, false, ''], true)
        ) {
            // If it is, return an empty array
            return [];
        }

        // --
        // Resolve Statamic entries queries (e.g., lazy relationship builders)
        // ---

        // Check if the value is a query builder
        if ($this->isQueryBuilder($value)) {
            // If it is, return the results of the query builder
            /** @var Collection<int|string, mixed> $results */
            // @phpstan-ignore method.notFound (Statamic's query-builder contract omits get(); every concrete builder — Statamic and Laravel — provides it)
            $results = $value->get();

            return array_values($results->all());
        }

        // ---
        // Unwrap Laravel / Statamic collections to items
        // ---

        // Check if the value is a collection
        if ($value instanceof Collection) {
            // If it is, return the items of the collection
            return $this->flattenListItems(array_values($value->all()));
        }

        // ---
        // Reindex plain arrays and unwrap nested builders
        // ---

        // Check if the value is an array
        if (\is_array($value)) {
            // If it is, return the items of the array
            return $this->flattenListItems(array_values($value));
        }

        // Return the value as a one-element array
        return [$value];
    }

    /**
     * Expand any query builders in a list and reindex.
     *
     * @param  array<int, mixed>  $items  List of items to flatten
     * @return array<int, mixed> Flattened list of items
     */
    private function flattenListItems(
        array $items
    ): array {
        // ---
        // Build a flat list without nested lazy query objects
        // ---

        // Initialize the output array
        $out = [];

        // Iterate over each item in the list
        foreach ($items as $item) {
            // Check if the item is a query builder
            if ($this->isQueryBuilder($item)) {
                // If it is, expand the query builder and add the results to the output array
                /** @var Collection<int|string, mixed> $results */
                // @phpstan-ignore method.notFound (Statamic's query-builder contract omits get(); every concrete builder — Statamic and Laravel — provides it)
                $results = $item->get();
                $out = [
                    ...$out,
                    ...array_values($results->all()),
                ];
            } else {
                // Otherwise, add the item to the output array
                $out[] = $item;
            }
        }

        // Return the output array
        return $out;
    }

    /**
     * True when the value is a Statamic or Laravel query builder.
     *
     * Uses the same instanceof rules as Statamic\Support\Comparator::isQueryBuilder()
     * but not the Compare facade, so pure PHPUnit tests do not need the app.
     *
     * @param  mixed  $value  Value to inspect
     * @return bool True when the value is a Statamic or Laravel query builder
     *
     * @phpstan-assert-if-true StatamicQueryBuilder|DatabaseQueryBuilder|EloquentQueryBuilder<Model> $value
     */
    private function isQueryBuilder(
        mixed $value
    ): bool {
        return $value instanceof StatamicQueryBuilder
            || $value instanceof DatabaseQueryBuilder
            || $value instanceof EloquentQueryBuilder;
    }
}
