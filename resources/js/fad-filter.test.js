// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';

import {
	DIRECTORY_FACET_KEYS,
	PROVIDER_FACET_KEYS,
	cardFacets,
	facetsMatch,
	matchesAlpha,
	matchesSearch,
	namespacedKey,
	parseSlugs,
	presentSlugs,
	registerFadFilter,
	sortCards,
} from './fad-filter.js';

/**
 * Instantiate an Alpine component object (without a real Alpine runtime) by capturing
 * the named factory `registerFadFilter` registers, then invoking it — mirroring the
 * replicator-field test harness. `registerFadFilter` registers two components
 * (`fadFilter` for the block / AoE lists, `fadProviderDirectory` for the UAMS-Web/uams-statamic-archive#570
 * directory), so the capture selects by name.
 *
 * @param {string[]} [facetKeys]  Facet keys to pass the factory (defaults to the providers set).
 * @param {string} [name]  Which registration to build (`fadFilter` by default).
 * @returns {object} A fresh component state object.
 */
function makeComponent(facetKeys, itemNoun, itemNounPlural, name = 'fadFilter') {
	const factories = {};
	registerFadFilter({ data: (factoryName, f) => { factories[factoryName] = f; } });
	const factory = factories[name];

	return facetKeys === undefined ? factory() : factory(facetKeys, itemNoun, itemNounPlural);
}

/**
 * Build a provider block DOM (an `x-data` root holding the facet selects and cards)
 * and mount the component on it: wire `$root`, stub `$watch`, and run `init()`.
 *
 * @param {Array<Record<string, string>>} cards  One object of `data-<facet>` values per card.
 * @param {Record<string, string[]>} [options]  Option slugs to render per facet select.
 * @param {string} [namespace]  Per-instance URL namespace (`data-fad-namespace`); '' → bare keys (UAMS-Web/uams-statamic-archive#1481).
 * @returns {{ component: object, root: HTMLElement }}
 */
function mount(cards, options = {}, namespace = '') {
	const selects = PROVIDER_FACET_KEYS.map((key) => {
		const opts = ['<option value="">Any</option>']
			.concat((options[key] || []).map((slug) => `<option value="${slug}">${slug}</option>`))
			.join('');

		return `<select data-fad-facet="${key}">${opts}</select>`;
	}).join('');

	const cardEls = cards.map((facets) => {
		const attrs = PROVIDER_FACET_KEYS
			.map((key) => `data-${key}="${facets[key] || ''}"`)
			.join(' ');

		return `<div class="card" data-fad-card ${attrs}></div>`;
	}).join('');

	const nsAttr = namespace ? ` data-fad-namespace="${namespace}"` : '';
	document.body.innerHTML = `<div id="root"${nsAttr}>${selects}${cardEls}</div>`;
	const root = document.getElementById('root');

	const component = makeComponent();
	component.$root = root;
	// Record the reactive watchers init() registers, so a test can assert the live-filtering
	// wiring exists and fire one to simulate Alpine reactivity (no manual apply() call).
	component.__watchers = [];
	component.$watch = (expr, cb) => component.__watchers.push({ expr, cb });
	component.init();

	return { component, root };
}

/** The card element list for a mounted root. */
function cardNodes(root) {
	return Array.from(root.querySelectorAll('[data-fad-card]'));
}

/** Simulate Alpine reactivity: run the callback init() registered for a facet key. */
function fireWatcher(component, key) {
	component.__watchers.find((w) => w.expr === `filters.${key}`).cb();
}

beforeEach(() => {
	// Reset the URL between cases so query-string assertions don't bleed.
	window.history.replaceState(null, '', '/');
});

describe('parseSlugs', () => {
	it('splits a space-delimited attribute into de-duplicated non-empty slugs', () => {
		expect(parseSlugs('cardiology  neurology cardiology')).toEqual(['cardiology', 'neurology']);
	});

	it('returns an empty array for empty / missing values', () => {
		expect(parseSlugs('')).toEqual([]);
		expect(parseSlugs(null)).toEqual([]);
		expect(parseSlugs(undefined)).toEqual([]);
		expect(parseSlugs('   ')).toEqual([]);
	});
});

