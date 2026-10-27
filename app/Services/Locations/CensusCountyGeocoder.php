<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;

/**
 * Reverse-geocodes a coordinate to its containing US county via the free,
 * keyless US Census Geocoder.
 *
 * Used to derive a location's Arkansas `regions` term from its map coordinate
 * (UAMS-Web/uams-statamic-archive#962): the resolved county feeds App\Services\Locations\LocationRegionResolver,
 * which maps county -> region. The Census Geocoder is authoritative (it is the
 * TIGER/Line source the boundary data ultimately comes from) and needs no API
 * key.
 *
 * The request is server-side so it can carry an identifying `User-Agent`, and it
 * is routed through {@see OutboundUrlGuard} so the configurable endpoint cannot
 * be pointed at internal infrastructure. Successful resolutions are cached by
 * rounded coordinate — county boundaries are static, so repeated saves or a bulk
 * import of the same coordinate never re-fetch — while transient transport
 * failures are deliberately *not* cached, so a later save retries.
 *
 * @see config/maps.php (the `census` section)
 *
 * @phpstan-type ResolvedCounty array{geoId: string, name: string}
 */
class CensusCountyGeocoder
{
    /**
     * Cache sentinel marking a coordinate that the Census definitively resolved
     * to no county (e.g. a point outside any county), distinct from a transient
     * failure which is not cached at all.
     */
    private const string NO_COUNTY = 'none';

    /**
     * Resolve a coordinate to its containing county, or null when none can be
     * determined (feature disabled, no county at the point, or a transient
     * failure).
     *
     * @param  float  $latitude  The coordinate latitude (decimal degrees)
     * @param  float  $longitude  The coordinate longitude (decimal degrees)
     * @return array{geoId: string, name: string}|null The county GEOID + name, or null
     */
    public function resolve(float $latitude, float $longitude): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $cacheKey = $this->cacheKey($latitude, $longitude);
        $cached = Cache::get($cacheKey);

        // A cached hit is either a resolved county array or the "no county"
        // sentinel; only a genuine cache miss (null) falls through to a fetch.
        if ($cached !== null) {
            return $cached === self::NO_COUNTY || ! \is_array($cached) ? null : $this->normalizeCached($cached);
        }

        $outcome = $this->fetchCounty($latitude, $longitude);

        // A transient failure is not cached, so a later save retries it.
        if (! $outcome['ok']) {
            return null;
        }

        // A definitive result (a county or a confirmed absence) is cached.
        Cache::put($cacheKey, $outcome['county'] ?? self::NO_COUNTY, $this->cacheTtl());

