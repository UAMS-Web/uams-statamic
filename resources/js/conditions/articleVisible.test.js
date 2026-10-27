import { describe, expect, it } from 'vitest';
import { evaluateArticleVisible } from './articleVisible.js';

const show = (values) => evaluateArticleVisible({ values });

describe('evaluateArticleVisible', () => {
	it('shows an original (not syndicated) article', () => {
		expect(show({ syndication_query: false })).toBe(true);
	});

	it('waits for the syndication question to be answered', () => {
		expect(show({ syndication_query: null })).toBe(false);
		expect(show({})).toBe(false);
	});

	it('waits for the syndication source to be picked when syndicated (state #3)', () => {
		expect(show({ syndication_query: true, syndication_source: null })).toBe(false);
		expect(show({ syndication_query: true })).toBe(false);
	});

	it('hides NCI-syndicated text, which uses the embed instead', () => {
		expect(show({ syndication_query: true, syndication_source: 'nci' })).toBe(false);
	});

	it('shows an article syndicated from a non-NCI (Other) source', () => {
		expect(show({ syndication_query: true, syndication_source: 'other' })).toBe(true);
	});

	it('treats string and numeric false spellings as not-syndicated', () => {
		expect(show({ syndication_query: 'false' })).toBe(true);
		expect(show({ syndication_query: 0 })).toBe(true);
	});

	it('does not depend on a type field (the blueprint is the article type)', () => {
		// No `type` key, and an unrelated `type` value, both resolve purely on syndication.
		expect(show({ syndication_query: false })).toBe(true);
		expect(show({ type: 'video', syndication_query: true, syndication_source: 'nci' })).toBe(false);
	});

	it('falls back to root values when values is absent', () => {
		expect(evaluateArticleVisible({ root: { syndication_query: false } })).toBe(true);
	});
});
