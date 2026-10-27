/**
 * Visibility for the location `parking_information` fields, whose logic is a
 * nested AND/OR that Statamic's flat `if` / `if_any` cannot express (a single
 * field uses only the first condition key — see `Validator.getConditions()`).
 *
 * Branches (mirror the agreed location parking table):
 *   - contained   = facility_query === true AND `facility` has a value
 *                   (a location only inherits once it has picked its facility)
 *   - standalone-with-parking = facility_query === false && parking_query === true
 *   - parkingApplies = contained || standalone-with-parking
 *
 * Roles (passed as `custom parkingVisible:<role>`):
 *   - facilityInherit → contained (the "inherit the parking facility?" toggle)
 *   - facility        → (contained && parking_facility_inherit_query === false)
 *                       || standalone-with-parking
 *   - inheritToggle   → parkingApplies (the instructions/directions inherit toggles)
 *   - instructions    → parking_instructions_inherit_query === false && parkingApplies
 *   - directions      → directions_from_parking_inherit_query === false && parkingApplies
 *
 * Children (a location with a `parent`) never set `facility_query`/`parking_query`
 * — those are gated on the parent condition — so every role resolves to `false`
 * for them and they inherit everything from the parent location.
 *
 * Since UAMS-Web/uams-statamic-archive#1269 a child can opt out of that: with `parent_parking_override_query`
 * on, `childOverride` makes parking apply to the child directly, so it can pick
 * its own parking facility and write its own instructions and directions. The
 * toggle defaults off, so a child that has not opted in behaves exactly as it did
 * before — inheriting everything, through the same false-for-every-role path.
 *
 * @param {Object} options
 * @param {string[]} [options.params]
 * @param {Object} [options.values]
 * @param {Object} [options.root]
 * @returns {boolean}
 */
export function evaluateParkingVisible({ params, values, root }) {
	const bag = values && typeof values === 'object' ? values : root;
	const role = (params && params[0]) || '';

	const isTrue = (v) => v === true || v === 'true' || v === 1;
	const isFalse = (v) => v === false || v === 'false' || v === 0;
	const hasValue = (v) => (Array.isArray(v) ? v.length > 0 : v !== null && v !== undefined && v !== '');

	const contained = isTrue(bag?.facility_query) && hasValue(bag?.facility);
	const standaloneWithParking = isFalse(bag?.facility_query) && isTrue(bag?.parking_query);

	// A child location that has opted out of inheriting its parent's parking (UAMS-Web/uams-statamic-archive#1269). It is
	// its own branch rather than a variant of the two above: a child does not answer the
	// facility or parking questions at all, so neither `contained` nor
	// `standaloneWithParking` can ever be true for one.
	const childOverride = hasValue(bag?.parent) && isTrue(bag?.parent_parking_override_query);

	const parkingApplies = contained || standaloneWithParking || childOverride;

	switch (role) {
		case 'facilityInherit':
			return contained;
		case 'facility':
			return (contained && isFalse(bag?.parking_facility_inherit_query)) || standaloneWithParking || childOverride;
		case 'inheritToggle':
			return parkingApplies;
		case 'instructions':
			return isFalse(bag?.parking_instructions_inherit_query) && parkingApplies;
		case 'directions':
			return isFalse(bag?.directions_from_parking_inherit_query) && parkingApplies;
		default:
			return false;
	}
}