        return $outcome['county'];
    }

    /**
     * Fetch and parse the county for a coordinate.
     *
     * @param  float  $latitude  The coordinate latitude
     * @param  float  $longitude  The coordinate longitude
     * @return array{ok: bool, county: array{geoId: string, name: string}|null} Whether the
     *                                                                          lookup completed, and the county it found (if any)
     */
    private function fetchCounty(float $latitude, float $longitude): array
    {
        $url = $this->buildUrl($latitude, $longitude);

        // Refuse before connecting if the configured endpoint resolves to an
        // internal/loopback/private target (SSRF defense).
        if (! OutboundUrlGuard::isAllowedUrl($url)) {
            return ['ok' => false, 'county' => null];
        }

        try {
            $response = OutboundUrlGuard::fetch(
                'GET',
                $url,
                fn (PendingRequest $pendingRequest): PendingRequest => $pendingRequest
                    ->timeout($this->timeout())
                    ->connectTimeout(3)
                    ->withHeaders([
                        'User-Agent' => $this->userAgent(),
                        'Accept' => 'application/json',
                    ]),
            );
        } catch (\Throwable $throwable) {
            unset($throwable);

            return ['ok' => false, 'county' => null];
        }

        // A non-2xx is treated as transient (not cached) so a later save retries.
        if (! $response->successful()) {
            return ['ok' => false, 'county' => null];
        }

        return ['ok' => true, 'county' => $this->parseCounty($response->json())];
    }

    /**
     * Extract the first county (GEOID + name) from a Census geographies response.
     *
     * @param  mixed  $json  The decoded Census Geocoder response body
     * @return array{geoId: string, name: string}|null The county, or null when absent/malformed
     */
    private function parseCounty($json): ?array
    {
        $counties = data_get($json, 'result.geographies.Counties');

        if (! \is_array($counties) || $counties === []) {
            return null;
        }

        $county = $counties[0];

        if (! \is_array($county)) {
            return null;
        }

        $geoId = $county['GEOID'] ?? null;
        $name = $county['NAME'] ?? null;

        if (! \is_string($geoId) || ! \is_string($name) || $geoId === '' || $name === '') {
            return null;
        }

        return ['geoId' => $geoId, 'name' => $name];
    }

    /**
     * Build the Census Geocoder geographies/coordinates request URL.
     *
     * @param  float  $latitude  The coordinate latitude (the Census `y`)
     * @param  float  $longitude  The coordinate longitude (the Census `x`)
     * @return string The fully-qualified request URL
     */
    private function buildUrl(float $latitude, float $longitude): string
    {
        $params = [
            'x' => (string) $longitude,
            'y' => (string) $latitude,
            'benchmark' => $this->stringConfig('maps.census.benchmark', 'Public_AR_Current'),
            'vintage' => $this->stringConfig('maps.census.vintage', 'Current_Current'),
            'layers' => 'Counties',
            'format' => 'json',
        ];

        return $this->endpoint().'?'.http_build_query($params);
    }

    /**
     * Re-validate a cached county payload back into the strict result shape.
     *
     * @param  array<mixed>  $cached  The cached value
     * @return array{geoId: string, name: string}|null The county, or null when malformed
     */
    private function normalizeCached(array $cached): ?array
    {
        $geoId = $cached['geoId'] ?? null;
        $name = $cached['name'] ?? null;

        if (! \is_string($geoId) || ! \is_string($name) || $geoId === '' || $name === '') {
            return null;
        }

        return ['geoId' => $geoId, 'name' => $name];
    }

    /**
     * The per-coordinate cache key, rounded so near-identical coordinates share
     * a cached county (5 places is ~1 m, well within any county).
     */
    private function cacheKey(float $latitude, float $longitude): string
    {
        return sprintf('maps:census:county:%s,%s', round($latitude, 5), round($longitude, 5));
    }

    /**
     * Whether coordinate-derived county lookup is enabled.
     */
    private function enabled(): bool
    {
        return (bool) config('maps.census.enabled', true);
    }

    /**
     * The configured Census Geocoder endpoint.
     */
    private function endpoint(): string
    {
        return $this->stringConfig(
            'maps.census.endpoint',
            'https://geocoding.geo.census.gov/geocoder/geographies/coordinates',
        );
    }

    /**
     * The descriptive User-Agent identifying this application to the Census API.
     */
    private function userAgent(): string
    {
        $configured = $this->stringConfig('maps.census.user_agent', '');

        if ($configured !== '') {
            return $configured;
        }

        $name = $this->stringConfig('app.name', 'Statamic');

        return sprintf('%s (%s)', $name !== '' ? $name : 'Statamic', $this->stringConfig('app.url', ''));
    }

    /**
     * The outbound request timeout in seconds (at least 1).
     */
    private function timeout(): int
    {
        $timeout = config('maps.census.timeout', 8);

        return is_numeric($timeout) ? max(1, (int) $timeout) : 8;
    }

    /**
     * The resolved-county cache TTL in seconds (at least 0).
     */
    private function cacheTtl(): int
    {
        $ttl = config('maps.census.cache_ttl', 2592000);

        return is_numeric($ttl) ? max(0, (int) $ttl) : 2592000;
    }

    /**
     * Read a config value as a trimmed string, falling back for non-scalars.
     *
     * @param  string  $key  The dotted config key
     * @param  string  $default  The fallback when the entry is non-scalar
     * @return string The trimmed string value
     */
    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        return \is_scalar($value) ? trim((string) $value) : $default;
    }
}
