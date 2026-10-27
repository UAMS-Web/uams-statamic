/**
 * Front-end table-of-contents engine for the Table of Contents page-builder block
 * (`resources/views/page_builder/_uamsweb_block_table_of_contents.antlers.html`, issue UAMS-Web/uams-statamic-archive#1283).
 *
 * The block server-renders an empty, hidden `<nav>`. This engine scans the entry content for
 * `h2`/`h3` headings, gives every heading a stable id, builds a nested list of jump links, and
 * reveals the nav. When the page has no qualifying headings the nav stays hidden, so a page
 * without headings renders no table of contents at all — and so does a page with JavaScript
 * turned off, which is the honest degradation here: a list of jump links whose targets were
 * never given ids would be a list of broken links.
 *
 * Scanning the rendered DOM rather than the entry's fields is what makes the ids agree. The id
 * a link points at and the id on the heading are assigned by the same pass over the same nodes,
 * so they cannot drift. Deriving them server-side would mean reproducing, in a second place,
 * every rule Bard and the block templates use to emit a heading.
 *
 * ANCHOR-ID CONVENTION, because a second implementation will meet it (see issue #1214, which
 * puts an author-supplied anchor id on a SET where this generates one on a HEADING):
 *
 *   1. An id already on the heading always wins and is never rewritten. That covers both the
 *      `module-<block-id>-title` ids that `page_builder/components/_block_header` already emits
 *      on every block `<h2>`, and anchors an editor set with Bard's anchor button. In practice
 *      this is the common case for `h2` and the rarer one for `h3`.
 *   2. Otherwise the id is `heading-` + a slug of the heading's text.
 *   3. On collision a `-2`, `-3`, ... suffix is appended, counting within the document.
 *
 * The `heading-` prefix is deliberate: a bare slug can collide with an unrelated element id, and
 * an id that is generated needs to be distinguishable from one a person chose. Anything adopting
 * this convention should keep the prefix and the reuse-first rule; the slug shape itself matters
 * less than that only one rule exists.
 *
 * Accessibility: the nav is a labelled `<nav>` containing an ordered list, so the heading
 * hierarchy is conveyed as structure rather than as indentation. A heading a block has hidden
 * with `sr-only` IS listed: the editor hid it visually, but it still marks a real section and
 * the block fieldset calls it "necessary for page hierarchy", which is exactly what a table of
 * contents reports.
 *
 * Registered through `lib/alpine-engine.js` for the shared, idempotent Alpine-lifecycle binding,
 * exactly like the sibling engines.
 */

import { registerAlpineEngine } from './lib/alpine-engine.js';

/** Selector for table-of-contents navs the render partial emitted. */
export const TOC_SELECTOR = '[data-toc]';

/** Where the headings are looked for when the block does not name a root. */
export const DEFAULT_CONTENT_SELECTOR = '#entry-content';

/** The heading levels a table of contents lists, outermost first. */
export const TOC_HEADING_LEVELS = ['H2', 'H3'];

/** Prefix on every id this engine generates. See the convention note above. */
export const GENERATED_ID_PREFIX = 'heading-';

/**
 * Reduce heading text to the slug half of a generated id.
 *
 * Unicode letters and digits are kept, so a heading that is not in English still yields a
 * readable id rather than an empty one; everything else collapses to a single hyphen.
 *
 * @param {string} text  The heading's text content
 * @returns {string} The slug, which may be empty when the heading has no alphanumeric characters
 */
export function slugifyHeading(text) {
	return String(text ?? '')
		// NFC, the COMPOSED form, and deliberately not a decompose-and-strip-accents pass.
		// Stripping combining marks is the usual way to reach an ASCII slug and it is wrong
		// here, because a combining mark is decorative in Latin and load-bearing elsewhere:
		// decomposing an accented Spanish word and dropping its mark is harmless, while the
		// same pass removes the voicing mark from a Japanese kana and yields a DIFFERENT
		// word -- silently, since nothing downstream can tell that the anchor now names
		// something else. Keeping letters as they are costs a percent-encoded fragment in a
		// copied URL and is correct in every script; ids may therefore be non-ASCII, which
		// HTML5 permits.
		.normalize('NFC')
		.toLowerCase()
		.replace(/[^\p{Letter}\p{Number}]+/gu, '-')
		.replace(/^-+|-+$/g, '');
}

/**
 * Derive a unique id for a heading that does not already have one.
 *
 * @param {string} text  The heading's text content
 * @param {(candidate: string) => boolean} isTaken  Whether an id is already in use
 * @returns {string} An id not currently in use
 */
