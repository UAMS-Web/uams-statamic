import { describe, expect, it } from 'vitest';

import { dateFieldOptions, findSetSiblingFields } from './date-field-options.js';

/**
 * A blueprint-builder field in the REAL shape Statamic emits (per
 * `Statamic\Fields\FieldTransformer` + how `Condition.vue` consumes the same list):
 * the top-level `type` is the STRUCTURAL kind (`inline`), and the FIELDTYPE lives at
 * `config.type` — not at the top-level `type`.
 */
const field = (handle, fieldtype, display) => ({
	handle,
	type: 'inline',
	fieldtype,
	config: display === undefined ? { type: fieldtype } : { type: fieldtype, display },
});

describe('dateFieldOptions', () => {
	it('keeps only date fields and maps them to value/label', () => {
		const fields = [
			field('start', 'date', 'Start Date'),
			field('title', 'text', 'Title'),
			field('finish', 'date', 'Finish Date'),
		];

		expect(dateFieldOptions(fields)).toEqual([
			{ value: 'start', label: 'Start Date' },
			{ value: 'finish', label: 'Finish Date' },
		]);
	});

	it('falls back to the handle when a date field has no display', () => {
		expect(dateFieldOptions([field('due', 'date', undefined)])).toEqual([{ value: 'due', label: 'due' }]);
	});

	it('excludes the field being edited (so a boundary cannot reference itself)', () => {
		const fields = [field('start', 'date', 'Start'), field('end', 'date', 'End')];

		expect(dateFieldOptions(fields, { excludeHandle: 'end' })).toEqual([{ value: 'start', label: 'Start' }]);
	});

	it('drops fields with a missing or empty handle', () => {
		const fields = [
			{ type: 'inline', config: { type: 'date', display: 'No handle' } },
			{ handle: '', type: 'inline', config: { type: 'date', display: 'Empty handle' } },
			field('ok', 'date', 'Kept'),
		];

		expect(dateFieldOptions(fields)).toEqual([{ value: 'ok', label: 'Kept' }]);
	});

	it('returns [] for non-arrays and null/undefined entries', () => {
		expect(dateFieldOptions(null)).toEqual([]);
		expect(dateFieldOptions(undefined)).toEqual([]);
		expect(dateFieldOptions('nope')).toEqual([]);
		expect(dateFieldOptions([null, undefined, field('kept', 'date', 'Kept')])).toEqual([{ value: 'kept', label: 'Kept' }]);
	});

	it('matches the fieldtype at config.type, not the structural top-level type', () => {
		// The fieldtype lives at `config.type`; the top-level `type` is the structural
		// kind (`inline`/`reference`/`import`). A decoy whose structural type is `date`
		// but whose fieldtype is text must NOT be offered, and a real date field
		// (structural `inline`, `config.type: date`) must be — guarding against reading
		// the fieldtype off the wrong key (the UAMS-Web/uams-statamic-archive#1393 empty-select bug).
		const fields = [
			{ handle: 'decoy', type: 'date', config: { type: 'text', display: 'Decoy' } },
			{ handle: 'real', type: 'inline', config: { type: 'date', display: 'Real Date' } },
		];

		expect(dateFieldOptions(fields)).toEqual([{ value: 'real', label: 'Real Date' }]);
	});

	it('includes a boundary-configured date field (its `config.type` stays `date`)', () => {
		// A sibling that itself configures a boundary still has the `date` fieldtype
		// (only its CP component differs to `custom_date`), so it remains selectable.
		expect(dateFieldOptions([field('anchor', 'date', 'Anchor')])).toEqual([{ value: 'anchor', label: 'Anchor' }]);
	});
});

/** A minimal Vue-instance-like ancestor node: an optional field list + a parent link. */
const ancestor = (suggestableConditionFields, parent = null) => ({ suggestableConditionFields, $parent: parent });

describe('findSetSiblingFields', () => {
	it('returns the nearest ancestor’s suggestableConditionFields array', () => {
		const setFields = [field('start', 'date', 'Start')];

		expect(findSetSiblingFields(ancestor(setFields))).toBe(setFields);
	});

	it('walks past ancestors that do not carry the list', () => {
		const setFields = [field('due', 'date', 'Due')];
		// Two intermediate publish wrappers with no field list, then the set editor.
		const chain = { $parent: { $parent: ancestor(setFields) } };

		expect(findSetSiblingFields(chain)).toBe(setFields);
	});

	it('returns the nearest match when several ancestors carry a list (nested sets)', () => {
		const inner = [field('inner', 'date', 'Inner')];
		const outer = [field('outer', 'date', 'Outer')];

		// Inner set's editor is nearer than the outer set's, so its fields win.
		expect(findSetSiblingFields(ancestor(inner, ancestor(outer)))).toBe(inner);
	});

	it('stops at the nearest ancestor even when its field list is empty (no fall-through to an outer set)', () => {
		const outer = [field('outer', 'date', 'Outer')];

		// A nested set with no sibling fields must resolve to [] at the inner boundary,
		// never leak the OUTER set's date fields — the walk stops at the first array,
		// even an empty one.
		expect(findSetSiblingFields(ancestor([], ancestor(outer)))).toEqual([]);
	});

	it('ignores a non-array suggestableConditionFields and keeps walking', () => {
		const setFields = [field('ok', 'date', 'OK')];

		expect(findSetSiblingFields(ancestor('nope', ancestor(setFields)))).toBe(setFields);
		expect(findSetSiblingFields(ancestor(undefined, ancestor(setFields)))).toBe(setFields);
	});

	it('returns null when no ancestor carries the list, or the chain is empty', () => {
		expect(findSetSiblingFields({ $parent: { $parent: null } })).toBeNull();
		expect(findSetSiblingFields(null)).toBeNull();
		expect(findSetSiblingFields(undefined)).toBeNull();
	});

	it('derives in-set date options from the discovered set fields', () => {
		// The set's field list as Statamic threads it onto the field-settings editor,
		// reached from the config field via one intermediate publish wrapper.
		const setFields = [
			field('event_start', 'date', 'Event Start'),
			field('headline', 'text', 'Headline'),
			field('event_end', 'date', 'Event End'),
		];
		const startVm = { $parent: ancestor(setFields) };

		// The component composes the walk with the derivation, excluding the field
		// being edited (event_end) so a boundary cannot reference itself.
		expect(dateFieldOptions(findSetSiblingFields(startVm), { excludeHandle: 'event_end' })).toEqual([
			{ value: 'event_start', label: 'Event Start' },
		]);
	});
});
