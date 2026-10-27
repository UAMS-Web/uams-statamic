// @vitest-environment jsdom

import Alpine from '@alpinejs/csp';
import { beforeAll, describe, expect, it } from 'vitest';

import { registerCalculationEngine } from './calculation-engine.js';
import { registerCheckboxRequiredGroup } from './checkbox-required-group.js';

/**
 * The real engine against the real `@alpinejs/csp` build. Alpine starts once for the file; each
 * test renders a fresh form, initializes its tree, and lets the engine bind the new inputs (the
 * engine's idempotency flag is per element, so a later registration binds only what is new).
 *
 * UAMS-Web/uams-statamic-archive#2884: a bracket-syntax formula naming `[get_param:x]` never filled, because the engine waited
 * for every referenced name to have a value in the form scope, and a URL parameter is never a
 * form-scope key.
 *
 * UAMS-Web/uams-statamic-archive#2913: a URL parameter overrode a form field with the same name, so `[qty]` read `?qty=` rather
 * than what the user typed. Each reference now reads its own source.
 *
 * UAMS-Web/uams-statamic-archive#2924: a field named `<handle>[]` (Checkboxes, a multiple Select) binds a list, and the engine
 * skipped any bracketed name, so its Dynamic Default Value never applied. The text result is now
 * written as the comma-separated option values it names.
 */
beforeAll(() => {
	window.Alpine = Alpine;
	Alpine.data('calcForm', () => ({ who: '', qty: '', out: '' }));
	Alpine.data('listForm', () => ({ who: '', pick: [], multi: [] }));
	registerCheckboxRequiredGroup(Alpine);
	Alpine.start();
});

/**
 * Render one form holding a calculated `out` input and return its reactive scope once the engine
 * has bound it.
 *
 * @param {{ formula: string, type?: 'math' | 'text', decimals?: string, search?: string, scope?: object }} options
 * @returns {Promise<Record<string, *>>}
 */
async function mount({ formula, type = 'text', decimals = '0', search = '', scope = {} }) {
	window.history.replaceState(null, '', `/form${search}`);

	document.body.innerHTML = `
		<form x-data="calcForm">
			<input name="who" x-model="who">
			<input name="qty" x-model="qty">
			<input name="out" x-model="out" readonly
				data-calculation-type="${type}"
				data-calculation-decimals="${decimals}">
		</form>`;

	const form = document.querySelector('form');
	form.querySelector('[name="out"]').dataset.calculationFormula = formula;

	Alpine.initTree(form);
	Object.assign(Alpine.$data(form), scope);

	registerCalculationEngine();
	await new Promise((resolve) => setTimeout(resolve, 0));

	return Alpine.$data(form);
}

