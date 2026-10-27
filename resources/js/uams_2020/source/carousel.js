/**
 * Framework-free hero carousel.
 *
 * Replaces Bootstrap 4's carousel plugin (`bootstrap.bundle.js`, UAMS-Web/uams-statamic-archive#1343) while
 * reusing the theme's already-compiled Bootstrap 4 carousel *CSS*: it drives the
 * same `.carousel-item.active` / `.carousel-item-{next,prev,left,right}` classes
 * Bootstrap's JS toggled, so the slide transition, indicators, and controls look
 * identical. It never auto-cycles (the hero pins `data-interval="false"` for
 * WCAG 2.2.2 — see UAMS-Web/uams-statamic-archive#604 / UAMS-Web/uams-statamic-archive#734) and supports Left/Right arrow keys on the
 * focusable root (UAMS-Web/uams-statamic-archive#736).
 *
 * The exported functions take their document/element so the index and
 * wrap-around logic unit-test under jsdom (where there is no CSS transition, so
 * slides swap instantly) without a real browser.
 */

/**
 * Read an element's CSS transition duration in milliseconds (0 when none).
 *
 * @param {HTMLElement} element
 * @returns {number}
 */
function getTransitionDurationMs(element) {
    const view = element.ownerDocument?.defaultView;

    if (!view || typeof view.getComputedStyle !== 'function') {
        return 0;
    }

    const { transitionDuration, transitionDelay } = view.getComputedStyle(element);
    const toMs = (value) => (value || '')
        .split(',')
        .reduce((max, part) => {
            const seconds = parseFloat(part) || 0;

            return Math.max(max, part.includes('ms') ? seconds : seconds * 1000);
        }, 0);

    return toMs(transitionDuration) + toMs(transitionDelay);
}

/**
 * Whether slide changes should animate. False under `prefers-reduced-motion`
 * or when there is no CSS transition (e.g. jsdom), so the swap happens instantly.
 *
 * @param {HTMLElement} item  A `.carousel-item`.
 * @returns {boolean}
 */
function shouldAnimate(item) {
    const view = item.ownerDocument?.defaultView;

    if (view?.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) {
        return false;
    }

    return getTransitionDurationMs(item) > 0;
}

/**
 * Wire one `.carousel` element: prev/next controls, indicators, and (when
 * enabled) Left/Right arrow keys. Returns a controller, or `null` when the
 * element has fewer than two slides (nothing to cycle).
 *
 * @param {HTMLElement} root  The `.carousel` element.
 * @returns {{next: Function, prev: Function, goTo: Function, current: () => number}|null}
 */
export function createCarousel(root) {
    if (!root) {
        return null;
    }

    const items = Array.from(root.querySelectorAll('.carousel-item'));

    if (items.length < 2) {
        return null;
    }

    const indicators = Array.from(root.querySelectorAll('.carousel-indicators [data-slide-to]'));
    let sliding = false;

    const currentIndex = () => {
        const active = items.findIndex((item) => item.classList.contains('active'));

        return active === -1 ? 0 : active;
    };

    const syncIndicators = (index) => {
        indicators.forEach((indicator, i) => {
            indicator.classList.toggle('active', i === index);
            indicator.setAttribute('aria-current', i === index ? 'true' : 'false');
        });
    };

    const activate = (fromEl, toEl, index) => {
        fromEl.classList.remove('active');
        toEl.classList.add('active');
        syncIndicators(index);
        sliding = false;
    };

    const goTo = (target, direction) => {
        const from = currentIndex();

        if (sliding || target === from || !items[target]) {
            return;
        }

        const dir = direction || (target > from ? 'next' : 'prev');
        const fromEl = items[from];
        const toEl = items[target];

        if (!shouldAnimate(toEl)) {
            activate(fromEl, toEl, target);

            return;
        }

        sliding = true;
        const positionClass = dir === 'next' ? 'carousel-item-next' : 'carousel-item-prev';
        const orderClass = dir === 'next' ? 'carousel-item-left' : 'carousel-item-right';

        toEl.classList.add(positionClass);
        // Force reflow so the starting position takes effect before sliding.
        void toEl.offsetHeight;
        fromEl.classList.add(orderClass);
        toEl.classList.add(orderClass);

        let settled = false;
        const finish = () => {
            if (settled) {
                return;
            }

            settled = true;
            toEl.classList.remove(positionClass, orderClass);
            fromEl.classList.remove(orderClass);
            activate(fromEl, toEl, target);
        };

        toEl.addEventListener('transitionend', finish, { once: true });
        // Safety net: if the transitionend never fires, settle anyway.
        root.ownerDocument.defaultView?.setTimeout(finish, getTransitionDurationMs(toEl) + 50);
    };

    const next = () => goTo((currentIndex() + 1) % items.length, 'next');
    const prev = () => goTo((currentIndex() - 1 + items.length) % items.length, 'prev');

    root.querySelectorAll('[data-slide="next"]').forEach((control) => {
        control.addEventListener('click', (event) => {
            event.preventDefault();
            next();
        });
    });
    root.querySelectorAll('[data-slide="prev"]').forEach((control) => {
        control.addEventListener('click', (event) => {
            event.preventDefault();
            prev();
        });
    });
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', (event) => {
            event.preventDefault();
            goTo(index);
        });
    });

    if (root.getAttribute('data-keyboard') !== 'false' && root.dataset.keyboardBound !== 'true') {
        root.dataset.keyboardBound = 'true';
        root.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                prev();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                next();
            }
        });
    }

    return { next, prev, goTo, current: currentIndex };
}

/**
 * Discover and wire every hero carousel on the page.
 *
 * @param {Document} [doc]  The owning document.
 * @returns {Array<ReturnType<typeof createCarousel>>}
 */
export function initCarousels(doc = document) {
    return Array.from(doc.querySelectorAll('.carousel.slide')).map((root) => createCarousel(root));
}
