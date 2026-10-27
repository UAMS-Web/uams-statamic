import { describe, expect, it } from 'vitest';
import { parseMapViewerConfig } from './map-viewer-config.js';

// A fully populated dataset, matching what `partials/map.antlers.html` emits.
const FULL = {
    lat: '34.7492719',
    lng: '-92.3198281',
    zoom: '16',
    provider: 'xyz',
    tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution: '© OpenStreetMap contributors',
    maxZoom: '19',
    subdomains: '',
    label: 'UAMS Medical Center',
};

describe('parseMapViewerConfig', () => {
    it('parses a populated dataset into typed values', () => {
        expect(parseMapViewerConfig(FULL)).toEqual({
            lat: 34.7492719,
            lng: -92.3198281,
            zoom: 16,
            provider: 'xyz',
            tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19,
            subdomains: undefined,
            label: 'UAMS Medical Center',
        });
    });

    it.each([
        ['missing lat', { ...FULL, lat: '' }],
        ['missing lng', { ...FULL, lng: undefined }],
        ['non-numeric lat', { ...FULL, lat: 'abc' }],
    ])('returns null when the coordinate is unusable (%s)', (_label, dataset) => {
        expect(parseMapViewerConfig(dataset)).toBeNull();
    });

    it.each([
        ['empty tile URL', { ...FULL, tileUrl: '' }],
        ['whitespace tile URL', { ...FULL, tileUrl: '   ' }],
        ['absent tile URL', { ...FULL, tileUrl: undefined }],
    ])('returns null when no provider tile URL is configured (%s)', (_label, dataset) => {
        expect(parseMapViewerConfig(dataset)).toBeNull();
    });

    it('falls back to a default zoom when zoom is absent or non-numeric', () => {
        expect(parseMapViewerConfig({ ...FULL, zoom: '' }).zoom).toBe(16);
        expect(parseMapViewerConfig({ ...FULL, zoom: 'soon' }).zoom).toBe(16);
    });

    it('falls back to a default max zoom and the xyz provider', () => {
        const config = parseMapViewerConfig({ ...FULL, maxZoom: '', provider: '' });

        expect(config.maxZoom).toBe(19);
        expect(config.provider).toBe('xyz');
    });

    it('passes through configured subdomains', () => {
        expect(parseMapViewerConfig({ ...FULL, subdomains: 'abc' }).subdomains).toBe('abc');
    });
});
