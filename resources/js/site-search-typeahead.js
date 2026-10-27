/**
 * Site-search type-ahead (UAMS-Web/uams-statamic-archive#1506).
 *
 * Progressive enhancement for the `/search` input (`resources/views/search/index.antlers.html`).
 * As the reader types, it asks `/!/search/suggestions` for a short list from the index the
 * page's active type tab searches (the unified `default` index on "All results"), and shows
 * it in a listbox under the input. It never replaces the page: the last option is always
 * "See all results", and submitting the form without choosing an option is an ordinary GET
 * to `/search`, exactly as without JavaScript.
 *
 * Accessibility follows the ARIA 1.2 combobox pattern with a listbox popup:
 * - the input carries `role="combobox"`, `aria-expanded`, `aria-controls` and the
 *   ARIA active-descendant reference, and keeps focus throughout;
 * - ArrowDown / ArrowUp move the active option, wrapping at either end, and scroll it into view;
 * - Enter follows the active option, or submits the form when none is active;
 * - Escape closes the list, and a second Escape clears the query. The browser's own Escape
 *   handling on a search field, which clears it at once, is prevented;
 * - the list closes when focus leaves the widget;
 * - a polite live region announces how many suggestions there are.
 *
 * Written for `@alpinejs/csp`: the markup only references properties, getters and
 * methods by name, never an inline expression the CSP build would refuse.
 *
 * The factory is exported so resources/js/site-search-typeahead.test.js can drive it
 * without a browser; `fetchImpl`, `navigate`, `debounceMs` and `index` are injectable for that.
 */

/** The fewest characters worth asking for, matching SiteSearchSuggestionsController. */
export const MIN_QUERY_LENGTH = 2;

/** The delay after the last keystroke before a request is sent. */
export const DEBOUNCE_MS = 200;

/** The suggestions endpoint. */
export const ENDPOINT = '/!/search/suggestions';

/** The listbox's element id, which each option id is derived from. */
export const LISTBOX_ID = 'site-search-suggestions';

/**
 * The `/search` URL for a query on an index, as the form would submit it.
 *
 * @param {string} query  The query.
 * @param {string} [index]  The index handle; `default` is "All results".
 * @returns {string} The results page URL.
 */
export function allResultsUrl(query, index = 'default') {
	return `/search?q=${encodeURIComponent(query)}&index=${encodeURIComponent(index)}`;
}

/**
 * Whether a URL is one the component may navigate to: site-relative, or http(s).
 * Anything else, such as a `javascript:` redirect an editor set, is refused.
 *
 * @param {string} url  The URL.
 * @returns {boolean} Whether it is safe to follow.
 */
export function isNavigableUrl(url) {
	return typeof url === 'string' && /^(?:\/(?!\/)|https?:\/\/)/i.test(url);
}

/**
 * The options the listbox renders: each suggestion, then "See all results".
 *
 * @param {Array<{title: string, url: string, type: string}>} results  The suggestions.
 * @param {string} query  The query they answer.
 * @param {number} activeIndex  The active option, or -1 for none.
 * @param {string} [index]  The index they came from, carried into "See all results".
 * @returns {Array<{key: string, id: string, title: string, type: string, url: string, selected: string, isAll: boolean, className: string}>}
 *   The options, with `selected` as the string ARIA expects and the classes the active
 *   option and the all-results option carry, since the CSP build cannot evaluate a
 *   class object in the markup.
 */
export function buildOptions(results, query, activeIndex, index = 'default') {
	const options = results.map((result, position) => ({
		key: `result-${position}-${result.url}`,
		id: `${LISTBOX_ID}-option-${position}`,
		title: result.title,
		type: result.type,
		url: result.url,
		isAll: false,
	}));

	options.push({
		key: 'all-results',
		id: `${LISTBOX_ID}-option-all`,
		title: `See all results for “${query}”`,
		type: '',
		url: allResultsUrl(query, index),
		isAll: true,
	});

	return options.map((option, position) => {
		const active = position === activeIndex;
		const classes = [active ? 'active' : '', option.isAll ? 'site-search-suggestion-all' : ''].filter(Boolean);

		return { ...option, selected: active ? 'true' : 'false', className: classes.join(' ') };
	});
}

