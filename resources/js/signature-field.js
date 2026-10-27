/**
 * Alpine component backing the public-form Signature fieldtype
 * (resources/views/vendor/statamic/forms/fields/signature.antlers.html, UAMS-Web/uams-statamic-archive#674).
 *
 * Captures a hand-drawn signature on a <canvas> via the signature_pad library —
 * the same dependency the CP SignatureFieldtype.vue uses — and writes the base64
 * PNG data URL into the field's hidden input, matching the value shape
 * App\Fieldtypes\Signature stores. signature_pad is dynamically imported the
 * first time a signature field initializes, so pages without one pay no JS cost.
 *
 * Accessibility fallback: pointer-free users switch to "type" mode and submit a
 * typed full name plus an attestation checkbox instead of drawing. The hidden
 * input then carries the typed name (a plain string, which Signature::process()
 * accepts alongside data URLs). A hidden input cannot carry HTML5 `required`, so
 * an empty required signature is caught server-side by the form's `required`
 * rule (Signature::process() nulls empty submissions).
 */

// ---
// Must run before `Alpine.start()` in site.js — Alpine ignores data names
// registered after its initial DOM scan.
// ---
export function registerSignatureField(Alpine) {
    // Factory returns a fresh state object per element so multiple signature
    // fields on one page stay isolated.
    Alpine.data('signatureField', () => ({
        // The signature_pad instance (null until the library lazy-loads).
        pad: null,
        // Active capture surface: 'draw' (canvas) or 'type' (accessible fallback).
        mode: 'draw',
        // Typed fallback state.
        typedName: '',
        attested: false,
        // Source of truth for the hidden input's submitted value.
        value: '',
        // This field's handle, read from the hidden input at init. Names the key in
        // the form scope that carries `value` across an `x-if` rebuild (UAMS-Web/uams-statamic-archive#2063).
        handle: '',

        // Alpine lifecycle hook — runs once when the component mounts.
        async init() {
            // ---
            // Two places a prior value can come from, and the order matters (UAMS-Web/uams-statamic-archive#2063).
            //
            // The hidden input carries a server-rendered value, which is what survives
            // a validation round-trip. But this field renders inside the conditions
            // partial's `<template x-if>`, which DESTROYS its subtree rather than
            // hiding it — so when a condition goes false and true again the input is
            // re-created from the original server render, empty, and everything the
            // user entered is gone with it.
            //
            // The form's own `x-data` scope is the outer component and survives that
            // teardown, so it is mirrored to below and preferred here. The field's
            // handle is read off the input's `name` rather than passed in, which keeps
            // `x-data="signatureField()"` argument-free for the CSP parser.
            // ---
            this.handle = (this.$refs.input && this.$refs.input.name) || '';

            const preserved = this.handle && typeof this[this.handle] === 'string'
                ? this[this.handle]
                : '';
            const existing = preserved !== ''
                ? preserved
                : ((this.$refs.input && this.$refs.input.value) || '');

            this.value = existing;

            // Mirror every later change out to the surviving form scope. Registered
            // before the `await` below so a rebuild cannot race the lazy import.
            this.$watch('value', (next) => {
                if (this.handle) {
                    this[this.handle] = next;
                }
            });
            if (existing && existing.indexOf('data:image/') !== 0) {
                this.mode = 'type';
                this.typedName = existing;
                this.attested = true;
            }

            const canvas = this.$refs.canvas;
            if (!canvas) {
                return;
            }

            // Lazy-load the drawing library only when a signature field is present.
            const { default: SignaturePad } = await import('signature_pad');
            this.pad = new SignaturePad(canvas, {
                penColor: '#000000',
                backgroundColor: 'rgba(255, 255, 255, 0)',
            });

            // Size the backing store for the current DPI before any drawing.
            this.resizeCanvas();

            // Best-effort visual restore of a previously drawn signature.
            if (existing.indexOf('data:image/') === 0) {
                this.pad.fromDataURL(existing);
            }

            // Persist the drawing to the hidden input each time a stroke ends.
            this.pad.onEnd = () => this.captureDrawing();

            // Rescale (preserving the drawing) when the viewport changes.
            this.onResize = () => this.resizeCanvas();
            window.addEventListener('resize', this.onResize);
        },

        // Alpine lifecycle hook — clean up the window listener on teardown.
        destroy() {
            if (this.onResize) {
                window.removeEventListener('resize', this.onResize);
            }
        },

        /**
         * Scale the canvas backing store to the device pixel ratio for crisp
         * strokes, preserving any existing drawing across the resize.
         */
        resizeCanvas() {
            const canvas = this.$refs.canvas;
            if (!canvas || !this.pad) {
                return;
            }

            // Snapshot the strokes so the resize (which clears the canvas) is lossless.
            const data = this.pad.toData();
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            this.pad.clear();

            if (data.length) {
                this.pad.fromData(data);
                this.captureDrawing();
            }
        },

        /**
         * Copy the drawn signature (or an empty string) into the submitted value.
         */
        captureDrawing() {
            this.value = this.pad && !this.pad.isEmpty()
                ? this.pad.toDataURL('image/png')
                : '';
        },

        // Clear the canvas and the submitted value.
        clear() {
            if (this.pad) {
                this.pad.clear();
            }
            this.value = '';
        },

        // Switch to the accessible typed fallback.
        useTypeMode() {
            this.mode = 'type';
            this.syncTyped();
        },

        // Switch back to canvas capture.
        useDrawMode() {
            this.mode = 'draw';
            this.captureDrawing();
        },

        /**
         * The typed fallback only yields a value once a name is entered AND the
         * attestation box is checked, so an unattested name never submits.
         */
        syncTyped() {
            const name = this.typedName.trim();
            this.value = (this.attested && name) ? name : '';
        },
    }));
}
