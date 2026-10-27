/**
 * Composite-variable split engine.
 *
 * A "composite" variable in a calculator entry is a single logical value
 * (e.g., weight in decimal pounds) rendered as two adjacent inputs (e.g., lb
 * and oz). The composite handle is the one referenced from other formulas;
 * the sub-handles are display-only projections that recompose back into the
 * composite when edited.
 *
 * This engine wires both directions:
 *
 *   Forward (composite → splits)
 *     One `Alpine.effect` per split sub-input. Reads the composite from the
 *     surrounding scope, applies the sub-input's `strategy` (with `multiplier`
 *     when applicable), formats to `decimals`, writes to the sub-handle in
 *     the scope. Skipped when the sub-input has focus, so the user's typed
 *     keystrokes aren't clobbered.
 *
 *   Reverse (sub-edit → composite)
 *     One `input` listener per split sub-input. Reads every sibling sub-input
 *     belonging to the same composite, sums their inverse contributions, marks
 *     the composite as touched (so the composite's own formula in
 *     `calculation-engine.js` won't immediately overwrite the recomposed
 *     value), and writes the recomposed composite to the scope.
 *
 * Strategies and their inverse contributions:
 *
 *   - `integer`:           forward = floor(composite); inverse = value
 *   - `fractional_times`:  forward = (composite - floor(composite)) * mult;
 *                          inverse = value / mult
 *
 * The empty-composite guard (`composite === '' || composite == null`) prevents
 * the initial page-load state from rendering "0" into every split sub-input
 * before any source variable has a value — mirrors the calculation engine's
 * `allRefsHaveValue` guard for the same reason.
 */

// The CSP build, matching `site.js` — attribute expressions are AST-interpreted
// rather than compiled through `new Function()`, so `script-src` needs no
// `unsafe-eval` (UAMS-Web/uams-statamic-archive#1859). The constraint is on `x-*` attributes, not on modules.
import Alpine from '@alpinejs/csp';
import { registerAlpineEngine } from './lib/alpine-engine.js';
import { parseFieldValue, formatCalculatedValue } from './lib/calculation.js';

/** Selector matching each split sub-input emitted by `partials/calculator_variable`. */
const SPLIT_INPUT_SELECTOR = '[data-split-of]';

/**
 * Scope container selector — matches what `calculation-engine.js` uses so
 * composite-bound inputs and split sub-inputs always resolve to the same
 * Alpine `x-data` scope.
 */
const SCOPE_CONTAINER_SELECTOR = '[data-calculator-context], form';

/**
 * Reactive key on the scope used to suppress the composite's own formula
 * effect when a split sub-input was just edited. Mirrors the constant by the
 * same name in `calculation-engine.js` — both engines read/write it.
 */
const LAST_TOUCHED_KEY = '_calc_last_touched';
const LAST_TOUCHED_GROUP_KEY = '_calc_last_touched_group';

/**
 * Project the composite's current numeric value onto one split sub-input
 * via its strategy.
 *
 * @param {number} composite Parsed numeric composite value.
 * @param {string} strategy 'integer' | 'fractional_times'
 * @param {number} multiplier Used by `fractional_times`.
 * @returns {number}
 */
function applyStrategy(composite, strategy, multiplier) {
	if (strategy === 'integer') {
		return Math.floor(composite);
	}
	if (strategy === 'fractional_times') {
		return (composite - Math.floor(composite)) * multiplier;
	}
	return 0;
}

/**
 * Reverse projection: how much this sub-input's typed value contributes to
 * the composite. Sum of all siblings' inverse contributions reproduces the
 * composite (e.g., `lb + oz/16` for decimal pounds).
 *
 * @param {number} subValue Parsed sub-input numeric value.
 * @param {string} strategy
 * @param {number} multiplier
 * @returns {number}
 */
function inverseContribution(subValue, strategy, multiplier) {
	if (strategy === 'integer') {
		return subValue;
	}
	if (strategy === 'fractional_times') {
		return multiplier === 0 ? 0 : subValue / multiplier;
	}
	return 0;
}

