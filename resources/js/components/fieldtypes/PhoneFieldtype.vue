<template>
	<custom-text-input
		:value="displayValue"
		:model-value="displayValue"
		:meta="meta"
		:id="id"
		:field-id="fieldId"
		:name="name"
		:readonly="isReadOnly"
		:config="effectiveConfig"
		@update:modelValue="onInput"
		@focus="$emit('focus')"
		@blur="$emit('blur')"
	/>
</template>

<script>
import { parsePhoneNumberFromString, AsYouType } from 'libphonenumber-js';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import CustomTextInputFieldtypeMixin from './mixins/CustomTextInputFieldtypeMixin.js';

export default {
	mixins: [Fieldtype, CustomTextInputFieldtypeMixin],
	data() {
		return {
			internalValue: this.value
		};
	},
	computed: {
		displayValue() {
			if (!this.internalValue) return '';

			const parseAsPhone = this.effectiveConfig.parse_as_phone !== false;
			if (!parseAsPhone) {
				return this.internalValue;
			}

			const country = this.effectiveConfig.country || 'US';
			const autocomplete = this.effectiveConfig.autocomplete || 'tel-national';
			const phone = parsePhoneNumberFromString(this.internalValue, country);

			if (autocomplete === 'tel') {
				return phone ? phone.formatInternational() : new AsYouType(country).input(this.internalValue);
			}
			if (autocomplete === 'tel-national') {
				return phone ? phone.formatNational() : new AsYouType(country).input(this.internalValue);
			}
			return phone ? phone.formatInternational() : new AsYouType(country).input(this.internalValue);
		}
	},
	watch: {
		value(newVal) {
			this.internalValue = newVal;
		}
	},
	methods: {
		onInput(val) {
			const parseAsPhone = this.effectiveConfig.parse_as_phone !== false;
			if (!parseAsPhone) {
				this.internalValue = val;
				this.update(val);
				return;
			}

			const country = this.effectiveConfig.country || 'US';
			const phone = parsePhoneNumberFromString(val, country);
			const e164 = phone ? phone.number : val;
			this.internalValue = val;
			this.update(e164);
		}
	}
};
</script>
