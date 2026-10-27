<!--
 * Partial date: optional year (integer), month (select), optional day (integer).
 * Entries persist canonical strings (`YYYY-MM-DD`, `XXXX-MM`, …); the CP still edits `{year,
 * month, day}` shapes that `PartialDate.php` converts on save/load.
 * Max day uses @internationalized/date (Gregorian); February without year allows 29.
 * GroupFieldtypeShell (bordered group) is used only when two or more parts are visible.
 * Empty optional fields without blueprint part defaults show an Add date control (core Date pattern);
 * Add date reveals inputs with no pre-filled calendar values.
 * Finer inputs are blocked until prerequisites are met (year before month unless month/day seeded;
 * day after month unless year-alone day or a stored day) via `inert` + overlay (not Combobox
 * `disabled`, which left muted styling); clearing year clears dependents; clearing month clears
 * day unless a year stays set (`YYYY-XX-DD`).
 * Controls stay mounted so typing in year does not steal focus when values update mid-entry.
 * When a subfield is disabled by this chain, a hover overlay shows the CP `v-tooltip` (floating-vue)
 * with guidance aligned with the partial-date grammar.
 -->
<template>
	<div class="partial-date min-w-[145px]">
		<Button
			v-if="showAddDateButton"
			:text="__('statamic::fieldtypes.partial_date.add')"
			icon="calendar"
			@click="addPartialDate"
		/>
		<component :is="shellWrapper" v-if="showPartialFields">
			<div :class="publishFieldsRootClass">
			<Field
				v-if="includeYear"
				:label="__('statamic::fieldtypes.partial_date.field.year')"
				:required="Boolean(fc.subfield_required?.year)"
				:class="[
					partFieldLayoutClass,
					{ 'has-error': errs('year').length > 0 },
				]"
				data-ui-input-group
			>
				<Input
					:id="fieldIdBase + '_year'"
					:name="fieldName('year')"
					type="number"
					:model-value="yearDisplay"
					:read-only="isReadOnly"
					:input-attrs="yearInputAttrs"
					@update:model-value="onYearInput($event)"
				/>
				<ErrorMessage
					v-for="(err, i) in errs('year')"
					:key="'y'+i"
					class="mt-1"
					:text="err"
				/>
			</Field>

			<Field
				v-if="includeMonth"
				:label="__('statamic::fieldtypes.partial_date.field.month')"
				:required="Boolean(fc.subfield_required?.month)"
				:class="[
					partFieldLayoutClass,
					{ 'has-error': errs('month').length > 0 },
				]"
				data-ui-input-group
			>
				<div class="relative w-full min-w-0">
					<!--
						Hierarchy lock: `inert` + overlay (not Combobox `disabled`, which had stuck opacity).
						`opacity-50 cursor-not-allowed` on the inert wrapper matches Combobox trigger `disabled` styles.
						`disabled` is only for blueprint read-only.
					-->
					<div
						:inert="monthHierarchyLocked"
						:class="{ 'opacity-50 cursor-not-allowed': monthHierarchyLocked }"
					>
						<Combobox
							:id="fieldIdBase + '_month'"
							:clearable="true"
							:disabled="isReadOnly"
							:model-value="monthModelValue"
							:options="monthOptionsFormatted"
							:option-label="'label'"
							:option-value="'value'"
							:read-only="isReadOnly"
							:searchable="false"
							:taggable="false"
							@update:model-value="onMonthInput($event)"
						/>
					</div>
					<div
						v-if="monthHierarchyLocked"
						class="absolute inset-0 z-5 cursor-not-allowed rounded-md"
						v-tooltip="monthHierarchyTooltip"
						aria-hidden="true"
						tabindex="-1"
					/>
				</div>
				<ErrorMessage
					v-for="(err, i) in errs('month')"
					:key="'m'+i"
					class="mt-1"
					:text="err"
				/>
			</Field>

			<Field
				v-if="includeDay"
				:label="__('statamic::fieldtypes.partial_date.field.day')"
				:required="Boolean(fc.subfield_required?.day)"
				:class="[
					partFieldLayoutClass,
					{ 'has-error': errs('day').length > 0 },
				]"
				data-ui-input-group
			>
				<div class="relative w-full min-w-0">
					<div
						:inert="dayHierarchyLocked"
						:class="{ 'opacity-50 cursor-not-allowed': dayHierarchyLocked }"
					>
						<Input
							:id="fieldIdBase + '_day'"
							:name="fieldName('day')"
							type="number"
							:model-value="dayDisplay"
							:disabled="isReadOnly"
							:read-only="isReadOnly"
							:input-attrs="dayInputAttrs"
							@update:model-value="onDayInput($event)"
						/>
					</div>
					<div
						v-if="dayHierarchyLocked"
						class="absolute inset-0 z-5 cursor-not-allowed rounded-md"
						v-tooltip="dayHierarchyTooltip"
						aria-hidden="true"
						tabindex="-1"
					/>
				</div>
				<ErrorMessage
					v-for="(err, i) in errs('day')"
					:key="'d'+i"
					class="mt-1"
					:text="err"
				/>
			</Field>
			</div>
		</component>
	</div>
