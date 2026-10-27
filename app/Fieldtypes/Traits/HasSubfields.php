<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use Illuminate\Support\Arr;
use Statamic\Fields\Field;
use Statamic\Support\Str;

/**
 * Trait HasSubfields
 *
 * App-specific trait; Statamic does not ship an equivalent. Provides reusable
 * logic for fieldtypes with multiple subfields, supporting:
 *   - Inclusion toggles (with defaults)
 *   - Required toggles (with defaults, only applies if parent is required)
 *   - Label overrides (with defaults)
 *   - Placeholders
 *   - Default values (with type flexibility)
 *   - Preset-driven config (e.g., Address Type)
 *   - Validation helpers for subfield requirements
 *
 * To use:
 *   - Implement getSubfieldDefinitions() in your fieldtype to define subfields
 *     and their config
 *   - Optionally implement getPresetOptions() and getPresetConfig() for
 *     preset-driven logic
 *
 * ## Creating Vue Components for HasSubfields Fieldtypes
 *
 * When creating a new fieldtype that uses this trait, you need to create a Vue
 * component that wraps the `has-subfields` partial component. Follow these steps:
 *
 * ### 1. Create the Vue Component
 *
 * Create a new Vue component file:
 * `resources/js/components/fieldtypes/FooBarFieldtype.vue`
 *
 * ```vue
 * <template>
 *     <has-subfields
 *         :value="value"
 *         :meta="meta"
 *         :handle="handle"
 *         :id="id"
 *         v-on="$listeners"
 *     />
 * </template>
 *
 * <script>
 * export default {
 *     mixins: [Fieldtype]
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
 *
 * In your PHP fieldtype class, define the `$component` property to link to your
 * Vue component:
 *
 * ```php
 * class FooBar extends Fieldtype
 * {
 *     use HasSubfields;
 *
 *     protected $component = 'foo_bar';
 *
 *     // ... rest of your fieldtype implementation
 * }
 * ```
 *
 * ### 4. Ensure HasSubfields is Registered
 *
 * The `has-subfields` partial component must be registered in `cp.js` under the
 * "Custom Fieldtype Partials" section:
 *
 * ```javascript
 * import HasSubfields from './components/fieldtypes/partials/HasSubfields.vue';
 *
 * Statamic.booting(() => {
 *     Statamic.$components.register('has-subfields', HasSubfields);
 * });
 * ```
 *
 * ### Key Points:
 *
 *   - The Vue component should be minimal and pass only `value`, `meta`,
 *     `handle` and `id` to `has-subfields`. `id` is Statamic's field id, which
 *     carries the publish container; without it two containers rendering the
 *     fieldtype give their subfields the same ids (#327)
 *   - The `has-subfields` component handles all subfield rendering, validation,
 *     and data management
 *   - The `meta` object contains `subfields` and `fieldtype_handle` from the
 *     trait's `preload()` method
 *   - The `$component` property in your PHP class must match the registered
 *     component name without the `-fieldtype` suffix
 */
trait HasSubfields
{
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
     * Validation helpers for blueprint `sometimes` and nested dotted rules.
     */
    use FieldtypeValidationHelpers;

    /**
     * Instance cache for subfield definitions to avoid repeated expensive calls
     *
     * @var ?list<array<string, mixed>> Cached subfield definitions array, or null if not yet populated
     */
    private ?array $subfieldDefinitionsCache = null;

    /**
     * Get the subfield definitions for this fieldtype.
     *
     * Each subfield definition should include:
     *   - 'handle': string (required)
     *   - 'display': string (required)
     *   - 'type': string ('text' or 'select')
     *   - 'inclusion_default': bool
     *   - 'required_default': bool
     *   - 'label_default': string
     *   - 'placeholder_default': ?string
     *   - 'default_type': string ('text' or 'select')
     *   - 'default_choices': ?array (for select)
     *   - 'default_value': ?mixed
     *   - 'canonical_handle': ?string (optional, for conditional subfields)
     *   - 'autocomplete': ?string (optional, e.g. 'name', 'address-line1')
     *   - 'clearable': ?bool (optional, for select fields to enable clear button)
     *
     * Example return value:
     * [
     *   [
     *     'handle' => 'city',
     *     'display' => 'City',
     *     'type' => 'text',
     *     'inclusion_default' => true,
     *     'required_default' => true,
     *     'label_default' => 'City',
     *     'placeholder_default' => 'Enter city',
     *     'default_type' => 'text',
     *     'default_value' => '',
     *     'canonical_handle' => 'city_value',
     *   ],
     *   ...
     * ]
     *
     * @return array Array of subfield definition arrays
     */
    abstract protected function getSubfieldDefinitions(): array;

    /**
     * Get cached subfield definitions to avoid repeated expensive calls.
     *
     * @return list<array<string, mixed>> Array of subfield definition arrays
     */
    protected function getCachedSubfieldDefinitions(): array
    {
        // Check if cache is null
        if ($this->subfieldDefinitionsCache === null) {
            // Call the abstract method to get subfield definitions and cache them
            $this->subfieldDefinitionsCache = $this->getSubfieldDefinitions();
        }

        // Return the cached subfield definitions
        return $this->subfieldDefinitionsCache;
    }

