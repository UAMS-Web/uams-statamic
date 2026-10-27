// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';

import { initTableFeatures } from './table-features-engine.js';

/**
 * Build a feature table in the document and enhance it.
 *
 * @param {object} options  Feature data-attributes to set
 * @param {Array<Array<string>>} rows  Body rows (each an array of cell texts)
 * @param {Array<string>} headers  Header cell texts
 * @returns {HTMLTableElement} The enhanced table
 */
function buildTable(options, rows, headers = ['Name', 'Age', 'City']) {
	const attrs = Object.entries(options)
		.map(([key, value]) => (value === true ? key : `${key}="${value}"`))
		.join(' ');

	const head = `<thead><tr>${headers.map((h) => `<th>${h}</th>`).join('')}</tr></thead>`;
	const body = `<tbody>${rows
		.map((cells) => `<tr>${cells.map((c) => `<td>${c}</td>`).join('')}</tr>`)
		.join('')}</tbody>`;

	document.body.innerHTML = `<div class="table-responsive"><table ${attrs}>${head}${body}</table></div>`;

	const table = document.querySelector('table');
	initTableFeatures(table);

	return table;
}

/** Visible (not hidden) body rows. */
function visibleRows(table) {
	return Array.from(table.tBodies[0].rows).filter((row) => !row.hidden);
}

/** First-column text of the visible rows, in DOM order. */
function visibleFirstColumn(table) {
	return visibleRows(table).map((row) => row.cells[0].textContent);
}

const PEOPLE = [
	['Carol', '30', 'Austin'],
	['Alice', '25', 'Boston'],
	['Bob', '40', 'Austin'],
	['Dave', '35', 'Denver'],
];