</template>

<script>
import { CalendarDate } from '@internationalized/date';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import {
	Button,
	Field,
	Input,
	ErrorMessage,
	publishContextKey,
	Combobox,
} from '@statamic/cms/ui';
import GroupFieldtypeShell from './partials/GroupFieldtypeShell.vue';

export default {
	name: 'PartialDateFieldtype',

	mixins: [Fieldtype],

	components: {
		Button,
		GroupFieldtypeShell,
		Field,
		Input,
		ErrorMessage,
		Combobox,
	},

	inject: {
		publishContext: { from: publishContextKey },
	},

	data() {
		return {
			/** True after the editor clicks Add date so empty subfields stay visible. */
			partialDateFieldsRevealed: false,
		};
	},

	computed: {
		/**
		 * Blueprint config lives under `meta.config` in the CP (same as other custom fieldtypes);
		 * preload extras (month_options, include_year, etc.) are merged on `meta`.
		 */
		fc() {
			const m = this.meta || {};
			const fromBlueprint =
				m.config && typeof m.config === 'object' ? m.config : {};
			const { config: _omitNestedConfig, ...metaRest } = m;

			return { ...fromBlueprint, ...metaRest };
		},

		includeYear() {
			return this.fc.include_year !== false;
		},

		includeMonth() {
			return this.fc.include_month !== false;
		},

		includeDay() {
			return this.fc.include_day !== false;
		},

		/**
		 * Coarser chain satisfied for unlocking the month control (year when included, or stored month/day).
		 * Read-only views always treat the chain as satisfied; `:disabled` still applies from `isReadOnly`.
		 */
		isMonthPartEnabled() {
			if (!this.includeMonth) {
				return false;
			}

			if (this.isReadOnly) {
				return true;
			}

			if (!this.includeYear) {
				return true;
			}

			const { year, month, day } = this.normalized;

			if (year !== null) {
				return true;
			}

			if (month !== null || day !== null) {
				return true;
			}

			return false;
		},

		/**
		 * Day control is interactive when month is set, day is seeded, year is known (allows
		 * `YYYY-XX-DD`), or month is omitted by blueprint.
		 */
		isDayPartEnabled() {
			if (!this.includeDay) {
				return false;
			}

			if (this.isReadOnly) {
				return true;
			}

			if (!this.includeMonth) {
				return true;
			}

			const { year, month, day } = this.normalized;

			if (month !== null) {
				return true;
			}

			if (day !== null) {
				return true;
			}

			if (this.includeYear && year !== null) {
				return true;
			}

			return false;
		},

		/** Subfield disabled by the year → month → day chain (not blueprint read-only). */
		monthHierarchyLocked() {
			return !this.isReadOnly && !this.isMonthPartEnabled;
		},

		/** Subfield disabled until prerequisites for month/day entry are satisfied. */
		dayHierarchyLocked() {
			return !this.isReadOnly && !this.isDayPartEnabled;
		},

		/**
		 * Statamic CP uses floating-vue (`v-tooltip`) for hierarchy lock explanations.
		 *
		 * @see resources/js/components/fieldtypes/partials/SubfieldLabel.vue
		 */
		monthHierarchyTooltip() {
			return {
				content: __(
					'statamic::fieldtypes.partial_date.hierarchy.month_locked_tooltip',
				),
				delay: { show: 300 },
				autoHide: false,
			};
		},

		dayHierarchyTooltip() {
			return {
				content: __(
					'statamic::fieldtypes.partial_date.hierarchy.day_locked_tooltip',
				),
				delay: { show: 300 },
				autoHide: false,
			};
		},

		visibleSubfieldCount() {
			let n = 0;

			if (this.includeYear) {
				n++;
			}

			if (this.includeMonth) {
				n++;
			}

			if (this.includeDay) {
				n++;
			}

			return n;
		},

		shellWrapper() {
			return this.visibleSubfieldCount > 1 ? GroupFieldtypeShell : 'div';
		},

		/** Layout mode from blueprint (`subfields_layout`). */
		subfieldsLayoutMode() {
			return this.fc.subfields_layout === 'stack' ? 'stack' : 'inline';
		},

		/**
		 * Same outer class Statamic uses in `Publish/Fields.vue`: `.publish-fields` establishes the
		 * CP grid for `field-w-*` widths. Do not add flex here — it overrides that layout.
		 */
		publishFieldsRootClass() {
			return 'publish-fields';
		},

		/**
		 * Matches `field_width_class()` in Statamic `globals.js` — must align with
		 * ``field-w-${field.width}`` on `Publish/Fields.vue`.
		 */
		statamicSubfieldWidthClass() {
			const n = this.visibleSubfieldCount;

			if (n >= 3) {
				return 'field-w-33';
			}

			if (n === 2) {
				return 'field-w-50';
			}

			return 'field-w-100';
		},

		/**
		 * Classes on each Statamic Field: stack = full-width rows (`field-w-100`); inline =
		 * proportional columns (`field-w-33` / `field-w-50`) like blueprint width on publish fields.
		 */
		partFieldLayoutClass() {
			const base = 'min-w-0 form-group';

			if (this.subfieldsLayoutMode === 'stack') {
				return `${base} field-w-100 w-full`;
			}

			return `${base} ${this.statamicSubfieldWidthClass}`;
		},

		fieldHandle() {
			return this.handle ?? this.name ?? '';
		},

		fieldIdBase() {
			const h = (this.handle || this.name || 'partial_date').replace(/[^a-z0-9_]/gi, '_');

			return String(this.id || `field_${h}`).replace(/\./g, '_');
		},

		normalized() {
			const v = this.value;

			if (typeof v === 'string') {
				const parsed = this.parseStoredPartialTripleFromString(v);

				return (
					parsed ?? {
						year: null,
						month: null,
						day: null,
					}
				);
			}

			const obj = v && typeof v === 'object' ? v : {};

			return {
				year: this.coerceNullableInt(obj.year),
				month: this.coerceNullableInt(obj.month),
				day: this.coerceNullableInt(obj.day),
			};
		},

		yearDisplay() {
			if (!this.includeYear) {
				return '';
			}

			const y = this.normalized.year;

			return y === null ? '' : String(y);
		},

		dayDisplay() {
			if (!this.includeDay) {
				return '';
			}

			const d = this.normalized.day;

			return d === null ? '' : String(d);
		},

		monthOptionsFormatted() {
			const raw = this.fc.month_options;

			if (!Array.isArray(raw)) {
				return [];
			}

			return raw.map((opt) => ({
				value: opt.value,
				label:
					typeof __ !== 'undefined'
						? __(opt.label ?? String(opt.value))
						: opt.label ?? String(opt.value),
			}));
		},

		monthModelValue() {
			const m = this.normalized.month;

			if (m === null || m === undefined) {
				return null;
			}

			const opts = this.monthOptionsFormatted;
			const found = opts.find((o) => Number(o.value) === Number(m));

			return found ? found.value : m;
		},

		maxDay() {
			return this.computeMaxDay(this.normalized.year, this.normalized.month);
		},

		/**
		 * Blueprint-level required rule (`validate` contains `required`), mirroring core Date
		 * treating required fields as always visible.
		 */
		isParentMarkedRequired() {
			const raw = this.fc.validate;

			if (raw === undefined || raw === null || raw === '') {
				return false;
			}

			const list = Array.isArray(raw) ? raw : [raw];

			return list.includes('required');
		},

		/** Whether year/month/day defaults are configured on the fieldtype for included parts. */
		hasConfiguredPartDefaults() {
			const c = this.fc;

			if (this.includeYear && this.defaultConfiguredPresent(c.year_default)) {
				return true;
			}

			if (this.includeMonth && this.defaultConfiguredPresent(c.month_default)) {
				return true;
			}

			if (this.includeDay && this.defaultConfiguredPresent(c.day_default)) {
				return true;
			}

			return false;
		},

		/** Stored value has at least one filled part among those included in the mode. */
		hasPartialContent() {
			const n = this.normalized;

			if (this.includeYear && n.year !== null) {
				return true;
			}

			if (this.includeMonth && n.month !== null) {
				return true;
			}

			if (this.includeDay && n.day !== null) {
				return true;
			}

			return false;
		},

		/** Show year/month/day inputs (always when read-only, required, populated, defaults, or Add date clicked). */
		showPartialFields() {
			return (
				this.isReadOnly ||
				this.isParentMarkedRequired ||
				this.hasPartialContent ||
				this.hasConfiguredPartDefaults ||
				this.partialDateFieldsRevealed
			);
		},

		/** Empty optional field without part defaults: single Add date control like core Date. */
		showAddDateButton() {
			return !this.isReadOnly && !this.showPartialFields;
		},

		yearInputAttrs() {
			const attrs = {
				step: '1',
				inputmode: 'numeric',
			};

			if (this.fc.year_min !== undefined && this.fc.year_min !== null && this.fc.year_min !== '') {
				attrs.min = String(this.fc.year_min);
			}

			if (this.fc.year_max !== undefined && this.fc.year_max !== null && this.fc.year_max !== '') {
				attrs.max = String(this.fc.year_max);
			}

			return attrs;
		},

		dayInputAttrs() {
			return {
				step: '1',
				inputmode: 'numeric',
				min: '1',
				max: String(this.maxDay),
			};
		},
	},

	methods: {
		defaultConfiguredPresent(val) {
			return val !== undefined && val !== null && val !== '';
		},

		/** Reveal inputs with empty slots; blueprint part defaults remain separate in `meta`. */
		addPartialDate() {
			this.partialDateFieldsRevealed = true;

			const partial = {};

			if (this.includeYear) {
				partial.year = null;
			}

			if (this.includeMonth) {
				partial.month = null;
			}

			if (this.includeDay) {
				partial.day = null;
			}

			this.patch(partial);
		},

		errs(dotSuffix = '') {
			const handle = this.fieldHandle;

			if (!handle) {
				return [];
			}

			const fullKey = dotSuffix ? `${handle}.${dotSuffix}` : handle;
			const bag = this.publishContext?.errors;
			const normalized =
				bag && typeof bag === 'object' && 'value' in bag ? bag.value : bag;

			if (!normalized || typeof normalized !== 'object') {
				return [];
			}

			const list = normalized[fullKey];

			return Array.isArray(list) ? list : [];
		},

		fieldName(part) {
			const n = this.name;

			return n ? `${n}.${part}` : part;
		},

		coerceNullableInt(val) {
			if (val === undefined || val === null || val === '') {
				return null;
			}

			const n = Number(val);

			return Number.isFinite(n) ? n : null;
		},

		/**
		 * Mirror `PartialDate::tryParseStoredPartialDateString()` for hydrated entry strings.
		 *
		 * @param {string} raw
		 * @returns {{year: ?number, month: ?number, day: ?number}|null}
		 */
		parseStoredPartialTripleFromString(raw) {
			const trimmed =
				raw === undefined || raw === null ? '' : String(raw).trim();

			if (trimmed === '') {
				return { year: null, month: null, day: null };
			}

			let m = trimmed.match(/^XXXX-(\d{2})-(\d{2})$/u);

			if (m) {
				return {
					year: null,
					month: Number(m[1]),
					day: Number(m[2]),
				};
			}

			m = trimmed.match(/^XXXX-(\d{2})$/u);

			if (m) {
				return {
					year: null,
					month: Number(m[1]),
					day: null,
				};
			}

			m = trimmed.match(/^XXXX-XX-(\d{2})$/u);

			if (m) {
				return {
					year: null,
					month: null,
					day: Number(m[1]),
				};
			}

			m = trimmed.match(/^(\d{4})-XX-(\d{2})$/u);

			if (m) {
				return {
					year: Number(m[1]),
					month: null,
					day: Number(m[2]),
				};
			}

			m = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/u);

			if (m) {
				return {
					year: Number(m[1]),
					month: Number(m[2]),
					day: Number(m[3]),
				};
			}

			m = trimmed.match(/^(\d{4})-(\d{2})$/u);

			if (m) {
				return {
					year: Number(m[1]),
					month: Number(m[2]),
					day: null,
				};
			}

			m = trimmed.match(/^(\d{4})$/u);

			if (m) {
				return {
					year: Number(m[1]),
					month: null,
					day: null,
				};
			}

			return null;
		},

		computeMaxDay(year, month) {
			if (!month || month < 1 || month > 12) {
				return 31;
			}

			if (month !== 2) {
				const d = new CalendarDate(2020, month, 1);

				return d.calendar.getDaysInMonth(d);
			}

			if (year !== null && year !== undefined) {
				const d = new CalendarDate(year, 2, 1);

				return d.calendar.getDaysInMonth(d);
			}

			return 29;
		},

		clampDay(day, year, month) {
			if (day === null || day === undefined) {
				return null;
			}

			const max = this.computeMaxDay(year, month);

			if (day < 1) {
				return null;
			}

			return Math.min(day, max);
		},

		patch(partial) {
			const next = {
				...this.normalized,
				...partial,
			};

			if (!this.includeYear) {
				next.year = null;
			}

			if (!this.includeMonth) {
				next.month = null;
			}

			if (!this.includeDay) {
				next.day = null;
			}

			if (
				this.includeDay &&
				(Object.prototype.hasOwnProperty.call(partial, 'month') ||
					Object.prototype.hasOwnProperty.call(partial, 'year'))
			) {
				next.day = this.clampDay(next.day, next.year, next.month);
			}

			this.update(next);
		},

		onYearInput(raw) {
			const v = raw === '' || raw === null || raw === undefined ? null : Number(raw);

			const nextYear = Number.isFinite(v) ? v : null;

			if (nextYear === null) {
				const cleared = { year: null };

				if (this.includeMonth) {
					cleared.month = null;
				}

				if (this.includeDay) {
					cleared.day = null;
				}

				this.patch(cleared);

				return;
			}

			this.patch({ year: nextYear });
		},

		onMonthInput(val) {
			if (val === null || val === undefined || val === '') {
				const cleared = { month: null };

				// Preserve day when the year stays set (`YYYY-XX-DD`), otherwise drop day too
				if (
					this.includeDay &&
					!(
						this.includeYear &&
						this.normalized.year !== null &&
						this.normalized.year !== undefined
					)
				) {
					cleared.day = null;
				}

				this.patch(cleared);

				return;
			}
			const num = Number(val);

			this.patch({ month: Number.isFinite(num) ? num : null });
		},

		onDayInput(raw) {
			if (!this.includeDay) {
				return;
			}

			const v = raw === '' || raw === null || raw === undefined ? null : Number(raw);

			const num = Number.isFinite(v) ? v : null;

			this.patch({
				day: this.clampDay(num, this.normalized.year, this.normalized.month),
			});
		},
	},
};
</script>