/**
 * The live-region sentence for a set of suggestions.
 *
 * @param {number} count  How many suggestions arrived.
 * @returns {string} The announcement.
 */
export function statusText(count) {
	if (count === 0) {
		return 'No suggestions. Press Enter to search.';
	}

	return `${count} ${count === 1 ? 'suggestion' : 'suggestions'} available. Use the up and down arrows to choose one.`;
}

/**
 * Fetch suggestions for a query.
 *
 * Resolves null, not an empty list, for anything that is not an answer: an aborted or
 * failed request, an HTTP error such as a 429 from the rate limiter, or a malformed
 * body. The caller then leaves the list as it was rather than claiming there are no
 * suggestions.
 *
 * @param {Function} fetchImpl  `fetch`, or a stand-in.
 * @param {string} query  The query.
 * @param {string} index  The index handle.
 * @param {AbortSignal} [signal]  Cancels a request a newer keystroke superseded.
 * @returns {Promise<Array<{title: string, url: string, type: string}>|null>} The suggestions, or null.
 */
export async function fetchSuggestions(fetchImpl, query, index, signal) {
	try {
		const response = await fetchImpl(
			`${ENDPOINT}?q=${encodeURIComponent(query)}&index=${encodeURIComponent(index)}`,
			{
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
				credentials: 'same-origin',
				signal,
			},
		);

		if (!response.ok) {
			return null;
		}

		const body = await response.json();

		return Array.isArray(body?.results) ? body.results : null;
	} catch {
		return null;
	}
}

/**
 * The Alpine component.
 *
 * @param {object} [deps]  Injectable collaborators, for tests.
 * @param {Function} [deps.fetchImpl]  Defaults to `window.fetch`.
 * @param {Function} [deps.navigate]  Defaults to `window.location.assign`.
 * @param {number} [deps.debounceMs]  Defaults to {@link DEBOUNCE_MS}.
 * @param {string} [deps.index]  The index handle; otherwise read from the element's `data-index`.
 * @returns {object} The component definition.
 */
