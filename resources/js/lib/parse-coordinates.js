/**
 * Parse a free-form coordinate string into decimal degrees (WGS84).
 *
 * Recognizes, in order, a geo: URI, degrees-minutes-seconds with hemisphere
 * letters (DMS), a UTM coordinate, and a plain decimal pair — and normalizes
 * each to `{ lat, lng }` in decimal degrees. Returns `null` when the input
 * matches none of the formats or resolves out of range, so callers can show a
 * single "couldn't parse" message.
 *
 * Supported examples:
 *   - Decimal — `38.897778, -77.036389`
 *   - DMS     — `38° 53′ 52″ N, 77° 2′ 11″ W`
 *   - Geo URI — `geo:38.897778,-77.036389`
 *   - UTM     — `18S 323404 4307404`
 *
 * @param  {string}  raw  The user-entered / pasted text.
 * @return {{ lat: number, lng: number } | null}
 */
export function parseCoordinates(raw) {
    if (raw == null) {
        return null;
    }

    const value = String(raw).trim();

    if (value === '') {
        return null;
    }

    return parseGeoUri(value) ?? parseUtm(value) ?? parseDms(value) ?? parseDecimal(value);
}

/**
 * Validate a lat/lng pair and return it, or `null` if either is out of range.
 *
 * @param  {number}  lat
 * @param  {number}  lng
 * @return {{ lat: number, lng: number } | null}
 */
function inRange(lat, lng) {
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        return null;
    }

    return { lat, lng };
}

/**
 * Parse `geo:lat,lng` (RFC 5870), ignoring any altitude or `;params` suffix.
 *
 * @param  {string}  value
 * @return {{ lat: number, lng: number } | null}
 */
function parseGeoUri(value) {
    const match = value.match(/^geo:\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/i);

    if (!match) {
        return null;
    }

    return inRange(parseFloat(match[1]), parseFloat(match[2]));
}

/**
 * Parse a plain decimal pair separated by a comma and/or whitespace.
 *
 * @param  {string}  value
 * @return {{ lat: number, lng: number } | null}
 */
function parseDecimal(value) {
    const match = value.match(/^([+-]?\d+(?:\.\d+)?)\s*[,\s]\s*([+-]?\d+(?:\.\d+)?)$/);

    if (!match) {
        return null;
    }

    return inRange(parseFloat(match[1]), parseFloat(match[2]));
}

/**
 * Parse two DMS components anchored by N/S/E/W hemisphere letters. The degree,
 * minute, and second symbols are all optional, and minutes/seconds may be
 * omitted (degrees-only or degrees-minutes). Requires exactly one latitude and
 * one longitude component.
 *
 * @param  {string}  value
 * @return {{ lat: number, lng: number } | null}
 */
