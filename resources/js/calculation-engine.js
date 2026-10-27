/**
 * Public-form calculation engine.
 *
 * Mirrors the CP-side reactivity that the Vue `CustomInput.vue` component
 * provides for `calculation_*` config, but built on Alpine instead of Vue.
 * The math/text/format/security logic itself lives in
 * `resources/js/lib/calculation.js` and is shared with the CP component —
 * single source of truth.
 *
 * How it wires up:
 *
 *  1. Form-field Antlers templates emit `data-calculation-*` attributes on
 *     calculated inputs when the blueprint sets `calculation_query: true`
 *     (see `resources/views/vendor/statamic/forms/fields/default.antlers.html`
 *     and `integer.antlers.html`).
 *  2. After Alpine initializes the form's `x-data` scope, this module finds
 *     each `[data-calculation-type]` input, resolves its parent form's
 *     reactive scope via `Alpine.$data`, and sets up an `Alpine.effect()` per
 *     calculated input.
 *  3. The effect reads each source field referenced by the formula directly
 *     from the form scope, which registers Alpine reactive dependencies; any
 *     change to a referenced field re-runs the effect and re-writes the
 *     computed value back to the form scope (which propagates to the input
 *     via the existing `x-model` binding).
 *  4. Calculated inputs are marked `readonly` by the template so users can
 *     see the value but can't fight Alpine for control of it.
 *  5. A field whose input is named `<handle>[]` binds a list (Checkboxes, a
 *     multiple Select). Its text result is written as the comma-separated
 *     option values it names, so the binding checks those (UAMS-Web/uams-statamic-archive#2924).
 *
 * Self-reference is guarded against by a stale-value check — re-writing the
 * same formatted result is a no-op and doesn't re-trigger the effect.
 */

// The CSP build, matching `site.js` — attribute expressions are AST-interpreted
// rather than compiled through `new Function()`, so `script-src` needs no
// `unsafe-eval` (UAMS-Web/uams-statamic-archive#1859). The constraint is on `x-*` attributes, not on modules.
import Alpine from '@alpinejs/csp';
import { registerAlpineEngine } from './lib/alpine-engine.js';
import * as Calc from './lib/calculation.js';

/** Selector for inputs that opted into calculation via the Antlers template. */
const CALCULATED_INPUT_SELECTOR = '[data-calculation-type]';

/**
 * Selector for the nearest ancestor that provides the reactive Alpine scope.
 * Public Statamic forms render `<form x-data>`; calculator Bard sets render
 * `<div data-calculator-context x-data>`. Either is a valid container.
 */
const SCOPE_CONTAINER_SELECTOR = '[data-calculator-context], form';

/**
 * Reactive flag stored on the scope object that records which calculated
 * input the user is currently editing. While set, effects bail out for that
 * handle so the engine doesn't overwrite what the user is typing — and so
 * bidirectional formulas (each variable is computed from the others, and all
 * are editable) don't form a write loop.
 *
 * Prefixed with `_calc_` to reduce the chance of colliding with a real
 * form-field handle.
 */
const LAST_TOUCHED_KEY = '_calc_last_touched';

/**
 * Reactive flag stored on the scope object that records the `group` value
 * (if any) of the variable currently being edited. Variables with a matching
 * non-empty group are treated as paired — their effects bail out so the
 * group's other variables aren't recomputed from the rounded value the user
 * just changed (e.g. editing `weight_lb` in the `imperial` group should not
 * cascade a rounded `weight_kg` back into `weight_oz`).
 */
const LAST_TOUCHED_GROUP_KEY = '_calc_last_touched_group';

/**
 * Suffix on the `name` of a field that submits a list: each Checkboxes box, and a
 * multiple Select (UAMS-Web/uams-statamic-archive#2924).
 */
const LIST_NAME_SUFFIX = '[]';

/**
 * Resolve the form-scope key for an input, and whether the field binds a list.
 * Public forms submit a single-value field as `name="<handle>"` and a list field
 * as `name="<handle>[]"`; either way `<handle>` is the form-scope key. A
 * composite subfield (`name="<handle>[subfield]"`) is not supported, so it
 * resolves to nothing.
 *
 * @param {HTMLElement} input
 * @returns {{ handle: string, isList: boolean } | null}
 */
function resolveField(input) {
	const name = input.getAttribute('name');
	if (!name) return null;

	const isList = name.endsWith(LIST_NAME_SUFFIX);
	const handle = isList ? name.slice(0, -LIST_NAME_SUFFIX.length) : name;

	if (handle === '' || handle.includes('[')) return null;

	return { handle, isList };
}

/**
 * The value to write for a list field: the formula's text result read as a
 * comma-separated list of option values, each trimmed, empty ones dropped
 * (UAMS-Web/uams-statamic-archive#2924). `"a, b"` checks the options `a` and `b`. An option value that itself
 * contains a comma cannot be named this way.
 *
 * @param {string} formatted
 * @returns {string[]}
 */
