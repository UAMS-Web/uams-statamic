<template>
	<div
		class="contents"
		@keydown="$emit('keydown', $event)"
	>
		<template v-if="shouldApplyMask && !viewable">
			<ui-input-group v-bind="outerAttrs">
				<ui-input-group-prepend
					v-if="effectivePrepend"
					v-text="effectivePrepend"
				/>
				<div class="group/input relative block w-full st-text-legibility" data-ui-input>
					<input
						ref="input"
						:value="effectiveDisplayValue"
						:type="effectiveType"
						:id="id"
						:name="name"
						:placeholder="effectivePlaceholder"
						:readonly="readonly"
						:disabled="disabled"
						:required="effectiveRequired"
						:class="mergedInputClass"
						data-ui-control
						data-ui-group-target
						v-bind="mergedInputAttrs"
						@input="handleInput"
					/>
					<div
						v-if="limit"
						class="absolute inset-y-0 right-2 flex items-center"
					>
						<ui-character-counter
							:text="effectiveDisplayValue"
							:limit="limit"
						/>
					</div>
				</div>
				<ui-input-group-append
					v-if="effectiveAppend"
					v-text="effectiveAppend"
				/>
			</ui-input-group>
		</template>
		<Input
			v-else
			ref="coreInput"
			:model-value="effectiveDisplayValue"
			:type="effectiveType"
			:id="id"
			:name="name"
			:placeholder="effectivePlaceholder"
			:read-only="readonly"
			:disabled="disabled"
			:required="effectiveRequired"
			:prepend="effectivePrepend"
			:append="effectiveAppend"
			:limit="limit"
			:size="size"
			:variant="variant"
			:focus="effectiveAutofocus"
			:icon="icon"
			:icon-prepend="iconPrepend"
			:icon-append="iconAppend"
			:clearable="clearable"
			:copyable="copyable"
			:viewable="viewable"
			:loading="loading"
			:badge="badge"
			:input-attrs="mergedInputAttrs"
			:input-class="mergedInputClass"
			v-bind="outerAttrs"
			@update:model-value="onUpdate"
		>
			<template
				v-if="$slots.prepend"
				#prepend
			>
				<slot name="prepend" />
			</template>
			<template
				v-if="$slots.append"
				#append
			>
				<slot name="append" />
			</template>
		</Input>
	</div>
</template>

<script>
import { CharacterCounter, Input, publishContextKey } from '@statamic/cms/ui';
import CpInputClassesMixin from '../mixins/CpInputClassesMixin.js';
import InputAttributes from '../mixins/InputAttributes.js';
import IMaskMixin from '../mixins/IMaskMixin.js';
// `calculation_*` evaluation: pure helpers shared with the public-form Alpine
// engine at `resources/js/calculation-engine.js`. Anything that's not Vue
// reactivity glue should live in this lib, not inlined here.
import * as Calc from '../../../lib/calculation.js';