function parseDms(value) {
    const component = /(\d+(?:\.\d+)?)\s*[°d]?\s*(?:(\d+(?:\.\d+)?)\s*['′m]?\s*)?(?:(\d+(?:\.\d+)?)\s*(?:["″]|''|s)?\s*)?([NSEW])/gi;
    const found = [];
    let match;

    while ((match = component.exec(value)) !== null) {
        const degrees = parseFloat(match[1]);
        const minutes = match[2] !== undefined ? parseFloat(match[2]) : 0;
        const seconds = match[3] !== undefined ? parseFloat(match[3]) : 0;
        const hemisphere = match[4].toUpperCase();

        let decimal = degrees + minutes / 60 + seconds / 3600;

        if (hemisphere === 'S' || hemisphere === 'W') {
            decimal = -decimal;
        }

        found.push({ decimal, axis: hemisphere === 'N' || hemisphere === 'S' ? 'lat' : 'lng' });
    }

    if (found.length !== 2) {
        return null;
    }

    const latitude = found.find((part) => part.axis === 'lat');
    const longitude = found.find((part) => part.axis === 'lng');

    if (!latitude || !longitude) {
        return null;
    }

    return inRange(latitude.decimal, longitude.decimal);
}

/**
 * Parse a UTM coordinate — `zone band easting northing` (e.g. `18S 323404
 * 4307404`). The band is an MGRS latitude-band letter (C-X, excluding I and O);
 * bands N-X are northern, C-M southern.
 *
 * @param  {string}  value
 * @return {{ lat: number, lng: number } | null}
 */
function parseUtm(value) {
    const match = value.match(/^(\d{1,2})\s*([C-HJ-NP-X])\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/i);

    if (!match) {
        return null;
    }

    const zone = parseInt(match[1], 10);

    if (zone < 1 || zone > 60) {
        return null;
    }

    const northern = match[2].toUpperCase() >= 'N';
    const { lat, lng } = utmToLatLng(zone, parseFloat(match[3]), parseFloat(match[4]), northern);

    return inRange(lat, lng);
}

/**
 * Convert a UTM coordinate to latitude / longitude on the WGS84 ellipsoid using
 * the standard inverse transverse-Mercator series (Snyder, USGS Professional
 * Paper 1395).
 *
 * @param  {number}   zone      UTM zone number (1-60).
 * @param  {number}   easting   Easting in meters (false-easting 500000 applied).
 * @param  {number}   northing  Northing in meters.
 * @param  {boolean}  northern  Whether the coordinate is in the northern hemisphere.
 * @return {{ lat: number, lng: number }}
 */
function utmToLatLng(zone, easting, northing, northern) {
    const a = 6378137.0;
    const f = 1 / 298.257223563;
    const k0 = 0.9996;
    const e2 = f * (2 - f);
    const ep2 = e2 / (1 - e2);

    const x = easting - 500000.0;
    const y = northern ? northing : northing - 10000000.0;

    const m = y / k0;
    const mu = m / (a * (1 - e2 / 4 - (3 * e2 * e2) / 64 - (5 * e2 ** 3) / 256));

    const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));
    const phi1 =
        mu +
        ((3 * e1) / 2 - (27 * e1 ** 3) / 32) * Math.sin(2 * mu) +
        ((21 * e1 ** 2) / 16 - (55 * e1 ** 4) / 32) * Math.sin(4 * mu) +
        ((151 * e1 ** 3) / 96) * Math.sin(6 * mu) +
        ((1097 * e1 ** 4) / 512) * Math.sin(8 * mu);

    const sinPhi1 = Math.sin(phi1);
    const cosPhi1 = Math.cos(phi1);
    const tanPhi1 = Math.tan(phi1);

    const c1 = ep2 * cosPhi1 ** 2;
    const t1 = tanPhi1 ** 2;
    const n1 = a / Math.sqrt(1 - e2 * sinPhi1 ** 2);
    const r1 = (a * (1 - e2)) / (1 - e2 * sinPhi1 ** 2) ** 1.5;
    const d = x / (n1 * k0);

    const latitude =
        phi1 -
        ((n1 * tanPhi1) / r1) *
            ((d * d) / 2 -
                ((5 + 3 * t1 + 10 * c1 - 4 * c1 ** 2 - 9 * ep2) * d ** 4) / 24 +
                ((61 + 90 * t1 + 298 * c1 + 45 * t1 ** 2 - 252 * ep2 - 3 * c1 ** 2) * d ** 6) / 720);

    const longitude =
        (d -
            ((1 + 2 * t1 + c1) * d ** 3) / 6 +
            ((5 - 2 * c1 + 28 * t1 - 3 * c1 ** 2 + 8 * ep2 + 24 * t1 ** 2) * d ** 5) / 120) /
        cosPhi1;

    const centralMeridian = (zone - 1) * 6 - 180 + 3;

    return {
        lat: (latitude * 180) / Math.PI,
        lng: centralMeridian + (longitude * 180) / Math.PI,
    };
}
