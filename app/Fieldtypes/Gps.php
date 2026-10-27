<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Rules\Latitude;
use App\Rules\Longitude;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * GPS / Map coordinate fieldtype.
 *
 * Replaces the legacy `uamsweb_common_address.map` text field — a free-text
 * "lat, lng" string validated by the comma-separated `lat_lng` rule — with a
 * dedicated, structured coordinate field backed by an interactive Leaflet +
 * OpenStreetMap picker in the Control Panel. The editor clicks or drags a
 * marker (or types the coordinates); the field stores a structured array of the
 * decimal latitude, longitude, and the zoom level the editor framed the
 * location at.
 *
 * Stored shape:
 *
 *   map:
 *     lat: 34.7492719
 *     lng: -92.3198281
 *     zoom: 16
 *
 * Latitude and longitude ranges are validated by reusing the existing
 * App\Rules\Latitude and App\Rules\Longitude rules, applied to the lat/lng
 * members via extraRules(); `required_with` pairing prevents persisting a
 * half-coordinate.
 */
class Gps extends Fieldtype
{
    /**
     * Decimal places stored for latitude and longitude.
     *
     * Seven places is roughly 11 mm of precision — well beyond the ~1 m the
     * legacy schema.org GeoCoordinates output needed, and fine enough that
     * rounding never visibly shifts the marker.
     */
    private const int COORDINATE_PRECISION = 7;

    /**
     * Indicates whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in collection blueprints
     */
    protected $selectable = true;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * A map picker is not meaningful on a public form, so it is hidden there.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = false;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'gps';

    /**
     * Categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector
     */
    protected $categories = ['special'];

