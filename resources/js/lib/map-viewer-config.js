/**
 * Normalize a `[data-map-viewer]` element's `data-*` attributes into a typed
 * map-viewer configuration.
 *
 * Framework-free (no DOM or Leaflet) so it can be unit tested in isolation and
 * reused by the `map-viewer` progressive-enhancement entry. The values arrive
 * as strings from the dataset; this parses and validates them, returning `null`
 * when there is nothing renderable (a missing/non-finite coordinate or an
 * unconfigured tile provider) so the caller leaves the non-interactive fallback
 * link in place.
 */

/**
 * @typedef {Object} MapViewerConfig
 * @property {number}            lat          Marker latitude in decimal degrees.
 * @property {number}            lng          Marker longitude in decimal degrees.
 * @property {number}            zoom         Initial zoom level.
 * @property {string}            provider     Tile-layer adapter key (e.g. `xyz`).
 * @property {string}            tileUrl      Tile URL template ({z}/{x}/{y}).
 * @property {string}            attribution  Required tile attribution markup.
 * @property {number}            maxZoom      Deepest zoom the layer allows.
 * @property {string|undefined}  subdomains   `{s}` subdomains, or undefined.
 * @property {string}            label        Accessible name for the marker.
 */

/**
 * Parse a dataset (or any plain object of the same `data-*` keys) into a
 * normalized map-viewer config.
 *
 * @param  {DOMStringMap|Object}  dataset  The element's `dataset`.
 * @return {MapViewerConfig|null}  The config, or null when nothing is renderable.
 */
export function parseMapViewerConfig(dataset) {
    const lat = Number.parseFloat(dataset.lat);
    const lng = Number.parseFloat(dataset.lng);

    // Without a finite coordinate there is nothing to place; the caller keeps
    // the non-interactive fallback link.
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    const tileUrl = (dataset.tileUrl || '').trim();

    // No configured provider tile URL means the interactive map is disabled.
    if (tileUrl === '') {
        return null;
    }

    const zoom = Number.parseInt(dataset.zoom, 10);
    const maxZoom = Number.parseInt(dataset.maxZoom, 10);
    const subdomains = (dataset.subdomains || '').trim();

    return {
        lat,
        lng,
        zoom: Number.isFinite(zoom) ? zoom : 16,
        provider: (dataset.provider || '').trim() || 'xyz',
        tileUrl,
        attribution: dataset.attribution || '',
        maxZoom: Number.isFinite(maxZoom) ? maxZoom : 19,
        subdomains: subdomains === '' ? undefined : subdomains,
        label: dataset.label || '',
    };
}
