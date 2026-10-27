// @vitest-environment jsdom
// `fakepath` is the placeholder path browsers report for a chosen file (UAMS-Web/uams-statamic-archive#2874).
// cspell:ignore fakepath

import { beforeEach, describe, expect, it } from 'vitest';

import { registerReplicatorField } from './replicator-field.js';

/**
 * Instantiate the `replicatorField` Alpine component object (without a real Alpine runtime) by
 * capturing the factory `registerReplicatorField` registers, then invoking it.
 *
 * @returns {object} A fresh component state object.
 */
function makeComponent() {
	let factory;
	// The stub models the Alpine surface the component actually uses. `initTree` is load-bearing:
	// the min-sets auto-fill runs during Alpine's own tree walk, before its mutation observer is
	// live, so a set added there is only initialized because the component asks for it (UAMS-Web/uams-statamic-archive#2056).
	registerReplicatorField({
		data: (_name, f) => { factory = f; },
		initTree: (el) => {
			initTreeCalls.push(el);

			// Opt-in, so the tests that need a nested replicator left unmounted keep it that way.
			if (mountNestedOnInitTree) {
				el.querySelectorAll('[data-replicator-handle]').forEach((root) => mountOn(root));
			}
		},
		$data: (el) => mountedComponents.get(el),
	});

	return factory();
}

/**
 * Elements passed to the Alpine stub's `initTree`, reset before each test.
 *
 * @type {Array<Element>}
 */
let initTreeCalls = [];

/**
 * Components mounted by `mountOn()`, keyed by their root, which is what the Alpine stub's `$data`
 * resolves.
 *
 * @type {Map<Element, object>}
 */
let mountedComponents = new Map();

/**
 * Whether the Alpine stub's `initTree` mounts the replicators inside the element it is given, the
 * way the real `initTree` does.
 *
 * @type {boolean}
 */
let mountNestedOnInitTree = false;

/**
 * Build a replicator field DOM (one `contact` set type, empty) inside a form.
 *
 * @param {number} minSets  The `min_sets` floor.
 * @returns {HTMLElement} The `.replicator-field` wrapper.
 */
function buildDom(minSets) {
	document.body.innerHTML = `
		<form>
			<div class="replicator-field"
				data-replicator-handle="referrals"
				data-replicator-min-sets="${minSets}"
				data-replicator-index-token="REPLICATORSETINDEX">
				<div data-replicator-sets></div>
				<div data-replicator-add>
					<button type="button" data-replicator-add-type="contact">Add Contact</button>
				</div>
				<template data-replicator-template="contact">
					<div class="replicator-field__set" data-replicator-set>
						<input type="hidden" name="referrals[REPLICATORSETINDEX][type]" value="contact">
						<input type="hidden" name="referrals[REPLICATORSETINDEX][enabled]" value="0">
						<input type="checkbox" name="referrals[REPLICATORSETINDEX][enabled]" value="1" checked>
						<input type="text" name="referrals[REPLICATORSETINDEX][name]"
							id="default-form-referrals[REPLICATORSETINDEX][name]-field">
						<button type="button" data-replicator-remove>Remove</button>
					</div>
				</template>
			</div>
		</form>`;

	return document.querySelector('.replicator-field');
}

/**
 * Mount the component on the wrapper and run its init (reads config + auto-fills).
 *
 * @param {number} minSets
 * @returns {object} The mounted component.
 */
function mount(minSets) {
	const el = buildDom(minSets);
	const component = makeComponent();
	component.$el = el;
	component.init();

	return component;
}

/** Count real sets (direct children of the sets container, never the templates). */
function setCount(component) {
	return component.setElements().length;
}

/** The `[type]` input names across all sets, in DOM order. */
function typeNames(component) {
	return Array.from(component.$el.querySelectorAll('input[name$="[type]"]')).map((el) => el.name);
}

