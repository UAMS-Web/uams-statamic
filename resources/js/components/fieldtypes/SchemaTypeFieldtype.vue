<!--
 * Schema.org type: chained drilldown from configured starting type(s), or
 * single combobox over the starting type(s) plus all descendants.
-->
<template>
	<section class="w-full">
		<!-- Single mode: one combobox (value is string|string[] at field root) -->
		<template v-if="isSingleMode">
			<div
				:class="['min-w-0 form-group select-fieldtype field-w-undefined', fieldErrors.length ? 'has-error' : '']"
				data-ui-input-group
			>
				<!-- Label and instructions come from Statamic publish field chrome -->
				<div dir="ltr">
					<schema-org-type-combobox
						:id="fieldIdBase"
						:model-value="singleComboboxModel"
						:options="singleComboboxOptions"
						:placeholder="singlePlaceholder"
						:clearable="singleClearable"
						:disabled="isReadOnly"
						:read-only="isReadOnly"
						:multiple="singleMultiple"
						:searchable="singleSearchable"
						:adaptive-width="true"
						@update:model-value="onSingleModelUpdate"
					/>
				</div>
				<div v-if="fieldErrors.length">
					<ErrorMessage
						v-for="(error, i) in fieldErrors"
						class="mt-2"
						:key="i"
						:text="error"
					/>
				</div>
				<schema-org-docs-alert
					v-if="singleSelectedTypes.length > 0"
					variant="types"
					:items="singleSelectedTypes"
					:descriptions-by-name="typeDescriptions"
					:multi-entity-title="__('statamic::fieldtypes.schema_type.field.multi_typed_entity')"
					:use-multi-entity-when-multiple="singleMultiple"
				/>
			</div>
		</template>

		<!-- Chained mode: group shell + tier comboboxes -->
		<template v-else>
			<group-fieldtype-shell>
				<div
					v-for="row in chainedRows"
					:key="row.key"
					class="min-w-0 form-group select-fieldtype field-w-undefined"
					data-ui-input-group
				>
					<div data-ui-field-text="" class="mb-1.5">
						<Label
							:for="tierInputId(row.tierDepth)"
							class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
							data-ui-label=""
							:required="chainedTierRequired"
						>{{ row.label }}</Label>
					</div>
					<div dir="ltr">
						<schema-org-type-combobox
							:id="tierInputId(row.tierDepth)"
							:model-value="comboboxModelForTier(row)"
							:options="row.comboboxOptions"
							:placeholder="__('statamic::fieldtypes.schema_type.field.select_placeholder')"
							:clearable="chainedClearable"
							:disabled="isReadOnly"
							:read-only="row.readOnly || isReadOnly"
							:multiple="false"
							:searchable="chainedSearchable"
							:adaptive-width="true"
							@update:model-value="(v) => onChainedTierChange(row, v)"
						/>
					</div>
					<schema-org-docs-alert
						v-if="chainedAlertTypeName(row)"
						variant="types"
						:items="[chainedAlertTypeName(row)]"
						:descriptions-by-name="typeDescriptions"
						multi-entity-title=""
						:use-multi-entity-when-multiple="false"
					/>
				</div>
			</group-fieldtype-shell>
			<div v-if="fieldErrors.length && !chainedRows.length">
				<ErrorMessage
					v-for="(error, i) in fieldErrors"
					class="mt-2"
					:key="'fe' + i"
					:text="error"
				/>
			</div>
		</template>
	</section>
</template>

<script>
import { getCurrentInstance, inject, computed } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { ErrorMessage, Label, publishContextKey } from '@statamic/cms/ui';
import SchemaOrgDocsAlert from './partials/SchemaOrgDocsAlert.vue';
import SchemaOrgTypeCombobox from './partials/SchemaOrgTypeCombobox.vue';
import { reconcileNoneExclusivity } from './partials/schemaNoneExclusivity.js';

/** Sentinel combobox value meaning “stop drilling; keep parent type”. */
const STOP_SENTINEL = '__schema_type_stop__';

/**
 * Statamic fieldtype: schema.org type picker (chained tiers or single list).
 */
