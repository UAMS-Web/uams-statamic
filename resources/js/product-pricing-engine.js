/**
 * Public-form product pricing engine.
 *
 * Mirrors the CP-side line-subtotal feedback that `ProductFieldtype.vue`
 * provides, but for the public form. For each rendered product field it:
 *
 *   1. Reads the active unit price — the single-product base price, or the
 *      selected `<option>`'s `data-price` in multiple-choice mode.
 *   2. Multiplies by the quantity input to get the line subtotal.
 *   3. Writes that subtotal to the hidden `[line_total]` input (so it
 *      round-trips on submission) and to the visible subtotal text.
 *   4. Dispatches a bubbling `product:price-changed` event on the parent
 *      <form>, carrying `{ handle, lineTotal }`.
 *
 * ## Total pipeline contract (issue UAMS-Web/uams-statamic-archive#488)
 *
 * A Total fieldtype sums the order by listening for `product:price-changed`
 * on the form and re-reading every `[data-product-line-total-input]` within
 * it (each input's `value` is the latest line subtotal, and its `name`
 * resolves the owning product handle). The event is the change signal; the
 * hidden inputs are the source of truth for the running sum. The engine also
 * fires once per field on init, so a Total can compute a correct starting
 * total before any user interaction.
 *
 * Unlike the calculation / person-name engines, this one needs no Alpine
 * scope — composite subfields drop `x-model` (see the forms `fields.antlers`
 * header note), so it binds plain DOM listeners instead. It still registers
 * through `lib/alpine-engine.js` purely for the shared lifecycle and
 * idempotency guard.
 */

import { registerAlpineEngine } from './lib/alpine-engine.js';

/** Selector for product-field wrappers emitted by `product.antlers.html`. */
const PRODUCT_FIELD_SELECTOR = '[data-product-field]';

/**
 * Format a number as a US-dollar string, keeping the sign outside the symbol
 * so negative line items render as `-$2,500.00` (Gravity Forms permits
 * negative product prices — e.g. credits on the IDHI e-Link quote form).
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
 * Wire one product field. Resolves its DOM hooks, then recomputes the line
 * subtotal on every quantity / option change (and once immediately).
 *
 * @param {HTMLElement} field
 */
function initProductField(field) {
	const form = field.closest('form');
	const handle = field.dataset.productHandle || '';
	const mode = field.dataset.productMode === 'multiple' ? 'multiple' : 'single';

	const quantityInput = field.querySelector('[data-product-quantity]');
	const lineTotalInput = field.querySelector('[data-product-line-total-input]');
	const lineTotalText = field.querySelector('[data-product-line-total-text]');
	const select = field.querySelector('[data-product-select]');
	const singlePriceEl = field.querySelector('[data-product-single-price]');

	/**
	 * The unit price of the active selection. Multiple-choice reads the
	 * selected option's `data-price`; single reads the base price element.
	 *
	 * @returns {number}
	 */
	const unitPrice = () => {
		if (mode === 'multiple') {
			if (!select) return 0;
			const option = select.options[select.selectedIndex];
			return option ? parseFloat(option.dataset.price || '0') || 0 : 0;
		}

		return singlePriceEl ? parseFloat(singlePriceEl.dataset.price || '0') || 0 : 0;
	};

	/**
	 * Recompute the line subtotal, write it to the hidden input + visible
	 * text, and notify the form (and any Total field) of the change.
	 */
	const recompute = () => {
		const rawQuantity = quantityInput ? parseInt(quantityInput.value, 10) : 1;
		const quantity = Number.isFinite(rawQuantity) && rawQuantity > 0 ? rawQuantity : 0;
		const lineTotal = Math.round(unitPrice() * quantity * 100) / 100;

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

	if (quantityInput) {
		quantityInput.addEventListener('input', recompute);
		quantityInput.addEventListener('change', recompute);
	}

	if (select) {
		select.addEventListener('change', recompute);
	}

	// Seed the subtotal (and the Total pipeline) before any interaction.
	recompute();
}

/**
 * Public entry. Lifecycle (wait for Alpine, scan, idempotent flag, per-element
 * init) lives in `lib/alpine-engine.js` and is shared with sibling engines.
 */
export function registerProductPricingEngine() {
	registerAlpineEngine({
		selector: PRODUCT_FIELD_SELECTOR,
		boundAttr: 'productPricingBound',
		init: initProductField,
	});
}
