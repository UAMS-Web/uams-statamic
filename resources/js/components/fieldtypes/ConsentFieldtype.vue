<template>
	<div class="consent-fieldtype-wrapper">
		<!-- Consent agreement: Card -->
		<Card class="mb-4">
			<div
				class="prose prose-sm prose-zinc prose-headings:font-medium max-w-full overflow-y-auto"
				style="max-height: 16rem;"
				v-html="agreementHtml"
			/>
		</Card>

		<!-- Single checkbox using Statamic UI Checkbox (forms checkbox) -->
		<Checkbox
			:model-value="isChecked"
			:label="checkboxLabel"
			:read-only="isReadOnly"
			@update:model-value="onCheckboxInput"
		/>
	</div>
</template>

<script>
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Card, Checkbox } from '@statamic/cms/ui';
import { resolveAgreementHtml } from './consent-agreement.js';

export default {
	// Use the Fieldtype mixin for Statamic fieldtype integration
	mixins: [Fieldtype],

	components: {
		Card,
		Checkbox,
	},

	computed: {
		// The agreement HTML to display (should be provided by backend). Resolved
		// (meta → config fallback) and sanitized before the `v-html` binding as
		// defense-in-depth; see `consent-agreement.js`.
		agreementHtml() {
			return resolveAgreementHtml(this.meta, this.config);
		},
		// The label for the checkbox
		checkboxLabel() {
			return this.config.checkbox_label || 'I agree';
		},
		// Boolean checked state derived from stored value
		isChecked() {
			if (typeof this.value === 'object' && this.value !== null) {
				return Boolean(this.value.value);
			}
			return Boolean(this.value);
		},
		// The agreement text to store (markdown or HTML)
		agreementText() {
			// Store the raw config value (markdown) if available
			return this.config.consent_agreement || '';
		},
	},
	methods: {
		// Handle checkbox toggle; update stored value and agreement text
		onCheckboxInput(checked) {
			this.update({
				value: Boolean(checked), // Store as boolean
				text: this.agreementText, // Store the agreement text
			});
		},
	},
};
</script>
