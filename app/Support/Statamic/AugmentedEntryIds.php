<?php

declare(strict_types=1);

namespace App\Support\Statamic;

use App\Tags\NewsSpringboard;
use App\Tags\UamsNews;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder as QueryBuilder;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;

/**
 * Normalizes an `entries` fieldtype value — however Antlers hands it over — to a
 * list of entry ids.
 *
 * A single-item picker augments to an {@see Entry}; a multi-item picker to an
 * unexecuted query builder (e.g. `OrderedQueryBuilder`) or a collection of
 * entries; and a raw (un-augmented) value is the stored id string or a list of
 * them. This one place unwraps every shape so tags reading entries fields —
 * {@see UamsNews} (UAMS-Web/uams-statamic-archive#76) and {@see NewsSpringboard} (UAMS-Web/uams-statamic-archive#1158) —
 * don't each re-implement it.
 */
final class AugmentedEntryIds
{
    /**
     * Extract a de-duplicated list of entry ids from an entries-field value.
     *
     * @return array<int, string>
     */
    public static function fromValue(mixed $value): array
    {
        $ids = [];

        foreach (self::toList($value) as $item) {
            $id = self::entryId($item);

            if ($id !== null && $id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Extract the first entry id from an entries-field value (for a single-item
     * picker), or null when empty.
     */
    public static function firstId(mixed $value): ?string
    {
        return self::fromValue($value)[0] ?? null;
    }

    /**
     * Extract an entry id from a single item (an augmented {@see Entry} or a raw
     * stored id).
     */
    private static function entryId(mixed $item): ?string
    {
        // A grid/replicator row hands its fields over as Value wrappers.
        if ($item instanceof Value) {
            $item = self::unwrap($item);
        }

        // An augmented Entry exposes id().
        if ($item instanceof Entry) {
            $id = $item->id();

            return \is_string($id) ? $id : null;
        }

        // A raw stored value is the entry id itself.
        if (\is_scalar($item)) {
            $raw = trim((string) $item);

            return $raw !== '' ? $raw : null;
        }

        return null;
    }

    /**
     * Normalize a value to a flat list: a collection/array/query builder becomes
     * a list of its items; a scalar/object becomes a single-element list; null
     * becomes empty.
     *
     * @return array<int, mixed>
     */
    private static function toList(mixed $value): array
    {
        // Unwrap a Statamic field value (a grid/replicator row's entries field
        // arrives wrapped) before inspecting its shape.
        if ($value instanceof Value) {
            $value = self::unwrap($value);
        }

        if ($value === null) {
            return [];
        }

        $value = self::materializeQueryBuilder($value);

        if ($value instanceof Collection) {
            return $value->values()->all();
        }

        return \is_array($value) ? array_values($value) : [$value];
    }

    /**
     * Execute an unexecuted query builder into its result collection; pass every
     * other value through untouched.
     *
     * A multi-item entries field augments to an unexecuted query builder rather
     * than a collection of objects, so the value must be run before its entries
     * can be read. (`method_exists` narrows the empty {@see QueryBuilder} marker
     * interface so the `get()` call type-checks.)
     */
    private static function materializeQueryBuilder(mixed $value): mixed
    {
        if ($value instanceof QueryBuilder && method_exists($value, 'get')) {
            return $value->get();
        }

        return $value;
    }

    /**
     * Unwrap a Statamic field {@see Value} to its underlying data.
     *
     * Prefers the augmented value, but falls back to the raw stored value when
     * augmentation yields null — a grid/replicator row's entries subfield does
     * not resolve outside a full request, yet its raw value is already the list
     * of entry ids this class is after.
     */
    private static function unwrap(Value $value): mixed
    {
        return $value->value() ?? $value->raw();
    }
}