export default {
	components: {
		ErrorMessage,
		Label,
		SchemaOrgDocsAlert,
		SchemaOrgTypeCombobox,
	},

	mixins: [Fieldtype],

	data() {
		return {
			chainedSelections: [],
			chainedChainComplete: false,
		};
	},

	setup() {
		const instance = getCurrentInstance().proxy;
		const publishContext = inject(publishContextKey);

		const fieldErrors = computed(() => {
			// Read the publish error bag from CP context
			const errs = publishContext?.errors;
			// Normalize bag shape when Statamic wraps errors under `value`
			const errsVal =
				errs && typeof errs === 'object' && 'value' in errs ? errs.value : errs;
			// Return no messages when the bag is missing
			if (!errsVal) {
				return [];
			}
			// Resolve this field’s handle for keyed errors
			const key = instance.handle ?? instance.name ?? '';

			return errsVal[key] ?? [];
		});

		return { fieldErrors };
	},

	computed: {
		isSingleMode() {
			return (this.config?.mode ?? 'chained') === 'single';
		},

		fieldValidateHasRequired() {
			const v = this.config?.validate;
			if (Array.isArray(v)) {
				return v.includes('required');
			}
			if (typeof v === 'string' && v !== '') {
				return v.split('|').some((s) => s.trim() === 'required');
			}

			return false;
		},

		fieldMarkedRequired() {
			return !!(this.config?.required || this.fieldValidateHasRequired);
		},

		chainedTierRequired() {
			return !this.isSingleMode && this.fieldMarkedRequired;
		},

		resolvedStarts() {
			const raw = this.config?.starting_types;
			// Fall back to Thing when config omits or empties starting types
			if (!Array.isArray(raw) || raw.length === 0) {
				return ['Thing'];
			}
			// Deduplicate non-empty string handles from blueprint config
			const out = [];
			raw.forEach((t) => {
				if (typeof t === 'string' && t !== '' && !out.includes(t)) {
					out.push(t);
				}
			});

			return out.length > 0 ? out : ['Thing'];
		},

		subtreeTypeNames() {
			return Array.isArray(this.meta?.subtree_type_names) ? this.meta.subtree_type_names : [];
		},

		chainedNextOptionsByParent() {
			return this.meta?.chained_next_options_by_parent &&
				typeof this.meta.chained_next_options_by_parent === 'object'
				? this.meta.chained_next_options_by_parent
				: {};
		},

		singleMultiple() {
			if (!this.isSingleMode) {
				return false;
			}

			return this.config?.multiple !== false;
		},

		singleClearable() {
			return this.config?.clearable !== false;
		},

		singleSearchable() {
			return this.config?.searchable !== false;
		},

		/** Reserved stored value for the explicit "None applies" choice. */
		noneValue() {
			return typeof this.meta?.none_value === 'string' && this.meta.none_value !== ''
				? this.meta.none_value
				: '__none__';
		},

		/** Whether the explicit "None applies" choice is offered (single mode only). */
		noneOptionEnabled() {
			return this.isSingleMode && !!this.meta?.none_option;
		},

		/** Label for the explicit "None applies" choice. */
		noneOptionLabel() {
			return typeof this.meta?.none_option_label === 'string' && this.meta.none_option_label !== ''
				? this.meta.none_option_label
				: 'None — no available option is relevant';
		},

		singlePlaceholder() {
			return this.singleMultiple
				? this.__('statamic::fieldtypes.schema_type.field.select_types')
				: this.__('statamic::fieldtypes.schema_type.field.select_a_type');
		},

		singleComboboxOptions() {
			// Prefer PHP merge of subtree types and enumeration members
			const metaVals = this.meta?.single_select_values;
			const base =
				Array.isArray(metaVals) && metaVals.length > 0
					? metaVals.map((t) => ({ value: t, label: t }))
					: this.subtreeTypeNames.map((t) => ({ value: t, label: t }));

			// Append the explicit "None applies" choice when enabled
			if (this.noneOptionEnabled) {
				base.push({ value: this.noneValue, label: this.noneOptionLabel });
			}

			return base;
		},

		singleComboboxModel() {
			const v = this.value;
			if (this.singleMultiple) {
				return Array.isArray(v) ? v : [];
			}
			return typeof v === 'string' && v !== '' ? v : '';
		},

		/**
		 * Type and enumeration-member descriptions from fieldtype preload (HTML).
		 *
		 * @returns {Record<string, string>}
		 */
		typeDescriptions() {
			return this.meta?.type_descriptions && typeof this.meta.type_descriptions === 'object'
				? this.meta.type_descriptions
				: {};
		},

		/**
		 * Selected type name(s) in single mode for alert content.
		 *
		 * @returns {string[]}
		 */
		singleSelectedTypes() {
			if (!this.isSingleMode) {
				return [];
			}
			const v = this.value;
			if (this.singleMultiple) {
				return Array.isArray(v)
					? v.filter((t) => typeof t === 'string' && t !== '' && t !== this.noneValue)
					: [];
			}

			return typeof v === 'string' && v !== '' && v !== this.noneValue ? [v] : [];
		},

		chainedClearable() {
			return this.config?.clearable !== false;
		},

		chainedSearchable() {
			return this.config?.searchable !== false;
		},

		fieldIdBase() {
			const h = this.handle ?? this.config?.handle ?? this.name ?? 'schema_type';
			return String(this.id || `field_${h.replace(/[^a-z0-9_]/gi, '_')}`).replace(/\./g, '_');
		},

		/**
		 * Rows driving chained combobox tiers (starting row plus subtype rows).
		 *
		 * @returns {Array<{ key: string, tierDepth: number, withSentinel: boolean, readOnly: boolean, label: string, comboboxOptions: Array<{ value: string, label: string }> }>}
		 */
		chainedRows() {
			const starts = this.resolvedStarts;
			const sel = Array.isArray(this.chainedSelections) ? [...this.chainedSelections] : [];
			const complete = !!this.chainedChainComplete;
			const rows = [];

			// Build tier zero: editable when multiple roots, read-only when one
			if (starts.length > 1) {
				rows.push({
					key: 'tier_0',
					tierDepth: 0,
					withSentinel: false,
					readOnly: false,
					label: this.__('statamic::fieldtypes.schema_type.field.starting_type'),
					comboboxOptions: starts.map((t) => ({ value: t, label: t })),
				});
			} else {
				rows.push({
					key: 'tier_0_single_root',
					tierDepth: 0,
					withSentinel: false,
					readOnly: false,
					label: this.__('statamic::fieldtypes.schema_type.field.starting_type'),
					comboboxOptions: starts.map((t) => ({ value: t, label: t })),
				});
			}

			let tierDepth = 1;

			// Append deeper tiers while selections and next options exist
			while (tierDepth < 500) {
				// Wait until the editor has chosen the parent for this depth
				if (sel.length < tierDepth) {
					break;
				}

				const parent = sel[tierDepth - 1];
				// Load merged subtype and enumeration member rows for the parent
				const optionRows = this.chainedNextOptionRows(parent);

				// Stop when nothing further can be selected under the parent
				if (!optionRows.length) {
					break;
				}

				const opts = this.withStopComboboxOptions(optionRows, parent);

				rows.push({
					key: `tier_${tierDepth}`,
					tierDepth,
					withSentinel: true,
					readOnly: false,
					label: this.__('statamic::fieldtypes.schema_type.field.subtype_of', { type: parent }),
					comboboxOptions: opts,
				});

				// Stop when the chain has not advanced past this depth yet
				if (sel.length === tierDepth) {
					break;
				}

				// Stop when sentinel ended the chain one step above this tier
				if (complete && sel.length === tierDepth + 1) {
					break;
				}

				tierDepth += 1;
			}

			return rows;
		},
	},

	watch: {
		value: {
			deep: true,
			immediate: true,
			handler(val) {
				// Chained state is owned locally; single mode uses the root value
				if (this.isSingleMode) {
					return;
				}
				// Extract selections array from the stored object shape
				const s =
					val && typeof val === 'object' && !Array.isArray(val) ? val.selections : null;
				this.chainedSelections = Array.isArray(s) ? [...s] : [];
				this.chainedChainComplete = !!(val && typeof val === 'object' && val.chain_complete);
				this.$nextTick(() => this.maybeApplySingleStartingTypeDefault());
			},
		},
	},

	mounted() {
		this.$nextTick(() => this.maybeApplySingleStartingTypeDefault());
	},

	methods: {
		/**
		 * When required + chained + exactly one root starting type, pre-select it if still empty
		 * (mirrors PHP defaultValue; covers CP timing where value arrives empty once).
		 */
		maybeApplySingleStartingTypeDefault() {
			if (this.isSingleMode || !this.fieldMarkedRequired || this.isReadOnly) {
				return;
			}

			const starts = this.resolvedStarts;

			if (starts.length !== 1) {
				return;
			}

			if (this.chainedSelections.length > 0) {
				return;
			}

			const only = starts[0];

			this.chainedSelections = [only];
			this.chainedChainComplete = false;
			this.emitChainedPayload();
		},

		/**
		 * Selected type/member for the chained-tier docs alert (omit when stop sentinel).
		 *
		 * @param {{ tierDepth: number, withSentinel: boolean }} row Tier row metadata
		 * @returns {string|null}
		 */
		chainedAlertTypeName(row) {
			const d = row.tierDepth;
			const sel = this.chainedSelections;

			if (row.withSentinel) {
				const picked = sel[d];
				// Real subtype/member only; no alert when “stop here” sentinel is chosen
				if (typeof picked === 'string' && picked !== '' && picked !== STOP_SENTINEL) {
					return picked;
				}

				return null;
			}

			const v = sel[d];

			return typeof v === 'string' && v !== '' ? v : null;
		},

		/**
		 * @param {string} parentType Schema type name whose next options are shown
		 * @returns {Array<{ value: string, label: string }>}
		 */
		chainedNextOptionRows(parentType) {
			const rows = this.chainedNextOptionsByParent[parentType];

			return Array.isArray(rows) ? rows : [];
		},

		/**
		 * @param {Array<{ value: string, label: string }>} optionRows Subtype and member rows
		 * @param {string} parentType Parent label for the stop sentinel text
		 * @returns {Array<{ value: string, label: string }>}
		 */
		withStopComboboxOptions(optionRows, parentType) {
			const base = Array.isArray(optionRows) ? optionRows : [];

			return [
				{
					value: STOP_SENTINEL,
					label: this.__('statamic::fieldtypes.schema_type.field.stop_here_use_type', {
						type: parentType,
					}),
				},
				...base,
			];
		},

		tierInputId(tierDepth) {
			return `${this.fieldIdBase}_tier_${tierDepth}`;
		},

		/**
		 * @param {{ tierDepth: number, withSentinel: boolean }} row Tier metadata
		 * @returns {string|null}
		 */
		comboboxModelForTier(row) {
			const d = row.tierDepth;
			const sel = this.chainedSelections;

			if (!row.withSentinel) {
				// Starting tier: bind directly to the selection slot
				if (sel[d] != null && sel[d] !== '') {
					return sel[d];
				}

				return null;
			}

			// Deeper tiers: bind to the chosen subtype or member when set
			if (sel[d] != null && sel[d] !== '') {
				return sel[d];
			}

			// Represent sentinel selection when the editor stopped at the parent
			if (this.chainedChainComplete && sel.length === d) {
				return STOP_SENTINEL;
			}

			return null;
		},

		emitChainedPayload() {
			const sel = [...this.chainedSelections];
			let value = null;

			// Derive the terminal type or member from the deepest selection
			if (sel.length > 0) {
				value = sel[sel.length - 1];
			}

			const payload = {
				selections: sel,
			};

			if (value != null && value !== '') {
				payload.value = value;
			}

			if (this.chainedChainComplete) {
				payload.chain_complete = true;
			}

			this.update(payload);
		},

		/**
		 * @param {{ tierDepth: number }} row Active tier row
		 * @param {string|null|undefined} picked Value from the combobox
		 */
		onChainedTierChange(row, picked) {
			const d = row.tierDepth;
			let next = [...this.chainedSelections];
			let complete = false;

			// Clear this tier and everything deeper when the editor clears input
			if (picked === null || picked === undefined || picked === '') {
				next = next.slice(0, d);
				complete = false;
				this.chainedSelections = next;
				this.chainedChainComplete = complete;
				this.emitChainedPayload();

				return;
			}

			// Truncate after the parent and mark chain complete for sentinel picks
			if (picked === STOP_SENTINEL) {
				next = next.slice(0, d);
				complete = true;
				this.chainedSelections = next;
				this.chainedChainComplete = complete;
				this.emitChainedPayload();

				return;
			}

			// Ignore non-string values from the combobox
			if (typeof picked !== 'string') {
				return;
			}

			// Record the chosen type or member at this depth
			next = [...next.slice(0, d), picked];
			complete = false;
			this.chainedSelections = next;
			this.chainedChainComplete = complete;
			this.emitChainedPayload();
		},

		/**
		 * @param {string|string[]|null|undefined} v Combobox model from single mode
		 */
		onSingleModelUpdate(v) {
			if (this.singleMultiple) {
				let next = Array.isArray(v) ? v : [];

				// None is mutually exclusive with real selections: whichever the
				// editor just added wins (see reconcileNoneExclusivity).
				if (this.noneOptionEnabled) {
					next = reconcileNoneExclusivity(this.value, next, this.noneValue);
				}

				this.update(next);
			} else if (v === null || v === undefined || v === '') {
				this.update(null);
			} else {
				this.update(v);
			}
		},
	},
};
</script>
