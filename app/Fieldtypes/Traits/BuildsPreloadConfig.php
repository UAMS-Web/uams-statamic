<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use Statamic\Fields\Field;

/**
 * Resolves and hydrates the runtime config delivered to the Vue component.
 *
 *   - Normalizes raw field config with defaults, applies preset overrides, and
 *     resolves the values the frontend partial consumes via `preload()`.
 *   - Child fieldtypes may override `modifyPreloadConfig()` to adjust the
 *     resolved config without re-implementing the resolution pipeline.
 *
 * Extracted from the former monolithic {@see Input} trait (issue UAMS-Web/uams-statamic-archive#873).
 */
trait BuildsPreloadConfig
{
    /**
     * Ensures all config fields are set, using defaults if not present.
     *
     * @param  array<string, mixed>  $config  The config array to normalize
     * @return array<string, mixed> The normalized config array
     */
    protected function normalizeConfigWithDefaults(
        array $config
    ): array {
        // Get config field overrides from the fieldtype (may include 'default' values)
        $overrides = $this->getConfigFieldOverrides();

        // Initialize the defaults array
        /** @var array<string, mixed> $defaults */
        $defaults = [];

        // ---
        // Gather default values from getConfigFieldOverrides (per-field 'default' keys)
        // ---

        // Loop through config field overrides to gather default values
        foreach ($overrides as $key => $attrs) {
            // Check if a default value is specified for this field
            if (\is_array($attrs) && isset($attrs['default'])) {
                // Add the default value to the defaults array
                $defaults[$key] = $attrs['default'];
            }
        }

        // ---
        // Merge in default values from getAdditionalConfigFields (if any)
        // ---

        // Loop through additional config fields to gather default values
        foreach ($this->getAdditionalConfigFields() as $key => $field) {
            // Check if a default value is specified for this additional config field
            if (\is_array($field) && isset($field['default'])) {
                // Add the default value to the defaults array
                $defaults[$key] = $field['default'];
            }
        }

        // ---
        // Merge in defaults from presets if the fieldtype supports presets
        // ---

        // Check if the fieldtype supports presets
        if (
            method_exists(
                $this,
                'getPresets'
            )
        ) {
            // Get the preset definitions
            /** @var array<string, mixed> $presets */
            $presets = $this->getPresets();

            // Determine the default preset key (if any)
            $defaultPresetKey = $presets['default'] ?? null;

            // ---
            // If a default preset exists and has config overrides, merge them in
            // ---

            // Extract the preset options array
            $presetOptions = $presets['options'] ?? [];

            // Check if a default preset exists and has config overrides
            if (
                $defaultPresetKey
                && (\is_string($defaultPresetKey) || \is_int($defaultPresetKey))
                && \is_array($presetOptions)
                && \is_array($presetOptions[$defaultPresetKey] ?? null)
                && isset($presetOptions[$defaultPresetKey]['config'])
                && \is_array($presetOptions[$defaultPresetKey]['config'])
            ) {
                // Merge the default preset's config values into the defaults array
                /** @var array<string, mixed> $presetConfig */
                $presetConfig = $presetOptions[$defaultPresetKey]['config'];
                $defaults = \array_merge(
                    $defaults,
                    $presetConfig
                );

                // Set the 'preset' key to the default preset key
                $defaults['preset'] = $defaultPresetKey;
            }
        }

        // ---
        // Merge in the provided config (user-supplied or from YAML), which takes
        // precedence
        // ---
        return \array_merge(
            $defaults,
            $config
        );
    }

