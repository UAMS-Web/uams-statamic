import { describe, expect, it } from 'vitest';

import { evaluateNotContainsAny } from './evaluateNotContainsAny.js';

describe('evaluateNotContainsAny', () => {
	it('passes when an array shares no value with the options', () => {
		expect(evaluateNotContainsAny({ target: ['Credential'], params: ['Occupation', 'WorkRole'] })).toBe(true);
		expect(evaluateNotContainsAny({ target: ['Occupation'], params: ['Occupation', 'WorkRole'] })).toBe(false);
	});

	it('fails when a string contains any option', () => {
		expect(evaluateNotContainsAny({ target: 'ceterms:Occupation', params: ['Occupation', 'WorkRole'] })).toBe(false);
		expect(evaluateNotContainsAny({ target: 'ceterms:Credential', params: ['Occupation', 'WorkRole'] })).toBe(true);
	});

	it('matches an option literally, not as a pattern, as `contains_any` does', () => {
		expect(evaluateNotContainsAny({ target: 'axb', params: ['a.b'] })).toBe(true);
		expect(evaluateNotContainsAny({ target: 'a.b', params: ['a.b'] })).toBe(false);
		expect(evaluateNotContainsAny({ target: 'C++', params: ['C++'] })).toBe(false);
	});

	it('treats a missing value as empty, and refuses an empty option list', () => {
		expect(evaluateNotContainsAny({ target: null, params: ['x'] })).toBe(true);
		expect(evaluateNotContainsAny({ target: 'x', params: [] })).toBe(false);
	});
});
