// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';

import { createMobileNav, initMobileNav, setExpanded, wireSubmenuToggle } from './mobile-nav.js';

/**
 * Mirrors the markup of layout/navigation/main/mobile.antlers.html (UAMS-Web/uams-statamic-archive#53):
 * a hamburger toggler, a hidden panel, and one branch with a submenu toggle.
 */
function mountMobileNav() {
    document.body.innerHTML = `
        <button data-mobile-nav-toggler aria-expanded="false">Menu</button>
        <nav data-mobile-nav>
            <div class="site-nav-mobile-panel" id="mobile-nav-primary" data-mobile-nav-panel hidden>
                <ul>
                    <li><a href="/about" id="first-link">About</a></li>
                    <li class="has-children">
                        <a href="/research">Research</a>
                        <button
                            data-mobile-nav-submenu-toggle
                            aria-expanded="false"
                            aria-controls="mobile-submenu-1"
                        >Toggle</button>
                        <ul id="mobile-submenu-1" hidden><li><a href="/research/labs">Labs</a></li></ul>
                    </li>
                </ul>
            </div>
        </nav>`;

    return {
        toggler: document.querySelector('[data-mobile-nav-toggler]'),
        root: document.querySelector('[data-mobile-nav]'),
        panel: document.querySelector('[data-mobile-nav-panel]'),
        submenuToggle: document.querySelector('[data-mobile-nav-submenu-toggle]'),
        submenu: document.getElementById('mobile-submenu-1'),
        firstLink: document.getElementById('first-link'),
    };
}

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('setExpanded', () => {
    it('reveals the controlled element and marks the trigger expanded', () => {
        const trigger = document.createElement('button');
        const controlled = document.createElement('div');
        controlled.setAttribute('hidden', '');

        setExpanded(trigger, controlled, true);

        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(controlled.hasAttribute('hidden')).toBe(false);
        expect(controlled.classList.contains('is-open')).toBe(true);
    });

    it('hides the controlled element and marks the trigger collapsed', () => {
        const trigger = document.createElement('button');
        const controlled = document.createElement('div');
        controlled.classList.add('is-open');

        setExpanded(trigger, controlled, false);

        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(controlled.hasAttribute('hidden')).toBe(true);
        expect(controlled.classList.contains('is-open')).toBe(false);
    });
});

describe('createMobileNav', () => {
    it('returns null when there is no mobile-nav region', () => {
        expect(createMobileNav(null)).toBeNull();
    });

    it('opens the panel and moves focus into it when the hamburger is clicked', () => {
        const { toggler, root, panel, firstLink } = mountMobileNav();
        createMobileNav(root, { toggler, doc: document });

        toggler.click();

        expect(toggler.getAttribute('aria-expanded')).toBe('true');
        expect(panel.hasAttribute('hidden')).toBe(false);
        expect(document.activeElement).toBe(firstLink);
    });

    it('closes the panel on a second hamburger click', () => {
        const { toggler, root, panel } = mountMobileNav();
        createMobileNav(root, { toggler, doc: document });

        toggler.click();
        toggler.click();

        expect(toggler.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hasAttribute('hidden')).toBe(true);
    });

    it('closes on Escape and returns focus to the hamburger', () => {
        const { toggler, root, panel } = mountMobileNav();
        createMobileNav(root, { toggler, doc: document });

        toggler.click();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(toggler.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(document.activeElement).toBe(toggler);
    });

    it('ignores Escape when the panel is already closed', () => {
        const { toggler, root } = mountMobileNav();
        const other = document.createElement('button');
        document.body.appendChild(other);
        other.focus();
        createMobileNav(root, { toggler, doc: document });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        // Focus is not yanked back to the toggler when nothing was open.
        expect(document.activeElement).toBe(other);
    });

    it('expands and collapses a nested submenu', () => {
        const { toggler, root, submenuToggle, submenu } = mountMobileNav();
        createMobileNav(root, { toggler, doc: document });

        submenuToggle.click();
        expect(submenuToggle.getAttribute('aria-expanded')).toBe('true');
        expect(submenu.hasAttribute('hidden')).toBe(false);

        submenuToggle.click();
        expect(submenuToggle.getAttribute('aria-expanded')).toBe('false');
        expect(submenu.hasAttribute('hidden')).toBe(true);
    });
});

describe('wireSubmenuToggle', () => {
    it('does nothing when the controlled submenu is missing', () => {
        mountMobileNav();
        const button = document.createElement('button');
        button.setAttribute('aria-controls', 'does-not-exist');
        button.setAttribute('aria-expanded', 'false');

        wireSubmenuToggle(button, document);
        button.click();

        // No throw, and the trigger state is untouched.
        expect(button.getAttribute('aria-expanded')).toBe('false');
    });
});

describe('initMobileNav', () => {
    it('discovers and wires the region from the document', () => {
        const { toggler, panel } = mountMobileNav();

        initMobileNav(document);
        toggler.click();

        expect(panel.hasAttribute('hidden')).toBe(false);
    });
});
