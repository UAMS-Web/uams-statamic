// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';
import { resolveAgreementHtml, sanitizeAgreementHtml } from './consent-agreement.js';

describe('sanitizeAgreementHtml', () => {
    it('strips <script> from the agreement HTML', () => {
        const out = sanitizeAgreementHtml('<p>I agree to the terms.</p><script>steal(document.cookie)</script>');

        expect(out).not.toMatch(/<script/i);
        expect(out).not.toContain('steal(document.cookie)');
        expect(out).toContain('<p>I agree to the terms.</p>');
    });

    it('strips inline event handlers', () => {
        const out = sanitizeAgreementHtml('<img src="x" onerror="alert(1)">');

        expect(out).not.toMatch(/onerror/i);
        expect(out).not.toContain('alert(1)');
    });

    it('strips javascript: URLs from links', () => {
        const out = sanitizeAgreementHtml('<a href="javascript:alert(1)">terms</a>');

        expect(out).not.toMatch(/javascript:/i);
        expect(out).toContain('terms');
    });

    it('strips data: URLs from link hrefs', () => {
        const out = sanitizeAgreementHtml('<a href="data:text/html,<script>alert(1)</script>">terms</a>');

        expect(out).not.toMatch(/data:/i);
        expect(out).toContain('terms');
    });

    it('preserves markdown-rendered formatting and link target', () => {
        const out = sanitizeAgreementHtml(
            '<p>See the <a href="https://uams.edu/privacy" target="_blank" rel="noopener">policy</a>.</p><ul><li>one</li></ul>',
        );

        expect(out).toContain('href="https://uams.edu/privacy"');
        expect(out).toContain('target="_blank"');
        expect(out).toContain('<ul><li>one</li></ul>');
    });

    it('returns an empty string for non-string or empty input', () => {
        expect(sanitizeAgreementHtml('')).toBe('');
        expect(sanitizeAgreementHtml(null)).toBe('');
        expect(sanitizeAgreementHtml(undefined)).toBe('');
        expect(sanitizeAgreementHtml(42)).toBe('');
    });
});

describe('resolveAgreementHtml (the exact value bound to v-html)', () => {
    it('prefers the server-rendered meta HTML over the config fallback', () => {
        const out = resolveAgreementHtml(
            { consent_agreement_html: '<p>rendered</p>' },
            { consent_agreement: 'raw markdown' },
        );

        expect(out).toBe('<p>rendered</p>');
    });

    it('falls back to the config markdown when meta is absent', () => {
        expect(resolveAgreementHtml({}, { consent_agreement: 'plain terms' })).toBe('plain terms');
        expect(resolveAgreementHtml(null, null)).toBe('');
    });

    it('sanitizes a payload arriving through the meta path', () => {
        const out = resolveAgreementHtml(
            { consent_agreement_html: '<p>ok</p><script>alert(1)</script>' },
            {},
        );

        expect(out).not.toMatch(/<script/i);
        expect(out).toContain('<p>ok</p>');
    });

    it('sanitizes a payload arriving through the config fallback', () => {
        const out = resolveAgreementHtml({}, { consent_agreement: '<img src=x onerror=alert(1)>' });

        expect(out).not.toMatch(/onerror/i);
    });
});
