import IMaskLibrary from 'imask';

/**
 * Convert a RegExp-source string (optionally `/…/`-wrapped) to a RegExp.
 * Mirrors the MaskedRegExp string→RegExp handling — declarative, never eval'd.
 *
 * @param {string} source
 * @returns {RegExp | string} A RegExp, or the original string when it is not a valid pattern.
 */
export function toRegExpFromSource(source) {
	let pattern = source;

	if (pattern.length > 1 && pattern.startsWith('/') && pattern.endsWith('/')) {
		pattern = pattern.slice(1, -1);
	}

	try {
		return new RegExp(pattern);
	} catch (error) {
		console.warn('IMaskMixin - Invalid RegExp source in definitions:', error, 'Source:', source);

		return source;
	}
}

/**
 * Build an IMask `definitions` object from a parsed JSON map, converting each
 * RegExp-source string value to a RegExp.
 *
 * @param {Record<string, unknown>} parsed
 * @returns {Record<string, unknown>}
 */
export function parseDefinitions(parsed) {
	const definitions = {};

	for (const [key, val] of Object.entries(parsed)) {
		definitions[key] = typeof val === 'string' ? toRegExpFromSource(val) : val;
	}

	return definitions;
}

/**
 * Parse an `input_mask_definitions` config string into an IMask definitions
 * object. The value is **JSON-parsed, never eval'd** — this replaces the former
 * `new Function(\`return ${value}\`)()`, so an admin-supplied code string can no
 * longer execute arbitrary JS. RegExp-source string values become RegExps.
 *
 * @param {string} value  The raw config string (expected JSON, e.g. `{"#":"[0-9X]"}`).
 * @returns {Record<string, unknown> | undefined}  The definitions object, or
 *          `undefined` when the value is empty or not valid JSON.
 */
export function parseDefinitionsConfig(value) {
	if (typeof value !== "string" || !value.trim()) {
		return undefined;
	}

	try {
		const parsed = JSON.parse(value);

		return parsed && typeof parsed === "object" ? parseDefinitions(parsed) : parsed;
	} catch (error) {
		console.warn("IMaskMixin - Error parsing definitions:", error, "Value:", value);

		return undefined;
	}
}

/**
 * IMask Mixin for Vue Components
 *
 * Provides input masking functionality using the IMask library. The library is
 * imported statically so it matches other CP entry points (e.g. MaskFieldtype)
 * and avoids ineffective dynamic import() when Rollup already pulls imask into
 * the same chunk.
 *
 * Mask instances are still created only when input_mask is enabled or
 * transform_case is specified.
 *
 * MaskedRegExp Support:
 *   - Uses IMask's MaskedRegExp class for regex-based input validation
 *   - Supports both slash-wrapped (/pattern/) and unwrapped (pattern) regex
 *     formats
 *   - Automatically converts string patterns to RegExp objects
 *   - Provides configurable options for overwrite, eager, skipInvalid, and autofix
 *   - Validates input against the regex pattern in real-time
 *   - Skips invalid characters by default to provide smooth user experience
 */
