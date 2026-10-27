<template>
	<div class="w-full">
		<Combobox
			:model-value="value"
			:options="options"
			:placeholder="__('Select a date field')"
			:adaptive-width="true"
			:read-only="isReadOnly"
			@update:model-value="fieldSelected"
		>
			<template #no-options>
				<div class="px-2 py-1 text-sm text-gray-500 dark:text-dark-150" v-text="__('No other date fields in this blueprint.')" />
			</template>
			<template #option="option">
				<div class="flex items-center">
					<span v-text="option.label" />
					<span
						v-if="option.label !== option.value"
						v-text="option.value"
						class="ml-2 font-mono text-2xs text-gray-500 dark:text-dark-150"
					/>
				</div>
			</template>
			<template #selected-option="{ option }">
				<div class="truncate" v-text="option?.label ?? option?.value ?? value" />
			</template>
		</Combobox>
	</div>
</template>

<script>
/**
 * CP config fieldtype `date_field_select` (App\Fieldtypes\DateFieldSelect) — the
 * select behind the custom Date fieldtype's `earliest_date_field` /
 * `latest_date_field` boundary config (UAMS-Web/uams-statamic-archive#1393). It lists the OTHER `date` fields of
 * the blueprint being edited so an editor choosing "Date From a Form Field" can
 * pick a sibling date field as a relative boundary.
 *
 * The server cannot populate this list (the config schema is built in isolation —
 * see App\Fieldtypes\DateFieldSelect), so it is derived client-side. Resolution
 * depends on where the edited date field lives:
 *
 * - At the TOP LEVEL of a blueprint, the blueprint builder's
 *   `suggestableConditionFieldsProvider` inject exposes the sibling-field list; its
 *   `suggestableFields(vm)` ignores the `vm` argument, so the no-arg call returns
 *   every blueprint field.
 * - INSIDE a Replicator/Bard set, the nearest injected provider is the set-scoped
 *   variant, whose `suggestableFields(vm)` reads `vm.section.fields` and THROWS on
 *   the no-arg call this config field can only make (UAMS-Web/uams-statamic-archive#1502). There the set's own
 *   sibling fields are read from the ancestor chain instead — Statamic threads them
 *   onto the field-settings editor (`components/fields/Settings.vue`) as a
 *   `suggestableConditionFields` array — via the pure `findSetSiblingFields()`.
 *
 * The `isInsideSet` inject (provided by Statamic's `SetsFieldtype`) selects between
 * the two. Both the provider and the threaded list are INTERNAL, UNDOCUMENTED
 * Statamic APIs (UAMS-Web/uams-statamic-archive#1498), so they are accessed defensively (optional chaining, a
 * `try/catch`, array guards) and may need revisiting across Statamic upgrades. The
 * option derivation and the ancestor walk are the pure, unit-tested helpers in
 * `resources/js/date-field-options.js`; this component is a thin shell around them.
 */
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Combobox, publishContextKey } from '@statamic/cms/ui';

import { dateFieldOptions, findSetSiblingFields } from '../../date-field-options.js';

export default {
	name: 'DateFieldSelectFieldtype',

	components: { Combobox },

	mixins: [Fieldtype],

	inject: {
		// The blueprint builder provides this; it exposes `.suggestableFields()`,
		// the flat list of the builder's sibling fields.
		suggestableConditionFieldsProvider: { default: null },
		// True when this config field is edited inside a Replicator/Bard set
		// (provided by Statamic's SetsFieldtype). Switches sibling-field resolution
		// to the set-scoped path, which the top-level provider cannot serve. (UAMS-Web/uams-statamic-archive#1502)
		isInsideSet: { default: false },
		// The field-settings publish context, used only to read the edited field's
		// own handle so a boundary cannot reference itself.
		publishContext: { from: publishContextKey, default: null },
	},

	computed: {
		/** The blueprint's other date fields as `{ value, label }` options. */
		options() {
			return dateFieldOptions(this.siblingFields, { excludeHandle: this.editedFieldHandle });
		},

		/**
		 * The sibling-field list to derive options from, resolved by nesting context.
		 *
		 * Inside a Replicator/Bard set the injected provider is the set-scoped variant,
		 * whose `suggestableFields(vm)` reads `vm.section.fields` and THROWS on the
		 * no-arg call this config field can only make — so the set's own sibling fields
		 * are read from the ancestor chain via `findSetSiblingFields()` instead (UAMS-Web/uams-statamic-archive#1502).
		 * At the top level the blueprint provider ignores its `vm` argument, so the
		 * no-arg call is safe; it is still guarded so an unexpected throw degrades to
		 * the empty state rather than crashing the field-settings panel (UAMS-Web/uams-statamic-archive#1498).
		 */
		siblingFields() {
			if (this.isInsideSet) {
				return findSetSiblingFields(this.$parent) ?? [];
			}

			try {
				return this.suggestableConditionFieldsProvider?.suggestableFields?.() ?? [];
			} catch {
				return [];
			}
		},

		/** Read-only when the field config marks it so (mirrors the base fieldtypes). */
		isReadOnly() {
			return this.config?.read_only === true || this.readOnly === true;
		},

		/** The handle of the field currently being edited (to exclude it from the list). */
		editedFieldHandle() {
			const context = this.publishContext;

			if (!context) {
				return null;
			}

			const values = context.values?.value ?? context.values;

			return values && typeof values === 'object' && typeof values.handle === 'string' ? values.handle : null;
		},
	},

	methods: {
		fieldSelected(handle) {
			this.update(handle);
		},
	},
};
</script>
