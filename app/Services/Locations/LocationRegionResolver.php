<?php

declare(strict_types=1);

namespace App\Services\Locations;

/**
 * Resolves a location's Arkansas `regions` term(s) from its coordinate (UAMS-Web/uams-statamic-archive#962).
 *
 * Reverse-geocodes the coordinate to its county (via the Census Geocoder) and
 * maps that county to its region term. Regions are an Arkansas-only concept, so
 * a coordinate whose county is outside Arkansas yields no region; the GEOID
 * state-FIPS gate also prevents an identically-named county in another state
 * (e.g. a "Union County" outside AR) from matching the Arkansas term.
 *
 * One coordinate resolves to exactly one county, hence at most one region — the
 * list return type keeps the shape forward-compatible with the multi-select
 * `regions` field and an editor narrowing or broadening the suggestion.
 */
class LocationRegionResolver
{
    /**
     * Arkansas state FIPS code; the GEOID's leading two digits. Regions are an
     * Arkansas concept, so only AR counties resolve to a region.
     */
    private const string ARKANSAS_STATE_FIPS = '05';

    public function __construct(
        private CensusCountyGeocoder $geocoder,
        private CountyRegionMap $countyRegionMap,
    ) {}

    /**
     * The Arkansas region term slug(s) for a coordinate.
     *
     * @param  float  $latitude  The coordinate latitude (decimal degrees)
     * @param  float  $longitude  The coordinate longitude (decimal degrees)
     * @return array<int, string> The matching region slug(s); empty when out of Arkansas or unresolved
     */
    public function regionsForCoordinate(float $latitude, float $longitude): array
    {
        $county = $this->geocoder->resolve($latitude, $longitude);

        if ($county === null) {
            return [];
        }

        // Gate on the GEOID's Arkansas state prefix before the name lookup, so a
        // same-named county in another state cannot match an Arkansas region.
        if (! str_starts_with($county['geoId'], self::ARKANSAS_STATE_FIPS)) {
            return [];
        }

        $slug = $this->countyRegionMap->regionSlugForCountyName($county['name']);

        return $slug !== null ? [$slug] : [];
    }
}
