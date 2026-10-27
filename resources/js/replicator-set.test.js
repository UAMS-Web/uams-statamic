// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';

import { registerReplicatorSet } from './replicator-field.js';

/**
 * Instantiate the `replicatorSet` component object (without a real Alpine runtime) by capturing
 * the factory `registerReplicatorSet` registers, then invoking it with a seed.
 *
 * @param {object|undefined} seed  The row's subfield values, keyed by bare handle.
 * @returns {object} A fresh component state object.
 */
function makeSet(seed) {
	let factory;
	registerReplicatorSet({ data: (_name, f) => { factory = f; } });

	return seed === undefined ? factory() : factory(seed);
}

describe('replicatorSet', () => {
	it('declares each seeded subfield as its own property', () => {
		const set = makeSet({ is_minor: true, parent_name: 'Ada' });

		// Declared, not assigned later: Alpine resolves an x-model identifier by walking UP the
		// scope stack, so a property this component does not own is found on an ancestor and
		// shared by every set — which would make one row's toggle reveal every row's field.
		expect(Object.prototype.hasOwnProperty.call(set, 'is_minor')).toBe(true);
		expect(Object.prototype.hasOwnProperty.call(set, 'parent_name')).toBe(true);
		expect(set.is_minor).toBe(true);
		expect(set.parent_name).toBe('Ada');
	});

	it('gives each row its own state rather than a shared object', () => {
		const first = makeSet({ is_minor: false });
		const second = makeSet({ is_minor: false });

		first.is_minor = true;

		expect(second.is_minor).toBe(false);
	});

	it('survives an absent seed', () => {
		expect(() => makeSet()).not.toThrow();
		expect(makeSet()).toEqual({});
	});

	it('keeps a null seed value as a declared property', () => {
		// A subfield with no submitted value still needs its property to exist, so the condition
		// reads "empty" instead of resolving against an undefined identifier.
		const set = makeSet({ always: null });

		expect(Object.prototype.hasOwnProperty.call(set, 'always')).toBe(true);
		expect(set.always).toBeNull();
	});
});
