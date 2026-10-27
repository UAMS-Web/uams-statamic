/**
 * Date boundary engine — the pure date arithmetic behind the custom Date
 * fieldtype (`resources/js/components/fieldtypes/DateFieldtype.vue`, UAMS-Web/uams-statamic-archive#193).
 *
 * App\Fieldtypes\Date adds a Boundaries & Limits config section the base CP
 * picker ignores. This module turns that config into the constraints the picker
 * needs — an effective earliest/latest `CalendarDate` and an availability
 * predicate for `days_of_week` + `date_exceptions` — with no Vue or Statamic
 * dependency, so the logic is unit-testable in isolation (see
 * `date-boundary-engine.test.js`). Everything is day-granularity: boundaries and
 * blackouts constrain the calendar day; time-of-day is out of scope here.
 *
 * @typedef {{ operator?: string, number?: (number|string), unit?: string }} OffsetRow
 */
import { CalendarDate, getLocalTimeZone, parseDate } from '@internationalized/date';

/** Offset units (from the config grid) → reka duration keys. */
export const OFFSET_UNIT_TO_DURATION_KEY = {
	day: 'days',
	week: 'weeks',
	month: 'months',
	year: 'years',
};

/**
 * Parse an ISO date/datetime string (e.g. `2026-01-15`, `2026-01-15T09:30:00Z`)
 * to a day-granularity CalendarDate, or null when it has no leading `Y-m-d`.
 *
 * @param {*} value
 * @returns {CalendarDate|null}
 */
export function parseToCalendarDate(value) {
	if (value instanceof CalendarDate) {
		return value;
	}

	if (typeof value !== 'string') {
		return null;
	}

	const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

	if (!match) {
		return null;
	}

	try {
		return parseDate(`${match[1]}-${match[2]}-${match[3]}`);
	} catch {
		return null;
	}
}

/**
 * The single offset row from a stacked-grid (`max_rows: 1`) offset config, which
 * may arrive as a one-element array or a bare object, or null when unset.
 *
 * @param {*} grid
 * @returns {OffsetRow|null}
 */
export function firstOffsetRow(grid) {
	if (Array.isArray(grid)) {
		return grid.length > 0 ? grid[0] : null;
	}

	if (grid && typeof grid === 'object' && 'operator' in grid) {
		return grid;
	}

	return null;
}

/**
 * Add or subtract an offset row from a CalendarDate. A missing/zero/negative
 * amount or an unknown unit is a no-op (returns the input date).
 *
 * @param {CalendarDate|null} date
 * @param {OffsetRow|null} offset
 * @returns {CalendarDate|null}
 */
export function applyOffset(date, offset) {
	if (!date || !offset) {
		return date;
	}

	const durationKey = OFFSET_UNIT_TO_DURATION_KEY[offset.unit];
	const amount = Number(offset.number);

	if (!durationKey || !Number.isFinite(amount) || amount <= 0) {
		return date;
	}

	const duration = { [durationKey]: amount };

	return offset.operator === 'subtract' ? date.subtract(duration) : date.add(duration);
}

/**
 * The effective boundary CalendarDate for one side (`earliest`/`latest`), or
 * null when the boundary is disabled or unresolvable.
 *
 * @param {object}  spec
 * @param {string}  [spec.type]        none | custom | today | field
 * @param {*}       [spec.customValue] ISO string for the `custom` type
 * @param {*}       [spec.fieldValue]  the referenced sibling field's value for `field`
 * @param {CalendarDate} [spec.todayDate] today's date for the `today` type (injected for testability)
 * @param {OffsetRow|null} [spec.offset]  add/subtract offset (today/field types)
 * @returns {CalendarDate|null}
 */
export function resolveBoundary({ type, customValue, fieldValue, todayDate, offset } = {}) {
	if (!type || type === 'none') {
		return null;
	}

	if (type === 'custom') {
		return parseToCalendarDate(customValue);
	}

	if (type === 'today') {
		return todayDate ? applyOffset(todayDate, offset) : null;
	}

	if (type === 'field') {
		const referenced = parseToCalendarDate(fieldValue);

		return referenced ? applyOffset(referenced, offset) : null;
	}

	return null;
}

/**
 * The later of two CalendarDates, ignoring nulls (or null when both are null).
 * Used to floor the earliest boundary at "today (+offset)" when
 * `earliest_date_exclude_before_today` is set alongside a custom/field date.
 *
 * @param {CalendarDate|null} a
 * @param {CalendarDate|null} b
 * @returns {CalendarDate|null}
 */
