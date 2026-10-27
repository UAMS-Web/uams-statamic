/**
 * Cross-calculator value sync engine.
 *
 * Mirrors a variable's value across every calculator on the page whose
 * matching variable has opted in (`sync_across_calculators: true` in the
 * blueprint, surfaced as `data-sync-handle="<handle>"` on the input by
 * `partials/calculator_variable.antlers.html`). The mental model is the
 * legacy WP behavior — entering `weight_kg` in one calculator populates
 * every other `weight_kg` field on the same page — but keyed on the
 * Statamic variable handle instead of a CSS class, with both sides having
 * to opt in so two unrelated calculators that happen to use the same
 * handle name don't cross-pollute.
 *
 * How it wires up:
 *
 *   1. A per-page Alpine store (`calcSync`) holds the current shared value
 *      for each handle. Both directions of the mirror funnel through it,
 *      so adding or removing a calculator doesn't need point-to-point
 *      wiring.
 *   2. For each `[data-sync-handle]` input, resolve the containing
 *      calculator's Alpine scope (the Bard set's
 *      `[data-calculator-context]` wrapper or its enclosing `<form>`) and
 *      install two `Alpine.effect`s:
 *        - store → scope: writes the shared value into this scope when it
 *          differs.
 *        - scope → store: publishes this scope's value to the store when
 *          it differs.
 *   3. Equality checks on both sides break the write loop — if the shared
 *      value already matches the scope value, the effect short-circuits
 *      without writing, so Alpine doesn't re-trigger the other direction.
 *
 * Computed handles (e.g. `weight_kg` as the primary in weight-conversion,
 * derived from `weight_lb` + `weight_oz`) sync the same way as
 * user-entered handles: the calculation engine writes the computed value
 * into the scope, which trips the scope → store effect just like a typed
 * value would.
 */

// The CSP build, matching `site.js` — attribute expressions are AST-interpreted
// rather than compiled through `new Function()`, so `script-src` needs no
// `unsafe-eval` (UAMS-Web/uams-statamic-archive#1859). The constraint is on `x-*` attributes, not on modules.
import Alpine from '@alpinejs/csp';
import { registerAlpineEngine } from './lib/alpine-engine.js';

/** Selector for inputs that opted into cross-calculator sync. */
const SYNC_INPUT_SELECTOR = '[data-sync-handle]';

/**
 * Selector for the nearest ancestor that provides the reactive Alpine
 * scope. Matches the calculator container set up by the Bard set
 * (`[data-calculator-context]`) or any enclosing `<form>` — same contract
 * as the calculation engine uses.
 */
const SCOPE_CONTAINER_SELECTOR = '[data-calculator-context], form';

/** Name of the page-level Alpine store that holds the shared values. */
const STORE_NAME = 'calcSync';

/**
 * Lazily create the shared-values store. Idempotent — calling once per
 * input is fine; only the first call actually registers the store.
 */
function ensureStore() {
	if (!Alpine.store(STORE_NAME)) {
		Alpine.store(STORE_NAME, { values: {} });
	}
	return Alpine.store(STORE_NAME);
}

/**
 * Wire up one sync-enabled input. Resolves the input's calculator scope
 * and handle, then installs the two-way mirror effects against the shared
 * store.
 *
 * @param {HTMLElement} input
 */
function initSync(input) {
	const scopeEl = input.closest(SCOPE_CONTAINER_SELECTOR);
	if (!scopeEl) {
		console.warn(
			'[calculator-sync-engine] Sync input is not inside a <form> or [data-calculator-context]:',
			input,
		);
		return;
	}

	const handle = input.dataset.syncHandle;
	if (!handle) return;

	const scope = Alpine.$data(scopeEl);
	if (!scope) {
		console.warn('[calculator-sync-engine] No Alpine scope on the container for input:', input);
		return;
	}

	const store = ensureStore();

	// Seed the store from this scope's current value if the store hasn't
	// been touched for this handle yet. Otherwise the next store → scope
	// effect would overwrite the local value with `undefined` on first
	// run before any peer has published.
	if (store.values[handle] === undefined) {
		store.values[handle] = scope[handle];
	}

	// Store → scope: when the shared value changes (because a peer
	// calculator wrote to it), copy it into this scope. Effect-based so
	// it fires whenever the store changes, regardless of who wrote.
	// Does NOT subscribe to `scope[handle]` — earlier attempts tried to
	// short-circuit via `if (scope[handle] === shared) return;` but
	// reading scope here subscribes this effect to scope changes, which
	// races with the scope → store path: when the user types into this
	// scope, both effects fire in subscription order and the
	// store-driven one runs first with the stale store value, clobbering
	// the typed value before the scope → store path can publish it.
	// Writing `scope[handle] = shared` unconditionally is safe: Vue's
	// reactive set trap dedupes equal-value writes.
	Alpine.effect(() => {
		const shared = store.values[handle];
		if (shared === undefined) return;
		scope[handle] = shared;
	});

	// Scope → store: handled by direct DOM listeners on every input
	// bound to this handle rather than an Alpine effect. The effect-
	// based version had a symmetric race — both calculators' sync
	// effects on the same handle subscribed to both `scope[handle]` and
	// `store.values[handle]`, so a store write could fire a peer's
	// scope → store effect before that peer's store → scope effect had
	// updated the peer's scope, and the stale local "" would get
	// published back into the store. Direct DOM listening fires only on
	// real user input, sidesteps Alpine's effect-ordering, and never
	// publishes a stale scope read.
	//
	// We also need to publish computed writes (e.g. weight-conversion's
	// bidirectional `weight_kg = weight_imperial * 0.453592`), which
	// don't go through a DOM input event. For those, a narrower effect
	// watches only the calculation engine's writes by gating on whether
	// the input is calculated; the gate prevents this effect from also
	// reacting to incoming sync writes (those land in scope as a
	// side-effect of the store → scope effect above and would otherwise
	// loop the value straight back to the store).
	const isCalculated = !!input.dataset.calculationType;
	if (isCalculated) {
		Alpine.effect(() => {
			const local = scope[handle];
			if (store.values[handle] === local) return;
			store.values[handle] = local;
		});
	} else {
		input.addEventListener('input', () => {
			const value = input.value;
			if (store.values[handle] !== value) {
				store.values[handle] = value;
			}
		});
	}
}

/**
 * Public entry. Lifecycle is shared with sibling engines via
 * `lib/alpine-engine.js`.
 */
export function registerCalculatorSyncEngine() {
	registerAlpineEngine({
		selector: SYNC_INPUT_SELECTOR,
		boundAttr: 'calculatorSyncBound',
		init: initSync,
	});
}