    /**
     * Process config for preload, including 'null' conversion and frontend
     * resolution. This method can be called by fieldtypes that override preload() to
     * get the same processing that the Input trait provides.
     *
     * @param  ?array<string, mixed>  $config  The config array to process
     * @return array<string, mixed> The processed config array
     */
    protected function processPreloadConfig(
        ?array $config = null
    ): array {
        // Check if config was not provided
        if ($config === null) {
            // Get the cached config
            $config = $this->getCachedConfig();
        }

        // Normalize config with defaults to ensure all required keys are present
        $config = $this->normalizeConfigWithDefaults($config);

        // ---
        // If the fieldtype defines presets and a preset is set, merge its config
        // overrides
        // ---

        // Check if fieldtype supports presets and a preset is configured
        if (
            method_exists(
                $this,
                'getPresets'
            )
            && isset($config['preset'])
        ) {
            // Get the preset definitions
            /** @var array<string, mixed> $presets */
            $presets = $this->getPresets();

            // Extract the preset options array
            $options = $presets['options'] ?? [];

            // Get the preset key from the cached config
            $presetKey = $config['preset'];

            // Check if the preset key exists in the options array and if it is an array
            if (
                \is_array($options)
                && (\is_string($presetKey) || \is_int($presetKey))
                && \is_array($options[$presetKey] ?? null)
                && isset($options[$presetKey]['config'])
                && \is_array($options[$presetKey]['config'])
            ) {
                // Merge the preset's config values into the config array
                $config = \array_merge(
                    $config,
                    $options[$presetKey]['config']
                );
            }
        }

        // --- Begin: Resolved config values for frontend ---

        // Convert 'null' values to null for consistent processing
        $config = \array_map(
            fn ($value) => ($value === 'null') ? null : $value,
            $config
        );

        // ---
        // autocomplete
        // ---

        // Check if autocomplete query is enabled and autocomplete is empty
        if (
            isset($config['autocomplete_query'])
            && $config['autocomplete_query'] === 'on'
            && empty($config['autocomplete'])
        ) {
            // Set autocomplete to the query value
            $config['autocomplete'] = $config['autocomplete_query'];
        }

        // ---
        // autocapitalize
        // ---

        // Check if autocapitalize is enabled and a setting is provided
        if (
            isset($config['autocapitalize'])
            && $config['autocapitalize'] === 'on'
            && ! empty($config['autocapitalize_setting'])
        ) {
            // Set autocapitalize to the setting value
            $config['autocapitalize'] = $config['autocapitalize_setting'];
        }

        // ---
        // contenteditable
        // ---

        // Check if field type is not hidden and visibility is not hidden
        if (
            (
                isset($config['type'])
                && $config['type'] !== 'hidden'
            )
            && (
                ! isset($config['visibility'])
                || $config['visibility'] !== 'hidden'
            )
        ) {
            // Set contenteditable to true for editable fields
            $config['contenteditable'] = 'true';
        } else { // Otherwise
            $config['contenteditable'] = 'false';
        }

        // ---
        // dirname
        // ---

        // Check if dir and name attributes are both set
        if (
            isset($config['dir'])
            && $config['dir']
            && isset($config['name'])
            && $config['name']
        ) {
            // Set dirname to name attribute with .dir suffix
            $name = $config['name'];
            $config['dirname'] = (\is_scalar($name) ? $name : '').'.dir';
        }

        // ---
        // Input Mask `mask` attribute
        // ---

        // Check if input mask is enabled
        if (
            isset($config['input_mask'])
            && $config['input_mask'] === true
        ) {
            // ---
            // Change input type (`input_type`) to `text` when `input_mask` is enabled so
            // that IMask will function
            // ---
            $config['input_type'] = 'text';

            // ---
            // Pass values to the `input_mask_mask` attribute based on the `input_mask_class`
            // value
            // ---

            // ---
            // For the `MaskedDynamic` class, set `input_mask_mask` to the value of
            // `input_mask_mask_MaskedDynamic`
            // ---

            // Check if MaskedDynamic class is selected and mask value is provided
            if (
                (
                    isset($config['input_mask_class'])
                    && $config['input_mask_class'] === 'MaskedDynamic'
                )
                && (
                    isset($config['input_mask_mask_MaskedDynamic'])
                    && $config['input_mask_mask_MaskedDynamic'] !== ''
                )
            ) {
                // Set `input_mask_mask` to the value of `input_mask_mask_MaskedDynamic`
                $config['input_mask_mask'] = $config['input_mask_mask_MaskedDynamic'];

                // Unset the `input_mask_mask_MaskedDynamic` attribute
                unset($config['input_mask_mask_MaskedDynamic']);
            }

            // ---
            // For the `MaskedFunction` class, set `input_mask_mask` to the value of
            // `input_mask_mask_MaskedFunction`
            // ---

            // Check if MaskedFunction class is selected and mask value is provided
            if (
                (
                    isset($config['input_mask_class'])
                    && $config['input_mask_class'] === 'MaskedFunction'
                )
                && (
                    isset($config['input_mask_mask_MaskedFunction'])
                    && $config['input_mask_mask_MaskedFunction'] !== ''
                )
            ) {
                // Set `input_mask_mask` to the value of `input_mask_mask_MaskedFunction`
                $config['input_mask_mask'] = $config['input_mask_mask_MaskedFunction'];

                // Unset the `input_mask_mask_MaskedFunction` attribute
                unset($config['input_mask_mask_MaskedFunction']);
            }

            // ---
            // For the `MaskedPattern` class, set `input_mask_mask` to the value of
            // `input_mask_mask_MaskedPattern`
            // ---

            // Check if MaskedPattern class is selected and mask value is provided
            if (
                (
                    isset($config['input_mask_class'])
                    && $config['input_mask_class'] === 'MaskedPattern'
                )
                && (
                    isset($config['input_mask_mask_MaskedPattern'])
                    && $config['input_mask_mask_MaskedPattern'] !== ''
                )
            ) {
                // Set `input_mask_mask` to the value of `input_mask_mask_MaskedPattern`
                $config['input_mask_mask'] = $config['input_mask_mask_MaskedPattern'];

                // Unset the `input_mask_mask_MaskedPattern` attribute
                unset($config['input_mask_mask_MaskedPattern']);
            }

            // ---
            // For the `MaskedRegExp` class, set `input_mask_mask` to the value of
            // `input_mask_mask_MaskedRegExp`
            // ---

            // Check if MaskedRegExp class is selected and mask value is provided
            if (
                (
                    isset($config['input_mask_class'])
                    && $config['input_mask_class'] === 'MaskedRegExp'
                )
                && (
                    isset($config['input_mask_mask_MaskedRegExp'])
                    && $config['input_mask_mask_MaskedRegExp'] !== ''
                )
            ) {
                // Set `input_mask_mask` to the value of `input_mask_mask_MaskedRegExp`
                $config['input_mask_mask'] = $config['input_mask_mask_MaskedRegExp'];

                // Unset the `input_mask_mask_MaskedRegExp` attribute
                unset($config['input_mask_mask_MaskedRegExp']);
            }
        }

        // ---
        // Dynamically set the value of the `calculation_formula` attribute based on the
        // `calculation_type` value
        // ---

        // Check if calculation query is enabled
        if (
            isset($config['calculation_query'])
            && $config['calculation_query'] === true
        ) {
            // ---
            // For the `math` type, set `calculation_formula` to the value of
            // `calculation_formula_math`
            // ---

            // Check if calculation type is math and formula is provided
            if (
                (
                    isset($config['calculation_type'])
                    && $config['calculation_type'] === 'math'
                )
                && (
                    isset($config['calculation_formula_math'])
                    && $config['calculation_formula_math'] !== ''
                )
            ) {
                // Set `calculation_formula` to the value of `calculation_formula_math`
                $config['calculation_formula'] = $config['calculation_formula_math'];

                // Unset the `calculation_formula_math` attribute
                unset($config['calculation_formula_math']);
            } elseif (
                (
                    isset($config['calculation_type'])
                    && $config['calculation_type'] === 'text'
                )
                && (
                    isset($config['calculation_formula_text'])
                    && $config['calculation_formula_text'] !== ''
                )
            ) { // Check if calculation type is text and formula is provided
                // ---
                // For the `text` type, set `calculation_formula` to the value of
                // `calculation_formula_text`
                // ---

                // Set `calculation_formula` to the value of `calculation_formula_text`
                $config['calculation_formula'] = $config['calculation_formula_text'];

                // Unset the `calculation_formula_text` attribute
                unset($config['calculation_formula_text']);
            }
        }

        // ---
        // Convert focus string values to proper types for Statamic's Vue component.
        // Statamic's TextFieldtype.vue checks `config.focus === false` to explicitly
        // disable.
        //
        // Note: Cannot use (bool) cast because string 'false' is truthy in PHP.
        // ---
        if (
            isset($config['focus'])
            && \is_string($config['focus'])
        ) {
            $config['focus'] = match ($config['focus']) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $config['focus'],
            };
        }