describe('facetsMatch', () => {
	const facets = { expertise: ['cardiology', 'oncology'], specialty: ['surgery'], region: ['central-arkansas'] };

	it('matches when no filter is active (all "Any")', () => {
		expect(facetsMatch(facets, { expertise: '', specialty: '', region: '' }, PROVIDER_FACET_KEYS)).toBe(true);
	});

	it('matches when every active filter is in the card\'s slug set', () => {
		expect(facetsMatch(facets, { expertise: 'cardiology', specialty: '', region: 'central-arkansas' }, PROVIDER_FACET_KEYS)).toBe(true);
	});

	it('rejects when any active filter is absent from the card (AND across facets)', () => {
		expect(facetsMatch(facets, { expertise: 'cardiology', specialty: 'surgery', region: 'northwest-arkansas' }, PROVIDER_FACET_KEYS)).toBe(false);
	});

	it('rejects a facet the card does not carry at all', () => {
		const noRegion = { expertise: ['cardiology'], specialty: [], region: [] };
		expect(facetsMatch(noRegion, { expertise: '', specialty: '', region: 'central-arkansas' }, PROVIDER_FACET_KEYS)).toBe(false);
	});
});

describe('presentSlugs', () => {
	it('collects the distinct slugs present across cards, per facet', () => {
		document.body.innerHTML = `
			<div data-fad-card data-expertise="cardiology oncology" data-specialty="surgery" data-region="central-arkansas"></div>
			<div data-fad-card data-expertise="cardiology" data-specialty="" data-region="northwest-arkansas"></div>`;
		const cards = Array.from(document.querySelectorAll('[data-fad-card]'));

		const present = presentSlugs(cards, PROVIDER_FACET_KEYS);

		expect([...present.expertise].sort()).toEqual(['cardiology', 'oncology']);
		expect([...present.specialty]).toEqual(['surgery']);
		expect([...present.region].sort()).toEqual(['central-arkansas', 'northwest-arkansas']);
	});
});

describe('cardFacets', () => {
	it('reads each facet attribute by its literal name (not camel-cased dataset)', () => {
		document.body.innerHTML = '<div data-fad-card data-expertise="a b" data-specialty="c" data-region=""></div>';
		const card = document.querySelector('[data-fad-card]');

		expect(cardFacets(card, PROVIDER_FACET_KEYS)).toEqual({ expertise: ['a', 'b'], specialty: ['c'], region: [] });
	});
});

