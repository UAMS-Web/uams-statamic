// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';

import {
	buildTocTree,
	collectHeadings,
	deriveHeadingId,
	initTableOfContents,
	slugifyHeading,
} from './toc-engine.js';

/**
 * Put an entry-content region and a table-of-contents nav in the document.
 *
 * @param {string} contentHtml  Markup for the scanned region
 * @returns {{nav: HTMLElement, content: HTMLElement}} The nav and the scanned region
 */
function build(contentHtml) {
	document.body.innerHTML = `
		<div id="entry-content">${contentHtml}</div>
		<nav data-toc hidden>
			<h2 id="toc-title">On this page</h2>
			<div data-toc-list></div>
		</nav>
	`;

	return {
		nav: document.querySelector('[data-toc]'),
		content: document.querySelector('#entry-content'),
	};
}

/** The rendered list, as `href` strings, nesting flattened depth-first. */
function linkHrefs(nav) {
	return Array.from(nav.querySelectorAll('a')).map((a) => a.getAttribute('href'));
}

beforeEach(() => {
	document.body.innerHTML = '';
});

describe('slugifyHeading', () => {
	it('reduces text to a hyphenated slug', () => {
		expect(slugifyHeading('What To Expect')).toBe('what-to-expect');
	});

	it('collapses punctuation and trims the edges', () => {
		expect(slugifyHeading('  Before & After -- Really?  ')).toBe('before-after-really');
	});

	it('composes a decomposed accent instead of splitting the word at it', () => {
		// Written as escapes so the source stays ASCII: the input is `Cafe` followed by a
		// COMBINING acute (U+0301), the output the single composed letter (U+00E9). Without
		// the NFC pass the mark is neither a letter nor a number, so it collapses to a
		// hyphen and one word becomes two.
		expect(slugifyHeading('Cafe\u0301 Menu')).toBe('caf\u00e9-menu');
	});

	it('does not alter a script where a combining mark is part of the letter', () => {
		// U+30B5 U+30FC U+30D3 U+30B9. The mark on the third character is part of the
		// letter, not decoration: a decompose-and-strip pass rewrites it to U+30D2 and the
		// word becomes a different one, with nothing downstream able to report that the
		// anchor now names something else. This is why accents are not stripped at all.
		const kana = '\u30b5\u30fc\u30d3\u30b9';

		expect(slugifyHeading(kana)).toBe(kana);
	});
});

describe('deriveHeadingId', () => {
	it('prefixes the slug', () => {
		expect(deriveHeadingId('Our Approach', () => false)).toBe('heading-our-approach');
	});

	it('counts from 2 on collision', () => {
		const taken = new Set(['heading-overview']);

		expect(deriveHeadingId('Overview', (id) => taken.has(id))).toBe('heading-overview-2');
	});

	it('falls back for a heading with no alphanumeric characters', () => {
		expect(deriveHeadingId('---', () => false)).toBe('heading-section');
	});
});

