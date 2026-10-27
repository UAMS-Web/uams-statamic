<!--
 * Custom fieldtype: button that triggers an action (e.g. import) when clicked.
 * Not a fork of a Statamic component; Statamic does not ship a Trigger fieldtype.
 * Uses publishContainer (Statamic 6); Button from UI for v6 parity.
 -->
<template>
	<div class="trigger-fieldtype-wrapper">
		<Button
			ref="triggerButton"
			variant="primary"
			:text="config.input_label || __('Trigger Action')"
			:disabled="isReadOnly || !isEnabled"
			@click="handleClick"
			@focus="$emit('focus')"
		/>
	</div>
</template>

<script>
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta, injects).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

export default {
	mixins: [Fieldtype],

	components: { Button },

	computed: {
		/**
		 * Determines if the field is read-only.
		 *
		 * @returns {boolean} True if the field is read-only, false otherwise
		 */
		isReadOnly() {
			return this.readOnly || false;
		},

		/**
		 * Gets the handle of the related field that controls button enabling.
		 *
		 * @returns {string} The related field handle or empty string
		 */
		relatedFieldHandle() {
			return this.config.related_field || '';
		},

		/**
		 * Gets the current value of the related field.
		 *
		 * @returns {any|null} The related field's value or null if not set
		 */
		relatedFieldValue() {
			// Check if a related field is configured
			if (!this.relatedFieldHandle) {
				return null;
			}
			// Access form values from publish context (Statamic 6)
			const values = this.publishContainer?.values ?? {};
			return values[this.relatedFieldHandle];
		},

		/**
		 * Determines if the trigger button is enabled.
		 *
		 * @returns {boolean} True if the related field has a non-empty value,
		 *          false otherwise
		 */
		isEnabled() {
			// Enable button if related field has a non-empty value
			return this.relatedFieldHandle && this.relatedFieldValue ? !this.isEmpty(this.relatedFieldValue) : false;
		},

		/**
		 * Gets all current form values from the publish context (Statamic 6).
		 *
		 * @returns {object} The form values
		 */
		formValues() {
			return this.publishContainer?.values ?? {};
		},
	},

	methods: {
		/**
		 * Checks if a value is empty.
		 *
		 * Handles arrays, objects, and primitive types.
		 *
		 * @param {any} value The value to check
		 * @returns {boolean} True if the value is empty, false otherwise
		 */
		isEmpty(value) {
			if (Array.isArray(value)) {
				return value.length === 0;
			}
			if (value && typeof value === 'object') {
				return Object.keys(value).length === 0;
			}
			return !value;
		},

		/**
		 * Handles the button click event.
		 *
		 * Sends a request to the trigger endpoint with form values and updates the form
		 * with returned values, ensuring reactivity.
		 *
		 * @returns {Promise<void>}
		 */
		// Parity with other fieldtypes (replicator, CP focus management)
		focus() {
			this.$refs.triggerButton?.$el?.focus?.();
		},

		async handleClick() {
			// Exit if the button is disabled or read-only
			if (this.isReadOnly || !this.isEnabled) {
				return;
			}

			try {
				// Send request to the trigger endpoint
				const response = await this.$axios.post('/!/trigger/activate', {
					trigger_handle: this.name,
					form_values: this.formValues,
				});

				// Handle successful response
				if (response.data.success) {
					// Show success toast
					this.$toast.success(response.data.success);

					// Update form with returned values (Statamic 6: direct assignment, publishContainer)
					if (response.data.updated_values && typeof response.data.updated_values === 'object') {
						const values = this.publishContainer?.values ?? {};
						Object.entries(response.data.updated_values).forEach(([field, value]) => {
							values[field] = value;
						});
					}
				} else if (response.data.error) {
					// Show error toast
					this.$toast.error(response.data.error);
				}
			} catch (error) {
				// Show error toast
				this.$toast.error('An error occurred while performing the action.');
			}
		},
	},
};
</script>
