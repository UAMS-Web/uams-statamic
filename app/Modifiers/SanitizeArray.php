<?php

declare(strict_types=1);

namespace App\Modifiers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: convert special characters to HTML entities per list item.
 *
 * Same behavior as Statamic `sanitize`: each string is passed through
 * `htmlspecialchars()` with `ENT_QUOTES` and `statamic.system.charset` (see core
 * implementation). Optional double encoding matches the docs: pass `true` as the
 * first argument to double-encode entities (e.g. when preserving JSON
 * formatting), as described under "Double Encoding" on the sanitize page.
 *
 * The left-hand value may be an indexed array, a Collection, or a single scalar
 * (treated as a one-element list). List items that are Value instances are
 * unwrapped before encoding.
 *
 * Examples (same argument patterns as sanitize, list on the left):
 *
 * - `{{ lines | sanitize_array }}`
 * - `{{ lines | sanitize_array(true) }}`
 *
 * @see https://statamic.dev/modifiers/sanitize
 */
class SanitizeArray extends Modifier
{
    /**
     * Sanitize each list item for safe HTML text.
     *
     * @param  mixed  $value  Indexed list of strings, collection, or scalar
     * @param  array<int|string, mixed>  $params  Optional first argument: pass `true` for double encoding (same as sanitize)
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return array<int, string> Encoded strings in list order
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): array {
        // ---
        // Normalize the incoming value to a flat, reindexed list
        // ---

        // Build the working list from array, collection, or scalar input
        $items = $this->listItems($value);

        // ---
        // Read double_encode from the first parameter (same as core sanitize)
        // ---

        // Resolve the htmlspecialchars double_encode flag from params
        $doubleEncode = (bool) ($params[0] ?? false);

        // ---
        // Resolve charset from Statamic config (same as core sanitize)
        // ---

        // Read the application charset used for htmlspecialchars
        $charsetConfig = Config::get(
            'statamic.system.charset',
            'UTF-8'
        );

        // Coerce the config value to a string charset for htmlspecialchars
        $charset = \is_string($charsetConfig) ? $charsetConfig : 'UTF-8';

        // ---
        // Encode each string, preserving list order
        // ---

        // Initialize the output list
        $result = [];

        // Walk each item and append its encoded string form
        foreach ($items as $item) {
            // Unwrap Statamic Value wrappers when present
            if ($item instanceof Value) {
                $item = $item->value();
            }

            // Coerce the item to a string like the core sanitize modifier
            $itemString = \is_scalar($item) ? (string) $item : '';

            // Encode special characters like the core sanitize modifier
            $result[] = htmlspecialchars(
                $itemString,
                ENT_QUOTES,
                $charset,
                $doubleEncode
            );
        }

        // Return the list of encoded strings
        return $result;
    }

    /**
     * Turn the modifier value into a reindexed list.
     *
     * @param  mixed  $value  Array, collection, or scalar
     * @return array<int, mixed> List items before per-item string coercion
     */
    private function listItems(
        mixed $value
    ): array {
        // Treat empty sentinels as an empty list
        if (
            in_array($value, [null, false, ''], true)
        ) {
            return [];
        }

        // Unwrap Laravel collections to their underlying items
        if ($value instanceof Collection) {
            return array_values($value->all());
        }

        // Reindex plain arrays without copying nested structures deeply
        if (\is_array($value)) {
            return array_values($value);
        }

        // Wrap any other single value as a one-element list
        return [$value];
    }
}
