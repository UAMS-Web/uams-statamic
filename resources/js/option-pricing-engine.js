/**
 * Public-form option pricing engine.
 *
 * Mirrors the CP-side subtotal feedback that `OptionFieldtype.vue` provides,
 * but for the public form. For each rendered option field it:
 *
 *   1. Reads the active price — the selected `<option>`/radio's `data-price`
 *      (single modes), or the sum of every checked checkbox's `data-price`
 *      (checkbox mode).
 *   2. Writes that subtotal to the hidden `[line_total]` input (so it
 *      round-trips on submission) and to the visible subtotal text.
 *   3. Dispatches a bubbling `product:price-changed` event on the parent
 *      <form>, carrying `{ handle, lineTotal }`.
 *
 * ## Total pipeline contract (issue UAMS-Web/uams-statamic-archive#488)
 *
 * The Gravity Forms option field is a price modifier on a parent product that
 * feeds the order Total. This engine reuses the *same* Total seam the product
 * fieldtype (UAMS-Web/uams-statamic-archive#300) established: the hidden line-total input also carries the
 * shared `[data-product-line-total-input]` hook, and the change signal is the
 * shared `product:price-changed` event. A Total fieldtype therefore sums
 * products and options uniformly — re-reading every
 * `[data-product-line-total-input]` on the form when the event fires — without
 * needing option-specific knowledge. The engine fires once per field on init,
 * so a Total can compute a correct starting total before any user interaction.
 *
 * Like `product-pricing-engine.js`, composite subfields drop `x-model`, so this
 * binds plain DOM listeners rather than reaching into an Alpine scope. It still
 * registers through `lib/alpine-engine.js` for the shared lifecycle and
 * idempotency guard.
 */

import { registerAlpineEngine } from './lib/alpine-engine.js';

/** Selector for option-field wrappers emitted by `option.antlers.html`. */
const OPTION_FIELD_SELECTOR = '[data-option-field]';

/**
 * Format a number as a US-dollar string, keeping the sign outside the symbol
 * so negative options render as `-$25.00` (Gravity Forms permits negative
 * option prices — credits / discounts).
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
 * Wire one option field. Resolves its DOM hooks, then recomputes the line
 * subtotal on every selection change (and once immediately).
 *
 * @param {HTMLElement} field
 */
function initOptionField(field) {
	const form = field.closest('form');
	const handle = field.dataset.optionHandle || '';
	const mode = field.dataset.optionMode || 'select';

	const lineTotalInput = field.querySelector('[data-option-line-total-input]');
	const lineTotalText = field.querySelector('[data-option-line-total-text]');
	const select = field.querySelector('[data-option-select]');
	const inputs = field.querySelectorAll('[data-option-input]');

	/**
	 * The active price of the current selection. Drop-down reads the selected
	 * `<option>`'s `data-price`; radio reads the checked radio; checkbox sums
	 * every checked input.
	 *
	 * @returns {number}
	 */
	const activePrice = () => {
		if (mode === 'select') {
			if (!select) return 0;
			const option = select.options[select.selectedIndex];
			return option ? parseFloat(option.dataset.price || '0') || 0 : 0;
		}

		let sum = 0;
		inputs.forEach((input) => {
			if (input.checked) {
				sum += parseFloat(input.dataset.price || '0') || 0;
			}
		});
		return sum;
	};

	/**
	 * Recompute the line subtotal, write it to the hidden input + visible text,
	 * and notify the form (and any Total field) of the change.
	 */
	const recompute = () => {
		const lineTotal = Math.round(activePrice() * 100) / 100;

		if (lineTotalInput) lineTotalInput.value = lineTotal.toFixed(2);
		if (lineTotalText) lineTotalText.textContent = formatCurrency(lineTotal);

		if (form) {
			form.dispatchEvent(
				new CustomEvent('product:price-changed', {
					bubbles: true,
					detail: { handle, lineTotal },
				}),
			);
		}
	};

	if (select) {
		select.addEventListener('change', recompute);
	}

	inputs.forEach((input) => {
		input.addEventListener('change', recompute);
	});

	// Seed the subtotal (and the Total pipeline) before any interaction.
	recompute();
}

/**
 * Public entry. Lifecycle (wait for Alpine, scan, idempotent flag, per-element
 * init) lives in `lib/alpine-engine.js` and is shared with sibling engines.
 */
export function registerOptionPricingEngine() {
	registerAlpineEngine({
		selector: OPTION_FIELD_SELECTOR,
		boundAttr: 'optionPricingBound',
		init: initOptionField,
	});
}
