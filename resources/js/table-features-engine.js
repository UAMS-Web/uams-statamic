/**
 * Front-end table-features engine (progressive enhancement for the Table Bard
 * set / `tables` collection render partial, `resources/views/sets/_table.antlers.html`).
 *
 * The Table set exposes visitor-facing feature toggles ported from TablePress:
 * sorting, searching, pagination (with an adjustable row count), an information
 * line, and row-hover/horizontal-scroll styling (issue UAMS-Web/uams-statamic-archive#231), plus per-column
 * filter dropdowns and a responsive stacked-card layout below a breakpoint
 * (issue UAMS-Web/uams-statamic-archive#243). The render
 * partial emits the enabled toggles as `data-table-*` attributes on the
 * `<table>`; this engine reads them and enhances the table **in place** —
 * reordering and hiding the existing server-rendered `<tr>` nodes rather than
 * re-rendering them, so the cells' sanitized HTML is never reconstructed.
 *
 * No jQuery: controls are built with the DOM API and reuse Bootstrap's existing
 * `.form-control` / `.pagination` styles. Without JavaScript the table renders
 * as a plain static table (the controls only ever exist once this engine runs).
 *
 * Accessibility:
 * - sortable headers are real `<button>`s and announce state via `aria-sort`;
 * - the search box has a (visually hidden) `<label>`;
 * - the pager is a `<nav>` of `<button>`s with `aria-current`/`aria-label`; and
 * - the information line is an `aria-live` status region.
 *
 * Registered through `lib/alpine-engine.js` for the shared, idempotent
 * Alpine-lifecycle binding, exactly like the sibling form engines.
 */

import { registerAlpineEngine } from './lib/alpine-engine.js';

/** Selector for tables the render partial flagged as feature-enhanced. */
const TABLE_FEATURES_SELECTOR = 'table[data-table-features]';

/** Page-length presets offered when the row count is adjustable. */
const PAGE_LENGTH_PRESETS = [10, 25, 50, 100];

/** Process-wide counter for unique control ids (label/input association). */
let instanceCount = 0;

/**
 * Compute a compact, windowed list of page numbers with ellipses.
 *
 * @param {number} current  The active page (1-based)
 * @param {number} total  The total number of pages
 * @returns {Array<number|string>} Page numbers, with `'…'` for gaps
 */
function pageWindow(current, total) {
	// Small ranges show every page
	if (total <= 7) {
		return Array.from({ length: total }, (_, i) => i + 1);
	}

	// Always include the first and last page plus a window around the current
	const pages = new Set([1, total, current, current - 1, current + 1]);
	const sorted = [...pages].filter((p) => p >= 1 && p <= total).sort((a, b) => a - b);

	// Insert ellipsis markers where the sequence skips pages
	const result = [];
	let previous = 0;
	sorted.forEach((page) => {
		if (page - previous > 1) {
			result.push('…');
		}
		result.push(page);
		previous = page;
	});

	return result;
}

/**
 * Per-table enhancement controller.
 */
