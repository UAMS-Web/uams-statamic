<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default map position
    |--------------------------------------------------------------------------
    |
    | The center and zoom the GPS / map picker opens at before a marker is
    | placed, used whenever a field sets no explicit `default_latitude` /
    | `default_longitude` / `default_zoom`. Override per environment with the
    | variables below; the built-in fallbacks center on central Arkansas.
    |
    */

    'default' => [

        'latitude' => (float) env('MAP_DEFAULT_LATITUDE', 34.74828329990028),

        'longitude' => (float) env('MAP_DEFAULT_LONGITUDE', -92.32145208472011),

        'zoom' => (int) env('MAP_DEFAULT_ZOOM', 14),

    ],

    /*
    |--------------------------------------------------------------------------
    | Map tiles
    |--------------------------------------------------------------------------
    |
    | Tile source for the GPS / map picker (and any future map rendering).
    |
    | The OpenStreetMap Tile Usage Policy
    | (https://operations.osmfoundation.org/policies/tiles/) requires that
    | attribution is always shown, asks that the tile URL is not hard-coded so
    | the provider can be switched without a code change, and prohibits bulk /
    | pre-emptive tile fetching. The public OpenStreetMap tile server used here
    | by default is appropriate for the low-volume, interactive Control Panel
    | picker. A high-traffic public-facing map should point these values at a
    | dedicated tile provider or a self-hosted tile server (see switch2osm.org)
    | by setting the environment variables below — no code change required.
    |
    | Note: the single-host URL (no `{s}` subdomains) is the form the policy now
    | requires; the legacy `a/b/c.tile.openstreetmap.org` subdomains are
    | deprecated.
    |
    */

    'tiles' => [

        'url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),

        'attribution' => env(
            'MAP_TILE_ATTRIBUTION',
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        ),

        'max_zoom' => (int) env('MAP_TILE_MAX_ZOOM', 19),

    ],

    /*
    |--------------------------------------------------------------------------
    | Aerial / satellite layer
    |--------------------------------------------------------------------------
    |
    | Optional imagery layer offered as a "Street / Aerial" toggle in the
    | picker. OpenStreetMap has no imagery, so this defaults to Esri World
    | Imagery, which needs no API key. Esri attribution is shown whenever the
    | aerial layer is active, as their terms require. Set the URL to an empty
    | string to disable the toggle and show only the street layer.
    |
    | Esri World Imagery uses ArcGIS REST tile order ({z}/{y}/{x}), not the XYZ
    | order ({z}/{x}/{y}) — keep the placeholders in that order when overriding.
    |
    | Zoom: the ArcGIS tiling scheme defines levels 0–23, but Esri only captures
    | imagery to a region-dependent resolution (~30 cm / level 19 across the US,
    | coarser elsewhere) and upsamples beyond that — so levels past `max_native`
    | add no detail. `max_native` is the deepest level with real tiles (Leaflet
    | stretches its own tiles past it instead of re-fetching identical upsampled
    | ones); `max_zoom` caps how far the picker lets you zoom on this layer.
    |
    | Terms: World Imagery is licensed under the Esri Master License Agreement
    | and is intended for use in ArcGIS apps; offline/tile export is prohibited.
    | Low-volume interactive Control Panel use is fine, but a high-traffic
    | public-facing map should use a properly licensed provider.
    |
    */

    'aerial' => [

        'url' => env(
            'MAP_AERIAL_TILE_URL',
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        ),

        'attribution' => env(
            'MAP_AERIAL_TILE_ATTRIBUTION',
            'Tiles &copy; <a href="https://www.esri.com/">Esri</a>, Vantor, Earthstar Geographics, and the GIS User Community',
        ),

        'max_zoom' => (int) env('MAP_AERIAL_TILE_MAX_ZOOM', 19),

        'max_native_zoom' => (int) env('MAP_AERIAL_TILE_MAX_NATIVE_ZOOM', 19),

    ],

    /*
    |--------------------------------------------------------------------------
    | Front-end map viewer
    |--------------------------------------------------------------------------
    |
    | Tile source for the public-facing map viewer (the `partials/map` Antlers
    | partial), kept separate from the Control Panel picker above so the public
    | map can point at a properly-licensed provider without changing the
    | low-volume CP picker.
    |
    | This is fully provider-agnostic and env-swappable. The default `xyz`
    | adapter renders any standard {z}/{x}/{y} raster tile service — change
    | `tile_url` to switch between OpenStreetMap, MapTiler, Mapbox,
    | Thunderforest, or a self-hosted tile server, embedding a keyed provider's
    | API key directly in the URL's query string (e.g. `...?key=...`,
    | `...?access_token=...`). Quadkey / JS-API providers (Bing, Google) are
    | reserved `provider` values whose tile-layer adapters slot into
    | `resources/js/map-viewer.js`.
    |
    | Defaults to OpenStreetMap so the viewer works out of the box. The OSM Tile
    | Usage Policy (https://operations.osmfoundation.org/policies/tiles/)
    | prohibits heavy / bulk use of `tile.openstreetmap.org`, so a high-traffic
    | public site must point `MAP_VIEWER_TILE_URL` at a licensed or self-hosted
    | provider. Leaving `tile_url` empty disables the interactive map, and the
    | partial degrades to its accessible, non-interactive fallback link.
    |
    | Note: `tile_subdomains` is only needed for providers whose URL uses the
    | `{s}` placeholder; the OSM single-host URL needs none.
    |
    */

    'viewer' => [

        'provider' => env('MAP_VIEWER_PROVIDER', 'xyz'),

        'tile_url' => env('MAP_VIEWER_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),

        'tile_attribution' => env(
            'MAP_VIEWER_TILE_ATTRIBUTION',
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        ),

        'tile_max_zoom' => (int) env('MAP_VIEWER_TILE_MAX_ZOOM', 19),

        'tile_subdomains' => env('MAP_VIEWER_TILE_SUBDOMAINS', ''),

        // Street-level fallback zoom for framing a single known location when the stored
        // coordinate omits its own zoom
        'default_zoom' => (int) env('MAP_VIEWER_DEFAULT_ZOOM', 16),

        // No-JS static-image fallback (the `partials/map` <noscript> slot, UAMS-Web/uams-statamic-archive#993). A URL
        // template with `{lat}` / `{lng}` / `{zoom}` placeholders that resolve to a flat
        // raster map image of the coordinate — substituted server-side by the
        // `static_map_url` tag (Antlers' `replace` cannot interpolate a variable). Empty by
        // default, so the fallback is off until a source is configured: no image is
        // rendered and no key leaks. Its concrete shape depends on the chosen provider
        // (UAMS-Web/uams-statamic-archive#994's self-hosted generator, or any keyed static-map service whose API key is
        // embedded directly in the template's query string).
        'static_url' => env('MAP_VIEWER_STATIC_URL', ''),

    ],

    /*
    |--------------------------------------------------------------------------
    | Nominatim address search (Control Panel)
    |--------------------------------------------------------------------------
    |
    | The GPS / map picker can offer an optional address-search box that
    | geocodes a typed address to a marker via OpenStreetMap's Nominatim
    | service. The search is proxied server-side (App\Http\Controllers\CP\Gps\
    | NominatimSearchController) rather than called from the editor's browser so
    | the request carries a descriptive, identifying `User-Agent` and is
    | centrally rate-limited, and so the outbound fetch passes through
    | App\Support\Http\OutboundUrlGuard (egress / SSRF hardening, UAMS-Web/uams-statamic-archive#931/UAMS-Web/uams-statamic-archive#1013).
    |
    | The Nominatim Usage Policy
    | (https://operations.osmfoundation.org/policies/nominatim/) requires a
    | valid identifying `User-Agent`, an absolute maximum of one request per
    | second, and result attribution. The defaults below honor that; a
    | high-volume deployment should point `MAP_NOMINATIM_ENDPOINT` at a
    | dedicated or self-hosted Nominatim instance.
    |
    */

    'nominatim' => [

        'endpoint' => env('MAP_NOMINATIM_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),

        // A descriptive identifier sent as the request User-Agent, per policy.
        // Falls back to the app name + URL when left empty (see the controller).
        'user_agent' => env('MAP_NOMINATIM_USER_AGENT', ''),

        // An optional contact email appended as the `email=` query parameter,
        // which the policy recommends so the operators can reach the site owner.
        'email' => env('MAP_NOMINATIM_EMAIL', ''),

        // Target ceiling on outbound requests per second (policy maximum is 1).
        // A best-effort app-wide throttle; see NominatimSearchController.
        'max_requests_per_second' => (int) env('MAP_NOMINATIM_MAX_RPS', 1),

        // Maximum geocoding results returned to the picker.
        'limit' => (int) env('MAP_NOMINATIM_LIMIT', 5),

        // Minimum query length before a search is issued (avoids noise requests).
        'min_query_length' => (int) env('MAP_NOMINATIM_MIN_QUERY_LENGTH', 3),

        // Optional ISO 3166-1 alpha-2 country bias, comma-separated (e.g. "us").
        'country_codes' => env('MAP_NOMINATIM_COUNTRY_CODES', 'us'),

        // Outbound request timeout, in seconds.
        'timeout' => (int) env('MAP_NOMINATIM_TIMEOUT', 5),

    ],

    /*
    |--------------------------------------------------------------------------
    | Census county reverse-geocoding (location regions)
    |--------------------------------------------------------------------------
    |
    | A location's Arkansas `regions` term is derived from its coordinate by
    | reverse-geocoding the point to its containing county via the free, keyless
    | US Census Geocoder, then mapping county -> region (the `regions` taxonomy
    | terms carry their constituent counties in `counties_covered`). See
    | App\Services\Locations\CensusCountyGeocoder and the assisted-model backfiller
    | App\Services\Locations\LocationRegionBackfiller, which runs off the save path
    | via the queued App\Jobs\BackfillLocationRegion job (UAMS-Web/uams-statamic-archive#962, UAMS-Web/uams-statamic-archive#1112).
    |
    | The lookup is server-side (so the request carries an identifying
    | `User-Agent`) and routed through App\Support\Http\OutboundUrlGuard (egress
    | hardening). Successful results are cached: county boundaries are static, so
    | repeated saves / a bulk import of the same coordinate never re-fetch.
    |
    | Census Geocoder reference:
    | https://geocoding.geo.census.gov/geocoder/Geocoding_Services_API.html
    |
    */

    'census' => [

        // Master switch for coordinate-derived regions. Disable to fall back to
        // purely manual region selection (e.g. during a very large import that
        // would prefer a separate, throttled backfill).
        'enabled' => (bool) env('MAP_CENSUS_GEOCODER_ENABLED', true),

        'endpoint' => env(
            'MAP_CENSUS_GEOCODER_ENDPOINT',
            'https://geocoding.geo.census.gov/geocoder/geographies/coordinates',
        ),

        // The Census Geocoder benchmark + vintage selecting "current" geographies.
        'benchmark' => env('MAP_CENSUS_GEOCODER_BENCHMARK', 'Public_AR_Current'),

        'vintage' => env('MAP_CENSUS_GEOCODER_VINTAGE', 'Current_Current'),

        // A descriptive identifier sent as the request User-Agent. Falls back to
        // the app name + URL when left empty (see the geocoder service).
        'user_agent' => env('MAP_CENSUS_GEOCODER_USER_AGENT', ''),

        // Outbound request timeout, in seconds.
        'timeout' => (int) env('MAP_CENSUS_GEOCODER_TIMEOUT', 8),

        // How long (seconds) to cache a resolved county for a coordinate. County
        // boundaries are effectively static, so this can be long; default 30 days.
        'cache_ttl' => (int) env('MAP_CENSUS_GEOCODER_CACHE_TTL', 2592000),

    ],

];
