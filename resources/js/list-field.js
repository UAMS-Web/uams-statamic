/**
 * Alpine component backing the public-form Lists fieldtype
 * (resources/views/vendor/statamic/forms/fields/list.antlers.html, UAMS-Web/uams-statamic-archive#2060).
 *
 * ## Why this file exists
 *
 * The partial used to declare its state inline:
 *
 *     x-data='{ items: [...], canAdd() {...}, add() {...}, remove(idx) {...} }'
 *
 * Under the `@alpinejs/csp` build (UAMS-Web/uams-statamic-archive#1859) an `x-data` attribute is parsed by a
 * restricted expression parser that accepts an object *literal* — property/value
 * pairs — and nothing else. Method shorthand is a syntax error, and the observable
 * result was:
 *
 *     CSP Parser Error: Expected PUNCTUATION ":" but got PUNCTUATION "("
 *     Undefined variable: items
 *     Undefined variable: canAdd
 *
 * With `x-data` failing to parse, `items` never existed, so the `x-for` rendered
 * **zero rows**: the field showed no inputs at all and submitted nothing. Registering
 * the component here moves the function bodies into module scope, where ordinary JS
 * is allowed, and leaves the attribute a plain call the CSP parser can read.
 *
 * ## Why the state is passed as arguments
 *
 * Same reason as `replicatorSet` (UAMS-Web/uams-statamic-archive#2056): Alpine resolves an `x-model` identifier by
 * walking up the scope stack, so a property this factory does not declare would be
 * found on (or created on) an ancestor. Seeding through the factory means each field
 * owns its own `items`, which matters when a form renders two list fields.
 */

// ---
// Must run before `Alpine.start()` in site.js — Alpine ignores data names registered
// after its initial DOM scan.
// ---
export function registerListField(Alpine) {
    Alpine.data('listField', (items = [''], maxItems = 999, minItems = 1, handle = '') => ({
        // The list's rows. Declared here so each field instance owns its own array.
        items: Array.isArray(items) && items.length > 0 ? items : [''],
        // Client-side gates mirroring Lists::rules(); server-side validation is authoritative.
        maxItems,
        minItems,
        // The field handle, used to address the surviving form scope. Passed in
        // rather than read off a row's `name`, because the rows are rendered by
        // `x-for` and none of them exists yet when `init()` runs.
        handle,

        /**
         * Alpine lifecycle hook — runs once when the component mounts.
         */
        init() {
            // ---
            // This field renders inside the conditions partial's `<template x-if>`,
            // which DESTROYS its subtree rather than hiding it (UAMS-Web/uams-statamic-archive#2063). When a
            // condition goes false and true again the component is re-created from
            // the server-rendered `initial_items_json` — so every row the user added
            // and every value they typed is gone, and nothing tells them.
            //
            // Measured for this field before it was fixed (UAMS-Web/uams-statamic-archive#2232): two rows entered,
            // condition driven false then true, and the submission read back as
            // `[""]`. That is worse than the signature case the same mechanism caused,
            // because here the rows ARE the submitted value rather than a mode.
            //
            // The form's own `x-data` scope is the outer component and survives the
            // teardown, so it is preferred here and mirrored to below.
            // ---
            const preserved = this.handle && Array.isArray(this[this.handle])
                ? this[this.handle]
                : null;

            if (preserved !== null && preserved.length > 0) {
                this.items = preserved.slice();
            }

            // Mirror every later change out to the surviving form scope. A deep watch,
            // because editing a row mutates the array in place rather than replacing it.
            this.$watch('items', (next) => {
                if (this.handle) {
                    this[this.handle] = Array.isArray(next) ? next.slice() : next;
                }
            });
        },

        /** Whether another row may be added. */
        canAdd() {
            return this.items.length < this.maxItems;
        },

        /** Whether a row may be removed without dropping below the floor. */
        canRemove() {
            return this.items.length > this.minItems;
        },

        /** Append an empty row, respecting the ceiling. */
        add() {
            if (this.canAdd()) {
                this.items.push('');
            }
        },

        /** Remove the row at `idx`, respecting the floor. */
        remove(idx) {
            if (this.canRemove()) {
                this.items.splice(idx, 1);
            }
        },
    }));
}
