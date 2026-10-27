<?php

declare(strict_types=1);

namespace App\Modifiers;

use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;
use Statamic\Support\Arr;
use Statamic\Support\Str;

/**
 * Antlers modifier: strip HTML tags from each string in a list.
 *
 * Optional arguments work like the core strip_tags modifier: pass nothing to
 * strip all tags, pass tag names to strip only those (others remain), or pass a
 * variable name whose value is the list of tags to strip. Implementation follows
 * CoreModifiers::stripTags() and Statamic\Support\Str::stripTags().
 *
 * The left-hand value may be an indexed array, a Collection, or a single scalar
 * (treated as a one-element list). List items that are Value instances are
 * unwrapped before stripping.
 *
 * Examples (parallel to strip_tags, with a list on the left):
 *
 * - `{{ lines | strip_tags_array }}`
 * - `{{ lines | strip_tags_array('p') }}`
 * - `{{ lines | strip_tags_array([p, blockquote]) }}`
 *
 * @see https://statamic.dev/modifiers/strip_tags
 */
class StripTagsArray extends Modifier
{
    /**
     * Strip tags from each list item.
     *
     * @param  mixed  $value  Indexed list of strings, collection, or scalar
     * @param  array<int|string, mixed>  $params  Tag names, context key, or list (same rules as strip_tags)
     * @param  array<int|string, mixed>  $context  Antlers modifier context
     * @return array<int, string> Stripped strings in list order
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
        // Resolve the tag list the same way as CoreModifiers::stripTags()
        // ---

        // Read the first parameter as the default tag configuration
        $tags = $params[0] ?? [];

        // Check whether Antlers runtime method syntax is active
        if (! $this->usingRuntimeMethodSyntax($context)) {
            // Coerce the context key to a string the same way core strip_tags does
            $tagKey = \is_scalar($tags) ? (string) $tags : '';

            // Resolve tag names from context when the first param references it
            $tags = ($tags)
                ? Arr::get(
                    $context,
                    $tagKey,
                    $params
                )
                : $params;
        }

        // Coerce tag names to a plain array for Str::stripTags()
        $tags = (array) $tags;

        // ---
        // Strip tags from each string, preserving list order
        // ---

        // Initialize the output list
        /** @var array<int, string> $result */
        $result = [];

        // Walk each item and append its stripped string form
        foreach ($items as $item) {
            // Unwrap Statamic Value wrappers when present
            if ($item instanceof Value) {
                $item = $item->value();
            }

            // Coerce the item to a string the same way the core modifier does
            $itemString = (\is_scalar($item) || $item instanceof \Stringable)
                ? (string) $item
                : '';

            // Strip tags using the same helper as the core modifier
            $stripped = Str::stripTags(
                $itemString,
                $tags
            );

            // Coerce the stripped result to a string before appending
            $result[] = \is_scalar($stripped) ? (string) $stripped : '';
        }

        // Return the list of stripped strings
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

    /**
     * True when Antlers runtime method syntax supplied modifier arguments.
     *
     * Uses the same `{__method_args}` context convention as Statamic core
     * strip_tags so tag parameters resolve identically.
     *
     * @param  array<int|string, mixed>  $context  Modifier context
     * @return bool True when runtime method syntax is in use
     */
    private function usingRuntimeMethodSyntax(
        array $context
    ): bool {
        return \array_key_exists(
            '{__method_args}',
            $context
        );
    }
}