describe('table-features-engine', () => {
	beforeEach(() => {
		document.body.innerHTML = '';
	});

	it('leaves a plain table untouched when no features are enabled', () => {
		const table = buildTable({}, PEOPLE);

		expect(document.querySelector('.table-features')).toBeNull();
		expect(visibleRows(table)).toHaveLength(4);
	});

	describe('pagination', () => {
		it('shows only the first page and a pager', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-paginate': true, 'data-table-page-length': '2' }, PEOPLE);

			expect(visibleRows(table)).toHaveLength(2);
			expect(visibleFirstColumn(table)).toEqual(['Carol', 'Alice']);
			expect(document.querySelector('.table-features__pager .pagination')).not.toBeNull();
		});

		it('advances to the next page when the next button is clicked', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-paginate': true, 'data-table-page-length': '2' }, PEOPLE);

			const next = [...document.querySelectorAll('.page-link')].find((b) => b.getAttribute('aria-label') === 'Next page');
			next.click();

			expect(visibleFirstColumn(table)).toEqual(['Bob', 'Dave']);
		});

		it('disables the previous button on the first page', () => {
			buildTable({ 'data-table-features': true, 'data-table-paginate': true, 'data-table-page-length': '2' }, PEOPLE);

			const prev = [...document.querySelectorAll('.page-link')].find((b) => b.getAttribute('aria-label') === 'Previous page');
			expect(prev.disabled).toBe(true);
		});

		it('shows every row when the adjustable length is set to All', () => {
			const table = buildTable(
				{ 'data-table-features': true, 'data-table-paginate': true, 'data-table-page-length': '2', 'data-table-page-length-adjustable': true },
				PEOPLE,
			);

			const select = document.querySelector('.table-features__length select');
			select.value = 'all';
			select.dispatchEvent(new Event('change'));

			expect(visibleRows(table)).toHaveLength(4);
		});
	});

	describe('search', () => {
		it('filters rows to those containing the term', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-search': true }, PEOPLE);

			const input = document.querySelector('.table-features__search input');
			input.value = 'austin';
			input.dispatchEvent(new Event('input'));

			expect(visibleFirstColumn(table)).toEqual(['Carol', 'Bob']);
		});

		it('labels the search input for assistive tech', () => {
			buildTable({ 'data-table-features': true, 'data-table-search': true }, PEOPLE);

			const input = document.querySelector('.table-features__search input');
			const label = document.querySelector(`label[for="${input.id}"]`);
			expect(label).not.toBeNull();
			expect(label.textContent).toBe('Search table');
		});
	});

	describe('sorting', () => {
		it('sorts a numeric column ascending then descending on repeat clicks', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-sort': true }, PEOPLE);

			const ageButton = table.tHead.rows[0].cells[1].querySelector('button');
			ageButton.click();
			expect(visibleFirstColumn(table)).toEqual(['Alice', 'Carol', 'Dave', 'Bob']); // 25,30,35,40

			ageButton.click();
			expect(visibleFirstColumn(table)).toEqual(['Bob', 'Dave', 'Carol', 'Alice']); // 40,35,30,25
		});

		it('sorts a text column alphabetically', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-sort': true }, PEOPLE);

			table.tHead.rows[0].cells[0].querySelector('button').click();

			expect(visibleFirstColumn(table)).toEqual(['Alice', 'Bob', 'Carol', 'Dave']);
		});

		it('announces sort state via aria-sort', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-sort': true }, PEOPLE);

			const ageCell = table.tHead.rows[0].cells[1];
			ageCell.querySelector('button').click();

			expect(ageCell.getAttribute('aria-sort')).toBe('ascending');
			expect(table.tHead.rows[0].cells[0].getAttribute('aria-sort')).toBe('none');
		});

		it('does not nest a header link inside the sort button', () => {
			document.body.innerHTML =
				'<div class="table-responsive"><table data-table-features data-table-sort>'
				+ '<thead><tr><th><a href="/docs">Name</a></th><th>Age</th></tr></thead>'
				+ '<tbody><tr><td>Bob</td><td>40</td></tr><tr><td>Al</td><td>25</td></tr></tbody></table></div>';
			const table = document.querySelector('table');
			initTableFeatures(table);

			const linkedHeader = table.tHead.rows[0].cells[0];
			// The link stays a direct, independently-clickable header child
			expect(linkedHeader.querySelector('button a')).toBeNull();
			expect(linkedHeader.querySelector(':scope > a[href="/docs"]')).not.toBeNull();
			// A separate, labelled sort button is added and still sorts
			const sortButton = linkedHeader.querySelector('button.table-features__sort-btn');
			expect(sortButton.getAttribute('aria-label')).toBe('Sort by Name');

			sortButton.click();
			expect(visibleFirstColumn(table)).toEqual(['Al', 'Bob']);
		});
	});

	describe('information line', () => {
		it('reports the visible range and total', () => {
			buildTable({ 'data-table-features': true, 'data-table-paginate': true, 'data-table-page-length': '2', 'data-table-info': true }, PEOPLE);

			expect(document.querySelector('.table-features__info').textContent).toBe('Showing 1 to 2 of 4 entries');
		});

		it('updates after a search and uses the singular noun for one match', () => {
			buildTable({ 'data-table-features': true, 'data-table-search': true, 'data-table-info': true }, PEOPLE);

			const input = document.querySelector('.table-features__search input');
			input.value = 'denver';
			input.dispatchEvent(new Event('input'));

			expect(document.querySelector('.table-features__info').textContent).toBe('Showing 1 to 1 of 1 entry');
		});
	});

	it('combines search and pagination, resetting to page one', () => {
		const table = buildTable(
			{ 'data-table-features': true, 'data-table-search': true, 'data-table-paginate': true, 'data-table-page-length': '1', 'data-table-info': true },
			PEOPLE,
		);

		const input = document.querySelector('.table-features__search input');
		input.value = 'austin';
		input.dispatchEvent(new Event('input'));

		// Two Austin rows, one per page → first page shows the first match only
		expect(visibleFirstColumn(table)).toEqual(['Carol']);
		expect(document.querySelector('.table-features__info').textContent).toBe('Showing 1 to 1 of 2 entries');
	});

	describe('column filters', () => {
		it('adds a dropdown of the distinct values for a configured column', () => {
			buildTable({ 'data-table-features': true, 'data-table-filter': true, 'data-table-filter-columns': 'C' }, PEOPLE);

			const selects = document.querySelectorAll('.table-features__filter select');
			expect(selects).toHaveLength(1); // only column C (City)
			expect([...selects[0].options].map((option) => option.textContent)).toEqual(['All City', 'Austin', 'Boston', 'Denver']);
		});

		it('filters rows to the selected column value', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-filter': true, 'data-table-filter-columns': 'C' }, PEOPLE);

			const select = document.querySelector('.table-features__filter select');
			select.value = 'Austin';
			select.dispatchEvent(new Event('change'));

			expect(visibleFirstColumn(table)).toEqual(['Carol', 'Bob']);
		});

		it('offers a dropdown for every column when none are specified', () => {
			buildTable({ 'data-table-features': true, 'data-table-filter': true }, PEOPLE);

			expect(document.querySelectorAll('.table-features__filter select')).toHaveLength(3);
		});

		it('combines a column filter with the search box', () => {
			const table = buildTable(
				{ 'data-table-features': true, 'data-table-filter': true, 'data-table-filter-columns': 'C', 'data-table-search': true },
				PEOPLE,
			);

			const select = document.querySelector('.table-features__filter select');
			select.value = 'Austin';
			select.dispatchEvent(new Event('change'));

			const input = document.querySelector('.table-features__search input');
			input.value = 'bob';
			input.dispatchEvent(new Event('input'));

			expect(visibleFirstColumn(table)).toEqual(['Bob']);
		});
	});

	describe('responsive stack', () => {
		it('marks the table to stack below the configured breakpoint', () => {
			const table = buildTable(
				{ 'data-table-features': true, 'data-table-responsive-stack': true, 'data-table-responsive-breakpoint': 'tablet' },
				PEOPLE,
			);

			expect(table.classList.contains('table--stack')).toBe(true);
			expect(table.classList.contains('table--stack-tablet')).toBe(true);
		});

		it('labels each body cell with its column header for the stacked card', () => {
			const table = buildTable({ 'data-table-features': true, 'data-table-responsive-stack': true }, PEOPLE);

			const cells = table.tBodies[0].rows[0].cells;
			expect(cells[0].getAttribute('data-label')).toBe('Name');
			expect(cells[1].getAttribute('data-label')).toBe('Age');
			expect(cells[2].getAttribute('data-label')).toBe('City');
		});
	});
});
