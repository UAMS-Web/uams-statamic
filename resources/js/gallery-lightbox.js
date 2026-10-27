/**
 * Alpine component backing the Image Gallery Bard set's accessible lightbox
 * (resources/views/sets/_gallery.antlers.html, UAMS-Web/uams-statamic-archive#188).
 *
 * The set server-renders a responsive grid of image triggers, each carrying the
 * full-size src, alt, and caption in `data-gallery-*` attributes. This component
 * reads those into an ordered list on init, then drives an overlay lightbox with
 * prev/next (wrapping), keyboard (Esc / arrows) and swipe navigation, a focus
 * trap, and focus return to the activating trigger on close — no jQuery.
 *
 * Must be registered before `Alpine.start()` in site.js.
 */
export function registerGalleryLightbox(Alpine) {
    Alpine.data('galleryLightbox', () => ({
        // Whether the lightbox overlay is open.
        open: false,
        // Index of the currently-shown image.
        current: 0,
        // Ordered { src, alt, caption } read from the grid triggers.
        images: [],
        // The trigger that opened the lightbox, so focus can return to it.
        lastTrigger: null,
        // X coordinate where a touch began, for swipe detection.
        touchStartX: null,

        // Alpine lifecycle hook — collect the gallery images once on mount.
        init() {
            this.images = this.triggers().map((el) => ({
                src: el.dataset.galleryFull || '',
                alt: el.dataset.galleryAlt || '',
                caption: el.dataset.galleryCaption || '',
            }));
        },

        // The grid's image triggers, in document (gallery) order.
        //
        // `$root`, NOT `$el`. Under `@alpinejs/csp` a method invoked from a
        // directive expression sees `$el` as the element carrying that directive,
        // so `this.$el` here is the clicked BUTTON rather than the component root
        // — and a button contains no `[data-gallery-item]`, so the query returned
        // an empty list. `show()` then found `indexOf(trigger) === -1` and returned
        // without opening anything, with no error to read (UAMS-Web/uams-statamic-archive#2246). `init()` masked
        // it: there `$el` IS the root, so `images` populated correctly and only the
        // click path was dead. `$root` is the component root from either context.
        triggers() {
            return Array.from(this.$root.querySelectorAll('[data-gallery-item]'));
        },

        // Open the lightbox at the image belonging to the given trigger.
        show(trigger) {
            const index = this.triggers().indexOf(trigger);

            if (index < 0 || this.images.length === 0) {
                return;
            }

            this.current = index;
            this.lastTrigger = trigger;
            this.open = true;

            // Move focus into the dialog once it is rendered.
            this.$nextTick(() => {
                if (this.$refs.close) {
                    this.$refs.close.focus();
                }
            });
        },

        // Close the lightbox and return focus to the activating trigger.
        close() {
            this.open = false;

            if (this.lastTrigger && typeof this.lastTrigger.focus === 'function') {
                this.lastTrigger.focus();
            }
        },

        // Advance to the next image, wrapping past the end.
        next() {
            if (this.images.length > 0) {
                this.current = (this.current + 1) % this.images.length;
            }
        },

        // Go to the previous image, wrapping before the start.
        prev() {
            if (this.images.length > 0) {
                this.current = (this.current - 1 + this.images.length) % this.images.length;
            }
        },

        // The image currently shown (a safe empty shape when there are none).
        get currentImage() {
            return this.images[this.current] || { src: '', alt: '', caption: '' };
        },

        // Record where a swipe began.
        onTouchStart(event) {
            this.touchStartX = event.changedTouches[0].clientX;
        },

        // Navigate on a horizontal swipe past a small threshold.
        onTouchEnd(event) {
            if (this.touchStartX === null) {
                return;
            }

            const deltaX = event.changedTouches[0].clientX - this.touchStartX;

            if (Math.abs(deltaX) > 40) {
                if (deltaX < 0) {
                    this.next();
                } else {
                    this.prev();
                }
            }

            this.touchStartX = null;
        },

        // Keep Tab focus cycling within the dialog while it is open.
        trapFocus(event) {
            if (!this.open || !this.$refs.dialog) {
                return;
            }

            // Exclude controls hidden by x-show (prev/next on a single-image
            // gallery) so focus can't escape onto a display:none button.
            const focusable = Array.from(this.$refs.dialog.querySelectorAll('button'))
                .filter((button) => button.offsetParent !== null);

            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }));
}
