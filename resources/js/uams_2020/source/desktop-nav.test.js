// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';

import { closeItem, createDesktopNav, enhanceItem, openItem } from './desktop-nav.js';

/**
 * Mirrors layout/navigation/main/desktop.antlers.html (#menu-dropdowns): a flat
 * item plus one dropdown item with two submenu links. Overflow (the "More"
 * collapse) needs real layout and is covered by the browser smoke test; jsdom
 * exercises the enhancement, open/close, and keyboard behavior.
 */
function mountNav() {
    document.body.innerHTML = `
        <ul id="menu-dropdowns" class="nav navbar-nav">
            <li class="nav-item"><a class="nav-link" href="/a">A</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link" href="/b">B</a>
                <ul role="menu" class="dropdown-menu">
                    <li class="nav-item"><a class="nav-link" href="/b1">B1</a></li>
                    <li class="nav-item"><a class="nav-link" href="/b2">B2</a></li>
                </ul>
            </li>
        </ul>
        <a href="/outside" id="outside">Outside</a>`;

    const dropdown = document.querySelector('.nav-item.dropdown');

    return {
        root: document.getElementById('menu-dropdowns'),
        dropdown,
        parentLink: dropdown.querySelector(':scope > a.nav-link'),
        submenu: dropdown.querySelector(':scope > ul.dropdown-menu'),
        subLinks: dropdown.querySelectorAll('.dropdown-menu .nav-link'),
    };
}

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('enhanceItem', () => {
    it('adds the caret, .has-submenu, and ARIA to a dropdown item', () => {
        const { dropdown, parentLink } = mountNav();

        enhanceItem(dropdown);

        expect(parentLink.classList.contains('has-submenu')).toBe(true);
        expect(parentLink.getAttribute('aria-haspopup')).toBe('true');
        expect(parentLink.getAttribute('aria-expanded')).toBe('false');
        expect(parentLink.querySelector('.sub-arrow')).not.toBeNull();
    });

    it('is idempotent (no duplicate carets)', () => {
        const { dropdown, parentLink } = mountNav();

        enhanceItem(dropdown);
        enhanceItem(dropdown);

        expect(parentLink.querySelectorAll('.sub-arrow')).toHaveLength(1);
    });
});

describe('openItem / closeItem', () => {
    it('toggles .show and aria-expanded', () => {
        const { dropdown, parentLink, submenu } = mountNav();

        openItem(dropdown);
        expect(submenu.classList.contains('show')).toBe(true);
        expect(parentLink.getAttribute('aria-expanded')).toBe('true');

        closeItem(dropdown);
        expect(submenu.classList.contains('show')).toBe(false);
        expect(parentLink.getAttribute('aria-expanded')).toBe('false');
    });
});

describe('createDesktopNav', () => {
    it('opens on hover and closes on mouse leave (pointer devices)', () => {
        const { root, dropdown, submenu } = mountNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });

        dropdown.dispatchEvent(new MouseEvent('mouseenter'));
        expect(submenu.classList.contains('show')).toBe(true);

        dropdown.dispatchEvent(new MouseEvent('mouseleave'));
        expect(submenu.classList.contains('show')).toBe(false);
    });

    it('opens on tap then follows the link on the second tap (touch devices)', () => {
        const { root, parentLink, submenu } = mountNav();
        createDesktopNav(root, { doc: document, hoverCapable: false });

        const first = new MouseEvent('click', { bubbles: true, cancelable: true });
        parentLink.dispatchEvent(first);
        expect(submenu.classList.contains('show')).toBe(true);
        expect(first.defaultPrevented).toBe(true);

        // Second tap: submenu already open, navigation is allowed through.
        const second = new MouseEvent('click', { bubbles: true, cancelable: true });
        parentLink.dispatchEvent(second);
        expect(second.defaultPrevented).toBe(false);
    });

    it('opens and focuses the first submenu link on ArrowDown', () => {
        const { root, parentLink, submenu, subLinks } = mountNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });

        parentLink.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

        expect(submenu.classList.contains('show')).toBe(true);
        expect(document.activeElement).toBe(subLinks[0]);
    });

    it('moves focus between submenu links with the arrow keys (wrapping)', () => {
        const { root, parentLink, subLinks } = mountNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });
        parentLink.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

        subLinks[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
        expect(document.activeElement).toBe(subLinks[1]);

        subLinks[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
        expect(document.activeElement).toBe(subLinks[0]);
    });

    it('closes and returns focus to the parent on Escape from within the submenu', () => {
        const { root, parentLink, submenu, subLinks } = mountNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });
        parentLink.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

        subLinks[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(submenu.classList.contains('show')).toBe(false);
        expect(document.activeElement).toBe(parentLink);
    });

    it('closes any open dropdown when clicking outside the nav', () => {
        const { root, dropdown, submenu } = mountNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });
        openItem(dropdown);

        document.getElementById('outside').dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(submenu.classList.contains('show')).toBe(false);
    });

    it('returns null when the nav root is absent', () => {
        expect(createDesktopNav(null)).toBeNull();
    });
});

