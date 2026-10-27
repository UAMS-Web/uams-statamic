<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\ReplicatorSetFlattener;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * A query scope powering the faceted search on the Areas of Expertise collection
 * index (`/expertise`, UAMS-Web/uams-statamic-archive#1440). Given a visitor's free-text `q`, it narrows the
 * `clinical_expertise` listing to the entries whose associated conditions or
 * services match the term by `title` or `alternate_name`.
 *
 * The term is resolved to the ids of matching `clinical_conditions` /
 * `clinical_services` entries — a case-insensitive substring match on `title` OR
 * on any `alternate_name` replicator row (flattened by the same
 * {@see ReplicatorSetFlattener} the search transformers use) — then the listing
 * is filtered to the Areas of Expertise whose `clinical_conditions` OR
 * `clinical_services` field overlaps those ids. The two legs are OR-ed inside a
 * single nested `where` group so the disjunction cannot leak into an outer
 * conjunction, mirroring {@see OntologyOverlapSpecialInterests}.
 *
 * Filter semantics:
 * - A blank `q` leaves the listing unfiltered (no active search).
 * - A non-blank `q` that matches no condition/service surfaces NO Areas of
 *   Expertise (an empty listing) rather than silently widening to all — a
 *   sentinel id that overlaps nothing forces the empty set.
 *
 * The per-entry `searchable` gate (UAMS-Web/uams-statamic-archive#1060, UAMS-Web/uams-statamic-archive#1489) excludes a condition/service
 * whose `searchable` toggle is explicitly `false` from this facet index — it never
 * surfaces an Area of Expertise through the search. A missing/null `searchable`
 * keeps the established default (participating), so existing conditions/services
 * are unaffected.
 *
 * Example usage on the index listing tag:
 * ```
 * {{ collection:clinical_expertise
 *      query_scope="expertise_by_associated_ontology_search"
 *      q="{{ get:q }}" }}
 * ```
 */
class ExpertiseByAssociatedOntologySearch extends Scope
{
    /**
     * The ontology collections whose matching entries drive the filter, mapped to
     * the Areas-of-Expertise relationship field that stores their ids. An Area of
     * Expertise matches when either field overlaps its collection's matches.
     *
     * @var array<string, string>
     */
    private const array ONTOLOGY_FIELDS = [
        'clinical_conditions' => 'clinical_conditions',
        'clinical_services' => 'clinical_services',
    ];

    /**
     * A sentinel id no entry carries, used to force an empty result set when a
     * non-blank search term matches no condition or service.
     */
    private const string NO_MATCH_SENTINEL = '__no_matching_ontology_term__';

    /**
     * Apply the scope to the query.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array<string, mixed>  $values  Parameters from the collection tag; reads `q`, the visitor's search term.
     * @return Builder The modified query.
     */
    public function apply(
        $query,
        $values
    ) {
        // Normalize the search term; a blank (or non-string) term is not an active search.
        $raw = $values['q'] ?? '';
        $term = \is_string($raw) ? \trim($raw) : '';
        if ($term === '') {
            return $query;
        }

        // Resolve the term to the ids of matching conditions and services.
        $needle = \mb_strtolower($term);
        $idsByField = [];
        foreach (self::ONTOLOGY_FIELDS as $collection => $field) {
            $ids = $this->matchingOntologyIds(
                $collection,
                $needle
            );
            if ($ids !== []) {
                $idsByField[$field] = $ids;
            }
        }

        // A non-blank term that matches nothing must not widen the listing: a
        // sentinel that overlaps no entry forces an empty result set.
        if ($idsByField === []) {
            $idsByField['clinical_conditions'] = [self::NO_MATCH_SENTINEL];
        }

        // Match an Area of Expertise whose conditions field OR services field
        // overlaps the resolved ids, grouped so the OR cannot merge into an outer
        // conjunction.
        //
        // Typed natively, as LiveClassifieds is: on the file entries driver,
        // `whereNested()` passes a `Statamic\Query\Builder`. The eloquent driver's
        // does not, so moving entries to it means revisiting this closure.
        $query->where(function (Builder $query) use ($idsByField): void {
            foreach ($idsByField as $field => $ids) {
                $query->orWhereJsonOverlaps(
                    $field,
                    $ids
                );
            }
        });

        return $query;
    }

    /**
     * Resolve the ids of published, searchable entries in the given ontology
     * collection whose `title` or flattened `alternate_name` contains the search
     * needle.
     *
     * @param  string  $collection  The ontology collection handle.
     * @param  string  $needle  The lower-cased search term.
     * @return list<string> The matching entry ids.
     */
    private function matchingOntologyIds(
        string $collection,
        string $needle
    ): array {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where(
            'collection',
            $collection
        );

        /** @var EntryCollection<int, Entry> $entries */
        $entries = $query->get();

        // Collect the ids of published, searchable entries whose title or alternate
        // name matches.
        $ids = [];
        foreach ($entries as $entry) {
            if (
                $entry->published()
                && $this->isSearchable($entry)
                && $this->entryMatches(
                    $entry,
                    $needle
                )
            ) {
                $id = $entry->id();
                if (\is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Whether an ontology entry participates in interactive search.
     *
     * An entry is searchable unless its per-entry `searchable` toggle (UAMS-Web/uams-statamic-archive#1060) is
     * explicitly `false`; a missing/null value keeps the established default
     * (searchable), so existing conditions/services are unaffected.
     *
     * @param  Entry  $entry  The condition or service entry.
     * @return bool True when the entry may surface an Area of Expertise.
     */
    private function isSearchable(Entry $entry): bool
    {
        return $entry->get('searchable') !== false;
    }

    /**
     * Whether an ontology entry's title or any alternate-name row contains the needle.
     *
     * @param  Entry  $entry  The condition or service entry.
     * @param  string  $needle  The lower-cased search term.
     * @return bool True when the entry matches.
     */
    private function entryMatches(
        Entry $entry,
        string $needle
    ): bool {
        // Title match (case-insensitive substring).
        $title = $entry->get('title');
        if (
            \is_string($title)
            && \str_contains(
                \mb_strtolower($title),
                $needle
            )
        ) {
            return true;
        }

        // Alternate-name match over the flattened replicator rows.
        $alternateNames = ReplicatorSetFlattener::flatten(
            $entry->get('alternate_name'),
            ['text']
        );

        return $alternateNames !== null
            && \str_contains(
                \mb_strtolower($alternateNames),
                $needle
            );
    }
}
