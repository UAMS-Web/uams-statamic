import { registerCustomCondition } from './registerCustomCondition.js';

/**
 * Visibility for the clinical resource `article` (Article Content) field, whose
 * logic is a nested AND/OR that Statamic's flat `if` / `if_any` cannot express
 * (a single field uses only the first condition key — see Validator.getConditions()).
 *
 * The blueprint itself is the `article` type (clinical resources are split into
 * per-type blueprints — there is no `type` discriminator field), so this gates
 * purely on the syndication answers. Show the Article body when the syndication
 * source is settled as "not NCI":
 *   - not syndicated (`syndication_query === false`) — an original article, OR
 *   - syndicated from a non-NCI source (`syndication_source === 'other'`).
 *
 * Reading both syndication fields here (rather than gating field-by-field) keeps
 * the Article hidden until the upstream questions have selections: it never shows
 * in an interim state that a later pick (e.g. choosing NCI) would hide. `null` /
 * unanswered `syndication_query` and an unpicked `syndication_source` both
 * resolve to hidden.
 *
 * @param {Object} options
 * @param {Object} [options.values]  Sibling field values (CP publish form)
 * @param {Object} [options.root]  Root values fallback (nested contexts)
 * @returns {boolean}  Whether the Article Content field should show
 */
export function evaluateArticleVisible({ values, root }) {
	// Prefer the sibling values bag; fall back to root for nested contexts
	const bag = values && typeof values === 'object' ? values : root;

	// Treat the boolean/string/numeric false spellings as "answered: not syndicated"
	const isFalse = (v) => v === false || v === 'false' || v === 0;

	return isFalse(bag?.syndication_query) || bag?.syndication_source === 'other';
}

registerCustomCondition('articleVisible', evaluateArticleVisible);
