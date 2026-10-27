/**
 * Calculator reset-button engine.
 *
 * Each `[data-calculator-reset]` button clears variable inputs by writing
 * `''` to the matching scope keys — engines react to the value changes
 * through their normal Alpine effects, so computed outputs blank out and
 * splits blank out without any engine-specific knowledge here. Synced
 * inputs (handles published by `[data-sync-handle]`) propagate through
 * the cross-calculator sync engine: clearing a synced handle in one
 * scope clears it in every other scope that publishes the same handle.
 *
 * The button's value picks the reset scope:
 *
 *   - `data-calculator-reset="local"` clears every input in this
 *     calculator's scope. Synced handles propagate page-wide through the
 *     sync engine; non-synced inputs in other calculators are left alone.
 *
 *   - `data-calculator-reset="page"` walks every calculator scope on the
 *     page and clears each one's inputs. Use to clear handles that this
 *     calculator doesn't itself reference but other calculators do.
 *
 * Reading the key list off the DOM (rather than iterating
 * `Object.keys(scope)` on Alpine's reactive proxy) avoids edge cases
 * where the proxy's `ownKeys` trap may behave differently across Alpine
 * versions, and matches what x-model actually binds against.
 *
 * Engine-internal flags whose names start with `_calc_` are never touched
 * — they'll naturally be cleared the next time the user focuses any
 * input.
 */

// The CSP build, matching `site.js` — attribute expressions are AST-interpreted
// rather than compiled through `new Function()`, so `script-src` needs no
// `unsafe-eval` (UAMS-Web/uams-statamic-archive#1859). The constraint is on `x-*` attributes, not on modules.
import Alpine from '@alpinejs/csp';
import { registerAlpineEngine } from './lib/alpine-engine.js';

const RESET_BUTTON_SELECTOR = '[data-calculator-reset]';
const SCOPE_CONTAINER_SELECTOR = '[data-calculator-context], form';

/**
 * Collect every scope that contains a reset button. Used by the page
 * reset to iterate every calculator scope.
 *
 * Restricting to scopes reachable from a reset button — rather than
 * `[data-calculator-context]` directly — ensures a calculator embedded
 * straight into a `<form>` is still covered, and prevents the page reset
 * from touching unrelated forms on the same page.
 */
function collectCalculatorScopes() {
	const scopes = new Set();
	document.querySelectorAll(RESET_BUTTON_SELECTOR).forEach((btn) => {
		const scope = btn.closest(SCOPE_CONTAINER_SELECTOR);
		if (scope) scopes.add(scope);
	});
	return scopes;
}

/** Clear every variable input in one scope. */
function clearInputsInScope(scopeEl) {
	const scope = Alpine.$data(scopeEl);
	if (!scope) return;
	scopeEl.querySelectorAll('input[name]').forEach((input) => {
		const name = input.getAttribute('name');
		if (!name || name.startsWith('_calc_')) return;
		if (scope[name] !== '') {
			scope[name] = '';
		}
	});
}

function initResetButton(button) {
	const scopeEl = button.closest(SCOPE_CONTAINER_SELECTOR);
	if (!scopeEl) {
		console.warn('[calculator-reset-engine] Reset button is not inside a calculator scope:', button);
		return;
	}
	const mode = button.dataset.calculatorReset === 'page' ? 'page' : 'local';

	button.addEventListener('click', () => {
		if (mode === 'page') {
			collectCalculatorScopes().forEach((el) => clearInputsInScope(el));
		} else {
			clearInputsInScope(scopeEl);
		}
	});
}

export function registerCalculatorResetEngine() {
	registerAlpineEngine({
		selector: RESET_BUTTON_SELECTOR,
		boundAttr: 'calculatorResetBound',
		init: initResetButton,
	});
}
