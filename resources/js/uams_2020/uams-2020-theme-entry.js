/**
 * UAMS 2020 front-end script stack.
 *
 * The theme's runtime JavaScript is now framework-free vanilla (jQuery, Popper,
 * smartmenus, and `bootstrap.bundle.js` were removed in UAMS-Web/uams-statamic-archive#1343). Each behavior is
 * a small ES module under `source/` with an exported `init*` entry point, unit-
 * tested under jsdom (mirroring the UAMS-Web/uams-statamic-archive#53 mobile-nav port). The theme's compiled
 * Bootstrap 4 *CSS* stays: these modules toggle the same state classes
 * Bootstrap's JS toggled (`.show`, `.carousel-item.active`, `.modal.show`), so
 * the flyouts, slides, and lightbox look unchanged.
 *
 * 1. `source/genesis/skip-links.js` — skip-link focus fix (self-initializing)
 * 2. `source/desktop-nav.js`        — desktop primary nav (replaces smartmenus)
 * 3. `source/gallery-cleanup.js`    — strip stray `<br>` from WP gallery markup
 * 4. `source/carousel.js`           — hero carousel (replaces BS4 carousel)
 * 5. `source/modal.js`              — gallery lightbox (replaces BS4 modal)
 * 6. `source/counter-list.js`       — Counter List block (self-initializing)
 * 7. `source/mobile-nav.js`         — accessible mobile primary navigation (UAMS-Web/uams-statamic-archive#53)
 */

import './source/genesis/skip-links.js';
import './source/counter-list.js';
import { initDesktopNav } from './source/desktop-nav.js';
import { initGalleryCleanup } from './source/gallery-cleanup.js';
import { initCarousels } from './source/carousel.js';
import { initModals } from './source/modal.js';
import { initMobileNav } from './source/mobile-nav.js';

/**
 * Wire every theme behavior once the DOM is ready.
 */
function initThemeBehaviors() {
    initDesktopNav();
    initGalleryCleanup();
    initCarousels();
    initModals();
    initMobileNav();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeBehaviors);
    } else {
        initThemeBehaviors();
    }
}
