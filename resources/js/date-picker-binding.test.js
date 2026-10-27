// @vitest-environment jsdom

import { DatePickerRoot } from 'reka-ui';
import { createApp, h } from 'vue';
import { describe, expect, it } from 'vitest';

import { toPickerValue } from './date-boundary-engine.js';

/**
 * Runtime regression test for the custom Date picker value binding
 * (`resources/js/components/fieldtypes/DateFieldtype.vue`, UAMS-Web/uams-statamic-archive#193).
 *
 * The Statamic `DatePicker`/`DateRangePicker` forward `modelValue` straight to
 * reka-ui's `DatePickerRoot`, which calls DateValue methods (`.copy()`) on it at
 * setup. Binding the raw stored ISO STRING (as the first cut of the component
 * did) therefore throws `TypeError: <string>.copy is not a function` and the
 * picker never renders. `toPickerValue()` parses the stored string to a reka
 * `CalendarDate` (a real DateValue), which mounts cleanly — this test pins that
 * contract so the string-binding regression cannot come back silently.
 */
function mountDatePickerRoot(modelValue) {
	const host = document.createElement('div');
	const app = createApp({
		render() {
			return h(DatePickerRoot, { modelValue });
		},
	});

	try {
		app.mount(host);
	} finally {
		app.unmount();
	}
}

describe('reka DatePickerRoot value binding', () => {
	it('throws when bound a raw ISO string (the original blocker)', () => {
		expect(() => mountDatePickerRoot('2026-01-15')).toThrow();
	});

	it('mounts cleanly when bound the parsed toPickerValue (the fix)', () => {
		expect(() => mountDatePickerRoot(toPickerValue('2026-01-15', false))).not.toThrow();
	});

	it('mounts cleanly for an empty (null) value', () => {
		expect(() => mountDatePickerRoot(toPickerValue(null, false))).not.toThrow();
	});
});
