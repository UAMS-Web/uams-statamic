<!--
 * Control-panel component for App\Fieldtypes\Option — the Gravity Forms
 * option-field analog. Renders priced choices in one of three modes, driven by
 * the blueprint `input_type`:
 *
 *   - select   → a drop-down of priced options (single selection).
 *   - radio    → radio buttons of priced options (single selection).
 *   - checkbox → checkboxes of priced options (multiple selection).
 *
 * The editable value is the minimal selection (`{ key }` for single modes,
 * `{ keys: [...] }` for checkbox); the authoritative `line_total` / `price`
 * snapshot is recomputed server-side by Option::process() on submit. A live
 * subtotal is shown here purely as feedback for whoever is editing the
 * submission in the CP.
 *
 * Not a fork of a core Statamic component — built directly on FieldtypeMixin
 * and the @statamic/cms/ui primitives, mirroring ProductFieldtype.vue.
 -->
<template>
	<div class="option-fieldtype space-y-3">
		<!-- Drop-down of priced options. -->
		<template v-if="isSelect">
			<Field
				:label="__('Option')"
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
					@update:model-value="onSingleUpdate"
				/>
			</Field>
		</template>

		<!-- Radio buttons of priced options. -->
		<template v-else-if="isRadio">
			<Field
				:label="__('Option')"
				class="min-w-0 form-group field-w-undefined"
			>
				<label
					v-for="option in optionList"
					:key="option.value"
					class="flex items-center gap-2"
				>
					<input
						type="radio"
						:value="option.value"
						:checked="selectedKey === option.value"
						:disabled="isReadOnly"
						@change="onSingleUpdate(option.value)"
					>
					<span>{{ option.label }}</span>
				</label>
			</Field>
		</template>

		<!-- Checkboxes of priced options (multiple selection). -->
		<template v-else>
			<Field
				:label="__('Options')"
				class="min-w-0 form-group field-w-undefined"
			>
				<label
					v-for="option in optionList"
					:key="option.value"
					class="flex items-center gap-2"
				>
					<input
						type="checkbox"
						:value="option.value"
						:checked="selectedKeys.includes(option.value)"
						:disabled="isReadOnly"
						@change="onMultipleUpdate(option.value, $event.target.checked)"
					>
					<span>{{ option.label }}</span>
				</label>
			</Field>
		</template>

		<!-- Live line subtotal (CP feedback; server recomputes on submit). -->
		<div class="text-sm font-medium text-gray-900 dark:text-gray-200" data-option-line-total>
			{{ __('Subtotal') }}: {{ formattedLineTotal }}
		</div>
	</div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Field, Select } from '@statamic/cms/ui';

export default {
	name: 'OptionFieldtype',

	components: {
		Field,
		Select,
	},

	mixins: [Fieldtype],

	computed: {
		/**
		 * Merged field config. Read from `meta.config` (populated by
		 * Option::preload()) with a fallback to the raw `config` prop — the
		 * prop name collides with Vue 3 internals, mirroring the note in
		 * RadioFieldtype.vue.
		 *
		 * @returns {Object}
		 */
		effectiveConfig() {
			return this.meta?.config ?? this.$props.config ?? {};
		},

		/** @returns {string} The configured input type (select / radio / checkbox). */
		inputType() {
			return this.effectiveConfig.input_type ?? 'select';
		},

		/** @returns {boolean} Whether this is a drop-down option field. */
		isSelect() {
			return this.inputType === 'select';
		},

		/** @returns {boolean} Whether this is a radio-button option field. */
		isRadio() {
			return this.inputType === 'radio';
		},

		/** @returns {boolean} Whether this is a checkbox (multiple) option field. */
		isCheckbox() {
			return this.inputType === 'checkbox';
		},

		/** @returns {Array<{value: string, label: string, price: number}>} Choice rows. */
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

		/** @returns {string|null} The selected key for single (select / radio) modes. */
		selectedKey() {
			return this.value?.key ?? null;
		},

		/** @returns {Array<string>} The selected keys for checkbox mode. */
		selectedKeys() {
			return Array.isArray(this.value?.keys) ? this.value.keys : [];
		},

		/** @returns {number} The selected line subtotal (sum of selected option prices). */
		lineTotal() {
			const selectedValues = this.isCheckbox ? this.selectedKeys : [this.selectedKey];

			const sum = this.optionList
				.filter((option) => selectedValues.includes(option.value))
				.reduce((total, option) => total + option.price, 0);

			return Math.round(sum * 100) / 100;
		},

		/** @returns {string} The formatted line subtotal. */
		formattedLineTotal() {
			return this.formatCurrency(this.lineTotal);
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
		 * symbol so negative options render as `-$25.00`.
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
		 * Emit a new selection for single (select / radio) modes.
		 *
		 * @param {string|null} key
		 */
		onSingleUpdate(key) {
			this.update({ key: key ?? null });
		},

		/**
		 * Toggle one checkbox key in the multiple-selection value.
		 *
		 * @param {string} key
		 * @param {boolean} checked
		 */
		onMultipleUpdate(key, checked) {
			const keys = new Set(this.selectedKeys);

			if (checked) {
				keys.add(key);
			} else {
				keys.delete(key);
			}

			this.update({ keys: Array.from(keys) });
		},
	},
};
</script>
