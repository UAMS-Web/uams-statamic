<!--
 * Quantitative value: number (int/float) range and/or duration-style unit groups.
 * Stored shape is normalized in PHP (process / preProcess).
 * CP copy: `lang/vendor/statamic/en/fieldtypes.php` keys `statamic::fieldtypes.quantitative_value.*`.
 -->
<template>
	<group-fieldtype-shell>
		<div class="publish-fields">
			<template v-if="isNumberMode">
				<Field
					v-for="major in majors"
					:key="major"
					:label="majorLabel(major)"
					class="min-w-0 form-group field-w-undefined"
					:class="{ 'has-error': errsForNumberMajor(major).length > 0 }"
					data-ui-input-group
				>
					<Input
						:id="inputId(major)"
						:name="numberFieldName(major)"
						type="text"
						:model-value="numberDisplay(major)"
						:read-only="isReadOnly"
						:input-attrs="numberInputAttrs"
						@update:model-value="onNumberModelUpdate(major, $event)"
					/>
					<ErrorMessage
						v-for="(err, i) in errsForNumberMajor(major)"
						:key="'n'+major+i"
						class="mt-1"
						:text="err"
					/>
				</Field>
				<Field
					v-if="fc.unit_code_enabled"
					:label="__('statamic::fieldtypes.quantitative_value.field.unit_code')"
					:instructions="fc.unit_code_instructions"
					class="min-w-0 form-group field-w-undefined"
					:class="{ 'has-error': errs('unit_code').length > 0 }"
					data-ui-input-group
				>
					<Input
						:id="fieldIdBase + '_unit_code'"
						:name="numberUnitFieldName('unit_code')"
						type="text"
						:model-value="unitCodeDisplay"
						:read-only="isReadOnly"
						:input-attrs="textSubfieldAttrs"
						@update:model-value="onUnitCodeUpdate"
					/>
					<ErrorMessage
						v-for="(err, i) in errs('unit_code')"
						:key="'uc'+i"
						class="mt-1"
						:text="err"
					/>
				</Field>
				<Field
					v-if="fc.unit_text_enabled"
					:label="__('statamic::fieldtypes.quantitative_value.field.unit_text')"
					:instructions="fc.unit_text_instructions"
					class="min-w-0 form-group field-w-undefined"
					:class="{ 'has-error': errs('unit_text').length > 0 }"
					data-ui-input-group
				>
					<Input
						:id="fieldIdBase + '_unit_text'"
						:name="numberUnitFieldName('unit_text')"
						type="text"
						:model-value="unitTextDisplay"
						:read-only="isReadOnly"
						:input-attrs="textSubfieldAttrs"
						@update:model-value="onUnitTextUpdate"
					/>
					<ErrorMessage
						v-for="(err, i) in errs('unit_text')"
						:key="'ut'+i"
						class="mt-1"
						:text="err"
					/>
				</Field>
			</template>
			<template v-else>
				<template v-if="durationGroupedSections">
					<div
						v-for="major in majors"
						:key="major"
						class="min-w-0 form-group group-fieldtype field-w-undefined space-y-2"
						:class="{ 'has-error': durationMajorHasError(major) }"
						data-ui-input-group
					>
						<Label class="flex justify-between text-sm font-medium text-gray-925 select-none dark:text-gray-300">
							{{ majorLabel(major) }}
						</Label>
						<group-fieldtype-shell body-class="space-y-3">
							<Field
								v-for="unit in units"
								:key="`${major}.${unit}`"
								:label="unitTitle(unit)"
								class="min-w-0"
								:class="{ 'has-error': errs(`${major}.${unit}`).length > 0 }"
							>
								<Input
									:id="durationInputId(major, unit)"
									:name="durationFieldName(major, unit)"
									type="text"
									:model-value="durationDisplay(major, unit)"
									:read-only="isReadOnly"
									:input-attrs="durationInputAttrs"
									@update:model-value="onDurationModelUpdate(major, unit, $event)"
								/>
								<ErrorMessage
									v-for="(err, i) in errs(`${major}.${unit}`)"
									:key="'d'+major+unit+i"
									class="mt-1"
									:text="err"
								/>
							</Field>
						</group-fieldtype-shell>
					</div>
				</template>
				<template v-else>
					<Field
						v-for="flatRow in durationFlatRows"
						:key="flatRow.key"
						:label="flatRow.label"
						class="min-w-0 form-group field-w-undefined"
						:class="{ 'has-error': errs(`${flatRow.major}.${flatRow.unit}`).length > 0 }"
						data-ui-input-group
					>
						<Input
							:id="durationInputId(flatRow.major, flatRow.unit)"
							:name="durationFieldName(flatRow.major, flatRow.unit)"
							type="text"
							:model-value="durationDisplay(flatRow.major, flatRow.unit)"
							:read-only="isReadOnly"
							:input-attrs="durationInputAttrs"
							@update:model-value="
								onDurationModelUpdate(flatRow.major, flatRow.unit, $event)
							"
						/>
						<ErrorMessage
							v-for="(err, i) in errs(`${flatRow.major}.${flatRow.unit}`)"
							:key="'df'+flatRow.key+i"
							class="mt-1"
							:text="err"
						/>
					</Field>
				</template>
			</template>
		</div>
	</group-fieldtype-shell>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Field, Input, Label, ErrorMessage, publishContextKey } from '@statamic/cms/ui';