        // --- End: Resolved config values for frontend ---

        // Allow fieldtypes to modify the config before internal keys are stripped
        $config = $this->modifyPreloadConfig($config);

        // ---
        // Strip out config keys flagged as 'internal' in configFieldItems
        // ---

        // Get the config field items array
        $fieldsArray = $this->configFieldItems();

        // Initialize array to store internal keys
        $internalKeys = [];

        // Loop through field sections to find internal fields
        foreach ($fieldsArray as $section) {
            // Check if section has fields and is an array
            if (
                ! empty($section['fields'])
                && \is_array($section['fields'])
            ) {
                // Loop through fields in this section
                foreach ($section['fields'] as $key => $field) {
                    // Check if field is marked as internal
                    if (\is_array($field) && ! empty($field['internal'])) {
                        // Add field key to internal keys array
                        $internalKeys[] = $key;
                    }
                }
            }
        }

        // Loop through internal keys to remove them from config
        foreach ($internalKeys as $internalKey) {
            // Remove internal key from config array
            unset($config[$internalKey]);
        }

        // Set the handle for Vue field ID generation
        /** @var Field $field */
        $field = $this->field();
        $config['handle'] = $field->handle();

        // Clean up variables to free up memory
        unset(
            $fieldsArray,
            $internalKeys,
            $internalKey,
            $section,
            $key,
            $field
        );

        // Return the processed config array
        return $config;
    }

    /**
     * Preload config data for the Vue component.
     *
     * This method provides the form fields to the Vue component so it can populate
     * the field selection dropdowns. It merges preset config overrides into the
     * config array so the Vue component always receives the expanded config,
     * regardless of what is in the YAML.
     *
     * @return array<string, mixed> Preload data
     */
    public function preload(): array
    {
        // Process the config using the helper method
        $config = $this->processPreloadConfig();

        // Return the config array with preset logic applied
        return [
            'config' => $config,
            'fieldtype_handle' => $this->handle(),
        ];
    }

    /**
     * Extension point for fieldtypes to modify the preload config array before
     * internal keys are stripped.
     *
     * @param  array<string, mixed>  $config  The config array to modify
     * @return array<string, mixed> The modified config array
     */
    protected function modifyPreloadConfig(
        array $config
    ): array {
        // Return config unchanged by default (child classes can override)
        return $config;
    }
}
