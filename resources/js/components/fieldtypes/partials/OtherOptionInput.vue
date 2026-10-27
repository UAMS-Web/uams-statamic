<template>
	<div
		v-if="isOtherSelected"
		class="mt-2"
		:id="`${name}_text_container`"
		:aria-hidden="!isOtherSelected"
		:aria-describedby="`${name}_other_help`"
	>
		<custom-text-input
			v-model="internalValue"
			:placeholder="otherPlaceholder"
			:name="`${name}_other`"
			:id="`${name}_other_input`"
		/>
		<div
			:id="`${name}_other_help`"
			class="sr-only"
			:aria-hidden="!isOtherSelected"
		>
			Enter a custom value for the "Other" option in the {{ fieldLabel || name }} field.
		</div>
	</div>
</template>

<script>
export default {
	name: 'OtherOptionInput',
	props: {
		isOtherSelected: Boolean,
		otherValue: String,
		otherPlaceholder: String,
		name: String,
		fieldLabel: String
	},
	computed: {
		internalValue: {
			get() {
				return this.otherValue;
			},
			set(val) {
				this.$emit('update:value', val);
			}
		}
	}
};
</script>

<style scoped>
.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}
</style>
