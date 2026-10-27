/**
 * Derive the `{ value, label }` options for the custom Date fieldtype's
 * `earliest_date_field` / `latest_date_field` boundary selects (UAMS-Web/uams-statamic-archive#1393) — the OTHER
 * `date` fields of the blueprint being edited.
 *
 * The sibling-field list comes from the blueprint builder's undocumented
 * `suggestableConditionFieldsProvider` inject (see
 * `resources/js/components/fieldtypes/DateFieldSelectFieldtype.vue`). Each entry is a
 * raw blueprint-builder field whose top-level `type` is the STRUCTURAL kind
 * (`inline` / `reference` / `import`, per `Statamic\Fields\FieldTransformer`) and
 * whose FIELDTYPE lives at `config.type` — so the fieldtype is matched there, exactly
 * as Statamic's own conditions builder (`Condition.vue`) reads it, not at the
 * top-level `type`. Keeping the derivation here — pure, with no Vue/inject dependency
 * — makes it unit-testable in isolation (see `date-field-options.test.js`), mirroring
 * the `date-boundary-engine.js` pattern; the component stays a thin shell around it.
 *
 * @typedef {{ handle?: string, type?: string, fieldtype?: string, config?: { type?: string, display?: string } }} BuilderField
 *
 * @param {*} siblingFields  the builder's flat field list (anything non-array → [])
 * @param {object} [options]
 * @param {string|null} [options.excludeHandle]  a handle to omit (the field being edited, so a boundary can't reference itself)
 * @returns {Array<{ value: string, label: string }>} date-field options, one per sibling date field
 */
export function dateFieldOptions(siblingFields, { excludeHandle = null } = {}) {
	if (!Array.isArray(siblingFields)) {
		return [];
	}

	return siblingFields
		.filter((field) => field && field.config && field.config.type === 'date')
		.filter((field) => typeof field.handle === 'string' && field.handle !== '' && field.handle !== excludeHandle)
		.map((field) => ({
			value: field.handle,
			label: (field.config && field.config.display) || field.handle,
		}));
}

/**
 * Walk a Vue instance's ancestor chain for the enclosing Replicator/Bard set's
 * sibling-field list.
 *
 * A `date` field nested in a set cannot use the top-level provider path: the
 * injected `suggestableConditionFieldsProvider` is the set-scoped variant, whose
 * `suggestableFields(vm)` dereferences `vm.section.fields` and throws on the no-arg
 * call this config field can only make (see
 * `resources/js/components/fieldtypes/DateFieldSelectFieldtype.vue`). Statamic
 * instead threads the set's own fields down onto the field-settings editor
 * (`components/fields/Settings.vue`) as a `suggestableConditionFields` array, so read
 * that off the nearest ancestor carrying it. Kept here — pure, walking only
 * `$parent` / `suggestableConditionFields` — so the set-nesting branch is unit-
 * testable without mounting the CP (mirroring `dateFieldOptions()`). The provider
 * and this threaded list are undocumented internal Statamic APIs (UAMS-Web/uams-statamic-archive#1498), so the
 * walk stays defensive: a non-array `suggestableConditionFields` is skipped and a
 * chain with no match returns `null` (the empty-state fallback).
 *
 * @param {*} startVm  a Vue instance-like node; the walk follows `.$parent`
 * @returns {Array<BuilderField>|null} the nearest ancestor's `suggestableConditionFields`, or `null` if none carries one
 */
export function findSetSiblingFields(startVm) {
	for (let vm = startVm; vm; vm = vm.$parent) {
		if (Array.isArray(vm.suggestableConditionFields)) {
			return vm.suggestableConditionFields;
		}
	}

	return null;
}
