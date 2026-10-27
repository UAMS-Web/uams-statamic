/**
 * Framework-free modal dialog (gallery lightbox).
 *
 * Replaces Bootstrap 4's modal plugin (`bootstrap.bundle.js`, UAMS-Web/uams-statamic-archive#1343) while
 * reusing the theme's already-compiled Bootstrap 4 modal *CSS*: it drives the
 * same `.modal.show` / `.modal-backdrop` / `body.modal-open` state Bootstrap's
 * JS toggled, so the fade, backdrop, and centering look identical. It adds a
 * focus trap, Escape-to-close, backdrop-click-to-close, and focus restoration to
 * the trigger — the accessibility Bootstrap's plugin provided.
 *
 * Consumes the Bootstrap 4 data-API the templates already emit —
 * `[data-toggle="modal"][data-target="#id"]` opens, `[data-dismiss="modal"]`
 * closes (`page_builder/_uamsweb_block_gallery.antlers.html`) — so no template
 * change is needed. The functions take their document so they unit-test under
 * jsdom (mirrors `source/mobile-nav.js`, UAMS-Web/uams-statamic-archive#53).
 */

const FOCUSABLE = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

/**
 * Read an element's CSS transition duration in milliseconds (0 when none, e.g.
 * under `prefers-reduced-motion` or jsdom), used to decide whether to wait for a
 * fade-out before hiding.
 *
 * @param {HTMLElement} element
 * @returns {number}
 */
function getTransitionDurationMs(element) {
    const view = element.ownerDocument?.defaultView;

    if (view?.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
        || !view || typeof view.getComputedStyle !== 'function') {
        return 0;
    }

    return (parseFloat(view.getComputedStyle(element).transitionDuration) || 0) * 1000;
}

/**
 * Create a controller that owns the single open modal at a time (galleries open
 * one lightbox at a time).
 *
 * @param {Document} [doc]  The owning document.
 * @returns {{open: (modal: HTMLElement, trigger?: HTMLElement|null) => void, close: () => void, isOpen: () => boolean, current: () => HTMLElement|null}}
 */
export function createModalController(doc = document) {
    let openModal = null;
    let backdrop = null;
    let lastTrigger = null;
    let previousBodyPaddingRight = '';

    const focusableIn = (modal) => Array.from(modal.querySelectorAll(FOCUSABLE))
        .filter((el) => !el.hasAttribute('disabled'));

    const close = () => {
        if (!openModal) {
            return;
        }

        const modal = openModal;
        const trigger = lastTrigger;
        // Capture the backdrop being torn down as a local: when a fade defers
        // finalize, a switch to another modal in that window reassigns the
        // module-level `backdrop`, and finalize must not remove the new one.
        const closingBackdrop = backdrop;
        openModal = null;
        lastTrigger = null;
        backdrop = null;

        modal.classList.remove('show');
        closingBackdrop?.classList.remove('show');

        const finalize = () => {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
            closingBackdrop?.remove();

            // Only release the body lock if nothing was opened since (a modal
            // switch during the fade leaves a new modal owning the lock).
            if (!openModal) {
                doc.body.classList.remove('modal-open');
                doc.body.style.paddingRight = previousBodyPaddingRight;
            }

            if (trigger && typeof trigger.focus === 'function') {
                trigger.focus();
            }
        };

        const duration = getTransitionDurationMs(modal);

        if (duration > 0) {
            let settled = false;
            const onEnd = () => {
                if (settled) {
                    return;
                }

                settled = true;
                finalize();
            };

            modal.addEventListener('transitionend', onEnd, { once: true });
            doc.defaultView?.setTimeout(onEnd, duration + 50);
        } else {
            finalize();
        }
    };

    const open = (modal, trigger = null) => {
        if (!modal || openModal === modal) {
            return;
        }

        if (openModal) {
            close();
        }

        lastTrigger = trigger;
        openModal = modal;

        // `.modal-open` sets `overflow: hidden` on the body (kept Bootstrap 4
        // CSS), removing the page scrollbar; pad the body by the scrollbar width
        // so the content does not jump horizontally (Bootstrap's _setScrollbar).
        // `clientWidth > 0` skips the compensation under jsdom (no real layout).
        const view = doc.defaultView;
        const clientWidth = doc.documentElement.clientWidth;
        const scrollbarWidth = view && clientWidth > 0 ? view.innerWidth - clientWidth : 0;

        if (scrollbarWidth > 0) {
            previousBodyPaddingRight = doc.body.style.paddingRight;
            doc.body.style.paddingRight = `${scrollbarWidth}px`;
        }

        doc.body.classList.add('modal-open');
        backdrop = doc.createElement('div');
        backdrop.className = 'modal-backdrop fade';
        backdrop.addEventListener('click', close);
        doc.body.appendChild(backdrop);

        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');
        modal.setAttribute('aria-modal', 'true');

        // Force reflow so `.show` animates from the hidden state.
        void modal.offsetHeight;
        modal.classList.add('show');
        backdrop.classList.add('show');

        const focusTarget = focusableIn(modal)[0] || modal;
        focusTarget.focus();
    };

    return {
        open,
        close,
        isOpen: () => openModal !== null,
        current: () => openModal,
    };
}

/**
 * Wire the Bootstrap-4 data-API modal triggers and global close affordances
 * (Escape, backdrop click, `[data-dismiss="modal"]`) plus a Tab focus trap.
 *
 * @param {Document} [doc]  The owning document.
 * @returns {ReturnType<typeof createModalController>}
 */
export function initModals(doc = document) {
    const controller = createModalController(doc);

    doc.querySelectorAll('[data-toggle="modal"][data-target]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            const target = doc.querySelector(trigger.getAttribute('data-target'));

            if (target) {
                event.preventDefault();
                controller.open(target, trigger);
            }
        });
    });

    doc.addEventListener('click', (event) => {
        const dismiss = event.target.closest?.('[data-dismiss="modal"]');

        if (dismiss && controller.isOpen()) {
            event.preventDefault();
            controller.close();
        }

        // A click on the modal element itself (the backdrop area outside the
        // centered dialog) closes it, matching Bootstrap's `backdrop: true`.
        if (event.target === controller.current()) {
            controller.close();
        }
    });

    doc.addEventListener('keydown', (event) => {
        const modal = controller.current();

        if (!modal) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            controller.close();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        // Focus trap: keep Tab within the open modal.
        const focusables = Array.from(modal.querySelectorAll(FOCUSABLE))
            .filter((el) => !el.hasAttribute('disabled'));

        if (focusables.length === 0) {
            event.preventDefault();

            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = doc.activeElement;

        if (event.shiftKey && (active === first || !modal.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !modal.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    });

    return controller;
}