class TableFeatures {
	/**
	 * @param {HTMLTableElement} table  The flagged table element
	 */
	constructor(table) {
		this.table = table;
		this.tbody = table.tBodies[0] || null;

		// A table with no body has nothing to sort, search or paginate
		if (!this.tbody) {
			return;
		}

		// The enhanceable rows are the body rows (head/foot stay fixed)
		this.rows = Array.from(this.tbody.rows);

		// Read the enabled features from the data-attributes
		this.sortable = table.hasAttribute('data-table-sort');
		this.searchable = table.hasAttribute('data-table-search');
		this.paginated = table.hasAttribute('data-table-paginate');
		this.showInfo = table.hasAttribute('data-table-info');
		this.lengthAdjustable = table.hasAttribute('data-table-page-length-adjustable');
		this.columnFilterable = table.hasAttribute('data-table-filter');
		this.responsiveStack = table.hasAttribute('data-table-responsive-stack');

		// Nothing to enhance unless at least one interactive feature is enabled
		if (!this.sortable && !this.searchable && !this.paginated && !this.showInfo && !this.columnFilterable && !this.responsiveStack) {
			return;
		}

		// Resolve the configured page length, defaulting to 10 when paginating
		const configured = parseInt(table.getAttribute('data-table-page-length') || '', 10);
		this.pageLength = this.paginated
			? (Number.isFinite(configured) && configured > 0 ? configured : 10)
			: Infinity;

		// Interaction state
		this.uid = `table-features-${++instanceCount}`;
		this.search = '';
		this.page = 1;
		this.sortIndex = null;
		this.sortDir = 'asc';
		this.filtered = this.rows;
		this.columnFilters = new Map();
		this.filterColumns = this.columnFilterable ? this.parseFilterColumns(table.getAttribute('data-table-filter-columns')) : [];

		// Build the surrounding controls, then do the first render
		this.buildScaffold();
		if (this.searchable) {
			this.buildSearch();
		}
		if (this.paginated && this.lengthAdjustable) {
			this.buildPageLength();
		}
		if (this.showInfo) {
			this.buildInfo();
		}
		if (this.paginated) {
			this.buildPager();
		}
		if (this.sortable) {
			this.buildSort();
		}
		if (this.columnFilterable) {
			this.buildColumnFilters();
		}
		if (this.responsiveStack) {
			this.applyResponsiveStack();
		}

		this.render();
	}

	/**
	 * Wrap the table in a container with top and bottom control bars.
	 */
	buildScaffold() {
		// Anchor on the responsive wrapper when present, else the table itself
		const anchor = this.table.closest('.table-responsive') || this.table;
		this.tableAnchor = anchor;

		// Container that groups the controls with the table
		this.container = document.createElement('div');
		this.container.className = 'table-features';
		anchor.parentNode.insertBefore(this.container, anchor);

		// Top bar (search + page length)
		this.topBar = document.createElement('div');
		this.topBar.className = 'table-features__controls table-features__controls--top';
		this.container.appendChild(this.topBar);

		// Move the table (wrapper) inside the container
		this.container.appendChild(anchor);

		// Bottom bar (information + pager)
		this.bottomBar = document.createElement('div');
		this.bottomBar.className = 'table-features__controls table-features__controls--bottom';
		this.container.appendChild(this.bottomBar);
	}

	/**
	 * Build the search input with a visually-hidden label.
	 */
	buildSearch() {
		const wrapper = document.createElement('div');
		wrapper.className = 'table-features__search';

		const id = `${this.uid}-search`;

		const label = document.createElement('label');
		label.setAttribute('for', id);
		label.className = 'sr-only';
		label.textContent = 'Search table';

		const input = document.createElement('input');
		input.type = 'search';
		input.id = id;
		input.className = 'form-control';
		input.placeholder = 'Search…';
		input.addEventListener('input', () => {
			this.search = input.value.trim().toLowerCase();
			this.page = 1;
			this.render();
		});

		wrapper.append(label, input);
		this.topBar.appendChild(wrapper);
	}

	/**
	 * Build the adjustable page-length select.
	 */
	buildPageLength() {
		const id = `${this.uid}-length`;

		const label = document.createElement('label');
		label.setAttribute('for', id);
		label.className = 'table-features__length';
		label.append('Show ');

		const select = document.createElement('select');
		select.id = id;
		select.className = 'form-control';

		// Offer the presets plus the configured length, then an "All" option
		const lengths = [...new Set([this.pageLength, ...PAGE_LENGTH_PRESETS])].sort((a, b) => a - b);
		lengths.forEach((length) => {
			const option = document.createElement('option');
			option.value = String(length);
			option.textContent = String(length);
			option.selected = length === this.pageLength;
			select.appendChild(option);
		});
		const allOption = document.createElement('option');
		allOption.value = 'all';
		allOption.textContent = 'All';
		select.appendChild(allOption);

		select.addEventListener('change', () => {
			this.pageLength = select.value === 'all' ? Infinity : parseInt(select.value, 10);
			this.page = 1;
			this.render();
		});

		label.append(select, ' entries');
		this.topBar.appendChild(label);
	}