describe('collectHeadings', () => {
	it('reuses an id the heading already has, and generates one where it is missing', () => {
		// The second acceptance criterion, both arms. The reuse arm is the common case on a
		// real page: every block h2 already carries module-<id>-title.
		const { content } = build(`
			<h2 id="module-abc-title">Already Anchored</h2>
			<h3>Needs One</h3>
		`);

		const headings = collectHeadings(content);

		expect(headings.map((h) => h.id)).toEqual(['module-abc-title', 'heading-needs-one']);
		// Asserted on the DOM too: the link is only useful if the target really got the id.
		expect(content.querySelector('h3').id).toBe('heading-needs-one');
	});

	it('does not rewrite an id that is already in use elsewhere', () => {
		const { content } = build(`
			<h2 id="heading-overview">Overview</h2>
			<h3>Overview</h3>
		`);

		expect(collectHeadings(content).map((h) => h.id)).toEqual([
			'heading-overview',
			'heading-overview-2',
		]);
	});

	it('ignores headings inside the table of contents itself', () => {
		// The nav's own "On this page" is an h2. Without the skip it lists itself, and it
		// does so on every page the block is used on.
		const { nav } = build('<h2>Real Heading</h2>');
		const scanned = collectHeadings(document.body, nav);

		expect(scanned.map((h) => h.text)).toEqual(['Real Heading']);
	});

	it('ignores every table of contents on the page, not only this one', () => {
		// Two of these blocks on one page is allowed, and each nav's title is an h2. Skipping
		// only the nav being filled makes each list the other's title as though it were a
		// section of the page.
		document.body.innerHTML = `
			<div id="entry-content">
				<nav data-toc><h2>On this page</h2></nav>
				<h2>Real Heading</h2>
				<nav data-toc><h2>Also on this page</h2></nav>
			</div>
		`;

		const first = document.querySelector('[data-toc]');
		const scanned = collectHeadings(document.querySelector('#entry-content'), first);

		expect(scanned.map((h) => h.text)).toEqual(['Real Heading']);
	});

	it('skips a heading with no text', () => {
		const { content } = build('<h2></h2><h2>Real</h2>');

		expect(collectHeadings(content).map((h) => h.text)).toEqual(['Real']);
	});
});

describe('buildTocTree', () => {
	it('nests h3 under the preceding h2', () => {
		const tree = buildTocTree([
			{ level: 1, id: 'a', text: 'A' },
			{ level: 2, id: 'a1', text: 'A1' },
			{ level: 2, id: 'a2', text: 'A2' },
			{ level: 1, id: 'b', text: 'B' },
		]);

		expect(tree.map((n) => n.id)).toEqual(['a', 'b']);
		expect(tree[0].children.map((n) => n.id)).toEqual(['a1', 'a2']);
		expect(tree[1].children).toEqual([]);
	});

	it('promotes an h3 that has no h2 above it', () => {
		// Malformed content should still produce reachable links rather than none.
		const tree = buildTocTree([{ level: 2, id: 'orphan', text: 'Orphan' }]);

		expect(tree.map((n) => n.id)).toEqual(['orphan']);
	});
});

describe('initTableOfContents', () => {
	it('renders nested jump links and reveals the nav', () => {
		const { nav } = build(`
			<h2>First</h2>
			<h3>First Detail</h3>
			<h2 id="module-x-title">Second</h2>
		`);

		const count = initTableOfContents(nav);

		expect(count).toBe(3);
		expect(nav.hidden).toBe(false);
		expect(linkHrefs(nav)).toEqual(['#heading-first', '#heading-first-detail', '#module-x-title']);
		// Nesting is structural, not visual: the h3 is inside the h2's list item.
		expect(nav.querySelectorAll('ol > li > ol > li').length).toBe(1);
	});

	it('renders nothing and stays hidden when there are no qualifying headings', () => {
		// The third acceptance criterion. Hidden rather than empty, so a page with no
		// headings shows no table of contents instead of a bare title over an empty list.
		const { nav } = build('<p>Prose with no headings.</p><h4>Too deep to list</h4>');

		expect(initTableOfContents(nav)).toBe(0);
		expect(nav.hidden).toBe(true);
		expect(nav.querySelectorAll('a').length).toBe(0);
	});

	it('takes link text from textContent, so content markup cannot reach the nav as HTML', () => {
		const { nav } = build('<h2>Safe <em>&lt;img src=x onerror=alert(1)&gt;</em></h2>');

		initTableOfContents(nav);

		const link = nav.querySelector('a');

		expect(link.querySelector('img')).toBeNull();
		expect(link.textContent).toContain('<img src=x onerror=alert(1)>');
	});

	it('honours a scan root named on the block', () => {
		document.body.innerHTML = `
			<div id="entry-content"><h2>Ignored</h2></div>
			<div id="other"><h2>Wanted</h2></div>
			<nav data-toc data-toc-root="#other" hidden><div data-toc-list></div></nav>
		`;

		const nav = document.querySelector('[data-toc]');
		initTableOfContents(nav);

		expect(nav.querySelector('a').textContent).toBe('Wanted');
	});
});
