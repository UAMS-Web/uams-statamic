import { describe, expect, it } from 'vitest';
import { parseCoordinates } from './parse-coordinates.js';

// All accepted-format fixtures describe the same point (the White House), so a
// successful parse of any of them lands on TARGET in decimal degrees.
const TARGET = { lat: 38.897778, lng: -77.036389 };

describe('parseCoordinates', () => {
    it.each([
        ['DMS', '38° 53′ 52″ N, 77° 2′ 11″ W'],
        ['DMS without separators', '38°53\'52"N 77°2\'11"W'],
        ['decimal, comma-separated', '38.897778, -77.036389'],
        ['decimal, space-separated', '38.897778 -77.036389'],
        ['geo: URI', 'geo:38.897778,-77.036389'],
        ['geo: URI with altitude and params', 'geo:38.897778,-77.036389,0;u=35'],
        ['UTM', '18S 323404 4307404'],
        ['UTM, lowercase band and spacing', '18 s 323404 4307404'],
    ])('parses %s to decimal degrees', (_label, input) => {
        const result = parseCoordinates(input);

        expect(result).not.toBeNull();
        expect(result.lat).toBeCloseTo(TARGET.lat, 4);
        expect(result.lng).toBeCloseTo(TARGET.lng, 4);
    });

    it('preserves the full decimal precision of a decimal pair', () => {
        expect(parseCoordinates('34.7492719, -92.3198281')).toEqual({
            lat: 34.7492719,
            lng: -92.3198281,
        });
    });

    it('negates the southern and western hemispheres in DMS', () => {
        const result = parseCoordinates('33° 51′ 54″ S, 151° 12′ 36″ E');

        expect(result.lat).toBeCloseTo(-33.865, 3);
        expect(result.lng).toBeCloseTo(151.21, 3);
    });

    it.each([
        ['an empty string', ''],
        ['whitespace only', '   '],
        ['unrecognized text', 'hello'],
        ['a latitude out of range', '200, 0'],
        ['a longitude out of range', '0, 999'],
        ['a bare geo: prefix', 'geo:'],
        ['an incomplete UTM token', '12X'],
        ['null', null],
    ])('returns null for %s', (_label, input) => {
        expect(parseCoordinates(input)).toBeNull();
    });
});