export function createSiteSearchTypeahead(deps = {}) {
	const fetchImpl = deps.fetchImpl ?? ((...args) => window.fetch(...args));
	const navigate = deps.navigate ?? ((url) => window.location.assign(url));
	const debounceMs = deps.debounceMs ?? DEBOUNCE_MS;

	let timer = null;
	let controller = null;

	/** Cancel a scheduled request and abort one in flight. */
	function cancelPending() {
		clearTimeout(timer);
		timer = null;
		controller?.abort();
		controller = null;
	}

	return {
		query: '',
		index: deps.index ?? 'default',
		results: [],
		answeredQuery: '',
		open: false,
		activeIndex: -1,
		status: '',
		listboxId: LISTBOX_ID,

		/** Seed the query from the input's server-rendered value, and the index from `data-index`. */
		init() {
			const input = this.$refs?.input;
			if (input && typeof input.value === 'string') {
				this.query = input.value;
			}

			const index = this.$el?.dataset?.index;
			if (deps.index === undefined && typeof index === 'string' && index !== '') {
				this.index = index;
			}
		},

		/** @returns {Array<object>} The rendered options. */
		get options() {
			return buildOptions(this.results, this.answeredQuery, this.activeIndex, this.index);
		},

		/** @returns {string} `aria-expanded` for the input. */
		get expanded() {
			return this.open ? 'true' : 'false';
		},

		/** @returns {string} The input's ARIA active-descendant id, or '' for none. */
		get activeDescendant() {
			const option = this.open ? this.options[this.activeIndex] : undefined;

			return option ? option.id : '';
		},

		/** Schedule a request for the current query, cancelling any pending one. */
		onInput() {
			cancelPending();

			const query = this.query.trim();

			if (query.length < MIN_QUERY_LENGTH) {
				this.close();
				this.results = [];
				this.answeredQuery = '';
				this.status = '';

				return;
			}

			timer = setTimeout(() => this.load(query), debounceMs);
		},

		/**
		 * Request suggestions for a query and show them, unless a newer request replaced it.
		 *
		 * @param {string} query  The trimmed query.
		 * @returns {Promise<void>}
		 */
		async load(query) {
			timer = null;
			controller?.abort();
			controller = typeof AbortController === 'function' ? new AbortController() : null;

			const results = await fetchSuggestions(fetchImpl, query, this.index, controller?.signal);

			// Aborted, failed, or the reader has typed on since; nothing to show for this query
			if (results === null || this.query.trim() !== query) {
				return;
			}

			controller = null;
			this.results = results;
			this.answeredQuery = query;
			this.activeIndex = -1;
			this.open = true;
			this.status = statusText(results.length);
		},

		/**
		 * Whether the shown answer is for the query now in the field. After typing on,
		 * or shortening the query, it is not, and the arrows must not offer it.
		 *
		 * @returns {boolean} Whether the answer is current.
		 */
		hasCurrentAnswer() {
			return this.answeredQuery !== '' && this.query.trim() === this.answeredQuery;
		},

		/** Move the active option down, opening the list if it has a current answer. */
		moveDown() {
			if (!this.hasCurrentAnswer()) {
				return;
			}

			this.open = true;
			this.activeIndex = (this.activeIndex + 1) % this.options.length;
			this.scrollActiveIntoView();
		},

		/** Move the active option up, wrapping to the last. */
		moveUp() {
			if (!this.hasCurrentAnswer()) {
				return;
			}

			this.open = true;
			const count = this.options.length;
			this.activeIndex = this.activeIndex <= 0 ? count - 1 : this.activeIndex - 1;
			this.scrollActiveIntoView();
		},

		/** Keep the active option visible in a list taller than its container. */
		scrollActiveIntoView() {
			const id = this.activeDescendant;

			if (id === '' || typeof document === 'undefined') {
				return;
			}

			const scroll = () => document.getElementById(id)?.scrollIntoView?.({ block: 'nearest' });

			if (typeof this.$nextTick === 'function') {
				this.$nextTick(scroll);
			} else {
				scroll();
			}
		},

		/**
		 * Follow the active option, or let the form submit when none is active.
		 *
		 * @param {KeyboardEvent} event  The Enter keydown.
		 */
		onEnter(event) {
			const option = this.open ? this.options[this.activeIndex] : undefined;

			if (!option) {
				return;
			}

			event?.preventDefault?.();
			this.follow(option.url);
		},

		/**
		 * Close the list; with the list already closed, clear the query. The browser's own
		 * Escape on a search field, which clears it immediately, is always prevented, so
		 * the first Escape only closes.
		 *
		 * @param {KeyboardEvent} [event]  The Escape keydown.
		 */
		onEscape(event) {
			event?.preventDefault?.();

			if (this.open) {
				this.close();

				return;
			}

			cancelPending();
			this.query = '';
			this.results = [];
			this.answeredQuery = '';
			this.status = '';
		},

		/**
		 * Close the list when focus leaves the widget, as with Tab to the Search button.
		 *
		 * @param {FocusEvent} event  The focusout event.
		 */
		onFocusOut(event) {
			const next = event?.relatedTarget;

			if (next && this.$el?.contains?.(next)) {
				return;
			}

			this.close();
		},

		/**
		 * Follow an option chosen with the pointer.
		 *
		 * @param {number} position  The option's position.
		 */
		choose(position) {
			const option = this.options[position];

			if (option) {
				this.follow(option.url);
			}
		},

		/**
		 * Close the list and go to a URL, refusing anything but a site-relative or http(s) one.
		 *
		 * @param {string} url  The option's URL.
		 */
		follow(url) {
			this.close();

			if (isNavigableUrl(url)) {
				navigate(url);
			}
		},

		/** Close the list, and cancel any request that would reopen it. */
		close() {
			cancelPending();
			this.open = false;
			this.activeIndex = -1;
		},
	};
}

/**
 * Register the component with Alpine as `siteSearchTypeahead`.
 *
 * @param {object} Alpine  The Alpine instance.
 */
export function registerSiteSearchTypeahead(Alpine) {
	Alpine.data('siteSearchTypeahead', () => createSiteSearchTypeahead());
}