/**
 * Read a split sub-input's authoring config off its dataset.
 *
 * @param {HTMLElement} input
 * @returns {{ compositeHandle: string, subHandle: string, strategy: string, multiplier: number, decimals: number }}
 */
function readSplitConfig(input) {
	const compositeHandle = input.dataset.splitOf || '';
	const subHandle = input.getAttribute('name') || '';
	const strategy = input.dataset.splitStrategy || 'integer';
	const multiplier = parseFloat(input.dataset.splitMultiplier ?? '0') || 0;
	const decimalsRaw = input.dataset.splitDecimals;
	const decimals = decimalsRaw !== undefined && decimalsRaw !== '' ? parseInt(decimalsRaw, 10) : 0;
	return { compositeHandle, subHandle, strategy, multiplier, decimals };
}

/**
 * Wire one split sub-input. Sets up:
 *   - The forward Alpine.effect that projects the composite into this sub.
 *   - The input listener that recomposes the composite when this sub changes.
 *
 * @param {HTMLElement} input
 */
function initSplit(input) {
	const scopeEl = input.closest(SCOPE_CONTAINER_SELECTOR);
	if (!scopeEl) {
		console.warn('[composite-split-engine] Split input is not inside a calculator scope:', input);
		return;
	}

	const { compositeHandle, subHandle, strategy, multiplier, decimals } = readSplitConfig(input);
	if (!compositeHandle || !subHandle) {
		console.warn('[composite-split-engine] Missing composite or sub handle for input:', input);
		return;
	}

	const scope = Alpine.$data(scopeEl);
	if (!scope) {
		console.warn('[composite-split-engine] No Alpine scope on the container for input:', input);
		return;
	}

	Alpine.effect(() => {
		const compositeRaw = scope[compositeHandle];
		// Empty composite — initial load with no upstream value yet. Don't
		// project "0" into every sub-input or the calculator looks
		// pre-populated on first render.
		if (compositeRaw === '' || compositeRaw == null) return;
		// Skip the sub the user is actively editing — writing the formatted
		// value back would clobber in-progress keystrokes.
		if (document.activeElement === input) return;

		const composite = parseFieldValue(compositeRaw);
		const projected = applyStrategy(composite, strategy, multiplier);
		const formatted = formatCalculatedValue(projected, 'math', decimals);
		if (scope[subHandle] !== formatted) {
			scope[subHandle] = formatted;
		}
	});

	input.addEventListener('input', () => {
		// Gather every sibling split sub-input that targets the same
		// composite. We re-query each event so newly-added composite splits
		// (a Bard re-render, say) are handled without stale closure state.
		const siblings = scopeEl.querySelectorAll(`[data-split-of="${CSS.escape(compositeHandle)}"]`);
		let recomposed = 0;
		siblings.forEach((sib) => {
			const cfg = readSplitConfig(sib);
			const value = parseFieldValue(scope[cfg.subHandle]);
			recomposed += inverseContribution(value, cfg.strategy, cfg.multiplier);
		});

		// Mark the composite as touched so its own formula effect in
		// `calculation-engine.js` won't fire on the value change we're about
		// to make. The group is also cleared so paired-group bailouts don't
		// inadvertently apply to other variables in the calculator.
		scope[LAST_TOUCHED_KEY] = compositeHandle;
		scope[LAST_TOUCHED_GROUP_KEY] = '';

		if (scope[compositeHandle] !== recomposed) {
			scope[compositeHandle] = recomposed;
		}
	});
}

/**
 * Public entry. Same lifecycle as `calculation-engine.js` and
 * `formula-caption-engine.js` — bind once Alpine has booted, idempotent on
 * re-scan.
 */
export function registerCompositeSplitEngine() {
	registerAlpineEngine({
		selector: SPLIT_INPUT_SELECTOR,
		boundAttr: 'compositeSplitBound',
		init: initSplit,
	});
}
