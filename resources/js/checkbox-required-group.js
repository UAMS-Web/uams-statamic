/**
 * Alpine component for checkbox groups with a "required" validation rule.
 *
 * HTML5 `required` on <input type="checkbox"> demands that specific box be
 * checked, so emitting it on every option in a group blocks submission until
 * every box is ticked. This component clears `required` from the whole group
 * as soon as any one box is checked and restores it when none are, giving
 * the standard "at least one must be selected" behavior.
 *
 * Used by resources/views/vendor/statamic/forms/fields/checkboxes.antlers.html
 * when the field has the `required` rule.
 */

// ---
// Must run before `Alpine.start()` in site.js — Alpine ignores data names
// registered after its initial DOM scan.
// ---
export function registerCheckboxRequiredGroup(Alpine) {
    // ---
    // Factory returns a fresh state object per element so multiple groups
    // on a page stay isolated.
    // ---
    Alpine.data('checkboxRequiredGroup', () => ({
        // Alpine lifecycle hook — runs once when the component mounts.
        init() {
            // Handle the prefilled case (e.g., re-render after a server validation error).
            this.sync();
        },
        // Reconciles `required` across the group; also bound to `@change` on the wrapper.
        sync() {
            // Scope to `this.$el` so sibling groups in other forms don't interfere.
            const boxes = this.$el.querySelectorAll('input[type="checkbox"]');

            // True when the group's "at least one" requirement is already satisfied.
            const anyChecked = Array.from(boxes).some((cb) => cb.checked);

            // Apply the same required state to every box in the group.
            boxes.forEach((cb) => {
                // ---
                // Assign the IDL property so one statement toggles both directions
                // (vs choosing between setAttribute / removeAttribute).
                // ---
                cb.required = !anyChecked;
            });
        },
    }));
}
