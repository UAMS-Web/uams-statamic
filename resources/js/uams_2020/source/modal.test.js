// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { createModalController, initModals } from './modal.js';

/**
 * Mirrors page_builder/_uamsweb_block_gallery.antlers.html: a data-API trigger
 * and a fade modal with a close button. jsdom has no CSS transition, so the
 * close finalizes synchronously.
 */
function mountModal(extraFocusable = '') {
    document.body.innerHTML = `
        <a id="trigger" href="#" data-toggle="modal" data-target="#modal_1">Open</a>
        <div class="modal fade" id="modal_1" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button id="close-btn" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    ${extraFocusable}
                </div>
            </div>
        </div>`;

    return {
        trigger: document.getElementById('trigger'),
        modal: document.getElementById('modal_1'),
        closeBtn: document.getElementById('close-btn'),
    };
}

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('initModals', () => {
    it('opens the target modal from its data-API trigger', () => {
        const { trigger, modal, closeBtn } = mountModal();
        initModals(document);

        trigger.click();

        expect(modal.style.display).toBe('block');
        expect(modal.classList.contains('show')).toBe(true);
        expect(modal.getAttribute('aria-modal')).toBe('true');
        expect(modal.hasAttribute('aria-hidden')).toBe(false);
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(document.querySelector('.modal-backdrop')).not.toBeNull();
        expect(document.activeElement).toBe(closeBtn);
    });

    it('closes from the dismiss button and restores focus to the trigger', () => {
        const { trigger, modal } = mountModal();
        initModals(document);
        trigger.click();

        document.getElementById('close-btn').click();

        expect(modal.style.display).toBe('none');
        expect(modal.classList.contains('show')).toBe(false);
        expect(modal.getAttribute('aria-hidden')).toBe('true');
        expect(document.body.classList.contains('modal-open')).toBe(false);
        expect(document.querySelector('.modal-backdrop')).toBeNull();
        expect(document.activeElement).toBe(trigger);
    });

    it('closes on Escape', () => {
        const { trigger, modal } = mountModal();
        initModals(document);
        trigger.click();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(modal.classList.contains('show')).toBe(false);
        expect(modal.style.display).toBe('none');
    });

    it('closes when the backdrop area (the modal element itself) is clicked', () => {
        const { trigger, modal } = mountModal();
        initModals(document);
        trigger.click();

        modal.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(modal.classList.contains('show')).toBe(false);
    });

    it('traps Tab within the open modal', () => {
        const { trigger, closeBtn } = mountModal('<a id="second" href="/more">More</a>');
        initModals(document);
        trigger.click();

        const second = document.getElementById('second');
        second.focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));
        expect(document.activeElement).toBe(closeBtn);

        closeBtn.focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true }));
        expect(document.activeElement).toBe(second);
    });
});

describe('createModalController', () => {
    it('reports and switches the open modal', () => {
        const { modal } = mountModal();
        const controller = createModalController(document);

        expect(controller.isOpen()).toBe(false);
        controller.open(modal);
        expect(controller.isOpen()).toBe(true);
        expect(controller.current()).toBe(modal);

        controller.close();
        expect(controller.isOpen()).toBe(false);
    });
});

describe('body scroll-lock compensation', () => {
    beforeEach(() => {
        window.innerWidth = 1024;
        // Simulate a 15px page scrollbar (clientWidth < innerWidth).
        Object.defineProperty(document.documentElement, 'clientWidth', { configurable: true, get: () => 1009 });
    });

    afterEach(() => {
        delete document.documentElement.clientWidth;
    });

    it('pads the body by the scrollbar width on open and restores it on close', () => {
        const { modal } = mountModal();
        const controller = createModalController(document);

        controller.open(modal);
        expect(document.body.style.paddingRight).toBe('15px');

        controller.close();
        expect(document.body.style.paddingRight).toBe('');
    });
});

describe('modal switch during a fade-out (transition branch)', () => {
    let realGetComputedStyle;

    beforeEach(() => {
        realGetComputedStyle = window.getComputedStyle;
        // Force the deferred (fading) close path.
        window.getComputedStyle = () => ({ transitionDuration: '0.15s' });
    });

    afterEach(() => {
        window.getComputedStyle = realGetComputedStyle;
    });

    it('keeps the newly-opened modal intact when the previous one finishes fading', () => {
        document.body.innerHTML = `
            <div class="modal fade" id="A" aria-hidden="true"><div class="modal-content"><button class="close" data-dismiss="modal">x</button></div></div>
            <div class="modal fade" id="B" aria-hidden="true"><div class="modal-content"><button class="close" data-dismiss="modal">x</button></div></div>`;
        const a = document.getElementById('A');
        const b = document.getElementById('B');
        const controller = createModalController(document);

        controller.open(a);
        controller.open(b); // closes A (deferred finalize) then opens B with a fresh backdrop

        // A's fade completes and runs its deferred finalize — it must tear down only
        // A's own backdrop, not B's, and must not release the body lock while B is open.
        a.dispatchEvent(new Event('transitionend'));

        expect(controller.current()).toBe(b);
        expect(b.classList.contains('show')).toBe(true);
        expect(b.style.display).toBe('block');
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(document.querySelectorAll('.modal-backdrop')).toHaveLength(1);
    });
});
