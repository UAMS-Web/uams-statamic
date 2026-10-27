<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Search-index query scope that keeps non-reader-facing collections out of the
 * unified `default` site-search index (config/statamic/search.php).
 *
 * The `default` index indexes `collection:*` (every collection); this scope
 * constrains it to the reader-facing collections, dropping the ones that are
 * infrastructure or building blocks rather than pages a visitor would search for —
 * reusable content blocks, schema.org objects, table data, logo-list items — plus
 * the content-type collections surfaced through their own directories rather than
 * site search (calculators, clinical conditions and services, facilities,
 * organizations, post categories).
 *
 * It also applies the per-entry `searchable` gate (UAMS-Web/uams-statamic-archive#1060/UAMS-Web/uams-statamic-archive#1438) via
 * {@see ExcludeUnsearchableEntries}, so an entry an editor has flagged out of
 * interactive search is dropped from the unified index too — sharing one gate
 * definition with the per-type `clinical_expertise` index.
 *
 * Applied via the index's `query_scope`, so these entries are never indexed (rather
 * than filtered out per query). The provider (Statamic\Search\Searchables\Entries)
 * applies it *before* `whereStatus('published')` when building the index and *after*
 * it on the per-save membership check, so {@see self::apply()} must not assume a
 * clause order; either way the published / scheduled / expired gate is preserved.
 */
final class ExcludeNonSearchableCollections extends Scope
{
    /**
     * Scope handle referenced by the `default` index's `query_scope`.
     *
     * @var string
     */
    protected static $handle = 'exclude_non_searchable_collections';

    /**
     * Collection handles kept out of the unified site-search index.
     *
     * @var array<int, string>
     */
    public const array EXCLUDED_COLLECTIONS = [
        'calculators',
        'clinical_conditions',
        'clinical_specializations',
        'clinical_services',
        'facilities',
        'faq_categories',
        'geographic_locations',
        'knowledge_base_tags',
        'logo_list_items',
        'logos_item_categories',
        'logos_item_labels',
        'organizations',
        'patient_types',
        'people_groups',
        'post_categories',
        'post_tags',
        'redirect_domains',
        'reusable_content',
        'schema_objects',
        'subject_matter',
        'tables',
    ];

    /**
     * Constrain the entry query to the reader-facing collections and drop the
     * per-entry searchable-gated entries.
     *
     * @param  Builder  $query  Entry query builder
     * @param  array<string, mixed>  $values  Unused — a config query scope passes no request values
     */
    public function apply(
        $query,
        $values
    ): void {
        $readerFacing = self::readerFacingCollections();

        // The search provider (Statamic\Search\Searchables\Entries) applies this
        // query scope in two opposite clause orders:
        //
        //  - `provide()` (full index build) runs it *before* `whereStatus('published')`.
        //    Here a *positive* `whereIn('collection', …)` is required: on the wildcard
        //    `default` index the builder's `$collections` starts empty, and a bare
        //    `whereNotIn('collection', …)` leaves it empty, so `whereStatus()`'s
        //    collection auto-detection reads the per-entry gate's later
        //    `whereNotIn('id', …)` (see ExcludeUnsearchableEntries) as an id filter and
        //    collapses the whole index to that id's single collection. A positive
        //    `whereIn` populates `$collections`, short-circuiting that auto-detection.
        //
        //  - `contains()` (the per-save membership check) runs it *after*
        //    `whereStatus('published')`. There the same positive clause trips the
        //    Stache's "collection clause must come before the status clause" guard
        //    (EntryQueryBuilder::verifyCollectionBeforeStatus). A *negative*
        //    `whereNotIn('collection', …)` — with no such guard — filters the
        //    single-entry check identically once the status collections are resolved.
        //
        // `queriedByStatus` is private with no accessor, so the guard's exception is
        // the only signal Statamic exposes; branch on it rather than duplicate the
        // scope across both provider call sites.
        try {
            $query->whereIn(
                'collection',
                $readerFacing
            );
        } catch (\LogicException) {
            $query->whereNotIn(
                'collection',
                self::EXCLUDED_COLLECTIONS
            );
        }

        // Also drop individual entries an editor has flagged out of interactive
        // search (UAMS-Web/uams-statamic-archive#1060/UAMS-Web/uams-statamic-archive#1438), sharing one gate definition with the per-type
        // `clinical_expertise` index's `exclude_unsearchable_entries` scope.
        ExcludeUnsearchableEntries::excludeUnsearchable($query);
    }

    /**
     * The reader-facing collection handles indexed by the unified `default` search
     * index: every collection minus {@see self::EXCLUDED_COLLECTIONS}. Derived from
     * the deny-list so that list stays the single source of truth.
     *
     * @return list<string>
     */
    private static function readerFacingCollections(): array
    {
        $handles = [];
        foreach (CollectionFacade::handles() as $handle) {
            if (\is_string($handle) && ! \in_array($handle, self::EXCLUDED_COLLECTIONS, true)) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }
}