describe('calculation engine, URL-parameter references', () => {
	it('fills a field reference (control)', async () => {
		const scope = await mount({ formula: 'Hi [who]', scope: { who: 'Ann' } });

		expect(scope.out).toBe('Hi Ann');
	});

	it('fills a text formula from the URL parameter on load', async () => {
		const scope = await mount({
			formula: '[get_param:editor_email_address]',
			search: '?editor_email_address=a%40b.c',
		});

		expect(scope.out).toBe('a@b.c');
	});

	it('fills a formula mixing a URL parameter and a field once both have values', async () => {
		const scope = await mount({ formula: '[get_param:greeting] [who]', search: '?greeting=Hello' });

		expect(scope.out).toBe('');

		scope.who = 'Ann';
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(scope.out).toBe('Hello Ann');
	});

	it('computes a bracket-syntax math formula using a URL parameter', async () => {
		const scope = await mount({
			formula: '[get_param:price] * [qty]',
			type: 'math',
			search: '?price=4',
			scope: { qty: '3' },
		});

		expect(scope.out).toBe('12');
	});

	it('leaves the field empty when the URL parameter is absent', async () => {
		const scope = await mount({ formula: '[get_param:editor_email_address]' });

		expect(scope.out).toBe('');
	});

	it('leaves the field empty when the URL parameter is present but empty', async () => {
		const scope = await mount({
			formula: '[get_param:editor_email_address]',
			search: '?editor_email_address=',
		});

		expect(scope.out).toBe('');
	});

	it('reads a plain field reference from the form when the URL carries an empty same-named parameter', async () => {
		// Before UAMS-Web/uams-statamic-archive#2913 the empty `?qty=` replaced the typed `3`: `Ann ` on main before UAMS-Web/uams-statamic-archive#2884, then
		// an empty field after it.
		const scope = await mount({ formula: '[who] [qty]', search: '?qty=', scope: { who: 'Ann', qty: '3' } });

		expect(scope.out).toBe('Ann 3');
	});

	it('reads a plain field reference from the form when the URL carries a same-named value', async () => {
		const scope = await mount({ formula: '[who] [qty]', search: '?qty=9', scope: { who: 'Ann', qty: '3' } });

		expect(scope.out).toBe('Ann 3');
	});

	it('reads a plain field reference in math from the form, not the URL', async () => {
		const scope = await mount({ formula: '[qty] * 2', type: 'math', search: '?qty=5', scope: { qty: '3' } });

		expect(scope.out).toBe('6');
	});

	it('reads [get_param:x] from the URL when a form field shares its name', async () => {
		const scope = await mount({ formula: '[get_param:qty]', search: '?qty=5', scope: { qty: '3' } });

		expect(scope.out).toBe('5');
	});

	it('keeps the two sources apart when one math formula reads both', async () => {
		const scope = await mount({
			formula: '[qty] + [get_param:qty]',
			type: 'math',
			search: '?qty=5',
			scope: { qty: '3' },
		});

		expect(scope.out).toBe('8');
	});

	it('waits for a plain field reference the URL alone would have filled', async () => {
		const scope = await mount({ formula: '[qty] * 2', type: 'math', search: '?qty=5' });

		expect(scope.out).toBe('');
	});

	it('treats [get_param:constructor] as unfilled when the URL lacks it (#2923)', async () => {
		const scope = await mount({ formula: 'Hi [get_param:constructor]', search: '?other=1' });

		expect(scope.out).toBe('');
	});

	it('treats a field reference named like an Object.prototype key as unfilled when no field has it (#2936)', async () => {
		const scope = await mount({ formula: 'Hi [constructor]' });
		const other = await mount({ formula: '[toString] and [who]', scope: { who: 'Ann' } });

		expect(scope.out).toBe('');
		expect(other.out).toBe('');
	});

	it('fills from a real field named like an Object.prototype key, and recomputes when it changes (#2936)', async () => {
		const scope = await mount({ formula: 'Hi [constructor]', scope: { constructor: 'Ann' } });

		expect(scope.out).toBe('Hi Ann');

		scope.constructor = 'Bea';
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(scope.out).toBe('Hi Bea');
	});

	it('still fills once a referenced field is added to the scope after the engine bound', async () => {
		const scope = await mount({ formula: 'Hi [late]' });

		expect(scope.out).toBe('');

		scope.late = 'Zed';
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(scope.out).toBe('Hi Zed');
	});

	it('still waits for a field reference when only the URL parameter has a value', async () => {
		const scope = await mount({ formula: '[get_param:price] * [qty]', type: 'math', search: '?price=4' });

		expect(scope.out).toBe('');
	});
});

/**
 * Render one form holding a Checkboxes group (`pick[]`, every box carrying the formula, as the
 * template emits it) and a multiple Select (`multi[]`), and return its reactive scope once the
 * engine has bound them.
 *
 * @param {{ formula: string, search?: string, scope?: object }} options
 * @returns {Promise<{ scope: Record<string, *>, form: HTMLFormElement }>}
 */