    /**
     * Clear the subfield definitions cache for this instance.
     *
     * Useful for testing or when cache needs to be refreshed.
     */
    public function clearSubfieldDefinitionsCache(): void
    {
        $this->subfieldDefinitionsCache = null;
    }

    /**
     * Clear all caches for this instance.
     *
     * Useful for testing or when caches need to be refreshed.
     */
    public function clearAllCaches(): void
    {
        // Clear subfield definitions
        $this->subfieldDefinitionsCache = null;

        // Clear config cache
        $this->clearConfigCache();
    }

    /**
     * Whether the stored value includes any non-blank subfield (by canonical key).
     *
     * @param  array<string, mixed>  $value  Incoming associative value
     * @return bool True when at least one canonical subfield is filled
     */
    protected function valueContainsFilledSubfield(array $value): bool
    {
        // Build unique storage keys (canonical handle when set, else handle)
        $storageKeys = [];

        // Collect one key per definition; duplicates share the same canonical key
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Resolve the key used for this subfield in stored data
            /** @var int|string $storageKey */
            $storageKey = $subfield['canonical_handle'] ?? $subfield['handle'];
            $storageKeys[$storageKey] = true;
        }

        // Check each storage key for a non-blank value
        foreach (array_keys($storageKeys) as $key) {
            // Cast to string for the array-dot helpers (keys may be int|string)
            $key = (string) $key;

            // Skip when the key is absent from the value
            if (! Arr::has($value, $key)) {
                continue;
            }

            // Treat non-blank values as stored subfield content
            if (filled(Arr::get($value, $key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Optionally provide preset options (e.g., Address Type).
     *
     * You may return either:
     *   - An array of ['handle' => 'Display'] pairs (simple options)
     *   - Or an array with keys:
     *       - 'display' => ?string (optional, label for the preset field)
     *       - 'instructions' => ?string (optional, instructions for the preset
     *         field)
     *       - 'options' => array (required, ['handle' => 'Display'] pairs)
     *
     * @return array<string, mixed> Preset options array, either simple ['handle' => 'Display'] pairs or extended format with 'display', 'instructions', and 'options' keys
     */
    protected function getPresetOptions(): array
    {
        return [];
    }

    /**
     * Optionally specify an 'if' condition for a subfield group based on the preset.
     *
     * Return an array to be merged into the 'if' condition, or null for none.
     *
     * @param  string  $handle  The subfield handle to check
     * @param  ?string  $preset  The preset value to check against
     * @return ?array<string, mixed> The 'if' condition array for the subfield and preset, or null if none
     */
    protected function getSubfieldPresetIfCondition(
        string $handle,
        ?string $preset
    ): ?array {
        return null;
    }

    /**
     * Generate config fields for all subfields, including toggles, labels,
     * placeholders, defaults, etc.
     *
     * @param  array<string, mixed>  $config  The current config (may include preset selection)
     * @return array<string, mixed> Array of config field definitions keyed by field handle
     */
    protected function getSubfieldConfigFields(
        array $config = []
    ): array {
        // Start with an empty fields array to collect subfield config fields
        $fields = [];

        // Get preset options for this fieldtype (if any)
        $presetOptions = $this->getPresetOptions();

        // Determine the current preset from config or use the first available preset
        /** @var ?string $preset */
        $preset = $presetOptions ? ($config['preset'] ?? array_key_first($presetOptions)) : null;

        // Iterate over each subfield definition to build config fields
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the handle for this subfield
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Get the display name for this subfield
            /** @var string $display */
            $display = $subfield['display'];

            // Get the type for this subfield (e.g., text, select)
            $type = $subfield['type'];

            // Get the default inclusion value for this subfield
            $inclusionDefault = $subfield['inclusion_default'] ?? true;

            // Get the default required value for this subfield
            $requiredDefault = $subfield['required_default'] ?? false;

            // Get the default label for this subfield
            $labelDefault = $subfield['label_default'] ?? $display;

            // Get the default placeholder for this subfield
            $placeholderDefault = $subfield['placeholder_default'] ?? '';

            // Get the default type for this subfield (text or select)
            $defaultType = $subfield['default_type'] ?? 'text';

            // Get the default choices for select subfields
            $defaultChoices = $subfield['default_choices'] ?? null;

            // Get the default value for this subfield
            $defaultValue = $subfield['default_value'] ?? null;

            // Get the clearable setting for this subfield (for select fields)
            $clearable = $subfield['clearable'] ?? null;

            // Get the visibility setting for this subfield
            $visibility = $subfield['visibility'] ?? 'visible';

            // Get the width setting for this subfield (if any)
            $width = $subfield['width'] ?? null;

            // Get preset-based 'if' condition for this subfield group
            $presetIf = $this->getSubfieldPresetIfCondition(
                $handle,
                $preset
            );

            // Inclusion toggle (now with presetIf)
            $fields[$handle.'_include'] = [
                'display' => 'Include '.$display,
                'type' => 'toggle',
                'default' => $inclusionDefault,
                'visibility' => $visibility,
                'if' => \is_array($presetIf) ? $presetIf : null,
            ];

            // All other fields should only display if *_include is true
            $ifInclude = [$handle.'_include' => true];

            // Merge presetIf into all 'if' conditions
            $mergeIf = function ($existingIf = null) use ($presetIf, $ifInclude): array {
                // Start with the inclusion condition for this subfield
                $result = $ifInclude;

                // Check if preset-based condition is present
                if (\is_array($presetIf)) {
                    // Merge preset-based condition
                    $result = \array_merge(
                        $result,
                        $presetIf
                    );
                }

                // Check if there are any existing 'if' conditions to merge
                if (\is_array($existingIf)) {
                    // Merge any existing 'if' conditions
                    return \array_merge(
                        $result,
                        $existingIf
                    );
                }

                // Return the merged 'if' condition array
                return $result;
            };

            // Required toggle (always visible, only applies if parent is required in validation)
            $fields[$handle.'_required'] = [
                'display' => $display.' Required',
                'type' => 'toggle',
                'default' => $requiredDefault,
                'if' => $mergeIf(),
                'visibility' => $visibility,
            ];

            // Add label override
            $fields[$handle.'_label'] = [
                'display' => $display.' Label',
                'type' => 'text',
                'placeholder' => $labelDefault ?: $display,
                'if' => $mergeIf(),
                'visibility' => $visibility,
            ];

            // Check the type is text or textarea
            if (
                \in_array(
                    $type,
                    [
                        'text',
                        'textarea',
                    ]
                )
            ) {
                // Add placeholder field
                $fields[$handle.'_placeholder'] = [
                    'display' => $display.' Placeholder',
                    'type' => 'text',
                    'placeholder' => $placeholderDefault ?: null,
                    'if' => $mergeIf(),
                    'visibility' => $visibility,
                ];
            }

            // Add default value
            $defaultField = [
                'type' => $defaultType,
                'default' => $defaultValue,
                'display' => 'Default '.$display,
                'if' => $mergeIf(),
                'visibility' => $visibility,
            ];

            // Check if the default type is select
            if ($defaultType === 'select') {
                // Check if default choices are defined
                if ($defaultChoices) {
                    // Add default choices to the default field config
                    $defaultField['options'] = $defaultChoices;
                }

                // Check if clearable is defined in subfield definition
                if ($clearable !== null) {
                    // Add clearable property to the default field config
                    $defaultField['clearable'] = $clearable;
                }

                // Add select value type config (key or label) for select subfields
                $fields[$handle.'_select_value_type'] = [
                    'display' => $display.' Value Type',
                    'type' => 'radio',
                    'options' => [
                        'label' => 'Label',
                        'key' => 'Key',
                    ],
                    'default' => 'label',
                    'instructions' => "Choose whether to store the selected option's key or label.",
                    'if' => $mergeIf(),
                    'visibility' => $visibility,
                ];
            }

            // Add the default field to the fields array
            $fields[$handle.'_default'] = $defaultField;

            // Check if width is set
            if ($width !== null) {
                // Add width field to the fields array
                $fields[$handle.'_width'] = [
                    'display' => $display.' Width',
                    'type' => 'width',
                    'default' => 100,
                    'if' => $mergeIf(),
                    'visibility' => $visibility,
                ];
            }
        }

        // Clean up variables to free up memory
        unset(
            $presetOptions,
            $preset,
            $subfield,
            $handle,
            $display,
            $type,
            $inclusionDefault,
            $requiredDefault,
            $labelDefault,
            $placeholderDefault,
            $defaultType,
            $defaultChoices,
            $defaultValue,
            $clearable,
            $visibility,
            $width,
            $presetIf,
            $ifInclude,
            $mergeIf,
            $defaultField,
        );

        // Return the complete fields array for all subfields
        return $fields;
    }

    /**
     * If using a preset field, generate the config field for it.
     *
     * @return ?array<string, mixed> Preset config field array with 'preset' key, or null if no presets are available
     */
    protected function getPresetConfigField(): ?array
    {
        // Get raw preset options for this fieldtype
        $presetOptionsRaw = $this->getPresetOptions();

        // Check if there are no preset options available
        if (! $presetOptionsRaw) {
            // Return null early since no presets are available
            return null;
        }

        // Use raw options as the options array
        $options = $presetOptionsRaw;

        // Default display label for the preset field
        $display = 'Preset';

        // Default instructions for the preset field
        $instructions = null;

        // Check if preset options are in the extended format
        if (isset($presetOptionsRaw['options'])) {
            // Extract the options array from the extended format
            /** @var array<string, mixed> $options */
            $options = $presetOptionsRaw['options'];

            // Check if display label is present in the options array
            if (
                isset($presetOptionsRaw['display'])
                && $presetOptionsRaw['display']
            ) {
                // Extract the display label
                $display = $presetOptionsRaw['display'];
            }

            // Check if instructions are present in the options array
            if (
                isset($presetOptionsRaw['instructions'])
                && $presetOptionsRaw['instructions']
            ) {
                // Extract the instructions
                $instructions = $presetOptionsRaw['instructions'];
            }
        }

        // Build the preset config field array
        $field = [
            'display' => $display,
            'type' => 'select',
            'options' => $options,
            'default' => array_key_first($options),
        ];

        // Check if instructions are present
        if ($instructions) {
            // Add instructions to the field config
            $field['instructions'] = $instructions;
        }

        // Return the preset config field as an associative array
        return [
            'preset' => $field,
        ];
    }

    /**
     * Provide config fields for blueprint editor, including preset and subfield
     * config fields.
     *
     * @return list<array<string, mixed>> Array of config field sections
     */
    public function configFieldItems(): array
    {
        // Initialize the fields array that will be returned
        $fields = [];

        // Get the current configuration for this fieldtype instance (cached to prevent recursion)
        $config = $this->getCachedConfig();

        // Generate all subfield configuration fields (toggles, labels, placeholders, etc.)
        /** @var array<string, array<string, mixed>> $subfieldConfigFields */
        $subfieldConfigFields = $this->getSubfieldConfigFields($config);

        // Initialize the grouped fields array for organizing subfield config
        $grouped = [];

        // Get preset options to determine current preset
        $presetOptionsRaw = $this->getPresetOptions();

        // Extract the actual options array from preset options
        $presetOptions = $presetOptionsRaw['options'] ?? $presetOptionsRaw;

        // Determine the current preset from config or use the first available preset
        /** @var ?string $currentPreset */
        $currentPreset = $config['preset'] ?? (\is_array($presetOptions) ? array_key_first($presetOptions) : null);

        // Check if there are any preset options available
        if ($preset = $this->getPresetConfigField()) {
            // Add each preset field to the grouped configuration
            foreach ($preset as $key => $presetField) {
                // Store preset field with its key
                $grouped[$key] = $presetField;
            }
        }

        // Process each subfield definition to create configuration sections
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the subfield handle for identification
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Get the display name for the subfield section
            /** @var string $display */
            $display = $subfield['display'];

            // Initialize array to collect all config fields for this subfield
            $subfieldFields = [];

            // Filter configuration fields that belong to this specific subfield
            foreach ($subfieldConfigFields as $key => $field) {
                // Check if this config field belongs to the current subfield
                if (
                    str_starts_with($key, $handle.'_')
                ) {
                    // Add the field to this subfield's configuration group
                    $subfieldFields[$key] = $field;
                }
            }

            // Check if this subfield has any visible configuration fields
            $hasVisible = false;

            // Determine if any fields in this subfield group are visible
            foreach ($subfieldFields as $field) {
                // Check if field is not explicitly hidden
                if (
                    ! isset($field['visibility'])
                    || $field['visibility'] !== 'hidden'
                ) {
                    // Found at least one visible field
                    $hasVisible = true;

                    // No need to check further
                    break;
                }
            }

            // Check if there are any visible fields to show
            if ($hasVisible) {
                // Check if preset-based visibility condition is present
                $presetIf = $this->getSubfieldPresetIfCondition(
                    $handle,
                    $currentPreset,
                );

                // Create a section container for this subfield's configuration array
                $section = [
                    'type' => 'subsection',
                    'display' => $display,
                ];

                // Check if preset-based visibility condition is present
                if ($presetIf !== null) {
                    // Apply the preset condition to control section visibility
                    $section['if'] = $presetIf;
                }

                // Add the section to the grouped configuration
                $grouped['section_'.$handle] = $section;

                // ---
                // Add all subfield configuration fields to the grouped array
                // ---

                // Iterate over each subfield configuration field
                foreach ($subfieldFields as $key => $field) {
                    // Include each subfield's config field in the final configuration
                    $grouped[$key] = $field;
                }
            }
        }

        // Create the main subfields configuration group
        $fields[] = [
            'display' => __('Subfields'),
            'fields' => $grouped,
        ];

        // Clean up variables to free up memory
        unset(
            $config,
            $subfieldConfigFields,
            $grouped,
            $presetOptions,
            $currentPreset,
            $preset,
            $presetField,
        );

        // Return the complete configuration field structure
        return $fields;
    }

    /**
     * Helper to generate validation rules for subfields.
     *
     * Only applies 'required' if parent is required and subfield is toggled on and
     * required.
     *
     * @param  array<string, mixed>  $config  The current field configuration
     * @param  bool  $parentRequired  Whether the parent field is required
     * @return array<string, mixed> Associative array mapping subfield canonical handles to validation rules, each a string or a list of rules (e.g., ['city' => 'required'])
     */
    protected function getSubfieldValidationRules(
        array $config,
        bool $parentRequired
    ): array {
        // Initialize the rules array
        $rules = [];

        // Get preset options for this fieldtype
        $presetOptionsRaw = $this->getPresetOptions();

        // Extract the actual options array from preset options
        $presetOptions = $presetOptionsRaw['options'] ?? $presetOptionsRaw;

        // Determine the current preset from config or use the first available preset
        /** @var ?string $currentPreset */
        $currentPreset = $config['preset'] ?? (\is_array($presetOptions) ? array_key_first($presetOptions) : null);

        // Loop through each subfield definition to generate validation rules
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the handle for this subfield
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Get the canonical handle for this subfield (fall back to the handle)
            $canonicalRaw = $subfield['canonical_handle'] ?? $handle;
            $canonical_handle = \is_scalar($canonicalRaw) ? (string) $canonicalRaw : $handle;

            // Get the include setting for this subfield from config or default
            $include = $config[$handle.'_include'] ?? $subfield['inclusion_default'] ?? true;

            // Check preset-based visibility
            $presetIf = $this->getSubfieldPresetIfCondition(
                $handle,
                $currentPreset
            );

            // Initialize preset visible to true by default
            $presetVisible = true;

            // Check if preset-based visibility condition is defined
            if ($presetIf !== null) {
                // If presetIf is defined, default to not visible until condition is met
                $presetVisible = false;

                // Iterate over each preset if condition
                foreach ($presetIf as $key => $value) {
                    // Resolve the preset key against the effective (default-aware) preset
                    // so a subfield gated on the default preset stays visible when the
                    // blueprint omits an explicit `preset` (the CP strips default-valued config)
                    $effectiveValue = $key === 'preset' ? $currentPreset : ($config[$key] ?? null);

                    // Check if the effective config value matches the condition
                    if ($effectiveValue === $value) {
                        // Set preset visible to true when condition is met
                        $presetVisible = true;

                        // Break from loop since condition is met
                        break;
                    }
                }
            }

            // Skip subfields hidden by the current preset so they cannot clobber a
            // shared canonical_handle rule set by the preset-visible sibling (e.g. the
            // three state_province_region_* subfields all write 'state_province_region')
            if (! $presetVisible) {
                continue;
            }

            // Get the required setting for this subfield from config or default
            $required = $config[$handle.'_required'] ?? $subfield['required_default'] ?? false;

            // Check if subfield should be required based on all conditions
            if (
                $parentRequired
                && $include
                && $required
            ) {
                // Set required validation rule for this subfield
                $rules[$canonical_handle] = 'required';
            } else { // Otherwise
                // Set nullable validation rule for this subfield
                $rules[$canonical_handle] = 'nullable';
            }
        }

        // Clean up variables to free up memory
        unset(
            $subfield,
            $handle,
            $canonical_handle,
            $include,
            $required,
            $presetIf,
            $presetVisible,
            $currentPreset,
            $presetOptions,
            $presetOptionsRaw
        );

        // Return the validation rules array
        return $rules;
    }

    /**
     * Format subfield data for storage, matching Statamic's array fieldtype structure.
     *
     * If a subfield has 'canonical_handle', its value will be stored under that key.
     * Only the first value for each canonical_handle is stored (useful for
     * conditional subfields).
     *
     * @param  array<string, mixed>  $data  Associative array of subfield values (handle => value)
     * @return list<array{key: string, value: mixed}> Array of ['key' => string, 'value' => mixed] associative arrays
     */
    protected function formatSubfieldsForStorage(
        array $data
    ): array {
        // Initialize the result array that will be returned
        $result = [];

        // Track which canonical_handle values have already been used
        $usedReturnHandles = [];

        // Get the current config for this field instance
        $config = $this->getCachedConfig();

        // Iterate over each subfield definition
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the subfield's handle
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Determine the key to use for storage (canonical_handle or handle)
            /** @var string $canonical_handle */
            $canonical_handle = $subfield['canonical_handle'] ?? $handle;

            // Get the subfield type (e.g., 'text', 'select')
            $type = $subfield['type'] ?? null;

            // Get the available choices for select fields, if any
            /** @var ?array<array-key, mixed> $defaultChoices */
            $defaultChoices = $subfield['default_choices'] ?? null;

            // Get the select value type config for this subfield ('key' or 'label')
            $selectValueType = $config[$handle.'_select_value_type'] ?? 'label';

            // Only store the first value for each canonical_handle, and only if the data exists for this handle
            if (
                \array_key_exists(
                    $handle,
                    $data
                )
                && ! \in_array(
                    $canonical_handle,
                    $usedReturnHandles,
                    true
                )
            ) {
                // Add the subfield value to the result array with the appropriate key
                $value = $data[$handle];

                // If this is a select subfield and selectValueType is set, transform value accordingly.
                if (
                    $type === 'select'
                    && $defaultChoices
                    && $selectValueType === 'label'
                ) {
                    // Check if value is a key
                    if (
                        (\is_string($value) || \is_int($value))
                        && \array_key_exists(
                            $value,
                            $defaultChoices
                        )
                    ) {
                        // Convert the value to the label
                        $value = $defaultChoices[$value];
                    }
                } elseif (
                    $type === 'select'
                    && $defaultChoices
                    && $selectValueType === 'key'
                ) { // Check if select value type is key and convert label to key
                    // Search for the value in the default choices array to find its key
                    $key = \array_search(
                        $value,
                        $defaultChoices,
                        true
                    );

                    // Check if the key is not false
                    if ($key !== false) {
                        // Convert the value to the key
                        $value = $key;
                    }
                }

                // Add the subfield entry to the result array
                $result[] = [
                    'key' => $canonical_handle,
                    'value' => $value,
                ];

                // Mark this canonical_handle as used so we don't store it again
                $usedReturnHandles[] = $canonical_handle;
            }
        }

        // Clean up variables to free up memory
        unset(
            $usedReturnHandles,
            $subfield,
            $handle,
            $canonical_handle,
            $type,
            $defaultChoices,
            $selectValueType,
            $value,
            $key,
        );

        // Return the formatted result array for storage
        return $result;
    }

