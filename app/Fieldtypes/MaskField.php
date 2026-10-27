<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Text fieldtype that applies an input mask and an optional case transform.
 *
 * Subclasses may declare a `presetInputMask` and/or `presetTransformCase`
 * property to lock the mask/transform to a fixed preset (e.g. a phone or ZIP
 * fieldtype); the base detects them reflectively and otherwise leaves the
 * mask and transform editable in the field config.
 *
 * @property ?string $presetInputMask
 * @property ?string $presetTransformCase
 */
class MaskField extends Fieldtype
{
    /**
     * Whether this fieldtype can be chosen inside form-builder UIs.
     *
     * Pinned to false explicitly (rather than inherited from the parent) so that
     * the no-public-form-rendering decision is visible at the class level. The
     * Vue CP component drives the input via IMask; no equivalent runtime exists
     * on the public side, so dropping this on a form blueprint would render a
     * plain text input with none of the masking, normalization, or value-storage
     * coordination from InputMaskValueStorage. The work to bring imask to public
     * forms was scoped in the (closed) follow-up issue spun off from UAMS-Web/uams-statamic-archive#195;
     * re-enable this flag in the same change that lands that work.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints.
     */
    protected $selectableInForms = false;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'mask_field';

    /**
     * Preset-derived config values applied by applyPresetConfig().
     *
     * Declared explicitly (rather than written as a dynamic property, deprecated
     * in PHP 8.4) so the preset merge in applyPresetConfig() is type-safe.
     *
     * @var array<string, mixed> Preset config values for this fieldtype
     */
    protected array $config = [];

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'form-text-field';
    }

    /**
     * Fieldtype Categories
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector
     */
    public $categories = [
        'number',
        'text',
        'special',
    ];

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'all caps',
        'all-caps',
        'capitalize',
        'case',
        'character',
        'characters',
        'format',
        'formatted',
        'formatting',
        'imask',
        'lowercase',
        'mask',
        'masking',
        'pattern',
        'patterns',
        'regex',
        'regexp',
        'regular expression',
        'regular expressions',
        'transform',
        'uppercase',
    ];

    /**
     * The blank/default value.
     *
     * @return array<string, mixed> The blank default value
     */
    #[\Override]
    public function defaultValue(): array
    {
        return [];
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): mixed {
        // Apply preset configuration if defined by child fieldtypes
        $this->applyPresetConfig();

        // Return the processed data
        return $data;
    }

    /**
     * Process the data before it gets saved.
     */
    #[\Override]
    public function process(
        mixed $data
    ): mixed {
        // Apply preset configuration if defined by child fieldtypes
        $this->applyPresetConfig();

        // Return the processed data
        return $data;
    }

    /**
     * Apply preset configuration values if defined by child fieldtypes.
     *
     * This method checks for preset configuration properties defined by child
     * fieldtypes and applies them automatically. This eliminates the need for
     * child fieldtypes to override preProcess and process methods.
     */
    protected function applyPresetConfig(): void
    {
        // Check if child fieldtype has defined preset properties
        if (property_exists($this, 'presetInputMask') && $this->presetInputMask !== null || property_exists($this, 'presetTransformCase') && $this->presetTransformCase !== null) {
            // Get preset input mask from child fieldtype or use empty string
            $inputMask = $this->presetInputMask ?? '';

            // Get preset transform case from child fieldtype or use 'none'
            $transformCase = $this->presetTransformCase ?? 'none';

            // Set the configuration values using the config() method
            $this->config = \array_merge(
                $this->config,
                [
                    'input_mask' => $inputMask,
                    'transform_case' => $transformCase,
                    '_preset_fields' => [
                        'input_mask' => $inputMask,
                        'transform_case' => $transformCase,
                    ],
                ]
            );
        }
    }

    /**
     * Hide specific configuration fields from the blueprint editor.
     *
     * This method allows child fieldtypes to hide complex configuration
     * options while preserving the functionality.
     *
     * @param  list<string>  $fieldsToHide  Array of field names to hide
     * @return list<array<string, mixed>> The modified configuration with hidden fields
     */
    protected function hideConfigFields(
        array $fieldsToHide
    ): array {
        // Get the full configuration from parent
        $config = $this->configFieldItems();

        // Iterate through each section in the configuration
        foreach ($config as &$section) {
            // Check if section has fields to process
            if (is_array($section['fields'] ?? null)) {
                // Remove each specified field from the section
                foreach ($fieldsToHide as $fieldName) {
                    // Clean up variables to free up memory
                    unset($section['fields'][$fieldName]);
                }
            }
        }

        // Return the modified configuration with hidden fields
        return $config;
    }

    /**
     * Create a simplified configuration with only specified fields.
     *
     * This method allows child fieldtypes to create a clean configuration
     * with only the fields they want to expose.
     *
     * @param  list<string>  $fieldsToShow  Array of field names to include
     * @return list<array<string, mixed>> The simplified configuration
     */
    protected function createSimplifiedConfig(
        array $fieldsToShow
    ): array {
        // Get the full configuration from parent
        $fullConfig = $this->configFieldItems();

        // Initialize simplified configuration array
        $simplifiedConfig = [];

        // Process each section in the full configuration
        foreach ($fullConfig as $section) {
            // Create a new section with only the display name
            $simplifiedSection = [
                'display' => $section['display'],
                'fields' => [],
            ];

            // Narrow the section's fields to an array before per-field offset reads
            /** @var array<string, mixed> $sectionFields */
            $sectionFields = is_array($section['fields'] ?? null) ? $section['fields'] : [];

            // Add only the specified fields to the simplified section
            foreach ($fieldsToShow as $fieldName) {
                // Check if the field exists in the original section
                if (isset($sectionFields[$fieldName])) {
                    $simplifiedSection['fields'][$fieldName] = $sectionFields[$fieldName];
                }
            }

            // Only add sections that have fields
            if ($simplifiedSection['fields'] !== []) {
                $simplifiedConfig[] = $simplifiedSection;
            }
        }

        // Return the simplified configuration
        return $simplifiedConfig;
    }

    /**
     * Hide preset fields from the configuration.
     *
     * This method automatically hides fields that have been set to preset
     * values using setPresetConfig(). It sets the fields to hidden visibility
     * instead of removing them completely, so the configuration values are preserved.
     *
     * @return list<array<string, mixed>> The modified configuration with preset fields hidden
     */
    protected function hidePresetFields(): array
    {
        // Get the full configuration from parent
        $config = $this->configFieldItems();

        // Get preset fields that should be hidden
        /** @var array<string, mixed> $presetFields */
        $presetFields = (array) $this->config(
            '_preset_fields',
            []
        );

        // Process each section in the configuration
        foreach ($config as &$section) {
            // Check if section has fields to process
            if (is_array($section['fields'] ?? null)) {
                // Hide each preset field from the section
                foreach ($presetFields as $fieldName => $presetValue) {
                    // Set the field to hidden visibility instead of removing it
                    if (isset($section['fields'][$fieldName]) && is_array($section['fields'][$fieldName])) {
                        $section['fields'][$fieldName]['visibility'] = 'hidden';
                    }
                }
            }
        }

        // Return the modified configuration with preset fields hidden
        return $config;
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * @return list<array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        $config = [
            [
                'display' => __('Input Behavior'),
                'fields' => [
                    'placeholder' => [
                        'display' => __('Placeholder'),
                        'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
                        'type' => 'text',
                    ],
                    /**
                     * Configuration array for the 'input_mask' field.
                     *
                     * Defines how the field appears and behaves in the Control Panel's blueprint
                     * editor.
                     */
                    'input_mask' => [
                        'display' => 'Input Mask',
                        'type' => 'text',
                        'placeholder' => 'e.g., 000-000-0000',

                        /**
                         * Multi-paragraph instructions providing guidance on defining an input mask.
                         * Uses heredoc syntax for readability, rendered as separate <p> tags in the UI.
                         * Outlines IMask pattern syntax, regex option, and links to full documentation.
                         */
                        'instructions' => <<<'EOT'
                            Define an input mask (e.g., `000-000-0000` or `/^\d{3}-\d{3}-\d{4}$/`). If set, the mask will be applied using IMask.

                            Use: `0` for any digit, `a` for any letter, `#` for any letter or digit (a-z, A-Z, 0-9), `*` for any character. Other characters (e.g., `(`, `-`) are fixed.

                            Make part of the input optional by surrounding it with `[]` (e.g., `[000]`).

                            Include a fixed part in the value by surrounding it with `{}` (e.g., `{+1}000`).

                            Add a backtick (`` ` ``) to stop characters or the cursor from shifting backward past a certain position toward earlier parts of the mask.

                            Escape fixed definition characters with `\` (e.g., `\0`)if they match `0`, `a`, or `*`.

                            To use regular expressions, wrap in `/` (e.g., `/^(\d{0,3}|\d{3}-\d{0,3})$/`) to enforce exact matches without placeholders. The regex must allow any value that can **lead** to a valid input when complete (e.g., `1`, `12`, `123-`, `123-4`, `123-45`, `123-456`) to enable progressive typing. Regex masks don't show placeholders, so users see raw input (e.g., `123-456`) without visual guidance, and only exact matches are allowed. Users will need to explicitly enter all characters to satisfy the regex mask.

                            See IMask documentation at https://imask.js.org/guide.html.
                            EOT
                    ],
                    /**
                     * Configuration array for the 'transform_case' field.
                     *
                     * Allows users to select whether to transform input case to uppercase, lowercase,
                     * or none.
                     */
                    'transform_case' => [
                        'display' => 'Transform Case',
                        'type' => 'radio',
                        'options' => [
                            'none' => 'None',
                            'uppercase' => 'Uppercase',
                            'lowercase' => 'Lowercase',
                        ],
                        'default' => 'none',
                        'clearable' => true,
                        'instructions' => 'Choose whether to transform input characters to uppercase, lowercase, or leave them unchanged. If not set, no transformation occurs.',
                    ],
                ],
            ],
        ];

        // Prepare an array to hold preset fields and their values
        $presetFields = [];

        // If the child fieldtype defines a preset input mask, add it to the preset fields
        if (property_exists($this, 'presetInputMask') && $this->presetInputMask !== null) {
            $presetFields['input_mask'] = $this->presetInputMask;
        }

        // If the child fieldtype defines a preset transform case, add it to the preset fields
        if (property_exists($this, 'presetTransformCase') && $this->presetTransformCase !== null) {
            $presetFields['transform_case'] = $this->presetTransformCase;
        }

        // Apply preset values to the configuration fields if any are defined
        if ($presetFields !== []) {
            // Iterate through each section in the configuration
            foreach ($config as &$section) {
                // For each preset field, set its default value in the config
                foreach ($presetFields as $fieldName => $presetValue) {
                    // Set the default value for the field to the preset value
                    $section['fields'][$fieldName]['default'] = $presetValue;
                }
            }
        }

        // Suppress hiding logic for testing preset values
        // if (!empty($presetFields)) {
        //     foreach ($config as &$section) {
        //         if (isset($section['fields'])) {
        //             foreach ($presetFields as $fieldName => $presetValue) {
        //                 if (isset($section['fields'][$fieldName])) {
        //                     $section['fields'][$fieldName]['visibility'] = 'hidden';
        //                 }
        //             }
        //         }
        //     }
        // }

        // Return the configuration with preset fields hidden
        return $config;
    }
}
