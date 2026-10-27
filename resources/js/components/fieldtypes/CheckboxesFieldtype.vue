<!--
 * Fork of Statamic 6 CheckboxesFieldtype
 * (vendor/statamic/cms/resources/js/components/fieldtypes/CheckboxesFieldtype.vue).
 * Uses CheckboxGroup and Checkbox from @statamic/cms/ui; adds Other option via
 * OtherOptionMixin and OtherOptionInput for App\Fieldtypes\Checkboxes.
 *
 * Customizations vs source:
 *   - Root <div>: needed so CheckboxGroup and OtherOptionInput can be siblings;
 *     source has only CheckboxGroup.
 *   - :model-value="checkboxesValue" and @update:model-value="onCheckboxesInput":
 *     controlled binding; source uses v-model="values" with data and watch.
 *   - Fieldtype and HasInputOptions from @statamic/cms (source uses
 *     ./Fieldtype.vue and ./HasInputOptions.js).
 *   - CheckboxGroup and Checkbox from @statamic/cms/ui (source uses
 *     @/components/ui).
 *   - effectiveConfig computed: meta?.config ?? this.$props.config ?? {}
 *     (named to avoid clashing with the Fieldtype `config` prop in Vue 3; source
 *     relies on mixin for merged config).
 *   - Other option: OtherOptionMixin, OtherOptionInput, modifiedMeta,
 *     checkboxesValue, isOtherSelected, onCheckboxesInput / emitOtherCheckboxesValue.
 *   - focus(): optional chaining this.$refs.checkboxes?.focus?.(); source calls
 *     this.$refs.checkboxes.focus().
 *   - replicatorPreview: map value to labels and handle __other__:text; source
 *     maps values only.
 *   - No data(): we use controlled :model-value / @update:model-value; source
 *     uses data() { values } and v-model so it needs local state and watch.
 *   - No watch(): updates are handled in onCheckboxesInput; source syncs
 *     values ↔ value via watch on values and value.
 *   - replicatorPreview uses `v` in .map() to avoid shadowing this.value; source
 *     uses .map((value) => ...) over this.values.
 -->
<template>
	<div>
		<CheckboxGroup
			:model-value="checkboxesValue"
			@update:model-value="onCheckboxesInput"
			:inline="effectiveConfig.inline"
			ref="checkboxes"
		>
			<Checkbox
				v-for="(option, index) in options"
				:disabled="effectiveConfig.disabled"
				:key="index"
				:label="option.label || option.value"
				:read-only="isReadOnly"
				:value="option.value"
			/>
		</CheckboxGroup>
		<OtherOptionInput
			:isOtherSelected="isOtherSelected"
			:otherValue="otherValue"
			:otherPlaceholder="otherPlaceholder"
			:name="name"
			:fieldLabel="fieldLabel"
			@update:value="updateOtherValue"
		/>
	</div>
</template>

<script>
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta).
import { FieldtypeMixin as Fieldtype, HasInputOptionsMixin as HasInputOptions } from '@statamic/cms'; // Fork: resolve from app bundle; source uses ./Fieldtype.vue and ./HasInputOptions.js.
import { CheckboxGroup, Checkbox } from '@statamic/cms/ui'; // Fork: resolve from app bundle; source uses @/components/ui.
import OtherOptionMixin from './mixins/OtherOptionMixin'; // Fork: adds "Other" option logic and modifiedMeta; not in source.
import { getOptionValues, isOtherCustomValue, extractOtherCustomText } from './utilities/OtherOptionUtilities'; // Fork: used for isOtherSelected, checkboxesValue, replicatorPreview; not in source.
import OtherOptionInput from './partials/OtherOptionInput.vue'; // Fork: renders "Other" text input; not in source.

export default {
	// Fork: explicit component name; source has no name.
	name: 'CheckboxesFieldtype',

	// Fork: prevent attrs falling through to root; source does not set this.
	inheritAttrs: false,

	// Fork: add OtherOptionMixin for "Other" option; source has only Fieldtype and HasInputOptions.
	mixins: [Fieldtype, HasInputOptions, OtherOptionMixin],

	// Fork: add OtherOptionInput; source has only CheckboxGroup and Checkbox.
	components: {
		CheckboxGroup,
		Checkbox,
		OtherOptionInput,
	},

	computed: {
		// Fork: merged field config; not named `config` — that key is reserved for the prop.
		effectiveConfig() {
			return this.meta?.config ?? this.$props.config ?? {};
		},

		options() {
			// Fork: modifiedMeta includes __other__ when enable_other; source uses meta.options || config.options.
			const base = this.modifiedMeta?.options ?? this.effectiveConfig?.options ?? [];
			return this.normalizeInputOptions(base);
		},

		// Fork: used by OtherOptionMixin for parent CSS class; not in source.
		fieldtypeClass() {
			return 'checkboxes-fieldtype';
		},

		// Fork: true when value includes __other__ or custom other value; not in source.
		isOtherSelected() {
			if (!Array.isArray(this.value)) return false;
			return this.value.includes('__other__') || this.value.some(isOtherCustomValue);
		},

		// Fork: sorted display array including __other__ when Other selected; source uses data.values.
		checkboxesValue() {
			if (!Array.isArray(this.value)) return [];
			const optionValues = getOptionValues(this.modifiedMeta);
			const selectedOptionVals = this.value.filter((v) => optionValues.includes(v));
			const sorted = this.options
				.filter((opt) => selectedOptionVals.includes(opt.value))
				.map((opt) => opt.value);
			if (
				this.effectiveConfig?.enable_other &&
				(this.value.includes('__other__') || this.value.some(isOtherCustomValue))
			) {
				sorted.push('__other__');
			}
			return sorted;
		},

		replicatorPreview() {
			// Fork: also guard on Array.isArray(this.value); source uses this.values (always array).
			if (!this.showFieldPreviews || !Array.isArray(this.value)) return;

			return this.value
				.map((v) => {
					// Fork: show extracted text or "Other" for __other__:text; not in source.
					if (isOtherCustomValue(v)) return extractOtherCustomText(v) || 'Other';

					// Fork: show "Other" for raw __other__; not in source.
					if (v === '__other__') return 'Other';

					// Fork: same as source (find label); v avoids shadowing this.value.
					const option = this.options.find((o) => o.value === v);

					return option ? option.label : v;
				})
				.join(', ');
		},
	},

	methods: {
		focus() {
			// Fork: optional chaining when ref not yet mounted; source has no optional chaining.
			this.$refs.checkboxes?.focus?.();
		},

		sortValues(values) {
			if (!values) return [];

			return this.options.filter((opt) => values.includes(opt.value)).map((opt) => opt.value);
		},

		// Fork: delegate to emitOtherCheckboxesValue; not in source.
		updateOtherValue(value) {
			this.emitOtherCheckboxesValue(value);
		},

		// Fork: sort then handle __other__ (emitOtherCheckboxesValue) or update(sorted); source uses watch on values.
		onCheckboxesInput(vals) {
			const sorted = this.sortValues(vals);
			if (sorted.includes('__other__')) {
				this.emitOtherCheckboxesValue(this.otherValue);
			} else {
				this.update(sorted);
			}
		},
	},
};
</script>
