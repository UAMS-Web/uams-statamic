<!--
 * Thin wrapper around Statamic Combobox for Schema.org type labels.
 * Pass `options` as `{ value, label }[]` or `optionsMap` as Record value→label.
 *
 * Combobox 2.0 (statamic/cms#13843) defaults the option list to the trigger
 * width; set adaptiveWidth when long schema.org labels must stay readable.
 -->
<template>
	<Combobox
		:id="id"
		:model-value="modelValue"
		:options="resolvedOptions"
		:placeholder="placeholder"
		:clearable="clearable"
		:disabled="disabled"
		:multiple="multiple"
		:read-only="readOnly"
		:searchable="searchable"
		v-bind="comboboxLayoutProps"
		@update:model-value="$emit('update:modelValue', $event)"
	/>
</template>

<script>
import { Combobox } from '@statamic/cms/ui';

/**
 * Reusable Combobox for schema.org type strings with optional custom labels.
 */
export default {
	name: 'SchemaOrgTypeCombobox',

	components: { Combobox },

	props: {
		modelValue: {
			type: [String, Array, null],
			default: null,
		},
		/**
		 * Combobox options as `{ value, label }[]`.
		 *
		 * @type {Array<{ value: string, label: string }>|undefined}
		 */
		options: {
			type: Array,
			default: undefined,
		},
		/**
		 * Alternative to `options`: map of type name => label (often identical).
		 *
		 * @type {Record<string, string>|undefined|null}
		 */
		optionsMap: {
			type: Object,
			default: undefined,
		},
		id: {
			type: String,
			default: '',
		},
		placeholder: {
			type: String,
			default: '',
		},
		clearable: {
			type: Boolean,
			default: true,
		},
		searchable: {
			type: Boolean,
			default: true,
		},
		multiple: {
			type: Boolean,
			default: false,
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
		/**
		 * When true, widen the dropdown to fit long option labels (Combobox 2.0).
		 *
		 * @see https://github.com/statamic/cms/issues/13843
		 */
		adaptiveWidth: {
			type: Boolean,
			default: false,
		},
		/**
		 * Dropdown alignment when supported by Statamic Combobox (e.g. start, end).
		 */
		align: {
			type: String,
			default: undefined,
		},
	},

	emits: ['update:modelValue'],

	computed: {
		/**
		 * Optional Combobox 2.0 layout props (omit keys when unset).
		 *
		 * @returns {Record<string, string|boolean>}
		 */
		comboboxLayoutProps() {
			const props = {};

			// Opt in to width-by-content when parents expect long schema.org labels
			if (this.adaptiveWidth) {
				props.adaptiveWidth = true;
			}

			// Pass alignment only when the parent supplies a value
			if (this.align != null && this.align !== '') {
				props.align = this.align;
			}

			return props;
		},

		/**
		 * Normalized `{ value, label }[]` for the underlying Combobox.
		 *
		 * @returns {Array<{ value: string, label: string }>}
		 */
		resolvedOptions() {
			// Prefer explicit map entries when the parent passes a label record
			if (this.optionsMap && typeof this.optionsMap === 'object') {
				return Object.entries(this.optionsMap).map(([value, label]) => ({
					value,
					label: label || value,
				}));
			}

			// Fall back to pre-built option rows from the fieldtype preload
			return Array.isArray(this.options) ? this.options : [];
		},
	},
};
</script>
