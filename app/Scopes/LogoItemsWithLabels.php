<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\Entries\EntryReferenceFilter;
use App\Support\Entries\EntryReferenceIds;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `logo_list_items` query to the items carrying one or
 * more `logos_item_labels` collection entries, honoring the block's any / not /
 * all label logic.
 *
 * UAMS-Web/uams-statamic-archive#2533 converted `logos_item_labels` from a flat taxonomy to a site-scoped
 * structured collection, so an item references its labels through the
 * `logos_item_labels` *entries* field, an array of label entry ids. The Logo List
 * block previously filtered with three mutually exclusive
 * `:taxonomy:logos_item_labels:any|not|all` parameters, and this scope carries
 * that logic across, as `LogoItemsInCategories` did for categories in UAMS-Web/uams-statamic-archive#1863.
 *
 * It takes its own `labels` and `label_logic` parameters so it can be chained
 * after `logo_items_in_categories` on the same tag, which reads `categories` and
 * `logic`:
 * `{{ collection:logo_list_items query_scope="logo_items_in_categories|logo_items_with_labels" :labels="…" :label_logic="…" }}`.
 *
 * An empty selection applies no constraint, as the retired parameters did; see
 * `EntryReferenceFilter`.
 */
class LogoItemsWithLabels extends Scope
{
    /**
     * The entries field holding an item's label references.
     */
    private const string FIELD = 'logos_item_labels';

    /**
     * Constrain the query to items matching the given label ids under the given
     * logic.
     *
     * @param  Builder  $query  Entry query builder for the `logo_list_items` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `labels` carries the ids, `label_logic` one of any|not|all
     */
    public function apply(
        $query,
        $values
    ): void {
        // Apply the block's label selection under its logic; see EntryReferenceFilter.
        EntryReferenceFilter::apply(
            $query,
            self::FIELD,
            EntryReferenceIds::from($values['labels'] ?? null),
            $values['label_logic'] ?? null,
        );
    }
}
