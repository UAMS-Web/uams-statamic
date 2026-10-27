import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    calculateMathValue,
    calculateTextValue,
    calculateValue,
    extractFieldNames,
    extractUrlParameterNames,
    getUrlParameters,
    translateBracketToBare,
} from './calculation.js';

const CTX = { weight_lb: '10', weight_oz: '8', price: 100, qty: 3, a: 2, b: 5 };

describe('translateBracketToBare', () => {
    it('strips [handle] brackets to bare symbols and gives [get_param:name] its own symbol', () => {
        expect(translateBracketToBare('[price] * [qty] + [get_param:fee]')).toBe('price * qty + $get_param_fee');
    });

    it('rewrites the JS Math dialect to mathjs (Math.fn -> fn, ** -> ^, Math.PI -> pi)', () => {
        expect(translateBracketToBare('Math.floor([a]) ** 2')).toBe('floor(a) ^ 2');
        expect(translateBracketToBare('Math.PI * Math.E')).toBe('pi * e');
    });

    it('rewrites digit-suffixed Math functions (log2, atan2, log10)', () => {
        expect(translateBracketToBare('Math.log2([a])')).toBe('log2(a)');
        expect(translateBracketToBare('Math.atan2([a], [b])')).toBe('atan2(a, b)');
        expect(translateBracketToBare('Math.log10([a])')).toBe('log10(a)');
    });
});

describe('calculateMathValue (bracket syntax via mathjs)', () => {
    it('evaluates field references and arithmetic', () => {
        // 10 * 0.453592 + 8 * 0.0283495 = 4.53592 + 0.226796 = 4.762716
        expect(calculateMathValue('[weight_lb] * 0.453592 + [weight_oz] * 0.0283495', CTX, 'bracket'))
            .toBeCloseTo(4.762716, 5);
    });

    it('supports Math.* function calls (translated to mathjs)', () => {
        expect(calculateMathValue('Math.floor([price] / [qty])', CTX, 'bracket')).toBe(33);
        expect(calculateMathValue('Math.round(([price] * [qty]) * 1.08)', CTX, 'bracket')).toBe(324);
    });

    it('supports ** exponentiation and % modulo', () => {
        expect(calculateMathValue('[a] ** [b]', CTX, 'bracket')).toBe(32);
        expect(calculateMathValue('[price] % [qty]', CTX, 'bracket')).toBe(1);
    });

    it('follows left-to-right precedence for / and * (matches the legacy behavior)', () => {
        // a / b * c === (a / b) * c
        expect(calculateMathValue('[price] / [qty] * [a]', { price: 12, qty: 2, a: 3 }, 'bracket')).toBe(18);
    });

    it('resolves Math.PI / Math.E to the real constants (not shadowed to 0)', () => {
        expect(calculateMathValue('Math.PI * [a]', { a: 2 }, 'bracket')).toBeCloseTo(6.283185, 5);
        expect(calculateMathValue('Math.E', {}, 'bracket')).toBeCloseTo(2.718281, 5);
        // bare mathjs constants too
        expect(calculateMathValue('pi', {}, 'bracket')).toBeCloseTo(3.141592, 5);
    });

    it('supports digit-suffixed Math functions (Math.log2)', () => {
        expect(calculateMathValue('Math.log2([a])', { a: 8 }, 'bracket')).toBe(3);
    });

    it('uses mathjs floored modulo — `%` follows the divisor sign', () => {
        // JS `-1 % 3` is -1; mathjs floored mod is 2. Pin the (documented) mathjs behavior.
        expect(calculateMathValue('[a] % [b]', { a: -1, b: 3 }, 'bracket')).toBe(2);
    });

    it('rounds halves away from zero (mathjs), pinned for negatives', () => {
        // JS Math.round(-0.5) is -0 (toward +Inf); mathjs round is -1 (away from zero).
        expect(calculateMathValue('Math.round([x])', { x: -0.5 }, 'bracket')).toBe(-1);
    });

    it('returns null for an unparseable formula', () => {
        expect(calculateMathValue('[price] +', CTX, 'bracket')).toBeNull();
    });
});

