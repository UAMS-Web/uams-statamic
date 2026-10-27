/**
 * Public-form total pricing engine (issue UAMS-Web/uams-statamic-archive#488).
 *
 * The Gravity Forms `total` field shows the running order total. This engine
 * consumes the Total seam that the product (UAMS-Web/uams-statamic-archive#300) and option (UAMS-Web/uams-statamic-archive#311) fieldtypes
 * establish: every product/option line writes its subtotal to a hidden
 * `[data-product-line-total-input]` and dispatches a bubbling
 * `product:price-changed` event on the <form>. For each Total field this engine:
 *
 *   1. Listens for `product:price-changed` on the parent <form>.
 *   2. Sums the `value` of every `[data-product-line-total-input]` in the form
 *      (products and options share the hook, so they sum uniformly).
 *   3. Writes the sum to the visible `[data-total-display-text]` and to the
 *      hidden `[data-total-input]` so it round-trips on submission.
 *
 * It also computes once on init. The order between this engine and the
 * product/option engines is irrelevant: if a line engine inits first, its
 * hidden input is already populated when this init reads it; if it inits later,
 * its `product:price-changed` event is caught by the listener registered here.
 *
 * Like the sibling pricing engines, it binds plain DOM listeners (composite
 * subfields drop `x-model`) but registers through `lib/alpine-engine.js` for the
 * shared lifecycle and idempotency guard.
 */

import { registerAlpineEngine } from './lib/alpine-engine.js';

/** Selector for total-field wrappers emitted by `total.antlers.html`. */
const TOTAL_FIELD_SELECTOR = '[data-total-field]';

/** Shared hook every product/option line-subtotal input carries. */
const LINE_TOTAL_SELECTOR = '[data-product-line-total-input]';

/**
 * Format a number as a US-dollar string, keeping the sign outside the symbol so
 * a net credit renders as `-$25.00` (products/options permit negative prices).
 *
 * @param {number} amount
 * @returns {string}
 */
function formatCurrency(amount) {
	const value = Number.isFinite(amount) ? amount : 0;
	const sign = value < 0 ? '-' : '';
	return `${sign}$${Math.abs(value).toFixed(2)}`;
}

/**
 * Wire one total field. Sums every line subtotal in the form on init and on
 * every `product:price-changed`.
 *
 * @param {HTMLElement} field
 */
function initTotalField(field) {
	const form = field.closest('form');
	if (!form) return;

	const displayText = field.querySelector('[data-total-display-text]');
	const input = field.querySelector('[data-total-input]');

	/**
	 * Sum every product/option line subtotal in the form and write the result to
	 * the visible display and the hidden input.
	 */
	const recompute = () => {
		let sum = 0;
		form.querySelectorAll(LINE_TOTAL_SELECTOR).forEach((el) => {
			const lineTotal = parseFloat(el.value || '0');
			if (Number.isFinite(lineTotal)) sum += lineTotal;
		});
		sum = Math.round(sum * 100) / 100;

		if (displayText) displayText.textContent = formatCurrency(sum);
		if (input) input.value = sum.toFixed(2);
	};

	form.addEventListener('product:price-changed', recompute);

	// Seed the total from whatever line subtotals are already present.
	recompute();
}

/**
 * Public entry. Lifecycle (wait for Alpine, scan, idempotent flag, per-element
 * init) lives in `lib/alpine-engine.js` and is shared with sibling engines.
 */
export function registerTotalPricingEngine() {
	registerAlpineEngine({
		selector: TOTAL_FIELD_SELECTOR,
		boundAttr: 'totalPricingBound',
		init: initTotalField,
	});
}
