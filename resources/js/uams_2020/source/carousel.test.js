// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { createCarousel } from './carousel.js';

/**
 * Mirrors layout/page-header/_hero-content.antlers.html: a focusable multi-slide
 * carousel with indicators and prev/next controls. jsdom has no CSS transition,
 * so slides swap instantly and the index logic is directly observable.
 */
function mountCarousel(slides = 3) {
    const items = Array.from({ length: slides }, (_, i) =>
        `<div class="carousel-item${i === 0 ? ' active' : ''}">Slide ${i}</div>`).join('');
    const indicators = Array.from({ length: slides }, (_, i) =>
        `<li data-slide-to="${i}"${i === 0 ? ' class="active"' : ''}></li>`).join('');

    document.body.innerHTML = `
        <section class="carousel slide" data-keyboard="true" tabindex="0">
            <ol class="carousel-indicators">${indicators}</ol>
            <div class="carousel-inner">${items}</div>
            <a class="carousel-control-prev" data-slide="prev" href="#">prev</a>
            <a class="carousel-control-next" data-slide="next" href="#">next</a>
        </section>`;

    return document.querySelector('.carousel');
}

const activeIndex = (root) =>
    Array.from(root.querySelectorAll('.carousel-item')).findIndex((i) => i.classList.contains('active'));

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('createCarousel', () => {
    it('returns null for a single-slide carousel', () => {
        expect(createCarousel(mountCarousel(1))).toBeNull();
    });

    it('advances to the next slide and syncs the indicator', () => {
        const root = mountCarousel();
        const carousel = createCarousel(root);

        carousel.next();

        expect(activeIndex(root)).toBe(1);
        expect(root.querySelectorAll('.carousel-indicators .active')).toHaveLength(1);
        expect(root.querySelector('[data-slide-to="1"]').classList.contains('active')).toBe(true);
    });

    it('wraps from the first slide to the last when going previous', () => {
        const root = mountCarousel();
        const carousel = createCarousel(root);

        carousel.prev();

        expect(activeIndex(root)).toBe(2);
    });

    it('wraps from the last slide to the first when going next', () => {
        const root = mountCarousel();
        const carousel = createCarousel(root);

        carousel.goTo(2);
        carousel.next();

        expect(activeIndex(root)).toBe(0);
    });

    it('jumps to a slide when its indicator is clicked', () => {
        const root = mountCarousel();
        createCarousel(root);

        root.querySelector('[data-slide-to="2"]').click();

        expect(activeIndex(root)).toBe(2);
    });

    it('advances / rewinds from the prev/next controls', () => {
        const root = mountCarousel();
        createCarousel(root);

        root.querySelector('[data-slide="next"]').click();
        expect(activeIndex(root)).toBe(1);

        root.querySelector('[data-slide="prev"]').click();
        expect(activeIndex(root)).toBe(0);
    });

    it('responds to Left/Right arrow keys on the focusable root', () => {
        const root = mountCarousel();
        createCarousel(root);

        root.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        expect(activeIndex(root)).toBe(1);

        root.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
        expect(activeIndex(root)).toBe(0);
    });

    it('ignores arrow keys when keyboard control is disabled', () => {
        const root = mountCarousel();
        root.setAttribute('data-keyboard', 'false');
        createCarousel(root);

        root.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

        expect(activeIndex(root)).toBe(0);
    });
});

describe('createCarousel (animated / transition branch)', () => {
    // Force the production animated path (jsdom and the CSS-less browser fixture
    // otherwise only ever hit the instant branch): report a real transition
    // duration and no reduced-motion preference.
    let realGetComputedStyle;
    let realMatchMedia;

    beforeEach(() => {
        realGetComputedStyle = window.getComputedStyle;
        realMatchMedia = window.matchMedia;
        window.getComputedStyle = () => ({ transitionDuration: '0.6s', transitionDelay: '0s' });
        window.matchMedia = () => ({ matches: false });
    });

    afterEach(() => {
        window.getComputedStyle = realGetComputedStyle;
        window.matchMedia = realMatchMedia;
        vi.useRealTimers();
    });

    it('slides via the directional classes and settles on transitionend', () => {
        const root = mountCarousel();
        const carousel = createCarousel(root);
        const items = root.querySelectorAll('.carousel-item');

        carousel.next();

        // Mid-transition: incoming positioned + sliding, active NOT yet swapped.
        expect(items[1].classList.contains('carousel-item-next')).toBe(true);
        expect(items[1].classList.contains('carousel-item-left')).toBe(true);
        expect(activeIndex(root)).toBe(0);

        items[1].dispatchEvent(new Event('transitionend'));

        // Settled: active swapped, transient classes cleared, and `sliding` reset
        // (a second advance works rather than being wedged).
        expect(activeIndex(root)).toBe(1);
        expect(items[1].classList.contains('carousel-item-next')).toBe(false);
        expect(items[1].classList.contains('carousel-item-left')).toBe(false);

        carousel.next();
        items[2].dispatchEvent(new Event('transitionend'));
        expect(activeIndex(root)).toBe(2);
    });

    it('settles via the safety timeout when transitionend never fires', () => {
        vi.useFakeTimers();
        const root = mountCarousel();
        const carousel = createCarousel(root);

        carousel.next();
        expect(activeIndex(root)).toBe(0); // still animating, no transitionend fired

        vi.advanceTimersByTime(700); // past duration (600) + 50 safety margin

        expect(activeIndex(root)).toBe(1);
    });
});
