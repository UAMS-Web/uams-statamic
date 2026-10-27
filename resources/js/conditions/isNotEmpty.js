import { registerCustomCondition } from './registerCustomCondition.js';

/**
 * Shows a field when the target has any non-empty value.
 *
 * @see resources/js/conditions.js — usage in blueprints
 */
registerCustomCondition('isNotEmpty', ({ target }) => {
	return target && target !== '' && target !== null && target !== undefined;
});
