/**
 * Shared calculation engine for `calculation_*` fieldtype config.
 *
 * Used by both:
 *   - The CP Vue fieldtype component
 *     (`resources/js/components/fieldtypes/partials/CustomInput.vue`)
 *   - The public-form Alpine engine
 *     (`resources/js/calculation-engine.js`)
 *
 * Functions here are pure (no `this`, no DOM beyond `window.location` in
 * `getUrlParameters`) so the two reactivity layers — Vue watches and Alpine
 * effects — can call into the same code path. Bug fixes and behavior tweaks
 * land once.
 *
 * Two formula syntaxes are supported for math mode, picked per field via the
 * `calculation_formula_syntax` config (default `bracket` for back-compat):
 *
 *   - `bracket` (legacy): `[weight_lb] * 0.453592 + [weight_oz] * 0.0283495`.
 *     `[handle]` references are translated to bare symbols (and `Math.fn(`→`fn(`,
 *     `**`→`^`) by `translateBracketToBare`, then evaluated by the SAME sandboxed
 *     mathjs instance as the bare path — no `new Function`, so an authored
 *     formula can never execute arbitrary JavaScript. Function calls may be
 *     written bare (`floor(...)`) or with the `Math.` prefix (translated away).
 *   - `bare`: `weight_lb * 0.453592 + weight_oz * 0.0283495`. Parsed and
 *     evaluated with mathjs in a sandboxed instance — the parser refuses to
 *     execute anything outside the registered function set (built-in math
 *     plus our `getParam(name)` URL-param helper). Function calls are bare
 *     names: `floor(weight_kg / 0.453592)`.
 *
 * Text mode is unaffected — it stays on the bracket-substitute path because
 * the use case (string templating) doesn't fit an expression evaluator.
 */

// `mathjs/number` is the number-only build — strips matrices, fractions,
// complex numbers, BigInt, and units. The full `mathjs` import balloons the
// bundle by ~250 KB minified for capabilities we don't use.
import { create, all } from 'mathjs/number';

/**
 * Sandboxed mathjs instance used by the bare-syntax math path. The default
 * `mathjs` exports expose `import`, `evaluate`, `simplify`, `derivative`,
 * and `createUnit` — any of which could be reached from inside an expression
 * to extend or escape the evaluator. We replace each with a thrower so a
 * malicious formula like `evaluate("…")` or `import("…")` fails fast rather
 * than executing.
 *
 * `parse` is intentionally **not** overridden: our own bare-syntax code
 * paths (`prettifyFormula`, `extractFieldNamesBare`, `calculateMathValueBare`)
 * all call `mathInstance.parse(formula)` to obtain an AST. Overriding the
 * public `parse` would disable our own evaluator entirely. Parsing alone
 * doesn't execute anything — any meaningful escape attempt would still need
 * to reach `evaluate` (still overridden) or `node.evaluate(scope)` via a
 * pre-existing Node reference (not reachable from inside a formula).
 *
 * Authored formulas are trusted (CMS author input, not visitor input), so
 * this is defense in depth — but defense in depth is cheap here.
 *
 * `getParam(name)` is registered as a user-facing function so bare-syntax
 * formulas can reach URL query parameters without polluting the variable
 * namespace. Returns 0 when not in a browser, when the param is missing, or
 * when the value isn't a finite number.
 */
const mathInstance = create(all, {});
mathInstance.import(
	{
		import: () => {
			throw new Error('Function import is disabled');
		},
		createUnit: () => {
			throw new Error('Function createUnit is disabled');
		},
		evaluate: () => {
			throw new Error('Function evaluate is disabled');
		},
		simplify: () => {
			throw new Error('Function simplify is disabled');
		},
		derivative: () => {
			throw new Error('Function derivative is disabled');
		},
		getParam: (name) => {
			if (typeof window === 'undefined' || !window.location) return 0;
			try {
				const value = new URLSearchParams(window.location.search).get(String(name));
				if (value === null) return 0;
				const parsed = parseFloat(value);
				return isNaN(parsed) || !isFinite(parsed) ? 0 : parsed;
			} catch {
				return 0;
			}
		},
	},
	{ override: true },
);

