/**
 * Knowledge base article engagement — view counting (UAMS-Web/uams-statamic-archive#287) and like / dislike
 * ratings (UAMS-Web/uams-statamic-archive#286).
 *
 * Progressive enhancement for the knowledge base article page
 * (`resources/views/knowledge_bases/default.antlers.html`). On load it fires one
 * view beacon for the article, and it wires the "Was This Article Helpful?" like /
 * dislike triggers rendered by `_interaction_statistic.antlers.html`. Both talk to
 * the app's engagement endpoints (`/!/kb/articles/{id}/view`, `.../react`) rather
 * than counting in the render, so the tallies survive page static-caching.
 *
 * Every response is a uniform snapshot — `{ views, likes, dislikes, net, reaction }`
 * — so a single `applySnapshot` updates the visible counts and the pressed state of
 * the two triggers. Firing the view beacon on load therefore also hydrates the
 * visitor's current vote state (the server never embeds it in the cacheable page).
 *
 * Without JavaScript the page degrades to the server-rendered counts with no voting,
 * exactly as before this enhancement.
 *
 * The DOM hooks (set by the Antlers partials):
 * - `article.knowledge_bases[data-article-id]` — the article, id for the view beacon.
 * - `a[data-kb-reaction][data-reaction][data-article-id]` — a like / dislike trigger.
 * - `[data-reaction-count="like|dislike"]` — the count span to update in place.
 *
 * The pure/DOM helpers are exported for unit testing.
 */

/** Selector for the article element carrying the id the view beacon reports. */
export const ARTICLE_SELECTOR = 'article.knowledge_bases[data-article-id]';

/** Selector for the like / dislike triggers. */
export const REACTION_SELECTOR = 'a[data-kb-reaction][data-reaction]';

/**
 * Format a tally for display, grouping thousands (mirrors the Antlers
 * `format_number(0, '.', ',')` the partial renders the initial value with).
 *
 * @param {number} value  The tally to format.
 * @returns {string} The grouped integer string.
 */
export function formatCount(value) {
	return new Intl.NumberFormat('en-US').format(value);
}

/**
 * POST to an engagement endpoint and resolve its JSON snapshot, or null on any
 * network / HTTP error so a failed beacon never throws into the page.
 *
 * @param {string} url  The endpoint URL.
 * @param {object|null} [body]  An optional JSON body.
 * @returns {Promise<object|null>} The parsed snapshot, or null on failure.
 */
export async function postJson(url, body = null) {
	try {
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: body === null ? undefined : JSON.stringify(body),
			credentials: 'same-origin',
		});

		if (!response.ok) {
			return null;
		}

		return await response.json();
	} catch {
		return null;
	}
}

/**
 * The count for a reaction type in a snapshot, or null when absent.
 *
 * @param {object} snapshot  A `{ likes, dislikes }` snapshot.
 * @param {string} type  `like` or `dislike`.
 * @returns {number|null} The count, or null.
 */
function countFor(snapshot, type) {
	const value = type === 'like' ? snapshot.likes : type === 'dislike' ? snapshot.dislikes : null;
	return typeof value === 'number' ? value : null;
}

/**
 * Apply an engagement snapshot to the DOM: update each reaction's count span, keep
 * its pluralized label in agreement with the new count, and reflect the visitor's
 * active vote on the two triggers' `aria-pressed`.
 *
 * @param {ParentNode} root  The scope to update within.
 * @param {object} snapshot  A `{ likes, dislikes, reaction }` snapshot.
 */
export function applySnapshot(root, snapshot) {
	if (!snapshot || typeof snapshot !== 'object') {
		return;
	}

	// Update the like / dislike count spans in place
	root.querySelectorAll('[data-reaction-count]').forEach((el) => {
		const count = countFor(snapshot, el.getAttribute('data-reaction-count'));
		if (count !== null) {
			el.textContent = formatCount(count);
		}
	});

	// Keep each count's label pluralized to match its new count ("1 Like",
	// "2 Likes") so a client-side update never leaves a stale "1 Likes"
	root.querySelectorAll('[data-reaction-label]').forEach((el) => {
		const count = countFor(snapshot, el.getAttribute('data-reaction-label'));
		const singular = el.getAttribute('data-reaction-label-singular') || '';
		if (count !== null && singular !== '') {
			el.textContent = count === 1 ? singular : `${singular}s`;
		}
	});

	// Reflect the visitor's current vote on each trigger
	root.querySelectorAll(REACTION_SELECTOR).forEach((trigger) => {
		const type = trigger.getAttribute('data-reaction');
		trigger.setAttribute('aria-pressed', String(snapshot.reaction === type));
	});
}