export function deriveHeadingId(text, isTaken) {
	const slug = slugifyHeading(text);
	const base = GENERATED_ID_PREFIX + (slug === '' ? 'section' : slug);

	if (!isTaken(base)) return base;

	// Count from 2, so the first duplicate reads as "the second one" rather than as "-1".
	let suffix = 2;
	while (isTaken(`${base}-${suffix}`)) suffix += 1;

	return `${base}-${suffix}`;
}

/**
 * Collect the headings a table of contents should list, assigning ids to those without one.
 *
 * This MUTATES the headings it finds, which is the point: the link and its target get their id
 * from the same call, so a generated id cannot be computed one way here and another way there.
 *
 * @param {Element} root  The element to scan
 * @param {Element|null} skip  A subtree to ignore, normally the table of contents itself
 * @returns {Array<{level: number, id: string, text: string}>} The headings, in document order
 */
export function collectHeadings(root, skip = null) {
	if (!root) return [];

	const doc = root.ownerDocument;
	const selector = TOC_HEADING_LEVELS.join(', ');
	const isTaken = (candidate) => doc.getElementById(candidate) !== null;

	return Array.from(root.querySelectorAll(selector))
		// Exclude every table of contents on the page, not merely this one. Each nav's own
		// title is an h2, so with only `skip` a second block on the same page lists the
		// first one's title as if it were a section -- a link that jumps the reader to
		// another table of contents. `skip` is kept for a caller scanning a region that is
		// not a nav.
		.filter((heading) => !heading.closest(TOC_SELECTOR))
		.filter((heading) => !(skip && skip.contains(heading)))
		.map((heading) => {
			const text = (heading.textContent ?? '').trim();

			if (!heading.id) {
				heading.id = deriveHeadingId(text, isTaken);
			}

			return {
				level: TOC_HEADING_LEVELS.indexOf(heading.tagName) + 1,
				id: heading.id,
				text,
			};
		})
		.filter((entry) => entry.text !== '');
}

/**
 * Nest a flat list of headings by level.
 *
 * A deeper heading with no parent above it — a page opening on an `h3` — is promoted to the top
 * level rather than dropped, because a jump link the reader can see is worth more than a tidy
 * tree, and the markup is malformed either way.
 *
 * @param {Array<{level: number, id: string, text: string}>} headings  Headings in document order
 * @returns {Array<{id: string, text: string, children: Array}>} The nested tree
 */
export function buildTocTree(headings) {
	const tree = [];

	headings.forEach((heading) => {
		const node = { id: heading.id, text: heading.text, children: [] };
		const parent = heading.level > 1 ? tree[tree.length - 1] : null;

		if (parent) {
			parent.children.push(node);
		} else {
			tree.push(node);
		}
	});

	return tree;
}

/**
 * Build the list markup for a table-of-contents tree.
 *
 * Built with the DOM API rather than assembled as an HTML string: the text comes from editor
 * content, and `textContent` cannot reintroduce markup the page already escaped.
 *
 * @param {Array<{id: string, text: string, children: Array}>} tree  The nested tree
 * @param {Document} doc  The document to create nodes in
 * @returns {HTMLOListElement} The list
 */
export function renderTocList(tree, doc) {
	const list = doc.createElement('ol');
	list.className = 'toc-list';

	tree.forEach((node) => {
		const item = doc.createElement('li');
		const link = doc.createElement('a');

		link.setAttribute('href', `#${node.id}`);
		link.textContent = node.text;
		item.appendChild(link);

		if (node.children.length > 0) {
			item.appendChild(renderTocList(node.children, doc));
		}

		list.appendChild(item);
	});

	return list;
}

/**
 * Populate and reveal one table-of-contents nav, or leave it hidden when there is nothing to list.
 *
 * @param {Element} el  The nav the render partial emitted
 * @returns {number} How many headings were listed
 */
export function initTableOfContents(el) {
	const doc = el.ownerDocument;
	const selector = el.dataset.tocRoot || DEFAULT_CONTENT_SELECTOR;
	const root = doc.querySelector(selector) || doc.body;

	const headings = collectHeadings(root, el);

	if (headings.length === 0) return 0;

	const target = el.querySelector('[data-toc-list]') || el;
	target.appendChild(renderTocList(buildTocTree(headings), doc));

	el.hidden = false;

	return headings.length;
}

/** Bind every table-of-contents nav on the page, once each. */
export function registerTocEngine() {
	registerAlpineEngine({
		selector: TOC_SELECTOR,
		boundAttr: 'tocBound',
		init: initTableOfContents,
	});
}