export function laterOf(a, b) {
	if (!a) {
		return b || null;
	}

	if (!b) {
		return a;
	}

	return a.compare(b) >= 0 ? a : b;
}

/**
 * The stored value parsed to the reka DateValue shape the picker's `model-value`
 * requires — a day-granularity CalendarDate (single) or `{ start, end }` of
 * CalendarDates (range), or null when empty/unparseable. The Statamic
 * DatePicker/DateRangePicker forward `modelValue` straight to reka, which calls
 * DateValue methods (`.copy()`, `.compare()`) on it — so a raw string must never
 * be bound. Only date-only fields route to the custom component (its component()
 * gate excludes `time_enabled`), so a CalendarDate round-trips losslessly with
 * {@link normalizeEmit} (`CalendarDate.toString()` === the stored `Y-m-d`).
 *
 * @param {*} value
 * @param {boolean} isRange
 * @returns {CalendarDate|{start: CalendarDate, end: CalendarDate}|null}
 */
export function toPickerValue(value, isRange) {
	if (isRange) {
		const start = parseToCalendarDate(value?.start);
		const end = parseToCalendarDate(value?.end);

		return start && end ? { start, end } : null;
	}

	return parseToCalendarDate(value);
}

/**
 * Normalize the `days_of_week` config to an array of allowed weekday keys
 * ('0' Sunday … '6' Saturday), or null when every weekday is allowed (no
 * restriction — empty, absent, or the full seven).
 *
 * @param {*} days
 * @returns {string[]|null}
 */
export function normalizeAllowedDays(days) {
	if (!Array.isArray(days) || days.length === 0 || days.length >= 7) {
		return null;
	}

	return days.map(String);
}

/**
 * Normalize the `date_exceptions` replicator value to a list of inclusive
 * blackout ranges (a single date becomes a one-day range). Unparseable or
 * malformed rows are dropped.
 *
 * @param {*} rows
 * @returns {Array<{start: CalendarDate, end: CalendarDate}>}
 */
export function normalizeExceptions(rows) {
	if (!Array.isArray(rows)) {
		return [];
	}

	return rows
		.map((row) => {
			if (row?.type === 'single_date') {
				const date = parseToCalendarDate(row.item);

				return date ? { start: date, end: date } : null;
			}

			if (row?.type === 'date_range' && row.item) {
				const start = parseToCalendarDate(row.item.start);
				const end = parseToCalendarDate(row.item.end);

				return start && end ? { start, end } : null;
			}

			return null;
		})
		.filter(Boolean);
}

/**
 * Whether a calendar day is unavailable — its weekday is not allowed, or it
 * falls within a blackout exception range.
 *
 * @param {CalendarDate|null} date
 * @param {object}  constraints
 * @param {string[]|null} [constraints.allowedDays]
 * @param {Array<{start: CalendarDate, end: CalendarDate}>} [constraints.exceptions]
 * @param {string}  [constraints.timeZone]
 * @returns {boolean}
 */
export function isDateUnavailable(date, { allowedDays = null, exceptions = [], timeZone } = {}) {
	if (!(date instanceof CalendarDate)) {
		return false;
	}

	if (allowedDays) {
		const weekday = date.toDate(timeZone || getLocalTimeZone()).getDay();

		if (!allowedDays.includes(String(weekday))) {
			return true;
		}
	}

	return exceptions.some((range) => date.compare(range.start) >= 0 && date.compare(range.end) <= 0);
}

/**
 * Coerce a picker emit (a reka DateValue, an ISO string, or null) back to the
 * base value contract — an ISO string (or null).
 *
 * @param {*} value
 * @returns {*}
 */
export function stringifyDate(value) {
	if (value == null || typeof value === 'string') {
		return value;
	}

	return typeof value.toString === 'function' ? value.toString() : value;
}

/**
 * Normalize a picker emit for either mode: a single value → ISO string; a range
 * `{ start, end }` → each side stringified.
 *
 * @param {*} value
 * @returns {*}
 */
export function normalizeEmit(value) {
	if (value == null) {
		return value;
	}

	if (typeof value === 'object' && 'start' in value) {
		return { start: stringifyDate(value.start), end: stringifyDate(value.end) };
	}

	return stringifyDate(value);
}