function listValues(formatted) {
	return formatted
		.split(',')
		.map((value) => value.trim())
		.filter((value) => value !== '');
}

/**
 * Wire up one calculated input. Reads the formula / type / decimals from
 * `data-*` attributes on the element, finds the form's Alpine scope, and
 * installs an `Alpine.effect` that recomputes on any source-field change.
 *
 * @param {HTMLElement} input
 */
function initCalculation(input) {
	const scopeEl = input.closest(SCOPE_CONTAINER_SELECTOR);
	if (!scopeEl) {
		console.warn(
			'[calculation-engine] Calculated input is not inside a <form> or [data-calculator-context]:',
			input,
		);
		return;
	}

	const field = resolveField(input);
	if (!field) {
		console.warn('[calculation-engine] Could not resolve form-scope handle for input:', input);
		return;
	}
	const { handle, isList } = field;

	const formula = input.dataset.calculationFormula || '';
	const type = input.dataset.calculationType;
	if (!formula || (type !== 'math' && type !== 'text')) {
		return;
	}

	const decimalsRaw = input.dataset.calculationDecimals;
	const decimals = decimalsRaw !== undefined && decimalsRaw !== '' ? parseInt(decimalsRaw, 10) : 2;

	// Primary = the calculator's "result" variable (the formula whose
	// target is the caption's LHS / the right column). When refs go
	// empty, the primary clears so a stale value isn't shown next to
	// missing inputs — non-primary formula-bearing variables bail
	// instead, preserving any value the user typed into them.
	const isPrimary = input.hasAttribute('data-calculation-primary');

	// Tracks the engine's own most recent write to `formScope[handle]`.
	// Used for the equality short-circuit so the effect doesn't read
	// `formScope[handle]` (which would register a reactive dep on its
	// own output and cause this effect to re-fire when an external
	// writer — cross-calculator sync, the reset engine — sets the
	// handle. For a bidirectional primary like weight-conversion's
	// `weight_kg`, that re-fire would compute the value back from
	// `weight_imperial` and clobber whatever sync just wrote, making
	// it impossible to change `weight_kg` from a peer calculator).
	let lastWritten = '';

	// Bracket (legacy `[handle]`) is the default when the attribute is
	// absent, so existing public Statamic forms that haven't opted in stay
	// on their current behavior. Calculator entries explicitly emit `bare`
	// from the partial.
	const syntaxRaw = input.dataset.calculationSyntax;
	const syntax = syntaxRaw === 'bare' ? 'bare' : 'bracket';

	// Optional pair-grouping identifier. Variables sharing a non-empty group
	// value treat each other as "paired" — when one is touched, the others
	// in the same group skip recompute so the user's typed value isn't
	// clobbered by a rounded round-trip through the group's other formulas.
	// Absent attribute → ungrouped (no group-based bail-out applies).
	const group = input.dataset.calculationGroup || '';

	// Reach into the container's Alpine scope. `Alpine.$data` returns the
	// reactive proxy that the container's `x-data` produced — reading
	// properties off it inside an effect registers Alpine dependencies
	// automatically.
	const formScope = Alpine.$data(scopeEl);
	if (!formScope) {
		console.warn('[calculation-engine] No Alpine scope on the container for input:', input);
		return;
	}

	// Pre-compute the list of source field references once; the formula is
	// fixed for the lifetime of the page so this doesn't need to live inside
	// the effect.
	const fieldNames = Calc.extractFieldNames(formula, syntax);

	// URL parameters a bracket formula reads through `[get_param:name]`. They
	// are never form-scope keys, so they're checked against the URL rather
	// than the scope. Bare syntax reads them through `getParam(name)`, which
	// the readiness check doesn't wait on.
	const urlParameterNames = syntax === 'bracket' ? Calc.extractUrlParameterNames(formula) : [];

	// Shape a fake config object for the shared dispatch helper so we can
	// reuse `Calc.calculateValue` end-to-end. This mirrors what the CP Vue
	// component would have as `this.config`.
	const config = {
		calculation_query: true,
		calculation_type: type,
		calculation_formula: formula,
		calculation_formula_syntax: syntax,
		calculation_decimals: decimals,
	};

	// Track which field the user is editing so bidirectional calculators
	// (every variable has a formula and is editable) don't end up overwriting
	// the user's keystrokes — or writing in a loop with each other. The
	// group is recorded alongside the handle so paired effects can bail out
	// when another member of their group is the active edit.
	const markTouched = () => {
		formScope[LAST_TOUCHED_KEY] = handle;
		formScope[LAST_TOUCHED_GROUP_KEY] = group;
	};
	input.addEventListener('input', markTouched);
	input.addEventListener('focus', markTouched);

	Alpine.effect(() => {
		// Bail out while the user is actively editing this field. Reading
		// the key registers a reactive dep so the effect re-runs when focus
		// moves to a sibling field.
		if (formScope[LAST_TOUCHED_KEY] === handle) return;

		// Bail when an explicit group is set on this effect and matches the
		// group of the currently-touched field — the user just typed into a
		// sibling of this paired group, so recomputing here would clobber
		// the user-entered value via a rounded round-trip. Variables with
		// no group never bail on this branch (the `group &&` short-circuit
		// makes ungrouped behavior identical to the prior un-paired
		// engine).
		if (group && formScope[LAST_TOUCHED_GROUP_KEY] === group) return;

		// Read each referenced field explicitly. That is what makes the values
		// readable at all: `Alpine.$data` returns a merged proxy that answers
		// no property-descriptor query, so `{ ...formScope }`,
		// `Object.entries()` and `Object.values()` all see an empty object.
		// It also registers a value dependency on exactly the fields the
		// formula names, and on no others.
		//
		// Keep a value only when the scope holds it as its own key, so a
		// reference such as `[constructor]` with no field of that name reads
		// as unfilled instead of copying `Object.prototype`'s value into an
		// own key (UAMS-Web/uams-statamic-archive#2936). `Alpine.$data` returns a merged proxy whose only
		// own-key answer is its `ownKeys` trap (no `getOwnPropertyDescriptor`),
		// so `Object.hasOwn` reports false even for real fields; read the key
		// list instead. That read registers an iteration dependency on the
		// scope's key set, so the effect also re-runs when any key is added to
		// or removed from the scope, whether or not the formula names it. The
		// per-reference value dependencies are unchanged, and a re-run with
		// nothing changed recomputes the same value and writes nothing.
		const scopeKeys = new Set(Reflect.ownKeys(formScope));
		const context = {};
		for (const name of fieldNames) {
			const value = formScope[name];
			context[name] = scopeKeys.has(name) ? value : undefined;
		}

		// Don't compute (and write a misleading "0.000" or partial result)
		// until every source field has a value. A partial set of inputs
		// yields garbage — e.g. on Feeding Calculator, having only
		// `weight_kg` (auto-populated by cross-calculator sync) would
		// produce NaN for `ml_kg_d = (ml_feed * feeds_per_d) / weight_kg`,
		// which formats as "0.000" and misleads. Wait for the full input
		// set; once all refs are filled, the formula runs normally and
		// follow-on edits recompute as expected.
		//
		// When refs *transition* from filled back to partial (e.g. a
		// synced `weight_kg` clears via a peer calculator's reset), the
		// primary clears too so the right column doesn't keep showing a
		// stale answer next to the now-missing input. Non-primary
		// formula-bearing variables bail instead — those are bidirectional
		// inputs the user may have typed into, and clobbering them would
		// destroy their typed value.
		//
		// Each reference is checked against the source it is evaluated from:
		// a field reference against the form scope, a `[get_param:name]`
		// against the URL (UAMS-Web/uams-statamic-archive#2884, UAMS-Web/uams-statamic-archive#2913).
		const hasValue = (v) => v !== undefined && v !== null && v !== '';
		const urlParameters = urlParameterNames.length > 0 ? Calc.getUrlParameters() : {};
		const allRefsHaveValue =
			fieldNames.every((name) => hasValue(context[name])) &&
			urlParameterNames.every((name) => hasValue(urlParameters[name]));
		if (!allRefsHaveValue) {
			if (isPrimary && lastWritten !== '') {
				lastWritten = '';
				formScope[handle] = isList ? [] : '';
			}
			return;
		}

		const result = Calc.calculateValue(config, context);
		if (result === null) return;

		const formatted = Calc.formatCalculatedValue(result, type, decimals);

		// Compare against the engine's own last write rather than the
		// current scope value so this effect doesn't register a reactive
		// dep on `formScope[handle]`. Without this, a bidirectional
		// primary like weight-conversion's `weight_kg` would re-fire on
		// any external write to its scope key (cross-calc sync, reset
		// engine) and immediately recompute the value back from its refs,
		// clobbering whatever was just written.
		if (lastWritten !== formatted) {
			lastWritten = formatted;
			// A list field binds an array: its boxes or options are checked by
			// value, so the text result is written as the values it names.
			formScope[handle] = isList ? listValues(formatted) : formatted;

			// `x-model` sets `checked` and `selected` without firing `change`, and
			// a required Checkboxes group reconciles its `required` attributes only
			// on `change` (checkbox-required-group.js). Fire it once the binding
			// has updated the controls, as a click would, so a box the engine
			// checked satisfies the group. The binding re-reads the control and
			// finds it already matches.
			if (isList) {
				Alpine.nextTick(() => input.dispatchEvent(new Event('change', { bubbles: true })));
			}
		}
	});
}

/**
 * Public entry. Lifecycle (wait for Alpine, scan, idempotent flag, per-element
 * init) lives in `lib/alpine-engine.js` and is shared with sibling engines
 * like `person-name-engine.js`. Safe to call from the site bundle's top level.
 */
export function registerCalculationEngine() {
	registerAlpineEngine({
		selector: CALCULATED_INPUT_SELECTOR,
		boundAttr: 'calculationBound',
		init: initCalculation,
	});
}
