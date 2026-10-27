import { describe, expect, it } from 'vitest';

import { DEFAULT_SITE_STATUS, resolveSiteStatus } from './uamsalert-site.js';

describe('resolveSiteStatus', () => {
    it('reads the bucket from the body data-uams-alert-site attribute', () => {
        expect(resolveSiteStatus({ dataset: { uamsAlertSite: 'uamshealth' } })).toBe('uamshealth');
    });

    it('falls back to uams when the attribute is empty, whitespace, or absent', () => {
        expect(resolveSiteStatus({ dataset: { uamsAlertSite: '' } })).toBe(DEFAULT_SITE_STATUS);
        expect(resolveSiteStatus({ dataset: { uamsAlertSite: '   ' } })).toBe(DEFAULT_SITE_STATUS);
        expect(resolveSiteStatus({ dataset: {} })).toBe(DEFAULT_SITE_STATUS);
    });

    it('trims surrounding whitespace from a valid bucket', () => {
        expect(resolveSiteStatus({ dataset: { uamsAlertSite: '  uamshealth  ' } })).toBe('uamshealth');
    });

    it('falls back to uams when there is no body or dataset', () => {
        expect(resolveSiteStatus(null)).toBe(DEFAULT_SITE_STATUS);
        expect(resolveSiteStatus({})).toBe(DEFAULT_SITE_STATUS);
    });

    it('defaults to the uams bucket', () => {
        expect(DEFAULT_SITE_STATUS).toBe('uams');
    });
});
