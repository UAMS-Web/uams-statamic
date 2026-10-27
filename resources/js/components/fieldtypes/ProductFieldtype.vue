<!--
 * Control-panel component for App\Fieldtypes\Product — the Gravity Forms
 * product-field analog. Renders the two configured modes:
 *
 *   - single   → the base price plus a quantity input (omitted when the
 *                blueprint sets `disable_quantity`).
 *   - multiple → a drop-down of priced option rows plus a quantity input.
 *
 * The editable value is the minimal `{ key?, quantity }` selection; the
 * authoritative `line_total` / `price` snapshot is recomputed server-side by
 * Product::process() on submit. A live subtotal is shown here purely as
 * feedback for whoever is editing the submission in the CP.
 *
 * Not a fork of a core Statamic component — built directly on FieldtypeMixin
 * and the @statamic/cms/ui primitives, mirroring QuantitativeValueFieldtype.
 -->
<template>
	<div class="product-fieldtype space-y-3">
		<!-- Multiple-choice: a drop-down of priced options. -->
		<template v-if="isMultiple">
			<Field
				:label="__('Product')"
				class="min-w-0 form-group field-w-undefined"
			>
				<Select
					:model-value="selectedKey"
					:options="optionList"
					option-label="label"
					option-value="value"
					:read-only="isReadOnly"
					:clearable="true"
					:placeholder="__('Please select...')"
					@update:model-value="onOptionUpdate"
				/>
			</Field>
		</template>
		<!-- Single product: a read-only base-price summary. -->
		<template v-else>
			<div class="text-sm text-gray-700 dark:text-gray-300" data-product-summary>
				<span v-if="productLabel" class="font-medium">{{ productLabel }} — </span>
				<span>{{ formattedUnitPrice }}</span>
			</div>
		</template>

		<!-- Quantity — always shown for multiple, and for single unless disabled. -->
		<Field
			v-if="showQuantity"
			:label="__('Quantity')"
			class="min-w-0 form-group field-w-undefined"
		>
			<Input
				:id="quantityId"
				type="number"
				:model-value="quantityDisplay"
				:read-only="isReadOnly"
				:input-attrs="quantityAttrs"
				@update:model-value="onQuantityUpdate"
			/>
		</Field>

		<!-- Live line subtotal (CP feedback; server recomputes on submit). -->
		<div class="text-sm font-medium text-gray-900 dark:text-gray-200" data-product-line-total>
			{{ __('Subtotal') }}: {{ formattedLineTotal }}
		</div>
	</div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Field, Input, Select } from '@statamic/cms/ui';

export default {
	name: 'ProductFieldtype',

	components: {
		Field,
		Input,
		Select,
	},

	mixins: [Fieldtype],

	computed: {
		/**
		 * Merged field config. Read from `meta.config` (populated by
		 * Product::preload()) with a fallback to the raw `config` prop —
		 * the prop name collides with Vue 3 internals, mirroring the note
		 * in RadioFieldtype.vue.
		 *
		 * @returns {Object}
		 */
		effectiveConfig() {
			return this.meta?.config ?? this.$props.config ?? {};
		},

		/** @returns {boolean} Whether this is a multiple-choice product. */
		isMultiple() {
			return this.effectiveConfig.selection_type === 'multiple';
		},

		/** @returns {boolean} Whether the single-product quantity field is hidden. */
		disableQuantity() {
			return Boolean(this.effectiveConfig.disable_quantity);
		},

		/** @returns {boolean} Whether to render a quantity input. */
		showQuantity() {
			return this.isMultiple || !this.disableQuantity;
		},

		/** @returns {string} The single-product label (the field's display name). */
		productLabel() {
			return this.effectiveConfig.display ?? '';
		},

		/** @returns {Array<{value: string, label: string, price: number}>} Drop-down rows. */
		optionList() {
			const options = Array.isArray(this.effectiveConfig.options)
				? this.effectiveConfig.options
				: [];

			return options.map((option) => {
				const key = option.key ?? '';
				const label = option.label || key;
				const price = this.toNumber(option.price);

				return {
					value: key,
					label: `${label} (${this.formatCurrency(price)})`,
					price,
				};
			});
		},

		/** @returns {string|null} The currently selected option key. */
		selectedKey() {
			return this.value?.key ?? null;
		},

		/** @returns {number} The unit price of the active selection. */
		unitPrice() {
			if (this.isMultiple) {
				const selected = this.optionList.find((option) => option.value === this.selectedKey);
				return selected ? selected.price : 0;
			}

			return this.toNumber(this.effectiveConfig.price);
		},

		/** @returns {string} The formatted single-product base price. */
		formattedUnitPrice() {
			return this.formatCurrency(this.toNumber(this.effectiveConfig.price));
		},

		/** @returns {number} The active quantity (1 when the field is disabled). */
		quantity() {
			if (this.disableQuantity && !this.isMultiple) {
				return 1;
			}

			const raw = parseInt(this.value?.quantity, 10);
			return Number.isFinite(raw) && raw > 0 ? raw : 0;
		},

		/** @returns {string|number} The quantity input's display value (blank when unset). */
		quantityDisplay() {
			const raw = this.value?.quantity;
			return raw === undefined || raw === null ? '' : raw;
		},

		/** @returns {number} The line subtotal (unit price × quantity). */
		lineTotal() {
			return Math.round(this.unitPrice * this.quantity * 100) / 100;
		},

		/** @returns {string} The formatted line subtotal. */
		formattedLineTotal() {
			return this.formatCurrency(this.lineTotal);
		},

		/** @returns {string} A DOM id for the quantity input. */
		quantityId() {
			return `${this.id || this.handle || 'product'}-quantity`;
		},

		/** @returns {Object} Extra attributes for the quantity number input. */
		quantityAttrs() {
			return {
				min: '0',
				step: '1',
				inputmode: 'numeric',
			};
		},
	},

	methods: {
		/**
		 * Coerce a possibly-string price into a finite number.
		 *
		 * @param {*} value
		 * @returns {number}
		 */
		toNumber(value) {
			const parsed = parseFloat(value);
			return Number.isFinite(parsed) ? parsed : 0;
		},

		/**
		 * Format a number as a US-dollar string, keeping the sign outside the
		 * symbol so negative line items render as `-$2,500.00`.
		 *
		 * @param {number} amount
		 * @returns {string}
		 */
		formatCurrency(amount) {
			const value = Number.isFinite(amount) ? amount : 0;
			const sign = value < 0 ? '-' : '';
			return `${sign}$${Math.abs(value).toFixed(2)}`;
		},

		/**
		 * Emit a new selection when the option drop-down changes.
		 *
		 * @param {string|null} key
		 */
		onOptionUpdate(key) {
			this.update({
				...this.selectionBase(),
				key: key ?? null,
			});
		},

		/**
		 * Emit a new selection when the quantity input changes.
		 *
		 * @param {string|number} raw
		 */
		onQuantityUpdate(raw) {
			const next = this.selectionBase();

			if (raw === '' || raw === null || raw === undefined) {
				delete next.quantity;
			} else {
				const parsed = parseInt(raw, 10);
				next.quantity = Number.isFinite(parsed) ? parsed : 0;
			}

			this.update(next);
		},

		/**
		 * Clone the current editable value as a plain object so updates never
		 * mutate the prop in place.
		 *
		 * @returns {Object}
		 */
		selectionBase() {
			return this.value && typeof this.value === 'object' ? { ...this.value } : {};
		},
	},
};
</script>
