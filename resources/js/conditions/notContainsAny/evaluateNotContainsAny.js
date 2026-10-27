/**
 * Inverse of `contains_any` (see `passesContainsAnyCondition` in `../validator.js`).
 *
 * @param {Object} options
 * @param {*} options.target
 * @param {string[]} options.params
 * @returns {boolean}
 */
export function evaluateNotContainsAny({ target, params }) {
	// Reject the condition when there is no list of options to test
	if (!params || params.length === 0) {
		return false;
	}

	// Split options the same way `contains_any` does (comma list after the operator)
	const values = params;

	// Reject a match when any option would have satisfied `contains_any`
	// Return true when the value matches none of the needles (intersection or substring)
	if (Array.isArray(target)) {
		// Reject when the array shares any value with the needle list
		return arrayIntersection(target, values).length === 0;
	}

	// Coerce scalars the way `contains_any` does, and test each needle as a
	// literal substring, as Statamic does since statamic/cms#15531 (6.35.0)
	const str = target == null ? '' : String(target);

	// Reject when any needle appears in the value
	return !values.some((needle) => str.includes(needle));
}

/**
 * @param {Array<*>} a
 * @param {string[]} b
 * @returns {Array<*>}
 */
function arrayIntersection(a, b) {
	// Keep only values that appear in both lists, matching lodash `intersection`
	return a.filter((x) => b.includes(x));
}
