/**
 * Shared registration helper for per-element Alpine engines.
 *
 * Each public-form "engine" — the `calculation_*` reactivity at
 * `resources/js/calculation-engine.js`, the PersonName display_format
 * reactivity at `resources/js/person-name-engine.js`, and any future
 * additions — follows the same lifecycle:
 *
 *   1. Wait for Alpine to finish booting (so the form's `x-data` proxy is
 *      reachable via `Alpine.$data(form)`).
 *   2. Scan the page for elements matching a selector.
 *   3. Skip elements that have already been wired up (idempotent re-runs).
 *   4. Mark each wired element with a flag attribute and hand it to a
 *      per-engine `init(element)` callback.
 *
 * Centralizing the lifecycle here means a new engine is roughly fifteen
 * lines: an `initElement` function plus one `registerAlpineEngine(...)`
 * call. Bug fixes (e.g., a new Alpine bootstrap event in a future
 * Statamic / Alpine release) land in one place.
 */

/**
 * @typedef {Object} RegisterAlpineEngineOptions
 * @property {string} selector
 *   CSS selector matching elements this engine should bind to.
 * @property {string} boundAttr
 *   Camel-cased `dataset` key used as the idempotency flag. Becomes the
 *   `data-<kebab>` HTML attribute. E.g., `"calculationBound"` →
 *   `data-calculation-bound="1"` once the element is wired.
 * @property {(el: Element) => void} init
 *   Engine-specific binding logic. Called once per matched element after
 *   Alpine has initialized.
 */

/**
 * Register a per-element Alpine engine. Safe to call from a module's top
 * level; deferred until Alpine signals it is ready.
 *
 * @param {RegisterAlpineEngineOptions} options
 */
export function registerAlpineEngine({ selector, boundAttr, init }) {
	const bindAll = () => {
		document.querySelectorAll(selector).forEach((el) => {
			if (el.dataset[boundAttr] === '1') return;
			el.dataset[boundAttr] = '1';
			init(el);
		});
	};

	// If the page is still parsing, wait for the standard Alpine lifecycle
	// event. If the document is already interactive AND Alpine has booted
	// (window.Alpine.version is set after start()), queue the bind for the
	// next microtask. The third branch is the same as the first but
	// covers the edge case where Alpine hasn't booted yet during a late
	// register call.
	if (document.readyState === 'loading') {
		document.addEventListener('alpine:initialized', bindAll, { once: true });
	} else if (window.Alpine && window.Alpine.version) {
		queueMicrotask(bindAll);
	} else {
		document.addEventListener('alpine:initialized', bindAll, { once: true });
	}
}
