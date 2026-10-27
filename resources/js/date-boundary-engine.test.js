import { CalendarDate } from '@internationalized/date';
import { describe, expect, it } from 'vitest';

import {
	applyOffset,
	firstOffsetRow,
	isDateUnavailable,
	laterOf,
	normalizeAllowedDays,
	normalizeEmit,
	normalizeExceptions,
	parseToCalendarDate,
	resolveBoundary,
	stringifyDate,
	toPickerValue,
} from './date-boundary-engine.js';

const iso = (date) => (date ? date.toString() : null);

describe('parseToCalendarDate', () => {
	it('parses a plain ISO date', () => {
		expect(iso(parseToCalendarDate('2026-01-15'))).toBe('2026-01-15');
	});

	it('takes the date part of an ISO datetime (with Z)', () => {
		expect(iso(parseToCalendarDate('2026-01-15T09:30:00.000Z'))).toBe('2026-01-15');
	});

	it('passes a CalendarDate through unchanged', () => {
		const d = new CalendarDate(2026, 3, 4);

		expect(parseToCalendarDate(d)).toBe(d);
	});

	it('returns null for non-strings and unparseable values', () => {
		expect(parseToCalendarDate(null)).toBeNull();
		expect(parseToCalendarDate(1234)).toBeNull();
		expect(parseToCalendarDate('not-a-date')).toBeNull();
		expect(parseToCalendarDate('2026-13-40')).toBeNull();
	});
});

describe('firstOffsetRow', () => {
	it('returns the first row of a stacked-grid array', () => {
		expect(firstOffsetRow([{ operator: 'add', number: 1, unit: 'day' }])).toEqual({
			operator: 'add',
			number: 1,
			unit: 'day',
		});
	});

	it('accepts a bare offset object', () => {
		const row = { operator: 'subtract', number: 2, unit: 'week' };

		expect(firstOffsetRow(row)).toBe(row);
	});

	it('returns null for empty / missing / malformed grids', () => {
		expect(firstOffsetRow([])).toBeNull();
		expect(firstOffsetRow(null)).toBeNull();
		expect(firstOffsetRow({})).toBeNull();
	});
});

describe('applyOffset', () => {
	const base = new CalendarDate(2026, 1, 15);

	it('adds each unit', () => {
		expect(iso(applyOffset(base, { operator: 'add', number: 3, unit: 'day' }))).toBe('2026-01-18');
		expect(iso(applyOffset(base, { operator: 'add', number: 2, unit: 'week' }))).toBe('2026-01-29');
		expect(iso(applyOffset(base, { operator: 'add', number: 1, unit: 'month' }))).toBe('2026-02-15');
		expect(iso(applyOffset(base, { operator: 'add', number: 1, unit: 'year' }))).toBe('2027-01-15');
	});

	it('subtracts', () => {
		expect(iso(applyOffset(base, { operator: 'subtract', number: 5, unit: 'day' }))).toBe(
			'2026-01-10',
		);
	});

	it('clamps a month/year offset to the end of the target month (parity with the PHP server engine)', () => {
		// reka CalendarDate clamps a short-month anchor (Jan 31 + 1 month =>
		// 2026-02-28); the PHP engine must match (it disables Carbon's overflow).
		const jan31 = new CalendarDate(2026, 1, 31);
		const feb29 = new CalendarDate(2028, 2, 29);
		const mar31 = new CalendarDate(2026, 3, 31);

		expect(iso(applyOffset(jan31, { operator: 'add', number: 1, unit: 'month' }))).toBe('2026-02-28');
		expect(iso(applyOffset(feb29, { operator: 'add', number: 1, unit: 'year' }))).toBe('2029-02-28');
		expect(iso(applyOffset(mar31, { operator: 'subtract', number: 1, unit: 'month' }))).toBe('2026-02-28');
	});

	it('is a no-op for zero / negative / unknown / missing', () => {
		expect(iso(applyOffset(base, { operator: 'add', number: 0, unit: 'day' }))).toBe('2026-01-15');
		expect(iso(applyOffset(base, { operator: 'add', number: -2, unit: 'day' }))).toBe('2026-01-15');
		expect(iso(applyOffset(base, { operator: 'add', number: 2, unit: 'fortnight' }))).toBe(
			'2026-01-15',
		);
		expect(iso(applyOffset(base, null))).toBe('2026-01-15');
		expect(applyOffset(null, { operator: 'add', number: 1, unit: 'day' })).toBeNull();
	});
});

