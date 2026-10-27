/**
 * Alpine component backing the lite-YouTube facade on the video Bard set
 * (resources/views/sets/_video.antlers.html, UAMS-Web/uams-statamic-archive#224).
 *
 * A YouTube embed renders up front as a lightweight thumbnail + an accessible,
 * keyboard-operable play button — no heavy iframe. On activation it swaps in the
 * real player iframe (with autoplay) and moves focus to it. Without JS the
 * server-rendered `<noscript>` iframe is used instead, so the video always works.
 *
 * The thumbnail comes from YouTube's `i.ytimg.com/vi/<id>` convention (hot-linked
 * here); local thumbnail caching and playlist facades are tracked as follow-ups.
 *
 * Must be registered before `Alpine.start()` in site.js.
 */
export function registerLiteVideo(Alpine) {
    Alpine.data('liteVideo', () => ({
        // Whether the real player iframe has been swapped in.
        loaded: false,
        // The extracted YouTube video id (empty when not a recognizable YouTube url).
        videoId: '',

        // The component's own root element. Captured HERE because `init()` is the
        // one place `$el` is the wrapper: Alpine binds `$el` to whichever element's
        // expression is running, so inside `load()` — invoked from `@click` on the
        // play button — `$el` is that BUTTON. Measured: `button` in `load()`, the
        // wrapper `div` in `init()`.
        root: null,

        // Alpine lifecycle hook — derive the video id from the wrapper's data.
        init() {
            this.root = this.$el;
            this.videoId = this.extractYouTubeId(this.$el.dataset.videoUrl || '');
        },

        // Pull the 11-char video id out of a watch / short / embed url.
        extractYouTubeId(url) {
            const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]{11})/);

            return match ? match[1] : '';
        },

        // The facade thumbnail (empty when the id could not be derived).
        get thumbnail() {
            return this.videoId ? `https://i.ytimg.com/vi/${this.videoId}/hqdefault.jpg` : '';
        },

        // Swap in the real player and move focus to it (from the play button).
        //
        // The src is NOT set here. Under `@alpinejs/csp` Alpine cannot touch an
        // iframe by any route — `evaluator.js` refuses to evaluate an expression
        // ON one, and `parser.js` refuses to let an expression access a value that
        // IS one — so the player's src is rendered server-side by the partial and
        // this component only decides WHEN the element exists (UAMS-Web/uams-statamic-archive#2245).
        //
        // Focus is different: a component method is compiled JS rather than a
        // parsed Alpine expression, so those guards do not apply to the DOM work
        // below.
        //
        // It searches from the root captured in `init()`, NOT from `$el` - see the
        // note there. A query rooted at the play button can never find the player.
        // The wrapper carries `tabindex="-1"` so focus still lands on the player
        // region when the element itself cannot take it.
        load() {
            this.loaded = true;

            this.$nextTick(() => {
                const player = this.root.querySelector('iframe.lite-video__player');

                (player ?? this.root).focus();
            });
        },
    }));
}
