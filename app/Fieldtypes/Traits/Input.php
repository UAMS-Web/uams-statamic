<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Provides DRY, extensible config and preset logic for input-based fieldtypes.
 *
 * ---
 *
 * Optional Preset Support:
 *
 * Fieldtypes may implement a `protected function getPresets(): array` method to
 * enable preset-driven config logic. If present, the Input trait will
 * automatically integrate preset selection and config overrides.
 *
 * The expected return structure for `getPresets()` is:
 *
 * ```php
 * [
 *     'display' => 'Preset Label', // (string) Label for the preset selector
 *     'instructions' => 'Instructions for the selector', // (string, optional)
 *     'default' => 'preset_key', // (string) The default preset key
 *     'options' => [
 *         'preset_key' => [
 *             'label' => 'Option Label', // (string) Label for this preset
 *             'config' => [              // (array) Config overrides for this preset
 *                 'field_name' => 'value',
 *                 // ...
 *             ],
 *         ],
 *         // ... more preset options ...
 *     ],
 * ]
 * ```
 *
 * Example:
 *
 * ```php
 * [
 *     'display' => 'Phone Format',
 *     'instructions' => 'Choose the phone number format for this field.',
 *     'default' => 'tel-national',
 *     'options' => [
 *         'tel' => [
 *             'label' => 'International',
 *             'config' => [
 *                 'autocomplete' => 'tel',
 *                 'pattern' => '^\\+?\\d{1,3}[- ]?\\(?\\d{3}\\)?[- ]?\\d{3}[- ]?\\d{4}$',
 *             ],
 *         ],
 *         'tel-national' => [
 *             'label' => 'National',
 *             'config' => [
 *                 'autocomplete' => 'tel-national',
 *                 'pattern' => '^\\(?\\d{3}\\)?[- ]?\\d{3}[- ]?\\d{4}$',
 *             ],
 *         ],
 *     ],
 * ]
 * ```
 *
 * ---
 *
 * Additional Config Fields are supported via getAdditionalConfigFields().
 * See that method's PHPDoc for details and usage examples.
 *
 * ## Creating Vue Components for Input Fieldtypes
 *
 * When creating a new fieldtype that uses this trait, you need to create a Vue
 * component that wraps the `custom-text-input` partial component. Follow these
 * steps:
 *
 * ### 1. Create the Vue Component
 *
 * Create a new Vue component file:
 * `resources/js/components/fieldtypes/FooBarFieldtype.vue`
 *
 * ```vue
 * <template>
 *     <custom-text-input
 *         :value="value"
 *         :meta="meta"
 *         v-on="$listeners"
 *     />
 * </template>
 *
 * <script>
 * export default {
 *     props: {
 *         value: { required: true },
 *         meta: { type: Object, default: null }
 *     }
 * };
 * </script>
 * ```
 *
 * ### 2. Register the Component
 *
 * Add the component registration to `resources/js/cp.js`:
 *
 * ```javascript
 * import FooBarFieldtype from './components/fieldtypes/FooBarFieldtype.vue';
 *
 * Statamic.booting(() => {
 *     Statamic.$components.register('foo_bar-fieldtype', FooBarFieldtype);
 * });
 * ```
 *
 * The component name defined in resources\js\cp.js should follow the pattern:
 * `{fieldtype_handle}-fieldtype`
 *
 * ### 3. Define the Component Property
 * In your PHP fieldtype class, define the `$component` property to link to your
 * Vue component:
 *
 * ```php
 * class FooBar extends Fieldtype
 * {
 *     use Input;
 *
 *     protected $component = 'foo_bar';
 *
 *     // ... rest of your fieldtype implementation
 * }
 * ```
 *
 * ### 4. Ensure CustomInputText is Registered
 *
 * The `custom-text-input` partial component must be registered in `cp.js` under
 * the "Custom Fieldtype Partials" section:
 *
 * ```javascript
 * import CustomInputText from './components/fieldtypes/partials/CustomInputText.vue';
 *
 * Statamic.booting(() => {
 *     Statamic.$components.register('custom-text-input', CustomInputText);
 * });
 * ```
 *
 * ### Key Points:
 *
 *   - **Simple fieldtypes**: The Vue component should be minimal and only pass
 *     `value` and `meta` to `custom-text-input`
 *   - **Complex fieldtypes**: Some fieldtypes require custom logic beyond the
 *     minimal pattern (see `PhoneFieldtype.vue` for an example)
 *   - The `custom-text-input` component handles all input rendering,
 *     validation, and data management
 *   - The `meta` object contains configuration data from the trait's
 *     `preload()` method
 *   - The `$component` property in your PHP class must match the registered
 *     component name without the `-fieldtype` suffix
 *   - For complex fieldtypes with custom logic, handle events explicitly
 *     instead of using `v-on="$listeners"` to avoid conflicts
 */
trait Input
{
    /**
     * Resolves and hydrates the runtime config delivered to the Vue component.
     *
     *   - Normalizes raw config with defaults, applies presets, and resolves the
     *     values the frontend partial consumes via `preload()`.
     */
    use BuildsPreloadConfig;

    /**
     * Provides recursion-safe, cached config access for fieldtypes.
     *
     * This trait ensures that repeated or recursive calls to config() do not
     * cause infinite loops and that config values are efficiently cached for
     * performance. Used by all input-based fieldtypes to keep config access DRY
     * and safe.
     */
    use ConfigCache;

    /**
     * Defines the Control Panel config-field schema.
     *
     *   - Assembles the config-field set from focused builders, then filters and
     *     groups them into ordered Control Panel sections.
     *   - Exposes the child extension hooks and preset integration used to tailor
     *     the schema per fieldtype.
     */
    use DefinesInputConfigFields;

    /**
     * Trait to provide reusable validation rule logic for Statamic fieldtypes.
     *
     *   - Supplies getValidationRules and setField methods for dynamic validation.
     *   - Allows this fieldtype to define custom validation rules and merge with
     *     parent rules.
     *   - Ensures validation logic is DRY and consistent across fieldtypes.
     */
    use FieldtypeValidationRules;

    /**
     * Trait to provide reusable logic for storing input mask values.
     *
     *   - Supplies isInputMaskEnabled and processInputMaskValue methods.
     *   - Allows this fieldtype to define custom logic for storing input mask values.
     *   - Ensures input mask value storage is DRY and consistent across fieldtypes.
     */
    use InputMaskValueStorage;

    /**
     * Handles value processing and validation.
     *
     *   - Normalizes and formats values on save/display and exposes
     *     pattern-derived validation rules.
     */
    use ProcessesInputValue;

    /**
     * Supplies the autocomplete option vocabulary.
     *
     *   - Provides the full set of HTML `autocomplete` tokens and the
     *     child-narrowed subset of allowed keys.
     */
    use ProvidesAutocompleteOptions;

    /**
     * The icon for this fieldtype.
     *
     * Uses the same icon as Statamic's Text fieldtype.
     *
     * @return string The icon name
     */
    public function icon(): string
    {
        return 'form-text-field';
    }
}
