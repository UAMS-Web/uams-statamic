<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use App\Enums\Country;
use App\Enums\Currency;
use App\Enums\Language;
use App\Enums\LanguageBCP47;
use App\Enums\MimeType;
use App\Enums\State;

/**
 * Trait to provide preset options functionality for select-like fieldtypes.
 *
 * When included in a fieldtype, this trait provides a method to override the
 * options with predefined lists (e.g., U.S. States) if a preset is selected
 * in the config. This allows for consistent, DRY preset functionality across
 * Checkboxes, Radio, and Select fieldtypes.
 *
 * The trait adds a 'preset' config field that allows users to select from
 * predefined option lists. When a preset is selected, it overrides the
 * manually entered options in the fieldtype configuration.
 *
 * Only use this trait in custom fieldtypes that:
 *   - Extend a Statamic fieldtype (or other parent) that implements getOptions().
 *   - Do not require further customization of getOptions() beyond preset
 * functionality.
 *
 * This trait works in conjunction with the RandomizesOptions trait. The
 * RandomizesOptions trait will automatically detect and use this trait's
 * getOptionsWithPreset() method to apply preset options before randomization.
 *
 * If you need additional custom logic in getOptions(), override it in your
 * fieldtype and call $this->maybeUsePresetOptions() as needed.
 */