describe('fadFilter component', () => {
	it('shows every card and counts them on init with no active filter', () => {
		const { component, root } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		expect(component.visibleCount).toBe(2);
		expect(cardNodes(root).every((card) => card.hidden === false)).toBe(true);
		expect(component.announcement).toBe('2 providers shown');
	});

	it('announces the visible count with a custom singular/plural noun when provided (AoE sections reuse the engine)', () => {
		document.body.innerHTML = '<div id="root">'
			+ '<div data-fad-card data-region="central-arkansas"></div>'
			+ '<div data-fad-card data-region="northwest-arkansas"></div>'
			+ '</div>';

		const component = makeComponent(['region'], 'location', 'locations');
		component.$root = document.getElementById('root');
		component.__watchers = [];
		component.$watch = (expr, cb) => component.__watchers.push({ expr, cb });
		component.init();

		// Both cards visible → the custom PLURAL noun, not the "providers" default.
		expect(component.announcement).toBe('2 locations shown');

		// Narrow to one → the custom SINGULAR noun.
		component.filters.region = 'central-arkansas';
		component.apply();
		expect(component.announcement).toBe('1 location shown');
	});

	it('registers a reactive re-apply watcher for every facet (the live-filtering + reset wiring)', () => {
		// AC #1: selecting a facet narrows the cards with no reload. That only works because
		// init() registers a $watch per facet — assert it exists so deleting the loop turns red.
		const { component } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
		]);

		expect(component.__watchers.map((w) => w.expr)).toEqual([
			'filters.expertise',
			'filters.specialty',
			'filters.region',
		]);
	});

	it('re-applies and narrows the cards when a watched filter changes (via the watcher, not a manual apply)', () => {
		const { component, root } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		// Change the model and fire ONLY the registered watcher — the reactive path.
		component.filters.expertise = 'cardiology';
		fireWatcher(component, 'expertise');

		expect(component.visibleCount).toBe(1);
		expect(cardNodes(root)[1].hidden).toBe(true);
	});

	it('re-shows every card when reset() is followed by the watcher (Reset button wiring)', () => {
		const { component, root } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		component.filters.expertise = 'cardiology';
		fireWatcher(component, 'expertise');
		expect(component.visibleCount).toBe(1);

		// reset() only mutates filters; the watcher is what re-applies (as on a real page).
		component.reset();
		fireWatcher(component, 'expertise');

		expect(component.visibleCount).toBe(2);
		expect(cardNodes(root).every((card) => card.hidden === false)).toBe(true);
	});

	it('drops a seeded facet that no card carries, showing all cards (a stale or cross-block deep link)', () => {
		window.history.replaceState(null, '', '/?specialty=nonexistent-specialty');

		const { component } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		// The absent seed is cleared, so the block shows everything rather than an empty
		// state whose active filter is invisible in the "Any"-reading controls.
		expect(component.filters.specialty).toBe('');
		expect(component.visibleCount).toBe(2);
	});

	it('hides non-matching cards when a facet is selected, updating the count and announcement', () => {
		const { component, root } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		component.filters.expertise = 'cardiology';
		component.apply();

		const cards = cardNodes(root);
		expect(cards[0].hidden).toBe(false);
		expect(cards[1].hidden).toBe(true);
		expect(component.visibleCount).toBe(1);
		expect(component.announcement).toBe('1 provider shown');
	});

	it('reveals the empty state (visibleCount 0) when a combination matches nothing', () => {
		const { component } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
		]);

		component.filters.region = 'northwest-arkansas';
		component.apply();

		expect(component.visibleCount).toBe(0);
	});

	it('reset() clears every filter and shows all cards again', () => {
		const { component, root } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		component.filters.expertise = 'cardiology';
		component.apply();
		expect(component.visibleCount).toBe(1);

		component.reset();
		component.apply();

		expect(component.filters).toEqual({ expertise: '', specialty: '', region: '' });
		expect(component.visibleCount).toBe(2);
		expect(cardNodes(root).every((card) => card.hidden === false)).toBe(true);
	});

	it('prunes options no card carries, keeping the "Any" default and present options', () => {
		const { root } = mount(
			[
				{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			],
			{ expertise: ['cardiology', 'oncology', 'dermatology'] },
		);

		const expertiseSelect = root.querySelector('[data-fad-facet="expertise"]');
		const values = Array.from(expertiseSelect.options).map((option) => option.value);

		// The "Any" default (empty value) and the only present slug survive; the
		// two slugs no card carries are pruned.
		expect(values).toEqual(['', 'cardiology']);
	});

	it('seeds the filters from the query string on init', () => {
		window.history.replaceState(null, '', '/?expertise=oncology');

		const { component, root } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
			{ expertise: 'oncology', specialty: 'surgery', region: 'northwest-arkansas' },
		]);

		expect(component.filters.expertise).toBe('oncology');
		expect(component.visibleCount).toBe(1);
		expect(cardNodes(root)[1].hidden).toBe(false);
	});

	it('mirrors active filters to the query string and clears them when reset', () => {
		const { component } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
		]);

		component.filters.specialty = 'surgery';
		component.apply();
		expect(new URLSearchParams(window.location.search).get('specialty')).toBe('surgery');

		component.reset();
		component.apply();
		expect(window.location.search).toBe('');
	});

	it('preserves unrelated query-string params when syncing', () => {
		window.history.replaceState(null, '', '/?utm_source=news');

		const { component } = mount([
			{ expertise: 'cardiology', specialty: 'surgery', region: 'central-arkansas' },
		]);

		component.filters.expertise = 'cardiology';
		component.apply();

		const params = new URLSearchParams(window.location.search);
		expect(params.get('utm_source')).toBe('news');
		expect(params.get('expertise')).toBe('cardiology');
	});
});

describe('namespacedKey (#1481)', () => {
	it('prefixes the key when a namespace is set', () => {
		expect(namespacedKey('module-x', 'expertise')).toBe('module-x_expertise');
	});

	it('keeps the bare key when the namespace is empty (single-block default)', () => {
		expect(namespacedKey('', 'expertise')).toBe('expertise');
	});
});

