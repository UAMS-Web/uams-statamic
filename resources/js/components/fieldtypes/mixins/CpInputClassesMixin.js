/**
 * Shared CP (Control Panel) input styling. Class values are kept in sync with
 * Statamic's UI components so custom inputs (e.g. mask branch, time picker)
 * match the default Control Panel appearance.
 *
 * Sources (vendor):
 *   - Standard text input: vendor/statamic/cms/resources/js/components/ui/Input/Input.vue
 *     (inputClasses computed: base + size.base variant, lines 95-131)
 *   - Time picker root: vendor/statamic/cms/resources/js/components/ui/TimePicker/TimePicker.vue
 *     (TimeFieldRoot :class array, lines 43-50; disabled/readOnly props: statamic/cms#14620)
 *
 * Use the mixin in Options API components, or import the constants in script-setup.
 */

/** Standard text input classes. Source: Input.vue inputClasses (base + size.base). */
const CP_INPUT_CLASSES = [
	'w-full block bg-white dark:bg-gray-900',
	'border border-gray-300 with-contrast:border-gray-500 dark:border-gray-700 dark:with-contrast:border-gray-500 dark:inset-shadow-2xs dark:inset-shadow-black',
	'text-gray-925 dark:text-gray-300 placeholder:text-gray-500 dark:placeholder:text-gray-400/85',
	'appearance-none antialiased shadow-ui-sm disabled:shadow-none disabled:opacity-50 read-only:border-dashed not-prose',
	'text-base rounded-lg px-3 py-2 h-10 leading-[1.375rem]',
].join(' ');

/** Time picker root classes (always applied). Source: TimePicker.vue lines 44-48. */
export const CP_TIME_PICKER_CLASSES = [
	'flex items-center w-full bg-white dark:bg-gray-900',
	'border border-gray-300 dark:border-gray-700',
	'leading-5 text-gray-600 dark:text-gray-300',
	'shadow-ui-sm not-prose h-10 rounded-lg py-2 px-3 disabled:shadow-none',
	'data-invalid:border-red-500',
];

/**
 * Extra TimeFieldRoot classes when `disabled` is true (TimePicker.vue line 49).
 *
 * @type {string}
 */
export const CP_TIME_PICKER_DISABLED_CLASSES = 'disabled:shadow-none disabled:opacity-50';

/**
 * Extra TimeFieldRoot classes when `readOnly` is true (TimePicker.vue line 50).
 *
 * @type {string}
 */
export const CP_TIME_PICKER_READ_ONLY_CLASSES = 'border-dashed';

export default {
	computed: {
		/** Standard CP text input classes (matches Statamic Input.vue) */
		cpInputClasses() {
			return CP_INPUT_CLASSES;
		},
	},
	methods: {
		/**
		 * Time picker root classes for :class (mirrors Statamic TimePicker
		 * TimeFieldRoot).
		 *
		 * @param {{ disabled?: boolean, readOnly?: boolean }} [options]  Match
		 *     Statamic when the control is non-interactive (statamic/cms#14620).
		 * @returns {string[]}
		 */
		cpTimePickerClasses(options = {}) {
			// Start from the same base stack as Statamic's TimeFieldRoot
			const classes = [...CP_TIME_PICKER_CLASSES];

			// Append disabled visual tail when mirroring a disabled picker
			if (options.disabled) {
				classes.push(CP_TIME_PICKER_DISABLED_CLASSES);
			}

			// Append read-only border treatment when mirroring a read-only picker
			if (options.readOnly) {
				classes.push(CP_TIME_PICKER_READ_ONLY_CLASSES);
			}

			return classes;
		},
	},
};

export { CP_INPUT_CLASSES };
