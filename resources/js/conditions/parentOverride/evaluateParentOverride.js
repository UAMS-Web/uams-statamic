/**
 * Visibility for the `clinical_locations` components a CHILD location may override (UAMS-Web/uams-statamic-archive#1269).
 *
 * A location with a parent inherits the parent's address, parking and directions. Until this
 * ticket that inheritance was all-or-nothing and enforced by hiding the fields outright
 * (`if: { parent: 'equals empty' }`), so a child clinic on a different floor of the same
 * building had no way to give its own suite-level directions.
 *
 * The rule each role expresses is an OR, which is why this is a custom condition rather than
 * another key in a flat `if:` block: a field applies when the location has NO parent, or when
 * it has one and the editor has turned the matching override on. Statamic AND-s the handles
 * inside one `if:`, so the OR has nowhere to live in the blueprint.
 *
 * Roles (passed as `custom parentOverride:<role>`):
 *   - address → no parent, or `parent_address_override_query` (the master) is on
 *   - parking → no parent, or `parent_parking_override_query` is on
 *
 * Since UAMS-Web/uams-statamic-archive#2608 the address override is split into four groups, matching
 * `UAMS-Web/UAMSWP-Find-a-Doc#833`. A child OWNS a group when the master and that group's
 * toggle are both on; a top-level location owns every group. The group roles also carry the
 * containment rule the address tab always had, because a flat `if:` cannot OR it in:
 *   - facility → the location owns the facility group (`facility_query`, `facility`)
 *   - street   → owns the street group (`address`, `regions`) and is not contained
 *   - map      → owns the map group (`map`) and is not contained
 *   - unit     → owns the unit group (`facility_level`, `suite`) and is not standalone
 * "Contained" and "standalone" are read only where the location owns its facility group:
 * on a top-level location that is `facility_query` yes or no, exactly as before; on a child
 * that inherits its facility, the answer is the parent's and is not on this form, so the
 * child's own street, map or unit fields show on the group toggle alone. That is the case
 * UAMS-Web/uams-statamic-archive#833 split the facility out for: a parent that IS the building answers no, and a child
 * inheriting that answer could otherwise never show a floor or suite.
 *
 * The two are separate deliberately, and the ticket's own example is why: a child clinic
 * "needs its own suite-level directions and may have distinct parking, even though it shares
 * the parent's street address". One combined toggle would force an editor to restate an
 * address that has not changed in order to correct the directions.
 *
 * Defaults preserve today's behavior exactly: both toggles default off, so a child with
 * neither set resolves every role to false and inherits everything, which is what it did
 * before this existed.
 *
 * @param {Object} options
 * @param {string[]} [options.params]
 * @param {Object} [options.values]
 * @param {Object} [options.root]
 * @returns {boolean}
 */
export function evaluateParentOverride({ params, values, root }) {
	const bag = values && typeof values === 'object' ? values : root;
	const role = (params && params[0]) || '';

	const isTrue = (v) => v === true || v === 'true' || v === 1;
	const isFalse = (v) => v === false || v === 'false' || v === 0;
	const hasValue = (v) => (Array.isArray(v) ? v.length > 0 : v !== null && v !== undefined && v !== '');

	const isChild = hasValue(bag?.parent);
	const owns = (group) =>
		!isChild || (isTrue(bag?.parent_address_override_query) && isTrue(bag?.[`parent_address_override_${group}_query`]));
	const ownsFacility = owns('facility');

	switch (role) {
		case 'facility':
			return ownsFacility;
		case 'street':
		case 'map':
			// Standalone where the location answers containment itself; otherwise only not contained
			return owns(role) && (isChild ? !(ownsFacility && isTrue(bag?.facility_query)) : isFalse(bag?.facility_query));
		case 'unit':
			// Contained where the location answers containment itself, as the resolver reads an
			// unanswered question as no; otherwise shown whenever the unit group is owned
			return owns('unit') && (isChild ? !(ownsFacility && !isTrue(bag?.facility_query)) : isTrue(bag?.facility_query));
	}

	// A top-level location is unaffected by the remaining roles: it has no parent to inherit
	// from, so every component is its own and every role is true regardless of the toggles.
	if (!isChild) {
		return true;
	}

	switch (role) {
		case 'address':
			return isTrue(bag?.parent_address_override_query);
		case 'parking':
			return isTrue(bag?.parent_parking_override_query);
		default:
			return false;
	}
}