describe('per-instance query namespace (#1481)', () => {
	it('seeds from its own namespaced key and ignores the bare / other-block key', () => {
		// Its own key + a bare key + another block's key all present at once.
		window.history.replaceState(null, '', '/?module-a_expertise=cardiology&expertise=oncology&module-b_expertise=oncology');

		const { component } = mount(
			[{ expertise: 'cardiology' }, { expertise: 'oncology' }],
			{ expertise: ['cardiology', 'oncology'] },
			'module-a',
		);

		// Reads module-a_expertise (cardiology), not the bare or module-b key.
		expect(component.filters.expertise).toBe('cardiology');
	});

	it('writes and clears the facet under its namespaced key only', () => {
		const { component } = mount(
			[{ expertise: 'cardiology' }],
			{ expertise: ['cardiology'] },
			'module-a',
		);

		component.filters.expertise = 'cardiology';
		component.apply();

		let params = new URLSearchParams(window.location.search);
		expect(params.get('module-a_expertise')).toBe('cardiology');
		// The bare key is never written, so a sibling block's bare state is untouched.
		expect(params.get('expertise')).toBeNull();

		component.reset();
		component.apply();

		params = new URLSearchParams(window.location.search);
		expect(params.get('module-a_expertise')).toBeNull();
	});

	it('two namespaced instances do not cross-seed from a shared URL', () => {
		window.history.replaceState(null, '', '/?module-a_expertise=cardiology&module-b_expertise=oncology');

		const { component: a } = mount([{ expertise: 'cardiology' }], { expertise: ['cardiology'] }, 'module-a');
		// mount() resets document.body but not the URL, so both read the same query string.
		const { component: b } = mount([{ expertise: 'oncology' }], { expertise: ['oncology'] }, 'module-b');

		expect(a.filters.expertise).toBe('cardiology');
		expect(b.filters.expertise).toBe('oncology');
	});
});

// ---------------------------------------------------------------------------
// Provider directory (UAMS-Web/uams-statamic-archive#570): the DOM-gated name-search, A–Z, sort, and
// pagination chrome layered on the same engine over DIRECTORY_FACET_KEYS.
// ---------------------------------------------------------------------------

/** Fire the watcher init() registered for a bare (non-facet) expression. */
function fireExprWatcher(component, expr) {
	component.__watchers.find((w) => w.expr === expr).cb();
}

/**
 * Mount the `fadProviderDirectory` component on a DOM carrying the directory chrome
 * (name search, A–Z bar, sort select, page-size) so `init()`'s feature detection
 * turns each capability on.
 *
 * @param {Array<Record<string, string>>} cards  One object of `data-*` values per card
 *   (`name`, `alpha`, `sortName`, plus any DIRECTORY_FACET_KEYS slug attribute).
 * @param {{ pageSize?: number, alphabet?: string[], sort?: boolean }} [chrome]  Which chrome to render.
 * @returns {{ component: object, root: HTMLElement }}
 */
function mountDirectory(cards, chrome = {}) {
	const { pageSize = 0, alphabet = [], sort = true } = chrome;

	const search = '<input type="search" data-fad-search>';
	const alphaBar = alphabet.length
		? `<div data-fad-alpha>${alphabet.map((letter) => `<button type="button" data-fad-alpha-letter="${letter}">${letter}</button>`).join('')}</div>`
		: '';
	const sortSelect = sort
		? '<select data-fad-sort><option value="">Sort</option><option value="name-asc">A-Z</option><option value="name-desc">Z-A</option></select>'
		: '';

	const cardEls = cards.map((card) => {
		const dataAttrs = Object.entries(card)
			.map(([key, value]) => {
				const attr = key === 'sortName' ? 'sort-name' : key;

				return `data-${attr}="${value}"`;
			})
			.join(' ');

		return `<div class="card" data-fad-card ${dataAttrs}></div>`;
	}).join('');

	const pageAttr = pageSize ? ` data-fad-page-size="${pageSize}"` : '';
	document.body.innerHTML = `<div id="root"${pageAttr}>${search}${alphaBar}${sortSelect}<div class="card-list">${cardEls}</div></div>`;
	const root = document.getElementById('root');

	const component = makeComponent(undefined, undefined, undefined, 'fadProviderDirectory');
	component.$root = root;
	component.__watchers = [];
	component.$watch = (expr, cb) => component.__watchers.push({ expr, cb });
	component.init();

	return { component, root };
}

/** The card element list for a mounted root, in DOM order. */
function directoryCards(root) {
	return Array.from(root.querySelectorAll('[data-fad-card]'));
}

