<?php

declare(strict_types=1);

namespace App\SearchTransformers;

use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Taxonomies\Term;

/**
 * Search transformer that resolves a stored term slug to the term's title for the search
 * index, falling back to the raw value when the term cannot be found.
 */
class TermsToTitleTransformer
{
    /**
     * Transform a Terms field value (slug) into the title of the matching term.
     *
     * Dynamically detects the taxonomy from the field's configuration in the
     * searchable term's blueprint, avoiding a hardcoded taxonomy map.
     *
     * @param  mixed  $value  The field value (slug)
     * @param  string  $field  The field name (e.g., 'publication', 'data_source')
     * @param  mixed  $searchable  Searchable model from the index; non-terms fall
     *                             back to the raw value
     * @return ?string The title of the term, or the original value if not found
     */
    public function handle(
        mixed $value,
        string $field,
        mixed $searchable
    ): ?string {
        if (! $value) {
            return null;
        }

        // ---
        // The field value is a slug; narrow the mixed input to a string so the
        // fallback returns and the term-ID interpolation are well-typed. A
        // truthy value reaching here is a scalar slug, so the scalar guard
        // before the cast preserves behavior.
        // ---
        $value = is_scalar($value) ? (string) $value : '';

        // ---
        // Ensure $searchable is a taxonomy Term and has a blueprint (the
        // contract interface is empty; the concrete class carries blueprint())
        // ---
        /** @var Blueprint|null $blueprint */
        $blueprint = $searchable instanceof Term ? $searchable->blueprint() : null;
        if (
            ! $searchable instanceof Term
            || ! $blueprint
        ) {
            return $value; // Fallback if not a term or no blueprint
        }

        // ---
        // Get the field configuration from the blueprint. Accept both the core
        // `terms` fieldtype and our `terms_search_index` extension of it — the
        // indexes wire this transformer onto `terms_search_index` fields
        // (certification `organization`, recognition-list `series` /
        // `data_source` / `publication`), which the narrower `terms`-only guard
        // silently skipped, indexing the raw slug instead of the term title.
        // ---
        /** @var Field|null $fieldConfig */
        $fieldConfig = $blueprint->field($field);
        if (
            ! $fieldConfig
            || ! in_array($fieldConfig->type(), ['terms', 'terms_search_index'], true)
        ) {
            return $value; // Fallback if field not found or not a Terms field
        }

        // ---
        // A single-taxonomy Terms field stores a BARE slug, but a
        // multi-taxonomy one stores a fully-qualified `taxonomy::slug` term ID
        // (Statamic\Fieldtypes\Terms::process() only rewrites to a bare slug
        // when usingSingleTaxonomy()). Resolve an already-qualified value
        // directly — prepending a taxonomy handle would double-prefix it and it
        // would never resolve (the `data_source` field spans two taxonomies and
        // stores e.g. `recognition_lists_data_sources::datajoe-research`).
        // ---
        if (str_contains($value, '::')) {
            /** @var Term|null $term */
            $term = TermFacade::find($value);
            if ($term === null) {
                return $value; // Qualified id but no such term; index the raw id
            }

            $title = $term->title();

            return is_scalar($title) ? (string) $title : $value;
        }

        // Extract the taxonomies from the field config
        $taxonomies = $fieldConfig->get(
            'taxonomies',
            []
        );
        if (! is_array($taxonomies) || $taxonomies === []) {
            return $value; // Fallback if no taxonomies defined
        }

        // ---
        // A bare slug can belong to any configured taxonomy, so resolve against
        // each in turn and return the first matching term's title.
        // ---
        foreach ($taxonomies as $taxonomy) {
            $taxonomyHandle = is_scalar($taxonomy) ? (string) $taxonomy : '';
            if ($taxonomyHandle === '') {
                continue;
            }

            // Construct term ID (taxonomy::slug) and fetch the term
            /** @var Term|null $term */
            $term = TermFacade::find(sprintf('%s::%s', $taxonomyHandle, $value));
            if ($term === null) {
                continue;
            }

            $title = $term->title();

            return is_scalar($title) ? (string) $title : $value;
        }

        // No matching term in any configured taxonomy; index the raw slug.
        return $value;
    }
}
