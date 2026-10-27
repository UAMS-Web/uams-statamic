<template>
	<custom-text-input
		:value="value"
		:model-value="value"
		:meta="meta"
		:id="id"
		:field-id="fieldId"
		:name="name"
		:readonly="isReadOnly"
		:config="effectiveConfig"
		@update:modelValue="inputUpdated"
		@focus="$emit('focus')"
		@blur="$emit('blur')"
	/>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import CustomTextInputFieldtypeMixin from './mixins/CustomTextInputFieldtypeMixin.js';

export default {
	mixins: [Fieldtype, CustomTextInputFieldtypeMixin],
	props: {
		value: { required: true },
		meta: { type: Object, default: null }
	}
};
</script>
