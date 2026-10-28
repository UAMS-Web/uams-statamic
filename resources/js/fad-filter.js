/**
 * Find-a-Doc facet filter — the `fadFilter` Alpine.data component (UAMS-Web/uams-statamic-archive#1242).
 *
 * Progressive enhancement for the Find-a-Doc Providers page-builder block
 * (`resources/views/page_builder/_uamsweb_block_fad_providers.antlers.html`), the
 * sibling ontology blocks (#140/UAMS-Web/uams-statamic-archive#61/UAMS-Web/uams-statamic-archive#62/UAMS-Web/uams-statamic-archive#63), and — via the same contract, with the
 * extra directory chrome below — the shipped `/providers` provider directory (UAMS-Web/uams-statamic-archive#570,
 * `resources/views/people/provider/_listing.antlers.html`).
 *
 * The block renders a bounded, editor-curated set of cards, each carrying its facet
 * values as space-delimited term/entry slugs in `data-<facet>` attributes
 * (`data-expertise`, `data-specialty`, `data-region`). Given the facet keys, this
 * component:
 *
 * - binds each `<select>` facet control via `x-model="filters.<facet>"`;
 * - hides every card whose facets don't satisfy the active filters (AND across
 *   facets, set-membership within a facet), toggling `hidden` so a hidden card also
 *   leaves the tab order;
 * - reveals an empty state (`visibleCount === 0`) when nothing matches;
 * - prunes `<option>`s that no card carries, so a zero-match facet is never offered;
 * - announces the visible count through an `aria-live` region; and
 * - mirrors the active facets to the query string (`history.replaceState`) for
 *   shareable / back-button state.
 *
 * The provider **directory** (`fadProviderDirectory`, UAMS-Web/uams-statamic-archive#570) reuses this whole engine
 * over the full Find-a-Doc facet set (`DIRECTORY_FACET_KEYS`) and layers four extra,
 * WP-parity capabilities on top — a name search, an A–Z last-name bar, a name sort,
 * and client-side pagination with a result count. Each is **DOM-gated**: the engine
 * detects the corresponding control (`[data-fad-search]`, `[data-fad-alpha]`,
 * `[data-fad-sort]`, `data-fad-page-size`) in `init()` and is inert when it is
 * absent, so the block and the AoE-section lists (which render none of them) behave
 * exactly as before.
 *
 * "AJAX" is a naming holdover from the WordPress theme's `admin-ajax.php` round-trip
 * — the providers are already in the DOM, so filtering is pure client-side, no
 * request. Without JavaScript the block/directory degrades to the full static list.
 *
 * The pure facet-matching / option-pruning helpers are exported for unit testing.
 */

/** The Providers block's facet keys (also the `fadFilter()` default). */
export const PROVIDER_FACET_KEYS = ['expertise', 'specialty', 'region'];

/**
 * The provider **directory**'s facet keys (UAMS-Web/uams-statamic-archive#570) — WP `archive-physicians.php`
 * parity (which carries no clinical-title/specialty facet, so neither does this).
 * Order matches the rendered filter controls. `patient_types` and `primary_care`
 * keep their underscores, which is why the engine reads facets with
 * `getAttribute('data-<key>')` (literal), never the camel-cased `dataset`.
 */
export const DIRECTORY_FACET_KEYS = [
	'expertise',
	'conditions',
	'treatments',
	'patient_types',
	'gender',
	'language',
	'location',
	'region',
	'primary_care',
];

/**
 * Namespace a facet key for the URL query string, so two filtering blocks on one
 * page don't cross-seed or clobber each other's state (UAMS-Web/uams-statamic-archive#1481).
 *
 * The engine's internal `filters` keys and the cards' `data-<facet>` attributes stay
 * bare (they're scoped to each block's own Alpine `$root`); only the shared URL query
 * string needs a per-instance prefix. An empty namespace keeps bare keys — the
 * single-block default and what a bare `<div x-data="fadFilter">` (no `data-fad-namespace`)
 * produces.
 *
 * @param {string} namespace  The per-instance token (e.g. the block's `module-<id>`), or ''.
 * @param {string} key  The facet key.
 * @returns {string} The query-string key, prefixed when a namespace is set.
 */
export function namespacedKey(namespace, key) {
	return namespace ? `${namespace}_${key}` : key;
}

/**
 * Parse a space-delimited slug attribute into a de-duplicated array of non-empty slugs.
 *
 * @param {string|null|undefined} value  The raw `data-<facet>` attribute value.
 * @returns {string[]} The slugs.
 */