    /**
     * Parse subfield data from storage (Statamic array fieldtype structure) to
     * associative array.
     *
     * If a subfield has 'canonical_handle', the value for that key will be mapped to
     * the subfield's handle.
     *
     * @param  list<mixed>  $data  List of ['key' => string, 'value' => mixed] associative arrays (rows may be malformed, hence the per-item array check)
     * @return array<string, mixed> Associative array mapping subfield handles to their values (handle => value)
     */
    protected function parseSubfieldsFromStorage(
        array $data
    ): array {
        // Start with an empty result array for parsed subfields
        $result = [];

        // Start with an empty storage array to map keys to values
        $storage = [];

        // Populate storage array from input data
        foreach ($data as $item) {
            // Check if the item is an array and has a 'key'
            if (
                \is_array($item)
                && isset($item['key'])
            ) {
                // Store the value in the storage array
                /** @var int|string $itemKey */
                $itemKey = $item['key'];
                $storage[$itemKey] = $item['value'] ?? null;
            }
        }

        // Map storage values to subfield handles
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the handle for this subfield
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Get the canonical handle for this subfield
            /** @var int|string $canonical_handle */
            $canonical_handle = $subfield['canonical_handle'] ?? $handle;

            // Check if the storage contains the canonical handle
            if (
                \array_key_exists(
                    $canonical_handle,
                    $storage
                )
            ) {
                // Map the value to the subfield handle
                $result[$handle] = $storage[$canonical_handle];
            }
        }