describe('replicatorField engine', () => {
	beforeEach(() => { initTreeCalls = []; });

beforeEach(() => {
		document.body.innerHTML = '';
	});

	it('auto-fills to min_sets on init', () => {
		expect(setCount(mount(2))).toBe(2);
	});

	it('does not auto-fill when min_sets is zero', () => {
		expect(setCount(mount(0))).toBe(0);
	});

	it('adds a set with a concrete index (placeholder token replaced)', () => {
		const component = mount(0);
		component.addSet('contact');

		expect(setCount(component)).toBe(1);
		const names = typeNames(component);
		expect(names).toHaveLength(1);
		expect(names[0]).not.toContain('REPLICATORSETINDEX');
		expect(names[0]).toMatch(/^referrals\[\d+]\[type]$/);
	});

	it('clamps removal at min_sets', () => {
		const component = mount(1);
		expect(setCount(component)).toBe(1);

		// The lone set cannot be removed below the floor of 1.
		const removeButton = component.$el.querySelector('[data-replicator-remove]');
		component.removeSet({ target: removeButton });
		expect(setCount(component)).toBe(1);
	});

	it('removes a set above the floor', () => {
		const component = mount(1);
		component.addSet('contact');
		expect(setCount(component)).toBe(2);

		const removeButton = component.$el.querySelector('[data-replicator-remove]');
		component.removeSet({ target: removeButton });
		expect(setCount(component)).toBe(1);
	});

	it('reindexes every set to its DOM position on form submit', () => {
		const component = mount(0);
		component.addSet('contact'); // index 1000
		component.addSet('contact'); // index 1001
		component.addSet('contact'); // index 1002
		expect(setCount(component)).toBe(3);

		// Names carry the high, non-sequential add indices until reindex.
		expect(typeNames(component).every((n) => /\[100\d]/.test(n))).toBe(true);

		// The submit-time listener (bound in init) rewrites indices to DOM positions.
		component.$el.closest('form').dispatchEvent(new Event('submit', { cancelable: true }));

		expect(typeNames(component)).toEqual([
			'referrals[0][type]',
			'referrals[1][type]',
			'referrals[2][type]',
		]);
	});

	it('reindexes to a gap-free list after a middle removal', () => {
		const component = mount(0);
		component.addSet('contact'); // 1000
		component.addSet('contact'); // 1001
		component.addSet('contact'); // 1002

		// Remove the middle set, leaving indices 1000 and 1002.
		const middleRemove = component.setElements()[1].querySelector('[data-replicator-remove]');
		component.removeSet({ target: middleRemove });
		expect(setCount(component)).toBe(2);

		component.$el.closest('form').dispatchEvent(new Event('submit', { cancelable: true }));

		expect(typeNames(component)).toEqual([
			'referrals[0][type]',
			'referrals[1][type]',
		]);
	});
});

describe('delegated add/remove controls', () => {
	it('adds a set when an add control is clicked', () => {
		// The `@click` expressions these replaced were never bound under the CSP build, which made
		// "Add Set" inert on every public form (UAMS-Web/uams-statamic-archive#2056).
		const wrapper = buildDom(0);
		const component = makeComponent();
		component.$el = wrapper;
		component.init();

		const before = wrapper.querySelectorAll('[data-replicator-sets] > [data-replicator-set]').length;
		wrapper.querySelector('[data-replicator-add-type]').click();

		expect(wrapper.querySelectorAll('[data-replicator-sets] > [data-replicator-set]').length).toBe(before + 1);
	});

	it('ignores a click that is not on a control', () => {
		const wrapper = buildDom(0);
		const component = makeComponent();
		component.$el = wrapper;
		component.init();

		const before = wrapper.querySelectorAll('[data-replicator-sets] > [data-replicator-set]').length;
		wrapper.querySelector('[data-replicator-sets]').click();

		expect(wrapper.querySelectorAll('[data-replicator-sets] > [data-replicator-set]').length).toBe(before);
	});
});

describe('Alpine initialization of inserted sets', () => {
	it('initializes each added set explicitly', () => {
		const wrapper = buildDom(0);
		const component = makeComponent();
		component.$el = wrapper;
		component.init();

		initTreeCalls = [];
		component.addSet('contact');

		// Without this the auto-filled sets never initialize: their nested `x-data` never runs and
		// every `<template x-if>` inside stays inert, so no subfield reaches the DOM at all.
		expect(initTreeCalls).toHaveLength(1);
		expect(initTreeCalls[0].matches('[data-replicator-set]')).toBe(true);
	});
});

/**
 * Build an outer replicator whose one set type holds a nested replicator (UAMS-Web/uams-statamic-archive#2133). The nested
 * template carries its own depth-1 token, as `App\Fieldtypes\Replicator::indexTokenFor()` emits.
 *
 * @returns {HTMLElement} The outer `.replicator-field` wrapper.
 */
