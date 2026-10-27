import { describe, expect, it } from 'vitest';

import { evaluateParkingVisible } from './evaluateParkingVisible.js';

const show = (role, values) => evaluateParkingVisible({ params: [role], values });

/*
 * This evaluator shipped untested. The coverage below was written alongside the UAMS-Web/uams-statamic-archive#1269 child
 * override rather than for it, because the pre-existing branches are what a change to this
 * file can silently break, and nothing would have reported it.
 */

describe('evaluateParkingVisible', () => {
	describe('a location contained within a facility', () => {
		const contained = { facility_query: true, facility: 'facility-1' };

		it('offers the facility-inherit toggle only once a facility is picked', () => {
			expect(show('facilityInherit', contained)).toBe(true);
			expect(show('facilityInherit', { facility_query: true })).toBe(false);
			expect(show('facilityInherit', { facility_query: true, facility: [] })).toBe(false);
		});

		it('offers its own parking facility only when it declines to inherit one', () => {
			expect(show('facility', contained)).toBe(false);
			expect(show('facility', { ...contained, parking_facility_inherit_query: false })).toBe(true);
		});

		it('offers the instruction and direction toggles', () => {
			expect(show('inheritToggle', contained)).toBe(true);
		});

		it('offers the instruction and direction bodies only when their toggles are off', () => {
			expect(show('instructions', contained)).toBe(false);
			expect(show('instructions', { ...contained, parking_instructions_inherit_query: false })).toBe(true);
			expect(show('directions', contained)).toBe(false);
			expect(show('directions', { ...contained, directions_from_parking_inherit_query: false })).toBe(true);
		});
	});

	describe('a standalone location', () => {
		it('applies parking only when it says it has parking', () => {
			expect(show('inheritToggle', { facility_query: false, parking_query: true })).toBe(true);
			expect(show('inheritToggle', { facility_query: false, parking_query: false })).toBe(false);
		});

		it('offers its own parking facility outright', () => {
			// Nothing to inherit from, so there is no inherit toggle to decline first.
			expect(show('facility', { facility_query: false, parking_query: true })).toBe(true);
		});
	});

	describe('a child location', () => {
		it('resolves every role false by default, inheriting from its parent', () => {
			// Unchanged by UAMS-Web/uams-statamic-archive#1269 and asserted so it stays that way: a child answers neither
			// the facility nor the parking question, so nothing applies to it directly.
			const child = { parent: 'parent-1' };

			['facilityInherit', 'facility', 'inheritToggle', 'instructions', 'directions'].forEach((role) => {
				expect(show(role, child)).toBe(false);
			});
		});

		it('applies parking to the child itself once the override is on (#1269)', () => {
			const overriding = { parent: 'parent-1', parent_parking_override_query: true };

			expect(show('inheritToggle', overriding)).toBe(true);
			expect(show('facility', overriding)).toBe(true);
		});

		it('still gates the bodies behind their own inherit toggles when overriding', () => {
			const overriding = { parent: 'parent-1', parent_parking_override_query: true };

			expect(show('instructions', overriding)).toBe(false);
			expect(show('instructions', { ...overriding, parking_instructions_inherit_query: false })).toBe(true);
			expect(show('directions', { ...overriding, directions_from_parking_inherit_query: false })).toBe(true);
		});

		it('does not offer the FACILITY-inherit toggle, which is a different question', () => {
			// That toggle asks whether to inherit from the building this location sits in.
			// A child overriding its parent LOCATION has not answered the facility question
			// at all, so there is nothing for it to decline.
			expect(show('facilityInherit', { parent: 'parent-1', parent_parking_override_query: true })).toBe(false);
		});
	});

	it('refuses an unknown role', () => {
		expect(show('nonsense', { facility_query: false, parking_query: true })).toBe(false);
	});
});
