// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
	applySnapshot,
	endpointUrl,
	fireViewBeacon,
	formatCount,
	initKbArticleEngagement,
	wireReactions,
} from './kb-article-engagement.js';

/**
 * Build a knowledge base article DOM: the `<article>` carrying the id plus a
 * reactions block with a like and a dislike trigger and their count spans.
 *
 * @param {string} [id]  The article entry id.
 * @returns {HTMLElement} The article root.
 */
function mountArticle(id = 'article-1') {
	document.body.innerHTML = `
		<article class="knowledge_bases" data-article-id="${id}">
			<div class="card-deck">
				<a href="#" data-kb-reaction data-reaction="like" aria-pressed="false" data-article-id="${id}">Like</a>
				<span data-reaction-count="like">0</span>
				<span data-reaction-label="like" data-reaction-label-singular="Like">Likes</span>
				<a href="#" data-kb-reaction data-reaction="dislike" aria-pressed="false" data-article-id="${id}">Dislike</a>
				<span data-reaction-count="dislike">0</span>
				<span data-reaction-label="dislike" data-reaction-label-singular="Dislike">Dislikes</span>
			</div>
		</article>
	`;

	return document.querySelector('article');
}

/** A resolved snapshot with the given fields. */
function snapshot(fields = {}) {
	return { views: 0, likes: 0, dislikes: 0, net: 0, reaction: null, ...fields };
}

beforeEach(() => {
	document.body.innerHTML = '';
});

describe('formatCount', () => {
	it('groups thousands', () => {
		expect(formatCount(0)).toBe('0');
		expect(formatCount(1234)).toBe('1,234');
		expect(formatCount(1000000)).toBe('1,000,000');
	});
});

describe('endpointUrl', () => {
	it('builds and encodes the endpoint path', () => {
		expect(endpointUrl('abc', 'view')).toBe('/!/kb/articles/abc/view');
		expect(endpointUrl('a/b', 'react')).toBe('/!/kb/articles/a%2Fb/react');
	});
});

describe('applySnapshot', () => {
	it('updates the count spans and pressed state from the snapshot', () => {
		const root = mountArticle();

		applySnapshot(root, snapshot({ likes: 1200, dislikes: 3, reaction: 'like' }));

		expect(root.querySelector('[data-reaction-count="like"]').textContent).toBe('1,200');
		expect(root.querySelector('[data-reaction-count="dislike"]').textContent).toBe('3');
		expect(root.querySelector('[data-reaction="like"]').getAttribute('aria-pressed')).toBe('true');
		expect(root.querySelector('[data-reaction="dislike"]').getAttribute('aria-pressed')).toBe('false');
	});

	it('clears the pressed state when the reaction is toggled off (null)', () => {
		const root = mountArticle();
		root.querySelector('[data-reaction="like"]').setAttribute('aria-pressed', 'true');

		applySnapshot(root, snapshot({ reaction: null }));

		expect(root.querySelector('[data-reaction="like"]').getAttribute('aria-pressed')).toBe('false');
	});

	it('pluralizes the count label to agree with the new count', () => {
		const root = mountArticle();

		applySnapshot(root, snapshot({ likes: 1, dislikes: 2 }));
		expect(root.querySelector('[data-reaction-label="like"]').textContent).toBe('Like');
		expect(root.querySelector('[data-reaction-label="dislike"]').textContent).toBe('Dislikes');

		applySnapshot(root, snapshot({ likes: 5, dislikes: 1 }));
		expect(root.querySelector('[data-reaction-label="like"]').textContent).toBe('Likes');
		expect(root.querySelector('[data-reaction-label="dislike"]').textContent).toBe('Dislike');
	});

	it('ignores a null snapshot (a failed request)', () => {
		const root = mountArticle();

		expect(() => applySnapshot(root, null)).not.toThrow();
		expect(root.querySelector('[data-reaction-count="like"]').textContent).toBe('0');
	});
});