export function parseSlugs(value) {
	return [...new Set(
		(value || '')
			.split(/\s+/)
			.map((slug) => slug.trim())
			.filter(Boolean),
	)];
}

/**
 * Read a card element's facet slug sets, keyed by facet.
 *
 * Uses `getAttribute('data-<facet>')` (not `dataset`) so a facet key with an
 * underscore maps to its literal attribute rather than a camel-cased one.
 *
 * @param {Element} card  A card root carrying `data-<facet>` attributes.
 * @param {string[]} facetKeys  The facet keys to read.
 * @returns {Record<string, string[]>} Slugs per facet key.
 */
export function cardFacets(card, facetKeys) {
	const facets = {};
	facetKeys.forEach((key) => {
		facets[key] = parseSlugs(card.getAttribute(`data-${key}`));
	});

	return facets;
}

/**
 * Whether a card's facet slug sets satisfy every active filter.
 *
 * A filter is active when its selected slug is non-empty; an active filter is
 * satisfied only when the card's slug set for that facet includes the selection.
 * Empty selections ("Any…") don't constrain (AND across facets).
 *
 * @param {Record<string, string[]>} facets  The card's slugs per facet.
 * @param {Record<string, string>} filters  The selected slug per facet.
 * @param {string[]} facetKeys  The facet keys to evaluate.
 * @returns {boolean} True when the card matches.
 */
export function facetsMatch(facets, filters, facetKeys) {
	return facetKeys.every((key) => {
		const selected = filters[key];

		if (!selected) {
			return true;
		}

		return (facets[key] || []).includes(selected);
	});
}

/**
 * Whether a card's searchable name contains the query (case-insensitive substring).
 * An empty query never constrains — the directory name search (UAMS-Web/uams-statamic-archive#570).
 *
 * @param {string|null|undefined} name  The card's `data-name` value.
 * @param {string|null|undefined} query  The typed search string.
 * @returns {boolean} True when the card matches (or the query is empty).
 */
export function matchesSearch(name, query) {
	const needle = (query || '').trim().toLowerCase();

	if (!needle) {
		return true;
	}

	return (name || '').toLowerCase().includes(needle);
}

/**
 * Whether a card's A–Z key starts with the selected letter (case-insensitive). An
 * empty selection never constrains — the directory last-name alpha bar (UAMS-Web/uams-statamic-archive#570).
 *
 * @param {string|null|undefined} alpha  The card's `data-alpha` value (its last-name initial).
 * @param {string|null|undefined} letter  The selected A–Z letter, or ''.
 * @returns {boolean} True when the card matches (or no letter is selected).
 */
export function matchesAlpha(alpha, letter) {
	if (!letter) {
		return true;
	}

	return (alpha || '').toLowerCase().startsWith(letter.toLowerCase());
}

/**
 * Return the cards ordered by their `data-sort-name` key. `name-desc` reverses;
 * any other value (incl. '') sorts ascending. Pure — does not mutate the input or
 * the DOM (the directory name sort, UAMS-Web/uams-statamic-archive#570).
 *
 * @param {Element[]} cards  The cards to order.
 * @param {string} sort  `name-asc` | `name-desc`.
 * @returns {Element[]} A new, ordered array.
 */
export function sortCards(cards, sort) {
	const direction = sort === 'name-desc' ? -1 : 1;

	return [...cards].sort((a, b) => {
		const an = (a.getAttribute('data-sort-name') || '').toLowerCase();
		const bn = (b.getAttribute('data-sort-name') || '').toLowerCase();

		if (an < bn) {
			return -direction;
		}

		if (an > bn) {
			return direction;
		}

		return 0;
	});
}

/**
 * The set of facet slugs present across every card, per facet — the basis for
 * pruning `<option>`s no card carries.
 *
 * @param {Element[]} cards  The card roots.
 * @param {string[]} facetKeys  The facet keys to collect.
 * @returns {Record<string, Set<string>>} Present slugs per facet key.
 */
export function presentSlugs(cards, facetKeys) {
	const present = {};
	facetKeys.forEach((key) => {
		present[key] = new Set();
	});

	cards.forEach((card) => {
		const facets = cardFacets(card, facetKeys);
		facetKeys.forEach((key) => {
			facets[key].forEach((slug) => present[key].add(slug));
		});
	});

	return present;
}