describe('calculateMathValue (bracket) — evasion attempts must not execute JS', () => {
    // The old bypassable deny-list is deleted; protection now comes solely from
    // the sandboxed mathjs instance. This sentinel uses `globalThis` — a token
    // the old deny-list never matched — so it can ONLY pass because mathjs does
    // not execute it (it would fire under a raw `new Function`).
    it('does not execute a side effect via a non-denylisted global token', () => {
        globalThis.__CALC_SINK__ = false;

        const result = calculateMathValue('[price] * (globalThis.__CALC_SINK__ = true)', CTX, 'bracket');

        expect(globalThis.__CALC_SINK__).toBe(false);
        expect(result).toBeNull();

        delete globalThis.__CALC_SINK__;
    });

    it('does not execute a constructor / prototype-pollution payload', () => {
        globalThis.__CALC_PWNED__ = false;

        const result = calculateMathValue(
            '[price].constructor.constructor("globalThis.__CALC_PWNED__=true")()',
            CTX,
            'bracket',
        );

        expect(globalThis.__CALC_PWNED__).toBe(false);
        expect(result).toBeNull();

        delete globalThis.__CALC_PWNED__;
    });

    it('does not execute a comma-sequence side effect', () => {
        globalThis.__CALC_PWNED__ = false;

        const result = calculateMathValue('(globalThis.__CALC_PWNED__ = true)', CTX, 'bracket');

        expect(globalThis.__CALC_PWNED__).toBe(false);
        expect(result).toBeNull();

        delete globalThis.__CALC_PWNED__;
    });

    it('cannot reach host globals — they are inert (undefined-field default or parse failure)', () => {
        // A bare `window`/`document`/`fetch`/`process` is treated as an unknown
        // field (→ 0) or fails to parse (→ null); it never resolves to the real
        // host object, so a malicious formula can read neither cookies nor APIs.
        for (const payload of ['window', 'document.cookie', 'fetch("/x")', 'process.exit(1)']) {
            const result = calculateMathValue(payload, CTX, 'bracket');
            expect(result === null || typeof result === 'number').toBe(true);
        }
    });
});

describe('calculateValue (top-level dispatch the Vue/Alpine layers call)', () => {
    it('dispatches bracket math', () => {
        const config = { calculation_query: true, calculation_type: 'math', calculation_formula: '[a] + [b]' };
        expect(calculateValue(config, { a: 2, b: 3 })).toBe(5);
    });

    it('dispatches bare math', () => {
        const config = {
            calculation_query: true,
            calculation_type: 'math',
            calculation_formula: 'a + b',
            calculation_formula_syntax: 'bare',
        };
        expect(calculateValue(config, { a: 2, b: 3 })).toBe(5);
    });

    it('dispatches text substitution', () => {
        const config = { calculation_query: true, calculation_type: 'text', calculation_formula: 'Hi [name]' };
        expect(calculateValue(config, { name: 'Bob' })).toBe('Hi Bob');
    });

    it('returns null when not opted in or the type is unknown', () => {
        expect(calculateValue({ calculation_query: false }, {})).toBeNull();
        expect(calculateValue({ calculation_query: true, calculation_type: 'bogus', calculation_formula: '[a]' }, { a: 1 })).toBeNull();
    });

    it('resolves a bracket [get_param:x] reference from the URL query', () => {
        globalThis.window = { location: { href: 'http://example.test/?qty=5', search: '?qty=5' } };
        try {
            const config = { calculation_query: true, calculation_type: 'math', calculation_formula: '[get_param:qty] * 2' };
            expect(calculateValue(config, {})).toBe(10);
        } finally {
            delete globalThis.window;
        }
    });
});

