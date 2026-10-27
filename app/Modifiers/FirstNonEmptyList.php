<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: pick the first list-like argument that normalizes to a
 * non-empty array.
 *
 * Each modifier parameter is normalized with the same rules as
 * {@see NormalizeList}. Empty arrays count as empty; this differs from
 * null-coalescing in templates, where an empty array is still “set” and can
 * block fallthrough to later alternatives.
 *
 * Pass candidates in priority order as modifier arguments. The pipe
 * left-hand value is not read; callers may use {@code []} for clarity.
 */
class FirstNonEmptyList extends Modifier
{
    /**
     * Return the first parameter that normalizes to a non-empty list.
     *
     * @param  mixed  $value  Unused; may be {@code []} or any placeholder
     * @param  array<int|string, mixed>  $params  Ordered list-like candidates to try
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return array<int, mixed> First non-empty normalized list, or empty array
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // Initialize list normalizer
        $normalizer = new NormalizeList;

        // Check each parameter in order for the first non-empty list
        foreach ($params as $param) {
            // Normalize the current candidate
            $normalized = $normalizer->index($param, [], []);

            // Skip candidates that resolve to an empty list
            if ($normalized === []) {
                continue;
            }

            // Return the first non-empty normalized list
            return $normalized;
        }

        // Return empty when every candidate was empty
        return [];
    }
}