/**
 * Build the `fadFilter` component state object. Shared by both the `fadFilter`
 * (block / AoE) and `fadProviderDirectory` (UAMS-Web/uams-statamic-archive#570) registrations — the directory
 * differs only in its wider facet set and the DOM-gated chrome it renders, all
 * detected from the `$root` at `init()`.
 *
 * @param {string[]} facetKeys  The facet keys this instance filters on.
 * @param {string} itemNoun  Singular noun for the visible-count announcement.
 * @param {string} itemNounPlural  Plural noun for the visible-count announcement.
 * @returns {object} A fresh Alpine component state object.
 */
export function createFadFilter(facetKeys, itemNoun, itemNounPlural) {
	return {
		/** The facet keys this instance filters on. */
		facetKeys,

		/** Singular / plural noun for the visible-count announcement — the Areas of
		 *  Expertise section subpages (UAMS-Web/uams-statamic-archive#1439) reuse this engine over non-provider
		 *  lists (locations, clinical resources). */
		itemNoun,
		itemNounPlural,

		/** Selected slug per facet, bound to the selects via `x-model`. */
		filters: Object.fromEntries(facetKeys.map((key) => [key, ''])),

		/** Directory name search (`data-fad-search`), '' when inactive (UAMS-Web/uams-statamic-archive#570). */
		search: '',

		/** Directory A–Z last-name letter (`data-fad-alpha`), '' for "all" (UAMS-Web/uams-statamic-archive#570). */
		alpha: '',

		/** Directory name sort (`data-fad-sort`): '' | 'name-asc' | 'name-desc' (UAMS-Web/uams-statamic-archive#570). */
		sort: '',

		/** Client-side page size (`data-fad-page-size`); 0 disables pagination (UAMS-Web/uams-statamic-archive#570). */
		pageSize: 0,

		/** Current 1-based page (UAMS-Web/uams-statamic-archive#570). */
		page: 1,

		/** Total pages for the current match set (drives the pager, UAMS-Web/uams-statamic-archive#570). */
		totalPages: 1,

		/** Whether the directory chrome controls are present (DOM-gated, UAMS-Web/uams-statamic-archive#570). */
		hasSearch: false,
		hasAlpha: false,
		hasSort: false,

		/** Number of cards matching the active facets/search/alpha — the full result
		 *  size, independent of the paginated window (drives the count + empty state). */
		visibleCount: 0,

		/** Live-region text announcing the result count. */
		announcement: '',

		/** Per-instance URL query-string namespace, read off the Alpine root's
		 *  `data-fad-namespace` (the block's `module-<id>`); '' → bare keys (UAMS-Web/uams-statamic-archive#1481). */
		namespace: '',

		/** Wire up: read cards, detect chrome, seed from the URL, prune, first apply. */
		init() {
			this.namespace = (this.$root.dataset && this.$root.dataset.fadNamespace) || '';
			this.cards = Array.from(this.$root.querySelectorAll('[data-fad-card]'));

			// Detect the directory chrome. Absent on the block / AoE lists, so every
			// path guarded by these flags is inert there and their behavior is unchanged.
			this.pageSize = Number.parseInt((this.$root.dataset && this.$root.dataset.fadPageSize) || '', 10) || 0;
			this.hasSearch = !! this.$root.querySelector('[data-fad-search]');
			this.hasAlpha = !! this.$root.querySelector('[data-fad-alpha]');
			this.hasSort = !! this.$root.querySelector('[data-fad-sort]');

			// Seed from the URL first, then drop any seeded facet no card carries (a stale
			// or cross-block deep link). Doing this before pruning means the controls never
			// read "Any" over a hidden active filter, and pruning never removes a still-
			// selected option.
			this.readFromQueryString();
			this.dropAbsentFilters();
			this.pruneOptions();
			this.pruneAlpha();
			this.apply();

			// Re-apply whenever any facet selection changes — the reactive contract behind
			// live filtering and the Reset button (covered in fad-filter.test.js). A facet
			// change returns to page 1 so the reader isn't stranded past the new last page.
			this.facetKeys.forEach((key) => {
				this.$watch(`filters.${key}`, () => {
					this.page = 1;
					this.apply();
				});
			});

			// The directory chrome (present only when its controls render). Search / alpha
			// reset to page 1; sort keeps the page.
			if (this.hasSearch) {
				this.$watch('search', () => {
					this.page = 1;
					this.apply();
				});
			}

			if (this.hasAlpha) {
				this.$watch('alpha', () => {
					this.page = 1;
					this.apply();
				});
			}

			if (this.hasSort) {
				this.$watch('sort', () => this.apply());
			}
		},

		/** Hide non-matching / off-page cards, reorder for the sort, update the count +
		 *  announcement, sync the URL. */
		apply() {
			// The full match set: facets AND name search AND alpha (each inert when empty).
			const matched = this.cards.filter((card) => (
				facetsMatch(cardFacets(card, this.facetKeys), this.filters, this.facetKeys)
				&& matchesSearch(card.getAttribute('data-name'), this.search)
				&& matchesAlpha(card.getAttribute('data-alpha'), this.alpha)
			));

			this.visibleCount = matched.length;

			// Order the match set (identity when no sort is active) and page it.
			const orderedMatched = this.sort ? sortCards(matched, this.sort) : matched;
			this.totalPages = this.pageSize > 0
				? Math.max(1, Math.ceil(orderedMatched.length / this.pageSize))
				: 1;

			if (this.page > this.totalPages) {
				this.page = this.totalPages;
			}

			const windowed = this.pageSize > 0
				? orderedMatched.slice((this.page - 1) * this.pageSize, this.page * this.pageSize)
				: orderedMatched;
			const onPage = new Set(windowed);

			// A card is shown iff it's in the current page window; all others are hidden
			// (so hidden cards also leave the tab order). With no chrome active this is
			// exactly `hidden = !facetsMatch`, the block's original behavior.
			this.cards.forEach((card) => {
				card.hidden = ! onPage.has(card);
			});

			// Reflect the sort order in the DOM by reordering the WHOLE card set (hidden ones
			// collapse, so the visible window reads in sorted order). Reordering the full set —
			// not just the match set — means clearing an active sort back to "Sort by" restores
			// the original order (`this.cards`). Gated on hasSort so the block / AoE lists (no
			// sort control) never reorder and are byte-for-byte unchanged.
			if (this.hasSort) {
				this.reorder(this.sort ? sortCards(this.cards, this.sort) : this.cards);
			}

			this.announcement = `${this.visibleCount} ${this.visibleCount === 1 ? this.itemNoun : this.itemNounPlural} shown`;
			this.writeToQueryString();
		},

		/** Reorder the given cards within their shared parent (append in order). Passed the
		 *  full card set so the DOM reflects the sort (or restores original order on clear);
		 *  hidden off-page cards collapse, leaving the visible window reading in order. */
		reorder(ordered) {
			const parent = this.cards.length ? this.cards[0].parentNode : null;

			if (! parent) {
				return;
			}

			ordered.forEach((card) => parent.appendChild(card));
		},

		/** Jump to a page (clamped), re-apply, and scroll the results into view. */
		goToPage(target) {
			const clamped = Math.min(Math.max(1, target), this.totalPages);

			if (clamped === this.page) {
				return;
			}

			this.page = clamped;
			this.apply();
			this.scrollToResults();
		},

		/** Previous / next page helpers for the pager controls. */
		nextPage() {
			this.goToPage(this.page + 1);
		},

		prevPage() {
			this.goToPage(this.page - 1);
		},

		/** The windowed page numbers to render around the current page (±2). */
		get pageWindow() {
			const span = 2;
			const start = Math.max(1, this.page - span);
			const end = Math.min(this.totalPages, this.page + span);
			const numbers = [];

			for (let number = start; number <= end; number += 1) {
				numbers.push(number);
			}

			return numbers;
		},

		/** Bring the results back into view after a pager click (no-op without a window). */
		scrollToResults() {
			if (typeof window === 'undefined' || ! this.$root || typeof this.$root.scrollIntoView !== 'function') {
				return;
			}

			this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
		},

		/** Set (or toggle off) the active A–Z letter — the alpha bar buttons (UAMS-Web/uams-statamic-archive#570). */
		setAlpha(letter) {
			this.alpha = this.alpha === letter ? '' : letter;
		},

		/** Clear every facet selection, the search, and the alpha letter; back to page 1. */
		reset() {
			this.facetKeys.forEach((key) => {
				this.filters[key] = '';
			});
			this.search = '';
			this.alpha = '';
			this.page = 1;
		},

		/** Clear any seeded filter whose slug no rendered card carries. */
		dropAbsentFilters() {
			const present = presentSlugs(this.cards, this.facetKeys);

			this.facetKeys.forEach((key) => {
				if (this.filters[key] && !present[key].has(this.filters[key])) {
					this.filters[key] = '';
				}
			});
		},

		/** Remove each `<option>` (bar the "Any" default) whose slug no card carries. */
		pruneOptions() {
			const present = presentSlugs(this.cards, this.facetKeys);

			this.facetKeys.forEach((key) => {
				const select = this.$root.querySelector(`[data-fad-facet="${key}"]`);

				// Only `<select>` controls have `.options` to prune; a non-select facet
				// control (were one ever added) is left untouched rather than crashing.
				if (!select || !select.options) {
					return;
				}

				Array.from(select.options).forEach((option) => {
					if (option.value && !present[key].has(option.value)) {
						option.remove();
					}
				});
			});
		},

		/** Disable each A–Z button whose letter no card's last name starts with (UAMS-Web/uams-statamic-archive#570). */
		pruneAlpha() {
			if (! this.hasAlpha) {
				return;
			}

			const present = new Set(
				this.cards
					.map((card) => (card.getAttribute('data-alpha') || '').toLowerCase().charAt(0))
					.filter(Boolean),
			);

			this.$root.querySelectorAll('[data-fad-alpha-letter]').forEach((button) => {
				const letter = (button.getAttribute('data-fad-alpha-letter') || '').toLowerCase();

				if (letter && !present.has(letter)) {
					button.disabled = true;
				}
			});
		},

		/** Seed the filters (and directory chrome) from the URL query string. */
		readFromQueryString() {
			if (typeof window === 'undefined') {
				return;
			}

			const params = new URLSearchParams(window.location.search);
			this.facetKeys.forEach((key) => {
				const value = params.get(namespacedKey(this.namespace, key));

				if (value) {
					this.filters[key] = value;
				}
			});

			if (this.hasSearch) {
				this.search = params.get(namespacedKey(this.namespace, 'q')) || '';
			}

			if (this.hasAlpha) {
				this.alpha = params.get(namespacedKey(this.namespace, 'alpha')) || '';
			}

			if (this.hasSort) {
				this.sort = params.get(namespacedKey(this.namespace, 'sort')) || '';
			}

			if (this.pageSize > 0) {
				const page = Number.parseInt(params.get(namespacedKey(this.namespace, 'page')) || '', 10);
				this.page = Number.isFinite(page) && page > 0 ? page : 1;
			}
		},

		/** Reflect the active filters (and directory chrome) in the URL without navigating. */
		writeToQueryString() {
			if (typeof window === 'undefined' || !window.history) {
				return;
			}

			const params = new URLSearchParams(window.location.search);
			this.facetKeys.forEach((key) => {
				this.writeParam(params, key, this.filters[key]);
			});

			// Directory chrome: written only when active / non-default, so the block's
			// query string (no chrome) is byte-for-byte what it was before UAMS-Web/uams-statamic-archive#570.
			this.writeParam(params, 'q', this.hasSearch ? this.search : '');
			this.writeParam(params, 'alpha', this.hasAlpha ? this.alpha : '');
			this.writeParam(params, 'sort', this.hasSort ? this.sort : '');
			this.writeParam(params, 'page', this.pageSize > 0 && this.page > 1 ? String(this.page) : '');

			const query = params.toString();
			const url = query
				? `?${query}${window.location.hash}`
				: `${window.location.pathname}${window.location.hash}`;

			window.history.replaceState(null, '', url);
		},

		/** Set or delete one namespaced query-string param (empty value → delete). */
		writeParam(params, key, value) {
			const qsKey = namespacedKey(this.namespace, key);

			if (value) {
				params.set(qsKey, value);
			} else {
				params.delete(qsKey);
			}
		},
	};
}

/**
 * Register the `fadFilter` (block / AoE) and `fadProviderDirectory` (UAMS-Web/uams-statamic-archive#570) Alpine
 * components. Both share {@link createFadFilter}; the directory pins the wider
 * `DIRECTORY_FACET_KEYS` and the provider noun and lets `init()` detect its chrome.
 *
 * @param {{ data: (name: string, factory: Function) => void }} Alpine  The Alpine instance.
 */
export function registerFadFilter(Alpine) {
	Alpine.data(
		'fadFilter',
		(facetKeys = PROVIDER_FACET_KEYS, itemNoun = 'provider', itemNounPlural = 'providers') => createFadFilter(facetKeys, itemNoun, itemNounPlural),
	);

	Alpine.data(
		'fadProviderDirectory',
		() => createFadFilter(DIRECTORY_FACET_KEYS, 'provider', 'providers'),
	);
}