describe('directory pure helpers (#570)', () => {
	it('matchesSearch is a case-insensitive substring, empty query never constrains', () => {
		expect(matchesSearch('Leonard McCoy', 'mcc')).toBe(true);
		expect(matchesSearch('Leonard McCoy', 'KIRK')).toBe(false);
		expect(matchesSearch('Leonard McCoy', '')).toBe(true);
		expect(matchesSearch('Leonard McCoy', '   ')).toBe(true);
		expect(matchesSearch(null, 'anything')).toBe(false);
	});

	it('matchesAlpha matches the leading letter, empty selection never constrains', () => {
		expect(matchesAlpha('M', 'm')).toBe(true);
		expect(matchesAlpha('M', 'K')).toBe(false);
		expect(matchesAlpha('M', '')).toBe(true);
		expect(matchesAlpha('', 'M')).toBe(false);
		expect(matchesAlpha(null, 'M')).toBe(false);
	});

	it('sortCards orders by data-sort-name ascending and descending without mutating input', () => {
		document.body.innerHTML = ''
			+ '<div id="a" data-sort-name="mccoy leonard"></div>'
			+ '<div id="b" data-sort-name="chekov pavel"></div>'
			+ '<div id="c" data-sort-name="sulu hikaru"></div>';
		const nodes = ['a', 'b', 'c'].map((id) => document.getElementById(id));

		expect(sortCards(nodes, 'name-asc').map((n) => n.id)).toEqual(['b', 'a', 'c']);
		expect(sortCards(nodes, 'name-desc').map((n) => n.id)).toEqual(['c', 'a', 'b']);
		// Pure: the source array order is untouched.
		expect(nodes.map((n) => n.id)).toEqual(['a', 'b', 'c']);
	});
});

