<?php

declare(strict_types=1);

namespace App\Support\Entries;

use Statamic\Query\Builder;
use Stringable;

/**
 * Applies a Logo List block's any / not / all display logic to an `entries`
 * reference field, for the query scopes that replaced the block's retired
 * `:taxonomy:<handle>:any|not|all` parameters (UAMS-Web/uams-statamic-archive#1863, UAMS-Web/uams-statamic-archive#2533).
 *
 * The three taxonomy parameters were mutually exclusive, so a block expressed its
 * logic by which one it set. Once the taxonomy became a collection that shape no
 * longer exists, and each scope passes the ids and the selected logic here, where
 * the same three-way meaning is applied against the entries field instead.
 */
final class EntryReferenceFilter
{
    /**
     * Constrain the query to entries whose reference field matches the given ids
     * under the given logic.
     *
     * An empty id list applies NO constraint, preserving what the retired taxonomy
     * parameters did when nothing was selected: each resolved to `void`, and
     * `Collection\Entries::queryTaxonomies()` skips an empty taxonomy value.
     *
     * @param  Builder  $query  Entry query builder to constrain.
     * @param  string  $field  The `entries` field holding the references.
     * @param  list<string>  $ids  The selected entry ids.
     * @param  mixed  $logic  The raw display logic: any, not or all.
     */
    public static function apply(
        Builder $query,
        string $field,
        array $ids,
        mixed $logic
    ): void {
        // Apply no constraint when nothing is selected
        if ($ids === []) {
            return;
        }

        match (self::logic($logic)) {
            // Every selected entry must be present on the item.
            'all' => array_walk($ids, static fn (string $id) => $query->whereJsonContains($field, $id)),
            // None of the selected entries may be present.
            'not' => array_walk($ids, static fn (string $id) => $query->whereJsonDoesntContain($field, $id)),
            // Any one of them is enough, the taxonomy parameter's default.
            default => $query->where(static function (Builder $builder) use ($field, $ids): void {
                foreach ($ids as $id) {
                    $builder->orWhereJsonContains($field, $id);
                }
            }),
        };
    }

    /**
     * The display logic the block selected, defaulting to `any`.
     *
     * The logic fields are radios, which augment to a `Statamic\Fields\LabeledValue`
     * at request time rather than to their raw key, so an `is_string()` test fails
     * on the production shape and would silently fall back to `any`, quietly
     * ignoring a `not` or `all` selection. A hermetic array render passes the bare
     * string, so both are accepted.
     *
     * @param  mixed  $logic  The raw `logic` parameter.
     * @return string One of `any`, `not` or `all`.
     */
    public static function logic(mixed $logic): string
    {
        $resolved = match (true) {
            is_string($logic) => $logic,
            $logic instanceof Stringable => (string) $logic,
            default => 'any',
        };

        // Anything but `not` or `all`, `any` included, resolves to the default
        return in_array($resolved, ['not', 'all'], true) ? $resolved : 'any';
    }
}
