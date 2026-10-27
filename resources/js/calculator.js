/**
 * Alpine component backing the Calculator Bard set
 * (resources/views/sets/_calculator.antlers.html, UAMS-Web/uams-statamic-archive#2247).
 *
 * The set previously declared its state as an inline `x-data` object literal,
 * pre-populated by interpolating every variable handle into the expression. That
 * is a parse error under the `@alpinejs/csp` build, so the component never
 * existed and the calculator had no state at all — the same shape as the Lists
 * fieldtype defect in UAMS-Web/uams-statamic-archive#2060, and latent here only because no content uses the
 * set yet.
 *
 * The handles arrive as a FACTORY ARGUMENT rather than a `data-` attribute, and
 * that is load-bearing rather than stylistic. Each one is bound with
 * `x-model="<handle>"`, and Alpine resolves an `x-model` identifier by walking
 * UP the scope stack — so a property this component does not DECLARE is found on
 * (or created on) an ancestor, which is a shared value rather than this
 * calculator's own. A `data-` attribute could only be read in `init()`, by which
 * time the bindings have already resolved. `replicatorSet` carries the same
 * constraint for the same reason.
 *
 * Seeding every handle is mandatory under the CSP build (UAMS-Web/uams-statamic-archive#1859): its interpreter
 * resolves identifiers only from the Alpine scope stack and throws
 * `Undefined variable: <handle>` on a miss, with no `window.<handle>` fallback.
 *
 * Must be registered before `Alpine.start()` in site.js.
 */
export function registerCalculator(Alpine) {
    Alpine.data('calculator', (handles = []) => ({
        // ---
        // Every variable handle, and every split sub-handle, declared as its own
        // property so `x-model="<handle>"` binds here rather than on an ancestor.
        // Declared by the factory, never assigned in `init()` — see above.
        //
        // Handles are guaranteed ASCII identifiers by the `ascii_identifier`
        // validation rule, so they need no escaping or normalization here.
        // ---
        ...Object.fromEntries(handles.map((handle) => [handle, ''])),
    }));
}
