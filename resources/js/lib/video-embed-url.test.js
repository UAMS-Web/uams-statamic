import { describe, expect, it } from 'vitest';
import { videoEmbedUrl } from './video-embed-url.js';

// Each expected value is what the `embedUrl` computed in
// `VideoFieldtype.vue` returned for the same input before it moved here (#391),
// so the table pins the move as behavior-preserving.
describe('videoEmbedUrl', () => {
    it.each([
        ['no value', null, ''],
        ['an empty value', '', ''],
        ['a YouTube watch link', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        ['a YouTube shorts link', 'https://www.youtube.com/shorts/abc123', 'https://www.youtube.com/embed/abc123'],
        ['a shorts link with a query', 'https://youtube.com/shorts/abc123?feature=share', 'https://youtube.com/embed/abc123?feature=share'],
        ['a youtu.be link', 'https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        ['a youtu.be link with a query', 'https://youtu.be/dQw4w9WgXcQ?si=xyz&t=10', 'https://www.youtube.com/embed/dQw4w9WgXcQ?si=xyz&t=10'],
        ['an embed link', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        ['a Vimeo link', 'https://vimeo.com/123456', 'https://player.vimeo.com/video/123456'],
        ['an unlisted Vimeo link', 'https://vimeo.com/123456/abcdef', 'https://player.vimeo.com/video/123456?h=abcdef'],
        ['an unlisted Vimeo link with a query', 'https://vimeo.com/123456/abcdef?share=copy&t=5', 'https://player.vimeo.com/video/123456?h=abcdef&share=copy&t=5'],
        [
            'a Vimeo progressive redirect',
            'https://player.vimeo.com/progressive_redirect/playback/1/rendition/720p/file.mp4?loc=external&signature=abc',
            'https://player.vimeo.com/progressive_redirect/playback/1/rendition/720p/file.mp4?loc=external&signature=abc',
        ],
        ['a direct file link', 'https://example.box.com/s/file.mp4', 'https://example.box.com/s/file.mp4'],
    ])('rewrites %s', (_label, input, expected) => {
        expect(videoEmbedUrl(input)).toBe(expected);
    });

    it.each([
        ['one parameter', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42', 'https://www.youtube.com/embed/dQw4w9WgXcQ?t=42'],
        [
            'several parameters',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42&list=PL1&index=3',
            'https://www.youtube.com/embed/dQw4w9WgXcQ?t=42&list=PL1&index=3',
        ],
        ['several parameters on youtu.be', 'https://youtu.be/dQw4w9WgXcQ&t=10&list=PL1', 'https://www.youtube.com/embed/dQw4w9WgXcQ?t=10&list=PL1'],
        ['several parameters on another host', 'https://example.com/a&b&c', 'https://example.com/a?b&c'],
    ])('starts the query at the first & only, for %s', (_label, input, expected) => {
        expect(videoEmbedUrl(input)).toBe(expected);
    });

    it('leaves every & alone when the URL already has a query', () => {
        expect(videoEmbedUrl('https://example.com/a&b?c&d')).toBe('https://example.com/a&b?c&d');
    });

    it('keeps parameter text exactly as entered', () => {
        // URLSearchParams would write the space as `+` and the bare key as `y=`
        expect(videoEmbedUrl('https://youtu.be/ID&si=a b&y')).toBe('https://www.youtube.com/embed/ID?si=a b&y');
    });
});
