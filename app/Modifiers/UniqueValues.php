<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: remove duplicate list entries while preserving first-seen
 * order.
 *
 * List input is normalized with {@see NormalizeList}. Equality uses a stable
 * serialized key per item so scalars and {@code null} dedupe predictably;
 * objects compare by identity unless they serialize distinctly.
 */
class UniqueValues extends Modifier
{
    /**
     * Normalize to a list, then drop entries whose stable key was seen
     * already.
     *
     * @param  mixed  $value  List-like input {@see NormalizeList} accepts
     * @param  array<int, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return array<int, mixed> Unique values in order
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // Normalize the incoming value to a flat list
        $normalizer = new NormalizeList;
        $items = $normalizer->index($value, [], []);

        // Track seen string keys and collect unique scalars
        $seen = [];
        $out = [];

        // Iterate each item and keep first occurrence per string key
        foreach ($items as $item) {
            // Build a comparison key for deduplication
            $key = $this->scalarKey($item);

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

        // Return unique values
        return $out;
    }

    /**
     * Convert one item to a stable string key for deduplication.
     *
     * @param  mixed  $item  List item
     * @return string Hashable key for isset dedupe
     */
    private function scalarKey(
        mixed $item
    ): string {
        // Represent scalars and null with a prefixed serialized form. The
        // `s:`/`o:`/`x:` prefixes are cosmetic once serialize()/spl_object_id()
        // make the rest of the key unique. Or→And and RemoveEarlyReturn only move
        // scalars onto the `x:` path with the same serialize() payload, so
        // dedupe results are unchanged.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, RemoveEarlyReturn
        if (\is_scalar($item) || $item === null) {
            // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
            return 's:'.\serialize($item);
        }

        // Represent objects by spl id when not scalar
        if (\is_object($item)) {
            // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
            return 'o:'.\spl_object_id($item);
        }

        // Arrays and resources: fall back to serialization
        // @pest-mutate-ignore: ConcatRemoveLeft, ConcatSwitchSides
        return 'x:'.\serialize($item);
    }
}
