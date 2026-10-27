/**
 * Shared logic for fieldtypes that use custom-text-input: effective config
 * (meta.config ?? config) and debounce-aware input update handler.
 * Use with FieldtypeMixin. Assumes props: value, meta, config; and
 * methods: update, updateDebounced from Fieldtype.
 */
export default {
	computed: {
		effectiveConfig() {
			return this.meta?.config ?? this.config ?? {};
		}
	},
	methods: {
		inputUpdated(value) {
			return this.effectiveConfig.debounce
				? this.updateDebounced(value)
				: this.update(value);
		}
	}
};