/**
 * The endpoint URL for a knowledge base article engagement action.
 *
 * @param {string} id  The article entry id.
 * @param {string} action  Either `view` or `react`.
 * @returns {string} The endpoint path.
 */
export function endpointUrl(id, action) {
	return `/!/kb/articles/${encodeURIComponent(id)}/${action}`;
}

/**
 * Wire the like / dislike triggers to the reaction endpoint. A single in-flight
 * guard collapses rapid double-clicks so the session-relative toggle stays coherent,
 * and marking `state.userActed` lets a later-arriving view beacon know not to
 * overwrite the visitor's vote.
 *
 * @param {ParentNode} root  The scope containing the triggers.
 * @param {(url: string, body?: object|null) => Promise<object|null>} request  The POST helper.
 * @param {{ userActed: boolean }} [state]  Shared interaction state.
 */
export function wireReactions(root, request, state = { userActed: false }) {
	const triggers = root.querySelectorAll(REACTION_SELECTOR);

	// One shared guard: there is a single reactions block per article page
	let inFlight = false;

	triggers.forEach((trigger) => {
		trigger.addEventListener('click', async (event) => {
			// The triggers are anchors (stretched-link); never navigate
			event.preventDefault();

			const id = trigger.getAttribute('data-article-id');
			const reaction = trigger.getAttribute('data-reaction');

			// Ignore a click with no target article, no reaction, or one already in flight
			if (!id || !reaction || inFlight) {
				return;
			}

			// The visitor has now voted — the view beacon must not revert this
			state.userActed = true;
			inFlight = true;

			try {
				const snapshot = await request(endpointUrl(id, 'react'), { reaction });
				applySnapshot(root, snapshot);
			} finally {
				inFlight = false;
			}
		});
	});
}

/**
 * Fire the one-per-load view beacon for the article and hydrate the rating UI from
 * its snapshot (counts + the visitor's current vote state). If the visitor has
 * already voted by the time the (possibly slow) beacon resolves, its stale snapshot
 * is dropped so it cannot undo the vote in the UI.
 *
 * @param {ParentNode} root  The document / scope to search for the article.
 * @param {(url: string, body?: object|null) => Promise<object|null>} request  The POST helper.
 * @param {{ userActed: boolean }} [state]  Shared interaction state.
 * @returns {Promise<void>}
 */
export async function fireViewBeacon(root, request, state = { userActed: false }) {
	const article = root.querySelector(ARTICLE_SELECTOR);

	// Nothing to count when the page is not a knowledge base article
	if (!article) {
		return;
	}

	const id = article.getAttribute('data-article-id');
	if (!id) {
		return;
	}

	// Record the view and hydrate the rating UI from the returned snapshot — unless
	// the visitor voted while the beacon was in flight, in which case the reaction
	// response is the fresher truth and must win.
	const snapshot = await request(endpointUrl(id, 'view'), null);
	if (!state.userActed) {
		applySnapshot(root, snapshot);
	}
}

/**
 * Initialize knowledge base article engagement on the given root: wire the reaction
 * triggers and fire the view beacon, sharing one interaction-state object so a slow
 * view beacon never clobbers a vote the visitor already cast.
 *
 * @param {ParentNode} [root]  The scope (defaults to document).
 * @param {(url: string, body?: object|null) => Promise<object|null>} [request]  The POST helper (defaults to postJson).
 */
export function initKbArticleEngagement(root = document, request = postJson) {
	const state = { userActed: false };
	wireReactions(root, request, state);
	fireViewBeacon(root, request, state);
}
