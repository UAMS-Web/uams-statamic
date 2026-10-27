import { describe, expect, it } from 'vitest';

import { evaluateParentOverride } from './evaluateParentOverride.js';

const show = (role, values) => evaluateParentOverride({ params: [role], values });

describe('evaluateParentOverride', () => {
	describe('a top-level location', () => {
		it('is unaffected, whatever the toggles say', () => {
			// The ticket's third criterion. A location with no parent has nothing to inherit,
			// so the override question does not arise and the fields are simply its own.
			expect(show('address', { parent: null })).toBe(true);
			expect(show('parking', { parent: null })).toBe(true);
			expect(show('address', {})).toBe(true);
			expect(show('address', { parent: '' })).toBe(true);
			expect(show('address', { parent: [] })).toBe(true);
		});

		it('is unaffected even with an override explicitly off', () => {
			expect(show('address', { parent: null, parent_address_override_query: false })).toBe(true);
		});
	});

	describe('a child location', () => {
		it('inherits by default, which is the behavior before this existed', () => {
			// The second criterion. Neither toggle set: every role false, so the fields stay
			// hidden and the child resolves to its parent's values exactly as before.
			expect(show('address', { parent: 'parent-1' })).toBe(false);
			expect(show('parking', { parent: 'parent-1' })).toBe(false);
		});

		it('shows the address components once the address override is on', () => {
			expect(show('address', { parent: 'parent-1', parent_address_override_query: true })).toBe(true);
		});

		it('shows the parking components once the parking override is on', () => {
			expect(show('parking', { parent: 'parent-1', parent_parking_override_query: true })).toBe(true);
		});

		it('keeps the two overrides independent', () => {
			// The ticket's own example: a child clinic on another floor needs its own
			// directions while sharing the parent's street address. One combined toggle would
			// make it restate an address that has not changed.
			const values = { parent: 'parent-1', parent_parking_override_query: true };

			expect(show('parking', values)).toBe(true);
			expect(show('address', values)).toBe(false);
		});

		it('reads a checkbox that arrives as a string or a number', () => {
			// Control-panel values are not always real booleans.
			expect(show('address', { parent: 'p', parent_address_override_query: 'true' })).toBe(true);
			expect(show('address', { parent: 'p', parent_address_override_query: 1 })).toBe(true);
			expect(show('address', { parent: 'p', parent_address_override_query: 'false' })).toBe(false);
			expect(show('address', { parent: 'p', parent_address_override_query: 0 })).toBe(false);
		});

		it('treats a parent stored as a single-item array as a parent', () => {
			// An entries field hands back an array even at max_items 1.
			expect(show('address', { parent: ['parent-1'] })).toBe(false);
		});
	});

	it('refuses an unknown role rather than defaulting to visible', () => {
		// A typo in a blueprint should hide the field, not reveal one that was meant to be
		// conditional. Checked on a CHILD, because a top-level location short-circuits first.
		expect(show('nonsense', { parent: 'parent-1', parent_address_override_query: true })).toBe(false);
		expect(evaluateParentOverride({ values: { parent: 'parent-1' } })).toBe(false);
	});

	describe('the address groups (#2608)', () => {
		const child = (groups, extra = {}) => ({
			parent: 'parent-1',
			parent_address_override_query: true,
			...Object.fromEntries(groups.map((group) => [`parent_address_override_${group}_query`, true])),
			...extra,
		});

		it('keeps the containment rule on a top-level location', () => {
			// Exactly what `facility_query: equals true|false` did before the split.
			expect(show('facility', { parent: null })).toBe(true);
			expect(show('street', { parent: null, facility_query: false })).toBe(true);
			expect(show('map', { parent: null, facility_query: false })).toBe(true);
			expect(show('unit', { parent: null, facility_query: false })).toBe(false);
			expect(show('street', { parent: null, facility_query: true })).toBe(false);
			expect(show('map', { parent: null, facility_query: true })).toBe(false);
			expect(show('unit', { parent: null, facility_query: true })).toBe(true);
			// Unanswered: neither branch shows until the question is answered.
			expect(show('street', { parent: null })).toBe(false);
			expect(show('unit', { parent: null })).toBe(false);
		});

		it('shows no group on a child with the master off, whatever the group toggles say', () => {
			const values = { ...child(['street', 'facility', 'unit', 'map']), parent_address_override_query: false };

			for (const role of ['street', 'facility', 'unit', 'map']) {
				expect(show(role, values)).toBe(false);
			}
		});

		it('shows only the overridden group on a child', () => {
			for (const group of ['street', 'facility', 'unit', 'map']) {
				const values = child([group]);

				for (const role of ['street', 'facility', 'unit', 'map']) {
					expect(show(role, values)).toBe(role === group);
				}
			}
		});

		it('shows a unit-only child its floor and suite without a containment answer of its own', () => {
			// The UAMS-Web/uams-statamic-archive#833 case: the parent IS the building and answers no, and the child inherits
			// that answer, so its own floor and suite must not wait on a question it is not asked.
			expect(show('unit', child(['unit']))).toBe(true);
			expect(show('unit', child(['unit'], { facility_query: false }))).toBe(true);
		});

		it('applies the containment rule once a child answers it for itself', () => {
			const all = ['street', 'facility', 'unit', 'map'];

			expect(show('street', child(all, { facility_query: true }))).toBe(false);
			expect(show('map', child(all, { facility_query: true }))).toBe(false);
			expect(show('unit', child(all, { facility_query: true }))).toBe(true);
			expect(show('street', child(all, { facility_query: false }))).toBe(true);
			expect(show('unit', child(all, { facility_query: false }))).toBe(false);
			// Unanswered reads as not contained, matching LocationInheritedAddressService.
			expect(show('unit', child(all))).toBe(false);
		});

		it('reads a group toggle that arrives as a string or a number', () => {
			expect(show('map', { parent: 'p', parent_address_override_query: 'true', parent_address_override_map_query: 1 })).toBe(true);
			expect(show('map', { parent: 'p', parent_address_override_query: 1, parent_address_override_map_query: 'false' })).toBe(false);
		});
	});

	it('falls back to root when no values bag is supplied', () => {
		expect(evaluateParentOverride({ params: ['address'], root: { parent: null } })).toBe(true);
	});
});
