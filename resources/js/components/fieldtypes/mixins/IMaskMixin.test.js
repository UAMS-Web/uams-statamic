import { describe, expect, it } from 'vitest';
import IMaskMixin, { parseDefinitions, parseDefinitionsConfig, toRegExpFromSource } from './IMaskMixin.js';

describe('toRegExpFromSource', () => {
    it('builds a RegExp from a bare pattern source', () => {
        const re = toRegExpFromSource('[0-9X]');

        expect(re).toBeInstanceOf(RegExp);
        expect(re.test('5')).toBe(true);
        expect(re.test('X')).toBe(true);
        expect(re.test('a')).toBe(false);
    });

    it('strips `/…/` wrapping before compiling', () => {
        const re = toRegExpFromSource('/[A-Za-z0-9X]/');

        expect(re).toBeInstanceOf(RegExp);
        expect(re.test('A')).toBe(true);
    });

    it('returns the original string when the pattern is invalid', () => {
        // An unterminated character class is not a valid RegExp.
        expect(toRegExpFromSource('[')).toBe('[');
    });
});

describe('parseDefinitions', () => {
    it('converts each RegExp-source string value to a RegExp', () => {
        const defs = parseDefinitions({ '#': '[0-9X]' });

        expect(defs['#']).toBeInstanceOf(RegExp);
        expect(defs['#'].test('0')).toBe(true);
    });

    it('leaves non-string values untouched', () => {
        const re = /[a-z]/;
        expect(parseDefinitions({ a: re }).a).toBe(re);
    });
});

describe('parseDefinitionsConfig (the eval boundary)', () => {
    it('parses a JSON definitions string into a RegExp map', () => {
        const defs = parseDefinitionsConfig('{"#": "[0-9X]"}');

        expect(defs['#']).toBeInstanceOf(RegExp);
        expect(defs['#'].test('X')).toBe(true);
    });

    it('does NOT execute a JavaScript code string (no new Function eval)', () => {
        // A `new Function("return " + value)()` of this string would run the
        // assignment; JSON.parse rejects it, so the side effect must never fire.
        globalThis.__IMASK_EVAL_PROBE__ = false;

        const result = parseDefinitionsConfig('(globalThis.__IMASK_EVAL_PROBE__ = true, {"#": "[0-9X]"})');

        expect(globalThis.__IMASK_EVAL_PROBE__).toBe(false);
        expect(result).toBeUndefined();

        delete globalThis.__IMASK_EVAL_PROBE__;
    });

    it('returns undefined for empty or non-string input', () => {
        expect(parseDefinitionsConfig('')).toBeUndefined();
        expect(parseDefinitionsConfig('   ')).toBeUndefined();
        expect(parseDefinitionsConfig(null)).toBeUndefined();
        expect(parseDefinitionsConfig(undefined)).toBeUndefined();
    });
});

describe('maskConfig (the real computed — declarative wiring, no eval)', () => {
    // Exercise the actual `maskConfig` computed via `.call(fakeThis)` so the
    // Vue wiring (config string → RegExp definitions, prepare_case → prepareChar)
    // is covered end-to-end, not just the extracted helpers. Uses the 'Z'
    // definition key — `#`/`@` are always overridden by the MaskedPattern
    // defaults, so 'Z' is what survives to assert on.
    function buildConfig(config) {
        const fakeThis = {
            effectiveInputMask: true,
            effectiveInputMaskClass: 'MaskedPattern',
            input_mask_prepend_text_enabled: false,
            input_mask_append_text_enabled: false,
            config,
        };

        return IMaskMixin.computed.maskConfig.call(fakeThis);
    }

    it('parses a definitions config string into RegExp values', () => {
        const cfg = buildConfig({ input_mask_definitions: '{"Z": "[0-9X]"}' });

        expect(cfg.definitions.Z).toBeInstanceOf(RegExp);
        expect(cfg.definitions.Z.test('X')).toBe(true);
        expect(cfg.definitions.Z.test('a')).toBe(false);
    });

    it('installs a built-in uppercase prepareChar from input_mask_prepare_case', () => {
        const cfg = buildConfig({ input_mask_prepare_case: 'uppercase' });

        expect(typeof cfg.prepareChar).toBe('function');
        expect(cfg.prepareChar('abc')).toBe('ABC');
    });

    it('does NOT execute a JS-code definitions string (eval boundary in the real computed)', () => {
        globalThis.__IMASK_CFG_PWNED__ = false;

        buildConfig({ input_mask_definitions: '(globalThis.__IMASK_CFG_PWNED__ = true, {"Z": "[0-9]"})' });

        expect(globalThis.__IMASK_CFG_PWNED__).toBe(false);

        delete globalThis.__IMASK_CFG_PWNED__;
    });

    it('omits the severed function-callback options from the built config (#1019)', () => {
        const cfg = buildConfig({
            input_mask_commit: 'return value;',
            input_mask_dispatch: 'return masks[0];',
            input_mask_format: 'return String(value);',
            input_mask_matchValue: 'return true;',
            input_mask_parse: 'return value;',
            input_mask_prepare: 'return value;',
            input_mask_validate: 'return true;',
        });

        for (const opt of ['commit', 'dispatch', 'format', 'matchValue', 'parse', 'prepare', 'validate']) {
            expect(cfg[opt]).toBeUndefined();
        }
    });
});
