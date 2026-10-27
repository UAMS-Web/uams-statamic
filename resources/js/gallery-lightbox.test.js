// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';

import { registerGalleryLightbox } from './gallery-lightbox.js';

/**
 * Instantiate the `galleryLightbox` component object by capturing the factory
 * that registerGalleryLightbox registers, then invoking it.
 *
 * @returns {object} A fresh component state object.
 */
function makeComponent() {
    let factory;
    registerGalleryLightbox({ data: (_name, f) => { factory = f; } });

    return factory();
}

/** Build a three-image gallery grid and return the wrapper. */
function buildDom() {
    document.body.innerHTML = `
        <aside class="gallery-set">
            <ul>
                <li><button data-gallery-item data-gallery-full="/a-1600.jpg" data-gallery-alt="Alpha" data-gallery-caption="Cap A"></button></li>
                <li><button data-gallery-item data-gallery-full="/b-1600.jpg" data-gallery-alt="Beta" data-gallery-caption=""></button></li>
                <li><button data-gallery-item data-gallery-full="/c-1600.jpg" data-gallery-alt="Gamma" data-gallery-caption="Cap C"></button></li>
            </ul>
        </aside>`;

    return document.querySelector('.gallery-set');
}

/**
 * Mount the component on the grid and run its init.
 *
 * `$root` is the component element and `$el` is deliberately a TRIGGER, not the
 * root. That models `@alpinejs/csp`: a method invoked from a directive expression
 * sees `$el` as the element carrying that directive, so a method reached from
 * `@click` on a trigger sees the button. Pointing `$el` at the root here is what
 * hid UAMS-Web/uams-statamic-archive#2246 from this suite — the component queried `this.$el`, which was the root
 * in jsdom and the clicked button in the browser, so `triggers()` came back empty
 * only in real use. Any future read of `$el` for a component-wide query fails here.
 */
function mount() {
    const el = buildDom();
    const component = makeComponent();

    component.$root = el;
    component.$el = el.querySelector('[data-gallery-item]');
    component.$nextTick = (fn) => fn();
    component.$refs = { close: document.createElement('button'), dialog: document.createElement('div') };
    component.init();

    return component;
}

describe('galleryLightbox', () => {
    it('reads images from the grid triggers on init', () => {
        const component = mount();

        expect(component.images).toHaveLength(3);
        expect(component.images[0]).toEqual({ src: '/a-1600.jpg', alt: 'Alpha', caption: 'Cap A' });
    });

    it('opens at the clicked trigger and records it for focus return', () => {
        const component = mount();
        const trigger = component.$root.querySelectorAll('[data-gallery-item]')[1];

        component.show(trigger);

        expect(component.open).toBe(true);
        expect(component.current).toBe(1);
        expect(component.lastTrigger).toBe(trigger);
        expect(component.currentImage.alt).toBe('Beta');
    });

    it('wraps around with next and prev', () => {
        const component = mount();
        component.show(component.$root.querySelectorAll('[data-gallery-item]')[2]);

        component.next();
        expect(component.current).toBe(0);

        component.prev();
        expect(component.current).toBe(2);
    });

    it('closes and returns focus to the opening trigger', () => {
        const component = mount();
        const trigger = component.$root.querySelectorAll('[data-gallery-item]')[0];
        let focused = false;
        trigger.focus = () => { focused = true; };

        component.show(trigger);
        component.close();

        expect(component.open).toBe(false);
        expect(focused).toBe(true);
    });

    it('navigates on a horizontal swipe', () => {
        const component = mount();
        component.show(component.$root.querySelectorAll('[data-gallery-item]')[0]);

        // Swipe left → next.
        component.onTouchStart({ changedTouches: [{ clientX: 200 }] });
        component.onTouchEnd({ changedTouches: [{ clientX: 100 }] });
        expect(component.current).toBe(1);

        // Swipe right → prev.
        component.onTouchStart({ changedTouches: [{ clientX: 100 }] });
        component.onTouchEnd({ changedTouches: [{ clientX: 200 }] });
        expect(component.current).toBe(0);
    });

    it('ignores a tap that is not a real swipe', () => {
        const component = mount();
        component.show(component.$root.querySelectorAll('[data-gallery-item]')[0]);

        component.onTouchStart({ changedTouches: [{ clientX: 100 }] });
        component.onTouchEnd({ changedTouches: [{ clientX: 110 }] });

        expect(component.current).toBe(0);
    });
});