describe('wireReactions', () => {
	it('posts the clicked reaction and applies the returned snapshot', async () => {
		const root = mountArticle('kb-42');
		const request = vi.fn().mockResolvedValue(snapshot({ likes: 1, reaction: 'like' }));

		wireReactions(root, request);
		root.querySelector('[data-reaction="like"]').click();
		await vi.waitFor(() => expect(request).toHaveBeenCalled());

		expect(request).toHaveBeenCalledWith('/!/kb/articles/kb-42/react', { reaction: 'like' });
		expect(root.querySelector('[data-reaction-count="like"]').textContent).toBe('1');
		expect(root.querySelector('[data-reaction="like"]').getAttribute('aria-pressed')).toBe('true');
	});

	it('prevents the anchor from navigating', () => {
		const root = mountArticle();
		wireReactions(root, vi.fn().mockResolvedValue(snapshot()));

		const event = new window.MouseEvent('click', { bubbles: true, cancelable: true });
		root.querySelector('[data-reaction="like"]').dispatchEvent(event);

		expect(event.defaultPrevented).toBe(true);
	});

	it('collapses a rapid double-click to a single in-flight request', async () => {
		const root = mountArticle();
		let resolve;
		const request = vi.fn(() => new Promise((r) => { resolve = r; }));

		wireReactions(root, request);
		const like = root.querySelector('[data-reaction="like"]');
		like.click();
		like.click();

		expect(request).toHaveBeenCalledTimes(1);

		resolve(snapshot({ likes: 1 }));
		await vi.waitFor(() => expect(request).toHaveBeenCalledTimes(1));
	});
});

describe('fireViewBeacon', () => {
	it('posts a view for the article and hydrates the UI', async () => {
		const root = mountArticle('kb-7');
		const request = vi.fn().mockResolvedValue(snapshot({ views: 5, likes: 2, reaction: 'dislike' }));

		await fireViewBeacon(document, request);

		expect(request).toHaveBeenCalledWith('/!/kb/articles/kb-7/view', null);
		expect(root.querySelector('[data-reaction-count="like"]').textContent).toBe('2');
		expect(root.querySelector('[data-reaction="dislike"]').getAttribute('aria-pressed')).toBe('true');
	});

	it('does nothing when the page is not a knowledge base article', async () => {
		document.body.innerHTML = '<article class="posts" data-article-id="p-1"></article>';
		const request = vi.fn();

		await fireViewBeacon(document, request);

		expect(request).not.toHaveBeenCalled();
	});

	it('does not hydrate (revert the vote) once the visitor has acted', async () => {
		const root = mountArticle('kb-3');
		// Simulate a vote already applied: like count 1, pressed.
		applySnapshot(root, snapshot({ likes: 1, reaction: 'like' }));
		const state = { userActed: true };
		// A stale beacon snapshot that would undo the vote if applied.
		const request = vi.fn().mockResolvedValue(snapshot({ likes: 0, reaction: null }));

		await fireViewBeacon(document, request, state);

		expect(request).toHaveBeenCalled(); // the view is still recorded server-side
		expect(root.querySelector('[data-reaction-count="like"]').textContent).toBe('1');
		expect(root.querySelector('[data-reaction="like"]').getAttribute('aria-pressed')).toBe('true');
	});
});

describe('init race', () => {
	it('a slow view beacon does not clobber a vote cast while it was in flight', async () => {
		const root = mountArticle('kb-race');
		let resolveBeacon;
		const request = vi.fn((url) => {
			if (url.endsWith('/view')) {
				return new Promise((r) => { resolveBeacon = r; });
			}
			// reaction resolves immediately
			return Promise.resolve(snapshot({ likes: 1, reaction: 'like' }));
		});

		initKbArticleEngagement(document, request);

		// User votes before the beacon resolves.
		root.querySelector('[data-reaction="like"]').click();
		await vi.waitFor(() =>
			expect(root.querySelector('[data-reaction="like"]').getAttribute('aria-pressed')).toBe('true'),
		);

		// The beacon now resolves with pre-vote state; it must not revert the UI.
		resolveBeacon(snapshot({ likes: 0, reaction: null }));
		await Promise.resolve();
		await Promise.resolve();

		expect(root.querySelector('[data-reaction-count="like"]').textContent).toBe('1');
		expect(root.querySelector('[data-reaction="like"]').getAttribute('aria-pressed')).toBe('true');
	});
});

describe('initKbArticleEngagement', () => {
	it('fires the view beacon and wires reactions on init', async () => {
		const root = mountArticle('kb-9');
		const request = vi.fn().mockResolvedValue(snapshot({ views: 1 }));

		initKbArticleEngagement(document, request);
		await vi.waitFor(() => expect(request).toHaveBeenCalledWith('/!/kb/articles/kb-9/view', null));

		root.querySelector('[data-reaction="like"]').click();
		await vi.waitFor(() =>
			expect(request).toHaveBeenCalledWith('/!/kb/articles/kb-9/react', { reaction: 'like' }),
		);
	});
});
