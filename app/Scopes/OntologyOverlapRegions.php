<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * A query scope for filtering UAMS Health Find-a-Doc ontology collection entries
 * (e.g., `providers`, `people`) to those whose related clinical locations sit in
 * one of a set of Arkansas regions.
 *
 * A page-builder block (e.g., `uamsweb_block_fad_providers`) supplies a set of
 * region term slugs via a Terms field with handle `regions`. Because a provider's
 * own `regions` is a *computed-only* aggregate (derived from its locations, never
 * stored) it cannot be a `where` target, so the scope routes through the
 * authoritative `regions` on `clinical_locations`:
 *
 *   1. resolve the block's region slugs to the ids of `clinical_locations` entries
 *      whose stored `regions` overlap them (one bounded lookup query), then
 *   2. constrain the ontology query IN PLACE with
 *      `whereJsonOverlaps('clinical_locations', $ids)`.
 *
 * Mutating in place is required: Statamic's `Scope::apply()` is a `@return void`
 * contract and every call site discards the return, so a scope that instead
 * `->get()`-filtered-and-returned a new collection was a silent no-op that left the
 * ontology query completely unfiltered.
 *
 * Example usage in a template:
 * ```
 * {{ collection:providers query_scope="ontology_overlap_regions" regions="{{ regions | pluck:'id' | implode:',' }}" }}
 * ```
 */
class OntologyOverlapRegions extends Scope
{
    /**
     * Apply the scope to the query.
     *
     * @param  Builder  $query  The ontology collection query builder.
     * @param  array<string, mixed>  $values  Parameters passed from the collection tag, including 'regions'.
     * @return Builder The modified query.
     */
    public function apply(
        $query,
        $values
    ) {
        // Resolve the block's region parameter to a list of bare region slugs.
        $regionSlugs = $this->regionSlugsFromValues($values);

        // Leave the query unchanged when no region filter is supplied.
        if ($regionSlugs === []) {
            return $query;
        }

        // Resolve the requested regions to the ids of the locations that sit in them.
        $locationIds = $this->locationIdsInRegions($regionSlugs);

        // Constrain the ontology query IN PLACE to entries related to those
        // locations. An empty id list overlaps nothing, so no locations in the
        // requested region correctly yields no entries.
        $query->whereJsonOverlaps(
            'clinical_locations',
            $locationIds
        );

        return $query;
    }

    /**
     * Normalize the `regions` block parameter to a list of bare region slugs,
     * dropping the optional `regions::` prefix and empty segments.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function regionSlugsFromValues(array $values): array
    {
        // Read the region parameter, defaulting to an empty selection.
        $regions = $values['regions'] ?? [];

        // A `join`/`implode` from the template arrives as a comma-separated string.
        if (\is_string($regions)) {
            $regions = \explode(',', $regions);
        }

        // Ignore any non-list value (e.g. a stray scalar the tag did not set).
        if (! \is_array($regions)) {
            return [];
        }

        // Strip the optional `regions::` prefix and drop empty segments.
        $slugs = [];

        foreach ($regions as $region) {
            // Coerce each entry to a string; ignore non-scalar noise.
            $slug = \is_scalar($region) ? \str_replace('regions::', '', (string) $region) : '';

            // Keep only non-empty slugs.
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        // Dedupe for a stable, minimal candidate set.
        return \array_values(\array_unique($slugs));
    }

    /**
     * Query `clinical_locations` for the ids of entries whose stored `regions`
     * overlap the requested slugs.
     *
     * Matches both stored formats — the bare slug and the `regions::slug` term
     * reference — so the lookup is robust to how the Terms field persisted.
     *
     * @param  array<int, string>  $regionSlugs
     * @return array<int, string>
     */
    private function locationIdsInRegions(array $regionSlugs): array
    {
        // Build both stored representations of each region for the overlap check.
        $candidates = [];

        foreach ($regionSlugs as $slug) {
            $candidates[] = $slug;
            $candidates[] = 'regions::'.$slug;
        }

        // One bounded lookup: locations whose authoritative `regions` overlap.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', 'clinical_locations');
        $query->whereJsonOverlaps('regions', $candidates);

        /** @var Collection<int, Entry> $locations */
        $locations = $query->get();

        // Collect the matching location ids, guarding the loosely-typed id().
        $locationIds = [];

        foreach ($locations as $location) {
            $id = $location->id();

            if (is_int($id) || is_string($id)) {
                $locationIds[] = (string) $id;
            }
        }

        return $locationIds;
    }
}