trait PresetOptions
{
    /**
     * Get all available presets with their complete configuration.
     *
     * This method defines the available presets with their options and config
     * settings. Override this method in your fieldtype to provide custom preset
     * definitions.
     *
     * @todo  Add support for defining/hiding custom config fields like `randomize_order`.
     *
     * @return array<string, array<string, mixed>> Array of presets with their complete configuration
     */
    protected function getPresetDefinitions(): array
    {
        return [
            'us_states' => [
                'display' => 'U.S. States, Territories and Districts',
                'options' => [], // Will be populated dynamically
                'has_config' => true,
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'canadian_provinces' => [
                'display' => 'Canadian Provinces',
                'options' => [
                    'AB' => 'Alberta',
                    'BC' => 'British Columbia',
                    'MB' => 'Manitoba',
                    'NB' => 'New Brunswick',
                    'NL' => 'Newfoundland and Labrador',
                    'NS' => 'Nova Scotia',
                    'NT' => 'Northwest Territories',
                    'NU' => 'Nunavut',
                    'ON' => 'Ontario',
                    'PE' => 'Prince Edward Island',
                    'QC' => 'Quebec',
                    'SK' => 'Saskatchewan',
                    'YT' => 'Yukon',
                ],
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'countries' => [
                'display' => 'Countries',
                'options' => $this->getCountryOptions(),
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'languages' => [
                'display' => 'Languages',
                'options' => [], // Will be populated dynamically
                'has_config' => true,
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'currencies' => [
                'display' => 'Currencies',
                'options' => $this->getCurrencyOptions(),
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'months' => [
                'display' => 'Months',
                'options' => [
                    '01' => 'January',
                    '02' => 'February',
                    '03' => 'March',
                    '04' => 'April',
                    '05' => 'May',
                    '06' => 'June',
                    '07' => 'July',
                    '08' => 'August',
                    '09' => 'September',
                    '10' => 'October',
                    '11' => 'November',
                    '12' => 'December',
                ],
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'days_of_week' => [
                'display' => 'Days of Week',
                'options' => [
                    '1' => 'Monday',
                    '2' => 'Tuesday',
                    '3' => 'Wednesday',
                    '4' => 'Thursday',
                    '5' => 'Friday',
                    '6' => 'Saturday',
                    '7' => 'Sunday',
                ],
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'yes_no' => [
                'display' => 'Yes/No',
                'options' => [
                    'yes' => 'Yes',
                    'no' => 'No',
                ],
                'config' => [
                    'cast_booleans' => false,
                    'max_items' => null,
                    'multiple' => false,
                    'taggable' => false,
                ],
            ],
            'true_false' => [
                'display' => 'True/False',
                'options' => [
                    'true' => 'True',
                    'false' => 'False',
                ],
                'config' => [
                    'cast_booleans' => true,
                    'max_items' => null,
                    'multiple' => false,
                    'taggable' => false,
                ],
            ],
            'mime_types' => [
                'display' => 'MIME Types',
                'options' => [], // Will be populated dynamically
                'has_config' => true,
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
            'mime_categories' => [
                'display' => 'MIME Type Categories',
                'options' => [], // Will be populated dynamically
                'has_config' => true,
                'config' => [
                    'cast_booleans' => false,
                    'taggable' => false,
                ],
            ],
        ];
    }

    /**
     * Get state options based on configuration settings.
     *
     * This method reads the state types from the fieldtype configuration
     * and calls getStateOptionsWithMultipleTypes() with the appropriate parameters.
     *
     * @return array<string, string> Array of state options with postal code as key and full name as value.
     */
    protected function getStateOptionsFromConfig(): array
    {
        // Get the state types from the fieldtype configuration with defaults
        $types = $this->config('state_types') ?? ['state'];

        // ---
        // Ensure types is always an array
        // ---

        // Check if types is not an array
        if (! \is_array($types)) {
            // Convert single type to array
            $types = [$types];
        }

        // Get the state options with multiple type filtering
        /** @var list<string> $types */
        return $this->getStateOptionsWithMultipleTypes($types);
    }

    /**
     * Get state options with multiple type filtering.
     *
     * This method filters states by multiple types, allowing users to
     * select multiple state types (e.g., both states and territories).
     *
     * @param  list<string>  $types  Array of state types to filter by
     * @return array<string, string> Array of state options with postal code as key and full name as value
     */
    protected function getStateOptionsWithMultipleTypes(
        array $types
    ): array {
        // Initialize the options array to store state data
        $options = [];

        // ---
        // Build the options array
        // ---

        // Loop through all State enum cases
        foreach (State::cases() as $state) {
            // ---
            // Check if the state's type matches any of the selected types
            // ---

            // Get the state's type
            $stateType = $state->type();
            $matchesType = array_any($types, fn (string $type): bool => $stateType === $type);

            // ---
            // Skip this state if it doesn't match any of the selected types
            // ---

            // Check if state doesn't match any selected type
            if (! $matchesType) {
                // Skip this state
                continue;
            }

            // Add matching states with postal code as key and full name as value
            $options[$state->value] = $state->fullName();
        }

        // Return the filtered state options array
        return $options;
    }

    /**
     * Get language options based on configuration settings.
     *
     * This method reads the language scope and standard from the fieldtype
     * configuration and calls getLanguageOptions() with the appropriate parameters.
     *
     * @return array<string, string> Array of language options with case value as key and name/description as value.
     */
    protected function getLanguageOptionsFromConfig(): array
    {
        // ---
        // Get the scope and standard from the fieldtype configuration with defaults
        // ---

        // Get language scope from config with default
        $scopes = $this->config('language_scope') ?? ['individual'];

        // Get language standard from config with default
        $standard = $this->config('language_standard') ?? 'iso639';

        // ---
        // Ensure scopes is always an array
        // ---

        // Check if scopes is not an array
        if (! \is_array($scopes)) {
            // Convert single scope to array
            $scopes = [$scopes];
        }

        // Determine the enum class based on the standard
        $enumClass = $standard === 'bcp47' ? 'LanguageBCP47' : 'Language';

        // Get the language options with multiple scope filtering
        /** @var list<string> $scopes */
        return $this->getLanguageOptions(
            $enumClass,
            $scopes
        );
    }

    /**
     * Map the user-friendly scope to the appropriate enum scope value.
     *
     * @param  ?string  $scope  The user-friendly scope value
     * @param  string  $enumClass  The enum class being used
     * @return ?string The mapped enum scope value
     */
    protected function mapLanguageScope(
        ?string $scope,
        string $enumClass
    ): ?string {
        // Check if scope is null or 'all'
        if (
            $scope === null
            || $scope === 'all'
        ) {
            // Return null for all scopes
            return null;
        }

        // ---
        // For Language enum (ISO 639)
        // ---

        // Check if enum class is Language
        if ($enumClass === 'Language') {
            // Map scope for ISO 639 Language enum
            return match ($scope) {
                'individual' => 'Individual',
                'collective' => 'Collective',
                'macrolanguages' => 'Macrolanguage',
                default => null,
            };
        }

        // ---
        // For LanguageBCP47 enum
        // ---

        // Check if enum class is LanguageBCP47
        if ($enumClass === 'LanguageBCP47') {
            // Map scope for BCP 47 Language enum
            return match ($scope) {
                'individual' => null, // Individual languages have null scope in BCP 47
                'collective' => 'collection',
                'macrolanguages' => 'macrolanguage',
                default => null,
            };
        }

        // Return null if no match found
        return null;
    }

    /**
     * Get language options from the specified enum with optional scope filtering.
     *
     * This method provides a unified way to get language options from either
     * the Language (ISO 639) or LanguageBCP47 enum with optional scope filtering.
     * The key is always set to the case value (the enum's string value).
     *
     * @param  string  $enumClass  The enum class to use: 'Language' or 'LanguageBCP47'
     * @param  list<string>  $scopes  Array of user-friendly scope values to filter by
     * @return array<string, string> Array of language options with case value as key and name/description as value
     */
    protected function getLanguageOptions(
        string $enumClass,
        array $scopes
    ): array {
        // Initialize the options array to store language data
        $options = [];

        // Build the full enum class name with namespace
        $fullEnumClass = '\App\Enums\\'.$enumClass;

        // ---
        // Build the options array
        // ---

        // Get all enum cases for the resolved language enum class
        /** @var list<Language|LanguageBCP47> $languageCases */
        $languageCases = $fullEnumClass::cases();

        // Loop through all language enum cases
        foreach ($languageCases as $language) {
            // ---
            // Check if the language's scope matches any of the selected scopes
            // ---

            // Get the language's scope value
            $languageScope = $language->scope();

            // Initialize flag to track if language matches any selected scope
            $matchesScope = false;

            // Loop through each selected scope
            foreach ($scopes as $scope) {
                // Map the user-friendly scope to the appropriate enum scope value
                $mappedScope = $this->mapLanguageScope(
                    $scope,
                    $enumClass
                );

                // ---
                // Normalize scope values for comparison (treat null and empty string as
                // equivalent)
                // ---

                // Normalize language scope (treat empty string as null)
                $normalizedLanguageScope = $languageScope === '' ? null : $languageScope;

                // Normalize mapped scope (treat empty string as null)
                $normalizedMappedScope = $mappedScope === '' ? null : $mappedScope;

                // Check if this language matches the current scope
                if ($normalizedLanguageScope === $normalizedMappedScope) {
                    // Language matches this scope
                    $matchesScope = true;

                    // Exit loop early since we found a match
                    break;
                }
            }

            // ---
            // Skip this language if it doesn't match any of the selected scopes
            // ---

            // Check if language doesn't match any selected scope
            if (! $matchesScope) {
                // Skip this language
                continue;
            }

            // Always use the case value as the key
            $key = $language->value;

            // Determine the label based on enum type
            $label = $language instanceof Language ? $language->name() : $language->description();

            // ---
            // Add the language to the options array with case value as key and
            // name/description as value
            // ---
            $options[$key] = $label;
        }

        // Return the complete language options array
        return $options;
    }

    /**
     * Get currency options from the Currency enum.
     *
     * This method loops through the Currency enum to generate the options array
     * for the Currencies preset.
     *
     * @return array<string, string> Array of currency options with ISO code as key and name as value
     */
    protected function getCurrencyOptions(): array
    {
        // Initialize the options array to store currency data
        $options = [];

        // ---
        // Build the options array
        // ---

        // Loop through all Currency enum cases
        foreach (Currency::cases() as $currency) {
            // Add currency option with ISO code as key and name as value
            $options[$currency->value] = $currency->name();
        }

        // Return the complete currency options array
        return $options;
    }

    /**
     * Get country options from the Country enum.
     *
     * This method loops through the Country enum to generate the options array
     * for the Countries preset.
     *
     * @return array<string, string> Array of country options with ISO code as key and name as value
     */
    protected function getCountryOptions(): array
    {
        // Initialize the options array to store country data
        $options = [];

        // ---
        // Build the options array
        // ---

        // Loop through all Country enum cases
        foreach (Country::cases() as $country) {
            // Add country option with ISO code as key and name as value
            $options[$country->value] = $country->country_name();
        }

        // Return the complete country options array
        return $options;
    }

    /**
     * Get MIME type options based on configuration settings.
     *
     * This method reads the MIME type categories from the fieldtype configuration
     * and calls getMimeTypeOptionsWithCategories() with the appropriate parameters.
     *
     * @return array<string, string> Array of MIME type options with MIME type as key and formatted display as value.
     */
    protected function getMimeTypeOptionsFromConfig(): array
    {
        // Get the MIME type categories from the fieldtype configuration with defaults
        $categories = $this->config('mime_type_categories') ?? [
            'application',
            'audio',
            'font',
            'image',
            'message',
            'model',
            'text',
            'video',
        ];

        // ---
        // Ensure categories is always an array
        // ---

        // Check if categories is not an array
        if (! \is_array($categories)) {
            // Convert single category to array
            $categories = [$categories];
        }

        // Get the MIME type options with category filtering
        /** @var list<string> $categories */
        return $this->getMimeTypeOptionsWithCategories($categories);
    }

    /**
     * Get MIME type options with category filtering.
     *
     * This method filters MIME types by categories, allowing users to
     * select specific MIME type categories (e.g., only images and videos).
     *
     * @param  list<string>  $categories  Array of MIME type categories to filter by
     * @return array<string, string> Array of MIME type options with MIME type as key and formatted display as value
     */
    protected function getMimeTypeOptionsWithCategories(
        array $categories
    ): array {
        // Initialize the options array to store MIME type data
        $options = [];

        // ---
        // Build the options array
        // ---

        // Loop through all MimeType enum cases
        foreach (MimeType::cases() as $mimeType) {
            // Get the MIME type string
            $mimeTypeString = $mimeType->mimeType();

            // Get the MIME type category
            $category = $mimeType->category();
            $matchesCategory = array_any($categories, fn (string $selectedCategory): bool => $category === $selectedCategory);

            // Check if MIME type doesn't match any selected category
            if (! $matchesCategory) {
                // Skip this MIME type
                continue;
            }

            // Get the extensions for this MIME type
            $extensions = $mimeType->extensions();

            // ---
            // Format the display value: "mimeType (ext1, ext2, ...)"
            // ---

            // Initialize display value with MIME type string
            $displayValue = $mimeTypeString;

            // Check if extensions array is not empty
            if ($extensions !== []) {
                // Append extensions list to display value
                $displayValue .= ' ('.\implode(
                    ', ',
                    $extensions
                ).')';
            }

            // Add matching MIME types with MIME type as key and formatted display as value
            $options[$mimeTypeString] = $displayValue;
        }

        // Return the filtered MIME type options array
        return $options;
    }

    /**
     * Get MIME category options from the MimeType enum.
     *
     * This method uses the MimeType::getCategories() method to generate the options
     * array for the MIME Categories preset.
     *
     * @return array<string, string> Array of MIME category options with category name as key and value
     */
    protected function getMimeCategoryOptions(): array
    {
        // Initialize the options array to store MIME category data
        $options = [];

        // Get all unique MIME type categories
        $categories = MimeType::getCategories();

        // ---
        // Build the options array
        // ---

        // Loop through all categories
        foreach ($categories as $category) {
            // ---
            // Use the category name as both key and value, with proper capitalization.
            // \ucwords() with '-' delimiter will capitalize first letter and letters after
            // hyphens.
            // ---
            $options[$category] = \ucwords(
                $category,
                '-'
            );
        }

        // Return the complete MIME category options array
        return $options;
    }

    /**
     * Get the options for this fieldtype, using preset if configured.
     *
     * This method should be called from the fieldtype's getOptions() method
     * to apply preset options before any other processing (like randomization).
     *
     * @return array<array-key, mixed> The options array (either from parent or preset)
     */
    protected function getOptionsWithPreset(): array
    {
        // Retrieve the options from the parent fieldtype.
        $options = parent::getOptions();

        // Use preset options if configured, otherwise return parent options.
        return $this->maybeUsePresetOptions($options);
    }

    /**
     * Use preset options if a preset is selected in the config.
     *
     * If a preset is selected in the config, this method returns the predefined
     * options for that preset. Otherwise, it returns the original options.
     *
     * @param  array<array-key, mixed>  $originalOptions  The original options from the parent fieldtype
     * @return array<array-key, mixed> The options array (either original or preset)
     */
    protected function maybeUsePresetOptions(
        array $originalOptions
    ): array {
        // Get the selected preset from config.
        $selectedPreset = $this->config('preset');

        // Check if no preset is selected or preset is 'none'
        if (
            empty($selectedPreset)
            || $selectedPreset === 'none'
        ) {
            // Return original options when no preset is selected
            return $originalOptions;
        }

        // Get the preset options for the selected preset.
        $presetOptions = $this->getPresetOptions(
            is_scalar($selectedPreset) ? (string) $selectedPreset : ''
        );

        // Check if preset options are found
        if (! empty($presetOptions)) {
            // Convert the preset options to the expected format and return them
            return $this->convertPresetOptionsToStatamicFormat($presetOptions);
        }

        // Otherwise return original options.
        return $originalOptions;
    }

    /**
     * Get the predefined options for a given preset.
     *
     * This method returns the predefined options array for the specified preset.
     * Override this method in your fieldtype to provide custom preset options.
     *
     * @param  string  $preset  The preset identifier
     * @return array<string, string> The predefined options array, or empty array if preset not found
     */
    protected function getPresetOptions(
        string $preset
    ): array {
        // Get preset definitions and extract options.
        $definitions = $this->getPresetDefinitions();

        // Get the preset definition
        $presetDefinition = $definitions[$preset] ?? null;

        // Check if preset definition was not found
        if (! $presetDefinition) {
            // Return empty array when preset not found
            return [];
        }

        // Check if this preset has dynamic options that need to be generated
        if (
            isset($presetDefinition['has_config'])
            && $presetDefinition['has_config']
        ) {
            // Return dynamically generated options for this preset
            return $this->getDynamicPresetOptions($preset);
        }

        // Return the static options for the specified preset
        /** @var array<string, string> $options */
        $options = $presetDefinition['options'] ?? [];

        return $options;
    }

    /**
     * Get dynamic options for presets that require configuration.
     *
     * This method handles presets that need to generate their options
     * based on configuration values rather than static definitions.
     *
     * @param  string  $preset  The preset identifier
     * @return array<string, string> The dynamically generated options array
     */
    protected function getDynamicPresetOptions(
        string $preset
    ): array {
        return match ($preset) {
            'languages' => $this->getLanguageOptionsFromConfig(),
            'us_states' => $this->getStateOptionsFromConfig(),
            'mime_types' => $this->getMimeTypeOptionsFromConfig(),
            'mime_categories' => $this->getMimeCategoryOptions(),
            default => [],
        };
    }

    /**
     * Convert preset options from key-value format to Statamic's expected format.
     *
     * Statamic expects options to be in the format:
     * [['value' => 'key', 'label' => 'value'], ...]
     *
     * But preset options are in the format:
     * ['key' => 'value', ...]
     *
     * @param  array<string, string>  $presetOptions  The preset options in key-value format
     * @return list<array{value: string, label: string}> The options in Statamic's expected format
     */
    protected function convertPresetOptionsToStatamicFormat(
        array $presetOptions
    ): array {
        // Initialize array to store converted options
        $convertedOptions = [];

        // Loop through each preset option
        foreach ($presetOptions as $key => $value) {
            // Add option in Statamic format with value and label
            $convertedOptions[] = [
                'value' => $key,
                'label' => $value,
            ];
        }

        // Return converted options array
        return $convertedOptions;
    }

    /**
     * Returns the config array for the 'preset' field.
     *
     * This method provides a reusable config field definition for
     * the preset selection dropdown, allowing fieldtypes to DRYly include
     * this config option in their configFieldItems().
     *
     * @return array<string, mixed> The config array for the preset selection field
     */
    public function presetConfigField(): array
    {
        // Get preset definitions to build the options list
        $definitions = $this->getPresetDefinitions();

        // Initialize options array with the default "no preset" option
        $options = ['none' => __('No Preset (Use Manual Options)')];

        // Add each preset to the options list
        foreach ($definitions as $key => $definition) {
            // Add preset option to dropdown list
            $display = $definition['display'] ?? null;
            $options[$key] = __(is_string($display) ? $display : null);
        }

        // Get preset-specific configuration fields
        $presetConfigFields = $this->getPresetConfigFields();

        // Start with the basic preset field
        $fields = [
            'preset' => [
                'display' => __('Preset Options'),
                'instructions' => __('Select a predefined list of options to use instead of manually entering options.'),
                'type' => 'select',
                'options' => $options,
                'default' => 'none',
                'validate' => ['required'],
            ],
        ];

        // ---
        // Merge in preset-specific configuration fields
        // ---

        // Loop through each preset's configuration fields
        foreach ($presetConfigFields as $presetFields) {
            // ---
            // Add each preset's configuration fields to the main fields array
            // ---

            // Loop through each field in the preset's configuration
            foreach ($presetFields as $fieldKey => $fieldConfig) {
                // Add field to main fields array
                $fields[$fieldKey] = $fieldConfig;
            }
        }

        // Clean up variables to free up memory
        unset(
            $definitions,
            $options,
            $presetConfigFields,
            $fieldKey,
            $fieldConfig
        );

        // Return the complete preset configuration field definition
        return $fields;
    }

    /**
     * Get additional config fields for presets that require configuration.
     *
     * This method returns config field definitions for presets that need
     * additional configuration options. The fields are shown only when
     * the specific preset is selected.
     *
     * @return array<string, array<string, mixed>> Array of config field definitions
     */
    protected function getPresetConfigFields(): array
    {
        return ['languages' => [
            'language_scope' => [
                'display' => __('Language Scope'),
                'instructions' => __('Choose which types of languages to include.'),
                'type' => 'checkboxes',
                'options' => [
                    'individual' => __('Individual Languages'),
                    'collective' => __('Collective Languages'),
                    'macrolanguages' => __('Macrolanguages'),
                ],
                'default' => ['individual'],
                'validate' => ['required'],
                'if' => [
                    'preset' => 'languages',
                ],
            ],
            'language_standard' => [
                'display' => __('Language Standard'),
                'instructions' => __('Choose the language standard to use.'),
                'type' => 'select',
                'options' => [
                    'iso639' => __('ISO 639'),
                    'bcp47' => __('BCP 47'),
                ],
                'default' => 'iso639',
                'validate' => ['required'],
                'if' => [
                    'preset' => 'languages',
                ],
            ],
        ], 'us_states' => [
            'state_types' => [
                'display' => __('State Types'),
                'instructions' => __('Choose which types of U.S. states to include.'),
                'type' => 'checkboxes',
                'options' => [
                    'state' => __('States'),
                    'territory' => __('Territories'),
                    'district' => __('Districts'),
                ],
                'default' => ['state'],
                'validate' => ['required'],
                'if' => [
                    'preset' => 'us_states',
                ],
            ],
        ], 'mime_types' => [
            'mime_type_categories' => [
                'display' => __('MIME Type Categories'),
                'instructions' => __('Choose which MIME type categories to include.'),
                'type' => 'checkboxes',
                'options' => [
                    'application' => __('Application'),
                    'audio' => __('Audio'),
                    'font' => __('Font'),
                    'image' => __('Image'),
                    'message' => __('Message'),
                    'model' => __('Model'),
                    'text' => __('Text'),
                    'video' => __('Video'),
                ],
                'default' => ['application', 'audio', 'font', 'image', 'message', 'model', 'text', 'video'],
                'validate' => ['required'],
                'if' => [
                    'preset' => 'mime_types',
                ],
            ],
        ]];
    }

    /**
     * Get parent configuration with conditional visibility applied to config fields.
     *
     * This method retrieves the parent configuration and applies conditional
     * visibility to hide config fields when a preset is selected. This
     * eliminates the need to repeat this logic in each fieldtype.
     *
     * @return array<array-key, mixed> The parent configuration with conditional visibility applied
     */
    protected function getParentConfigWithConditionalVisibility(): array
    {
        // Get parent configuration
        $parentConfig = parent::configFieldItems();

        // ---
        // Get all preset definitions to analyze which config fields are used by each
        // preset
        // ---
        $definitions = $this->getPresetDefinitions();

        // Initialize the config field map to track which presets use each config field
        $configFieldMap = [];

        // ---
        // Build a map of config keys to the presets that use them
        // ---

        // Loop through each preset definition
        foreach ($definitions as $presetKey => $definition) {
            // Check if this preset has configuration fields defined
            if (
                isset($definition['config'])
                && \is_array($definition['config'])
            ) {
                // Iterate through each config field used by this preset
                foreach (array_keys($definition['config']) as $configKey) {
                    // ---
                    // Initialize the array for this config key if it doesn't exist
                    // ---

                    // Check if config key doesn't exist in map yet
                    if (! isset($configFieldMap[$configKey])) {
                        // Create an empty array to store preset keys that use this config field
                        $configFieldMap[$configKey] = [];
                    }

                    // Add this preset to the list of presets that use this config field
                    $configFieldMap[$configKey][] = $presetKey;
                }
            }
        }

        // ---
        // Apply conditional visibility to existing fields
        // ---

        foreach ($parentConfig as &$section) {
            // Check if this section contains field definitions
            if (\is_array($section) && isset($section['fields']) && \is_array($section['fields'])) {
                // Iterate through each field in the section
                foreach ($section['fields'] as $fieldKey => &$field) {
                    // Skip non-array field definitions
                    if (! \is_array($field)) {
                        continue;
                    }

                    // ---
                    // Special handling for the push_tags field - always hide
                    // ---

                    // Check if field key is 'push_tags'
                    if ($fieldKey === 'push_tags') {
                        // Apply unconditional visibility to hide push_tags field
                        $field['if'] = [
                            '__always_false__' => true,
                        ];
                    }

                    // ---
                    // Special handling for the options field - hide when any preset is selected
                    // ---

                    // Check if field key is 'options'
                    if ($fieldKey === 'options') {
                        // Apply conditional visibility to hide options field when preset is selected
                        $field['if'] = [
                            'preset' => 'custom isEmptyOrValue:none',
                        ];
                    }

                    // ---
                    // Apply conditional visibility to config fields based on preset usage
                    // ---

                    // Check if config field map contains this field key
                    if (isset($configFieldMap[$fieldKey])) {
                        // Hide config field unless the preset is one that uses it
                        $field['unless'] = [
                            'preset' => 'contains_any '.\implode(
                                ',',
                                $configFieldMap[$fieldKey]
                            ),
                        ];
                    }
                }
            }
        }

        // Clean up variables to free up memory
        unset(
            $definitions,
            $configFieldMap,
            $fieldKey,
            $field,
            $presetKey,
            $definition,
            $configKey,
            $section
        );

        // Return the modified configuration with conditional visibility applied
        return $parentConfig;
    }
}
