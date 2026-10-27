<!--
 * Chained CTDL class selector: CredentialRegistry top-level ceterms classes,
 * then subclasses per selection. Stores dynamic ceterms_*_type keys and `value`.
 * Subfields sit in a bordered group (same pattern as SubfieldContainer /
 * SchemaObjectFieldtype properties block).
 -->
<template>
	<section class="w-full">
		<div class="bg-white dark:bg-gray-800 dark:border-dark-900 rounded-lg border">
			<div class="publish-fields pt-4 px-4 py-4 space-y-2">
				<div
					v-for="row in rows"
					:key="row.key"
					class="min-w-0 form-group select-fieldtype field-w-undefined"
					data-ui-input-group
				>
					<div data-ui-field-text="" class="mb-1.5">
						<label
							:for="inputId(row.key)"
							class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
							data-ui-label=""
						>
							<span>{{ row.label }}</span>
						</label>
					</div>
					<div dir="ltr">
						<Combobox
							:id="inputId(row.key)"
							:model-value="comboboxModelFor(row.key)"
							:options="row.comboboxOptions"
							:placeholder="__('Select…')"
							:clearable="true"
							:disabled="isReadOnly"
							:read-only="isReadOnly"
							:searchable="true"
							:adaptive-width="true"
							@update:model-value="(v) => onLevelChange(row, v)"
						/>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Combobox } from '@statamic/cms/ui';

const NONE_SENTINEL = '__ctdl_none__';

/**
 * Convert PascalCase CTDL slug to snake_case for subfield keys.
 *
 * @param {string} slug
 * @returns {string}
 */
function pascalSlugToSnake(slug) {
	return slug
		.replace(/([a-z\d])([A-Z])/g, '$1_$2')
		.replace(/([A-Z]+)([A-Z][a-z])/g, '$1_$2')
		.toLowerCase();
}

/**
 * Strip ceterms: prefix and return local class name.
 *
 * @param {string} shorthand
 * @returns {string}
 */
function shorthandToSlug(shorthand) {
	const i = shorthand.indexOf(':');
	return i === -1 ? shorthand : shorthand.slice(i + 1);
}