function buildNestedDom() {
	document.body.innerHTML = `
		<form>
			<div class="replicator-field"
				data-replicator-handle="groups"
				data-replicator-min-sets="0"
				data-replicator-index-token="REPLICATORSETINDEX">
				<div data-replicator-sets></div>
				<div data-replicator-add>
					<button type="button" data-replicator-add-type="group">Add Group</button>
				</div>
				<template data-replicator-template="group">
					<div class="replicator-field__set" data-replicator-set>
						<input type="hidden" name="groups[REPLICATORSETINDEX][type]" value="group">
						<div class="replicator-field"
							data-replicator-handle="groups[REPLICATORSETINDEX][members]"
							data-replicator-min-sets="0"
							data-replicator-index-token="REPLICATOR-NESTED-1-INDEX">
							<div data-replicator-sets></div>
							<div data-replicator-add>
								<button type="button" data-replicator-add-type="member">Add Member</button>
							</div>
							<template data-replicator-template="member">
								<div class="replicator-field__set" data-replicator-set>
									<input type="hidden" name="groups[REPLICATORSETINDEX][members][REPLICATOR-NESTED-1-INDEX][type]" value="member">
									<input type="text" name="groups[REPLICATORSETINDEX][members][REPLICATOR-NESTED-1-INDEX][guardian]">
									<button type="button" data-replicator-remove>Remove</button>
								</div>
							</template>
						</div>
						<button type="button" data-replicator-remove>Remove</button>
					</div>
				</template>
			</div>
		</form>`;

	return document.querySelector('.replicator-field');
}

/**
 * Mount a component on an element and run its init.
 *
 * @param {HTMLElement} el  The `.replicator-field` wrapper.
 * @returns {object} The mounted component.
 */
function mountOn(el) {
	const component = makeComponent();
	component.$el = el;
	mountedComponents.set(el, component);
	component.init();

	return component;
}

describe('nested replicators (#2133)', () => {
	beforeEach(() => {
		initTreeCalls = [];
		mountedComponents = new Map();
		mountNestedOnInitTree = false;
	});

	it('leaves the nested template placeholder intact when an outer set is cloned', () => {
		const outer = mountOn(buildNestedDom());
		outer.addSet('group');

		const set = outer.setElements()[0];
		const nestedTemplate = set.querySelector('[data-replicator-template="member"]');
		const nestedName = nestedTemplate.content.querySelector('input[type=text]').name;

		// The outer index is stamped over the outer token only; the nested token survives for the
		// nested engine to replace. A shared token would leave nothing for it to replace.
		expect(nestedName).toMatch(/^groups\[\d+]\[members]\[REPLICATOR-NESTED-1-INDEX]\[guardian]$/);
		expect(set.querySelector('[data-replicator-handle]').dataset.replicatorHandle).toMatch(/^groups\[\d+]\[members]$/);
	});

	it('gives each nested set its own name', () => {
		const outer = mountOn(buildNestedDom());
		outer.addSet('group');

		const nested = mountOn(outer.setElements()[0].querySelector('.replicator-field'));
		nested.addSet('member');
		nested.addSet('member');

		const names = nested.setElements().map((set) => set.querySelector('input[type=text]').name);

		expect(names).toHaveLength(2);
		expect(new Set(names).size).toBe(2);
		names.forEach((name) => expect(name).not.toContain('REPLICATOR-NESTED-1-INDEX'));
	});

	it('does not treat a nested replicator control as its own', () => {
		const wrapper = buildNestedDom();
		const outer = mountOn(wrapper);
		outer.addSet('group');

		// Only the outer component is mounted, so any change here would be the outer reacting to a
		// click that bubbled up from the nested replicator.
		outer.setElements()[0].querySelector('.replicator-field [data-replicator-add-type="member"]').click();

		expect(outer.setElements()).toHaveLength(1);
		expect(outer.setElements()[0].querySelectorAll('[data-replicator-set]')).toHaveLength(0);
	});

	it('only clones its own templates, never a nested set type', () => {
		const outer = mountOn(buildNestedDom());
		// Clone one outer set first, so the nested template is in the live DOM to be found.
		outer.addSet('group');
		outer.addSet('member');

		expect(outer.setElements()).toHaveLength(1);
		expect(outer.setElements()[0].querySelectorAll('[data-replicator-set]')).toHaveLength(0);
	});
});

