<!--
 * App-specific container for has-subfields layout: subfield list, labels,
 * error display. Structure/attributes aligned with Statamic GroupFieldtype.
 * Uses publish context for errors (Statamic 6).
 -->
<template>
	<section :class="sectionClass">
		<div :class="{
			'bg-white dark:bg-gray-800 dark:border-dark-900 rounded-lg border': config.border,
		}">
			<div
				class="publish-fields pt-4"
				:class="{ 'px-4 py-4': config.border }"
			>
				<!-- Subfields Loop (Start) -->
				<div
					v-for="subfield in subfields"
					:key="subfield.handle"
					:class="formGroupClasses(subfield, subfieldErrors(subfield.handle).length)"
					data-ui-input-group
				>
					<div>
						<div data-ui-field-text="" class="mb-1.5">
							<SubfieldLabel
								:label="subfield.label"
								:handle="subfield.handle"
								:required="subfield.required"
								:id="subfieldId(props.fieldId, parentHandle, subfield.handle)"
							/>
						</div>
					</div>
					<div dir="ltr">
						<slot name="input" :subfield="subfield" />
					</div>
					<div v-if="subfieldErrors(subfield.handle).length">
						<small
							class="help-block text-red-500 mt-2 mb-0"
							v-for="(error, i) in subfieldErrors(subfield.handle)"
							:key="i"
							v-text="error"
						/>
					</div>
				</div>
				<!-- Subfields Loop (End) -->
			</div>
		</div>
	</section>
</template>

<script setup>
import { inject } from 'vue';
import { publishContextKey } from '@statamic/cms/ui';
import SubfieldLabel from './SubfieldLabel.vue';
import { subfieldId } from './subfieldId.js';

defineOptions({ name: 'SubfieldContainer' });

const props = defineProps({
	subfields: { type: Array, required: true },
	config: {
		type: Object,
		default: () => ({ border: true }),
	},
	customClass: { type: String, default: '' },
	fieldtypeHandle: { type: String, required: true },
	parentHandle: { type: String, required: true },
	// The fieldtype's id from Statamic, which carries the publish container (#327).
	fieldId: { type: String, default: '' },
});

const sectionClass = { 'mt-14 p-4': props.config.fullscreen };

const publishContext = inject(publishContextKey);

function formGroupClasses(subfield, hasError) {
	return [
		'min-w-0',
		`${subfield.type}-fieldtype`,
		'form-group',
		`field-w-${subfield.width}`,
		hasError ? 'has-error' : '',
	];
}

function subfieldErrors(subfieldHandle) {
	// Statamic 6: use publish context (errors may be a ref)
	const errs = publishContext?.errors;
	const errsVal = errs && typeof errs === 'object' && 'value' in errs ? errs.value : errs;
	if (!errsVal) return [];
	const key = `${props.parentHandle}.${subfieldHandle}`;
	return errsVal[key] || [];
}
</script>
