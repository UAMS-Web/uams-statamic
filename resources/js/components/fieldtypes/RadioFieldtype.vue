<!--
 * Fork of Statamic 6 RadioFieldtype
 * (vendor/statamic/cms/resources/js/components/fieldtypes/RadioFieldtype.vue).
 * Uses RadioGroup and Radio from @statamic/cms/ui; adds Other option via
 * OtherOptionMixin and OtherOptionInput for App\Fieldtypes\Radio.
 *
 * Customizations vs source:
 *   - Root <div>: needed so RadioGroup and OtherOptionInput can be siblings;
 *     source has only RadioGroup.
 *   - :model-value="radioValue": shows '__other__' when "Other" is selected so
 *     the correct option is highlighted; source uses :model-value="value".
 *   - @update:model-value="onRadioUpdate": onRadioUpdate handles '__other__'
 *     (emit custom value or '__other__') and otherwise calls update(val); source
 *     uses @update:model-value="update".
 *   - Fieldtype and HasInputOptions from @statamic/cms (source uses
 *     ./Fieldtype.vue and ./HasInputOptions.js).
 *   - RadioGroup and Radio from @statamic/cms/ui (source uses @/components/ui).
 *   - effectiveConfig computed: meta?.config ?? this.$props.config ?? {}
 *     (named to avoid clashing with the Fieldtype `config` prop in Vue 3; source
 *     relies on mixin for merged config).
 *   - Other option: OtherOptionMixin, OtherOptionInput, modifiedMeta, radioValue,
 *     isOtherSelected, onRadioUpdate / emitOtherRadioValue.
 *   - focus(): optional chaining this.$refs.radio?.focus?.() so no error when ref
 *     is missing; source calls this.$refs.radio.focus().
 *   - replicatorPreview: use const for block-scoped option; source uses var.
 -->
<template>
	<div>
		<RadioGroup
			:inline="effectiveConfig.inline"
			:model-value="radioValue"
			@update:model-value="onRadioUpdate"
			ref="radio"
		>
			<Radio
				v-for="(option, index) in options"
				:disabled="effectiveConfig.disabled"
				:key="index"
				:label="option.label || option.value"
				:read-only="isReadOnly"
				:value="option.value"
			/>
		</RadioGroup>
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
import { RadioGroup, Radio } from '@statamic/cms/ui'; // Fork: resolve from app bundle; source uses @/components/ui.
import OtherOptionMixin from './mixins/OtherOptionMixin'; // Fork: adds "Other" option logic and modifiedMeta; not in source.
import { getOptionValues, isOtherCustomValue } from './utilities/OtherOptionUtilities'; // Fork: used for isOtherSelected; not in source.
import OtherOptionInput from './partials/OtherOptionInput.vue'; // Fork: renders "Other" text input; not in source.

export default {
	// Fork: explicit component name; source has no name.
	name: 'RadioFieldtype',

	// Fork: prevent attrs falling through to root; source does not set this.
	inheritAttrs: false,

	// Fork: add OtherOptionMixin for "Other" option; source has only Fieldtype and HasInputOptions.
	mixins: [Fieldtype, HasInputOptions, OtherOptionMixin],

	// Fork: add OtherOptionInput; source has only RadioGroup and Radio.
	components: {
		RadioGroup,
		Radio,
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

			// Fork: same as source; we pass base that may include __other__.
			return this.normalizeInputOptions(base);
		},

		// Fork: used by OtherOptionMixin for parent CSS class; not in source.
		fieldtypeClass() {
			return 'radio-fieldtype';
		},

		// Fork: true when value is '__other__' or custom other value; not in source.
		isOtherSelected() {
			const optionValues = getOptionValues(this.modifiedMeta);
			return this.value === '__other__' || isOtherCustomValue(this.value);
		},

		// Fork: value or '__other__' when Other selected so RadioGroup shows correct selection; not in source.
		radioValue() {
			if (this.isOtherSelected) return '__other__';
			return this.value;
		},

		replicatorPreview() {
			if (!this.showFieldPreviews) return;

			const option = this.options.find((o) => o.value === this.value); // Fork: Use const for block-scoped option; source uses var.
			return option ? option.label : this.value;
		},
	},
	methods: {
		focus() {
			this.$refs.radio?.focus?.(); // Fork: optional chaining when ref not yet mounted; source has no optional chaining.
		},

		// Fork: delegate to emitOtherRadioValue; not in source.
		updateOtherValue(value) {
			this.emitOtherRadioValue(value);
		},

		// Fork: handle '__other__' (emit custom value or '__other__') else update(val); not in source.
		onRadioUpdate(val) {
			if (val === '__other__') {
				this.emitOtherRadioValue(this.otherValue);
			} else {
				this.update(val);
			}
		},
	},
};
</script>