describe('nested replicator snapshots (#2612)', () => {
	beforeEach(() => {
		initTreeCalls = [];
		mountedComponents = new Map();
		mountNestedOnInitTree = true;
	});

	/**
	 * Mount an outer replicator with one group holding two members, the second guardian typed.
	 *
	 * @returns {object} The mounted outer component.
	 */
	function outerWithTwoMembers() {
		const outer = mountOn(buildNestedDom());
		outer.addSet('group');

		const nested = mountedComponents.get(outer.setElements()[0].querySelector('[data-replicator-handle]'));
		nested.addSet('member');
		nested.addSet('member');
		nested.setElements()[1].querySelector('input[type=text]').value = 'second guardian';

		return outer;
	}

	it('keys only its own inputs as values and captures a nested replicator by position', () => {
		const snapshot = outerWithTwoMembers().snapshotSets();

		// The nested inputs carry the nested set index, so as outer values they could only ever
		// match a rebuilt set that happened to be renumbered identically.
		expect(Object.keys(snapshot[0].values)).toEqual(['[type]|hidden']);
		expect(snapshot[0].nested['[members]']).toEqual([
			// Both nested sets were added at runtime, so neither has a server-render position.
			{ type: 'member', source: null, values: { '[type]|hidden': 'member', '[guardian]|text': '' }, nested: {} },
			{ type: 'member', source: null, values: { '[type]|hidden': 'member', '[guardian]|text': 'second guardian' }, nested: {} },
		]);
	});

	it('rebuilds the nested sets and values into a fresh replicator', () => {
		const snapshot = outerWithTwoMembers().snapshotSets();

		// A fresh DOM is what a conditional rebuild leaves: the outer and nested sets are gone.
		const rebuilt = mountOn(buildNestedDom());
		rebuilt.restoreSets(snapshot);

		const nestedSets = rebuilt.setElements()[0].querySelectorAll('[data-replicator-handle] [data-replicator-set]');
		expect(Array.from(nestedSets).map((set) => set.querySelector('input[type=text]').value))
			.toEqual(['', 'second guardian']);
	});

	it('keeps no form-scope snapshot of its own when nested', () => {
		const outer = outerWithTwoMembers();
		const nested = mountedComponents.get(outer.setElements()[0].querySelector('[data-replicator-handle]'));

		nested.mirrorToFormScope();

		expect(nested.isNestedReplicator).toBe(true);
		expect(nested[nested.handle]).toBeUndefined();
	});

	it('builds a set from a preprocessed row without writing its fields back as values', () => {
		const component = mount(0);

		// On a first load the form scope holds Statamic's preprocessed rows under the handle, which
		// is how a blueprint `default` becomes a set. A subfield named `values` puts a string where
		// a snapshot keeps its value map.
		expect(() => component.restoreSets([{ type: 'contact', values: 'abc', enabled: true }])).not.toThrow();
		expect(setCount(component)).toBe(1);
	});
});

