import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
	allResultsUrl,
	buildOptions,
	isNavigableUrl,
	createSiteSearchTypeahead,
	DEBOUNCE_MS,
	fetchSuggestions,
	statusText,
} from './site-search-typeahead.js';

const SUGGESTIONS = [
	{ title: 'Leonard McCoy', url: '/people/leonard-mccoy', type: 'Person' },
	{ title: 'Cardiology', url: '/cardiology', type: 'Area of Expertise' },
];

/**
 * A `fetch` stand-in answering every request with the given suggestions.
 *
 * @param {Array<object>} results  The suggestions to return.
 * @returns {Function} The mock.
 */
function fetchReturning(results) {
	return vi.fn(async () => ({ ok: true, json: async () => ({ results }) }));
}

/**
 * A component with its collaborators mocked.
 *
 * @param {Function} fetchImpl  The fetch stand-in.
 * @returns {{component: object, navigate: Function}} The component and its navigate spy.
 */
function makeComponent(fetchImpl) {
	const navigate = vi.fn();
	const component = createSiteSearchTypeahead({ fetchImpl, navigate, debounceMs: DEBOUNCE_MS, index: 'default' });

	return { component, navigate };
}

/**
 * Type a query and let the debounce and the request settle.
 *
 * @param {object} component  The component.
 * @param {string} query  The query.
 */
async function typeAndSettle(component, query) {
	component.query = query;
	component.onInput();
	await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
}

describe('buildOptions', () => {
	it('lists each suggestion, then a "see all results" option that lands on /search', () => {
		const options = buildOptions(SUGGESTIONS, 'card io', -1);

		expect(options.map((option) => option.url)).toEqual([
			'/people/leonard-mccoy',
			'/cardiology',
			'/search?q=card%20io&index=default',
		]);
		expect(options[2].isAll).toBe(true);
		expect(options[2].title).toBe('See all results for “card io”');
		expect(new Set(options.map((option) => option.id)).size).toBe(3);
	});

	it('marks only the active option selected, as the string ARIA expects', () => {
		const options = buildOptions(SUGGESTIONS, 'card', 1);

		expect(options.map((option) => option.selected)).toEqual(['false', 'true', 'false']);
		expect(options[1].className).toBe('active');
		expect(options[2].className).toBe('site-search-suggestion-all');
	});
});

describe('allResultsUrl and statusText', () => {
	it('encodes the query the way a form submission would reach /search', () => {
		expect(allResultsUrl("O'Brien & co")).toBe("/search?q=O'Brien%20%26%20co&index=default");
		expect(allResultsUrl('card', 'people')).toBe('/search?q=card&index=people');
		expect(allResultsUrl('card', 'people', { path: '/health/search', scope: 'all' })).toBe('/health/search?q=card&index=people&scope=all');
		expect(allResultsUrl('card', 'people', { path: '/health/search', scope: '' })).toBe('/health/search?q=card&index=people');
	});

	it('announces the count, singular and plural, and the empty case', () => {
		expect(statusText(0)).toBe('No suggestions. Press Enter to search.');
		expect(statusText(1)).toMatch(/^1 suggestion available/);
		expect(statusText(2)).toMatch(/^2 suggestions available/);
	});
});

describe('fetchSuggestions', () => {
	it('asks the suggestions endpoint with the query encoded', async () => {
		const fetchImpl = fetchReturning(SUGGESTIONS);

		expect(await fetchSuggestions(fetchImpl, 'a&b', 'people')).toEqual(SUGGESTIONS);
		expect(fetchImpl.mock.calls[0][0]).toBe('/!/search/suggestions?q=a%26b&index=people');
	});

	it('resolves null, not "no suggestions", for a rate limit, a failure, an abort or a malformed body', async () => {
		const abort = Object.assign(new Error('aborted'), { name: 'AbortError' });

		expect(await fetchSuggestions(async () => ({ ok: false, status: 429 }), 'x', 'default')).toBeNull();
		expect(await fetchSuggestions(async () => { throw new TypeError('offline'); }, 'x', 'default')).toBeNull();
		expect(await fetchSuggestions(async () => { throw abort; }, 'x', 'default')).toBeNull();
		expect(await fetchSuggestions(async () => ({ ok: true, json: async () => ({}) }), 'x', 'default')).toBeNull();
	});

	it('returns an empty answer as an empty list', async () => {
		expect(await fetchSuggestions(fetchReturning([]), 'x', 'default')).toEqual([]);
	});
});

