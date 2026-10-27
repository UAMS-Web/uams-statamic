import { registerCustomCondition } from './registerCustomCondition.js';

/**
 * Shows a field when the target is empty OR matches one of the listed values.
 *
 * @see resources/js/conditions.js — usage in blueprints
 */
registerCustomCondition('isEmptyOrValue', ({ target, params }) => {
	if (!params || params.length === 0) {
		return !target || target === '' || target === null || target === undefined;
	}

	if (!target || target === '' || target === null || target === undefined) {
		return true;
	}

	return params.includes(target);
});
