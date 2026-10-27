<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Entry query scope filtering the clinical_resources index to a single resource
 * subtype — the entry's blueprint handle (`article` / `audio` / `document` /
 * `infographic` / `video`) — from the `?type=` query param.
 *
 * Drives the on-page resource-type control (`clinical_resources/_type_filter`,
 * UAMS-Web/uams-statamic-archive#1468). A blank (or absent) type — the "All" case — leaves the query
 * unfiltered so every subtype lists; a value constrains to that blueprint.
 *
 * A scope is used rather than the collection tag's inline `blueprint:is`
 * *because* of the "All" case: `blueprint:is="{{ empty }}"` matches NO entry in
 * Statamic 6 (the shipped UAMS-Web/uams-statamic-archive#576 listing assumed an empty condition would be
 * rejected and list everything — it is not), so the index rendered its empty
 * state by default. Normalizing the blank case here restores the browse-all view.
 *
 * Example usage on the index listing tag:
 * ```
 * {{ collection:clinical_resources
 *      query_scope="clinical_resource_type"
 *      type="{{ get:type }}" }}
 * ```
 */
class ClinicalResourceType extends Scope
{
    /**
     * Apply the resource-type filter to the query.
     *
     * @param  Builder  $query  Entry query builder (modified in place).
     * @param  array<string, mixed>  $values  Parameters from the collection tag; reads `type`, the requested blueprint handle.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Normalize the requested type; a blank (or non-string) value is the "All" case.
        $raw = $values['type'] ?? '';
        $type = \is_string($raw) ? \trim($raw) : '';
        if ($type === '') {
            return;
        }

        // Constrain to entries of the requested blueprint subtype.
        $query->where('blueprint', $type);
    }
}