	/**
	 * Build the live information region.
	 */
	buildInfo() {
		this.info = document.createElement('div');
		this.info.className = 'table-features__info';
		this.info.setAttribute('role', 'status');
		this.info.setAttribute('aria-live', 'polite');
		this.bottomBar.appendChild(this.info);
	}

	/**
	 * Build the pagination navigation landmark.
	 */
	buildPager() {
		this.pager = document.createElement('nav');
		this.pager.className = 'table-features__pager';
		this.pager.setAttribute('aria-label', 'Table pagination');
		this.bottomBar.appendChild(this.pager);
	}

	/**
	 * Turn each header cell into a sort button and seed its `aria-sort`.
	 */
	buildSort() {
		const head = this.table.tHead;

		// Sorting needs a header row to click
		if (!head || head.rows.length === 0) {
			this.sortable = false;

			return;
		}

		this.headerCells = Array.from(head.rows[0].cells);
		this.headerCells.forEach((cell, index) => {
			cell.classList.add('table-features__sortable');
			cell.setAttribute('aria-sort', 'none');

			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'table-features__sort-btn';

			if (cell.querySelector('a[href], button, input, select, textarea')) {
				// The header already holds interactive content (e.g. a link from
				// a sanitized cell). Nesting it inside the sort button would be
				// invalid HTML and break it, so add the sort trigger alongside,
				// naming the column for assistive tech.
				button.classList.add('table-features__sort-btn--icon');
				button.setAttribute('aria-label', `Sort by ${cell.textContent.trim()}`);
				cell.appendChild(button);
			} else {
				// Wrap the header text so the whole header is the sort target
				while (cell.firstChild) {
					button.appendChild(cell.firstChild);
				}
				cell.appendChild(button);
			}

			button.addEventListener('click', () => this.sortBy(index));
		});
	}

	/**
	 * Parse the configured filter-column list into zero-based column indices.
	 *
	 * Accepts spreadsheet letters (`A`, `B`, … `AA`) and 1-based numbers; an empty
	 * or absent list means every column gets a dropdown.
	 *
	 * @param {string|null} attr  The `data-table-filter-columns` value
	 * @returns {number[]} The distinct, in-range column indices to filter on
	 */
	parseFilterColumns(attr) {
		// Column count comes from the header row, else the first body row
		const columnCount = this.table.tHead?.rows[0]?.cells.length || this.rows[0]?.cells.length || 0;

		// No explicit list means offer a dropdown for every column
		const raw = (attr || '').trim();
		if (raw === '') {
			return Array.from({ length: columnCount }, (_, i) => i);
		}

		// Map each token to a column index, dropping unparseable / out-of-range ones
		const indices = raw
			.split(',')
			.map((token) => this.columnIndex(token.trim()))
			.filter((index) => index !== null && index >= 0 && index < columnCount);

		return [...new Set(indices)];
	}

	/**
	 * Resolve a single column token (spreadsheet letter or 1-based number) to a
	 * zero-based index, or null when it is neither.
	 *
	 * @param {string} token  A trimmed column reference
	 * @returns {number|null} The zero-based column index, or null
	 */
	columnIndex(token) {
		// A run of digits is a 1-based column position
		if (/^\d+$/.test(token)) {
			const position = parseInt(token, 10) - 1;

			return position >= 0 ? position : null;
		}

		// Spreadsheet letters: A=0, B=1, … Z=25, AA=26, …
		if (/^[A-Za-z]+$/.test(token)) {
			let index = 0;
			for (const character of token.toUpperCase()) {
				index = index * 26 + (character.charCodeAt(0) - 64);
			}

			return index - 1;
		}

		return null;
	}

