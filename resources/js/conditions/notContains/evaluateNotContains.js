/**
 * Inverse of Statamic’s `passesIncludesCondition` (`contains` / `includes`).
 *
 * @param {Object} options
 * @param {*} options.target
 * @param {string[]} options.params
 * @returns {boolean}
 */
export function evaluateNotContains({ target, params }) {
	// Reject the condition when there is no substring / value to test
	if (!params || params.length === 0) {
		return false;
	}

	// Read the single needle the same way `contains` uses one RHS value
	const rhs = params[0];

	// Build `lhs` like Validator `prepareLhs` for the `includes` operator
	const lhs = lhsForIncludes(target);

	// Reject the match when Statamic’s `includes` would have passed
	return !includesLikeStatamic(lhs, rhs);
}

/**
 * Mirror `Validator` `prepareLhs` for `includes` (non-object values become strings).
 *
 * @param {*} target
 * @returns {string|Array<*>|object}
 */
function lhsForIncludes(target) {
	// Treat nullish like an empty string for `.includes` checks
	if (target === null || target === undefined) {
		return '';
	}

	// Cast primitives to string so `includes` matches the core field condition
	if (typeof target !== 'object') {
		return target.toString();
	}

	// Arrays and objects pass through; only arrays support `.includes` in core
	return target;
}

/**
 * Match `passesIncludesCondition` without reaching into the Validator class.
 *
 * @param {string|Array<*>|object} lhs
 * @param {string} rhs
 * @returns {boolean}
 */
function includesLikeStatamic(lhs, rhs) {
	// Check list membership or substring the same way core does
	if (typeof lhs.includes === 'function') {
		return lhs.includes(rhs);
	}

	// Non-arrays with no `.includes` never pass `contains` in practice
	return false;
}
