<!--
 * Custom fieldtype for canvas-based signature capture (SignaturePad). Not a fork
 * of a Statamic component; Statamic does not ship a Signature fieldtype. Kept
 * in sync with other input-like fieldtypes for focus() and focus emit.
 -->
<template>
	<div class="signature-fieldtype">
		<div
			class="signature-container"
			:style="containerStyle"
		>
			<canvas
				ref="signatureCanvas"
				:style="canvasStyle"
				tabindex="0"
				@focus="$emit('focus')"
			></canvas>
		</div>
		<div
			class="signature-toolbar"
			:style="toolbarStyle"
		>
			<Button
				variant="ghost"
				size="sm"
				icon="x"
				icon-only
				:disabled="!hasSignature"
				:title="__('Clear Signature')"
				@click="clearSignature"
			/>
			<div
				v-if="statusMessage"
				class="signature-status"
				:style="statusStyle"
			>
				{{ statusMessage }}
			</div>
		</div>
		<input
			type="hidden"
			:value="signatureData"
		>
	</div>
</template>

<script>
// Import SignaturePad library for canvas-based signature drawing
import SignaturePad from 'signature_pad';
// Kept in Options API: depends on FieldtypeMixin (this.update(), value/config/meta).
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

// Vue component definition for signature fieldtype
export default {
	// Use Fieldtype mixin to inherit Statamic fieldtype functionality
	mixins: [Fieldtype],

	components: { Button },

	// Component props definition
	props: {
		// Current field value (signature data as string or object)
		value: {
			type: [String, Object],
			default: null
		},

		// Field configuration object
		config: {
			type: Object,
			default: () => ({})
		},

		// Metadata object containing additional field information
		meta: {
			type: Object,
			default: null
		}
	},

	// Component reactive data
	data() {
		// Return initial component state
		return {
			signaturePad: null,
			statusMessage: '',
			canvasWidth: 300,
			canvasHeight: 180,
			// The last value this component emitted via update(). The value
			// watcher ignores it so a self-emitted signature is not reloaded
			// (which would redraw it — asynchronously and, on HiDPI, shrunken —
			// and race the Clear button).
			lastEmittedValue: null
		};
	},

	// Computed properties for reactive values
	computed: {
		/**
		 * Get configuration with defaults applied.
		 *
		 * Retrieves the field configuration from either the meta object (when
		 * available) or the config prop. This ensures consistent access to
		 * configuration values throughout the component, regardless of whether
		 * the fieldtype is used in a form context (meta.config) or directly
		 * (config prop).
		 *
		 * @return {Object} Configuration object with defaults applied. Contains
		 *      all fieldtype-specific settings such as canvas dimensions, colors,
		 *      pen settings, and border styling options.
		 */
		fieldConfig() {
			// Return config from meta if available, otherwise use config prop
			return this.meta && this.meta.config
				? this.meta.config
				: this.config;
		},

		/**
		 * Canvas width from config or default.
		 *
		 * Calculates the canvas width based on the field configuration. If
		 * `box_width` is specified in the config, that value is used;
		 * otherwise, falls back to the default width of 300 pixels. This
		 * ensures the canvas maintains a consistent size regardless of
		 * configuration state.
		 *
		 * @return {number} Canvas width in pixels. Defaults to 300 if not
		 *      specified in configuration.
		 */
		computedCanvasWidth() {
			// Return configured width or default canvas width
			return this.fieldConfig.box_width || this.canvasWidth;
		},

		/**
		 * Canvas height from config or default.
		 *
		 * Calculates the canvas height based on the field configuration. If
		 * `box_height` is specified in the config, that value is used;
		 * otherwise, falls back to the default height of 180 pixels. This
		 * ensures the canvas maintains a consistent size regardless of
		 * configuration state.
		 *
		 * @return {number} Canvas height in pixels. Defaults to 180 if not
		 *      specified in configuration.
		 */
		computedCanvasHeight() {
			// Return configured height or default canvas height
			return this.fieldConfig.box_height || this.canvasHeight;
		},

		/**
		 * Background color from config or default.
		 *
		 * Retrieves the background color for the signature canvas from the
		 * field configuration. This color is used as the canvas background and
		 * is preserved when the signature is exported as an image. If not
		 * specified in the config, defaults to white (#ffffff) for optimal
		 * contrast with most pen colors.
		 *
		 * @return {string} Background color as a hex value (e.g., '#ffffff').
		 *      Defaults to '#ffffff' (white) if not specified in
		 *      configuration.
		 */
		backgroundColor() {
			// Return configured background color or default white
			return this.fieldConfig.background_color || '#ffffff';
		},

		/**
		 * Pen color from config or default.
		 *
		 * Retrieves the pen color for drawing signatures from the field
		 * configuration. This color is used for all strokes drawn on the
		 * canvas. If not specified in the config, defaults to blue (#0000ff)
		 * which provides good visibility on white backgrounds and is commonly
		 * used for signature fields.
		 *
		 * @return {string} Pen color as a hex value (e.g., '#0000ff').
		 *      Defaults to '#0000ff' (blue) if not specified in
		 *      configuration.
		 */
		penColor() {
			// Return configured pen color or default blue
			return this.fieldConfig.pen_color || '#0000ff';
		},

		/**
		 * Pen width from config or default.
		 *
		 * Retrieves the pen stroke width directly from the `pen_size`
		 * configuration option. The config value is a numeric string (e.g., '1',
		 * '2', '3') representing pixels, which is converted to a number. This
		 * width is applied to both the minimum and maximum pen width in
		 * SignaturePad, creating a consistent stroke thickness throughout the
		 * signature.
		 *
		 * @return {number} Pen width in pixels. Defaults to 2 if not specified
		 *      in configuration or if an invalid value is provided.
		 */
		penWidth() {
			// Get pen size from config or use default '2'
			const size = this.fieldConfig.pen_size || '2';

			// Parse size as integer, defaulting to 2 if invalid
			return parseInt(size, 10) || 2;
		},

		/**
		 * Border width from config or default.
		 *
		 * Retrieves the CSS border width value directly from the
		 * `border_width` configuration option. The config value is a CSS pixel
		 * value (e.g., '0', '1px', '2px', '4px') which can be used directly
		 * without translation. Returns the configured value or defaults to
		 * '2px' if not specified.
		 *
		 * @return {string} Border width as a CSS value (e.g., '2px', '0').
		 *      Defaults to '2px' if not specified in configuration.
		 */
		borderWidth() {
			// Return configured border width or default '2px'
			return this.fieldConfig.border_width || '2px';
		},

		/**
		 * Border style from config.
		 *
		 * Retrieves the CSS border style from the field configuration. This
		 * determines how the border around the signature canvas is rendered
		 * (solid, dashed, dotted, etc.). If not specified in the config,
		 * defaults to 'dashed' which is commonly used for signature fields to
		 * indicate the drawing area.
		 *
		 * @return {string} Border style as a CSS value (e.g., 'dashed',
		 *      'solid', 'dotted'). Defaults to 'dashed' if not specified in
		 *      configuration.
		 */
		borderStyle() {
			// Return configured border style or default 'dashed'
			return this.fieldConfig.border_style || 'dashed';
		},

		/**
		 * Border color from config.
		 *
		 * Retrieves the border color for the signature canvas from the field
		 * configuration. This color is used for the border around the canvas
		 * area. If not specified in the config, defaults to a light gray
		 * (#dddddd) which provides subtle visual definition without being
		 * distracting.
		 *
		 * @return {string} Border color as a hex value (e.g., '#dddddd').
		 *      Defaults to '#dddddd' (light gray) if not specified in
		 *      configuration.
		 */
		borderColor() {
			// Return configured border color or default light gray
			return this.fieldConfig.border_color || '#dddddd';
		},

		/**
		 * Container style object.
		 *
		 * Generates the inline CSS styles for the signature container div. The
		 * container wraps the canvas element and provides positioning context.
		 * The width and height are dynamically set based on the computed canvas
		 * dimensions, and a high z-index ensures the signature area appears
		 * above other form elements.
		 *
		 * @return {Object} CSS style object for the container div. Contains
		 *      width, height (matching canvas dimensions), position
		 *      ('relative'), and zIndex (99) properties.
		 */
		containerStyle() {
			// Return style object for signature container
			return {
				width: `${this.computedCanvasWidth}px`,
				height: `${this.computedCanvasHeight}px`,
				position: 'relative',
				zIndex: 99
			};
		},

		/**
		 * Canvas style object.
		 *
		 * Generates the inline CSS styles for the signature canvas element.
		 * This includes border styling (width, style, color), background color,
		 * cursor appearance, display properties, and touch action settings. The
		 * width and height are set to match the computed canvas dimensions,
		 * ensuring the canvas displays at the correct size. The 'touchAction:
		 * none' property prevents default touch behaviors (like scrolling) when
		 * drawing on touch devices.
		 *
		 * @return {Object} CSS style object for the canvas element. Contains
		 *      borderWidth, borderStyle, borderColor, backgroundColor, cursor
		 *      ('crosshair'), width, height, display ('block'), and
		 *      touchAction ('none') properties.
		 */
		canvasStyle() {
			// Return style object for signature canvas element
			return {
				borderWidth: this.borderWidth,
				borderStyle: this.borderStyle,
				borderColor: this.borderColor,
				backgroundColor: this.backgroundColor,
				cursor: 'crosshair',
				width: `${this.computedCanvasWidth}px`,
				height: `${this.computedCanvasHeight}px`,
				display: 'block',
				touchAction: 'none'
			};
		},

		/**
		 * Toolbar style object.
		 *
		 * Generates the inline CSS styles for the signature toolbar div. The
		 * toolbar contains the reset/clear button and optional status messages.
		 * It uses flexbox layout to align items to the right side, with the
		 * toolbar width matching the canvas width for visual consistency. The
		 * transparent background ensures it doesn't interfere with the form
		 * design.
		 *
		 * @return {Object} CSS style object for the toolbar div. Contains
		 *      margin, position ('relative'), height ('24px'), width (matching
		 *      canvas width), backgroundColor ('transparent'), display
		 *      ('flex'), alignItems ('center'), justifyContent ('flex-end'), and
		 *      gap ('10px') properties.
		 */
		toolbarStyle() {
			// Return style object for signature toolbar
			return {
				margin: '5px 0',
				position: 'relative',
				height: '24px',
				width: `${this.computedCanvasWidth}px`,
				backgroundColor: 'transparent',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'flex-end',
				gap: '10px'
			};
		},
		/**
		 * Status message style object.
		 *
		 * Generates the inline CSS styles for the status message div. The status
		 * message can be used to display informational text to users (e.g.,
		 * validation messages, instructions). It uses a blue color for
		 * visibility and a simple font for readability. The marginRight 'auto'
		 * property pushes the status message to the left side of the toolbar,
		 * opposite the reset button.
		 *
		 * @return {Object} CSS style object for the status message div.
		 *      Contains color ('#0066cc'), height ('20px'), width ('auto'),
		 *      padding ('2px'), fontFamily ('verdana, sans-serif'), fontSize
		 *      ('12px'), and marginRight ('auto') properties.
		 */
		statusStyle() {
			// Return style object for status message display
			return {
				color: '#0066cc',
				height: '20px',
				width: 'auto',
				padding: '2px',
				fontFamily: 'verdana, sans-serif',
				fontSize: '12px',
				marginRight: 'auto'
			};
		},
		/**
		 * Check if signature has been drawn.
		 *
		 * Determines whether the user has drawn a signature on the canvas. This
		 * is used to enable/disable the reset button and to determine whether
		 * to save signature data. Returns false if the SignaturePad instance
		 * doesn't exist or if the canvas is empty (no strokes have been drawn).
		 *
		 * @return {boolean} True if the SignaturePad instance exists and
		 *      contains at least one stroke (signature has been drawn). False
		 *      if the pad doesn't exist or the canvas is empty.
		 */
		hasSignature() {
			// Check if signature pad exists and contains strokes
			return this.signaturePad && !this.signaturePad.isEmpty();
		},

		/**
		 * Get signature data as base64 string.
		 *
		 * Converts the current signature on the canvas to a base64-encoded PNG
		 * image data URL. This data URL can be stored in the database and later
		 * used to restore the signature. Returns null if no signature has been
		 * drawn, which allows the field to be cleared or left empty. The data
		 * URL format is: 'data:image/png;base64,...' and can be used directly
		 * as an image source or stored for later retrieval.
		 *
		 * @return {string|null} Base64 encoded PNG image data URL (format:
		 *      'data:image/png;base64,...') if a signature exists, or null if
		 *      the canvas is empty or SignaturePad instance doesn't exist.
		 */
		signatureData() {
			// Check if signature pad exists and has content
			if (!this.signaturePad || this.signaturePad.isEmpty()) {
				// Return null if no signature exists
				return null;
			}

			// Convert signature to base64 PNG data URL
			return this.signaturePad.toDataURL('image/png');
		}
	},

	// Watchers for reactive property changes
	watch: {
		// Watch for changes to the value prop
		value: {
			// Handler called when value changes
			handler(newValue) {
				// Ignore the value we just emitted ourselves. Reloading it would
				// redraw the signature (asynchronously via fromDataURL, shrunken on
				// HiDPI) and race the Clear button, which repaints after clear().
				if (newValue === this.lastEmittedValue) {
					return;
				}

				// If new value exists and pad is initialized, load it
				if (newValue && this.signaturePad) {
					// Load signature from new value
					this.loadSignature(newValue);
				}

				// If value is cleared and pad exists, clear canvas
				else if (!newValue && this.signaturePad) {
					// Clear signature pad canvas
					this.signaturePad.clear();
				}
			},

			// Don't run handler immediately on component creation
			immediate: false
		},

		// Watch for changes to read-only state
		readOnly: {
			// Handler called when read-only state changes
			handler(newValue) {
				// Check if signature pad is initialized
				if (this.signaturePad) {
					// If read-only mode is enabled, disable drawing
					if (newValue) {
						// Disable signature pad event handlers
						this.signaturePad.off();
					}

					// Otherwise
					else {
						// ---
						// Re-enable by re-assigning event callbacks
						// ---

						// Re-assign onBegin callback to clear status message
						this.signaturePad.onBegin = () => {
							// Clear status message when drawing begins
							this.statusMessage = '';
						};

						// Re-assign onEnd callback to save signature
						this.signaturePad.onEnd = () => {
							// Save signature when stroke ends
							this.saveSignature();
						};
					}
				}
			},

			// Don't run handler immediately on component creation
			immediate: false
		},

		// Watch for changes to computed canvas width
		computedCanvasWidth: {
			// Handler called when canvas width changes
			handler() {
				// Wait for next DOM update cycle
				this.$nextTick(() => {
					// Check if canvas ref exists before resizing
					if (this.$refs.signatureCanvas) {
						// Resize canvas to new width
						this.resizeCanvas();
					}
				});
			}
		},

		// Watch for changes to computed canvas height
		computedCanvasHeight: {
			// Handler called when canvas height changes
			handler() {
				// Wait for next DOM update cycle
				this.$nextTick(() => {
					// Check if canvas ref exists before resizing
					if (this.$refs.signatureCanvas) {
						// Resize canvas to new height
						this.resizeCanvas();
					}
				});
			}
		},

		// Watch for changes to pen color
		penColor: {
			// Handler called when pen color changes
			handler(newColor) {
				// Check if signature pad is initialized
				if (this.signaturePad) {
					// Update pen color on signature pad
					this.signaturePad.penColor = newColor;
				}
			}
		},

		// Watch for changes to pen width
		penWidth: {
			// Handler called when pen width changes
			handler(newWidth) {
				// Check if signature pad is initialized
				if (this.signaturePad) {
					// Set minimum pen width
					this.signaturePad.minWidth = newWidth;

					// Set maximum pen width
					this.signaturePad.maxWidth = newWidth;
				}
			}
		},

		// Watch for changes to background color
		backgroundColor: {
			// Handler called when background color changes
			handler(newColor) {
				// Check if signature pad is initialized
				if (this.signaturePad) {
					// Update background color on signature pad
					this.signaturePad.backgroundColor = newColor;

					// ---
					// Redraw to show new background
					// ---

					// If signature exists, preserve it during background change
					if (!this.signaturePad.isEmpty()) {
						// Save current signature data
						const data = this.signaturePad.toData();

						// Clear canvas to apply new background
						this.signaturePad.clear();

						// Restore signature data with new background
						this.signaturePad.fromData(data);
					}
				}
			}
		}
	},

	// Component methods
	methods: {
		/**
		 * Initialize the signature pad.
		 *
		 * Sets up the SignaturePad instance on the canvas element. This method
		 * is called when the component is mounted and ensures the canvas is
		 * properly sized and configured. It creates a new SignaturePad instance
		 * with the current configuration (background color, pen color, pen
		 * width), sets up event callbacks for stroke begin/end events, handles
		 * read-only mode if applicable, and loads any existing signature data
		 * from the value prop. The canvas dimensions are set based on computed
		 * width and height values.
		 *
		 * @return {void}
		 */
		initializeSignaturePad() {
			// Check if canvas ref exists
			if (!this.$refs.signatureCanvas) {
				// Exit early if canvas not available
				return;
			}

			// Get canvas element reference
			const canvas = this.$refs.signatureCanvas;

			// Set canvas width from computed value
			this.canvasWidth = this.computedCanvasWidth;

			// Set canvas height from computed value
			this.canvasHeight = this.computedCanvasHeight;

			// ---
			// Backing-store scaling for the device pixel ratio is applied by
			// configureCanvasScale().
			// ---

			// Set canvas element width
			canvas.width = this.canvasWidth;

			// Set canvas element height
			canvas.height = this.canvasHeight;

			// Initialize SignaturePad
			this.signaturePad = new SignaturePad(canvas, {
				backgroundColor: this.backgroundColor,
				penColor: this.penColor,
				throttle: 16,
				minWidth: this.penWidth,
				maxWidth: this.penWidth
			});

			// ---
			// Set up event callbacks (must be assigned after initialization).
			// Use bound methods to maintain 'this' context.
			// ---

			// Store component reference for use in callbacks
			const self = this;

			// Assign onBegin callback to clear status message
			this.signaturePad.onBegin = function() {
				// Clear status message when drawing begins
				self.statusMessage = '';
			};

			// Assign onEnd callback to save signature
			this.signaturePad.onEnd = function() {
				// Save signature when stroke ends
				self.saveSignature();
			};

			// ---
			// Also add event listeners as a backup (some versions support this)
			// ---

			// Check if addEventListener method is available
			if (typeof this.signaturePad.addEventListener === 'function') {
				// Add beginStroke event listener
				this.signaturePad.addEventListener('beginStroke', () => {
					// Clear status message when stroke begins
					this.statusMessage = '';
				});

				// Add endStroke event listener
				this.signaturePad.addEventListener('endStroke', () => {
					// Save signature when stroke ends
					this.saveSignature();
				});
			}

			// ---
			// Handle read-only mode
			// ---

			// Check if component is in read-only mode
			if (this.readOnly) {
				// Disable signature pad event handlers
				this.signaturePad.off();
			}

			// ---
			// Load existing signature if value exists
			// ---

			// Scale the canvas backing store for the device pixel ratio so strokes
			// render at the correct size and position on HiDPI displays (clears the pad).
			this.configureCanvasScale();

			// Check if initial value exists
			if (this.value) {
				// Load signature from value prop
				this.loadSignature(this.value);
			}
		},
		/**
		 * Load signature from value.
		 *
		 * Restores a previously saved signature onto the canvas. Handles
		 * multiple value formats: base64 data URL strings (e.g.,
		 * 'data:image/png;base64,...') or objects containing signature data in
		 * properties like 'data', 'image', or 'signature'. This allows
		 * flexibility in how signature data is stored and retrieved. The
		 * SignaturePad's fromDataURL method handles the actual image loading
		 * and rendering on the canvas.
		 *
		 * @param {string|Object} value Signature data to load. Can be a base64
		 *      data URL string or an object with signature data in 'data',
		 *      'image', or 'signature' properties.
		 * @return {void}
		 */
		loadSignature(value) {
			// Check if signature pad is initialized
			if (!this.signaturePad) {
				// Exit early if pad not available
				return;
			}

			// Initialize variable to hold image data
			let imageData = null;

			// ---
			// Handle different value formats
			// ---

			// Check if value is a string (base64 data URL)
			if (typeof value === 'string') {
				// Assume it's a base64 data URL
				imageData = value;
			}

			// Check if value is an object with signature data
			else if (value && typeof value === 'object') {
				// Handle object format (e.g., {data: 'base64...'})
				imageData = value.data || value.image || value.signature;
			}

			// Check if image data was extracted
			if (imageData) {
				// SignaturePad's fromDataURL handles loading the image
				this.signaturePad.fromDataURL(imageData);
			}
		},
		/**
		 * Save signature and emit value.
		 *
		 * Converts the current signature on the canvas to a base64-encoded PNG
		 * data URL and emits it as the field value using the Fieldtype mixin's
		 * update method. This is called automatically when a stroke ends (via
		 * the onEnd callback) to ensure the signature is saved in real-time as
		 * the user draws. If the canvas is empty, emits null to clear the
		 * field value. The emitted value can be stored in the database and used
		 * to restore the signature later.
		 *
		 * @return {void}
		 */
		saveSignature() {
			// Check if signature pad exists and has content
			if (!this.signaturePad || this.signaturePad.isEmpty()) {
				// Clear field value if no signature exists
				this.lastEmittedValue = null;
				this.update(null);

				// Exit early
				return;
			}

			// Convert signature to base64 PNG data URL
			const data = this.signaturePad.toDataURL('image/png');

			// Remember what we emit so the value watcher does not reload it.
			this.lastEmittedValue = data;

			// ---
			// Use update() method from Fieldtype mixin to emit the value.
			// For large base64 strings, use updateDebounced to avoid performance issues.
			// ---

			// Check if data is large enough to use debounced update
			if (data && data.length > 10000) {
				// Use debounced update for large data strings
				this.updateDebounced(data);
			}

			// Otherwise
			else {
				// Use immediate update for smaller data strings
				this.update(data);
			}
		},
		/**
		 * Clear the signature.
		 *
		 * Removes all strokes from the canvas and clears the field value. This
		 * method is called when the user clicks the reset/clear button. It
		 * clears the SignaturePad canvas, emits null as the field value to
		 * indicate the signature has been removed, and clears any status
		 * messages. After clearing, the reset button will be disabled until a
		 * new signature is drawn.
		 *
		 * @return {void}
		 */
		clearSignature() {
			// Check if signature pad is initialized
			if (this.signaturePad) {
				// Clear all strokes from canvas
				this.signaturePad.clear();

				// Clear field value
				this.lastEmittedValue = null;
				this.update(null);

				// Clear status message
				this.statusMessage = '';
			}
		},

		/**
		 * Scale the canvas backing store to the device pixel ratio.
		 *
		 * signature_pad maps pointer input and redraws stored strokes against
		 * canvas.width / devicePixelRatio, so a canvas sized at its CSS (logical)
		 * dimensions with no context scale renders strokes — and any reload via
		 * fromData/fromDataURL — shrunken in the top-left on HiDPI displays. Size the
		 * backing store to logical x ratio and scale the 2D context to match; the CSS
		 * size stays logical (canvasStyle). Resizing the backing store resets it, so
		 * clear the pad afterwards.
		 *
		 * @return {void}
		 */
		configureCanvasScale() {
			// Bail if the canvas or pad is not ready
			if (!this.$refs.signatureCanvas || !this.signaturePad) {
				return;
			}

			// Get the canvas element
			const canvas = this.$refs.signatureCanvas;

			// Device pixel ratio, at least 1
			const ratio = Math.max(window.devicePixelRatio || 1, 1);

			// Backing store = logical size x ratio (CSS size stays logical via canvasStyle)
			canvas.width = this.computedCanvasWidth * ratio;
			canvas.height = this.computedCanvasHeight * ratio;

			// Scale the drawing context so input coordinates map 1:1 to CSS pixels
			canvas.getContext('2d').scale(ratio, ratio);

			// Resizing the backing store resets it — clear so isEmpty() stays correct
			this.signaturePad.clear();
		},

		// Parity with other input fieldtypes (replicator, CP focus management)
		focus() {
			this.$refs.signatureCanvas?.focus?.();
		},

		/**
		 * Resize the canvas.
		 *
		 * Handles dynamic resizing of the signature canvas when configuration
		 * changes (e.g., box_width or box_height). This method preserves any
		 * existing signature data by saving it before resizing, then restoring
		 * it after the canvas has been reinitialized with new dimensions. The
		 * canvas is recreated with updated dimensions and configuration, event
		 * callbacks are re-established, and read-only mode is reapplied if
		 * necessary. This ensures the signature remains intact when canvas size
		 * is changed via configuration updates.
		 *
		 * @return {void}
		 */
		resizeCanvas() {
			// Check if canvas ref and signature pad exist
			if (!this.$refs.signatureCanvas || !this.signaturePad) {
				// Exit early if required elements not available
				return;
			}

			// Get canvas element reference
			const canvas = this.$refs.signatureCanvas;

			// Save current signature data before resizing
			const data = this.signaturePad.toData();

			// ---
			// Update dimensions
			// ---

			// Update canvas width from computed value
			this.canvasWidth = this.computedCanvasWidth;

			// Update canvas height from computed value
			this.canvasHeight = this.computedCanvasHeight;

			// ---
			// Backing-store scaling for the device pixel ratio is applied by
			// configureCanvasScale().
			// ---

			// Set canvas element width
			canvas.width = this.canvasWidth;

			// Set canvas element height
			canvas.height = this.canvasHeight;

			// Reinitialize with new size
			this.signaturePad = new SignaturePad(canvas, {
				backgroundColor: this.backgroundColor,
				penColor: this.penColor,
				throttle: 16,
				minWidth: this.penWidth,
				maxWidth: this.penWidth
			});

			// ---
			// Set up event callbacks (must be assigned after initialization)
			// ---

			// Assign onBegin callback to clear status message
			this.signaturePad.onBegin = () => {
				// Clear status message when drawing begins
				this.statusMessage = '';
			};

			// Assign onEnd callback to save signature
			this.signaturePad.onEnd = () => {
				// Save signature when stroke ends
				this.saveSignature();
			};

			// Re-scale the backing store for the device pixel ratio (clears the pad)
			this.configureCanvasScale();

			// ---
			// Restore signature data if it existed
			// ---

			// Check if signature data was saved
			if (data && data.length > 0) {
				// Restore signature data to resized canvas
				this.signaturePad.fromData(data);
			}

			// ---
			// Handle read-only mode
			// ---

			// Check if component is in read-only mode
			if (this.readOnly) {
				// Disable signature pad event handlers
				this.signaturePad.off();
			}
		}
	},

	// Component lifecycle hook called after component is mounted
	mounted() {
		// Wait for next DOM update cycle
		this.$nextTick(() => {
			// Initialize signature pad after DOM is ready
			this.initializeSignaturePad();
		});
	},

	// Vue 3 lifecycle: beforeUnmount instead of beforeDestroy. Save signature and
	// clean up SignaturePad; Statamic 6 does not use container.saving event.
	beforeUnmount() {
		if (this.signaturePad && !this.signaturePad.isEmpty()) {
			// Save signature one final time before component is unmounted
			this.saveSignature();
		}

		// Check if signature pad exists
		if (this.signaturePad) {
			// Disable signature pad event handlers
			this.signaturePad.off();

			// Clear signature pad reference
			this.signaturePad = null;
		}
	}
};
</script>

<style scoped>
.signature-fieldtype {
	width: 100%;
}

.signature-container {
	position: relative;
}

.signature-toolbar {
	display: flex;
	align-items: center;
	justify-content: flex-end;
}

.signature-status {
	display: flex;
	align-items: center;
}
</style>
