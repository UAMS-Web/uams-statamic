<!--
 * Custom fieldtype for masked text input (IMask). Not a fork of a Statamic component;
 * Statamic does not ship a Mask fieldtype. Uses a raw input so IMask can bind to the
 * DOM element. Kept in sync with other input fieldtypes for focus() and focus emit.
 -->
<template>
	<div class="mask-field-fieldtype group/input relative block w-full st-text-legibility" data-ui-input>
		<input
			ref="input"
			:value="maskedValue"
			:class="maskInputClasses"
			data-ui-control
			data-ui-group-target
			@input="updateValue"
			@focus="$emit('focus')"
		/>
	</div>
</template>

<script>
import IMask from 'imask';
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import CpInputClassesMixin from './mixins/CpInputClassesMixin.js';

export default {
	mixins: [Fieldtype, CpInputClassesMixin],
	props: {
		value: {
			type: String,
			default: '',
		},
		config: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			maskedValue: this.value,
			imask: null,
		};
	},

	computed: {
		maskInputClasses() {
			return `input-text ${this.cpInputClasses}`;
		},
		maskPattern() {
			const mask = this.config.input_mask.match(/^\/(.+)\/$/)
				? new RegExp(this.config.input_mask.slice(1, -1))
				: this.config.input_mask;
			return {
				mask: mask,
				definitions: {
					'#': /[a-zA-Z0-9]/, // Custom definition: any letter or digit
					'@': /[a-zA-Z0-9-_]/, // Custom definition: any letter, digit, dash, or underscore
				},
				lazy: false,
				placeholderChar: '_',
				overwrite: true,
			};
		},

		transformCase() {
			return this.config.transform_case || 'none';
		},
	},

	watch: {
		value(newValue) {
			// Only update if different and not already synced
			if (newValue !== this.maskedValue) {
				this.maskedValue = newValue;
				if (this.imask) {
					this.imask.value = newValue;
				}
			}
		},
	},

	methods: {
		updateValue(event) {
			let value = event.target.value;

			// Apply case transformation if specified
			if (this.transformCase === 'uppercase') {
				value = value.toUpperCase();
			} else if (this.transformCase === 'lowercase') {
				value = value.toLowerCase();
			}

			this.maskedValue = value;
			if (this.imask) {
				this.imask.value = value; // Sync IMask immediately
			}
			const maskedValue = this.imask ? this.imask.value : value;
			this.update(maskedValue);
		},

		initializeMask() {
			if (this.$refs.input) {
				this.imask = IMask(this.$refs.input, this.maskPattern);
				// Set initial value after mask initialization
				if (this.value) {
					this.imask.value = this.value;
					this.maskedValue = this.imask.value;
				}
			}
		},

		// Parity with other input fieldtypes (replicator, CP focus management)
		focus() {
			this.$refs.input?.focus?.();
		},
	},

	mounted() {
		this.initializeMask();
	},

	beforeUnmount() {
		if (this.imask) {
			this.imask.destroy();
		}
	},
};
</script>

<style scoped>
.mask-field-fieldtype {
	position: relative;
}
</style>
