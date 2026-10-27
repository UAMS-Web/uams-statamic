import { describe, expect, it } from 'vitest';
import { evaluateNotSyndicated } from './notSyndicated.js';

const show = (values) => evaluateNotSyndicated({ values });

describe('evaluateNotSyndicated', () => {
	it('shows once the syndication question is answered "No"', () => {
		expect(show({ syndication_query: false })).toBe(true);
	});

	it('stays hidden until the syndication question has a value', () => {
		expect(show({ syndication_query: null })).toBe(false);
		expect(show({})).toBe(false);
	});

	it('hides for a syndicated article', () => {
		expect(show({ syndication_query: true })).toBe(false);
	});

	it('treats string and numeric false spellings as answered "No"', () => {
		expect(show({ syndication_query: 'false' })).toBe(true);
		expect(show({ syndication_query: 0 })).toBe(true);
	});

	it('falls back to root values when values is absent', () => {
		expect(evaluateNotSyndicated({ root: { syndication_query: false } })).toBe(true);
	});
});
