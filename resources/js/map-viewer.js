// ---------------------------------------
// Front-end map viewer (progressive enhancement)
// ---------------------------------------
//
// Upgrades every `[data-map-viewer]` element rendered by the
// `partials/map.antlers.html` partial from its accessible, non-interactive
// fallback link into an interactive Leaflet map. This module (and Leaflet + its
// CSS) is dynamically imported by `site.js` only when a map element is present,
// so pages without a map pay no JS/CSS cost.
//
// Provider-agnostic: the tile URL, attribution, max zoom, and subdomains all
// come from the `viewer` section of `config/maps.php` via data attributes, so
// OpenStreetMap, MapTiler, Mapbox, Thunderforest, or a self-hosted tile server
// are a config change, not a code change.

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { parseMapViewerConfig } from './lib/map-viewer-config.js';

// Leaflet resolves its default marker images relative to its CSS, which breaks
// under Vite's hashed asset URLs; point them at the bundled imports instead.
// (Mirrors the Control Panel picker in GpsFieldtype.vue.)
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

/**
 * Build the Leaflet tile layer for a parsed viewer config.
 *
 * The `xyz` adapter covers any standard {z}/{x}/{y} raster service —
 * OpenStreetMap, MapTiler, Mapbox, Thunderforest, or a self-hosted server (a
 * keyed provider just embeds its key in the configured tile URL). Quadkey /
 * JS-API providers (Bing, Google) slot in here as additional cases.
 *
 * @param  {import('./lib/map-viewer-config.js').MapViewerConfig}  config
 * @return {L.TileLayer}  The configured tile layer.
 */
function createTileLayer(config) {
    const options = {
        attribution: config.attribution,
        maxZoom: config.maxZoom,
    };

    // Only providers whose URL uses the `{s}` placeholder need subdomains.
    if (config.subdomains) {
        options.subdomains = config.subdomains;
    }

    switch (config.provider) {
        case 'xyz':
        default:
            return L.tileLayer(config.tileUrl, options);
    }
}

/**
 * Upgrade a single `[data-map-viewer]` element into an interactive map.
 *
 * @param  {HTMLElement}  element  The map container element.
 * @return {void}
 */
function mountMapViewer(element) {
    const config = parseMapViewerConfig(element.dataset);

    // Nothing renderable (no coordinate or no provider) — leave the fallback.
    if (config === null) {
        return;
    }

    // Scroll-wheel zoom would hijack page scrolling on a content page; keep
    // dragging, the zoom buttons, and keyboard navigation.
    const map = L.map(element, {
        center: [config.lat, config.lng],
        zoom: config.zoom,
        scrollWheelZoom: false,
    });

    createTileLayer(config).addTo(map);

    L.marker([config.lat, config.lng], {
        title: config.label || undefined,
    }).addTo(map);

    // The element now hosts a live map; mark it so a re-init skips it.
    element.removeAttribute('data-map-viewer');
    element.dataset.mapViewerReady = 'true';
}

/**
 * Find and mount every map viewer within `root`.
 *
 * @param  {Document|HTMLElement}  [root]  The subtree to scan. Defaults to the
 *   whole document.
 * @return {void}
 */
export function initMapViewers(root = document) {
    root.querySelectorAll('[data-map-viewer]').forEach((element) => {
        try {
            mountMapViewer(element);
        } catch (error) {
            // Leave the non-interactive fallback link in place if Leaflet fails
            // to initialize this particular map.
            console.error('Map viewer failed to initialize.', error);
        }
    });
}