/**
 * mathjs built-in constant names. When one of these appears as a bare symbol in
 * a formula it must resolve to mathjs's own value, so the scope builder must NOT
 * shadow it with a `0` default (the missing-field fallback). `Math.PI`/`Math.E`
 * are translated to `pi`/`e` by `translateBracketToBare` and rely on this too.
 *
 * @type {Set<string>}
 */
const MATHJS_CONSTANTS = new Set(['pi', 'e', 'tau', 'phi', 'Infinity', 'NaN']);

/**
 * Patterns blocked from a text formula — same shape as the math list but
 * focused on injection vectors that would otherwise survive interpolation
 * into HTML.
 *
 * @type {RegExp[]}
 */
export const DANGEROUS_PATTERNS_TEXT = [
	/\beval\s*\(/, /\bFunction\s*\(/, /\bscript\b/i, /\bon\w+\s*=/, /\bjavascript:/i, /\bdata:/i, /\bvbscript:/i,
];

/**
 * Display string for binary operators in the pretty-printed formula caption.
 * Names not listed here pass through verbatim — covers `+`, `-`, etc.
 */
const PRETTY_OPERATOR = {
	'*': '×',
	'/': '÷',
};

/**
 * Pretty-print a bare-syntax math formula for display above a calculator's
 * inputs, substituting each variable handle with its human label. Operators
 * `*` and `/` are rewritten as `×` and `÷`; everything else is left as the
 * author wrote it. Parentheses, function calls, and ternary conditionals are
 * preserved as the parser found them.
 *
 * Walks the mathjs AST so precedence is correctly reflected in the output —
 * a string regex replacement of handles would mangle adjacent identifiers
 * (e.g., turning `weight_lbs * 2` into `Weight (lb)s × 2` if the labels map
 * had `weight_lb` ahead of `weight_lbs`).
 *
 * When `options.html` is true, variable labels are wrapped in `<strong>` tags
 * and HTML-special characters in the label text are escaped. The caller is
 * expected to assign the result to `innerHTML`. When false (default), the
 * result is plain text safe for `textContent`.
 *
 * Returns the original formula string on parse failure so the caption stays
 * informative even if the formula is malformed.
 *
 * @param {string} formula The bare-syntax math expression.
 * @param {Record<string, string>} [labels={}] Map of handle → display label.
 *                                              Handles missing from the map
 *                                              render as the bare handle.
 * @param {{ html?: boolean }} [options]
 * @returns {string}
 */
export function prettifyFormula(formula, labels = {}, options = {}) {
	if (typeof formula !== 'string' || !formula.trim()) return '';
	const html = options.html === true;
	try {
		const node = mathInstance.parse(formula);
		return prettifyNode(node, labels, html);
	} catch (error) {
		console.warn('[calculation] Formula prettify failed; returning raw:', error);
		return html ? escapeHtml(formula) : formula;
	}
}

/**
 * Recursive walker for `prettifyFormula`. Each branch mirrors the AST node
 * types mathjs produces for the expressions our formulas use. The only
 * user-controlled string entering the output is the variable label, which is
 * HTML-escaped when `html` mode is active; everything else (operators from
 * `PRETTY_OPERATOR`, numeric constants, function names from mathjs's own
 * registry, parentheses) is either a controlled value or comes from the
 * already-validated formula source.
 *
 * @param {*} node A mathjs Node.
 * @param {Record<string, string>} labels
 * @param {boolean} html When true, wrap symbol labels in `<strong>` tags.
 * @returns {string}
 */
function prettifyNode(node, labels, html) {
	if (node.isSymbolNode) {
		const label = labels[node.name] ?? node.name;
		return html ? `<strong>${escapeHtml(label)}</strong>` : label;
	}
	if (node.isConstantNode) {
		return String(node.value);
	}
	if (node.isParenthesisNode) {
		return `(${prettifyNode(node.content, labels, html)})`;
	}
	if (node.isOperatorNode) {
		const op = PRETTY_OPERATOR[node.op] ?? node.op;
		// Unary operators (e.g., unary minus) render `op operand` with no space.
		if (node.args.length === 1) {
			return `${op}${prettifyNode(node.args[0], labels, html)}`;
		}
		// Binary operators render `left op right` with spaces.
		if (node.args.length === 2) {
			return `${prettifyNode(node.args[0], labels, html)} ${op} ${prettifyNode(node.args[1], labels, html)}`;
		}
		// Fallback for any operator arity we haven't anticipated.
		return node.toString();
	}
	if (node.isFunctionNode) {
		const fnName = node.fn?.name ?? node.name ?? '';
		const args = node.args.map((arg) => prettifyNode(arg, labels, html)).join(', ');
		return `${fnName}(${args})`;
	}
	if (node.isConditionalNode) {
		const condition = prettifyNode(node.condition, labels, html);
		const truthy = prettifyNode(node.trueExpr, labels, html);
		const falsy = prettifyNode(node.falseExpr, labels, html);
		return `${condition} ? ${truthy} : ${falsy}`;
	}
	// Unknown node shape — defer to mathjs's own stringifier.
	return typeof node.toString === 'function' ? node.toString() : '';
}

/**
 * Cap on how many references one formula may contain, `[field]` and
 * `[get_param:name]` counted together. Enforced on bracket-syntax math, by
 * `calculateMathValue()`, and on text, by `calculateTextValue()`. Bare-syntax
 * math is not checked against it: `calculateMathValue()` hands a bare formula
 * to mathjs before the check runs (UAMS-Web/uams-statamic-archive#2932).
 */
export const MAX_FIELDS_IN_FORMULA = 100;

/** Cap on how many replacement iterations may run per evaluation. */
export const MAX_REPLACEMENTS = 1000;

/**
 * Cap on a formula's length. Field-name extraction returns nothing past it, and
 * bracket-syntax math and text refuse a longer formula. Bare-syntax math skips
 * the check, as it does the reference cap (UAMS-Web/uams-statamic-archive#2932).
 */
export const MAX_FORMULA_LENGTH = 10000;

/** Cap on text-mode output length before truncation. */
export const MAX_TEXT_OUTPUT_LENGTH = 50000;

/** Cap on recursion depth when coercing a field value to a number. */
export const MAX_PARSE_DEPTH = 10;

/**
 * Read the active formula string out of a fieldtype config object.
 *
 * Blueprint shape: `calculation_formula` is the resolved value the Input trait
 * derives from either `calculation_formula_math` or `calculation_formula_text`;
 * the CP also writes through that key. Some authoring fieldtypes (e.g. code
 * editor) store the value as `{ code: "..." }`; unwrap that.
 *
 * @param {object | null | undefined} config Fieldtype config from the blueprint.
 * @returns {string} The formula string, or `""` when not set.
 */
export function resolveCalculationFormula(config) {
	let formula = config?.calculation_formula ?? config?.calculation_formula_math ?? '';
	if (typeof formula === 'object' && formula?.code) {
		formula = formula.code;
	}
	return typeof formula === 'string' ? formula : '';
}

/**
 * Decimal places the math formatter should use. Defaults to 2 when unset.
 *
 * @param {object | null | undefined} config Fieldtype config from the blueprint.
 * @returns {number} Decimals, possibly out of the 0–20 range — the formatter
 *                   handles clamping.
 */
export function resolveCalculationDecimals(config) {
	if (config != null && 'calculation_decimals' in config) {
		return config.calculation_decimals;
	}
	return 2;
}

/**
 * Whether the field is opted into calculation evaluation.
 *
 * @param {object | null | undefined} config
 * @returns {boolean}
 */
export function shouldCalculate(config) {
	return config?.calculation_query === true;
}

/**
 * `'math'` | `'text'` | `null` based on `calculation_type`.
 *
 * @param {object | null | undefined} config
 * @returns {'math' | 'text' | null}
 */
export function resolveCalculationType(config) {
	const t = config?.calculation_type;
	return t === 'math' || t === 'text' ? t : null;
}

/**
 * `'bare'` | `'bracket'` based on `calculation_formula_syntax`. Defaults to
 * `'bracket'` for backward compatibility with formulas authored against the
 * legacy `[handle]` syntax (the only shape that existed before the bare
 * mathjs-based path was added).
 *
 * Text mode ignores this value — text substitution always uses bracket
 * syntax regardless of what's configured.
 *
 * @param {object | null | undefined} config
 * @returns {'bare' | 'bracket'}
 */
export function resolveCalculationSyntax(config) {
	const s = config?.calculation_formula_syntax;
	return s === 'bare' ? 'bare' : 'bracket';
}

/**
 * Extract the field-handle references a formula depends on. Used by the
 * Alpine engine to register reactive dependencies before the effect runs.
 *
 * Dispatches on syntax:
 *   - `bracket` (default): regex-matches `[handle]` tokens. `[get_param:name]`
 *     references are URL parameters, not fields, so they are left out — read
 *     them with `extractUrlParameterNames()`. A URL parameter and a field may
 *     share a name, and each reads its own source (UAMS-Web/uams-statamic-archive#2913).
 *   - `bare`: parses the formula with mathjs and walks the AST for symbol
 *     references, excluding identifiers that appear in function-call
 *     position (so `floor` in `floor(x)` is not a variable, but `x` is).
 *     URL params are accessed via the `getParam(name)` function at
 *     evaluation time, not pre-merged, so they don't appear here.
 *
 * @param {string} formula
 * @param {'bare' | 'bracket'} [syntax='bracket']
 * @returns {string[]} Handles in encounter order, uncapped. A formula over
 *                     MAX_FIELDS_IN_FORMULA references is refused where it is
 *                     evaluated, not truncated here (UAMS-Web/uams-statamic-archive#2927): by
 *                     `calculateTextValue()`, and by `calculateMathValue()` on
 *                     the bracket path only. A bare math formula is not
 *                     checked against the cap (UAMS-Web/uams-statamic-archive#2932). The bare path returns
 *                     whatever the parser found.
 */
export function extractFieldNames(formula, syntax = 'bracket') {
	if (typeof formula !== 'string' || formula.length > MAX_FORMULA_LENGTH) {
		console.warn('[calculation] Formula too long for field extraction');
		return [];
	}
	if (syntax === 'bare') {
		return extractFieldNamesBare(formula);
	}
	try {
		const fieldMatches = formula.match(/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g) || [];
		return fieldMatches.map((m) => m.slice(1, -1));
	} catch (error) {
		console.error('[calculation] Error extracting field names:', error);
		return [];
	}
}

/**
 * The opening of a `[get_param:name]` token, sliced off to leave the name.
 *
 * @type {string}
 */
const URL_PARAMETER_TOKEN_PREFIX = '[get_param:';

/**
 * The prefix the bracket math path gives a URL parameter's mathjs symbol, so
 * `[get_param:qty]` and `[qty]` become different symbols. `$` is legal in a
 * mathjs symbol and illegal in a field handle (`[a-zA-Z_][a-zA-Z0-9_]*`), so
 * no field can collide with it.
 *
 * @type {string}
 */
const URL_PARAMETER_SYMBOL_PREFIX = '$get_param_';

/**
 * A text formula's `[field]` and `[get_param:name]` tokens, in one pattern so
 * they are substituted in a single pass. Group 1 is the `get_param:` prefix
 * when present; group 2 is the name.
 *
 * @type {RegExp}
 */
const TEXT_TOKEN_PATTERN = /\[(get_param:)?([a-zA-Z_][a-zA-Z0-9_]*)\]/g;

/**
 * Read `key` from `source` only when it is the object's own property, so a
 * name like `constructor` or `toString` never resolves to a value inherited
 * from `Object.prototype` (UAMS-Web/uams-statamic-archive#2923).
 *
 * @param {Record<string, *> | null | undefined} source
 * @param {string} key
 * @returns {*} The own value, or `undefined`.
 */
function ownValue(source, key) {
	return source !== null && source !== undefined && Object.hasOwn(source, key) ? source[key] : undefined;
}

/**
 * Extract the URL-parameter names a bracket formula reads through
 * `[get_param:name]`, in encounter order. The bare syntax reads URL parameters
 * through `getParam(name)` instead, so a bare formula yields none.
 *
 * @param {string} formula
 * @returns {string[]}
 */
export function extractUrlParameterNames(formula) {
	if (typeof formula !== 'string' || formula.length > MAX_FORMULA_LENGTH) return [];
	const matches = formula.match(/\[get_param:([a-zA-Z_][a-zA-Z0-9_]*)\]/g) || [];
	return matches.map((m) => m.slice(URL_PARAMETER_TOKEN_PREFIX.length, -1));
}

/**
 * Walk the parsed AST of a bare-syntax formula and collect symbol names that
 * aren't function-call positions. Returns an empty array on parse failure
 * (Alpine then registers no deps, the effect runs once, the evaluation also
 * fails, and the engine leaves the output blank — the desired UX for an
 * unparseable formula).
 *
 * @param {string} formula
 * @returns {string[]}
 */
function extractFieldNamesBare(formula) {
	try {
		const node = mathInstance.parse(formula);
		const names = new Set();
		node.traverse((n, _path, parent) => {
			if (!n.isSymbolNode) return;
			// Skip the symbol that names a function in a `fn(args)` call —
			// it's a function reference, not a variable. mathjs models this
			// as a FunctionNode whose `.fn` child is the name symbol.
			const isFunctionName = parent && parent.isFunctionNode && parent.fn === n;
			if (!isFunctionName) names.add(n.name);
		});
		return Array.from(names);
	} catch (error) {
		console.error('[calculation] Bare-syntax field extraction error:', error);
		return [];
	}
}

/**
 * Whether the formula passes the text deny-list.
 *
 * @param {string} formula
 * @returns {boolean}
 */
export function isValidTextFormula(formula) {
	for (const p of DANGEROUS_PATTERNS_TEXT) {
		if (p.test(formula)) {
			console.warn('[calculation] Potentially dangerous pattern detected in text formula:', p.source);
			return false;
		}
	}
	return true;
}

/**
 * Coerce a raw field value to a finite number for math evaluation.
 *
 * @param {*} value Anything from the form scope.
 * @param {number} depth Internal recursion counter; cap is MAX_PARSE_DEPTH.
 * @returns {number} A finite number; `0` for unparseable input.
 */
export function parseFieldValue(value, depth = 0) {
	if (depth > MAX_PARSE_DEPTH) {
		console.warn('[calculation] Maximum recursion depth exceeded in parseFieldValue');
		return 0;
	}
	if (value === null || value === undefined || value === '') return 0;
	if (typeof value === 'boolean') return value ? 1 : 0;
	if (Array.isArray(value)) {
		if (value.length === 0) return 0;
		if (depth > 0) return 0;
		return parseFieldValue(value[0], depth + 1);
	}
	if (typeof value === 'object' && value !== null) {
		try {
			const s = String(value);
			if (/^-?\d*\.?\d+$/.test(s)) return parseFloat(s);
			return 0;
		} catch {
			return 0;
		}
	}
	if (typeof value === 'string') {
		const trimmed = value.trim();
		if (trimmed === '') return 0;
		if (!/^-?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test(trimmed)) return 0;
		const parsed = parseFloat(trimmed);
		if (isNaN(parsed) || !isFinite(parsed)) return 0;
		if (Math.abs(parsed) > Number.MAX_SAFE_INTEGER) {
			return parsed > 0 ? Number.MAX_SAFE_INTEGER : -Number.MAX_SAFE_INTEGER;
		}
		return parsed;
	}
	if (typeof value === 'number') {
		if (isNaN(value) || !isFinite(value)) return 0;
		if (Math.abs(value) > Number.MAX_SAFE_INTEGER) {
			return value > 0 ? Number.MAX_SAFE_INTEGER : -Number.MAX_SAFE_INTEGER;
		}
		return value;
	}
	try {
		const parsed = parseFloat(String(value));
		return isNaN(parsed) ? 0 : parsed;
	} catch {
		return 0;
	}
}

/**
 * Translate a bracket-syntax formula into the bare mathjs dialect so it can be
 * evaluated by the sandboxed mathjs instance instead of `new Function`:
 *
 *   - `[handle]` → the bare symbol `handle`; `[get_param:name]` → the symbol
 *     `$get_param_name`, so a URL parameter never shares a symbol with a
 *     field of the same name (UAMS-Web/uams-statamic-archive#2913).
 *   - `Math.PI` / `Math.E` → `pi` / `e`; `Math.fn(` → `fn(` (mathjs uses bare
 *     function names — `Math.*` is not a symbol it knows).
 *   - `**` → `^` (mathjs's exponentiation operator).
 *
 * Operators `+ - * / % ^` and parentheses are already valid mathjs and pass
 * through unchanged.
 *
 * @param {string} formula  A bracket-syntax math formula.
 * @returns {string}  The equivalent bare-syntax formula.
 */
export function translateBracketToBare(formula) {
	return formula
		.replace(/\[get_param:([a-zA-Z_][a-zA-Z0-9_]*)\]/g, (_match, name) => URL_PARAMETER_SYMBOL_PREFIX + name)
		.replace(/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g, '$1')
		.replace(/\bMath\.PI\b/g, 'pi')
		.replace(/\bMath\.E\b/g, 'e')
		.replace(/\bMath\.([a-zA-Z][a-zA-Z0-9]*)\s*\(/g, '$1(')
		.replace(/\*\*/g, '^');
}

/**
 * Read the current URL's query parameters as a flat object.
 *
 * @returns {Record<string, string>}
 */
export function getUrlParameters() {
	// Fast path for non-browser contexts (Node tests, SSR). Browsers always have
	// window; missing window is "no URL params available" not an error worth logging.
	if (typeof window === 'undefined' || !window.location) return Object.create(null);
	try {
		// Null prototype: a lookup of `constructor` or `toString` finds only a
		// real query parameter by that name (UAMS-Web/uams-statamic-archive#2923).
		const params = Object.create(null);
		const url = new URL(window.location.href);
		for (const [key, value] of url.searchParams.entries()) {
			params[key] = value;
		}
		return params;
	} catch (error) {
		console.error('[calculation] Error getting URL parameters:', error);
		return Object.create(null);
	}
}

/**
 * Whether the formula contains any `[get_param:name]` references.
 *
 * @param {string} formula
 * @returns {boolean}
 */
export function hasUrlParameters(formula) {
	if (!formula) return false;
	try {
		return /\[get_param:([a-zA-Z_][a-zA-Z0-9_]*)\]/.test(formula);
	} catch (error) {
		console.error('[calculation] Error checking for URL parameters:', error);
		return false;
	}
}

/**
 * Evaluate a math formula against a context of named values. Returns `null`
 * when the formula is empty, fails validation, or evaluation throws — caller
 * should treat `null` as "no calculated value, leave the input as-is."
 *
 * Dispatches on syntax. See the module-level docstring for the difference
 * between the two paths.
 *
 * @param {string} formula Raw blueprint formula.
 * @param {Record<string, *>} context Map of available field values; values
 *                                    may be any type and will be coerced via
 *                                    `parseFieldValue`.
 * @param {'bare' | 'bracket'} [syntax='bracket']
 * @param {Record<string, string>} [urlParameters] The URL's query parameters,
 *                                    read by bracket `[get_param:name]`
 *                                    references. Unused by the bare path.
 * @returns {number | null}
 */
export function calculateMathValue(formula, context, syntax = 'bracket', urlParameters = getUrlParameters()) {
	if (syntax === 'bare') {
		return calculateMathValueBare(formula, context);
	}
	// Bracket syntax: translate `[handle]` references (and the JS `Math.fn`/`**`
	// dialect) into the bare mathjs syntax, then evaluate through the SAME
	// sandboxed mathjs instance as the bare path — no `new Function`, so an
	// author-supplied formula can never execute arbitrary JavaScript.
	try {
		if (!formula) return null;
		if (formula.length > MAX_FORMULA_LENGTH) {
			throw new Error(`Formula too long (${formula.length})`);
		}
		const referenceCount = extractFieldNames(formula).length + extractUrlParameterNames(formula).length;
		if (referenceCount > MAX_FIELDS_IN_FORMULA) {
			throw new Error(`Too many fields in formula (${referenceCount}), maximum allowed is ${MAX_FIELDS_IN_FORMULA}`);
		}
		const scopeContext = { ...context };
		for (const name of extractUrlParameterNames(formula)) {
			scopeContext[URL_PARAMETER_SYMBOL_PREFIX + name] = ownValue(urlParameters, name);
		}
		return calculateMathValueBare(translateBracketToBare(formula), scopeContext);
	} catch (error) {
		console.error('[calculation] Math calculation error:', error);
		return null;
	}
}

/**
 * Bare-syntax math evaluation via the sandboxed mathjs instance. Builds a
 * scope object from the context (coerced through `parseFieldValue` so empty
 * strings, arrays, etc. become 0) and hands it to the parsed expression's
 * `.evaluate()`. Returns `null` on parse or evaluation failure; the caller
 * leaves the field blank.
 *
 * URL params are NOT pre-merged into the scope — the bare path exposes them
 * through the `getParam(name)` function registered on `mathInstance`. That
 * keeps the variable namespace clean (a handle named `utm_source` shadows
 * nothing) and makes URL-param access explicit in the formula.
 *
 * @param {string} formula
 * @param {Record<string, *>} context
 * @returns {number | null}
 */
function calculateMathValueBare(formula, context) {
	try {
		if (!formula) return null;
		const node = mathInstance.parse(formula);
		const scope = {};
		// Coerce every referenced variable; missing or non-numeric values
		// become 0 so a partially-filled calculator still evaluates rather
		// than throwing a "variable is undefined" parser error. Skip mathjs
		// built-in constants (pi, e, …) so they resolve to their real value
		// instead of being shadowed to 0 (e.g. a translated `Math.PI`).
		extractFieldNamesBare(formula).forEach((name) => {
			if (!MATHJS_CONSTANTS.has(name)) {
				scope[name] = parseFieldValue(context?.[name]);
			}
		});
		const result = node.evaluate(scope);
		if (typeof result !== 'number' || !isFinite(result)) {
			throw new Error('Formula result is not a finite number');
		}
		return result;
	} catch (error) {
		console.error('[calculation] Bare-syntax math calculation error:', error);
		return null;
	}
}

/**
 * Evaluate a text formula — straight string substitution of `[field]` and
 * `[get_param:name]` tokens. Returns `""` on failure.
 *
 * @param {string} formula
 * @param {Record<string, *>} context Field values, read by `[field]`.
 * @param {Record<string, string>} [urlParameters] The URL's query parameters,
 *                                    read by `[get_param:name]`.
 * @returns {string}
 */
export function calculateTextValue(formula, context, urlParameters = getUrlParameters()) {
	try {
		if (!formula) return '';
		if (formula.length > MAX_FORMULA_LENGTH) {
			throw new Error(`Formula too long (${formula.length})`);
		}
		if (!isValidTextFormula(formula)) {
			throw new Error('Invalid text formula structure detected');
		}
		// One pass over both token forms, with a replacer function: an inserted
		// value is never scanned for tokens again, and `$&`, `$'` and the like
		// in a value are inserted as typed rather than read as a replacement
		// pattern. Values are read from own keys only, so a name such as
		// `constructor` does not resolve to `Object.prototype` (UAMS-Web/uams-statamic-archive#2923).
		const tokenCount = (formula.match(TEXT_TOKEN_PATTERN) || []).length;
		if (tokenCount > MAX_FIELDS_IN_FORMULA) throw new Error('Too many replacements');
		let result = formula.replace(TEXT_TOKEN_PATTERN, (_token, urlPrefix, name) => {
			const value = ownValue(urlPrefix ? urlParameters : context, name) ?? '';
			return typeof value === 'string' && value.length > MAX_FORMULA_LENGTH
				? value.substring(0, MAX_FORMULA_LENGTH) + '...'
				: String(value);
		});
		if (result.length > MAX_TEXT_OUTPUT_LENGTH) result = result.substring(0, MAX_TEXT_OUTPUT_LENGTH) + '...';
		return result;
	} catch (error) {
		console.error('[calculation] Text calculation error:', error);
		return '';
	}
}

/**
 * Format a calculation result for display in the input. Math values are
 * passed through `Number.toFixed(decimals)` with decimals clamped to 0–20;
 * decimals === 0 rounds to integer. Text values are stringified as-is.
 *
 * @param {*} value Raw result from `calculateMathValue` or `calculateTextValue`.
 * @param {'math' | 'text'} type
 * @param {number} decimals
 * @returns {string}
 */
export function formatCalculatedValue(value, type, decimals) {
	if (value === null || value === undefined) return '';
	if (type === 'math') {
		const numValue = Number(value);
		if (isNaN(numValue) || !isFinite(numValue)) return String(value);
		if (Math.abs(numValue) > Number.MAX_SAFE_INTEGER) return numValue > 0 ? '∞' : '-∞';
		if (typeof decimals !== 'number' || decimals < 0 || decimals > 20) return numValue.toFixed(2);
		if (decimals === 0) return Math.round(numValue).toString();
		return numValue.toFixed(decimals);
	}
	if (type === 'text') return String(value);
	return String(value);
}

/**
 * Top-level dispatch — pick math or text and run the matching evaluator.
 * Returns `null` when the formula doesn't apply (no `calculation_query`,
 * unknown `calculation_type`, or empty formula).
 *
 * @param {object} config Field config (provides `calculation_query`,
 *                        `calculation_type`, `calculation_formula`).
 * @param {Record<string, *>} formValues Current form scope, read by field
 *                                       references. URL parameters are read
 *                                       separately, by `[get_param:name]`.
 * @returns {number | string | null}
 */
export function calculateValue(config, formValues) {
	if (!shouldCalculate(config)) return null;
	const formula = resolveCalculationFormula(config);
	if (!formula) return null;
	const type = resolveCalculationType(config);
	if (!type) return null;
	const syntax = resolveCalculationSyntax(config);
	// Field references read the form values and `[get_param:name]` reads the
	// URL, each from its own source, so a URL parameter never overrides a
	// field of the same name (UAMS-Web/uams-statamic-archive#2913). Bare-syntax math reaches URL params
	// through `getParam(name)` instead.
	const urlParameters = getUrlParameters();
	if (type === 'math') return calculateMathValue(formula, { ...formValues }, syntax, urlParameters);
	if (type === 'text') return calculateTextValue(formula, { ...formValues }, urlParameters);
	return null;
}

/**
 * Escape a string for safe interpolation into HTML. Used by `prettifyNode`
 * in `html` mode so variable labels — the only piece of user-controlled text
 * that reaches the rendered caption — can't inject markup.
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
