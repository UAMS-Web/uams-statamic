/**
 * Accessible mobile primary navigation (UAMS-Web/uams-statamic-archive#53).
 *
 * Framework-free counterpart to the desktop SmartMenus nav: the hamburger
 * toggles the panel, each branch's button expands its nested submenu, and
 * Escape closes the panel and returns focus to the hamburger. The two navs are
 * breakpoint-gated in the markup, so only one is interactive at a time.
 *
 * The exported functions take their elements/document so they unit-test under
 * jsdom without a real browser.
 */

const TOGGLER_SELECTOR = '[data-mobile-nav-toggler]';
const ROOT_SELECTOR = '[data-mobile-nav]';
const PANEL_SELECTOR = '[data-mobile-nav-panel]';
const SUBMENU_TOGGLE_SELECTOR = '[data-mobile-nav-submenu-toggle]';

/**
 * Reflect an open/closed state onto a trigger button and the element it controls.
 *
 * @param {HTMLElement} trigger  The button whose `aria-expanded` is synced
 * @param {HTMLElement} controlled  The panel/submenu shown or hidden
 * @param {boolean} expanded  Whether the controlled element is now open
 */
export function setExpanded(trigger, controlled, expanded) {
    trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    if (expanded) {
        controlled.removeAttribute('hidden');
        controlled.classList.add('is-open');
    } else {
        controlled.setAttribute('hidden', '');
        controlled.classList.remove('is-open');
    }
}

/**
 * Wire one submenu toggle button to show/hide its controlled submenu.
 *
 * @param {HTMLElement} button  A `[data-mobile-nav-submenu-toggle]` button
 * @param {Document} doc  The owning document
 */
export function wireSubmenuToggle(button, doc) {
    const submenu = doc.getElementById(button.getAttribute('aria-controls') || '');

    if (!submenu) {
        return;
    }

    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        setExpanded(button, submenu, !expanded);
    });
}

/**
 * Wire one mobile-nav region: the hamburger panel toggle, submenu toggles, and
 * Escape-to-close with focus return.
 *
 * @param {HTMLElement} root  The `[data-mobile-nav]` region
 * @param {object} [options]
 * @param {HTMLElement|null} [options.toggler]  The hamburger button
 * @param {Document} [options.doc]  The owning document
 * @returns {{open: Function, close: Function, toggle: Function, isOpen: Function}|null}
 */
export function createMobileNav(root, { toggler = null, doc = document } = {}) {
    if (!root) {
        return null;
    }

    const panel = root.querySelector(PANEL_SELECTOR);

    if (!panel) {
        return null;
    }

    const isOpen = () => toggler?.getAttribute('aria-expanded') === 'true';

    const open = () => {
        if (toggler) {
            setExpanded(toggler, panel, true);
        }

        // Move focus into the panel so keyboard users land on the first link.
        const firstLink = panel.querySelector('a, button');

        if (firstLink && typeof firstLink.focus === 'function') {
            firstLink.focus();
        }
    };

    const close = ({ returnFocus = false } = {}) => {
        if (toggler) {
            setExpanded(toggler, panel, false);

            if (returnFocus) {
                toggler.focus();
            }
        }
    };

    const toggle = () => (isOpen() ? close() : open());

    if (toggler) {
        toggler.addEventListener('click', toggle);
    }

    root.querySelectorAll(SUBMENU_TOGGLE_SELECTOR).forEach((button) => {
        wireSubmenuToggle(button, doc);
    });

    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            close({ returnFocus: true });
        }
    });

    return { open, close, toggle, isOpen };
}

/**
 * Discover and wire the page's mobile navigation.
 *
 * @param {Document} [doc]  The owning document
 * @returns {ReturnType<typeof createMobileNav>}
 */
export function initMobileNav(doc = document) {
    return createMobileNav(doc.querySelector(ROOT_SELECTOR), {
        toggler: doc.querySelector(TOGGLER_SELECTOR),
        doc,
    });
}
