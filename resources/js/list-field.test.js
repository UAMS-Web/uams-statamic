// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';

import { registerListField } from './list-field.js';

/**
 * Instantiate the `listField` component object (without a real Alpine runtime) by capturing
 * the factory `registerListField` registers, then invoking it with the same arguments the
 * partial passes.
 *
 * @param {Array<string>|undefined} items  Initial rows.
 * @param {number|undefined} max  The `max_items` ceiling.
 * @param {number|undefined} min  The `min_items` floor.
 * @returns {object} A fresh component state object.
 */
function makeList(items, max, min) {
	let factory;
	registerListField({ data: (_name, f) => { factory = f; } });

	return factory(...[items, max, min].filter((a) => a !== undefined));
}

describe('listField', () => {
	it('declares items so each field instance owns its own array', () => {
		// Alpine resolves an x-model identifier up the scope stack, so an undeclared `items`
		// would be found on an ancestor and two list fields on one form would share rows.
		const a = makeList(['x']);
		const b = makeList(['y']);
		a.items.push('z');

		expect(a.items).toEqual(['x', 'z']);
		expect(b.items).toEqual(['y']);
	});

	it('defaults to a single empty row when given nothing usable', () => {
		expect(makeList().items).toEqual(['']);
		expect(makeList([]).items).toEqual(['']);
	});

	it('seeds from a prior submission', () => {
		expect(makeList(['first', 'second']).items).toEqual(['first', 'second']);
	});

	it('adds rows up to max_items and no further', () => {
		const list = makeList([''], 3, 1);
		list.add();
		list.add();
		expect(list.items).toHaveLength(3);

		list.add();
		expect(list.items).toHaveLength(3);
		expect(list.canAdd()).toBe(false);
	});

	it('removes the addressed row, never dropping below min_items', () => {
		const list = makeList(['a', 'b', 'c'], 9, 2);
		list.remove(1);
		expect(list.items).toEqual(['a', 'c']);

		// At the floor, removal is refused rather than silently shrinking the field.
		list.remove(0);
		expect(list.items).toEqual(['a', 'c']);
		expect(list.canRemove()).toBe(false);
	});

	it('gates add and remove independently of each other', () => {
		const list = makeList(['a', 'b'], 2, 2);
		expect(list.canAdd()).toBe(false);
		expect(list.canRemove()).toBe(false);
	});
});