describe('server-rendered sets removed before a rebuild (#2687)', () => {
	beforeEach(() => {
		document.body.innerHTML = '';
	});

	/**
	 * Mount on a DOM whose server render holds one `contact` set per name, the way old input
	 * renders them, each at its position as its index.
	 *
	 * @param {Array<string>} names  The `name` value of each server-rendered set.
	 * @returns {object} The mounted component.
	 */
	function mountServerRendered(names) {
		const el = buildDom(0);
		el.querySelector('[data-replicator-sets]').innerHTML = names.map((name, i) => `
			<div class="replicator-field__set" data-replicator-set>
				<input type="hidden" name="referrals[${i}][type]" value="contact">
				<input type="text" name="referrals[${i}][name]" value="${name}">
				<button type="button" data-replicator-remove>Remove</button>
			</div>`).join('');

		const component = makeComponent();
		component.$el = el;
		component.init();

		return component;
	}

	/** Each set's `name` value, in DOM order. */
	function names(component) {
		return component.setElements().map((set) => set.querySelector('input[type=text]').value);
	}

	it('removes the server-rendered set its snapshot dropped, whatever its position', async () => {
		const before = mountServerRendered(['first', 'second', 'third']);
		before.setElements()[1].remove();
		const snapshot = before.snapshotSets();

		// The rebuild renders all three server-rendered sets again.
		const rebuilt = mountServerRendered(['first', 'second', 'third']);
		rebuilt.restoreSets(snapshot);
		await Promise.resolve();

		expect(names(rebuilt)).toEqual(['first', 'third']);
	});

	it('removes every server-rendered set for an empty snapshot only when the caller vouches for it', () => {
		// An empty list is both what a visitor who removed every set leaves and what a first load
		// with no rows holds, so its shape cannot tell them apart.
		const withoutVouch = mountServerRendered(['first', 'second']);
		withoutVouch.restoreSets([]);
		expect(names(withoutVouch)).toEqual(['first', 'second']);

		const vouched = mountServerRendered(['first', 'second']);
		vouched.restoreSets([], false, true);
		expect(names(vouched)).toEqual([]);
	});

	it('treats a rebuilt replicator\'s empty form scope as its own snapshot once it has mirrored', () => {
		const before = mountServerRendered(['first', 'second']);
		before.setElements().forEach((set) => set.remove());
		before.mirrorToFormScope();
		const mirrored = before.referrals;

		// The form element survives the teardown; the replicator inside it is rebuilt.
		const form = before.$el.closest('form');
		const rebuilt = makeComponent();
		form.innerHTML = buildDom(0).outerHTML;
		rebuilt.$el = form.querySelector('.replicator-field');
		rebuilt.$el.querySelector('[data-replicator-sets]').innerHTML = `
			<div data-replicator-set><input type="hidden" name="referrals[0][type]" value="contact"><input type="text" name="referrals[0][name]" value="first"></div>`;
		rebuilt.referrals = mirrored;
		rebuilt.init();

		expect(mirrored).toEqual([]);
		expect(names(rebuilt)).toEqual([]);
	});

	it('never removes a server-rendered set for the rows Statamic preprocesses on a first load', () => {
		const component = mountServerRendered(['first', 'second']);

		// Preprocessed rows carry no `source`, `values` map or `nested` map, so they cannot say
		// which sets were removed. A shorter list here must not trim the server render.
		component.restoreSets([{ type: 'contact', name: 'first', enabled: true }]);

		expect(names(component)).toEqual(['first', 'second']);
	});
});

describe('file inputs and nested keys after a reindex (#2874)', () => {
	beforeEach(() => {
		initTreeCalls = [];
		mountedComponents = new Map();
		mountNestedOnInitTree = true;
	});

	/**
	 * Mount a replicator whose `contact` set has a file input ahead of a text input, the order in
	 * which a thrown write on the file input would leave the text input unrestored.
	 *
	 * @returns {object} The mounted component.
	 */
	function mountWithFileInput() {
		const el = buildDom(0);
		const name = el.querySelector('template').content.querySelector('input[type=text]');
		const file = document.createElement('input');
		file.type = 'file';
		file.name = 'referrals[REPLICATORSETINDEX][attachment]';
		name.before(file);

		return mountOn(el);
	}

	it('does not capture a file input in a snapshot', () => {
		const component = mountWithFileInput();
		component.addSet('contact');

		expect(Object.keys(component.snapshotSets()[0].values)).not.toContain('[attachment]|file');
	});

	it('restores the inputs after a file input even when a value for it is present', () => {
		const component = mountWithFileInput();

		// A browser reports a chosen file as `C:\fakepath\<name>`, and writing that back to a file
		// input throws, which used to end the set's restore at the file input.
		expect(() => component.restoreSets([{
			type: 'contact',
			values: { '[attachment]|file': 'C:\\fakepath\\a.pdf', '[name]|text': 'Ada' },
		}], true)).not.toThrow();

		expect(component.setElements()[0].querySelector('input[type=text]').value).toBe('Ada');
	});

	it('derives a nested key only from a handle that starts with the set prefix', () => {
		const outer = mountOn(buildNestedDom());
		outer.addSet('group');
		const set = outer.setElements()[0];

		// Before a reindex the handle and the set prefix agree.
		expect(outer.nestedReplicators(set).map(({ key }) => key)).toEqual(['[members]']);

		// A set added at runtime is numbered from 1000, and a reindex renames its inputs to its
		// position while its nested `data-replicator-handle` keeps the old index.
		outer.reindex();
		expect(set.querySelector(':scope > input[name$="[type]"]').name).toBe('groups[0][type]');
		expect(set.querySelector('[data-replicator-handle]').dataset.replicatorHandle).toBe('groups[1000][members]');

		// Sliced by length, the handle gave `00][members]`, a key no rebuild matches.
		expect(outer.nestedReplicators(set)).toEqual([]);
		expect(Object.keys(outer.snapshotSets()[0].nested)).not.toContain('00][members]');
	});
});
