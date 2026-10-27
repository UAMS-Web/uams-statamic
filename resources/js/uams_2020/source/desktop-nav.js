/**
 * Framework-free desktop primary navigation (smartmenus replacement).
 *
 * Replaces `smartmenus` + `smartmenus-bootstrap-4` + the custom
 * `overflowing-navbar` (all jQuery, UAMS-Web/uams-statamic-archive#1343) for the desktop dropdown nav
 * (`layout/navigation/main/desktop.antlers.html`, `#menu-dropdowns`). It reuses
 * the theme's compiled Bootstrap 4 dropdown *CSS* — toggling the same
 * `.dropdown-menu.show` class Bootstrap/smartmenus toggled, on `.dropdown`
 * positioning contexts already in the markup — so the flyouts look identical.
 *
 * Like smartmenus, it enhances the server markup at runtime: each parent link
 * gets `.has-submenu`, a `.sub-arrow` caret, `aria-haspopup`, and
 * `aria-expanded`. Behavior: hover opens on pointer devices, tap opens then
 * navigates on touch, ArrowUp/Down + Escape drive keyboard use, and clicking
 * away or tabbing out closes everything. Items that would wrap onto a second row
 * collapse into a trailing "More" dropdown, recomputed on resize (the
 * `overflowing-navbar` behavior).
 *
 * The exported functions take their root/document so the enhancement and
 * open/close/keyboard logic unit-test under jsdom (overflow needs real layout,
 * so it is covered by the browser smoke test); mirrors `source/mobile-nav.js`.
 */

const ROOT_SELECTOR = '#menu-dropdowns';
const OVERFLOW_DEBOUNCE_MS = 150;

/**
 * The direct-child submenu of a nav item, or null when the item is a leaf.
 *
 * @param {HTMLElement} item  An `li` nav item.
 * @returns {HTMLElement|null}
 */
function submenuOf(item) {
    return Array.from(item.children).find((child) => child.matches('ul.dropdown-menu')) || null;
}

/**
 * The direct-child nav link of a nav item.
 *
 * @param {HTMLElement} item  An `li` nav item.
 * @returns {HTMLElement|null}
 */
function linkOf(item) {
    return Array.from(item.children).find((child) => child.matches('a.nav-link, a.dropdown-item')) || null;
}

/**
 * Open a nav item's submenu (idempotent).
 *
 * @param {HTMLElement} item
 */
export function openItem(item) {
    const submenu = submenuOf(item);
    const link = linkOf(item);

    if (!submenu || !link) {
        return;
    }

    submenu.classList.add('show');
    link.setAttribute('aria-expanded', 'true');
}

/**
 * Close a nav item's submenu and every submenu nested inside it.
 *
 * @param {HTMLElement} item
 */
export function closeItem(item) {
    const submenu = submenuOf(item);
    const link = linkOf(item);

    if (!submenu || !link) {
        return;
    }

    submenu.querySelectorAll('ul.dropdown-menu.show').forEach((nested) => {
        nested.classList.remove('show');
    });
    submenu.querySelectorAll('.nav-link[aria-expanded="true"], .dropdown-item[aria-expanded="true"]')
        .forEach((nestedLink) => nestedLink.setAttribute('aria-expanded', 'false'));

    submenu.classList.remove('show');
    link.setAttribute('aria-expanded', 'false');
}

/**
 * Whether a nav item's submenu is currently open.
 *
 * @param {HTMLElement} item
 * @returns {boolean}
 */
function isOpen(item) {
    return submenuOf(item)?.classList.contains('show') === true;
}

/**
 * Close any sibling dropdowns at the same level so at most one flyout is open per
 * level, matching smartmenus' single-active-menu behavior. Needed on the tap and
 * keyboard open paths (hover already closes via `mouseleave`).
 *
 * @param {HTMLElement} item  The item about to open.
 */
function closeSiblings(item) {
    const list = item.parentElement;

    if (!list) {
        return;
    }

    Array.from(list.children).forEach((sibling) => {
        if (sibling !== item && sibling.matches?.('li') && isOpen(sibling)) {
            closeItem(sibling);
        }
    });
}

