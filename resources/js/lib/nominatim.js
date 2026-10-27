// Helpers for the GPS fieldtype's OpenStreetMap (Nominatim) address search.
//
// The address search is proxied server-side (App\Http\Controllers\CP\Gps\
// NominatimSearchController), which returns already-normalized results in the
// shape { lat, lng, label }. These helpers turn a chosen result into the
// fieldtype's stored { lat, lng, zoom } coordinate, and are the unit-tested
// "geocode-result -> coordinate update path" the picker uses on selection.

// Decimal places kept for latitude / longitude (mirrors the PHP fieldtype's
// App\Fieldtypes\Gps::COORDINATE_PRECISION and the component's display).
export const COORDINATE_PRECISION = 7;

/**
 * Round a coordinate component to the stored precision.
 *
 * @param {number} value
 * @returns {number}
 */
export function roundCoordinate(value) {
	const factor = 10 ** COORDINATE_PRECISION;

	return Math.round(value * factor) / factor;
}

/**
 * Map a geocoding result and a framing zoom into the fieldtype's stored
 * { lat, lng, zoom } shape, or null when the result has no usable numeric
 * coordinate.
 *
 * @param {{lat: *, lng: *}|null|undefined} result
 * @param {*} zoom  The current map zoom to frame the pin at (optional)
 * @returns {{lat: number, lng: number, zoom?: number}|null}
 */
export function resultToStoredCoordinate(result, zoom) {
	const lat = Number(result?.lat);
	const lng = Number(result?.lng);

	if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
		return null;
	}

	const coordinate = {
		lat: roundCoordinate(lat),
		lng: roundCoordinate(lng),
	};

	// Only frame a zoom when one was actually supplied: Number(null) is 0 (a
	// finite value), so the null/undefined guard keeps an absent zoom absent.
	const numericZoom = Number(zoom);

	if (zoom != null && Number.isFinite(numericZoom)) {
		coordinate.zoom = Math.round(numericZoom);
	}

	return coordinate;
}