export default {
	components: { Combobox },

	mixins: [Fieldtype],

	watch: {
		/**
		 * When only one root class is allowed, keep `ceterms_type` fixed (hidden UI).
		 *
		 * @param {string|null} slug
		 */
		hiddenFixedTopSlug: {
			immediate: true,
			handler(slug) {
				if (slug === null || slug === undefined) {
					return;
				}

				if (this.isReadOnly) {
					return;
				}

				if (this.stored.ceterms_type === slug) {
					return;
				}

				const next = { ...this.stored, ceterms_type: slug };
				this.pruneDescendants(next, 'ceterms_type');
				this.applyResolvedValue(next);
				this.update(next);
			},
		},
	},

	computed: {
		/**
		 * Preload payload from the PHP fieldtype (hierarchy + labels).
		 *
		 * @returns {object}
		 */
		pre() {
			return this.meta || {};
		},

		/**
		 * Working copy of the stored object for two-way binding.
		 *
		 * @returns {object}
		 */
		stored() {
			return this.value && typeof this.value === 'object' && !Array.isArray(this.value)
				? this.value
				: {};
		},

		/**
		 * Allowed top-level slugs from blueprint config (default: all in preload).
		 *
		 * @returns {string[]}
		 */
		allowedCetermsSlugs() {
			const all = (this.pre.top_level || []).map((o) => o.key);
			const f = this.config?.ceterms_type_filter;

			if (!Array.isArray(f) || f.length === 0) {
				return all;
			}

			const intersect = f.filter((s) => all.includes(s));

			return intersect.length > 0 ? intersect : all;
		},

		/**
		 * True when the top combobox is hidden (single allowed root class).
		 *
		 * @returns {boolean}
		 */
		hideTopLevelCetermsType() {
			return this.allowedCetermsSlugs.length === 1;
		},

		/**
		 * Sole root slug when the top control is hidden; otherwise null.
		 *
		 * @returns {string|null}
		 */
		hiddenFixedTopSlug() {
			return this.hideTopLevelCetermsType ? this.allowedCetermsSlugs[0] : null;
		},

		/**
		 * Top-level options filtered by blueprint allowlist.
		 *
		 * @returns {Array<{key: string, value: string}>}
		 */
		filteredTopLevelOptions() {
			const allow = new Set(this.allowedCetermsSlugs);

			return (this.pre.top_level || []).filter((o) => allow.has(o.key));
		},

		/**
		 * Build each visible tier: first `ceterms_type`, then ceterms_*_type keys.
		 *
		 * @returns {Array<{key: string, label: string, comboboxOptions: Array, options: Array}>}
		 */
		rows() {
			const meta = this.pre;
			const childrenBy = meta.children_by_shorthand || {};
			const labelsBy = meta.labels_by_slug || {};
			const v = this.stored;

			const tiers = [];

			// First tier: optional when multiple roots are allowed (filtered list)
			if (!this.hideTopLevelCetermsType) {
				tiers.push({
					key: 'ceterms_type',
					label: __('CTDL class'),
					options: this.filteredTopLevelOptions,
				});
			}

			// Walk deeper while a parent shorthand has children and a selection exists
			let parentShorthand = v.ceterms_type ? `ceterms:${v.ceterms_type}` : null;

			while (parentShorthand) {
				const kids = childrenBy[parentShorthand];
				if (!kids || !kids.length) {
					break;
				}

				const parentSlug = shorthandToSlug(parentShorthand);
				const key = `ceterms_${pascalSlugToSnake(parentSlug)}_type`;
				const parentLabel = labelsBy[parentSlug] || parentSlug;

				tiers.push({
					key,
					label: `${parentLabel} ${__('Type')}`,
					options: kids,
				});

				const selected = v[key];

				// Stop if "none" or not yet chosen (still show this tier only)
				if (selected === false || selected === undefined || selected === null || selected === '') {
					break;
				}

				parentShorthand = `ceterms:${selected}`;
			}

			return tiers.map((t) => ({
				...t,
				comboboxOptions: this.withNoneComboboxOptions(t.options),
			}));
		},
	},

	methods: {
		/**
		 * Stable id for label/input association.
		 *
		 * @param {string} key
		 * @returns {string}
		 */
		inputId(key) {
			return `${this.fieldId}-${key}`;
		},

		/**
		 * Map preload rows to Combobox options plus the None row.
		 *
		 * @param {Array<{key: string, value: string}>} options
		 * @returns {Array<{value: string, label: string}>}
		 */
		withNoneComboboxOptions(options) {
			const base = (options || []).map((o) => ({
				value: o.key,
				label: o.value,
			}));

			return [
				{
					value: NONE_SENTINEL,
					label: __('None — No available option is relevant to this entity'),
				},
				...base,
			];
		},

		/**
		 * Current combobox value for a tier (slug, sentinel, or null).
		 *
		 * @param {string} key
		 * @returns {string|null}
		 */
		comboboxModelFor(key) {
			const raw = this.stored[key];

			if (raw === false) {
				return NONE_SENTINEL;
			}

			if (raw === undefined || raw === null || raw === '') {
				return null;
			}

			return raw;
		},

		/**
		 * Handle selection for one tier: update chain, prune descendants, set `value`.
		 *
		 * @param {{key: string}} row
		 * @param {string|null} picked
		 */
		onLevelChange(row, picked) {
			const next = { ...this.stored };

			if (picked === null || picked === undefined || picked === '') {
				delete next[row.key];
				this.pruneDescendants(next, row.key);
				this.applyResolvedValue(next);
				this.update(next);
				return;
			}

			if (picked === NONE_SENTINEL) {
				next[row.key] = false;
				this.pruneDescendants(next, row.key);
				this.applyResolvedValue(next);
				this.update(next);
				return;
			}

			next[row.key] = picked;
			this.pruneDescendants(next, row.key);
			this.applyResolvedValue(next);
			this.update(next);
		},

		/**
		 * Set or remove `value` from the resolved slug.
		 *
		 * @param {object} data
		 */
		applyResolvedValue(data) {
			const resolved = this.computeResolvedValue(data);

			if (resolved === null || resolved === undefined || resolved === '') {
				delete data.value;
			} else {
				data.value = resolved;
			}
		},

		/**
		 * Drop tier keys that are no longer on the path after `editedKey`.
		 *
		 * @param {object} data
		 * @param {string} editedKey
		 */
		pruneDescendants(data, editedKey) {
			const order = this.chainKeyOrder(data);
			const idx = order.indexOf(editedKey);

			if (idx === -1) {
				return;
			}

			const keep = new Set(order.slice(0, idx + 1));

			Object.keys(data).forEach((k) => {
				if (k === 'value') {
					return;
				}

				if (k === 'ceterms_type' || /^ceterms_.*_type$/.test(k)) {
					if (!keep.has(k)) {
						delete data[k];
					}
				}
			});
		},

		/**
		 * Ordered chain keys present or implied for pruning.
		 *
		 * @param {object} data
		 * @returns {string[]}
		 */
		chainKeyOrder(data) {
			const meta = this.pre;
			const childrenBy = meta.children_by_shorthand || {};
			const keys = ['ceterms_type'];

			if (!data.ceterms_type) {
				return keys;
			}

			let parentShorthand = `ceterms:${data.ceterms_type}`;

			while (parentShorthand) {
				const kids = childrenBy[parentShorthand];
				if (!kids || !kids.length) {
					break;
				}

				const parentSlug = shorthandToSlug(parentShorthand);
				const tierKey = `ceterms_${pascalSlugToSnake(parentSlug)}_type`;

				keys.push(tierKey);

				const selected = data[tierKey];

				if (selected === false || selected === undefined || selected === null || selected === '') {
					break;
				}

				parentShorthand = `ceterms:${selected}`;
			}

			return keys;
		},

		/**
		 * Compute the resolved CTDL class slug for `value`.
		 *
		 * @param {object} data
		 * @returns {string|null}
		 */
		computeResolvedValue(data) {
			const order = this.chainKeyOrder(data);
			let previous = null;

			for (let i = 0; i < order.length; i++) {
				const k = order[i];
				const part = data[k];

				if (part === false) {
					return previous;
				}

				if (typeof part === 'string' && part !== '') {
					previous = part;
				}
			}

			return previous;
		},
	},
};
</script>