/**
 * Two sibling dropdowns at the top level, for the single-open-per-level invariant.
 */
function mountSiblingNav() {
    document.body.innerHTML = `
        <ul id="menu-dropdowns" class="nav navbar-nav">
            <li class="nav-item dropdown" id="a">
                <a class="nav-link" href="/a">A</a>
                <ul class="dropdown-menu"><li class="nav-item"><a class="nav-link" href="/a1">A1</a></li></ul>
            </li>
            <li class="nav-item dropdown" id="b">
                <a class="nav-link" href="/b">B</a>
                <ul class="dropdown-menu"><li class="nav-item"><a class="nav-link" href="/b1">B1</a></li></ul>
            </li>
        </ul>`;

    return {
        root: document.getElementById('menu-dropdowns'),
        a: document.getElementById('a'),
        b: document.getElementById('b'),
        aMenu: document.querySelector('#a > .dropdown-menu'),
        bMenu: document.querySelector('#b > .dropdown-menu'),
    };
}

/**
 * A three-level nav (top -> L2 dropdown -> L3 flyout) for multi-level keyboard tests.
 */
function mountDeepNav() {
    document.body.innerHTML = `
        <ul id="menu-dropdowns" class="nav navbar-nav">
            <li class="nav-item dropdown" id="l1">
                <a class="nav-link" href="/l1">L1</a>
                <ul class="dropdown-menu">
                    <li class="nav-item dropdown" id="l2a">
                        <a class="nav-link" href="/l2a">L2A</a>
                        <ul class="dropdown-menu"><li class="nav-item"><a class="nav-link" href="/l3a1">L3A1</a></li></ul>
                    </li>
                    <li class="nav-item" id="l2b"><a class="nav-link" href="/l2b">L2B</a></li>
                </ul>
            </li>
        </ul>`;

    return {
        root: document.getElementById('menu-dropdowns'),
        l1: document.getElementById('l1'),
        l2a: document.getElementById('l2a'),
        l1Menu: document.querySelector('#l1 > .dropdown-menu'),
        l2aLink: document.querySelector('#l2a > a.nav-link'),
        l2bLink: document.querySelector('#l2b > a.nav-link'),
        l3Menu: document.querySelector('#l2a > .dropdown-menu'),
        l3Link: document.querySelector('#l2a .dropdown-menu .nav-link'),
    };
}

describe('single-open-per-level invariant', () => {
    it('closes an open sibling dropdown when another opens', () => {
        const { root, a, b, aMenu, bMenu } = mountSiblingNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });

        a.dispatchEvent(new MouseEvent('mouseenter'));
        expect(aMenu.classList.contains('show')).toBe(true);

        // Open B without a mouseleave on A: the sibling-close must still collapse A.
        b.dispatchEvent(new MouseEvent('mouseenter'));
        expect(bMenu.classList.contains('show')).toBe(true);
        expect(aMenu.classList.contains('show')).toBe(false);
    });
});

describe('multi-level keyboard handling', () => {
    it('opens a nested flyout on ArrowDown without the ancestor stealing focus to a sibling', () => {
        const { root, l1, l2aLink, l2bLink, l3Menu, l3Link } = mountDeepNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });
        openItem(l1);

        l2aLink.focus();
        l2aLink.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

        // The L3 flyout opened and focus is on its first item — NOT bumped to the
        // L2 sibling by the ancestor submenu handler (stopPropagation).
        expect(l3Menu.classList.contains('show')).toBe(true);
        expect(document.activeElement).toBe(l3Link);
        expect(document.activeElement).not.toBe(l2bLink);
    });

    it('closes only the current level on Escape from a nested flyout', () => {
        const { root, l1, l2a, l1Menu, l2aLink, l3Menu, l3Link } = mountDeepNav();
        createDesktopNav(root, { doc: document, hoverCapable: true });
        openItem(l1);
        openItem(l2a);

        l3Link.focus();
        l3Link.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        // Only the L3 flyout closes; the L2 menu stays open and focus returns to
        // the L3's parent link (not thrown two levels up to the top).
        expect(l3Menu.classList.contains('show')).toBe(false);
        expect(l1Menu.classList.contains('show')).toBe(true);
        expect(document.activeElement).toBe(l2aLink);
    });
});