/**
 * Open an item's submenu, first collapsing any open sibling at the same level.
 *
 * @param {HTMLElement} item
 */
function openExclusive(item) {
    closeSiblings(item);
    openItem(item);
}

/**
 * Enhance one nav item that has a submenu: add the caret, the `.has-submenu`
 * hook the SCSS styles, and the ARIA a screen reader needs. Idempotent.
 *
 * @param {HTMLElement} item  An `li` nav item with a child `ul.dropdown-menu`.
 */
export function enhanceItem(item) {
    const submenu = submenuOf(item);
    const link = linkOf(item);

    if (!submenu || !link || link.dataset.submenuEnhanced === 'true') {
        return;
    }

    link.dataset.submenuEnhanced = 'true';
    link.classList.add('has-submenu');
    link.setAttribute('aria-haspopup', 'true');
    link.setAttribute('aria-expanded', 'false');

    if (!link.querySelector('.sub-arrow')) {
        const arrow = item.ownerDocument.createElement('span');
        arrow.className = 'sub-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        link.appendChild(arrow);
    }
}

/**
 * Focusable nav links directly inside a submenu (its immediate item links).
 *
 * @param {HTMLElement} submenu
 * @returns {HTMLElement[]}
 */
function submenuLinks(submenu) {
    return Array.from(submenu.children)
        .map((child) => linkOf(child))
        .filter((link) => link != null);
}

/**
 * Wire one dropdown nav item's pointer, click, and keyboard interactions.
 *
 * @param {HTMLElement} item
 * @param {object} options
 * @param {boolean} options.hoverCapable  Open on hover (pointer) vs tap (touch).
 */
function wireItem(item, { hoverCapable }) {
    const link = linkOf(item);
    const submenu = submenuOf(item);

    if (!link || !submenu) {
        return;
    }

    if (hoverCapable) {
        item.addEventListener('mouseenter', () => openExclusive(item));
        item.addEventListener('mouseleave', () => closeItem(item));
    } else {
        // Touch: first tap opens the submenu, a second tap follows the link.
        link.addEventListener('click', (event) => {
            if (!isOpen(item)) {
                event.preventDefault();
                openExclusive(item);
            }
        });
    }

    link.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            // stopPropagation so a nested parent link's arrow key is not also
            // handled by its ancestor submenu (which would move focus to a
            // sibling and orphan this flyout).
            event.preventDefault();
            event.stopPropagation();
            openExclusive(item);
            const links = submenuLinks(submenu);
            (event.key === 'ArrowDown' ? links[0] : links[links.length - 1])?.focus();
        } else if (event.key === 'Escape' && isOpen(item)) {
            event.preventDefault();
            event.stopPropagation();
            closeItem(item);
        }
    });

    submenu.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Escape') {
            return;
        }

        if (event.key === 'Escape') {
            // stopPropagation so exactly this one level closes; without it the
            // event bubbles to every ancestor submenu (and the document handler),
            // collapsing the whole nested branch on a single Escape.
            event.preventDefault();
            event.stopPropagation();
            closeItem(item);
            link.focus();

            return;
        }

        const links = submenuLinks(submenu);
        const index = links.indexOf(event.target.closest('a'));

        if (index === -1) {
            return;
        }

        event.preventDefault();
        const delta = event.key === 'ArrowDown' ? 1 : -1;
        const nextIndex = (index + delta + links.length) % links.length;
        links[nextIndex].focus();
    });
}

/**
 * Collapse nav items that wrap onto a second row into a trailing "More"
 * dropdown, and expand them back out when there is room. No-op where layout is
 * unmeasurable (e.g. jsdom, where every `offsetTop` is 0).
 *
 * @param {HTMLElement} root  The `#menu-dropdowns` list.
 * @param {HTMLElement} moreItem  The reusable "More" `li`.
 * @param {HTMLElement[]} originalItems  The nav items in their authored order.
 */
