<!--
 * App-specific label for has-subfields subfields. Structure/attributes
 * aligned with Statamic publish field label (data-ui-label, group label classes).
 -->
<template>
	<label
		class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
		data-ui-label=""
		:for="id"
	>
		<div>
			<span
				class="rtl:ml-1 ltr:mr-1"
				v-text="label"
				v-tooltip="{ content: handle, delay: 500, autoHide: false }"
			/>
			<span
				v-if="required"
				class="relative -top-px ms-0.5 text-red-600"
			>*</span>
		</div>
	</label>
</template>

<script>
export default {
	name: 'SubfieldLabel',
	props: {
		label: { type: String, required: true },
		handle: { type: String, required: true },
		required: { type: Boolean, default: false },
		id: { type: String, required: true },
	},
};
</script>
