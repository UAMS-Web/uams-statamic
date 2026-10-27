// Mixin for shared 'Other' option logic in custom radio/checkboxes fieldtypes.
// Parent components must define computed `effectiveConfig` (meta.config merged
// with the `config` prop) so we avoid colliding with Vue 3's prop name.
import {
	getOptionValues,
	isOtherCustomValue,
	extractOtherCustomText,
	buildOtherCustomValue,
	findOtherCustomValueInArray,
	filterOutOtherValues
} from '../utilities/OtherOptionUtilities';

export default {
	/**
	 * Component data for managing the 'Other' text input and its preservation.
	 * @returns {Object}
	 */
	data() {
		return {
			/**
			 * Current value of the 'Other' text input.
			 * @type {string}
			 */
			otherValue: '',
			/**
			 * Preserved value when switching away from 'Other' option.
			 * @type {string}
			 */
			preservedOtherValue: '',
			/**
			 * MutationObserver instance for watching class changes.
			 * @type {MutationObserver|null}
			 */
			classObserver: null,
		};
	},

	computed: {
		/**
		 * Returns the meta object with the 'Other' option appended if enabled.
		 * @returns {Object}
		 */
		modifiedMeta() {
			const baseOptions = this.meta?.options || this.effectiveConfig?.options || [];
			if (this.effectiveConfig?.enable_other) {
				return {
					...this.meta,
					options: [
						...baseOptions,
						{
							value: '__other__',
							label: this.effectiveConfig?.other_label || 'Other',
						},
					],
				};
			}
			return this.meta;
		},
		/**
		 * Gets the placeholder text for the 'Other' text input.
		 * @returns {string}
		 */
		otherPlaceholder() {
			return this.effectiveConfig?.other_placeholder || 'Enter your custom value';
		},
		/**
		 * Generates a unique field ID for accessibility purposes.
		 * @returns {string}
		 */
		fieldId() {
			return this.name || 'custom_field';
		},
		/**
		 * Gets the human-readable field label for accessibility descriptions.
		 * @returns {string}
		 */
		fieldLabel() {
			return this.effectiveConfig?.display || this.name || 'Custom Field';
		},
	},

	methods: {
		/**
		 * Emits the correct value for radio fieldtypes: '__other__:<customText>' or
		 * '__other__'.
		 *
		 * @param {string} text
		 */
		emitOtherRadioValue(text) {
			if (text && text.trim() !== '') {
				this.update(buildOtherCustomValue(text));
			} else {
				this.update('__other__');
			}
		},
		/**
		 * Emits the correct array for checkboxes fieldtypes: includes
		 * '__other__:<customText>' or '__other__'.
		 *
		 * @param {string} text
		 */
		emitOtherCheckboxesValue(text) {
			let vals = Array.isArray(this.value) ? [...this.value] : [];
			vals = filterOutOtherValues(vals);
			if (text && text.trim() !== '') {
				vals.push(buildOtherCustomValue(text));
			}
			this.update(vals);
		},

		/**
		 * Ensures the fieldtype class is present on the parent publish field element.
		 * This method can be called multiple times safely.
		 */
		ensureFieldtypeClass() {
			// Wait for the next DOM update cycle to ensure the element exists
			this.$nextTick(() => {
				// Find the closest parent element with the 'publish-field' class
				const parentField = this.$el.closest('.publish-field');
				if (parentField && this.fieldtypeClass) {
					// Add the correct fieldtype class for proper styling
					// This is safe to call multiple times - classList.add won't duplicate
					parentField.classList.add(this.fieldtypeClass);
				}
			});
		},

		/**
		 * Sets up a MutationObserver to watch for changes to the parent field's class
		 * attribute. This ensures the fieldtype class is re-added whenever Statamic
		 * updates the classes.
		 */
		setupClassWatcher() {
			this.$nextTick(() => {
				const parentField = this.$el.closest('.publish-field');
				if (parentField && this.fieldtypeClass) {
					// Create a MutationObserver to watch for class changes
					this.classObserver = new MutationObserver((mutations) => {
						mutations.forEach((mutation) => {
							if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
								// Check if our fieldtype class is still present
								if (!parentField.classList.contains(this.fieldtypeClass)) {
									// Re-add the fieldtype class
									parentField.classList.add(this.fieldtypeClass);
								}
							}
						});
					});

					// Start observing the parent field for class attribute changes
					this.classObserver.observe(parentField, {
						attributes: true,
						attributeFilter: ['class']
					});
				}
			});
		}
	},
	/**
	 * Watches for changes to the value prop and manages the 'Other' option logic.
	 * Handles both radio (string) and checkboxes (array) fieldtypes.
	 * Loads the custom value from '__other__:<customText>'
	 */
	watch: {
		/**
		 * Watches for changes to the value prop and manages the 'Other' option logic.
		 * Handles both radio (string) and checkboxes (array) fieldtypes.
		 * Loads the custom value from '__other__:<customText>'
		 */
		value: {
			immediate: true,
			handler(newVal, oldVal) {
				if (Array.isArray(newVal)) {
					// Checkboxes
					const custom = findOtherCustomValueInArray(newVal);
					if (custom) {
						this.otherValue = extractOtherCustomText(custom);
						this.preservedOtherValue = this.otherValue;
					} else if (newVal.includes('__other__')) {
						if (!oldVal || !oldVal.includes('__other__')) {
							this.otherValue = this.preservedOtherValue;
						}
					} else if (oldVal && (oldVal.includes && (oldVal.includes('__other__') || findOtherCustomValueInArray(oldVal)))) {
						this.preservedOtherValue = this.otherValue;
					}
				} else if (typeof newVal === 'string') {
					// Radio
					if (isOtherCustomValue(newVal)) {
						this.otherValue = extractOtherCustomText(newVal);
						this.preservedOtherValue = this.otherValue;
					} else if (newVal === '__other__') {
						if (oldVal !== '__other__') {
							this.otherValue = this.preservedOtherValue;
						}
					} else if (oldVal && (oldVal === '__other__' || isOtherCustomValue(oldVal))) {
						this.preservedOtherValue = this.otherValue;
					}
				}
			}
		}
	},
	/**
	 * Lifecycle hook that adds the correct fieldtype CSS class to the parent publish
	 * field div for styling.
	 *
	 * Expects the component to provide a computed property 'fieldtypeClass'.
	 */
	mounted() {
		this.ensureFieldtypeClass();
		this.setupClassWatcher();
	},

	/**
	 * Cleanup the MutationObserver when the component is unmounted (Vue 3 lifecycle).
	 */
	beforeUnmount() {
		if (this.classObserver) {
			this.classObserver.disconnect();
		}
	},

	// Integration points:
	// - Component should use this.otherValue and this.preservedOtherValue for custom value logic
	// - Component should use modifiedMeta for options
	// - Component is responsible for emitting correct value format
};