export default {
	inheritAttrs: false,

	mixins: [InputAttributes, IMaskMixin, CpInputClassesMixin],

	components: {
		CharacterCounter,
		Input
	},

	inject: {
		publishContext: { from: publishContextKey }
	},

	props: {
		/** Current value (string, number, or object e.g. { masked, unmasked }) */
		value: { type: [String, Number, Object], default: '' },
		/** HTML input type (e.g. text, email); fallback from config.input_type */
		type: { type: String, default: 'text' },
		/** HTML id for the input element */
		id: { type: String, default: undefined },
		/** Form field name for the input */
		name: { type: String, default: undefined },
		/** Placeholder text; fallback from config.placeholder */
		placeholder: { type: String, default: undefined },
		/** When true, the input is not editable */
		readonly: { type: Boolean, default: false },
		/** When true, the input is disabled */
		disabled: { type: Boolean, default: false },
		/** Text shown before the input; fallback from config.prepend */
		prepend: { type: String, default: null },
		/** Text shown after the input; fallback from config.append */
		append: { type: String, default: null },
		/** UI size (e.g. base, sm) for styling */
		size: { type: String, default: 'base' },
		/** UI variant (e.g. default) for styling */
		variant: { type: String, default: 'default' },
		/** Max character count; when set, character counter is shown */
		limit: { type: Number, default: null },
		/** When true, the input receives focus on mount */
		autofocus: { type: Boolean, default: false },
		/** Extra attributes to apply to the underlying input element */
		inputAttrs: { type: [Object, String], default: () => ({}) },
		/** Extra CSS class(es) for the underlying input element */
		inputClass: { type: String, default: '' },
		/** Configuration object from field blueprint (placeholder, prepend, append, calculation, etc.) */
		config: { type: Object, default: () => ({}) },
		/** Base class for the input, typically set by wrapper components */
		baseClass: { type: String, default: '' },
		/** Additional classes from parent, merged with baseClass and inputClass */
		classes: { type: [String, Array, Object], default: '' },
		/** When true, select all input content on mount */
		autoselect: { type: Boolean, default: false },
		/** Icon name (before text). See Statamic UI Icon docs for names. */
		icon: { type: String, default: null },
		/** Icon name before the text. */
		iconPrepend: { type: String, default: null },
		/** Icon name after the text. */
		iconAppend: { type: String, default: null },
		/** When true, shows a clear button to empty the input */
		clearable: { type: Boolean, default: false },
		/** When true, shows a copy button to copy the value to clipboard */
		copyable: { type: Boolean, default: false },
		/** When true, shows an eye icon to toggle password visibility */
		viewable: { type: Boolean, default: false },
		/** When true, shows an animated loading indicator next to the input */
		loading: { type: Boolean, default: false },
		/** Badge text to display on the right side */
		badge: { type: String, default: null },
		/** When true, the input is required */
		required: { type: Boolean, default: false }
	},

	emits: ['update:modelValue', 'keydown', 'focus', 'blur'],

	data() {
		return {
			calculationError: null,
			unmaskedValue: null,
			_innerInputEl: null,
			_focusHandler: null,
			_blurHandler: null
		};
	},

	computed: {
		// --- Display value (masked or plain); used when not calculating
		displayValue() {
			if (typeof this.value === 'object' && this.value !== null && 'masked' in this.value) {
				return this.value.masked ?? '';
			}
			return this.value ?? '';
		},

		// --- Config-based fallbacks for props passed to Input
		effectiveType() {
			return this.type || this.config?.input_type || 'text';
		},
		effectivePlaceholder() {
			return this.placeholder ?? this.config?.placeholder ?? undefined;
		},
		effectivePrepend() {
			return this.prepend ?? this.config?.prepend ?? null;
		},
		effectiveAppend() {
			return this.append ?? this.config?.append ?? null;
		},
		effectiveRequired() {
			return this.required || this.config?.required === true;
		},
		/** Whether the core Input should receive focus on mount (autofocus or mixin focus prop) */
		effectiveAutofocus() {
			if (this.autofocus) {
				return true;
			}
			return this.focus === true;
		},

		// --- Merge $attrs into outer (non-input); exclude input keys and events we handle ourselves
		outerAttrs() {
			const inputKeys = [
				'accept', 'autocomplete', 'autofocus', 'capture', 'checked', 'dirname', 'form',
				'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget',
				'list', 'max', 'maxlength', 'min', 'minlength', 'multiple', 'name', 'pattern',
				'readonly', 'required', 'size', 'src', 'step', 'tabindex', 'value'
			];
			const handledEvents = ['keydown', 'focus', 'blur'];
			const result = {};
			const attrs = this.$attrs || {};
			for (const key in attrs) {
				if (inputKeys.includes(key.toLowerCase()) || handledEvents.includes(key)) {
					continue;
				}
				result[key] = attrs[key];
			}
			return result;
		},
		/**
		 * HTML attributes derived from InputAttributes mixin (computed and props).
		 * Excludes id, name, type, placeholder, value, readonly, required (passed as
		 * Input props) and size (conflicts with our UI size prop).
		 */
		inputAttrsFromMixin() {
			const out = {};
			const set = (key, value) => {
				if (value !== null && value !== undefined && value !== '') {
					out[key] = value;
				}
			};
			// Mixin computeds (input-type-aware)
			set('accept', this.accept_computed);
			set('alt', this.alt_computed);
			set('autocapitalize', this.autocapitalize_input_computed);
			set('autocomplete', this.autocomplete_computed);
			set('capture', this.capture_computed);
			set('checked', this.checked_computed);
			set('dirname', this.dirname_computed);
			set('formaction', this.formaction_computed);
			set('formenctype', this.formenctype_computed);
			set('formmethod', this.formmethod_computed);
			set('formnovalidate', this.formnovalidate_computed);
			set('formtarget', this.formtarget_computed);
			set('height', this.height_computed);
			set('list', this.list_computed);
			set('max', this.max_computed);
			set('maxlength', this.maxlength_computed);
			set('min', this.min_computed);
			set('minlength', this.minlength_computed);
			set('multiple', this.multiple_computed);
			set('pattern', this.pattern_computed);
			set('popovertarget', this.popovertarget_computed);
			set('popovertargetaction', this.popovertargetaction_computed);
			set('src', this.src_computed);
			set('step', this.step_computed);
			set('width', this.width_computed);
			// HTML size only when numeric (mixin size conflicts with our UI size)
			const sizeVal = this.size_computed;
			if (sizeVal != null && String(sizeVal).match(/^\d+$/)) {
				out.size = sizeVal;
			}
			// Mixin props: form and global/ARIA attributes
			set('form', this.form);
			set('accesskey', this.accesskey);
			set('contenteditable', this.contenteditable);
			set('dir', this.direction);
			set('draggable', this.draggable);
			set('enterkeyhint', this.enterkeyhint);
			set('exportparts', this.exportparts);
			set('hidden', this.hidden);
			set('inert', this.inert);
			set('inputmode', this.inputmode);
			set('itemid', this.itemid);
			set('itemprop', this.itemprop);
			set('itemref', this.itemref);
			set('itemscope', this.itemscope);
			set('itemtype', this.itemtype);
			set('lang', this.lang);
			set('nonce', this.nonce);
			set('part', this.part);
			set('popover', this.popover);
			set('role', this.role);
			set('slot', this.slot);
			set('spellcheck', this.spellcheck);
			set('style', this.style);
			set('tabindex', this.tabindex);
			set('title', this.title);
			set('translate', this.translate);
			set('virtualkeyboardpolicy', this.virtualkeyboardpolicy);
			set('writingsuggestions', this.writingsuggestions);
			set('anchor', this.anchor);
			set('autocorrect', this.autocorrect);
			return out;
		},
		mergedInputAttrs() {
			const inputKeys = [
				'accept', 'autocomplete', 'autofocus', 'capture', 'checked', 'dirname', 'form',
				'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget',
				'list', 'max', 'maxlength', 'min', 'minlength', 'multiple', 'name', 'pattern',
				'readonly', 'required', 'size', 'src', 'step', 'tabindex', 'value'
			];
			const fromAttrs = {};
			const attrs = this.$attrs || {};
			for (const key in attrs) {
				if (inputKeys.includes(key.toLowerCase())) {
					fromAttrs[key] = attrs[key];
				}
			}
			const fromConfig = {};
			if (this.config?.maxlength != null) fromConfig.maxlength = this.config.maxlength;
			if (this.config?.autocomplete != null) fromConfig.autocomplete = this.config.autocomplete;
			const raw = this.inputAttrs;
			const normalized = typeof raw === 'string'
				? raw.split(' ').filter((a) => a.length > 0).reduce((acc, a) => ({ ...acc, [a]: true }), {})
				: { ...raw };
			return {
				...this.inputAttrsFromMixin,
				...fromAttrs,
				...fromConfig,
				...normalized
			};
		},
		/** Default input styling when mask branch is shown; from CpInputClassesMixin */
		defaultInputClasses() {
			return this.cpInputClasses;
		},
		mergedInputClass() {
			const classes = this.classes || (this.shouldApplyMask ? this.defaultInputClasses : '');
			const parts = [this.baseClass, classes, this.inputClass].filter(Boolean);
			return parts.join(' ');
		},

		// --- Calculation: value shown in the input when calculation is enabled.
		// Reactivity (computed / watch / lifecycle) lives in this component;
		// the actual math/text/format/security logic lives in `@/lib/calculation`
		// so the public-form Alpine engine can reuse the same code paths.
		shouldCalculate() {
			return Calc.shouldCalculate(this.config);
		},
		calculationType() {
			return Calc.resolveCalculationType(this.config);
		},
		shouldCalculateMath() {
			return this.shouldCalculate && this.calculationType === 'math';
		},
		shouldCalculateText() {
			return this.shouldCalculate && this.calculationType === 'text';
		},
		calculationFormula() {
			return Calc.resolveCalculationFormula(this.config);
		},
		calculationDecimals() {
			return Calc.resolveCalculationDecimals(this.config);
		},
		currentFormValues() {
			try {
				if (!this.shouldCalculate) return {};
				const valuesRef = this.publishContext?.values;
				if (!valuesRef) return {};
				const values = typeof valuesRef === 'object' && 'value' in valuesRef ? valuesRef.value : valuesRef;
				return values || {};
			} catch (error) {
				console.error('CustomInput: Error accessing form values:', error);
				return {};
			}
		},
		calculatedDisplayValue() {
			if (!this.shouldCalculate) return this.displayValue;
			try {
				const result = this.calculateValue();
				return Calc.formatCalculatedValue(result, this.calculationType, this.calculationDecimals);
			} catch (error) {
				console.error('CustomInput: Calculation error:', error);
				this.calculationError = `Calculation error: ${error.message}`;
				return this.displayValue;
			}
		},
		effectiveDisplayValue() {
			return this.shouldCalculate ? this.calculatedDisplayValue : this.displayValue;
		}
	},

	watch: {
		currentFormValues: {
			handler() {
				if (this.shouldCalculate) this.recalculate();
			},
			deep: true
		},
		calculationFormula() {
			if (this.shouldCalculate) this.recalculate();
		},
		config: {
			handler() {
				if (this.shouldCalculate) this.recalculate();
			},
			deep: true
		}
	},

	methods: {
		onUpdate(payload) {
			this.$emit('update:modelValue', payload);
		},
		/** Delegates to handleMaskedInput when masking/transform_case, else emits value. */
		handleInput(event) {
			if (this.shouldApplyMask) {
				this.handleMaskedInput(event);
				return;
			}
			this.onUpdate(event.target.value);
		},
		/** Called by IMask mixin when mask emits; emit { masked, unmasked } for parent. */
		handleInputUpdate(maskedValue, unmaskedValue) {
			this.unmaskedValue = unmaskedValue;
			this.$emit('update:modelValue', { masked: maskedValue, unmasked: unmaskedValue });
		},
		/** Override mixin: when no IMask (transform_case only), apply case and emit. */
		handleMaskedInput(event) {
			if (this.imask) return;
			let value = event.target.value;
			if (this.transform_case === 'uppercase') value = value.toUpperCase();
			else if (this.transform_case === 'lowercase') value = value.toLowerCase();
			this.$emit('update:modelValue', value);
		},
		/**
		 * Move keyboard focus to the underlying input.
		 * Named focusInput so it does not collide with the InputAttributes `focus` prop.
		 */
		focusInput() {
			if (this.shouldApplyMask && this.$refs.input) {
				this.$refs.input.focus();
			} else {
				this.$refs.coreInput?.focus();
			}
		},
		select() {
			if (this.shouldApplyMask && this.$refs.input) {
				this.$refs.input.select();
			} else {
				this.$refs.coreInput?.select();
			}
		},

		/** Top-level calculation dispatch. Reads config and current form scope,
		 *  delegates to the shared pure helpers in `@/lib/calculation`. */
		calculateValue() {
			return Calc.calculateValue(this.config, this.currentFormValues);
		},
		/** Whether the active formula references any `[get_param:name]` token.
		 *  Used by `mounted`/`beforeUnmount`/`handleUrlChange` to gate the
		 *  `popstate` listener. */
		hasUrlParameters() {
			return Calc.hasUrlParameters(this.calculationFormula);
		},
		recalculate() {
			this.calculationError = null;
			const result = this.calculateValue();
			if (result !== null) {
				this.$emit('update:modelValue', result);
			}
		},
		handleUrlChange() {
			if (this.shouldCalculate && this.hasUrlParameters()) {
				this.$nextTick(() => this.recalculate());
			}
		},
		attachFocusBlurListeners() {
			const inputEl = this.shouldApplyMask
				? this.$refs.input
				: (this.$refs.coreInput?.$el?.querySelector?.('input') ?? this.$refs.coreInput?.$el?.querySelector?.('[data-ui-control]'));
			if (!inputEl || this._innerInputEl) {
				return;
			}
			this._innerInputEl = inputEl;
			this._focusHandler = () => this.$emit('focus');
			this._blurHandler = () => this.$emit('blur');
			inputEl.addEventListener('focus', this._focusHandler);
			inputEl.addEventListener('blur', this._blurHandler);
		},
		detachFocusBlurListeners() {
			if (!this._innerInputEl || !this._focusHandler || !this._blurHandler) {
				return;
			}
			this._innerInputEl.removeEventListener('focus', this._focusHandler);
			this._innerInputEl.removeEventListener('blur', this._blurHandler);
			this._innerInputEl = null;
			this._focusHandler = null;
			this._blurHandler = null;
		}
	},

	mounted() {
		const autoselectVal = this.autoselect || this.config?.autoselect;
		if (autoselectVal) {
			this.$nextTick(() => {
				if (this.shouldApplyMask && this.$refs.input) {
					this.$refs.input.select();
				} else {
					this.$refs.coreInput?.select();
				}
			});
		}
		if (this.shouldCalculate) {
			this.recalculate();
		}
		if (this.shouldCalculate && this.hasUrlParameters()) {
			window.addEventListener('popstate', this.handleUrlChange);
		}
		this.$nextTick(() => this.attachFocusBlurListeners());
	},

	beforeUnmount() {
		if (this.shouldCalculate && this.hasUrlParameters()) {
			window.removeEventListener('popstate', this.handleUrlChange);
		}
		this.detachFocusBlurListeners();
	}
};
</script>
