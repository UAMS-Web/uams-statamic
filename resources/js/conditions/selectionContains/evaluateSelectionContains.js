/**
 * Whether a checkbox-style value includes a given option handle.
 *
 * @param {Object} options
 * @param {*} options.target
 * @param {string[]} options.params
 * @returns {boolean}
 */
export function evaluateSelectionContains({ target, params }) {
	if (!params || params.length === 0) {
		return false;
	}

	const needle = params[0];

	if (Array.isArray(target)) {
		return target.includes(needle);
	}

	if (target && typeof target === 'object') {
		return (
			Object.prototype.hasOwnProperty.call(target, needle) && !!target[needle]
		);
	}

	return false;
}
