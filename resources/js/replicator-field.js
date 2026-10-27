/**
 * Alpine component backing the public-form Replicator fieldtype
 * (resources/views/vendor/statamic/forms/fields/replicator.antlers.html, UAMS-Web/uams-statamic-archive#675).
 *
 * App\Fieldtypes\Replicator::preProcessTagRenderable() server-renders each existing set at a
 * concrete row index and one blank `<template>` per set type at the placeholder index token. This
 * engine clones a template on "Add Set" (swapping the token for a fresh unique index across the
 * clone's names + ids), removes a set (clamped at `min_sets`), and — right before the form posts —
 * reindexes every set's field names to its DOM position so
 * Statamic\Fieldtypes\Replicator::process() receives a clean sequential list. Per-set `enabled`
 * needs no JS: the paired hidden `0` + checkbox `1` posts the toggle state natively.
 *
 * Known limit, left unfixed (UAMS-Web/uams-statamic-archive#2687): a `select` with `multiple` on is captured and restored by its
 * `value`, which is only its first selected option, so a conditional rebuild keeps one of its
 * choices. No form blueprint puts a multiple select in a replicator set, and the restore for one
 * would need the selected options captured as a list, a shape no other input here has.
 */

// ---
// Must run before `Alpine.start()` in site.js — Alpine ignores data names registered after its
// initial DOM scan.
// ---
/**
 * Per-set Alpine scope backing field conditions inside a replicator set (UAMS-Web/uams-statamic-archive#2056).
 *
 * A subfield's condition names a sibling by its BARE handle (`if: { is_minor: 'equals true' }`),
 * the same row-relative model the Control Panel uses. `$showField(config, $data, handle)` resolves
 * that handle against `$data`, so each set needs its own scope where the row's values are keyed by
 * bare handle — the form-level scope only has the replicator's array under one key.
 *
 * The values are seeded from `data-replicator-set-values` (written server-side by
 * App\Fieldtypes\Replicator::setValuesJson) rather than discovered from the DOM, because the
 * properties must exist BEFORE Alpine binds the descendants' `x-model`. Alpine runs a component's
 * `init()` before walking its children, so seeding here is early enough; a DOM scan on the same
 * hook would race the `<template x-if>` wrappers, whose contents are not in the DOM yet.
 *
 * `UAMSWeb\AlpineCspForms\JsDrivers\AlpineCsp::addToRenderableFieldAttributes()` (in the alpine-csp-forms add-on) binds each subfield's
 * `x-model` to its bare handle so writes land on these properties. The input's `name` keeps the
 * bracketed `parent[index][child]` shape, so submission is unaffected — and because the scope is
 * keyed by bare handle, it survives both `addSet()` (which rewrites indices in the cloned markup)
 * and `reindex()` (which rewrites them again on submit) without any condition needing to change.
 */
export function registerReplicatorSet(Alpine) {
    Alpine.data('replicatorSet', (seed = {}) => ({
        // ---
        // The row's subfield values, spread in as DECLARED properties (UAMS-Web/uams-statamic-archive#2056).
        //
        // They must be declared by the factory, not assigned in `init()`. Alpine resolves an
        // `x-model` identifier by walking UP the scope stack, so a property this component does
        // not declare is found on (or created on) an ancestor — which means every set shares one
        // value and toggling one row reveals the conditional subfield in all of them. Declaring
        // them here gives each set its own, which is what makes the condition per-row.
        //
        // The seed arrives as an argument rather than a data attribute for the same reason:
        // an attribute could only be read in `init()`, which is already too late.
        // ---
        ...seed,
    }));
}

