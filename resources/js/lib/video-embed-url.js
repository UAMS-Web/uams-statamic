/**
 * Build the preview `<iframe>` URL for a YouTube or Vimeo link entered in the
 * video fieldtype.
 *
 * The rewrites are core Statamic's `VideoFieldtype.vue` `embedUrl`, moved here
 * so they can be tested: a watch, shorts or youtu.be link becomes a
 * `youtube.com/embed/` link, and a Vimeo page link becomes a
 * `player.vimeo.com/video/` link carrying its unlisted hash as `h`. Any other
 * value is returned as given.
 *
 * Rewriting a YouTube link moves the video id from the query string into the
 * path, so `watch?v=ID&t=42` becomes `embed/ID&t=42`, which has parameters but
 * no `?`. The last step makes the first `&` the start of the query string and
 * leaves the rest as separators (`embed/ID?t=42`). That is the only `&` that
 * should change, so it is done by position rather than with a string
 * `replace()`, which reads as incomplete escaping (#391).
 *
 * `URL` and `URLSearchParams` are not used: `URL` reads `ID&t=42` as part of
 * the path, and `URLSearchParams` re-serializes the parameters (a space becomes
 * `+`, a bare `&key` becomes `&key=`), so the preview would no longer load the
 * link exactly as the editor entered it.
 *
 * @param  {string|null|undefined}  value  The URL as entered in the field.
 * @return {string} The URL to load in the preview, or `''` for no value.
 */
export function videoEmbedUrl(value) {
    let embedUrl = value || '';

    // Point a YouTube watch or shorts link at the embed player
    if (embedUrl.includes('youtube')) {
        embedUrl = embedUrl.includes('shorts/')
            ? embedUrl.replace('shorts/', 'embed/')
            : embedUrl.replace('watch?v=', 'embed/');
    }

    // Expand a youtu.be short link to the embed player
    if (embedUrl.includes('youtu.be')) {
        embedUrl = embedUrl.replace('youtu.be', 'www.youtube.com/embed');
    }

    // Point a Vimeo page link at the player, carrying an unlisted hash as `h`
    if (embedUrl.includes('vimeo')) {
        embedUrl = embedUrl.replace('/vimeo.com', '/player.vimeo.com/video');

        if (!value.includes('progressive_redirect') && embedUrl.split('/').length > 5) {
            const hash = embedUrl.substr(embedUrl.lastIndexOf('/') + 1);
            embedUrl = embedUrl.substr(0, embedUrl.lastIndexOf('/')) + '?h=' + hash.replace('?', '&');
        }
    }

    // Start the query string at the first `&` when the rewrite left none
    const firstAmpersand = embedUrl.indexOf('&');
    if (firstAmpersand !== -1 && !embedUrl.includes('?')) {
        embedUrl = embedUrl.slice(0, firstAmpersand) + '?' + embedUrl.slice(firstAmpersand + 1);
    }

    return embedUrl;
}
