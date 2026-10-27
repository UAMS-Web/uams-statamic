<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Support\Statamic\Taxonomies\AugmentedRecognitionListsSeriesTerm;
use Closure;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

/**
 * Routes `recognition_lists_series` terms through a custom augmented class so the
 * virtual `full_name` label resolves consistently.
 *
 * The flat `recognition_lists` taxonomy (and its `award_name` augmented term) was
 * retired when recognition-list membership moved to a structured collection
 * (UAMS-Web/uams-statamic-archive#1546); only the surviving series taxonomy is routed here now.
 */
final class RecognitionListTaxonomyAugmentedRegistrar
{
    /**
     * Register the LocalizedTerm augmented hook that swaps augmented implementations per taxonomy.
     */
    public function register(): void
    {
        // Subscribe to augmented term hydration for recognition list taxonomies
        LocalizedTerm::hook(
            'augmented',
            function (mixed $augmented, Closure $next): mixed {
                // Resolve the localized term instance bound by Statamic for this hook
                /** @var LocalizedTerm $term */
                // @phpstan-ignore varTag.nativeType (Statamic binds this hook closure to the LocalizedTerm at runtime; statically $this is the registrar)
                $term = $this;

                // Read the taxonomy handle for the current term
                /** @var Taxonomy $taxonomy */
                $taxonomy = $term->taxonomy();
                $taxonomyHandle = $taxonomy->handle();

                // Route series taxonomy terms through the series augmented wrapper
                if ($taxonomyHandle === 'recognition_lists_series') {
                    return $next(new AugmentedRecognitionListsSeriesTerm($term));
                }

                // Preserve default augmented term behavior for all other taxonomies
                return $next($augmented);
            }
        );
    }
}
