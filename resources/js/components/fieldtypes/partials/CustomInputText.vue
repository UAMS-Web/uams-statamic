<template>
	<custom-input
		ref="customInput"
		v-bind="maskPropsFromConfig"
		:value="internalValue"
		:type="computedConfig.input_type || 'text'"
		:id="id ?? fieldId"
		:name="name"
		:placeholder="effectivePlaceholder"
		:readonly="readonly || computedConfig.readonly"
		:prepend="effectivePrepend"
		:append="effectiveAppend"
		:direction="effectiveDir"
		:autoselect="autoselect ?? computedConfig.autoselect"
		:focus="shouldFocus"
		:classes="computedConfig.classes"
		:limit="computedConfig.character_limit ?? null"
		base-class="input-text"
		:config="computedConfig"
		:viewable="viewable"
		@update:modelValue="$emit('update:modelValue', $event)"
		@focus="$emit('focus')"
		@blur="$emit('blur')"
	/>
</template>

<script>
export default {
	inheritAttrs: false,

	props: {
		/** Current value of the input (from field or parent) */
		value: { type: [String, Number], default: '' },
		/** When provided (e.g. grid/replicator), config is taken from meta.config */
		meta: { type: Object, default: null },
		/** HTML id for the input element */
		id: { type: String, default: undefined },
		/** Fallback for id when used from fieldtype (e.g. fieldId from mixin) */
		fieldId: { type: String, default: undefined },
		/** Form field name for the input */
		name: { type: String, default: undefined },
		/** Placeholder text (e.g. translated from config in parent) */
		placeholder: { type: String, default: undefined },
		/** When true, the input is not editable */
		readonly: { type: Boolean, default: false },
		/** Prepended text (e.g. translated from config in parent) */
		prepend: { type: String, default: null },
		/** Appended text (e.g. translated from config in parent) */
		append: { type: String, default: null },
		/** Select all text on focus */
		autoselect: { type: Boolean, default: undefined },
		/** v-model value; when set, used instead of value */
		modelValue: { type: [String, Number], default: undefined },
		/** Field config from blueprint (placeholder, calculation, etc.) */
		config: { type: Object, default: () => ({}) },
		/** When true, shows an eye icon to toggle password visibility */
		viewable: { type: Boolean, default: false }
	},

	emits: ['update:modelValue', 'focus', 'blur'],

	methods: {
		/**
		 * Focus the inner input (CP / replicator parity with native fieldtypes).
		 */
		focus() {
			this.$refs.customInput?.focusInput?.();
		}
	},

	computed: {
		computedConfig() {
			return this.meta?.config ?? this.config ?? {};
		},

		/** Pass mask-related config as explicit props so IMaskMixin receives them before first render */
		maskPropsFromConfig() {
			const c = this.computedConfig ?? {};
			const keys = [
				'input_mask', 'input_mask_class', 'input_mask_store_unmasked', 'transform_case',
				'input_mask_append_text', 'input_mask_append_text_enabled', 'input_mask_autofix',
				'input_mask_blocks', 'input_mask_compiledMasks', 'input_mask_currentMask',
				'input_mask_definitions', 'input_mask_displayChar', 'input_mask_eager',
				'input_mask_enum', 'input_mask_exposeMask', 'input_mask_from',
				'input_mask_lazy', 'input_mask_mapToRadix', 'input_mask_mask',
				'input_mask_max', 'input_mask_maxLength', 'input_mask_min', 'input_mask_normalizeZeros',
				'input_mask_overwrite', 'input_mask_padFractionalZeros', 'input_mask_parent',
				'input_mask_pattern', 'input_mask_placeholderChar', 'input_mask_prepareChar',
				'input_mask_prepend_text', 'input_mask_prepend_text_enabled', 'input_mask_radix',
				'input_mask_repeat', 'input_mask_scale', 'input_mask_skipInvalid', 'input_mask_thousandsSeparator',
				'input_mask_to',
			];
			const out = {};
			keys.forEach((k) => {
				if (c[k] !== undefined) out[k] = c[k];
			});
			return out;
		},

		internalValue() {
			return this.modelValue != null ? this.modelValue : this.value;
		},

		/** Pass-through: autofocus when config.focus or name is title/alt */
		shouldFocus() {
			const config = this.computedConfig ?? {};
			if (config.focus === false) return false;
			return config.focus === true || this.name === 'title' || this.name === 'alt';
		},

		/** Yield to config.direction (v6) else config.dir (Input.php); exclude "Not Specified" */
		effectiveDir() {
			const c = this.computedConfig ?? {};
			const value = c.direction ?? c.dir;
			if (value == null || value === 'null') return undefined;
			return value;
		},

		/** Placeholder: prop or translated from config */
		effectivePlaceholder() {
			if (this.placeholder != null && this.placeholder !== '') return this.placeholder;
			const raw = this.computedConfig?.placeholder;
			return raw != null && raw !== '' ? (typeof __ !== 'undefined' ? __(raw) : raw) : undefined;
		},

		/** Prepend: prop or translated from config */
		effectivePrepend() {
			if (this.prepend != null && this.prepend !== '') return this.prepend;
			const raw = this.computedConfig?.prepend;
			return raw != null && raw !== '' ? (typeof __ !== 'undefined' ? __(raw) : raw) : null;
		},

		/** Append: prop or translated from config */
		effectiveAppend() {
			if (this.append != null && this.append !== '') return this.append;
			const raw = this.computedConfig?.append;
			return raw != null && raw !== '' ? (typeof __ !== 'undefined' ? __(raw) : raw) : null;
		}
	}
};
</script>
