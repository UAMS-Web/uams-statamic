<?php

declare(strict_types=1);

namespace App\Support\Entries;

use Illuminate\Support\Collection as SupportCollection;
use RuntimeException;
use Statamic\Contracts\Query\Builder;
use Statamic\Entries\Entry;
use Stringable;

/**
 * Normalizes an `entries` fieldtype selection to a list of entry ids (UAMS-Web/uams-statamic-archive#1863).
 *
 * A query scope reading an entries-reference parameter cannot assume it receives
 * ids. The same field arrives in three shapes depending on the caller:
 *
 *   - **Raw ids** — a `{{ field | raw }}` value, or a hermetic array render in a
 *     test: `['abc-123']`.
 *   - **An augmented `entries` field** — NOT a list of `Entry` objects, as it
 *     looks. `Entries::augment()` returns a `Statamic\Query\StatusQueryBuilder`,
 *     so a `:param="field"` binding hands the scope a *query builder*. Treating
 *     that as a scalar yields zero ids, which turns "the items in this category"
 *     into "no items" — and an empty result reads as a content problem rather
 *     than a code one. This shape is REFUSED with a named error instead.
 *   - **Entry objects** — a caller that already resolved the relation.
 *   - **A single value** — a `max_items: 1` field hands over one item rather
 *     than a list of one.
 *
 * Templates must therefore pass `| raw`, as the People and Logo List blocks do.
 * That is cheaper than resolving the builder anyway, and the refusal makes
 * forgetting it a loud failure rather than a silently empty block.
 *
 * Centralizing the flattening keeps `App\Scopes\PeopleInGroups` and
 * `App\Scopes\LogoItemsInCategories` from each getting it subtly wrong. They are
 * named in prose rather than with `{@see}` so this shared helper does not import
 * its own consumers.
 */
final class EntryReferenceIds
{
    /**
     * Flatten an entries-field parameter to the list of entry ids it denotes.
     *
     * @param  mixed  $value  The raw tag parameter.
     * @return list<string> The referenced entry ids, empty when nothing is selected.
     */
    public static function from(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = match (true) {
            is_array($value) => $value,
            $value instanceof SupportCollection => $value->all(),
            // An augmented `entries` field, which is a query builder rather than
            // a list of ids. Refuse it loudly — see below.
            $value instanceof Builder => self::refuseBuilder(),
            default => [$value],
        };

        $ids = [];

        foreach ($items as $item) {
            $id = self::idOf($item);

            if ($id !== null && $id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Refuse an augmented `entries` field, naming the fix.
     *
     * `Statamic\Contracts\Query\Builder` is an empty marker interface, so there
     * is no typed way to resolve it here — and silently returning no ids is the
     * failure this class exists to prevent: the caller's block renders its shell
     * around an empty list, which reads as missing content rather than as a bug.
     * Throwing turns that into a named error at the call site.
     *
     * @return never
     */
    private static function refuseBuilder(): array
    {
        throw new RuntimeException(
            'An entries-reference parameter arrived augmented (a query builder) rather than as ids. '
            .'Pass the raw value instead — `{{ $ids = <field> | raw }}` — as the People and Logo List blocks do.'
        );
    }

    /**
     * The entry id a single selection item denotes.
     *
     * @param  mixed  $item  One selected item: an id, an `Entry`, or a stringable.
     * @return ?string The id, or null when the item denotes none.
     */
    private static function idOf(mixed $item): ?string
    {
        if ($item instanceof Entry) {
            $id = $item->id();

            return is_string($id) ? $id : null;
        }

        if (is_string($item)) {
            return $item;
        }

        // A `LabeledValue` / `ArrayableString` and friends stringify to their key.
        if ($item instanceof Stringable) {
            return (string) $item;
        }

        return null;
    }
}
