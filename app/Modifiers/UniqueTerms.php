<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: remove duplicate list entries when items expose a stable
 * {@code id()} string.
 *
 * List input is normalized with {@see NormalizeList}. Items that implement
 * {@code id()} are compared by that string; scalars, null and arrays by
 * value; other objects and resources by instance, so the list still dedupes
 * in a deterministic way.
 */
class UniqueTerms extends Modifier
{
    /**
     * Deduplicate list items by stable id when the item exposes {@code id()}.
     *
     * @param  mixed  $value  List-like input {@see NormalizeList} accepts
     * @param  array<int, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return array<int, mixed> First occurrence of each unique id key
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // Normalize the incoming value to a flat list
        $normalizer = new NormalizeList;
        $items = $normalizer->index($value, [], []);

        // Track seen stable keys and build unique output
        $seen = [];
        $out = [];

        // Iterate each item and keep first occurrence per identity key
        foreach ($items as $item) {
            // Build a stable dedupe key for this item
            $key = $this->stableKeyForItem($item);

            // Skip duplicates
            if (isset($seen[$key])) {
                continue;
            }

            // Mark this key as seen. TrueToFalse is equivalent: isset() only
            // cares that the key exists.
            // @pest-mutate-ignore: TrueToFalse
            $seen[$key] = true;

            // Append the first occurrence
            $out[] = $item;
        }

        // Return unique items
        return $out;
    }

    /**
     * Build a string key for deduplicating one list item.
     *
     * @param  mixed  $item  One element after list normalization
     * @return string Stable comparison key
     */
    private function stableKeyForItem(
        mixed $item
    ): string {
        // Prefer object id() when available
        if (\is_object($item) && method_exists($item, 'id')) {
            // Narrow the untyped id() result before the implicit string cast
            /** @var scalar|\Stringable|null $id */
            $id = $item->id();

            return (string) $id;
        }

        // Fall back to serialized scalars. The `scalar:` prefix is cosmetic once
        // serialize() already makes the rest of the key unique, so concat order and
        // left-prefix removal are equivalent.
        if (\is_scalar($item) || $item === null) {
            // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
            return 'scalar:'.\serialize($item);
        }

        // An array has no identity of its own, so compare it by value, the way a
        // scalar is compared (UAMS-Web/uams-statamic-archive#2916). The `array:` prefix is cosmetic for the same
        // reason as `scalar:`.
        if (\is_array($item)) {
            // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
            return 'array:'.\serialize($item);
        }

        // An object without an id() method compares by instance. Its number and a
        // resource's can coincide, so the `obj:` and `resource:` prefixes keep them
        // apart; either one alone is enough, so each is cosmetic on its own.
        if (\is_object($item)) {
            // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
            return 'obj:'.\spl_object_id($item);
        }

        // Only a resource is left, open or closed (is_resource() is false for a
        // closed one, so it cannot narrow this); PHP numbers both kinds the way it
        // numbers objects
        /** @var resource $item */
        // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
        return 'resource:'.\get_resource_id($item);
    }
}
