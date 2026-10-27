// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';

import { initGalleryCleanup } from './gallery-cleanup.js';

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('initGalleryCleanup', () => {
    it('removes stray <br> elements inside a .gallery', () => {
        document.body.innerHTML = `
            <div class="gallery">
                <figure><img src="/a.jpg" alt="A"></figure><br>
                <figure><img src="/b.jpg" alt="B"></figure><br>
            </div>`;

        initGalleryCleanup(document);

        expect(document.querySelectorAll('.gallery br')).toHaveLength(0);
        expect(document.querySelectorAll('.gallery figure')).toHaveLength(2);
    });

    it('leaves <br> outside a .gallery untouched', () => {
        document.body.innerHTML = '<p>keep<br>me</p><div class="gallery"><br></div>';

        initGalleryCleanup(document);

        expect(document.querySelectorAll('p br')).toHaveLength(1);
        expect(document.querySelectorAll('.gallery br')).toHaveLength(0);
    });

    it('does not throw when there is no gallery', () => {
        document.body.innerHTML = '<main>No gallery here</main>';

        expect(() => initGalleryCleanup(document)).not.toThrow();
    });
});
