<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope for filtering UAMS Health Find-a-Doc ontology collection entries
 * (e.g., `providers`, `expertise`, `locations`, `clinical_resources`) based on
 * overlapping `expertise` values.
 *
 * This scope is used in scenarios where a page builder block
 * (e.g., `uamsweb_block_fad_providers`) defines a set of expertise IDs (via an
 * Entries field), and we need to retrieve the relevant ontology collection
 * entries whose `expertise` field (also an Entries field with multiple
 * selections) contains at least one of those IDs. The scope facilitates an array
 * intersection check between the block's expertise IDs and each ontology item's
 * expertise IDs, returning only matching entries.
 *
 * Example usage in a template:
 * ```
 * {{ collection:providers query_scope="ontology_overlap_expertise" expertise="{{ expertise | pluck:'id' | implode:',' }}" }}
 * ```
 * - The `expertise` parameter passes a comma-separated string of expertise IDs
 *   from the block.
 * - Ontology items are filtered to include those where any of their `expertise`
 *   IDs match any of the block's IDs.
 *
 * Implementation Note:
 * Uses `whereJsonOverlaps()` (Statamic v5.64.0+) for efficient array overlap
 * checking, replacing the previous loop-based approach with `orWhereJsonContains()`.
 */
class OntologyOverlapExpertise extends Scope
{
    /**
     * Apply the scope to the query.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array<string, mixed>  $values  Parameters passed from the collection tag, including 'expertise'.
     * @return Builder The modified query.
     */
    public function apply(
        $query,
        $values
    ) {
        // Extract expertise IDs from the tag parameter (e.g., 'id1,id2' or ['id1', 'id2'])
        $expertiseIds = $values['expertise'] ?? [];

        // ---
        // If the expertise parameter is a string (from `join`), convert it to an array,
        // dropping empty segments. `explode(',', '')` returns `['']`, not `[]`, so an
        // empty or absent `expertise` (e.g. a provider block with no editor pre-filter)
        // would otherwise fall through to a `whereJsonOverlaps('expertise', [''])` that
        // matches no entries; filtering the empties restores the intended pass-through.
        // ---
        if (is_string($expertiseIds)) {
            $expertiseIds = array_values(array_filter(
                explode(
                    ',',
                    $expertiseIds
                ),
                static fn (string $id): bool => $id !== ''
            ));
        }

        // If no valid expertise IDs are provided, return the query unchanged to avoid filtering
        if (! is_array($expertiseIds) || $expertiseIds === []) {
            return $query;
        }

        // ---
        // Apply an OR condition: include ontology items where expertise contains any of
        // the specified IDs
        // ---

        // Check if the expertise array overlaps with any of the specified IDs
        $query->whereJsonOverlaps(
            'expertise',
            $expertiseIds
        );

        return $query;
    }
}
