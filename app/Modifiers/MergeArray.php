<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: concatenate list-like values into one indexed array.
 *
 * Prefer this over Statamic's built-in `merge` when Antlers-built arrays or
 * relationship values trigger errors or merge incorrectly. The left-hand
 * value and every argument are normalized with {@see NormalizeList}
 * first, so Laravel `Collection` instances (including Statamic entry
 * lists), Statamic or Laravel query builders, plain arrays, and scalars
 * all flatten the same way before concatenation.
 *
 * Arguments equal to {@code null}, {@code false}, or {@code ''} contribute
 * nothing after normalization (NormalizeList maps them to {@code []}), so
 * optional fields (e.g. unset creator roles) do not push empty placeholders
 * into the result.
 *
 * Examples:
 *   {{ $arr = [] }}
 *   {{ $arr = $arr | merge_array('resources/js/site.js') }}
 *   {{ $arr = $arr | merge_array('a.css', 'b.css') }}
 *   {{ $arr = $arr | merge_array(['scss/global.scss', 'scss/theme.scss']) }}
 */
class MergeArray extends Modifier
{
    /**
     * Normalize the base value and each parameter, then concatenate lists.
     *
     * @param  mixed  $value  Base list; any input {@see NormalizeList} accepts
     * @param  array<int|string, mixed>  $params  Extra values to append after normalization
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return array<int, mixed> Concatenated list of items
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // ---
        // Build the accumulator from the left-hand value
        // ---

        // Initialize the normalizer
        $normalizer = new NormalizeList;

        // Normalize the base value
        $result = $normalizer->index($value, [], []);

        // ---
        // Append each argument after the same list normalization
        // ---

        // Iterate over each parameter. null / false / '' need no special skip:
        // NormalizeList already maps them to [], and spreading [] is a no-op.
        foreach ($params as $param) {
            // Concatenate the parameter to the result
            $result = [
                ...$result,
                ...$normalizer->index($param, [], []),
            ];
        }

        // Return the concatenated list of items
        return $result;
    }
}
