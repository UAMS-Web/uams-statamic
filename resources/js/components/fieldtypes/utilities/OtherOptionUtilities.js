// Utility helpers for custom radio/checkboxes fieldtypes with 'Other' option

/**
 * Extracts the array of option values from a meta/config object.
 * @param {Object} meta - The meta or config object containing options
 * @returns {Array<string>} Array of option values
 */
export function getOptionValues(meta) {
	return (meta?.options || []).map(opt => opt.value);
}

/**
 * Finds the first custom value in the value array (not in optionValues).
 * @param {Array<string>} valueArray - The array of selected values
 * @param {Array<string>} optionValues - The array of valid option values
 * @returns {string|undefined} The custom value, or undefined if none found
 */
export function findCustomValue(valueArray, optionValues) {
	return valueArray.find(val => !optionValues.includes(val));
}

/**
 * Filters out '__other__' and any custom value from the value array.
 * @param {Array<string>} valueArray - The array of selected values
 * @param {Array<string>} optionValues - The array of valid option values
 * @returns {Array<string>} The filtered array
 */
export function filterOutOtherAndCustom(valueArray, optionValues) {
	return valueArray.filter(v => v !== '__other__' && optionValues.includes(v));
}

/**
 * Returns true if value is a custom 'other' value (starts with '__other__:'),
 * false otherwise.
 *
 * @param {string} val
 * @returns {boolean}
 */
export function isOtherCustomValue(val) {
	return typeof val === 'string' && val.startsWith('__other__:');
}

/**
 * Extracts the custom text from a '__other__:<customText>' value.
 * @param {string} val
 * @returns {string}
 */
export function extractOtherCustomText(val) {
	return isOtherCustomValue(val) ? val.substring('__other__:'.length) : '';
}

/**
 * Builds a '__other__:<customText>' value from text.
 * @param {string} text
 * @returns {string}
 */
export function buildOtherCustomValue(text) {
	return `__other__:${text}`;
}

/**
 * Finds the first custom 'other' value in an array.
 * @param {Array<string>} arr
 * @returns {string|undefined}
 */
export function findOtherCustomValueInArray(arr) {
	return arr.find(isOtherCustomValue);
}

/**
 * Removes all '__other__' and custom 'other' values from an array.
 * @param {Array<string>} arr
 * @returns {Array<string>}
 */
export function filterOutOtherValues(arr) {
	return arr.filter(val => val !== '__other__' && !isOtherCustomValue(val));
}