	/**
	 * Build a labelled dropdown above the table for each configured filter column,
	 * populated with that column's distinct values.
	 */
	buildColumnFilters() {
		this.filterBar = document.createElement('div');
		this.filterBar.className = 'table-features__filters';

		const headerCells = this.table.tHead?.rows[0]?.cells;

		this.filterColumns.forEach((index) => {
			// Distinct, naturally-sorted non-empty values in this column
			const values = [
				...new Set(
					this.rows
						.map((row) => (row.cells[index]?.textContent || '').trim())
						.filter((value) => value !== ''),
				),
			].sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

			// Nothing to filter on — skip this column
			if (values.length === 0) {
				return;
			}

			const label = headerCells?.[index]?.textContent.trim() || `Column ${index + 1}`;
			const id = `${this.uid}-filter-${index}`;

			const field = document.createElement('label');
			field.className = 'table-features__filter';
			field.setAttribute('for', id);
			field.append(`${label} `);

			const select = document.createElement('select');
			select.id = id;
			select.className = 'form-control';

			const allOption = document.createElement('option');
			allOption.value = '';
			allOption.textContent = `All ${label}`;
			select.appendChild(allOption);

			values.forEach((value) => {
				const option = document.createElement('option');
				option.value = value;
				option.textContent = value;
				select.appendChild(option);
			});

			select.addEventListener('change', () => {
				if (select.value === '') {
					this.columnFilters.delete(index);
				} else {
					this.columnFilters.set(index, select.value);
				}
				this.page = 1;
				this.render();
			});

			field.append(select);
			this.filterBar.appendChild(field);
		});

		// Only insert the bar (above the table) if a column produced a dropdown
		if (this.filterBar.children.length > 0) {
			this.container.insertBefore(this.filterBar, this.tableAnchor);
		}
	}