        // Clean up variables to free up memory
        unset(
            $storage,
            $item,
            $subfield,
            $handle,
            $canonical_handle,
        );

        // Return the associative array of subfield values
        return $result;
    }

    /**
     * Get subfield definitions for the Vue component.
     *
     * This method provides the subfield data to the Vue component through the meta
     * property. Only includes subfields that should be visible based on preset and
     * inclusion settings.
     *
     * @return list<array<string, mixed>> Array of subfield definition arrays for Vue component
     */
    public function getSubfieldsForVue(): array
    {
        // Start with an empty subfields array for Vue
        $subfields = [];

        // Get preset options for this fieldtype (if any)
        $presetOptionsRaw = $this->getPresetOptions();

        // Get cached config to prevent infinite recursion
        $config = $this->getCachedConfig();

        // Extract the actual options array from preset options
        $presetOptions = $presetOptionsRaw['options'] ?? $presetOptionsRaw;

        // Determine the current preset from config or use the first available preset
        /** @var ?string $currentPreset */
        $currentPreset = $config['preset'] ?? (\is_array($presetOptions) ? array_key_first($presetOptions) : null);

        // Iterate over each subfield definition to build the Vue subfields array
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the handle for this subfield
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Check if this subfield should be included based on config
            $include = $config[$handle.'_include'] ?? $subfield['inclusion_default'] ?? true;

            // Check if this subfield should be visible based on preset
            $presetIf = $this->getSubfieldPresetIfCondition(
                $handle,
                $currentPreset
            );

            // Default to visible unless presetIf restricts it
            $presetVisible = true;

            // ---
            // If presetIf is defined, check if the preset condition is met
            // ---

            // Check if preset-based visibility condition is defined
            if ($presetIf !== null) {
                // Default to not visible until condition is met
                $presetVisible = false;

                // ---
                // If the config matches the presetIf condition, mark as visible
                // ---

                // Loop through each preset if condition to check visibility
                foreach ($presetIf as $key => $value) {
                    // Resolve the preset key against the effective (default-aware) preset
                    // so the default-preset subfields still render when `preset` is omitted
                    $effectiveValue = $key === 'preset' ? $currentPreset : ($config[$key] ?? null);

                    // Check if the effective config value matches the condition
                    if ($effectiveValue === $value) {
                        // Set preset visible to true when condition is met
                        $presetVisible = true;

                        // Break from loop since condition is met
                        break;
                    }
                }
            }

            // Check if subfield should be included and is visible for current preset
            if (
                $include
                && $presetVisible
            ) {
                // Get the canonical handle for this subfield
                $canonical_handle = $subfield['canonical_handle'] ?? $subfield['handle'];

                // Build the subfield array for Vue
                $subfieldArr = [
                    'handle' => $canonical_handle,
                    'vue_handle' => $canonical_handle,
                    'label' => $config[$handle.'_label'] ?? $subfield['label_default'] ?? $subfield['display'],
                    'type' => $subfield['type'],
                    'options' => $subfield['default_choices'] ?? null,
                    'width' => $config[$handle.'_width'] ?? null,
                    'tailwind_width_class' => Str::tailwindWidthClass($config[$handle.'_width'] ?? null),
                    'required' => $config[$handle.'_required'] ?? $subfield['required_default'] ?? false,
                    'include' => $include,
                    'canonical_handle' => $subfield['canonical_handle'] ?? null,
                    'autocomplete' => $subfield['autocomplete'] ?? null,
                    'default' => $config[$handle.'_default'] ?? $subfield['default_value'] ?? null,
                ];

                // ---
                // Only set placeholder for text or textarea subfields
                // ---

                // Check if subfield type is text, textarea, or zip_code
                if (
                    \in_array(
                        $subfield['type'],
                        [
                            'text',
                            'textarea',
                            'zip_code',
                        ]
                    )
                ) {
                    // Set placeholder value for this subfield
                    $subfieldArr['placeholder'] = $config[$handle.'_placeholder'] ?? $subfield['placeholder_default'] ?? null;
                }

                // ---
                // Pass select_value_type for select subfields
                // ---

                // Check if subfield type is select
                if ($subfield['type'] === 'select') {
                    // Get the select value type from config or default to label
                    $selectValueType = $config[$handle.'_select_value_type'] ?? 'label';

                    // Set select value type in the subfield array
                    $subfieldArr['select_value_type'] = $selectValueType;

                    // Check if select value type is label and default value exists
                    if (
                        $selectValueType === 'label'
                        && $subfield['default_choices']
                        && $subfieldArr['default']
                    ) {
                        // Get the default value for conversion
                        $defaultValue = $subfieldArr['default'];

                        // Get the choices array to look up the matching label
                        /** @var array<array-key, mixed> $choices */
                        $choices = $subfield['default_choices'];

                        // Check if default value exists as a key in the choices array
                        if (
                            (\is_string($defaultValue) || \is_int($defaultValue))
                            && \array_key_exists(
                                $defaultValue,
                                $choices
                            )
                        ) {
                            // Convert the default value key to its corresponding label
                            $subfieldArr['default'] = $choices[$defaultValue];
                        }
                    }
                }

                // Add the subfield array to the subfields list
                $subfields[] = $subfieldArr;
            }
        }

        // Clean up variables to free up memory
        unset(
            $presetOptions,
            $presetOptionsRaw,
            $config,
            $currentPreset,
            $subfield,
            $handle,
            $include,
            $presetIf,
            $presetVisible,
            $canonical_handle,
            $subfieldArr,
        );

        // Return the array of subfields for Vue
        return $subfields;
    }

    /**
     * Provide meta data for the Vue component, including subfields and the fieldtype
     * handle.
     *
     * This can be used as the preload() method for all fieldtypes using this trait.
     *
     * @return array<string, mixed> Preload data
     */
    public function preload(): array
    {
        return [
            'subfields' => $this->getSubfieldsForVue(),
            'fieldtype_handle' => $this->handle(),
        ];
    }

    /**
     * Statamic validation: only validate as array at parent level.
     * All required/other rules are attached to subfields in extraRules().
     *
     * @return array<string> Validation rules (Laravel format)
     */
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * Statamic extra validation: attach rules to subfields using dot notation.
     *
     * @return array<string, mixed> Associative array mapping dot-notated field paths to validation rules (e.g., ['address.city' => 'required'])
     */
    public function extraRules(): array
    {
        // Get the current config for this field instance
        $config = $this->getCachedConfig();

        // Determine if the parent field is required
        $parentRequired = isset($config['validate'])
            && \in_array(
                'required',
                (array) $config['validate'],
                true
            );

        // Get validation rules for each subfield (handle => rule)
        $rules = $this->getSubfieldValidationRules(
            $config,
            $parentRequired
        );

        // Get the parent field handle to use as the prefix
        /** @var Field $field */
        $field = $this->field();
        /** @var string $prefix */
        $prefix = $field->handle();

        // Explode blueprint validate rules for sometimes handling on nested keys
        $explodedValidate = $this->explodedBlueprintValidateRulesFromConfig($config);

        // Build keyed rule lists for conditional prepending of sometimes
        /** @var array<string, array<int, object|string>> $keyedRules */
        $keyedRules = [];

        // Attach each subfield rule using dot notation (e.g., address.city)
        foreach ($rules as $canonical_handle => $rule) {
            // Normalize string rules to arrays for prependSometimes processing
            /** @var array<int, object|string> $ruleSet */
            $ruleSet = \is_array($rule)
                ? $rule
                : [$rule];
            $keyedRules[sprintf('%s.%s', $prefix, $canonical_handle)] = $ruleSet;
        }

        // Prepend sometimes to nested paths when the blueprint uses sometimes
        $dotRules = $this->conditionallyPrependSometimesToKeyedRuleSets(
            $keyedRules,
            $explodedValidate
        );

        // Clean up variables to free up memory
        unset(
            $rules,
            $prefix,
            $config,
            $parentRequired,
            $explodedValidate,
            $keyedRules,
        );

        // Return the dot-notated rules array
        return $dotRules;
    }

    /**
     * Ensure the value is always an array for validation and processing.
     *
     * This matches Statamic's group fieldtype behavior and prevents the parent
     * 'array' rule from failing (which would show a global error).
     *
     * @param  mixed  $data  The data to pre-process
     * @return array<string, mixed> The data as an array (empty array if not an array)
     */
    public function preProcess(
        $data
    ): array {
        /** @var array<string, mixed> $result */
        $result = \is_array($data) ? $data : [];

        return $result;
    }

    /**
     * Ensure the value is always an array when saving.
     *
     * @param  mixed  $data  The data to process
     * @return array<string, mixed> The data as an array (empty array if not an array)
     */
    public function process(
        $data
    ): array {
        /** @var array<string, mixed> $result */
        $result = \is_array($data) ? $data : [];

        return $result;
    }

    /**
     * Ensure all subfields are present in the value passed to validation.
     *
     * This matches Statamic's Group fieldtype and prevents the parent error
     * from showing when only subfields are required.
     *
     * @param  mixed  $value  The value to pre-process for validation
     * @return array<string, mixed> The value as an array with all subfields present
     */
    public function preProcessValidatable(
        $value
    ): array {
        // Normalize absent or scalar input for validation
        if ($value === null || $value === '') {
            return [];
        }

        // Coerce to array for subfield checks and merging
        /** @var array<string, mixed> $normalized */
        $normalized = \is_array($value) ? $value : [];

        // Avoid expanding to null-filled keys when there is no real input (hidden or
        // cleared conditional fields); nested required rules then skip with sometimes.
        if (! $this->valueContainsFilledSubfield($normalized)) {
            return [];
        }

        // Merge the provided value with all subfields (ensuring all are present)
        $result = \array_merge(
            $normalized,
            $this->getSubfieldsForValidation($normalized)
        );

        // Return the merged result for validation
        return $result;
    }

    /**
     * Helper to get all subfields with their value or null.
     *
     * @param  array<string, mixed>  $value  The current value array
     * @return array<string, mixed> Associative array mapping subfield handles to their values or null
     */
    protected function getSubfieldsForValidation(
        array $value
    ): array {
        // Start with an empty result array for subfields
        $result = [];

        // Iterate over each subfield definition to ensure all are present
        foreach ($this->getCachedSubfieldDefinitions() as $subfield) {
            // Get the handle for this subfield
            /** @var string $handle */
            $handle = $subfield['handle'];

            // Set the value for this subfield or null if not present
            $result[$handle] = $value[$handle] ?? null;
        }

        // Return the array of subfields with values or null
        return $result;
    }

    /**
     * Public proxy for preset-based subfield condition logic.
     *
     * Allows external code to check if a subfield should be included for a given
     * preset.
     *
     * @param  string  $handle  The subfield handle to check
     * @param  ?string  $preset  The preset value to check against (e.g., 'american', 'international')
     * @return ?array<string, mixed> The 'if' condition array for the subfield and preset, or null if none
     */
    public function subfieldPresetIfCondition(
        string $handle,
        ?string $preset
    ): ?array {
        return $this->getSubfieldPresetIfCondition(
            $handle,
            $preset
        );
    }

    /**
     * Given a canonical handle and a preset, return the Statamic subfield handle for
     * config keys.
     *
     * This method finds all subfields whose canonical_handle matches the given
     * canonical handle. If multiple matches exist, it prefers the one whose handle
     * ends with _{preset}. Otherwise, it returns the first match. Returns null if no
     * match is found.
     *
     * @param  string  $canonicalHandle  The canonical handle to search for (e.g., 'state_province_region')
     * @param  ?string  $preset  The preset value to match (e.g., 'american', 'international')
     * @return ?string The Statamic subfield handle for config keys, or null if not found
     */
    public function getPresetSpecificSubfieldHandle(
        string $canonicalHandle,
        ?string $preset = null
    ): ?string {
        // Use the provided preset, or fall back to the current config's preset if not given
        $preset ??= $this->getCachedConfig()['preset'] ?? null;
        /** @var ?string $preset */

        // Find all subfields whose canonical_handle matches the given canonical handle
        $matches = \array_filter(
            $this->getCachedSubfieldDefinitions(),
            fn (array $subfield): bool => ($subfield['canonical_handle'] ?? $subfield['handle']) === $canonicalHandle
        );

        // Check if no matches are found
        if ($matches === []) {
            // Return null if no matching subfields found
            return null;
        }

        // ---
        // If a preset is provided, prefer a subfield whose handle ends with _{preset}
        // ---

        // Check if a preset is provided
        if ($preset) {
            // Loop through each matching subfield to find preset-specific match
            foreach ($matches as $subfield) {
                // Get the handle for this matching subfield
                /** @var string $matchHandle */
                $matchHandle = $subfield['handle'];

                // Check if subfield handle ends with the preset suffix
                if (
                    \str_ends_with(
                        $matchHandle,
                        '_'.$preset
                    )
                ) {
                    // Return the preset-specific subfield handle
                    return $matchHandle;
                }
            }
        }

        // ---
        // Fallback to the first matching handle if no preset-specific match is found
        // ---

        // Get the first matching handle as fallback ($matches is non-empty here)
        /** @var string $first */
        $first = $matches[array_key_first($matches)]['handle'];

        // Return the first matching handle
        return $first;
    }
}
