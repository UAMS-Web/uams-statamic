<!--
 * Custom partial: renders a grid of subfields (text, select) for fieldtypes
 * using the HasSubfields PHP trait. Not a fork of a Statamic component;
 * Statamic does not ship an equivalent. Uses SubfieldContainer + slot for
 * inputs; custom-text-input and Combobox from app bundle. Text subfields use
 * the same read-only state as zip/select (FieldtypeMixin `isReadOnly`).
 -->
<template>
	<SubfieldContainer
		:subfields="subfields"
		:parent-handle="handle"
		:fieldtype-handle="fieldtypeHandle"
	>
		<template #input="{ subfield }">
			<custom-text-input
				v-if="subfield.type === 'text'"
				:model-value="[undefined, null].includes(currentValue[subfield.handle]) ? subfield.default : currentValue[subfield.handle]"
				:id="'field_' + subfield.handle"
				:name="subfield.handle"
				:placeholder="subfield.placeholder"
				:config="subfield"
				:readonly="isReadOnly"
				@update:model-value="onSubfieldInput(subfield.handle, $event)"
			/>
			<zip_code-fieldtype
				v-else-if="subfield.type === 'zip_code'"
				:id="'field_' + subfield.handle"
				:config="subfield"
				:handle="subfield.handle"
				:meta="meta || {}"
				:name-prefix="handle"
				:read-only="isReadOnly"
				:value="currentValue[subfield.handle] ?? subfield.default"
				@update:value="(v) => onSubfieldInput(subfield.handle, v)"
			/>
			<Combobox
				v-else-if="subfield.type === 'select'"
				:clearable="subfield.clearable ?? false"
				:disabled="subfield.disabled ?? false"
				:id="'field_' + subfield.handle"
				:model-value="selectModelValues[subfield.handle]"
				:options="formatOptions(subfield.options)"
				:option-label="'label'"
				:option-value="'value'"
				:placeholder="subfield.placeholder ?? undefined"
				:read-only="isReadOnly"
				:searchable="subfield.searchable ?? true"
				:taggable="false"
				:autocomplete="subfield.autocomplete || undefined"
				@update:model-value="(val) => onSubfieldInput(subfield.handle, val, subfield)"
			/>
		</template>
	</SubfieldContainer>
</template>

<script>
import { computed, getCurrentInstance } from 'vue';
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta, injects).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Combobox } from '@statamic/cms/ui';
import SubfieldContainer from './SubfieldContainer.vue';

export default {
	name: 'HasSubfields',
	mixins: [Fieldtype],
	components: { Combobox, SubfieldContainer },
	props: {
		value: {
			type: Object,
			required: true
		},
		meta: {
			type: Object,
			required: false,
			default: null
		},
		handle: {
			type: String,
			required: true
		}
	},
	setup(props) {
		const instance = getCurrentInstance().proxy;

		const subfields = computed(() => props.meta?.subfields || []);

		// Normalize value to a plain object (server may send [] for empty address)
		const currentValue = computed(() => {
			const v = props.value;
			if (v == null) return {};
			if (Array.isArray(v)) return {};
			return typeof v === 'object' ? { ...v } : {};
		});

		const fieldtypeHandle = computed(
			() => props.meta?.fieldtype_handle || 'unknown'
		);

		function formatOptions(options) {
			if (!options) return [];
			if (
				Array.isArray(options) &&
				options.length &&
				typeof options[0] === 'object'
			) {
				return options.map((opt) =>
					typeof opt === 'object' && opt !== null
						? {
								value: opt.value ?? opt.key,
								label:
									typeof __ !== 'undefined'
										? __(opt.label ?? opt.value ?? opt.key ?? '')
										: (opt.label ?? opt.value ?? opt.key ?? '')
							}
						: { value: opt, label: typeof __ !== 'undefined' ? __(opt) : opt }
				);
			}
			return Object.entries(options).map(([val, lbl]) => ({
				value: val,
				label:
					typeof __ !== 'undefined' ? __(lbl) || val : lbl
			}));
		}

		const selectModelValues = computed(() => {
			const out = {};
			for (const sf of subfields.value) {
				if (sf.type !== 'select') continue;
				const raw =
					currentValue.value[sf.handle] ?? sf.default;
				const display = getSelectDisplayValue(sf, raw);
				out[sf.handle] =
					display === '' ? null : display;
			}
			return out;
		});

		function getSelectDisplayValue(subfield, value) {
			if (subfield.type !== 'select') return value;
			if (value === undefined || value === null) return value;
			const optionsArr = formatOptions(subfield.options);
			if (subfield.select_value_type === 'label') {
				// Stored value is the label; convert to option value for Combobox
				const foundByLabel = optionsArr.find((opt) => opt.label == value);
				if (foundByLabel) return foundByLabel.value;
				// Stored value may be the option value (e.g. from another source)
				const foundByValue = optionsArr.find((opt) => opt.value == value);
				if (foundByValue) return foundByValue.value;
				// No match: pass through so Combobox can show fallback
				return value;
			}
			// select_value_type 'value': use as-is; ensure Combobox can match
			const found = optionsArr.find((opt) => opt.value == value);
			return found ? found.value : value;
		}

		function onSubfieldInput(handle, newValue, subfield = null) {
			const updated = { ...currentValue.value };
			if (subfield && subfield.type === 'select') {
				const optionsArr = formatOptions(subfield.options);
				if (subfield.select_value_type === 'label') {
					const found = optionsArr.find(
						(opt) => opt.value == newValue
					);
					updated[handle] = found ? found.label : newValue;
				} else {
					updated[handle] = newValue;
				}
			} else {
				updated[handle] = newValue;
			}
			instance.update(updated);
		}

		return {
			subfields,
			currentValue,
			fieldtypeHandle,
			formatOptions,
			getSelectDisplayValue,
			onSubfieldInput,
			selectModelValues
		};
	}
};
</script>