describe('resolveBoundary', () => {
	const todayDate = new CalendarDate(2026, 6, 1);

	it('is null when disabled', () => {
		expect(resolveBoundary({ type: 'none' })).toBeNull();
		expect(resolveBoundary({})).toBeNull();
	});

	it('resolves a custom date', () => {
		expect(iso(resolveBoundary({ type: 'custom', customValue: '2026-12-25' }))).toBe('2026-12-25');
	});

	it('resolves today with an offset', () => {
		expect(iso(resolveBoundary({ type: 'today', todayDate }))).toBe('2026-06-01');
		expect(
			iso(
				resolveBoundary({
					type: 'today',
					todayDate,
					offset: { operator: 'add', number: 1, unit: 'week' },
				}),
			),
		).toBe('2026-06-08');
	});

	it('resolves a referenced field value with an offset', () => {
		expect(
			iso(
				resolveBoundary({
					type: 'field',
					fieldValue: '2026-06-10T00:00:00Z',
					offset: { operator: 'subtract', number: 2, unit: 'day' },
				}),
			),
		).toBe('2026-06-08');
	});

	it('is null when the referenced field is empty', () => {
		expect(resolveBoundary({ type: 'field', fieldValue: null })).toBeNull();
	});
});

describe('normalizeAllowedDays', () => {
	it('returns null when every weekday is allowed', () => {
		expect(normalizeAllowedDays(['0', '1', '2', '3', '4', '5', '6'])).toBeNull();
		expect(normalizeAllowedDays([])).toBeNull();
		expect(normalizeAllowedDays(undefined)).toBeNull();
	});

	it('returns the restricted set as strings', () => {
		expect(normalizeAllowedDays([1, 2, 3, 4, 5])).toEqual(['1', '2', '3', '4', '5']);
	});
});

describe('normalizeExceptions', () => {
	it('turns a single date into a one-day range', () => {
		const [range] = normalizeExceptions([{ type: 'single_date', item: '2026-07-04' }]);

		expect(iso(range.start)).toBe('2026-07-04');
		expect(iso(range.end)).toBe('2026-07-04');
	});

	it('keeps a date range', () => {
		const [range] = normalizeExceptions([
			{ type: 'date_range', item: { start: '2026-07-01', end: '2026-07-07' } },
		]);

		expect(iso(range.start)).toBe('2026-07-01');
		expect(iso(range.end)).toBe('2026-07-07');
	});

	it('drops malformed rows', () => {
		expect(
			normalizeExceptions([
				{ type: 'single_date', item: 'nope' },
				{ type: 'date_range', item: { start: '2026-07-01' } },
				{ type: 'mystery' },
				null,
			]),
		).toEqual([]);
	});

	it('returns [] for non-arrays', () => {
		expect(normalizeExceptions(null)).toEqual([]);
	});
});

