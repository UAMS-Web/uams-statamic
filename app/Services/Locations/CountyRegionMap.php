<?php

declare(strict_types=1);

namespace App\Services\Locations;

use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Maps an Arkansas county name to its `regions` taxonomy term.
 *
 * The county -> region relationship is authored once, on the `regions` terms
 * themselves: each term lists its constituent counties in `counties_covered`.
 * This service inverts that into a county-name -> region-slug lookup (built once
 * and memoized) so App\Services\Locations\LocationRegionResolver can turn a
 * reverse-geocoded county into a region without duplicating the mapping (UAMS-Web/uams-statamic-archive#962).
 *
 * Names are normalized (case- and whitespace-insensitive) so the Census
 * Geocoder's county `NAME` matches the taxonomy's `counties_covered` entries.
 * The map is Arkansas-only by construction (the `regions` taxonomy lists only AR
 * counties); the resolver still gates on the GEOID state prefix first so an
 * identically-named county in another state cannot match.
 */
class CountyRegionMap
{
    /**
     * Memoized normalized-county-name => region-term-slug map.
     *
     * @var array<string, string>|null
     */
    private ?array $map = null;

    /**
     * The `regions` term slug a county belongs to, or null when unmapped.
     *
     * @param  string  $countyName  The county name (e.g. "Pulaski County")
     * @return string|null The region term slug (e.g. "central-arkansas"), or null
     */
    public function regionSlugForCountyName(string $countyName): ?string
    {
        return $this->map()[$this->normalize($countyName)] ?? null;
    }

    /**
     * The memoized county-name => region-slug map.
     *
     * @return array<string, string>
     */
    private function map(): array
    {
        return $this->map ??= $this->build();
    }

    /**
     * Build the county-name => region-slug map from the `regions` taxonomy terms.
     *
     * @return array<string, string>
     */
    private function build(): array
    {
        $map = [];

        foreach (Term::whereTaxonomy('regions') as $term) {
            if (! $term instanceof LocalizedTerm) {
                continue;
            }

            $counties = $term->get('counties_covered');
            $slug = $term->slug();
            if (! \is_array($counties)) {
                continue;
            }

            if (! \is_string($slug)) {
                continue;
            }

            if ($slug === '') {
                continue;
            }

            foreach ($counties as $county) {
                if (\is_string($county) && trim($county) !== '') {
                    $map[$this->normalize($county)] = $slug;
                }
            }
        }

        unset($term, $counties, $slug, $county);

        return $map;
    }

    /**
     * Normalize a county name for case- and whitespace-insensitive matching.
     *
     * @param  string  $name  The raw county name
     * @return string The normalized key
     */
    private function normalize(string $name): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($name));

        // preg_replace() returns null only when a pattern fails, so the fallback is never used.
        // @pest-mutate-ignore: UnwrapTrim
        return strtolower($collapsed ?? trim($name));
    }
}