describe('field references and URL parameters read separate sources (#2913)', () => {
    const withUrl = (href, fn) => {
        globalThis.window = { location: { href, search: new URL(href).search } };
        try {
            fn();
        } finally {
            delete globalThis.window;
        }
    };
    const math = (formula) => ({ calculation_query: true, calculation_type: 'math', calculation_formula: formula });
    const text = (formula) => ({ calculation_query: true, calculation_type: 'text', calculation_formula: formula });

    it('lists field references and URL parameters apart', () => {
        expect(extractFieldNames('[qty] + [get_param:qty] + [price]')).toEqual(['qty', 'price']);
        expect(extractUrlParameterNames('[qty] + [get_param:qty] + [price]')).toEqual(['qty']);
        expect(extractUrlParameterNames('getParam("qty") + qty')).toEqual([]);
    });

    it('reads [qty] from the form when the URL also carries qty', () => {
        withUrl('http://example.test/?qty=5', () => {
            expect(calculateValue(math('[price] * [qty]'), { price: '4', qty: '3' })).toBe(12);
            expect(calculateValue(text('Hi [qty]'), { qty: '3' })).toBe('Hi 3');
        });
        withUrl('http://example.test/?qty=', () => {
            expect(calculateValue(math('[price] * [qty]'), { price: '4', qty: '3' })).toBe(12);
        });
    });

    it('reads [get_param:qty] from the URL when a form field qty exists', () => {
        withUrl('http://example.test/?qty=5', () => {
            expect(calculateValue(math('[get_param:qty] * 2'), { qty: '3' })).toBe(10);
            expect(calculateValue(text('Hi [get_param:qty]'), { qty: '3' })).toBe('Hi 5');
            expect(calculateValue(math('[qty] + [get_param:qty]'), { qty: '3' })).toBe(8);
            expect(calculateValue(text('[qty]/[get_param:qty]'), { qty: '3' })).toBe('3/5');
        });
    });

    it('takes URL parameters as an argument when called directly', () => {
        expect(calculateMathValue('[get_param:qty] * 2', { qty: '3' }, 'bracket', { qty: '5' })).toBe(10);
        expect(calculateTextValue('[qty]-[get_param:qty]', { qty: '3' }, { qty: '5' })).toBe('3-5');
    });

    it('refuses a bracket math formula over the reference cap, counting both kinds', () => {
        const fields = Array.from({ length: 60 }, (_, i) => `[f${i}]`).join(' + ');
        const params = Array.from({ length: 41 }, (_, i) => `[get_param:p${i}]`).join(' + ');
        expect(calculateMathValue(`${fields} + ${params}`, {}, 'bracket', {})).toBeNull();
        expect(calculateMathValue(fields, {}, 'bracket', {})).toBe(0);
    });

    it('leaves bare-syntax math on getParam()', () => {
        withUrl('http://example.test/?qty=5', () => {
            const config = { ...math('qty + getParam("qty")'), calculation_formula_syntax: 'bare' };
            expect(calculateValue(config, { qty: 3 })).toBe(8);
        });
    });
});

describe('the reference cap applies to bracket math only (#2932)', () => {
    // A characterization of today's asymmetry, not a claim that it is right:
    // whether bare math should be bounded too is a policy question UAMS-Web/uams-statamic-archive#2932 leaves
    // open. If that changes, the bare case below is the one to flip.
    const names = Array.from({ length: 101 }, (_, i) => `a${i}`);
    const context = Object.fromEntries(names.map((name) => [name, 1]));

    it('refuses a 101-reference formula on the bracket path', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(calculateMathValue(names.map((name) => `[${name}]`).join(' + '), context, 'bracket', {})).toBeNull();
        expect(error).toHaveBeenCalledTimes(1);
        vi.restoreAllMocks();
    });

    it('evaluates the same 101 references on the bare path', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(calculateMathValue(names.join(' + '), context, 'bare')).toBe(101);
        expect(error).not.toHaveBeenCalled();
        vi.restoreAllMocks();
    });

    it('evaluates a three-reference formula on both paths (control)', () => {
        expect(calculateMathValue('[a0] + [a1] + [a2]', context, 'bracket', {})).toBe(3);
        expect(calculateMathValue('a0 + a1 + a2', context, 'bare')).toBe(3);
    });
});

