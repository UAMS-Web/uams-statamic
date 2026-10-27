import { registerCustomCondition } from './registerCustomCondition.js';

/**
 * Visibility for the clinical resource Article blueprint's "local authoring"
 * sections (Creators, Text Content Origin, Location), which only apply to an
 * original, non-syndicated article.
 *
 * Show only once the syndication question is answered "No"
 * (`syndication_query === false`). While the question is unanswered (`null` /
 * cleared) or answered "Yes", these sections stay hidden — a syndicated article
 * has no local creators/origin/location, and nothing downstream should appear
 * before the syndication question has a value. A built-in `equals false` would
 * also match the unanswered state, so this strict check (excluding `null`) is
 * what keeps the sections hidden until the question is actually answered.
 *
 * @param {Object} options
 * @param {Object} [options.values]  Sibling field values (CP publish form)
 * @param {Object} [options.root]  Root values fallback (nested contexts)
 * @returns {boolean}  Whether the gated section should show
 */
export function evaluateNotSyndicated({ values, root }) {
	// Prefer the sibling values bag; fall back to root for nested contexts
	const bag = values && typeof values === 'object' ? values : root;

	// Only an explicit "No" shows these sections; null/unanswered and true hide them
	return bag?.syndication_query === false
		|| bag?.syndication_query === 'false'
		|| bag?.syndication_query === 0;
}

registerCustomCondition('notSyndicated', evaluateNotSyndicated);