describe('fadProviderDirectory component (#570)', () => {
	it('filters over the full WP-parity facet set (conditions, gender, primary_care, …)', () => {
		const { component } = mountDirectory([
			{ name: 'a', conditions: 'asthma', gender: 'female', primary_care: 'yes' },
			{ name: 'b', conditions: 'diabetes', gender: 'male', primary_care: '' },
		]);

		expect(component.facetKeys).toEqual(DIRECTORY_FACET_KEYS);

		component.filters.gender = 'female';
		component.apply();
		expect(component.visibleCount).toBe(1);

		component.reset();
		component.filters.primary_care = 'yes';
		component.apply();
		expect(component.visibleCount).toBe(1);
	});

	it('narrows by name search (substring), announcing the matched count', () => {
		const { component, root } = mountDirectory([
			{ name: 'leonard mccoy', alpha: 'M' },
			{ name: 'pavel chekov', alpha: 'C' },
		]);

		component.search = 'mcc';
		fireExprWatcher(component, 'search');

		expect(component.visibleCount).toBe(1);
		expect(directoryCards(root)[0].hidden).toBe(false);
		expect(directoryCards(root)[1].hidden).toBe(true);
		expect(component.announcement).toBe('1 provider shown');
	});

	it('narrows by the A–Z last-name letter and toggles it off on a second select', () => {
		const { component } = mountDirectory(
			[
				{ name: 'leonard mccoy', alpha: 'M' },
				{ name: 'pavel chekov', alpha: 'C' },
			],
			{ alphabet: ['C', 'M'] },
		);

		component.setAlpha('M');
		fireExprWatcher(component, 'alpha');
		expect(component.visibleCount).toBe(1);

		// Selecting the active letter again clears it (back to all).
		component.setAlpha('M');
		fireExprWatcher(component, 'alpha');
		expect(component.visibleCount).toBe(2);
	});

	it('disables an A–Z letter no rendered provider carries (pruneAlpha)', () => {
		const { root } = mountDirectory(
			[{ name: 'leonard mccoy', alpha: 'M' }],
			{ alphabet: ['C', 'M', 'Z'] },
		);

		const buttons = Object.fromEntries(
			Array.from(root.querySelectorAll('[data-fad-alpha-letter]'))
				.map((b) => [b.getAttribute('data-fad-alpha-letter'), b]),
		);

		expect(buttons.M.disabled).toBe(false);
		expect(buttons.C.disabled).toBe(true);
		expect(buttons.Z.disabled).toBe(true);
	});

	it('reorders the cards in the DOM when a name sort is chosen', () => {
		const { component, root } = mountDirectory([
			{ name: 'sulu hikaru', sortName: 'sulu hikaru' },
			{ name: 'chekov pavel', sortName: 'chekov pavel' },
			{ name: 'mccoy leonard', sortName: 'mccoy leonard' },
		]);

		component.sort = 'name-asc';
		fireExprWatcher(component, 'sort');
		expect(directoryCards(root).map((c) => c.getAttribute('data-name'))).toEqual([
			'chekov pavel', 'mccoy leonard', 'sulu hikaru',
		]);

		component.sort = 'name-desc';
		fireExprWatcher(component, 'sort');
		expect(directoryCards(root).map((c) => c.getAttribute('data-name'))).toEqual([
			'sulu hikaru', 'mccoy leonard', 'chekov pavel',
		]);
	});

	it('paginates: the count is the full match set, but only a page-size window is shown', () => {
		const { component, root } = mountDirectory(
			Array.from({ length: 5 }, (_unused, index) => ({ name: `p${index}`, sortName: `p${index}` })),
			{ pageSize: 2 },
		);

		// 5 providers, 2 per page → count is 5, three pages, page 1 shows 2.
		expect(component.visibleCount).toBe(5);
		expect(component.totalPages).toBe(3);
		expect(directoryCards(root).filter((c) => c.hidden === false)).toHaveLength(2);

		component.goToPage(3);
		// Last page shows the remaining 1.
		expect(component.page).toBe(3);
		expect(directoryCards(root).filter((c) => c.hidden === false)).toHaveLength(1);
	});

	it('re-clamps the page and resets to 1 when a filter shrinks the result set', () => {
		const { component } = mountDirectory(
			[
				{ name: 'a', gender: 'female' },
				{ name: 'b', gender: 'female' },
				{ name: 'c', gender: 'male' },
				{ name: 'd', gender: 'male' },
			],
			{ pageSize: 2 },
		);

		component.goToPage(2);
		expect(component.page).toBe(2);

		// A facet change routes through the watcher, which resets to page 1.
		component.filters.gender = 'female';
		fireExprWatcher(component, 'filters.gender');
		expect(component.page).toBe(1);
		expect(component.visibleCount).toBe(2);
		expect(component.totalPages).toBe(1);
	});

	it('reset() clears the facets, search, and alpha and returns to page 1', () => {
		const { component } = mountDirectory(
			[
				{ name: 'leonard mccoy', alpha: 'M', gender: 'female' },
				{ name: 'pavel chekov', alpha: 'C', gender: 'male' },
			],
			{ pageSize: 1 },
		);

		component.filters.gender = 'female';
		component.search = 'mcc';
		component.setAlpha('M');
		component.goToPage(1);
		component.apply();

		component.reset();
		component.apply();

		expect(component.filters.gender).toBe('');
		expect(component.search).toBe('');
		expect(component.alpha).toBe('');
		expect(component.page).toBe(1);
		expect(component.visibleCount).toBe(2);
	});

	it('seeds search / alpha / sort / page from the query string and writes them back', () => {
		window.history.replaceState(null, '', '/?q=mcc&alpha=M&sort=name-desc&page=1');

		const { component } = mountDirectory(
			[
				{ name: 'leonard mccoy', alpha: 'M', sortName: 'mccoy leonard' },
				{ name: 'malcolm reed', alpha: 'M', sortName: 'reed malcolm' },
			],
			{ pageSize: 1, alphabet: ['M'] },
		);

		expect(component.search).toBe('mcc');
		expect(component.alpha).toBe('M');
		expect(component.sort).toBe('name-desc');

		component.search = '';
		fireExprWatcher(component, 'search');
		const params = new URLSearchParams(window.location.search);
		// Cleared search drops `q`; alpha + sort persist.
		expect(params.get('q')).toBeNull();
		expect(params.get('alpha')).toBe('M');
		expect(params.get('sort')).toBe('name-desc');
	});

	it('combines facet, search, and alpha with AND; an impossible combination is empty', () => {
		const { component } = mountDirectory([
			{ name: 'leonard mccoy', alpha: 'M', gender: 'male' },
			{ name: 'marla mccoy', alpha: 'M', gender: 'female' },
		]);

		component.filters.gender = 'female';
		component.search = 'mccoy';
		component.setAlpha('M');
		component.apply();
		expect(component.visibleCount).toBe(1);

		// No male "chekov" starting with M → empty state (visibleCount 0).
		component.filters.gender = 'male';
		component.search = 'chekov';
		component.apply();
		expect(component.visibleCount).toBe(0);
	});

	it('clamps a deep-linked page past the last page to the last page (not an empty grid)', () => {
		// Regression guard for the apply() re-clamp: a stale ?page=5 deep link on a 3-page
		// result must land on page 3 showing its card, never an empty grid under a full count.
		window.history.replaceState(null, '', '/?page=5');

		const { component, root } = mountDirectory(
			Array.from({ length: 5 }, (_unused, index) => ({ name: `p${index}`, sortName: `p${index}` })),
			{ pageSize: 2 },
		);

		expect(component.totalPages).toBe(3);
		expect(component.page).toBe(3);
		// The last page shows its 1 card; the count is still the full match set.
		expect(directoryCards(root).filter((c) => c.hidden === false)).toHaveLength(1);
		expect(component.visibleCount).toBe(5);
	});

	it('restores the original card order when an active sort is cleared back to the default', () => {
		const { component, root } = mountDirectory([
			{ name: 'p0', sortName: 'charlie' },
			{ name: 'p1', sortName: 'alpha' },
			{ name: 'p2', sortName: 'bravo' },
		]);

		// Sort Z-A: charlie, bravo, alpha → p0, p2, p1.
		component.sort = 'name-desc';
		fireExprWatcher(component, 'sort');
		expect(directoryCards(root).map((c) => c.getAttribute('data-name'))).toEqual(['p0', 'p2', 'p1']);

		// Clear back to "Sort by": the DOM must return to the original order, not stay sorted.
		component.sort = '';
		fireExprWatcher(component, 'sort');
		expect(directoryCards(root).map((c) => c.getAttribute('data-name'))).toEqual(['p0', 'p1', 'p2']);
	});
});