describe('the component', () => {
	beforeEach(() => {
		vi.useFakeTimers();
	});

	afterEach(() => {
		vi.useRealTimers();
	});

	it('waits for the debounce, then opens with the suggestions and announces them', async () => {
		const fetchImpl = fetchReturning(SUGGESTIONS);
		const { component } = makeComponent(fetchImpl);

		component.query = 'card';
		component.onInput();
		await vi.advanceTimersByTimeAsync(DEBOUNCE_MS - 1);
		expect(fetchImpl).not.toHaveBeenCalled();

		await vi.advanceTimersByTimeAsync(1);

		expect(fetchImpl).toHaveBeenCalledTimes(1);
		expect(component.open).toBe(true);
		expect(component.expanded).toBe('true');
		expect(component.options).toHaveLength(3);
		expect(component.status).toMatch(/^2 suggestions available/);
	});

	it('sends one request for a burst of keystrokes', async () => {
		const fetchImpl = fetchReturning(SUGGESTIONS);
		const { component } = makeComponent(fetchImpl);

		for (const query of ['ca', 'car', 'card']) {
			component.query = query;
			component.onInput();
			await vi.advanceTimersByTimeAsync(DEBOUNCE_MS / 2);
		}
		await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

		expect(fetchImpl).toHaveBeenCalledTimes(1);
		expect(fetchImpl.mock.calls[0][0]).toBe('/!/search/suggestions?q=card&index=default');
	});

	it('asks nothing below the minimum length, and closes an open list', async () => {
		const fetchImpl = fetchReturning(SUGGESTIONS);
		const { component } = makeComponent(fetchImpl);

		await typeAndSettle(component, 'card');
		await typeAndSettle(component, 'c');

		expect(fetchImpl).toHaveBeenCalledTimes(1);
		expect(component.open).toBe(false);
		expect(component.results).toEqual([]);
	});

	it('drops an answer for a query the reader has already typed past', async () => {
		let resolveFirst;
		const fetchImpl = vi.fn(() => new Promise((resolve) => { resolveFirst = resolve; }));
		const { component } = makeComponent(fetchImpl);

		component.query = 'card';
		component.onInput();
		await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

		component.query = 'cardiology';
		resolveFirst({ ok: true, json: async () => ({ results: SUGGESTIONS }) });
		await vi.advanceTimersByTimeAsync(0);

		expect(component.open).toBe(false);
		expect(component.results).toEqual([]);
	});

	it('moves the active option with the arrow keys, wrapping at both ends', async () => {
		const { component } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');

		expect(component.activeDescendant).toBe('');

		component.moveDown();
		expect(component.activeIndex).toBe(0);
		expect(component.activeDescendant).toBe('site-search-suggestions-option-0');

		component.moveDown();
		component.moveDown();
		expect(component.activeIndex).toBe(2);

		component.moveDown();
		expect(component.activeIndex).toBe(0);

		component.moveUp();
		expect(component.activeIndex).toBe(2);
	});

	it('follows the active option on Enter, and leaves the form to submit when none is active', async () => {
		const { component, navigate } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');

		const untouched = { preventDefault: vi.fn() };
		component.onEnter(untouched);
		expect(untouched.preventDefault).not.toHaveBeenCalled();
		expect(navigate).not.toHaveBeenCalled();

		component.moveDown();
		component.moveDown();
		const chosen = { preventDefault: vi.fn() };
		component.onEnter(chosen);

		expect(chosen.preventDefault).toHaveBeenCalledTimes(1);
		expect(navigate).toHaveBeenCalledWith('/cardiology');
		expect(component.open).toBe(false);
	});

	it('lands on /search from the "see all results" option', async () => {
		const { component, navigate } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');

		component.moveUp();
		component.onEnter({ preventDefault() {} });

		expect(navigate).toHaveBeenCalledWith('/search?q=card&index=default');
	});

	it('closes on the first Escape and clears the query on the second', async () => {
		const { component } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');

		component.moveDown();
		component.onEscape();
		expect(component.open).toBe(false);
		expect(component.activeIndex).toBe(-1);
		expect(component.query).toBe('card');

		component.onEscape();
		expect(component.query).toBe('');
	});

	it('reopens the last answer with an arrow key after Escape, but not before any answer', async () => {
		const { component } = makeComponent(fetchReturning(SUGGESTIONS));

		component.moveDown();
		expect(component.open).toBe(false);

		await typeAndSettle(component, 'card');
		component.onEscape();
		component.moveDown();

		expect(component.open).toBe(true);
		expect(component.activeIndex).toBe(0);
	});

	it('follows an option chosen with the pointer', async () => {
		const { component, navigate } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');

		component.choose(0);

		expect(navigate).toHaveBeenCalledWith('/people/leonard-mccoy');
	});

	it('still offers "see all results" when there are no suggestions', async () => {
		const { component } = makeComponent(fetchReturning([]));
		await typeAndSettle(component, 'zzz');

		expect(component.open).toBe(true);
		expect(component.options.map((option) => option.url)).toEqual(['/search?q=zzz&index=default']);
		expect(component.status).toBe('No suggestions. Press Enter to search.');
	});

	it('prevents the browser clearing the search field on Escape, and still clears on the second', async () => {
		const { component } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');

		const first = { preventDefault: vi.fn() };
		component.onEscape(first);
		expect(first.preventDefault).toHaveBeenCalledTimes(1);
		expect(component.query).toBe('card');

		const second = { preventDefault: vi.fn() };
		component.onEscape(second);
		expect(second.preventDefault).toHaveBeenCalledTimes(1);
		expect(component.query).toBe('');
	});

	it('does not reopen a list closed while a request was pending', async () => {
		const fetchImpl = fetchReturning(SUGGESTIONS);
		const { component } = makeComponent(fetchImpl);
		await typeAndSettle(component, 'card');

		component.query = 'cardiac';
		component.onInput();
		component.onEscape({ preventDefault() {} });
		await vi.advanceTimersByTimeAsync(DEBOUNCE_MS * 2);

		expect(fetchImpl).toHaveBeenCalledTimes(1);
		expect(component.open).toBe(false);
	});

	it('offers no stale answer to the arrow keys after the query is shortened or changed', async () => {
		const { component, navigate } = makeComponent(fetchReturning(SUGGESTIONS));
		await typeAndSettle(component, 'card');
		component.onEscape({ preventDefault() {} });

		component.query = 'c';
		component.onInput();
		component.moveDown();
		expect(component.open).toBe(false);
		expect(component.answeredQuery).toBe('');

		await typeAndSettle(component, 'card');
		component.query = 'cardio';
		component.onInput();
		component.moveDown();
		component.onEnter({ preventDefault() {} });

		expect(navigate).not.toHaveBeenCalled();
	});

	it('closes when focus leaves the widget, and not when it moves within it', async () => {
		const inside = { id: 'inside' };
		const { component } = makeComponent(fetchReturning(SUGGESTIONS));
		component.$el = { contains: (node) => node === inside };
		await typeAndSettle(component, 'card');

		component.onFocusOut({ relatedTarget: inside });
		expect(component.open).toBe(true);

		component.onFocusOut({ relatedTarget: { id: 'search-button' } });
		expect(component.open).toBe(false);
	});

	it('links to the search page and scope the reader is on', async () => {
		const component = createSiteSearchTypeahead({ fetchImpl: fetchReturning(SUGGESTIONS), navigate: vi.fn(), debounceMs: DEBOUNCE_MS, index: 'people', path: '/health/search', scope: 'all' });
		await typeAndSettle(component, 'card');

		expect(component.options.at(-1).url).toBe('/health/search?q=card&index=people&scope=all');
	});

	it('reads the search page and scope from the element', () => {
		const component = createSiteSearchTypeahead({ fetchImpl: vi.fn(), navigate: vi.fn(), debounceMs: DEBOUNCE_MS });
		component.$el = { dataset: { searchPath: '/health/search', scope: 'all' } };
		component.init();

		expect(component.path).toBe('/health/search');
		expect(component.scope).toBe('all');
	});

	it('asks for and links to the index the page searches', async () => {
		const fetchImpl = fetchReturning(SUGGESTIONS);
		const component = createSiteSearchTypeahead({ fetchImpl, navigate: vi.fn(), debounceMs: DEBOUNCE_MS, index: 'people' });
		await typeAndSettle(component, 'card');

		expect(fetchImpl.mock.calls[0][0]).toBe('/!/search/suggestions?q=card&index=people');
		expect(component.options.at(-1).url).toBe('/search?q=card&index=people');
	});

	it('refuses to navigate to anything but a site-relative or http(s) URL', async () => {
		expect(isNavigableUrl('/cardiology')).toBe(true);
		expect(isNavigableUrl('https://uamshealth.com/x')).toBe(true);
		expect(isNavigableUrl('javascript:alert(1)')).toBe(false);
		expect(isNavigableUrl('//evil.example/x')).toBe(false);

		const { component, navigate } = makeComponent(fetchReturning([{ title: 'Bad', url: 'javascript:alert(1)', type: 'Page' }]));
		await typeAndSettle(component, 'bad');
		component.choose(0);

		expect(navigate).not.toHaveBeenCalled();
		expect(component.open).toBe(false);
	});
});
