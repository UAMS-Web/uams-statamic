// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';

import { registerLiteVideo } from './lite-video.js';

/** Instantiate the `liteVideo` component object by capturing its factory. */
function makeComponent() {
    let factory;
    registerLiteVideo({ data: (_name, f) => { factory = f; } });

    return factory();
}

/**
 * Mount the component on a wrapper carrying the given data attributes.
 *
 * `withPlayer` renders the iframe the partial server-renders once `loaded` flips,
 * with the src already on it — the component never sets that (UAMS-Web/uams-statamic-archive#2245).
 */
function mount(videoUrl, embedUrl, { withPlayer = false } = {}) {
    const player = withPlayer
        ? `<iframe class="lite-video__player" src="${embedUrl}"></iframe>`
        : '';

    document.body.innerHTML = `<div class="lite-video" tabindex="-1"`
        + ` data-video-url="${videoUrl}" data-embed-url="${embedUrl}">${player}</div>`;

    const component = makeComponent();

    component.$el = document.querySelector('.lite-video');
    component.$nextTick = (fn) => fn();
    component.init();

    return component;
}

describe('liteVideo', () => {
    it('extracts the video id from watch, short, and embed urls', () => {
        const component = makeComponent();

        expect(component.extractYouTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
        expect(component.extractYouTubeId('https://youtu.be/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
        expect(component.extractYouTubeId('https://www.youtube.com/embed/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
        expect(component.extractYouTubeId('https://vimeo.com/12345')).toBe('');
    });

    it('derives the hot-linked thumbnail from the id', () => {
        const component = mount('https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ');

        expect(component.videoId).toBe('dQw4w9WgXcQ');
        expect(component.thumbnail).toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
    });

    // The autoplay url is built by the partial now, not here, so there is nothing
    // left in this file to assert about it. `tests/Feature/LiteVideoFacadeTest.php`
    // covers the rendered src, and the browser suite asserts the live attribute.

    it('focuses the player from its own root rather than from $el', () => {
        const component = mount('https://youtu.be/x', 'https://www.youtube.com/embed/x', { withPlayer: true });

        // Reproduce what Alpine actually does: `load()` runs from `@click` on the
        // play button, so `$el` is that BUTTON and not the wrapper. Querying from
        // `$el` can never reach the player — measured in the browser, and the reason
        // an earlier fix that swapped `$refs` for a DOM query still found nothing.
        component.$el = document.createElement('button');

        component.load();

        expect(component.loaded).toBe(true);
        expect(document.activeElement.className).toContain('lite-video__player');
    });

    it('falls back to the wrapper when the player is absent', () => {
        const component = mount('https://youtu.be/x', 'https://www.youtube.com/embed/x');

        component.$el = document.createElement('button');

        component.load();

        expect(document.activeElement.className).toContain('lite-video');
    });
});
