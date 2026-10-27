/**
 * Strip stray `<br>` elements out of imported WordPress `[gallery]` markup.
 *
 * The classic WordPress gallery shortcode emits `<br style="clear:both">`
 * between rows; the UAMS 2020 grid clears its own floats, so the theme has
 * always removed them. This is the framework-free replacement for the jQuery
 * `$('.gallery').find('br').detach()` that lived in the retired
 * `source/app.js` (jQuery removal, UAMS-Web/uams-statamic-archive#1343).
 *
 * @param {Document|HTMLElement} [root]  The scope to clean (defaults to `document`).
 */
export function initGalleryCleanup(root = document) {
    root.querySelectorAll('.gallery br').forEach((br) => {
        br.remove();
    });
}
