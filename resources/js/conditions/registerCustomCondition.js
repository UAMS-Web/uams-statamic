/**
 * Registers a custom Statamic field condition for both CP and front-end fallbacks.
 *
 * @param {string} name
 * @param {Function} conditionFunction  (options) => boolean
 */
export function registerCustomCondition(name, conditionFunction) {
	if (
		typeof Statamic !== 'undefined'
		&& Statamic.$conditions
		&& typeof Statamic.$conditions.add === 'function'
	) {
		Statamic.$conditions.add(name, conditionFunction);
	}

	if (typeof window !== 'undefined' && !window.StatamicCustomConditions) {
		window.StatamicCustomConditions = {};
	}
	if (typeof window !== 'undefined' && window.StatamicCustomConditions) {
		window.StatamicCustomConditions[name] = conditionFunction;
	}
}