export default {
	// Mixin definition for IMask functionality
	props: {
		// Enable input masking (boolean)
		input_mask: {
			type: Boolean,
			default: false
		},

		// Input mask class type
		input_mask_class: {
			type: String,
			default: 'MaskedPattern'
		},

		// IMask configuration options
		input_mask_append_text: { default: undefined },
		input_mask_append_text_enabled: { default: false },
		input_mask_autofix: { default: undefined },
		input_mask_blocks: { default: undefined },
		input_mask_compiledMasks: { default: [] },
		input_mask_currentMask: { default: undefined },
		input_mask_definitions: { default: {} },
		input_mask_displayChar: { default: undefined },
		input_mask_eager: { default: undefined },
		input_mask_enum: { default: undefined },
		input_mask_exposeMask: { default: undefined },
		input_mask_from: { default: undefined },
		input_mask_lazy: { default: true },
		input_mask_mapToRadix: { default: [] },
		input_mask_mask: { default: undefined },
		input_mask_max: { default: undefined },
		input_mask_maxLength: { default: undefined },
		input_mask_min: { default: undefined },
		input_mask_normalizeZeros: { default: false },
		input_mask_overwrite: { default: undefined },
		input_mask_padFractionalZeros: { default: false },
		input_mask_parent: { default: undefined },
		input_mask_pattern: { default: 'd{.}`m{.}`Y' },
		input_mask_placeholderChar: { default: '_' },
		input_mask_prepareChar: { default: undefined },
		// Declarative case transform ('uppercase' | 'lowercase') — replaces the
		// former `prepareChar` JS-snippet config with a built-in, non-eval'd transform.
		input_mask_prepare_case: { default: undefined },
		input_mask_prepend_text: { default: undefined },
		input_mask_prepend_text_enabled: { default: false },
		input_mask_radix: { default: '.' },
		input_mask_repeat: { default: undefined },
		input_mask_scale: { default: 0 },
		input_mask_skipInvalid: { default: true },
		input_mask_thousandsSeparator: { default: ',' },
		input_mask_to: { default: undefined },

		// Case transformation: 'uppercase', 'lowercase', or 'none'
		transform_case: {
			type: String,
			default: 'none'
		},

		// Control whether to store masked or unmasked value
		input_mask_store_unmasked: {
			type: Boolean,
			default: false
		},
	},

	// Data properties for IMask instance and library
	data() {
		return {
			imask: null, // Holds the IMask instance (lowercase) - the actual mask object that controls input behavior
			IMask: null, // Holds the IMask library/constructor (uppercase) - the class used to create mask instances, will be set conditionally
		};
	},

	// Computed properties for mask logic
	computed: {
		// Use config fallback when component receives config (e.g. CustomInput) but mask props
		// are not passed explicitly (so mixin props keep defaults).
		effectiveInputMask() {
			return this.input_mask ?? this.config?.input_mask ?? false;
		},
		effectiveInputMaskClass() {
			return this.input_mask_class ?? this.config?.input_mask_class ?? 'MaskedPattern';
		},

		// Determine if masking or case transformation is needed
		shouldApplyMask() {
			// Determine if masking should be applied (use effective so config-driven mask is applied)
			return this.effectiveInputMask || this.transform_case !== 'none';
		},

		// Create mask configuration from props (and config fallback)
		maskConfig() {
			// Return null if masking is not enabled (use effective so config-driven mask is applied)
			if (!this.effectiveInputMask) {
				return null;
			}

			// Build config object for IMask
			const config = {};

			// List of supported IMask options. The function-callback options
			// (commit, dispatch, format, matchValue, parse, prepare, validate) are
			// not config props: UAMS-Web/uams-statamic-archive#892 removed their `new Function` eval and UAMS-Web/uams-statamic-archive#1019
			// removed their now-inert config fields/props. `prepareChar` is still
			// set, but from the declarative `input_mask_prepare_case` option below
			// — never code.
			const maskOptions = [
				'autofix',
				'blocks',
				'compiledMasks',
				'currentMask',
				'definitions',
				'displayChar',
				'eager',
				'enum',
				'exposeMask',
				'from',
				'lazy',
				'mapToRadix',
				'mask',
				'max',
				'maxLength',
				'min',
				'normalizeZeros',
				'overwrite',
				'padFractionalZeros',
				'parent',
				'pattern',
				'placeholderChar',
				'radix',
				'repeat',
				'scale',
				'skipInvalid',
				'thousandsSeparator',
				'to'
			];

			// Iterate through all possible options (use prop or config fallback so config-driven mask works)
			maskOptions.forEach(option => {
				// Build the prop name for each option
				const propName = `input_mask_${option}`;

				// Use direct prop or config fallback (e.g. CustomInput passes config only)
				let value = this[propName] ?? this.config?.[propName];

				// Only process if the value is defined
				if (value !== undefined) {

					// For certain options, convert null to undefined
					if (value === null && ['blocks', 'compiledMasks', 'currentMask', 'definitions', 'enum', 'mapToRadix'].includes(option)) {
						value = undefined;
					}

					// Parse string values for options that expect objects, arrays, or functions
					if (typeof value === 'string') {
						// Handle array options that may come as JSON strings
						if (['enum', 'mapToRadix'].includes(option)) {
							// Attempt to parse as JSON if value is a string
							try {
								// Attempt to parse JSON for enum/mapToRadix
								value = JSON.parse(value);
							} catch (error) {
								// Log warning if parsing fails
								console.warn(`IMaskMixin - Error parsing ${option} as JSON:`, error, 'Value:', value);
							}
						}

						// Handle object options that come as JSON strings. Parsed
						// declaratively — never eval'd. `definitions` values are
						// RegExp-source strings, converted to RegExp.
						else if (['blocks', 'definitions', 'compiledMasks'].includes(option)) {
							if (value.trim()) {
								if (option === 'definitions') {
									const parsed = parseDefinitionsConfig(value);
									if (parsed !== undefined) {
										value = parsed;
									}
								} else {
									try {
										value = JSON.parse(value);
									} catch (error) {
										// Log warning if parsing fails
										console.warn(`IMaskMixin - Error parsing ${option}:`, error, 'Value:', value);
									}
								}
							}
						}

						// Handle mask option for different mask classes
						else if (option === 'mask') {
							// If MaskedRegExp, convert string to RegExp
							if (this.effectiveInputMaskClass === 'MaskedRegExp') {
								// Handle both formats: with slashes and without
								if (typeof value === 'string') {
									let pattern = value;
									// Remove slashes if present
									if (pattern.startsWith('/') && pattern.endsWith('/')) {
										pattern = pattern.slice(1, -1);
									}
									// Create RegExp object
									try {
										value = new RegExp(pattern);
									} catch (error) {
										// Log warning if RegExp creation fails
										console.warn('IMaskMixin - Error creating RegExp for MaskedRegExp:', error, 'Pattern:', pattern);
										// Set to undefined to prevent invalid mask creation
										value = undefined;
									}
								}
							}

							// If MaskedNumber, set to Number constructor
							else if (this.effectiveInputMaskClass === 'MaskedNumber') {
								value = Number;
							}

							// If MaskedDate, set to Date constructor
							else if (this.effectiveInputMaskClass === 'MaskedDate') {
								value = Date;
							}

							// If MaskedDynamic, parse array of mask configs
							else if (this.effectiveInputMaskClass === 'MaskedDynamic') {
								try {
									// Parse as JSON if value is a string
									if (typeof value === 'string') {
										value = JSON.parse(value);
									}
								} catch (error) {
									// Log warning if parsing fails
									console.warn('IMaskMixin - Error parsing MaskedDynamic mask array:', error, 'Value:', value);
								}
							}
						}

						// Handle max/min options for different mask classes
						else if (['max', 'min'].includes(option)) {
							// For MaskedDate, convert string to Date
							if (this.effectiveInputMaskClass === 'MaskedDate') {
								if (typeof value === 'string' && value.trim()) {
									try {
										// Try to create Date object
										value = new Date(value);
									} catch (error) {
										// Log warning if parsing fails
										console.warn(`IMaskMixin - Error parsing ${option} as Date:`, error, 'Value:', value);
									}
								}
							}

							// For MaskedNumber and MaskedRange, convert to number
							else {
								const numValue = parseFloat(value);
								if (!isNaN(numValue)) {
									value = numValue;
								}
							}
						}

						// Handle numeric options (scale, maxLength, from, to)
						else if (['scale', 'maxLength', 'from', 'to'].includes(option)) {
							// Convert to number if possible
							const numValue = parseFloat(value);
							if (!isNaN(numValue)) {
								value = numValue;
							}
						}
					}

					// Only add to config if value is meaningful
					if (value !== undefined && value !== null) {
						config[option] = value;
					}
				}
			});

			// Declarative case transform for masked input — installs a built-in
			// `prepareChar` (no user code is ever evaluated). Replaces the legacy
			// `input_mask_prepareChar: 'return chars.toUpperCase();'` JS snippet.
			const prepareCase = this.input_mask_prepare_case ?? this.config?.input_mask_prepare_case;
			if (prepareCase === 'uppercase') {
				config.prepareChar = (chars) => chars.toUpperCase();
			} else if (prepareCase === 'lowercase') {
				config.prepareChar = (chars) => chars.toLowerCase();
			}

			// Special handling for MaskedNumber defaults
			if (this.effectiveInputMaskClass === 'MaskedNumber') {
				// Always set mask to Number for MaskedNumber
				config.mask = Number;

				// Set default scale if not provided
				if (config.scale === undefined || config.scale === null) {
					config.scale = 0;
				}

				// Set default radix if not provided
				if (config.radix === undefined || config.radix === null) {
					config.radix = '.';
				}

				// Set default thousandsSeparator if not provided
				if (config.thousandsSeparator === undefined || config.thousandsSeparator === null) {
					config.thousandsSeparator = ',';
				}

				// Set default padFractionalZeros if not provided
				if (config.padFractionalZeros === undefined || config.padFractionalZeros === null) {
					config.padFractionalZeros = false;
				}

				// Set default normalizeZeros if not provided
				if (config.normalizeZeros === undefined || config.normalizeZeros === null) {
					config.normalizeZeros = false;
				}

				// Set default autofix if not provided
				if (config.autofix === undefined || config.autofix === null) {
					config.autofix = false;
				}

				// Set default eager if not provided
				if (config.eager === undefined || config.eager === null) {
					config.eager = false;
				}

				// Set default overwrite if not provided
				if (config.overwrite === undefined || config.overwrite === null) {
					config.overwrite = false;
				}

				// Remove pattern for MaskedNumber
				delete config.pattern;
			}

			// Special handling for MaskedDate defaults
			else if (this.effectiveInputMaskClass === 'MaskedDate') {
				// Set default pattern for MaskedDate if not provided
				if (!config.pattern) {
					config.pattern = 'd{.}`m{.}`Y';
				}
			}

			// Special handling for MaskedPattern defaults
			else if (this.effectiveInputMaskClass === 'MaskedPattern') {
				// Set default lazy for MaskedPattern
				if (config.lazy === undefined) {
					config.lazy = true;
				}

				// Set default placeholderChar for MaskedPattern
				if (config.placeholderChar === undefined) {
					config.placeholderChar = '_';
				}

				// Set default skipInvalid for MaskedPattern
				if (config.skipInvalid === undefined) {
					config.skipInvalid = true;
				}
			}

			// Special handling for MaskedRegExp defaults
			else if (this.effectiveInputMaskClass === 'MaskedRegExp') {
				// Set default skipInvalid for MaskedRegExp
				if (config.skipInvalid === undefined) {
					config.skipInvalid = true;
				}

				// Set default overwrite for MaskedRegExp
				if (config.overwrite === undefined) {
					config.overwrite = false;
				}

				// Set default eager for MaskedRegExp
				if (config.eager === undefined) {
					config.eager = false;
				}

				// Set default autofix for MaskedRegExp
				if (config.autofix === undefined) {
					config.autofix = false;
				}
			}

			// Add custom definitions for pattern masks
			if (this.effectiveInputMaskClass === 'MaskedPattern') {
				config.definitions = {
					...config.definitions,
					'#': /[a-zA-Z0-9]/, // Custom definition: any letter or digit
					'@': /[a-zA-Z0-9-_]/, // Custom definition: any letter, digit, dash, or underscore
				};
			}

			// Handle prepend/append text configuration (use config fallback)
			const prependEnabled = (this.input_mask_prepend_text_enabled ?? this.config?.input_mask_prepend_text_enabled) === true;
			const appendEnabled = (this.input_mask_append_text_enabled ?? this.config?.input_mask_append_text_enabled) === true;
			const prependText = prependEnabled ? ((this.input_mask_prepend_text ?? this.config?.input_mask_prepend_text) || '') : '';
			const appendText = appendEnabled ? ((this.input_mask_append_text ?? this.config?.input_mask_append_text) || '') : '';

			// If either prepend or append is enabled, restructure the mask using array format
			if (prependEnabled || appendEnabled) {
				// Create a block name for the original mask
				const blockName = 'value';

				// Create a clean block configuration with only the necessary properties
				const blockConfig = {
					...config, // Preserve all original config options before removing/overriding for the block
					// Remove properties that should not be in the block
					lazy: undefined
				};

				// Remove undefined properties and the blocks property (since we're creating a new blocks object)
				Object.keys(blockConfig).forEach(key => {
					if (blockConfig[key] === undefined || key === 'blocks') {
						delete blockConfig[key];
					}
				});

				// Create the new mask configuration with array format
				const newConfig = {
					...config, // Spread all original config options first so we can override mask below
					mask: [
						{ mask: '' }, // Empty mask for empty input
						{
							mask: `${prependText}${blockName}${appendText}`,
							lazy: false, // Show prepend/append immediately
							blocks: {
								[blockName]: blockConfig
							}
						}
					]
				};

				return newConfig;
			}

			// Return the final config object
			return config;
		},
	},

	// Watchers for prop changes
	watch: {
		// When value prop changes, update mask value
		value(newValue) {
			// Check if mask exists and value has actually changed
			if (this.imask) {
				// Extract the actual string value to set on IMask
				let stringValue = '';

				// Handle different value types
				if (typeof newValue === 'object' && newValue !== null) {
					// If it's an object with masked property, use that
					stringValue = newValue.masked || '';
				} else if (typeof newValue === 'string') {
					// If it's already a string, use it directly
					stringValue = newValue;
				} else {
					// Convert to string for other types
					stringValue = String(newValue || '');
				}

				// Only update if the value has actually changed
				if (stringValue !== this.imask.value) {
					// Try to set the new value on the mask
					try {
						this.imask.value = stringValue;
					} catch (error) {
						// Log warning if setting value fails
						console.warn('IMaskMixin - Error setting mask value:', error);
					}
				}
			}
		},

		// When mask config becomes available after mount (e.g. config arrived late),
		// create the mask so currency/mask fieldtypes work when effectiveConfig
		// updates on first render.
		effectiveInputMask: {
			handler() {
				if (this.shouldApplyMask && this.effectiveInputMask && this.maskConfig && !this.imask) {
					this.$nextTick(() => {
						this.initializeMask();
					});
				}
			},
			immediate: false
		},
		maskConfig: {
			handler() {
				if (this.shouldApplyMask && this.effectiveInputMask && this.maskConfig && !this.imask) {
					this.$nextTick(() => {
						this.initializeMask();
					});
				}
			},
			immediate: false,
			deep: true
		}
	},

	// Methods for mask lifecycle and input handling
	methods: {
		/**
		 * Initialize the IMask instance
		 * Assigns the shared IMask constructor when first needed
		 */
		initializeMask() {
			// If mask not needed or input ref missing, exit early
			if (!this.shouldApplyMask) {
				return;
			}
			if (!this.$refs.input) {
				return;
			}

			// Use the statically imported constructor (same module graph as MaskFieldtype)
			if (!this.IMask) {
				this.IMask = IMaskLibrary;
			}

			// If masking is enabled and config is present, create mask (use effective for config-driven)
			if (!this.effectiveInputMask || !this.maskConfig) {
				return;
			}
			// Avoid creating a second mask if watcher and nextTick both run
			if (this.imask) {
				return;
			}
			// If no mask is defined (except for MaskedDynamic), warn and exit
			if (!this.maskConfig.mask && this.effectiveInputMaskClass !== 'MaskedDynamic') {
				console.warn('IMaskMixin - No mask defined for', this.effectiveInputMaskClass);
				return;
			}

				// Additional validation for MaskedRegExp
				if (this.effectiveInputMaskClass === 'MaskedRegExp' && !(this.maskConfig.mask instanceof RegExp)) {
					// Log warning for invalid RegExp mask
					console.warn('IMaskMixin - Invalid RegExp mask for MaskedRegExp:', this.maskConfig.mask);

					// Exit if invalid RegExp
					return;
				}

				// Create the IMask instance
				try {
					this.imask = this.IMask(this.$refs.input, this.maskConfig);
				} catch (err) {
					console.error('IMaskMixin - IMask create failed', err);
					throw err;
				}

				// Listen for input events from IMask
				/**
				 * 'input' event: Fires on every keystroke and character change
				 *
				 *   - Triggers immediately as the user types or modifies the input
				 *   - Fires for both valid and invalid input attempts
				 *   - Provides real-time feedback during typing
				 *   - Used for live validation and immediate UI updates
				 *   - May fire multiple times for a single character (e.g., when IMask reformats)
				 *
				 * Use case: Real-time input processing, live validation, immediate feedback
				 */
				this.imask.on('input', () => {
					// Always emit both masked and unmasked values
					// Let the backend decide which to store based on config
					const maskedValue = this.imask.value;
					const unmaskedValue = this.imask.unmaskedValue;

					this.handleInputUpdate(maskedValue, unmaskedValue);
				});

				// Also listen for accept and complete events
				/**
				 * 'accept' event: Fires when input is successfully processed and accepted
				 *
				 *   - Triggers when IMask successfully processes and accepts the input
				 *   - Only fires for valid input that passes mask validation
				 *   - Indicates the input has been "accepted" by the mask rules
				 *   - Fires after the input has been formatted according to mask rules
				 *   - May not fire for every keystroke (e.g., invalid characters are rejected)
				 *
				 * Use case: Confirming valid input, triggering side effects on successful input
				 */
				this.imask.on('accept', () => {
					// Always emit both masked and unmasked values
					// Let the backend decide which to store based on config
					const maskedValue = this.imask.value;
					const unmaskedValue = this.imask.unmaskedValue;

					this.handleInputUpdate(maskedValue, unmaskedValue);
				});

				// Listen for complete events from IMask
				/**
				 * 'complete' event: Fires when the input field is fully filled/completed
				 *
				 *   - Triggers when the input matches the complete mask pattern
				 *   - Only fires when all required positions in the mask are filled
				 *   - Indicates the field is "complete" according to mask requirements
				 *   - Fires once when the field transitions from incomplete to complete
				 *   - Useful for triggering actions when a field is fully filled
				 *
				 * Use case: Form validation, auto-advance to next field, completion indicators
				 *
				 * Examples:
				 *   - Phone number: Fires when all digits are entered
				 *   - Date: Fires when day, month, and year are complete
				 *   - Credit card: Fires when all 16 digits are entered
				 */
				this.imask.on('complete', () => {
					// Always emit both masked and unmasked values
					// Let the backend decide which to store based on config
					const maskedValue = this.imask.value;
					const unmaskedValue = this.imask.unmaskedValue;

					this.handleInputUpdate(maskedValue, unmaskedValue);
				});

				// Set initial value after mask initialization
				if (this.value) {
					// Extract the actual string value to set on IMask
					let stringValue = '';

					// Handle different value types
					if (typeof this.value === 'object' && this.value !== null) {
						// If it's an object with masked property, use that
						stringValue = this.value.masked || '';
					} else if (typeof this.value === 'string') {
						// If it's already a string, use it directly
						stringValue = this.value;
					} else {
						// Convert to string for other types
						stringValue = String(this.value || '');
					}

					// Set the value on the mask
					try {
						this.imask.value = stringValue;
					} catch (error) {
						// Log warning if setting value fails
						console.warn('IMaskMixin - Error setting initial mask value:', error);
					}
				}
		},

		/**
		 * Handle input events with masking and case transformation
		 * @param {Event} event - The input event
		 */
		handleMaskedInput(event) {
			// For masked inputs, let IMask handle everything automatically
			// The input event is already handled by IMask's 'input' event listener
			if (this.imask) {
				// Don't emit anything, let IMask handle it
				return;
			}

			// For non-masked inputs, handle case transformation
			let value = event.target.value;

			// If transform_case is uppercase, convert value
			if (this.transform_case === 'uppercase') {
				value = value.toUpperCase();
			}

			// If transform_case is lowercase, convert value
			else if (this.transform_case === 'lowercase') {
				value = value.toLowerCase();
			}

			// Emit value via Fieldtype mixin (Statamic 6: use update instead of input)
			this.update(value);
		},

		/**
		 * Clean up the IMask instance
		 */
		destroyMask() {

			// If IMask instance exists, destroy it
			if (this.imask) {
				// Call destroy on IMask instance
				this.imask.destroy();

				// Clear the imask reference
				this.imask = null;
			}
		},
	},

	// Lifecycle hooks for mask setup and teardown
	mounted() {
		// Defer so parent prop updates (e.g. mask props from config) and DOM (ref="input")
		// are applied before first initializeMask run (fixes currency field when config
		// arrives after first render).
		this.$nextTick(() => {
			this.initializeMask();
		});
	},

	// Vue 3 lifecycle: beforeUnmount (replaces beforeDestroy)
	beforeUnmount() {
		// Clean up mask instance before component is unmounted
		this.destroyMask();
	},
};
