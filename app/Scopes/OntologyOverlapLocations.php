<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope for filtering UAMS Health Find-a-Doc ontology collection entries
 * (e.g., `providers`, `expertise`, `clinical_resources`) based on overlapping
 * `locations` values.
 *
 * This scope is used in scenarios where a page builder block
 * (e.g., `uamsweb_block_fad_providers`) defines a set of location IDs (via an
 * Entries field), and we need to retrieve the relevant ontology collection
 * entries whose `clinical_locations` field (also an Entries field with multiple
 * selections) contains at least one of those IDs. The scope facilitates an array
 * intersection check between the block's location IDs and each ontology item's
 * location IDs, returning only matching entries.
 *
 * The stored handle is `clinical_locations` — the universal FaD locations field
 * (`uamsweb_common_fad.clinical_locations`) on `people` / `clinical_expertise`.
 * There is no bare `locations` field; filtering it matched nothing (UAMS-Web/uams-statamic-archive#1366).
 *
 * Example usage in a template:
 * ```
 * {{ collection:providers query_scope="ontology_overlap_location" locations="{{ locations | pluck:'id' | implode:',' }}" }}
 * ```
 * - The `locations` parameter passes a comma-separated string of location IDs
 *   from the block.
 * - Ontology items are filtered to include those where any of their
 *   `clinical_locations` IDs match any of the block's IDs.
 *
 * Implementation Note:
 * Uses `whereJsonOverlaps()` (Statamic v5.64.0+) for efficient array overlap
 * checking, replacing the previous loop-based approach with `orWhereJsonContains()`.
 */
class OntologyOverlapLocations extends Scope
{
    /**
     * Apply the scope to the query.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array<string, mixed>  $values  Parameters passed from the collection tag, including 'locations'.
     * @return Builder The modified query.
     */
    public function apply(
        $query,
        $values
    ) {
        // Extract location IDs from the tag parameter (e.g., 'id1,id2' or ['id1', 'id2'])
        $locationIds = $values['locations'] ?? [];

        // If the locations parameter is a string (from implode), convert it to an
        // array, dropping empty segments. `explode(',', '')` returns `['']`, not
        // `[]`, so an empty or absent `locations` would otherwise fall through to a
        // `whereJsonOverlaps('clinical_locations', [''])` that matches no entries.
        if (is_string($locationIds)) {
            $locationIds = array_values(array_filter(
                explode(
                    ',',
                    $locationIds
                ),
                static fn (string $id): bool => $id !== ''
            ));
        }

        // If no valid location IDs are provided, return the query unchanged to avoid filtering
        if (! is_array($locationIds) || $locationIds === []) {
            return $query;
        }

        // ---
        // Apply an OR condition: include ontology items where clinical_locations
        // contains any of the specified IDs
        // ---

        // Check if the clinical_locations array overlaps with any of the specified IDs
        $query->whereJsonOverlaps(
            'clinical_locations',
            $locationIds
        );

        return $query;
    }
}
