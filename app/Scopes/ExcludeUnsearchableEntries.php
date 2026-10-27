<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Search-index query scope that keeps entries flagged out of interactive search —
 * a per-entry `searchable` toggle (UAMS-Web/uams-statamic-archive#1060) explicitly set to `false` — out of the
 * site-search indexes (config/statamic/search.php). A broad parent or organizational
 * entry can then stay published, routable, and in the sitemap while never surfacing
 * as its own search result (UAMS-Web/uams-statamic-archive#1438).
 *
 * Applied at index-build time via an index's `query_scope`, so it composes with the
 * provider's `whereStatus('published')` gate (Statamic\Search\Searchables\Entries),
 * which runs afterwards. An entry participates unless it *explicitly* stores
 * `searchable: false`; a missing/null value — every entry predating the toggle, and
 * every collection without the field — is treated as searchable, so nothing
 * currently indexed is silently dropped.
 */
final class ExcludeUnsearchableEntries extends Scope
{
    /**
     * Scope handle referenced by an index's `query_scope`.
     *
     * @var string
     */
    protected static $handle = 'exclude_unsearchable_entries';

    /**
     * Collections whose per-entry `searchable` toggle gates the interactive
     * site-search indexes. Only `clinical_expertise` (Areas of Expertise, UAMS-Web/uams-statamic-archive#1438)
     * surfaces in those indexes today; the ontology collections carry the same
     * toggle for the `/expertise` faceted search (UAMS-Web/uams-statamic-archive#1489), which applies its own gate
     * in {@see ExpertiseByAssociatedOntologySearch}. Extend this if another
     * searchable-gated collection joins the site-search indexes.
     *
     * @var list<string>
     */
    private const array GATED_COLLECTIONS = ['clinical_expertise'];

    /**
     * Exclude entries flagged out of interactive search from the query.
     *
     * @param  Builder  $query  Entry query builder
     * @param  array<string, mixed>  $values  Unused — a config query scope passes no request values
     */
    public function apply(
        $query,
        $values
    ): void {
        self::excludeUnsearchable($query);
    }

    /**
     * Constrain a query to entries that participate in interactive search: every
     * entry except those whose `searchable` toggle is explicitly `false`.
     *
     * No query constraint can do this for a gated collection, and the reason is the
     * index rather than the operators. The Stache's `whereNull` does match an
     * absent key in general (statamic/docs#2002), but in a collection whose blueprint
     * carries the `searchable` toggle, an entry with no stored value, or a stored
     * `null`, is indexed as `false`, while a key no blueprint declares is not
     * (measured on statamic/cms v6.26.0, UAMS-Web/uams-statamic-archive#2703). `whereNull('searchable')`
     * therefore matches nothing there, `where('searchable', false)` matches missing,
     * `null` and `false` alike, and `where('searchable', '!=', false)` keeps only
     * `true` — each drops the entries predating the toggle (a silent flip). So
     * resolve the ids of entries whose toggle is *strictly* `false` in PHP, from the
     * stored data rather than the index, and exclude just those with `whereNotIn`,
     * leaving missing and `true` entries (the searchable default) intact. The scan is over the small
     * {@see self::GATED_COLLECTIONS} set (bounded, in-memory); it runs once per index
     * build and once on the wildcard `default` index's per-save incremental
     * membership check — an accepted minor cost for the strict-`false` correctness.
     *
     * Exposed statically so the unified `default` index (via
     * {@see ExcludeNonSearchableCollections}) and the per-type `clinical_expertise`
     * index share one gate definition.
     *
     * @param  Builder  $query  Entry query builder
     */
    public static function excludeUnsearchable(
        $query
    ): void {
        // Mirror ExpertiseByAssociatedOntologySearch: annotate the Stache builder so
        // the fluent chain and `get()` type-check.
        /** @var EntryQueryBuilder $lookup */
        $lookup = EntryFacade::query();
        $lookup->whereIn(
            'collection',
            self::GATED_COLLECTIONS
        );

        /** @var EntryCollection<int, Entry> $gated */
        $gated = $lookup->get();

        // Ids of entries an editor has explicitly flagged out of interactive search
        // (strict `false`, so a missing toggle is never caught).
        $unsearchableIds = [];
        foreach ($gated as $entry) {
            if ($entry->get('searchable') === false) {
                $id = $entry->id();
                if (is_string($id)) {
                    $unsearchableIds[] = $id;
                }
            }
        }

        if ($unsearchableIds !== []) {
            $query->whereNotIn(
                'id',
                $unsearchableIds
            );
        }
    }
}