function layoutOverflow(root, moreItem, originalItems) {
    const moreSubmenu = submenuOf(moreItem);

    // Restore every item to the main bar in authored order, then drop "More".
    originalItems.forEach((item) => root.appendChild(item));
    moreSubmenu.replaceChildren();

    if (moreItem.parentNode) {
        moreItem.remove();
    }

    const firstTop = originalItems[0]?.offsetTop ?? 0;
    const wraps = originalItems.some((item) => item.offsetTop > firstTop);

    if (!wraps) {
        return;
    }

    root.appendChild(moreItem);

    // Move trailing items into "More" until the bar (including "More") fits one row.
    let guard = originalItems.length;

    while (guard-- > 0 && moreItem.offsetTop > firstTop) {
        const overflowing = originalItems.filter((item) => item.parentNode === root);
        const last = overflowing[overflowing.length - 1];

        if (!last) {
            break;
        }

        moreSubmenu.insertBefore(last, moreSubmenu.firstChild);
    }
}

/**
 * Wire the desktop nav: enhance every dropdown item, add global close-on-outside
 * / close-on-tab-out, and manage the overflow "More" menu.
 *
 * @param {HTMLElement} root  The `#menu-dropdowns` list.
 * @param {object} [options]
 * @param {Document} [options.doc]
 * @param {boolean} [options.hoverCapable]  Defaults to the device's hover media query.
 * @returns {{closeAll: Function, relayout: Function}|null}
 */
export function createDesktopNav(root, { doc = document, hoverCapable } = {}) {
    if (!root) {
        return null;
    }

    const canHover = hoverCapable
        ?? (doc.defaultView?.matchMedia?.('(hover: hover)')?.matches ?? true);

    const dropdownItems = () => Array.from(root.querySelectorAll('li'))
        .filter((item) => submenuOf(item) != null);

    dropdownItems().forEach((item) => {
        enhanceItem(item);
        wireItem(item, { hoverCapable: canHover });
    });

    const closeAll = () => {
        root.querySelectorAll('ul.dropdown-menu.show').forEach((menu) => menu.classList.remove('show'));
        root.querySelectorAll('.has-submenu[aria-expanded="true"]')
            .forEach((link) => link.setAttribute('aria-expanded', 'false'));
    };

    // Close on click outside the nav and when focus leaves it (Tab-out).
    doc.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closeAll();
        }
    });
    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
    root.addEventListener('focusout', (event) => {
        if (!root.contains(event.relatedTarget)) {
            closeAll();
        }
    });

    // Overflow "More": a reusable dropdown item enhanced like any other. It is a
    // disclosure control (a plain `<ul>`, not `role="menu"`, so the moved links
    // don't need `role="menuitem"`), and its `href="#"` button toggles on click
    // and on Enter/Space (the activation keys for `role="button"`), not just hover.
    const moreItem = doc.createElement('li');
    moreItem.className = 'nav-item dropdown nav-more';
    moreItem.innerHTML = '<a class="nav-link" href="#" role="button">More</a>'
        + '<ul class="dropdown-menu dropdown-menu-right"></ul>';
    const originalItems = Array.from(root.children);
    enhanceItem(moreItem);
    wireItem(moreItem, { hoverCapable: canHover });

    const moreLink = linkOf(moreItem);
    const toggleMore = (event) => {
        event.preventDefault();

        if (isOpen(moreItem)) {
            closeItem(moreItem);
        } else {
            openExclusive(moreItem);
        }
    };
    moreLink.addEventListener('click', toggleMore);
    moreLink.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            toggleMore(event);
        }
    });

    const relayout = () => layoutOverflow(root, moreItem, originalItems);
    relayout();

    let debounce = null;
    doc.defaultView?.addEventListener('resize', () => {
        doc.defaultView.clearTimeout(debounce);
        debounce = doc.defaultView.setTimeout(relayout, OVERFLOW_DEBOUNCE_MS);
    });

    return { closeAll, relayout };
}

/**
 * Discover and wire the page's desktop primary navigation.
 *
 * @param {Document} [doc]
 * @returns {ReturnType<typeof createDesktopNav>}
 */
export function initDesktopNav(doc = document) {
    return createDesktopNav(doc.querySelector(ROOT_SELECTOR), { doc });
}