import GroupFieldtypeShell from './partials/GroupFieldtypeShell.vue';

export default {
	mixins: [Fieldtype],

	components: {
		GroupFieldtypeShell,
		Field,
		Input,
		Label,
		ErrorMessage,
	},

	inject: {
		publishContext: { from: publishContextKey },
	},

	computed: {
		fc() {
			return this.meta?.config ?? {};
		},
		fieldHandle() {
			return this.handle ?? this.name ?? '';
		},
		isNumberMode() {
			return this.fc.mode !== 'duration';
		},
		isFloat() {
			return this.fc.number_mode === 'float';
		},
		numberInputAttrs() {
			return {
				step: this.isFloat ? 'any' : '1',
				inputmode: this.isFloat ? 'decimal' : 'numeric',
			};
		},
		durationInputAttrs() {
			return {
				step: '1',
				inputmode: 'numeric',
			};
		},
		textSubfieldAttrs() {
			return {
				autocomplete: 'off',
			};
		},
		unitCodeDisplay() {
			return String(this.unitMetadataDisplay('unit_code'));
		},
		unitTextDisplay() {
			return String(this.unitMetadataDisplay('unit_text'));
		},
		majors() {
			return Array.isArray(this.fc.major_components) ? this.fc.major_components : ['value', 'min', 'max'];
		},
		units() {
			return Array.isArray(this.fc.duration_units) ? this.fc.duration_units : ['years', 'months', 'days'];
		},
		durationGroupedSections() {
			return Boolean(this.fc.duration_grouped_sections);
		},
		durationFlatRows() {
			const rows = [];
			this.majors.forEach((major) => {
				this.units.forEach((unit) => {
					rows.push({
						key: `${major}_${unit}`,
						major,
						unit,
						label: this.durationFlatLabel(major, unit),
					});
				});
			});
			return rows;
		},
		fieldIdBase() {
			const h = (this.handle || this.name || 'qv').replace(/[^a-z0-9_]/gi, '_');
			return String(this.id || `field_${h}`).replace(/\./g, '_');
		},
	},

	methods: {
		/**
		 * Validation messages for a dotted suffix under this field handle (matches PHP extraRules).
		 *
		 * @param {string} [dotSuffix] e.g. "value", "min", "value.years"; empty string = field root handle
		 * @returns {string[]}
		 */
		errs(dotSuffix = '') {
			const handle = this.fieldHandle;
			if (!handle) {
				return [];
			}
			const fullKey = dotSuffix ? `${handle}.${dotSuffix}` : handle;
			const bag = this.publishContext?.errors;
			const normalized =
				bag && typeof bag === 'object' && 'value' in bag ? bag.value : bag;
			if (!normalized || typeof normalized !== 'object') {
				return [];
			}

			const list = normalized[fullKey];

			return Array.isArray(list) ? list : [];
		},

		errsForNumberMajor(major) {
			if (this.fc.stores_number_as_flat_scalar) {
				return this.errs('');
			}

			return this.errs(major);
		},

		durationMajorHasError(major) {
			return this.units.some((unit) => this.errs(`${major}.${unit}`).length > 0);
		},

		numberFieldName(major) {
			const n = this.name;

			return n ? `${n}.${major}` : major;
		},

		numberUnitFieldName(key) {
			const n = this.name;

			return n ? `${n}.${key}` : key;
		},

		durationFieldName(major, unit) {
			const n = this.name;

			return n ? `${n}.${major}.${unit}` : `${major}.${unit}`;
		},

		inputId(major) {
			return `${this.fieldIdBase}_${major}`;
		},

		durationInputId(major, unit) {
			return `${this.fieldIdBase}_${major}_${unit}`;
		},

		majorLabel(major) {
			if (major === 'min') {
				return this.__('statamic::fieldtypes.quantitative_value.field.minimum');
			}
			if (major === 'max') {
				return this.__('statamic::fieldtypes.quantitative_value.field.maximum');
			}

			return this.__('statamic::fieldtypes.quantitative_value.field.value');
		},

		durationUnitLabel(unit) {
			return this.__('statamic::fieldtypes.quantitative_value.field.duration_unit.' + unit);
		},

		unitTitle(unit) {
			return this.durationUnitLabel(unit);
		},

		durationFlatLabel(major, unit) {
			const unitLabel = this.durationUnitLabel(unit);
			if (major === 'value') {
				return this.__('statamic::fieldtypes.quantitative_value.field.duration_flat_label_value', {
					unit: unitLabel,
					label: this.majorLabel('value'),
				});
			}
			if (major === 'min') {
				return this.__('statamic::fieldtypes.quantitative_value.field.duration_flat_label_min', {
					unit: unitLabel,
					label: this.majorLabel('min'),
				});
			}

			return this.__('statamic::fieldtypes.quantitative_value.field.duration_flat_label_max', {
				unit: unitLabel,
				label: this.majorLabel('max'),
			});
		},

		numberDisplay(major) {
			const v = this.value && typeof this.value === 'object' ? this.value[major] : null;
			if (v === null || v === undefined || v === '') return '';
			return v;
		},

		unitMetadataDisplay(key) {
			if (!this.value || typeof this.value !== 'object') {
				return '';
			}
			if (!Object.prototype.hasOwnProperty.call(this.value, key)) {
				return '';
			}
			const v = this.value[key];
			if (v === null || v === undefined || v === '') {
				return '';
			}
			return v;
		},

		durationDisplay(major, unit) {
			const block = this.value && typeof this.value === 'object' ? this.value[major] : null;
			if (!block || typeof block !== 'object') return '';
			const v = block[unit];
			if (v === null || v === undefined || v === '') return '';
			return v;
		},

		onNumberModelUpdate(major, raw) {
			const next = { ...(this.value && typeof this.value === 'object' ? this.value : {}) };
			if (raw === '' || raw === null || raw === undefined) {
				delete next[major];
			} else if (this.isFloat) {
				const n = parseFloat(String(raw));
				next[major] = Number.isNaN(n) ? raw : n;
			} else {
				next[major] = parseInt(String(raw), 10);
			}
			this.update(next);
		},

		onUnitCodeUpdate(raw) {
			const next = { ...(this.value && typeof this.value === 'object' ? this.value : {}) };
			const s = raw === null || raw === undefined ? '' : String(raw);
			if (s.trim() === '') {
				delete next.unit_code;
			} else {
				next.unit_code = s;
			}
			this.update(next);
		},

		onUnitTextUpdate(raw) {
			const next = { ...(this.value && typeof this.value === 'object' ? this.value : {}) };
			const s = raw === null || raw === undefined ? '' : String(raw);
			if (s.trim() === '') {
				delete next.unit_text;
			} else {
				next.unit_text = s;
			}
			this.update(next);
		},

		onDurationModelUpdate(major, unit, raw) {
			const root = { ...(this.value && typeof this.value === 'object' ? this.value : {}) };
			const block = { ...(root[major] && typeof root[major] === 'object' ? root[major] : {}) };
			if (raw === '' || raw === null || raw === undefined) {
				delete block[unit];
			} else {
				block[unit] = parseInt(String(raw), 10);
			}
			root[major] = block;
			this.update(root);
		},
	},
};
</script>
