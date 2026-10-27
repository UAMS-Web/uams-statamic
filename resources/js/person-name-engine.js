/**
 * Public-form PersonName reactive engine.
 *
 * Mirrors the CP-side reactivity that `PersonNameFieldtype.vue` provides for
 * the `display_format` <select>: as the user fills in name parts (or toggles
 * a `no_*` skip flag), the option list recomputes via
 * `buildDisplayFormatOptions`. The CP component imports the same module
 * (`./components/fieldtypes/person-name/personNameDisplayFormats.js`), so
 * the option set on the public side stays in lockstep with the CP — no
 * server-round-trip needed, no second implementation to drift.
 *
 * Lifecycle (shared with sibling engines via `lib/alpine-engine.js`):
 *
 *   1. Form-field Antlers template (`person_name.antlers.html`) emits
 *      `data-person-name-handle` and `data-person-name-include-nickname`
 *      attributes on the display_format <select> when the blueprint sets
 *      `include_display_format: true` (the default).
 *   2. After Alpine initializes the form's `x-data` scope, this module finds
 *      each matching <select>, resolves the parent form's reactive scope via
 *      `Alpine.$data(form)`, and sets up an `Alpine.effect()` that reads the
 *      relevant composite subfields. Any change to a referenced subfield
 *      re-runs the effect and rebuilds the <option> elements.
 *   3. If the user's currently-selected key falls out of the recomputed set
 *      (e.g., they cleared a required name part), `coerceDisplayFormatKey`
 *      maps it back to null and writes that to the form scope — keeps the
 *      submitted value valid against `App\Rules\PersonNameDisplayFormatRule`.
 */

// The CSP build, matching `site.js` — attribute expressions are AST-interpreted
// rather than compiled through `new Function()`, so `script-src` needs no
// `unsafe-eval` (UAMS-Web/uams-statamic-archive#1859). The constraint is on `x-*` attributes, not on modules.
import Alpine from '@alpinejs/csp';
import { registerAlpineEngine } from './lib/alpine-engine.js';
import {
	buildDisplayFormatOptions,
	coerceDisplayFormatKey,
} from './components/fieldtypes/person-name/personNameDisplayFormats.js';

/** Selector for display_format <select>s opted into reactive recomputation. */
const PERSON_NAME_SELECTOR = 'select[data-person-name-handle]';

/**
 * Wire one display_format <select>. Resolves the form's Alpine scope, then
 * installs an effect that rebuilds the <option> list on every relevant
 * subfield change.
 *
 * @param {HTMLSelectElement} select
 */
function initPersonNameDisplayFormat(select) {
	const form = select.closest('form');
	if (!form) {
		console.warn('[person-name-engine] <select> is not inside a <form>:', select);
		return;
	}

	const handle = select.dataset.personNameHandle;
	if (!handle) {
		console.warn('[person-name-engine] missing data-person-name-handle:', select);
		return;
	}

	// `data-person-name-include-nickname` is the kebab-case form of the
	// blueprint's `include_nickname` config. Default to true (matches the
	// CP). String "false" is the only value that disables it.
	const includeNickname = select.dataset.personNameIncludeNickname !== 'false';

	const formScope = Alpine.$data(form);
	if (!formScope) {
		console.warn('[person-name-engine] no Alpine scope on the form:', form);
		return;
	}

	// Keep the placeholder <option value=""> so the rebuild preserves the
	// blueprint's "Please select..." UX. Snapshotted up front because the
	// effect wipes innerHTML on each run.
	const placeholderOption = select.querySelector('option[value=""]');
	const placeholderHtml = placeholderOption ? placeholderOption.outerHTML : '';

	Alpine.effect(() => {
		const composite = formScope[handle];
		if (composite == null || typeof composite !== 'object') return;

		// `buildDisplayFormatOptions` is pure and works directly on the shape
		// the form's x-data exposes (per PersonName::emptyValue). Reading the
		// nested fields here is what registers Alpine dependencies, so the
		// effect re-runs when any of them change.
		const options = buildDisplayFormatOptions(composite, { includeNickname });
		const allowedKeys = options.map((opt) => opt.key);
		const currentKey = composite.display_format ?? null;
		const coercedKey = coerceDisplayFormatKey(allowedKeys, currentKey);

		const optionsHtml = options
			.map((opt) => {
				const selected = opt.key === coercedKey ? ' selected' : '';
				return `<option value="${escapeHtml(opt.key)}"${selected}>${escapeHtml(opt.label)}</option>`;
			})
			.join('');

		select.innerHTML = placeholderHtml + optionsHtml;

		// If the user's prior selection was invalidated by their edits, write
		// the coerced value back so the form state and the rendered DOM stay
		// in sync. Guard against an unchanged write — Alpine reactivity would
		// re-trigger the effect otherwise.
		if (currentKey !== coercedKey && composite.display_format !== coercedKey) {
			composite.display_format = coercedKey;
		}
	});
}

/**
 * Minimal HTML escaping for option values / labels. The labels come from
 * `buildDisplayFormatOptions`, which assembles them from user-entered name
 * parts — escape to keep stray characters from breaking the <option> markup.
 *
 * @param {string} s
 * @returns {string}
 */
function escapeHtml(s) {
	return String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

/**
 * Public entry. Lifecycle handled by `lib/alpine-engine.js`.
 */
export function registerPersonNameEngine() {
	registerAlpineEngine({
		selector: PERSON_NAME_SELECTOR,
		boundAttr: 'personNameBound',
		init: initPersonNameDisplayFormat,
	});
}
