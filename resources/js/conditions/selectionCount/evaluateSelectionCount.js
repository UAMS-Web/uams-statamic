import { resolveNumericOperand, selectionCountValue } from './helpers.js';

/**
 * Compare selection count to operands (see selectionCount/index.js PHPDoc in parent conditions entry).
 *
 * @param {Object} options
 * @param {*} options.target
 * @param {string[]} options.params
 * @param {Object} [options.values]
 * @param {Object} [options.root]
 * @returns {boolean}
 */
export function evaluateSelectionCount({ target, params, values, root }) {
	// Derive the selection count from the target field value
	const count = selectionCountValue(target);

	// Reject when the parser produced no operator or operands
	if (!params || params.length === 0) {
		return false;
	}

	// Read operator and operands from the condition string
	const op = params[0];
	const a1 = params[1];
	const a2 = params[2];

	// Compare count to operands based on the operator keyword
	switch (op) {
		// Greater than
		case 'gt':
			return count > resolveNumericOperand(a1, values, root);
		// Greater than or equal to
		case 'gte':
			return count >= resolveNumericOperand(a1, values, root);
		// Less than
		case 'lt':
			return count < resolveNumericOperand(a1, values, root);
		// Less than or equal to
		case 'lte':
			return count <= resolveNumericOperand(a1, values, root);
		// Equals
		case 'equals':
		case 'equal':
		case 'eq':
			return count === resolveNumericOperand(a1, values, root);
		// Not equals
		case 'not':
		case 'notequal':
		case 'notequals':
		case 'neq':
			return count !== resolveNumericOperand(a1, values, root);
		// Between
		case 'between': {
			// Resolve inclusive min and max (literals or field handles)
			const min = resolveNumericOperand(a1, values, root);
			const max = resolveNumericOperand(a2, values, root);

			// Fail closed when either bound did not resolve to a number
			if (Number.isNaN(min) || Number.isNaN(max)) {
				return false;
			}

			// Check whether count lies in the inclusive range
			return count >= min && count <= max;
		}
		// Default case: invalid operator or operands
		default:
			return false;
	}
}
