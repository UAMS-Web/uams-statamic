<template>
	<div class="custom-date-fieldtype">
		<component
			:is="isRange ? 'DateRangePicker' : 'DatePicker'"
			:model-value="pickerValue"
			:min="minBoundary"
			:max="maxBoundary"
			:is-date-unavailable="isDateUnavailable"
			granularity="day"
			:required="isRequired"
			:read-only="readOnly"
			@update:model-value="handleUpdate"
		/>

		<ErrorMessage
			v-if="constraintWarning"
			:text="constraintWarning"
		/>
	</div>
</template>

<script>
/**
 * Custom Date fieldtype (App\Fieldtypes\Date, handle `custom_date`).
 *
 * Renders only for DATE-ONLY `date` fields that configure a Boundaries & Limits
 * constraint — App\Fieldtypes\Date::boundariesConfigured() gates the component()
 * override AND excludes `time_enabled` fields, so the value is always a plain
 * `Y-m-d` string (single) or `{ start, end }` of them (range). Every other date
 * field keeps the base picker. It re-implements the picker via the exported
 * DatePicker/DateRangePicker primitives and applies the config the base picker
 * ignores:
 *
 *   - earliest/latest min-max for each boundary type (none / custom / today /
 *     field), each with an optional add/subtract offset; the `field` type reads
 *     a sibling date field live from the publish context so a relative boundary
 *     tracks the referenced field's value; `earliest_date_exclude_before_today`
 *     floors the earliest boundary at today (+ its offset);
 *   - allowed `days_of_week` (weekdays outside the set are unavailable);
 *   - `date_exceptions` blackout single dates and ranges.
 *
 * The picker's `model-value` MUST be a reka DateValue (the primitive calls
 * `.copy()`/`.compare()` on it), so the stored string is parsed to a CalendarDate
 * via {@link toPickerValue}; emits are stringified back with {@link normalizeEmit}
 * (`CalendarDate.toString()` === the stored `Y-m-d`), so the value contract is
 * preserved and server-side `process()`/`augment()` are unaffected.
 *
 * The date arithmetic lives in `resources/js/date-boundary-engine.js` (pure,
 * unit-tested). These client-side constraints are a CONVENIENCE that steers the
 * picker; they are NOT yet backstopped server-side (a follow-up adds boundary
 * validation to App\Fieldtypes\Date), so the `constraintWarning` flags — but
 * does not itself reject — an out-of-range value.
 */
import { getLocalTimeZone, today } from '@internationalized/date';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { DatePicker, DateRangePicker, ErrorMessage, publishContextKey } from '@statamic/cms/ui';

import {
	applyOffset,
	firstOffsetRow,
	isDateUnavailable as computeUnavailable,
	laterOf,
	normalizeAllowedDays,
	normalizeEmit,
	normalizeExceptions,
	parseToCalendarDate,
	resolveBoundary,
	toPickerValue,
} from '../../date-boundary-engine.js';

export default {
	name: 'DateFieldtype',

	components: {
		DatePicker,
		DateRangePicker,
		ErrorMessage,
	},

	mixins: [Fieldtype],

	inject: {
		publishContext: { from: publishContextKey, default: null },
	},

	computed: {
		/** Blueprint field config, with `meta.config` merged in as a fallback. */
		fc() {
			const metaConfig =
				this.meta && typeof this.meta.config === 'object' ? this.meta.config : {};

			return { ...metaConfig, ...(this.config || {}) };
		},

		isRange() {
			return this.fc.mode === 'range';
		},

		isRequired() {
			return Array.isArray(this.fc.validate) && this.fc.validate.includes('required');
		},

		/** The stored value parsed to the reka DateValue the picker requires. */
		pickerValue() {
			return toPickerValue(this.value, this.isRange);
		},

		allowedDays() {
			return normalizeAllowedDays(this.fc.days_of_week);
		},

		exceptions() {
			return normalizeExceptions(this.fc.date_exceptions);
		},

		minBoundary() {
			return this.resolveBoundaryFor('earliest');
		},

		maxBoundary() {
			return this.resolveBoundaryFor('latest');
		},

		/** A warning shown when either endpoint of the value is an unavailable date. */
		constraintWarning() {
			const parts = this.isRange ? [this.value?.start, this.value?.end] : [this.value];

			const anyUnavailable = parts.some((part) => {
				const date = parseToCalendarDate(part);

				return date && this.isDateUnavailable(date);
			});

			return anyUnavailable
				? this.__('This date is outside the allowed dates.')
				: null;
		},
	},

	methods: {
		/** Resolve the `earliest`/`latest` boundary from config + live context. */
		resolveBoundaryFor(which) {
			const type = this.fc[`${which}_date_type`] || 'none';
			const handle = this.fc[`${which}_date_field`];
			const todayDate = today(getLocalTimeZone());

			const typeBoundary = resolveBoundary({
				type,
				customValue: this.fc[`${which}_date_custom`],
				fieldValue: handle ? this.siblingValue(handle) : null,
				todayDate,
				offset: firstOffsetRow(
					type === 'today'
						? this.fc[`${which}_date_today_offset`]
						: this.fc[`${which}_date_field_offset`],
				),
			});

			// `earliest_date_exclude_before_today` floors the earliest boundary at
			// today (+ its offset), whichever is later than the custom/field date.
			if (which === 'earliest' && this.fc.earliest_date_exclude_before_today === true) {
				const floor = applyOffset(todayDate, firstOffsetRow(this.fc.earliest_date_today_offset));

				return laterOf(typeBoundary, floor);
			}

			return typeBoundary;
		},

		/** Read a sibling field's value from the publish context (reactively). */
		siblingValue(handle) {
			const context = this.publishContext;

			if (!context) {
				return null;
			}

			const values = context.values?.value ?? context.values;

			return values && typeof values === 'object' ? (values[handle] ?? null) : null;
		},

		/** Reka `isDateUnavailable` matcher for days_of_week + date_exceptions. */
		isDateUnavailable(dateValue) {
			return computeUnavailable(parseToCalendarDate(dateValue), {
				allowedDays: this.allowedDays,
				exceptions: this.exceptions,
			});
		},

		/** Normalize a picker emit to the base value contract before updating. */
		handleUpdate(next) {
			this.update(normalizeEmit(next));
		},
	},
};
</script>
