<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * A query scope for the "special interest in [condition/service]" filter on the
 * Find-a-Doc `providers` / `locations` page-builder blocks. It filters a
 * `people` / `clinical_locations` listing to the profiles whose own authored
 * special-interest selection overlaps the condition/service ids the block chose.
 *
 * Special interest is authored in two typed fields (UAMS-Web/uams-statamic-archive#1064) — a profile keeps its
 * condition ids in `special_interest_clinical_conditions` and its service ids in
 * `special_interest_clinical_services` — so this scope reads BOTH block
 * parameters and matches a profile whose conditions field overlaps the selected
 * condition ids OR whose services field overlaps the selected service ids. The
 * two legs are OR-ed inside a single nested `where` group so the disjunction
 * cannot leak into an outer conjunction (an unrelated `where` on the same query).
 * It is the sibling of the shipped `ontology_overlap_expertise` filter, over the
 * two typed special-interest fields instead of the single `expertise` field.
 *
 * The live render wiring (dropping this `query_scope` into the FaD block card
 * markup) is deferred to the block-completion tickets UAMS-Web/uams-statamic-archive#61/UAMS-Web/uams-statamic-archive#62/UAMS-Web/uams-statamic-archive#63/UAMS-Web/uams-statamic-archive#64/UAMS-Web/uams-statamic-archive#919, which
 * own the currently-placeholder provider/location render partials; this scope is
 * the infrastructure they consume, so it is exercised here in isolation.
 *
 * Example usage in a template (once those tickets build the live card):
 * ```
 * {{ collection:clinical_providers
 *      query_scope="ontology_overlap_special_interests"
 *      special_interest_clinical_conditions="{{ special_interest_clinical_conditions | pluck:'id' | implode:',' }}"
 *      special_interest_clinical_services="{{ special_interest_clinical_services | pluck:'id' | implode:',' }}"
 * }}
 * ```
 *
 * Implementation Note:
 * Uses `whereJsonOverlaps()` (Statamic v5.64.0+) for efficient array overlap
 * checking, matching the sibling ontology-overlap scopes.
 */
class OntologyOverlapSpecialInterests extends Scope
{
    /**
     * The two typed special-interest fields (UAMS-Web/uams-statamic-archive#1064), keyed by the block filter
     * parameter that supplies their ids. A profile matches if either field
     * overlaps its selection.
     *
     * @var array<string, string>
     */
    private const array OVERLAP_FIELDS = [
        'special_interest_clinical_conditions' => 'special_interest_clinical_conditions',
        'special_interest_clinical_services' => 'special_interest_clinical_services',
    ];

    /**
     * Apply the scope to the query.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  array<string, mixed>  $values  Parameters passed from the collection tag: the two typed special-interest id lists.
     * @return Builder The modified query.
     */
    public function apply(
        $query,
        $values
    ) {
        // Resolve each typed field's block-selected ids, dropping blanks.
        $selectionByField = [];
        foreach (self::OVERLAP_FIELDS as $parameter => $field) {
            $ids = $this->extractIds($values[$parameter] ?? []);
            if ($ids !== []) {
                $selectionByField[$field] = $ids;
            }
        }

        // No special-interest selection on either field → leave the listing unfiltered.
        if ($selectionByField === []) {
            return $query;
        }

        // Match a profile whose conditions field OR services field overlaps its
        // selection, grouped so the OR cannot merge into an outer conjunction.
        //
        // Typed natively, as LiveClassifieds is: on the file entries driver,
        // `whereNested()` passes a `Statamic\Query\Builder`. The eloquent driver's
        // does not, so moving entries to it means revisiting this closure.
        $query->where(function (Builder $query) use ($selectionByField): void {
            foreach ($selectionByField as $field => $ids) {
                $query->orWhereJsonOverlaps($field, $ids);
            }
        });

        return $query;
    }

    /**
     * Normalize a block filter parameter to a list of non-blank id strings.
     *
     * The value arrives either already as an array (a raw Entries value) or as a
     * comma-separated string (from `implode` in the template). An unfiltered
     * block passes an empty string, which `explode` turns into `['']`; those
     * blanks are dropped so an empty filter leaves the listing unfiltered instead
     * of matching nothing.
     *
     * @return list<string>
     */
    private function extractIds(
        mixed $value
    ): array {
        if (\is_string($value)) {
            $value = \explode(
                ',',
                $value
            );
        }

        if (! \is_array($value)) {
            return [];
        }

        return \array_values(\array_filter(
            $value,
            static fn (mixed $id): bool => \is_string($id) && $id !== ''
        ));
    }
}