describe('AoE section filter instance (#1490)', () => {
	// The Areas of Expertise section subpages reuse the same `createFadFilter` engine, but
	// with a wider optional facet set (patient types / gender / languages / location type) and
	// a free-text search box gated on `[data-fad-search]`. Prove the AoE-shaped instance narrows
	// by both a new facet and the search box (the block/directory tests cover the shared helpers).
	const AOE_PROVIDER_KEYS = ['specialty', 'region', 'expertise', 'patient_types', 'gender', 'language'];

	/** Mount an AoE provider-section fadFilter carrying a search box + a `patient_types` facet. */
	function mountAoeProviders(cards) {
		const search = '<input type="search" data-fad-search>';
		const patientTypes = '<select data-fad-facet="patient_types"><option value="">Any</option>'
			+ '<option value="children">children</option><option value="adults">adults</option></select>';
		const cardEls = cards.map((card) => {
			const attrs = Object.entries(card).map(([key, value]) => `data-${key}="${value}"`).join(' ');

			return `<div class="card" data-fad-card ${attrs}></div>`;
		}).join('');

		document.body.innerHTML = `<div id="root">${search}${patientTypes}${cardEls}</div>`;
		const root = document.getElementById('root');

		const component = makeComponent(AOE_PROVIDER_KEYS, 'provider', 'providers');
		component.$root = root;
		component.__watchers = [];
		component.$watch = (expr, cb) => component.__watchers.push({ expr, cb });
		component.init();

		return { component, root };
	}

	it('narrows by free-text search over data-name (the AoE section search box)', () => {
		const { component, root } = mountAoeProviders([
			{ name: 'leonard mccoy', patient_types: 'children' },
			{ name: 'christine chapel', patient_types: 'adults' },
		]);
		const cards = Array.from(root.querySelectorAll('[data-fad-card]'));

		expect(cards.every((card) => !card.hidden)).toBe(true);

		component.search = 'mccoy';
		fireExprWatcher(component, 'search');

		expect(component.visibleCount).toBe(1);
		expect(cards[0].hidden).toBe(false);
		expect(cards[1].hidden).toBe(true);
	});

	it('narrows by an optional facet (patient_types) added for AoE providers', () => {
		const { component, root } = mountAoeProviders([
			{ name: 'leonard mccoy', patient_types: 'children' },
			{ name: 'christine chapel', patient_types: 'adults' },
		]);
		const cards = Array.from(root.querySelectorAll('[data-fad-card]'));

		component.filters.patient_types = 'children';
		fireWatcher(component, 'patient_types');

		expect(component.visibleCount).toBe(1);
		expect(cards[0].hidden).toBe(false);
		expect(cards[1].hidden).toBe(true);
	});
});