describe('extractFieldNames over the reference cap (#2927)', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    const fields = Array.from({ length: 101 }, (_, i) => `[f${i}]`).join(' + ');

    it('returns every field reference, in encounter order, without claiming to limit them', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const names = extractFieldNames(`${fields} + [get_param:p]`);

        expect(names).toHaveLength(101);
        expect(names[0]).toBe('f0');
        expect(names[100]).toBe('f100');
        expect(names).not.toContain('p');
        expect(warn).not.toHaveBeenCalled();
    });

    it('leaves refusing an over-cap formula to the evaluators', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(calculateMathValue(fields, {}, 'bracket', {})).toBeNull();
        expect(calculateTextValue(fields, {}, {})).toBe('');
        expect(error).toHaveBeenCalledTimes(2);
    });
});

describe('text substitution inserts values literally, once, from own keys (#2923)', () => {
    const text = (formula) => ({ calculation_query: true, calculation_type: 'text', calculation_formula: formula });
    const withUrl = (search, fn) => {
        globalThis.window = { location: { href: `http://example.test/${search}`, search } };
        try {
            fn();
        } finally {
            delete globalThis.window;
        }
    };

    it('substitutes a plain value (control)', () => {
        expect(calculateValue(text('Hi [who]'), { who: 'ab' })).toBe('Hi ab');
    });

    it.each(['a$&b', 'a$`b', "a$'b", 'a$1b', 'a$$b'])('inserts %s without reading it as a replacement pattern', (value) => {
        expect(calculateValue(text('Hi [who]'), { who: value })).toBe(`Hi ${value}`);
        withUrl(`?x=${encodeURIComponent(value)}`, () => {
            expect(calculateValue(text('Hi [get_param:x]'), {})).toBe(`Hi ${value}`);
        });
    });

    it('does not substitute a token that a substituted value contains', () => {
        withUrl('?x=URL', () => {
            expect(calculateValue(text('[who] / [get_param:x]'), { who: '[get_param:x]' })).toBe('[get_param:x] / URL');
            expect(calculateValue(text('[who] / [other]'), { who: '[other]', other: 'O' })).toBe('[other] / O');
        });
    });

    it('resolves a missing parameter (control) and an Object.prototype name to empty', () => {
        withUrl('', () => {
            expect(calculateValue(text('[get_param:missing]'), {})).toBe('');
            expect(calculateValue(text('[get_param:constructor]'), {})).toBe('');
            expect(calculateValue(text('[get_param:toString]|[get_param:__proto__]'), {})).toBe('|');
        });
        expect(calculateTextValue('[get_param:constructor]', {}, {})).toBe('');
        expect(calculateTextValue('[constructor]', {}, {})).toBe('');
    });

    it('still reads a parameter named like a prototype key when the URL carries it', () => {
        withUrl('?constructor=C', () => {
            expect(calculateValue(text('[get_param:constructor]'), {})).toBe('C');
        });
    });

    it('refuses a text formula over the length limit, as the math path does', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        const long = `[who] ${'x'.repeat(10000)}`;

        expect(calculateTextValue(long, { who: 'Ann' }, {})).toBe('');
        expect(calculateMathValue(`[who] + ${'1'.repeat(10000)}`, { who: 1 }, 'bracket', {})).toBeNull();
        expect(error).toHaveBeenCalledTimes(2);
        vi.restoreAllMocks();
    });

    it('builds URL parameters on a null prototype on every path', () => {
        expect(Object.getPrototypeOf(getUrlParameters())).toBeNull();
        withUrl('?a=1', () => {
            expect(Object.getPrototypeOf(getUrlParameters())).toBeNull();
        });
    });

    it('still refuses a text formula over the reference cap', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        const tokens = Array.from({ length: 60 }, (_, i) => `[f${i}]`).concat(Array.from({ length: 41 }, (_, i) => `[get_param:p${i}]`));

        expect(calculateTextValue(tokens.join(' '), {}, {})).toBe('');
        expect(calculateTextValue(tokens.slice(0, 100).join(''), {}, {})).toBe('');
        expect(error).toHaveBeenCalledTimes(1);
        vi.restoreAllMocks();
    });
});
