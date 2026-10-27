import { describe, expect, it } from 'vitest';
import { COORDINATE_PRECISION, resultToStoredCoordinate, roundCoordinate } from './nominatim.js';

describe('roundCoordinate', () => {
	it('rounds to the stored coordinate precision', () => {
		expect(roundCoordinate(34.749271912345)).toBe(34.7492719);
		expect(roundCoordinate(-92.319828154321)).toBe(-92.3198282);
	});

	it('keeps COORDINATE_PRECISION in lockstep with the PHP fieldtype (7 places)', () => {
		expect(COORDINATE_PRECISION).toBe(7);
	});
});

describe('resultToStoredCoordinate', () => {
	it('maps a geocoding result and zoom into the stored { lat, lng, zoom } shape', () => {
		const result = { lat: 34.7492719, lng: -92.3198281, label: '4301 W Markham St, Little Rock' };

		expect(resultToStoredCoordinate(result, 16)).toEqual({
			lat: 34.7492719,
			lng: -92.3198281,
			zoom: 16,
		});
	});

	it('accepts numeric strings (as the proxy may serialize them) and rounds them', () => {
		expect(resultToStoredCoordinate({ lat: '34.749271912', lng: '-92.319828154' }, '16')).toEqual({
			lat: 34.7492719,
			lng: -92.3198282,
			zoom: 16,
		});
	});

	it('omits zoom when it is not a finite number', () => {
		const coordinate = resultToStoredCoordinate({ lat: 34.75, lng: -92.32 }, null);

		expect(coordinate).toEqual({ lat: 34.75, lng: -92.32 });
		expect(coordinate).not.toHaveProperty('zoom');
	});

	it.each([
		['null result', null],
		['undefined result', undefined],
		['missing longitude', { lat: 34.75 }],
		['non-numeric coordinate', { lat: 'abc', lng: 'def' }],
	])('returns null for %s', (_label, input) => {
		expect(resultToStoredCoordinate(input, 16)).toBeNull();
	});
});