// ---
// The last snapshot of each nested replicator, per enclosing set element (UAMS-Web/uams-statamic-archive#2687).
//
// A nested replicator behind a condition on a sibling field in its own set is not in the DOM
// while that condition is false, so a snapshot taken then cannot read it, and a restore cannot
// reach it. Its last snapshot is kept here, keyed by the set element and then by the nested
// replicator's handle suffix, so a snapshot can carry it while it is hidden and the nested
// replicator can restore itself from it when the condition shows it again. A WeakMap, so the
// entry goes with the set element when a set is removed or a rebuild discards it.
// ---
const nestedSnapshots = new WeakMap();

// ---
// The replicator handles each form has mirrored a snapshot for, keyed by the form element
// (UAMS-Web/uams-statamic-archive#2687). See `recordMirrored()`.
// ---
const mirroredHandles = new WeakMap();

export function registerReplicatorField(Alpine) {
    Alpine.data('replicatorField', () => ({
        // The field handle (root input-name segment), read from the wrapper.
        handle: '',
        // The minimum number of sets; removal is clamped here and the form auto-fills to it.
        minSets: 0,
        // The placeholder swapped for a concrete index when a set is cloned.
        indexToken: 'REPLICATORSETINDEX',
        // Monotonic index for newly-added sets; final positions are assigned by reindex() on submit.
        nextIndex: 0,
        // Whether this replicator sits inside another replicator's set (UAMS-Web/uams-statamic-archive#2612). A nested one keeps
        // no snapshot of its own: its enclosing replicator's snapshot carries it.
        isNestedReplicator: false,

        // Alpine lifecycle hook — runs once when the component mounts.
        init() {
            this.handle = this.$el.dataset.replicatorHandle || '';
            this.minSets = parseInt(this.$el.dataset.replicatorMinSets || '0', 10) || 0;
            this.indexToken = this.$el.dataset.replicatorIndexToken || this.indexToken;
            this.isNestedReplicator = Boolean(this.$el.parentElement?.closest('[data-replicator-handle]'));

            // Record which sets the server rendered, before a restore or the auto-fill adds any
            // (UAMS-Web/uams-statamic-archive#2687). A rebuild renders them again in the same order, so each one's position
            // here identifies it across the rebuild, whatever a submit-time reindex has done to
            // its names since.
            this.setElements().forEach((set, position) => {
                set.dataset.replicatorSource = String(position);
            });

            // ---
            // Delegate the add/remove controls rather than relying on `@click` (UAMS-Web/uams-statamic-archive#2056).
            //
            // Under the @alpinejs/csp build the `@click="addSet('participant')"` expressions the
            // partial used to carry were never bound — measured: clicking the button left the set
            // count unchanged while calling `addSet()` on the component directly added one. That
            // made "Add Set" inert on every public form, independently of conditions; it went
            // unnoticed because no browser test clicked it.
            //
            // A delegated listener needs no attribute expression, so it is immune to the CSP
            // interpreter's rules, and it keeps working for sets cloned in later.
            // ---
            // Rebuild anything a conditional teardown destroyed, then keep the surviving
            // form scope current. Restore runs BEFORE the listeners so the mirror does not
            // immediately overwrite the snapshot with the pre-restore (empty) set list.
            this.restoreFromFormScope();
            this.mirrorToFormScope();

            // Any edit inside a set changes what a restore would put back.
            this.$el.addEventListener('input', () => this.mirrorToFormScope());

            this.$el.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : null;
                if (!target) {
                    return;
                }

                // A nested replicator's controls bubble up here too; leave them to their own engine,
                // which has already run, and only record the nested sets it added or removed.
                if (!this.ownsControl(target)) {
                    this.mirrorToFormScope();

                    return;
                }

                const add = target.closest('[data-replicator-add-type]');
                if (add && this.$el.contains(add)) {
                    this.addSet(add.dataset.replicatorAddType || '');
                    this.mirrorToFormScope();

                    return;
                }

                const remove = target.closest('[data-replicator-remove]');
                if (remove && this.$el.contains(remove)) {
                    this.removeSet(event);
                    this.mirrorToFormScope();
                }
            });

            // Start new-set indices clear of the server-rendered rows so intermediate names never
            // collide before the submit-time reindex.
            this.nextIndex = this.setElements().length + 1000;

            // Ensure the form opens with at least the minimum number of sets. Bounded by the
            // shortfall (not a re-checked length) so a failed clone can never spin infinitely.
            const firstTemplate = this.$el.querySelector(':scope > [data-replicator-template]');
            if (firstTemplate) {
                const type = firstTemplate.dataset.replicatorTemplate;
                let shortfall = this.minSets - this.setElements().length;
                while (shortfall-- > 0) {
                    this.addSet(type);
                }
            }

            // Reindex to sequential positions right before the form posts.
            const form = this.$el.closest('form');
            if (form) {
                form.addEventListener('submit', () => this.reindex());
            }
        },

        /**
         * Whether a clicked element belongs to this replicator rather than to one nested in a set.
         *
         * Every replicator marks its root with `data-replicator-handle`, so the nearest such root
         * above the element is the replicator that owns it (UAMS-Web/uams-statamic-archive#2133).
         */
        ownsControl(element) {
            return element.closest('[data-replicator-handle]') === this.$el;
        },

        // The container holding the rendered sets.
        setsContainer() {
            return this.$el.querySelector(':scope > [data-replicator-sets]');
        },

        // The current set elements, in DOM order.
        setElements() {
            const container = this.setsContainer();
            return container
                ? Array.from(container.querySelectorAll(':scope > [data-replicator-set]'))
                : [];
        },

        /**
         * Capture every set's type, field values and nested replicators, so they can be re-applied
         * to freshly cloned sets whose indices differ.
         *
         * A value is keyed by its name suffix AFTER the set's own `handle[idx]` prefix, plus the
         * input type — the `enabled` control is a paired hidden `0` and checkbox `1` sharing one
         * name, so the suffix alone would collide and the restore would drop the checkbox state.
         *
         * Only this replicator's own inputs are keyed that way (UAMS-Web/uams-statamic-archive#2612). A nested replicator's
         * inputs carry the nested set's index in their names, and a rebuilt nested replicator
         * numbers its sets afresh, so a flat key would match nothing past the sets its auto-fill
         * happens to renumber identically. Each nested replicator is captured instead as its own
         * snapshot, keyed by its handle suffix (`[members]`), which is position-based all the way
         * down. A nested replicator its own set's conditions have hidden is carried from its
         * last snapshot (UAMS-Web/uams-statamic-archive#2687).
         *
         * `source` is the set's server-render position, or null for a set added at runtime, so a
         * restore can tell which server-rendered sets the visitor removed (UAMS-Web/uams-statamic-archive#2687).
         *
         * A file input is not captured, so a chosen file is lost across a rebuild (UAMS-Web/uams-statamic-archive#2874). A browser
         * lets script set a file input's `value` only to the empty string, and never sets its
         * `files`, so there is nothing a restore could write back. Captured, the placeholder path a
         * browser reports for a chosen file would throw when written, and stop the restore of every
         * later input in the set.
         */
        snapshotSets() {
            return this.setElements().map((set) => {
                const typeInput = set.querySelector(':scope > input[name$="[type]"]');
                const prefix = this.setNamePrefix(set);
                const values = {};
                const nested = { ...(nestedSnapshots.get(set) || {}) };

                set.querySelectorAll('[name]').forEach((input) => {
                    if (!this.ownsControl(input) || !input.name.startsWith(prefix) || this.isFile(input)) {
                        return;
                    }

                    values[this.valueKey(input, prefix)] = this.isChoice(input) ? input.checked : input.value;
                });

                this.nestedReplicators(set).forEach(({ key, component }) => {
                    nested[key] = component.snapshotSets();
                });

                nestedSnapshots.set(set, nested);

                return {
                    type: typeInput ? typeInput.value : '',
                    source: set.dataset.replicatorSource ?? null,
                    values,
                    nested,
                };
            });
        },

        /** Whether an input is a checkbox or radio, whose state is `checked` rather than `value`. */
        isChoice(input) {
            return input.type === 'checkbox' || input.type === 'radio';
        },

        /** Whether an input is a file input, whose chosen file script can neither read back nor set. */
        isFile(input) {
            return input.type === 'file';
        },

        /**
         * The key an input's value is captured under: its name after the set's `handle[idx]`
         * prefix, plus its type. A checkbox or radio adds its own value (UAMS-Web/uams-statamic-archive#2687), because every
         * input in a radio group or a checkbox group shares one name, so the name alone would
         * keep only the last one's state and write it back over all of them.
         */
        valueKey(input, prefix) {
            const key = input.name.slice(prefix.length) + '|' + input.type;

            return this.isChoice(input) ? key + '|' + input.value : key;
        },

        /**
         * The `handle[idx]` prefix every name in a set starts with, read from its `type` input.
         * Empty when the set has none, which makes every prefix check fail closed.
         */
        setNamePrefix(set) {
            const typeInput = set ? set.querySelector(':scope > input[name$="[type]"]') : null;

            return typeInput ? typeInput.name.slice(0, -'[type]'.length) : '';
        },

        /**
         * The replicators nested directly in a set — not ones nested deeper, which their own
         * replicator reaches — each with its handle suffix after the set prefix and its Alpine
         * component. One whose component has not initialized is skipped.
         *
         * So is one whose handle does not start with the set prefix (UAMS-Web/uams-statamic-archive#2874). `reindex()` rewrites
         * input names on submit but not `data-replicator-handle`, so after a reindex a set's prefix
         * and its nested replicator's handle can disagree; slicing by length then yields a wrong
         * key, which would capture the nested replicator under a key its rebuild never matches.
         */
        nestedReplicators(set) {
            const prefix = this.setNamePrefix(set);

            if (prefix === '') {
                return [];
            }

            return Array.from(set.querySelectorAll('[data-replicator-handle]'))
                .filter((root) => root.parentElement.closest('[data-replicator-handle]') === this.$el)
                .map((root) => {
                    const handle = root.dataset.replicatorHandle || '';

                    return {
                        key: handle.startsWith(prefix) ? handle.slice(prefix.length) : '',
                        component: typeof Alpine.$data === 'function' ? Alpine.$data(root) : null,
                    };
                })
                .filter(({ key, component }) => key !== '' && component && typeof component.snapshotSets === 'function');
        },

        /**
         * Mirror the current sets out to the form scope, which survives the wrapper teardown. A
         * nested replicator mirrors nothing: its sets are part of its enclosing replicator's
         * snapshot (UAMS-Web/uams-statamic-archive#2612).
         */
        mirrorToFormScope() {
            if (this.handle && !this.isNestedReplicator) {
                this[this.handle] = this.snapshotSets();
                this.recordMirrored();
            }
        },

        /**
         * Note that this form's scope now holds a snapshot for this replicator (UAMS-Web/uams-statamic-archive#2687). The form
         * element survives a conditional teardown, so a rebuilt replicator can tell its own
         * mirrored snapshot from the rows Statamic put there on a first load, even when both are
         * an empty list.
         */
        recordMirrored() {
            const form = this.$el.closest('form');

            if (!form) {
                return;
            }

            const handles = mirroredHandles.get(form) || new Set();
            handles.add(this.handle);
            mirroredHandles.set(form, handles);
        },

        /** Whether this form's scope holds a snapshot this replicator mirrored before a rebuild. */
        hasMirrored() {
            const form = this.$el.closest('form');

            return Boolean(form && mirroredHandles.get(form)?.has(this.handle));
        },

        /**
         * Mirror from the outermost replicator, whose snapshot carries this one if it is nested
         * (UAMS-Web/uams-statamic-archive#2687). A nested replicator that restores itself asks for this explicitly rather than
         * relying on the `input` events its writes dispatch, so the outermost snapshot records
         * the restore whatever events it did or did not produce.
         */
        mirrorOutermost() {
            if (!this.isNestedReplicator) {
                this.mirrorToFormScope();

                return;
            }

            const root = this.$el.parentElement?.closest('[data-replicator-handle]');
            const parent = root && typeof Alpine.$data === 'function' ? Alpine.$data(root) : null;

            if (parent && typeof parent.mirrorOutermost === 'function') {
                parent.mirrorOutermost();
            }
        },

        /**
         * Rebuild the sets from the form scope after a conditional rebuild (UAMS-Web/uams-statamic-archive#2253).
         *
         * `<template x-if>` DESTROYS its subtree, and a set the user added was cloned in by
         * `addSet()` at runtime — the server render never contained it, so unlike the signature
         * field (UAMS-Web/uams-statamic-archive#2063) and `listField` (UAMS-Web/uams-statamic-archive#2232) there is no surviving element for a carried value
         * to land in. The whole set has to be re-created, which is why this is a different
         * mechanism rather than the same carry a third time.
         *
         * Only ever ADDS. The server-rendered sets are already present when this runs, so
         * restoring the snapshot's tail is enough; rebuilding from zero would discard a
         * server-rendered set on an ordinary first load.
         *
         * A nested replicator has no form-scope snapshot: its enclosing replicator's snapshot
         * restores it (UAMS-Web/uams-statamic-archive#2612). The exception is one that a condition on a sibling field in its
         * own set has just shown again (UAMS-Web/uams-statamic-archive#2687), either after its set was restored with it hidden
         * or after the visitor hid and showed it. It was not in the DOM when its set was last
         * restored or snapshotted, so it restores itself from the last snapshot kept for it.
         */
        restoreFromFormScope() {
            if (!this.handle) {
                return;
            }

            if (!this.isNestedReplicator) {
                this.restoreSets(this[this.handle], false, this.hasMirrored());

                return;
            }

            const set = this.$el.closest('[data-replicator-set]');
            const prefix = this.setNamePrefix(set);
            const kept = set ? nestedSnapshots.get(set) : null;
            const key = prefix !== '' && this.handle.startsWith(prefix) ? this.handle.slice(prefix.length) : '';

            if (kept && key !== '' && Array.isArray(kept[key])) {
                this.restoreSets(kept[key], false, true);
                this.mirrorOutermost();
            }
        },

        /**
         * Rebuild the sets a snapshot describes, and write its values and nested replicators back
         * (UAMS-Web/uams-statamic-archive#2612).
         *
         * A set this call ADDS gets its entry at once. A set that was already present gets it
         * after a microtask, unless `immediate` is set. When this runs from `init()`, a present
         * set is the server render, and Alpine has not yet expanded the `<template x-if>`
         * wrappers holding its subfields and nested replicators, because a component's
         * descendants initialize after it; by the next microtask the tree walk has finished. The
         * deferred write then re-mirrors, since `init()` has already mirrored the set without
         * them. A nested replicator is restored with `immediate`, because by then its enclosing
         * set is fully initialized and its present sets are the blank ones its `min_sets`
         * auto-fill just added.
         *
         * A set whose type differs from its entry is skipped, so a set never posts a type its
         * fields do not belong to.
         *
         * A server-rendered set the snapshot no longer holds is removed first (UAMS-Web/uams-statamic-archive#2687). The rebuild
         * renders every server-rendered set again, including one the visitor removed, and without
         * this the sets after it would also take the wrong entries by position.
         *
         * @param {Array} snapshot  What `snapshotSets()` captured.
         * @param {boolean} immediate  Whether present sets get their entries without waiting.
         * @param {boolean} isSnapshot  Whether the caller knows `snapshot` came from
         *     `snapshotSets()`, which is the only way an empty one can say every server-rendered
         *     set was removed.
         */
        restoreSets(snapshot, immediate = false, isSnapshot = false) {
            if (!Array.isArray(snapshot)) {
                return;
            }

            this.removeDroppedSourceSets(snapshot, isSnapshot);

            if (snapshot.length === 0) {
                return;
            }

            const present = this.setElements().length;

            for (let i = present; i < snapshot.length; i++) {
                if (this.hasSetType(snapshot[i])) {
                    this.addSet(snapshot[i].type);
                }
            }

            const sets = this.setElements();
            const deferred = [];

            snapshot.forEach((entry, i) => {
                const set = sets[i];

                if (!set || !this.hasSetType(entry) || this.setType(set) !== entry.type) {
                    return;
                }

                if (i < present && !immediate) {
                    deferred.push([set, entry]);

                    return;
                }

                this.restoreSet(set, entry);
            });

            if (deferred.length > 0) {
                queueMicrotask(() => {
                    deferred.forEach(([set, entry]) => this.restoreSet(set, entry));
                    this.mirrorOutermost();
                });
            }
        },

        /**
         * Remove each server-rendered set that a snapshot shows the visitor removed (UAMS-Web/uams-statamic-archive#2687).
         *
         * Only a snapshot `snapshotSets()` took can say that. On a first load the form scope
         * holds Statamic's preprocessed rows instead, which describe the server render itself
         * and must never remove any of it. So this does nothing unless the caller vouches for the
         * snapshot, or every entry of a non-empty one has the snapshot's shape. An empty list has
         * no entries to check, and on a first load it is what a replicator with no rows holds, so
         * only the caller's word lets an empty snapshot remove every server-rendered set.
         *
         * A set is matched by its server-render position, recorded in `init()`, never by its
         * names: a submit-time reindex rewrites those.
         *
         * @param {Array} snapshot  What `snapshotSets()` captured.
         * @param {boolean} isSnapshot  Whether the caller knows it came from `snapshotSets()`.
         */
        removeDroppedSourceSets(snapshot, isSnapshot = false) {
            const hasSnapshotShape = snapshot.length > 0 && snapshot.every((entry) => this.isPlainMap(entry)
                && 'source' in entry
                && this.isPlainMap(entry.values)
                && this.isPlainMap(entry.nested));

            if (!isSnapshot && !hasSnapshotShape) {
                return;
            }

            const kept = new Set(snapshot.map((entry) => entry.source).filter((source) => source !== null));

            this.setElements().forEach((set) => {
                const source = set.dataset.replicatorSource;

                if (source !== undefined && !kept.has(source)) {
                    set.remove();
                }
            });
        },

        /**
         * Write one entry's nested replicators and values back into a set.
         *
         * Each part is written only when it has the shape `snapshotSets()` gives it. On a first
         * load the form scope holds Statamic's own preprocessed rows under the handle instead:
         * they carry a `type`, which is how a blueprint `default` becomes a set, but a `values`
         * key there (if any) is a subfield's value, not a map to write back.
         */
        restoreSet(set, entry) {
            if (this.isPlainMap(entry.nested)) {
                // Kept for a nested replicator this set's own conditions hide until the values
                // below are written; it restores itself from here when they show it (UAMS-Web/uams-statamic-archive#2687).
                nestedSnapshots.set(set, { ...entry.nested });

                this.nestedReplicators(set).forEach(({ key, component }) => {
                    if (key in entry.nested) {
                        component.restoreSets(entry.nested[key], true, true);
                    }
                });
            }

            if (this.isPlainMap(entry.values)) {
                this.applyValues(set, entry.values);
            }
        },

        /** Whether a restore entry names a set type to rebuild. */
        hasSetType(entry) {
            return this.isPlainMap(entry) && typeof entry.type === 'string' && entry.type !== '';
        },

        /** Whether a value is a plain key-value object rather than a string, array or null. */
        isPlainMap(value) {
            return value !== null && typeof value === 'object' && !Array.isArray(value);
        },

        /** A set's type, read from its own `type` input. */
        setType(set) {
            const typeInput = set.querySelector(':scope > input[name$="[type]"]');

            return typeInput ? typeInput.value : '';
        },

        /**
         * Write captured values back into one set's own inputs.
         *
         * Alpine binds subfields with `x-model`, so the set's own state only follows a
         * programmatic write when the event it listens for is dispatched: `input` for a text
         * input, but `change` for a checkbox, a radio and a select (UAMS-Web/uams-statamic-archive#2687). Without `change`, a
         * restored choice shows in the DOM while the state the set's conditions read keeps the
         * blank one. `change` goes only to an input the write actually changed, as it would for
         * a visitor's click: a radio being unchecked gets none, because its group's state is the
         * checked one's value, and an untouched toggle keeps its null rather than becoming false.
         */
        applyValues(set, values) {
            if (!set || !values) {
                return;
            }

            const prefix = this.setNamePrefix(set);

            set.querySelectorAll('[name]').forEach((input) => {
                if (!this.ownsControl(input) || prefix === '' || !input.name.startsWith(prefix) || this.isFile(input)) {
                    return;
                }

                const key = this.valueKey(input, prefix);

                if (!(key in values)) {
                    return;
                }

                const was = this.isChoice(input) ? input.checked : input.value;

                if (this.isChoice(input)) {
                    input.checked = Boolean(values[key]);
                } else {
                    input.value = values[key];
                }

                const changed = (this.isChoice(input) ? input.checked : input.value) !== was;

                input.dispatchEvent(new Event('input', { bubbles: true }));

                if (changed && (input.tagName === 'SELECT' || input.type === 'checkbox' || (input.type === 'radio' && input.checked))) {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        },

        /**
         * Clone the blank template for a set type, stamping a fresh index over the placeholder token
         * across its names + ids, and append it.
         */
        addSet(type) {
            // Only this replicator's own templates: a nested replicator's sit inside its sets (UAMS-Web/uams-statamic-archive#2133).
            const template = this.$el.querySelector(`:scope > [data-replicator-template="${type}"]`);
            const container = this.setsContainer();
            if (!template || !container) {
                return;
            }

            const index = String(this.nextIndex++);
            const html = template.innerHTML.split(this.indexToken).join(index);

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const set = wrapper.firstElementChild;
            if (set) {
                container.appendChild(set);

                // ---
                // Initialize the inserted subtree explicitly (UAMS-Web/uams-statamic-archive#2056). The min-sets auto-fill calls
                // this from `init()`, i.e. DURING Alpine's own tree walk, before its mutation
                // observer is live — so a set added there is never initialized, its nested
                // `x-data="replicatorSet()"` never runs, and every `<template x-if>` inside it
                // stays inert. The observable symptom is a set that renders its scaffolding but
                // none of its subfields, conditional or not.
                //
                // Safe for user-clicked adds too: initTree is idempotent on an uninitialized tree,
                // and Alpine skips nodes it has already marked.
                // ---
                Alpine.initTree(set);
            }
        },

        /**
         * Remove the set the clicked control belongs to, never dropping below `min_sets`.
         */
        removeSet(event) {
            const set = event.target.closest('[data-replicator-set]');
            if (!set) {
                return;
            }

            // The clicked set is still in the DOM, so `<=` blocks the drop that would go under min.
            if (this.setElements().length <= this.minSets) {
                return;
            }

            set.remove();
        },

        /**
         * Rewrite every set's field-name index to its current DOM position, so the POST is a clean
         * sequential list regardless of the add/remove history (which can leave index gaps).
         */
        reindex() {
            const escaped = this.handle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const pattern = new RegExp('^' + escaped + '\\[[^\\]]*\\]');

            this.setElements().forEach((set, position) => {
                set.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(pattern, `${this.handle}[${position}]`);
                });
            });
        },
    }));
}
