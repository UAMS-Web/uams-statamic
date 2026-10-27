<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a JSON-LD `GeoCoordinates` fragment from a coordinate pair.
 *
 * Parameters (all optional; an empty or unresolved coordinate emits nothing):
 *   - coordinate: bound value from the `Gps` fieldtype (`App\Fieldtypes\Gps`);
 *     an associative array of `lat` / `lng` (and an ignored `zoom`), or the
 *     schema.org `latitude` / `longitude` keys. Supplies defaults for the
 *     scalar parameters below when those parameters are not passed on the tag.
 *   - latitude, longitude: numeric strings or numbers; override the matching
 *     value taken from `coordinate` when present on the tag.
 *   - elevation: numeric string or number; the `Gps` field stores no elevation,
 *     so this is only honored when passed explicitly.
 *
 * Latitude and longitude are emitted as JSON numbers (not strings) so consumers
 * read them numerically. Output is empty unless both latitude and longitude
 * resolve to finite numbers.
 *
 * Example with a Gps field:
 *   {{ schema_geo_coordinates :coordinate="map" }}
 *
 * Example with explicit scalars:
 *   {{ schema_geo_coordinates latitude="34.7492719" longitude="-92.3198281" }}
 *
 * @property Parameters $params
 */
class SchemaGeoCoordinates extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_geo_coordinates }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_geo_coordinates';

    /**
     * Memoized `SchemaObjectFragmentBuilder::normalizedType()` result for `GeoCoordinates`.
     */
    private static ?string $normalizedGeoCoordinatesType = null;

    /**
     * Build JSON text for the current parameters and Antlers context.
     *
     * @return string JSON object, or empty string when no fragment is produced
     */
    public function index(): string
    {
        // Build default scalar params from the Gps fieldtype when bound
        $fromCoordinate = $this->scalarParamsFromCoordinateValue();

        // Resolve latitude, preferring an explicit tag parameter when passed
        $latitude = $this->numericParam('latitude', $fromCoordinate['latitude'] ?? null);

        // Resolve longitude, preferring an explicit tag parameter when passed
        $longitude = $this->numericParam('longitude', $fromCoordinate['longitude'] ?? null);

        // A GeoCoordinates fragment requires both latitude and longitude
        if ($latitude === null || $longitude === null) {
            // Return an empty string when the coordinate pair is incomplete
            return '';
        }

        // Resolve the schema.org `@type` URI for GeoCoordinates (cached)
        $geoType = $this->cachedNormalizedGeoCoordinatesType();

        // Check if the GeoCoordinates type did not resolve
        if ($geoType === null) {
            // Return an empty string when the type is unusable
            return '';
        }

        // Build the GeoCoordinates payload with numeric latitude/longitude
        $payload = [
            '@type' => $geoType,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        // Add elevation only when an explicit, numeric value was passed
        $elevation = $this->numericParam('elevation', null);

        // Check if an elevation value was resolved
        if ($elevation !== null) {
            // Include the elevation in the payload
            $payload['elevation'] = $elevation;
        }

        // Serialize the fragment for JSON-LD embedding
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Check if encoding failed
        if ($json === false) {
            // Return an empty string when JSON encoding fails
            return '';
        }

        // Return the JSON string
        return $json;
    }

    /**
     * Resolve a numeric tag parameter to a float, falling back to a default.
     *
     * Prefers an explicit tag parameter when that parameter is present on the
     * tag (so a passed value wins even over a `coordinate`-derived default).
     *
     * @param  string  $param  Tag parameter name (e.g. `latitude`)
     * @param  mixed  $default  Value used when the parameter is absent from the tag
     * @return float|null Finite float, or null when the value is not numeric/finite
     */
    private function numericParam(string $param, mixed $default): ?float
    {
        // Prefer an explicit tag parameter when that parameter was passed
        $raw = $this->params->has($param)
            ? $this->params->get($param)
            : $default;

        // Unwrap a Statamic field value before numeric coercion
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Reject values that are not numeric (null, '', non-numeric strings)
        if (! \is_numeric($raw)) {
            // Return null to signal an unusable coordinate component
            return null;
        }

        // Coerce the numeric value to a float
        $float = (float) $raw;

        // Reject non-finite floats (NAN/INF would serialize to invalid JSON)
        if (! is_finite($float)) {
            // Return null when the float is not finite
            return null;
        }

        // Return the finite float coordinate component
        return $float;
    }

    /**
     * Map a Gps fieldtype stored value to `latitude` / `longitude` scalar keys.
     *
     * @return array<string, mixed> Keys are tag param names (`latitude`, `longitude`)
     */
    private function scalarParamsFromCoordinateValue(): array
    {
        // Check if the coordinate parameter was not passed
        if (! $this->params->has('coordinate')) {
            // Return an empty map when there is no Gps field binding
            return [];
        }

        // Read the raw coordinate value from the tag
        $raw = $this->params->get('coordinate');

        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Check for a non-array coordinate value
        if (! \is_array($raw)) {
            // Return an empty map when the value is not array-like
            return [];
        }

        // Resolve latitude from the Gps `lat` key or schema.org `latitude` key
        $latitude = $raw['lat'] ?? $raw['latitude'] ?? null;

        // Resolve longitude from the Gps `lng` key or schema.org `longitude` key
        $longitude = $raw['lng'] ?? $raw['longitude'] ?? null;

        // Return the derived scalar parameter defaults
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Resolved HTTPS schema.org `@type` URI for `GeoCoordinates` (memoized).
     *
     * @return string|null Same as `SchemaObjectFragmentBuilder::normalizedType()`
     */
    private function cachedNormalizedGeoCoordinatesType(): ?string
    {
        // Resolve and memoize the normalized type on first use
        if (self::$normalizedGeoCoordinatesType === null) {
            self::$normalizedGeoCoordinatesType = SchemaObjectFragmentBuilder::normalizedType(
                'GeoCoordinates'
            );
        }

        // Return the cached normalized type URI
        return self::$normalizedGeoCoordinatesType;
    }
}