describe('isDateUnavailable', () => {
	// 2026-06-01 is a Monday; 2026-06-06 is a Saturday; 2026-06-07 is a Sunday.
	const monday = new CalendarDate(2026, 6, 1);
	const saturday = new CalendarDate(2026, 6, 6);

	it('disables weekdays outside the allowed set (weekends off)', () => {
		const weekdaysOnly = { allowedDays: ['1', '2', '3', '4', '5'] };

		expect(isDateUnavailable(monday, weekdaysOnly)).toBe(false);
		expect(isDateUnavailable(saturday, weekdaysOnly)).toBe(true);
	});

	it('disables dates inside an exception range (inclusive) and the endpoints', () => {
		const exceptions = [{ start: new CalendarDate(2026, 6, 1), end: new CalendarDate(2026, 6, 3) }];

		expect(isDateUnavailable(new CalendarDate(2026, 6, 1), { exceptions })).toBe(true);
		expect(isDateUnavailable(new CalendarDate(2026, 6, 2), { exceptions })).toBe(true);
		expect(isDateUnavailable(new CalendarDate(2026, 6, 3), { exceptions })).toBe(true);
		expect(isDateUnavailable(new CalendarDate(2026, 6, 4), { exceptions })).toBe(false);
	});

	it('is available with no constraints and false for non-CalendarDate', () => {
		expect(isDateUnavailable(monday, {})).toBe(false);
		expect(isDateUnavailable(null, { allowedDays: ['1'] })).toBe(false);
	});
});

describe('laterOf', () => {
	it('returns the later of two dates', () => {
		const a = new CalendarDate(2026, 6, 1);
		const b = new CalendarDate(2026, 6, 10);

		expect(iso(laterOf(a, b))).toBe('2026-06-10');
		expect(iso(laterOf(b, a))).toBe('2026-06-10');
	});

	it('ignores nulls', () => {
		const a = new CalendarDate(2026, 6, 1);

		expect(iso(laterOf(a, null))).toBe('2026-06-01');
		expect(iso(laterOf(null, a))).toBe('2026-06-01');
		expect(laterOf(null, null)).toBeNull();
	});

	it('floors a custom earliest at today (the exclude_before_today case)', () => {
		// earliest_date_type=custom (past) + exclude_before_today ⇒ effective earliest is today.
		const customPast = resolveBoundary({ type: 'custom', customValue: '2020-01-01' });
		const todayFloor = new CalendarDate(2026, 6, 1);

		expect(iso(laterOf(customPast, todayFloor))).toBe('2026-06-01');
	});
});

describe('toPickerValue', () => {
	it('parses a single date-only value to a CalendarDate', () => {
		expect(iso(toPickerValue('2026-01-15', false))).toBe('2026-01-15');
		expect(toPickerValue('2026-01-15', false)).toBeInstanceOf(CalendarDate);
	});

	it('parses a range to a start/end pair of CalendarDates', () => {
		const range = toPickerValue({ start: '2026-01-01', end: '2026-01-31' }, true);

		expect(iso(range.start)).toBe('2026-01-01');
		expect(iso(range.end)).toBe('2026-01-31');
	});

	it('is null for empty / partial values', () => {
		expect(toPickerValue(null, false)).toBeNull();
		expect(toPickerValue('', false)).toBeNull();
		expect(toPickerValue({ start: '2026-01-01' }, true)).toBeNull();
		expect(toPickerValue(null, true)).toBeNull();
	});

	it('round-trips losslessly with normalizeEmit (the value contract)', () => {
		expect(normalizeEmit(toPickerValue('2026-01-15', false))).toBe('2026-01-15');
		expect(normalizeEmit(toPickerValue({ start: '2026-01-01', end: '2026-01-31' }, true))).toEqual({
			start: '2026-01-01',
			end: '2026-01-31',
		});
	});
});

describe('normalizeEmit / stringifyDate', () => {
	it('passes strings and null through', () => {
		expect(normalizeEmit('2026-01-15')).toBe('2026-01-15');
		expect(normalizeEmit(null)).toBeNull();
	});

	it('stringifies a DateValue-like emit', () => {
		expect(stringifyDate(new CalendarDate(2026, 1, 15))).toBe('2026-01-15');
		expect(normalizeEmit(new CalendarDate(2026, 1, 15))).toBe('2026-01-15');
	});

	it('stringifies both ends of a range emit', () => {
		expect(
			normalizeEmit({ start: new CalendarDate(2026, 1, 1), end: new CalendarDate(2026, 1, 31) }),
		).toEqual({ start: '2026-01-01', end: '2026-01-31' });
	});
});
