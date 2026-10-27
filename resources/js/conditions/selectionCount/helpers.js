/**
 * Utilities for comparing “selection count” (checkboxes, etc.) to operands.
 */

/**
 * Count selected items for array-like field values (e.g. checkboxes).
 *
 * @param {*} target
 * @returns {number}
 */
export function selectionCountValue(target) {
	// Use array length when the target is a list of selections
	if (Array.isArray(target)) {
		return target.length;
	}

	// Treat plain objects as key/value maps and count keys
	if (target && typeof target === 'object') {
		return Object.keys(target).length;
	}

	// Treat null, undefined, or empty string as no selections
	if (target === null || target === undefined || target === '') {
		return 0;
	}

	// Count any other scalar as a single value
	return 1;
}

/**
 * @param {object|undefined} obj
 * @param {string} path
 * @returns {*}
 */
export function getByPath(obj, path) {
	// Return nothing when the object or path is missing
	if (!obj || !path) {
		return undefined;
	}

	// Read a top-level property when the path has no dots
	if (!path.includes('.')) {
		return obj[path];
	}

	// Walk dotted segments to resolve nested values
	return path.split('.').reduce((acc, key) => (acc != null ? acc[key] : undefined), obj);
}

/**
 * Resolve a numeric operand from a literal or a sibling field handle (dotted path).
 *
 * @param {string|undefined} token
 * @param {object|undefined} values
 * @param {object|undefined} root
 * @returns {number}
 */
export function resolveNumericOperand(token, values, root) {
	// Treat a missing token as an unusable operand
	if (token === undefined || token === null) {
		return NaN;
	}

	// Normalize to a trimmed string for parsing and lookup
	const s = String(token).trim();

	// Reject blank tokens
	if (s === '') {
		return NaN;
	}

	// Parse numeric literals directly
	if (/^-?\d+(\.\d+)?$/.test(s)) {
		return Number(s);
	}

	// Look up the field in the current values bag first
	const fromValues = getByPath(values, s);

	// Fall back to the root values bag when the handle is absent
	const raw = fromValues !== undefined && fromValues !== null ? fromValues : getByPath(root, s);

	// Signal failure when nothing was found at that handle
	if (raw === undefined || raw === null) {
		return NaN;
	}

	// Use array length when the referenced field holds a list
	if (Array.isArray(raw)) {
		return raw.length;
	}

	// Pass numbers through unchanged
	if (typeof raw === 'number') {
		return raw;
	}

	// Map booleans to 0 or 1 for comparison
	if (typeof raw === 'boolean') {
		return raw ? 1 : 0;
	}

	// Coerce string and other primitives to a number when possible
	const n = Number(raw);
	return Number.isNaN(n) ? NaN : n;
}