	/**
	 * Whether a row passes the active search term and every active column filter.
	 *
	 * @param {HTMLTableRowElement} row  A body row
	 * @returns {boolean} True when the row should remain visible
	 */
	matchesFilters(row) {
		// Whole-row text match for the search box
		if (this.search && !row.textContent.toLowerCase().includes(this.search)) {
			return false;
		}

		// Each active column filter must match that column's cell exactly
		for (const [index, value] of this.columnFilters) {
			if ((row.cells[index]?.textContent || '').trim() !== value) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Switch the table to a stacked, per-row card layout below the configured
	 * breakpoint, labelling each cell with its column header.
	 */
	applyResponsiveStack() {
		const breakpoint = this.table.getAttribute('data-table-responsive-breakpoint') || 'phone';
		this.table.classList.add('table--stack', `table--stack-${breakpoint}`);

		// Stamp each body cell with its column header so the stacked card reads well
		const headerCells = this.table.tHead?.rows[0]?.cells;
		if (!headerCells) {
			return;
		}

		this.rows.forEach((row) => {
			Array.from(row.cells).forEach((cell, index) => {
				const label = headerCells[index]?.textContent.trim();
				if (label) {
					cell.setAttribute('data-label', label);
				}
			});
		});
	}

	/**
	 * Sort the rows by a column, toggling direction on repeat clicks.
	 *
	 * @param {number} index  The column index to sort by
	 */
	sortBy(index) {
		// Toggle direction when re-sorting the same column, else ascend
		if (this.sortIndex === index) {
			this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
		} else {
			this.sortIndex = index;
			this.sortDir = 'asc';
		}

		const direction = this.sortDir === 'asc' ? 1 : -1;
		const valueOf = (row) => (row.cells[index]?.textContent || '').trim();

		// Numeric when both cells parse as numbers, else natural string order
		this.rows.sort((a, b) => {
			const aValue = valueOf(a);
			const bValue = valueOf(b);
			const aNumber = Number.parseFloat(aValue);
			const bNumber = Number.parseFloat(bValue);
			const numeric = aValue !== '' && bValue !== '' && !Number.isNaN(aNumber) && !Number.isNaN(bNumber);

			if (numeric) {
				return (aNumber - bNumber) * direction;
			}

			return aValue.localeCompare(bValue, undefined, { numeric: true, sensitivity: 'base' }) * direction;
		});

		// Reflect the active sort on the headers
		this.headerCells.forEach((cell, cellIndex) => {
			cell.setAttribute(
				'aria-sort',
				cellIndex === index ? (this.sortDir === 'asc' ? 'ascending' : 'descending') : 'none',
			);
		});

		this.page = 1;
		this.render();
	}

	/**
	 * Apply the current search, sort order and page to the DOM.
	 */
	render() {
		// Filter by the search term and any active per-column filters
		this.filtered = this.rows.filter((row) => this.matchesFilters(row));

		const total = this.filtered.length;

		// Effective page size (the full set when "All" / not paginating)
		const pageSize = this.pageLength === Infinity ? Math.max(total, 1) : this.pageLength;
		const totalPages = Math.max(1, Math.ceil(total / pageSize));

		// Clamp the active page into range
		this.page = Math.min(Math.max(this.page, 1), totalPages);

		const start = (this.page - 1) * pageSize;
		const visible = new Set(this.filtered.slice(start, start + pageSize));

		// Re-append every row in sorted order, then toggle page visibility
		const fragment = document.createDocumentFragment();
		this.rows.forEach((row) => fragment.appendChild(row));
		this.tbody.appendChild(fragment);
		this.rows.forEach((row) => {
			row.hidden = !visible.has(row);
		});

		// Update the information line
		if (this.info) {
			const from = total === 0 ? 0 : start + 1;
			const to = Math.min(start + pageSize, total);
			this.info.textContent = `Showing ${from} to ${to} of ${total} ${total === 1 ? 'entry' : 'entries'}`;
		}

		// Rebuild the pager
		if (this.pager) {
			this.renderPager(totalPages);
		}
	}

	/**
	 * Render the pagination controls for the given page count.
	 *
	 * @param {number} totalPages  The total number of pages
	 */
	renderPager(totalPages) {
		// Only our own buttons are written here, so innerHTML reset is safe
		this.pager.innerHTML = '';

		// A single page needs no controls
		if (totalPages <= 1) {
			return;
		}

		const list = document.createElement('ul');
		list.className = 'pagination';

		const addItem = (label, page, { disabled = false, current = false, ariaLabel } = {}) => {
			const item = document.createElement('li');
			item.className = `page-item${disabled ? ' disabled' : ''}${current ? ' active' : ''}`;

			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'page-link';
			button.textContent = label;
			if (ariaLabel) {
				button.setAttribute('aria-label', ariaLabel);
			}
			if (current) {
				button.setAttribute('aria-current', 'page');
			}
			if (disabled) {
				button.disabled = true;
			} else {
				button.addEventListener('click', () => {
					this.page = page;
					this.render();
				});
			}

			item.appendChild(button);
			list.appendChild(item);
		};

		// Previous arrow
		addItem('‹', this.page - 1, { disabled: this.page <= 1, ariaLabel: 'Previous page' });

		// Windowed page numbers with ellipses
		pageWindow(this.page, totalPages).forEach((entry) => {
			if (entry === '…') {
				const item = document.createElement('li');
				item.className = 'page-item disabled';
				const span = document.createElement('span');
				span.className = 'page-link';
				span.textContent = '…';
				item.appendChild(span);
				list.appendChild(item);

				return;
			}

			addItem(String(entry), entry, { current: entry === this.page, ariaLabel: `Page ${entry}` });
		});

		// Next arrow
		addItem('›', this.page + 1, { disabled: this.page >= totalPages, ariaLabel: 'Next page' });

		this.pager.appendChild(list);
	}
}

/**
 * Initialize one feature-enhanced table.
 *
 * @param {Element} table  The matched `[data-table-features]` table
 */
export function initTableFeatures(table) {
	new TableFeatures(table);
}

/**
 * Public entry. The Alpine-lifecycle binding (scan, idempotent flag,
 * per-element init) lives in `lib/alpine-engine.js`, shared with sibling
 * engines.
 */
export function registerTableFeaturesEngine() {
	registerAlpineEngine({
		selector: TABLE_FEATURES_SELECTOR,
		boundAttr: 'tableFeaturesBound',
		init: initTableFeatures,
	});
}