    /**
     * Search keywords used to find this fieldtype in the Control Panel.
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'coordinates',
        'geo',
        'gps',
        'latitude',
        'location',
        'longitude',
        'map',
    ];

    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel
     */
    protected static $title = 'statamic::fieldtypes.gps.title';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'map-search';
    }

    /**
     * Define the blueprint configuration fields for this fieldtype.
     *
     * Lets each field set the picker's starting center and zoom so a blueprint
     * can frame a sensible default region for its editors.
     *
     * @return array<int, array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('statamic::fieldtypes.gps.config.section_heading'),
                'fields' => [
                    'default_latitude' => [
                        'display' => __('statamic::fieldtypes.gps.config.default_latitude_display'),
                        'type' => 'text',
                        'instructions' => __('statamic::fieldtypes.gps.config.default_latitude_instructions'),
                    ],
                    'default_longitude' => [
                        'display' => __('statamic::fieldtypes.gps.config.default_longitude_display'),
                        'type' => 'text',
                        'instructions' => __('statamic::fieldtypes.gps.config.default_longitude_instructions'),
                    ],
                    'default_zoom' => [
                        'display' => __('statamic::fieldtypes.gps.config.default_zoom_display'),
                        'type' => 'integer',
                        'instructions' => __('statamic::fieldtypes.gps.config.default_zoom_instructions'),
                    ],
                    'enable_address_search' => [
                        'display' => __('statamic::fieldtypes.gps.config.enable_address_search_display'),
                        'type' => 'toggle',
                        'instructions' => __('statamic::fieldtypes.gps.config.enable_address_search_instructions'),
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Surface the resolved map defaults and tile source to the Vue component
     * as `meta`.
     *
     * The starting center and zoom fall back to `config/maps.php`
     * (`maps.default.*`, env-backed) when a field sets no explicit default; the
     * tile URL, attribution, and max zoom likewise come from config (not
     * hard-coded) so the provider can be switched without a code change, per
     * the OpenStreetMap Tile Usage Policy.
     *
     * @return array<string, bool|float|int|string> Preload data exposed to the Vue component as `meta`
     */
    #[\Override]
    public function preload(): array
    {
        // Narrow each mixed config()/config() read to the scalar the meta shape
        // declares before exposing it; PHP would coerce these implicitly, the
        // casts/scalar conversions only make that explicit for the type checker.
        $tileUrl = config('maps.tiles.url');
        $tileAttribution = config('maps.tiles.attribution');
        $tileMaxZoom = config('maps.tiles.max_zoom');
        $aerialUrl = config('maps.aerial.url');
        $aerialAttribution = config('maps.aerial.attribution');
        $aerialMaxZoom = config('maps.aerial.max_zoom');
        $aerialMaxNativeZoom = config('maps.aerial.max_native_zoom');

        // Narrow the resolved config()/$this->config() defaults to scalars before
        // casting; PHP would coerce these implicitly, the scalar guards only make
        // that explicit for the type checker.
        $defaultLatitude = $this->config('default_latitude', config('maps.default.latitude'));
        $defaultLongitude = $this->config('default_longitude', config('maps.default.longitude'));
        $defaultZoom = $this->config('default_zoom', config('maps.default.zoom'));

        // The address-search box is opt-in per field; its minimum query length
        // comes from config so the component matches the server-side guard.
        $addressSearchMinLength = config('maps.nominatim.min_query_length');

        // Return the meta shape, each value cast to the type the component expects
        return [
            'defaultLatitude' => \is_numeric($defaultLatitude) ? (float) $defaultLatitude : 0.0,
            'defaultLongitude' => \is_numeric($defaultLongitude) ? (float) $defaultLongitude : 0.0,
            'defaultZoom' => \is_numeric($defaultZoom) ? (int) $defaultZoom : 0,
            'tileUrl' => \is_scalar($tileUrl) ? (string) $tileUrl : '',
            'tileAttribution' => \is_scalar($tileAttribution) ? (string) $tileAttribution : '',
            'tileMaxZoom' => \is_numeric($tileMaxZoom) ? (int) $tileMaxZoom : 0,
            'aerialUrl' => \is_scalar($aerialUrl) ? (string) $aerialUrl : '',
            'aerialAttribution' => \is_scalar($aerialAttribution) ? (string) $aerialAttribution : '',
            'aerialMaxZoom' => \is_numeric($aerialMaxZoom) ? (int) $aerialMaxZoom : 0,
            'aerialMaxNativeZoom' => \is_numeric($aerialMaxNativeZoom) ? (int) $aerialMaxNativeZoom : 0,
            'addressSearchEnabled' => (bool) $this->config('enable_address_search', false),
            'addressSearchMinLength' => \is_numeric($addressSearchMinLength) ? max(1, (int) $addressSearchMinLength) : 3,
        ];
    }

    /**
     * Normalize the stored value into the shape the Vue component expects.
     *
     * Accepts the structured array shape and, defensively, the legacy
     * comma-separated "lat, lng" string so a field flipped to this type keeps
     * rendering. Returns null when there is no usable coordinate.
     *
     * @param  mixed  $data  The stored value
     * @return array<string, float|int>|null The normalized coordinate, or null when empty
     */
    #[\Override]
    public function preProcess($data): mixed
    {
        return $this->normalizeCoordinate($data);
    }

    /**
     * Normalize the submitted value into the stored shape.
     *
     * Returns null when no marker was placed so empty coordinates are not
     * persisted as a partial array.
     *
     * @param  mixed  $data  The submitted value from the publish form
     * @return array<string, float|int>|null The normalized coordinate, or null when empty
     */
    #[\Override]
    public function process($data): mixed
    {
        return $this->normalizeCoordinate($data);
    }

    /**
     * Augment the stored value for template and API consumption.
     *
     * @param  mixed  $value  The stored value
     * @return array<string, float|int>|null The normalized coordinate, or null when empty
     */
    #[\Override]
    public function augment($value): mixed
    {
        return $this->normalizeCoordinate($value);
    }

    /**
     * Validate the coordinate by reusing the existing range rules.
     *
     * Keyed by the field's dotted handle so the rules target the lat/lng
     * members of the stored array. The rules skip empty values, so an
     * optional, unset field passes; `required_with` pairing rejects a
     * half-coordinate (one member without the other).
     *
     * @return array<string, array<int, mixed>> Extra validation rules keyed by dotted field path
     */
    #[\Override]
    public function extraRules(): array
    {
        /** @var Field $field */
        $field = $this->field;
        /** @var string $handle */
        $handle = $field->handle();

        return [
            $handle.'.lat' => ['nullable', sprintf('required_with:%s.lng', $handle), new Latitude],
            $handle.'.lng' => ['nullable', sprintf('required_with:%s.lat', $handle), new Longitude],
        ];
    }

    /**
     * Coerce any supported input into the canonical { lat, lng, zoom } shape,
     * or null when latitude/longitude cannot be resolved.
     *
     * @param  mixed  $data  The raw value (array, legacy string, or empty)
     * @return array<string, float|int>|null The normalized coordinate, or null when empty
     */
    private function normalizeCoordinate($data): ?array
    {
        // Defensively parse the legacy "lat, lng" string shape.
        if (\is_string($data)) {
            $data = $this->parseLegacyString($data);
        }

        // Anything that is not an associative coordinate array is "no value".
        // Keep the happy path inside the array branch so the null return is a
        // single exit (RemoveEarlyReturn on a mid-method `return null` stayed
        // untested under PCOV even when process(non-array) covered the branch).
        if (\is_array($data)) {
            $lat = $data['lat'] ?? null;
            $lng = $data['lng'] ?? null;

            // A coordinate requires both numeric members.
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return null;
            }

            // Round to the stored precision so the value is stable and compact.
            $coordinate = [
                'lat' => round((float) $lat, self::COORDINATE_PRECISION),
                'lng' => round((float) $lng, self::COORDINATE_PRECISION),
            ];

            // Persist the framed zoom only when present and valid.
            if (isset($data['zoom']) && is_numeric($data['zoom'])) {
                $coordinate['zoom'] = (int) $data['zoom'];
            }

            return $coordinate;
        }

        return null;
    }

    /**
     * Parse a legacy comma-separated "lat, lng" string into a coordinate array.
     *
     * @param  string  $value  The legacy comma-separated coordinate string
     * @return array<string, string>|null The parsed lat/lng pair, or null when malformed
     */
    private function parseLegacyString(string $value): ?array
    {
        // Split on a comma with optional surrounding whitespace.
        // UnwrapTrim stays untested under PCOV (the trim path runs; the call
        // node is not attributed).
        // @pest-mutate-ignore: UnwrapTrim
        $parts = preg_split('/\s*,\s*/', trim($value));

        // A valid legacy value is exactly two parts (latitude, longitude).
        if (! \is_array($parts) || \count($parts) !== 2) {
            return null;
        }

        return ['lat' => $parts[0], 'lng' => $parts[1]];
    }
}
