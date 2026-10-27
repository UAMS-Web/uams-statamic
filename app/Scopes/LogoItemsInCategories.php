<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\Entries\EntryReferenceFilter;
use App\Support\Entries\EntryReferenceIds;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `logo_list_items` query to the items filed under one
 * or more `logos_item_categories` collection entries, honoring the block's
 * any / not / all display logic.
 *
 * UAMS-Web/uams-statamic-archive#1863 migrated `logos_item_categories` from a flat taxonomy to a structured
 * collection, so an item now references its categories through the
 * `logos_item_categories` *entries* field — an array of category entry ids. The
 * Logo List block previously filtered with three mutually exclusive
 * `:taxonomy:logos_item_categories:any|not|all` parameters; this scope carries
 * that same three-way logic across, because the taxonomy parameter has no
 * meaning once the taxonomy is gone.
 *
 * Invoked from the block as
 * `{{ collection:logo_list_items query_scope="logo_items_in_categories" :categories="…" :logic="…" }}`.
 *
 * An empty selection applies NO constraint. The three `:taxonomy:` parameters it
 * replaces each resolved to `void` when their logic did not match, and
 * `Collection\Entries::queryTaxonomies()` skips an empty taxonomy value — so a
 * block with no category selected listed every item. The base `$block_valid`
 * guard does not require a category, so that block does render, and matching
 * nothing here would silently empty it.
 */
class LogoItemsInCategories extends Scope
{
    /**
     * The entries field holding an item's category references.
     */
    private const string FIELD = 'logos_item_categories';

    /**
     * Constrain the query to items matching the given category ids under the
     * given logic.
     *
     * @param  Builder  $query  Entry query builder for the `logo_list_items` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters; `categories` carries the ids, `logic` one of any|not|all
     */
    public function apply(
        $query,
        $values
    ): void {
        // Apply the block's category selection under its logic; see EntryReferenceFilter.
        EntryReferenceFilter::apply(
            $query,
            self::FIELD,
            EntryReferenceIds::from($values['categories'] ?? null),
            $values['logic'] ?? null,
        );
    }
}