async function mountList({ formula, search = '', scope = {} }) {
	window.history.replaceState(null, '', `/form${search}`);

	// Required, as `checkboxes.antlers.html` renders a field with the `required` rule: every box
	// carries `required` inside the group component that clears it once one is checked
	const box = (value) =>
		`<input type="checkbox" name="pick[]" value="${value}" x-model="pick" required data-calculation-type="text" data-calculation-decimals="">`;

	document.body.innerHTML = `
		<form x-data="listForm">
			<input name="who" x-model="who">
			<div x-data="checkboxRequiredGroup" @change="sync">
				<input type="hidden" name="pick[]">
				${box('a')}${box('b')}${box('c')}
			</div>
			<select name="multi[]" multiple x-model="multi" data-calculation-type="text" data-calculation-decimals="">
				<option value="a">A</option><option value="b">B</option><option value="c">C</option>
			</select>
		</form>`;

	const form = document.querySelector('form');
	for (const element of form.querySelectorAll('[data-calculation-type]')) {
		element.dataset.calculationFormula = formula;
	}

	Alpine.initTree(form);
	Object.assign(Alpine.$data(form), scope);

	registerCalculationEngine();
	await new Promise((resolve) => setTimeout(resolve, 0));

	return { scope: Alpine.$data(form), form };
}

/**
 * The values of the checked boxes and the selected options, as the browser shows them.
 *
 * @param {HTMLFormElement} form
 */
function shown(form) {
	return {
		checked: [...form.querySelectorAll('input[type="checkbox"]:checked')].map((box) => box.value),
		selected: [...form.querySelector('select').selectedOptions].map((option) => option.value),
	};
}

/**
 * How many boxes still carry `required`.
 *
 * @param {HTMLFormElement} form
 */
function requiredBoxes(form) {
	return [...form.querySelectorAll('input[type="checkbox"]')].filter((box) => box.required).length;
}

describe('calculation engine, list fields', () => {
	it('checks the boxes and selects the options a comma-separated result names', async () => {
		const { scope, form } = await mountList({ formula: '[get_param:pick]', search: '?pick=a,c' });

		expect(scope.pick).toEqual(['a', 'c']);
		expect(scope.multi).toEqual(['a', 'c']);
		expect(shown(form)).toEqual({ checked: ['a', 'c'], selected: ['a', 'c'] });
	});

	it('names a single value as a one-item list', async () => {
		const { scope, form } = await mountList({ formula: '[get_param:pick]', search: '?pick=b' });

		expect(scope.pick).toEqual(['b']);
		expect(shown(form)).toEqual({ checked: ['b'], selected: ['b'] });
	});

	it('trims each value and drops empty ones', async () => {
		const { scope } = await mountList({ formula: '[get_param:pick]', search: '?pick=%20a%20,,%20c' });

		expect(scope.pick).toEqual(['a', 'c']);
	});

	it('leaves the list empty while the formula has no value', async () => {
		const { scope, form } = await mountList({ formula: '[get_param:pick]' });

		expect(scope.pick).toEqual([]);
		expect(shown(form)).toEqual({ checked: [], selected: [] });

		// Control for the required case below: nothing checked, so every box stays required
		expect(requiredBoxes(form)).toBe(3);
	});

	it('clears required from a required group once it checks a box', async () => {
		const { form } = await mountList({ formula: '[get_param:pick]', search: '?pick=b' });
		await new Promise((resolve) => setTimeout(resolve, 0));

		// Without it, the browser would refuse to submit until every box was ticked
		expect(shown(form).checked).toEqual(['b']);
		expect(requiredBoxes(form)).toBe(0);
	});

	it('recomputes from a field reference, until the visitor changes a box', async () => {
		const { scope, form } = await mountList({ formula: '[who]', scope: { who: 'a' } });

		expect(scope.pick).toEqual(['a']);

		scope.who = 'b,c';
		await new Promise((resolve) => setTimeout(resolve, 0));
		expect(scope.pick).toEqual(['b', 'c']);

		// A click on any box is the visitor's own choice, so the engine stops writing the field
		form.querySelector('input[type="checkbox"][value="a"]').click();
		await new Promise((resolve) => setTimeout(resolve, 0));
		scope.who = 'c';
		await new Promise((resolve) => setTimeout(resolve, 0));

		expect(scope.pick).toEqual(['b', 'c', 'a']);
	});
});
