/**
 * Advances UAMS Counter List page builder block items each second.
 *
 * Reads the server-rendered start instant (`data-start-date`, ISO 8601 with
 * offset) and rate (`data-rate`, per-second) and replaces the value/append
 * spans. A `data-counter-initialized` flag prevents stacking intervals when
 * the same item is matched by repeat initialization.
 */

function initCounters() {
	document.querySelectorAll('.counter-list .item').forEach(function (item) {
		if (item.dataset.counterInitialized === 'true') return;
		item.dataset.counterInitialized = 'true';

		const startDate = new Date(item.getAttribute('data-start-date'));
		const rate = parseFloat(item.getAttribute('data-rate'));
		const unitSingular = item.getAttribute('data-unit-singular');
		const unitPlural = item.getAttribute('data-unit-plural');
		const valueEl = item.querySelector('.count .value');
		const appendEl = item.querySelector('.count .append');

		if (!valueEl || !appendEl || isNaN(startDate.getTime()) || isNaN(rate)) return;

		function recalculateCount() {
			const count = ((Date.now() - startDate.getTime()) / 1000) * rate;
			if (count < 1) {
				valueEl.textContent = '<1';
				appendEl.textContent = unitSingular;
			} else {
				valueEl.textContent = Math.floor(count).toLocaleString('en');
				appendEl.textContent = unitPlural;
			}
		}

		recalculateCount();
		setInterval(recalculateCount, 1000);
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initCounters);
} else {
	initCounters();
}
