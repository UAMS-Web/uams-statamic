<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Defines the Control Panel config-field schema for input-based fieldtypes.
 *
 *   - Assembles the full config-field set from focused builders (HTML attributes,
 *     Statamic-specific fields, input-mask fields), then filters and groups them
 *     into ordered Control Panel sections.
 *   - Exposes the child extension hooks (`getConfigFieldOverrides()`,
 *     `getUnsetConfigFields()`, `getHiddenConfigFields()`, `getAdditionalConfigFields()`)
 *     and preset integration used to tailor the schema per fieldtype.
 *
 * Extracted from the former monolithic {@see Input} trait (issue UAMS-Web/uams-statamic-archive#873).
 */
trait DefinesInputConfigFields
{
    /**
     * Allows child fieldtypes to override any config field attribute (e.g.,
     * 'instructions', 'type', etc.) for fields defined in configFieldItems().
     *
     * Example:
     *   ```
     *   protected function getConfigFieldOverrides(): array {
     *     return [
     *       'min' => [
     *         'instructions' => 'Set the minimum allowed week. Format: YYYY-Www (e.g., 2020-W01 for week 1 of 2020).',
     *         'type' => 'week',
     *       ],
     *       'max' => [
     *         'instructions' => 'Set the maximum allowed week. Format: YYYY-Www (e.g., 2030-W52 for week 52 of 2030).',
     *         'type' => 'week',
     *       ],
     *     ];
     *   }
     *   ```
     *
     * @return array<string, mixed> Config field overrides
     */
    protected function getConfigFieldOverrides(): array
    {
        return [];
    }

    /**
     * Get the default HTML `type` attribute for this input fieldtype.
     *
     * Used as the fallback for the `input_type` config field when a fieldtype
     * does not supply an `input_type.default` override via
     * getConfigFieldOverrides(). Override this in child classes to change the
     * default input type (e.g. `email`, `number`, `tel`).
     *
     * @return string The default HTML input type
     */
    protected function getInputType(): string
    {
        return 'text';
    }

    /**
     * Get config fields that should be unset (removed) for this fieldtype.
     *
     * Override this in child classes to remove specific config fields.
     *
     * @return array<string> Config fields that should be hidden (visibility: hidden) for this fieldtype
     */
    protected function getUnsetConfigFields(): array
    {
        return [];
    }

    /**
     * Get config fields that should be hidden (visibility: hidden) for this
     * fieldtype.
     *
     * Override this in child classes to hide specific config fields.
     *
     * @return array<string> Config fields that should be hidden (visibility: hidden) for this fieldtype
     */
    protected function getHiddenConfigFields(): array
    {
        return [];
    }

    /**
     * Get additional config fields to be added after the preset field.
     * Override this in child classes to add field-specific config options.
     *
     * Each field can include conditional display logic using the 'if' key
     * to show/hide based on preset values or other config settings.
     *
     * Example usage:
     * ```php
     * protected function getAdditionalConfigFields(): array
     * {
     *     return [
     *         'country' => [
     *             'display' => 'Country',
     *             'instructions' => 'Select the country for validation.',
     *             'type' => 'select',
     *             'options' => [
     *                 'US' => 'United States',
     *                 'CA' => 'Canada',
     *             ],
     *             'default' => 'US',
     *             'if' => [
     *                 'preset' => 'tel-national', // Show only when preset is 'tel-national'
     *             ],
     *         ],
     *         'custom_field' => [
     *             'display' => 'Custom Field',
     *             'type' => 'text',
     *             'if' => [
     *                 'preset' => ['option1', 'option2'], // Show for multiple preset values
     *             ],
     *         ],
     *     ];
     * }
     * ```
     *
     * Conditional display supports:
     *   - Single value: 'field' => 'value'
     *   - Multiple values: 'field' => ['value1', 'value2']
     *   - Multiple conditions: 'field1' => 'value1', 'field2' => 'value2'
     *
     * @return array<string, mixed> Additional config fields
     */
    protected function getAdditionalConfigFields(): array
    {
        return [];
    }

    /**
     * Inject additional config fields after the preset selector (if present) in the
     * first section.
     *
     * @param  array<int, array<string, mixed>>  $fields  The config fields array (by section)
     * @return array<int, array<string, mixed>> The modified config fields array
     */
    protected function injectAdditionalConfigFields(
        array $fields
    ): array {
        // ---
        // Ensure the first section and its fields exist and are an array
        // ---

        // Check if the first section or its fields are missing or invalid
        if (
            ! isset($fields[0]['fields'])
            || ! \is_array($fields[0]['fields'])
        ) {
            // Return fields unchanged if structure is invalid
            return $fields;
        }

        // Copy the fields from the first section for manipulation
        $sectionFields = $fields[0]['fields'];

        // Prepare an array to hold injected fields (preset and additional config fields)
        $injected = [];

        // ---
        // If a preset selector exists, keep it as the first field
        // ---

        // Check if a preset selector exists in the section fields
        if (
            \array_key_exists(
                'preset',
                $sectionFields
            )
        ) {
            // Add the preset selector to the injected fields
            $injected['preset'] = $sectionFields['preset'];

            // Remove the preset selector from the section fields to avoid duplication
            unset(
                $sectionFields['preset']
            );
        }

        // ---
        // Inject additional config fields after the preset selector (if present)
        // ---

        // Loop through additional config fields from child class
        foreach ($this->getAdditionalConfigFields() as $fieldName => $fieldConfig) {
            // Add each additional config field to the injected fields array
            $injected[$fieldName] = $fieldConfig;
        }

        // Merge injected fields (preset and additional) with the remaining section fields
        $fields[0]['fields'] = \array_merge(
            $injected,
            $sectionFields
        );

        // Return the modified fields array with additional config fields injected
        return $fields;
    }

    /**
     * Apply preset-driven config logic to config fields.
     *
     *   - Adds the preset selector section if getPresets() is defined.
     *   - Determines the current preset from config or default.
     *   - Injects/hides config fields based on the selected preset's config.
     *
     * @param  array<int, array<string, mixed>>  $fields  The config fields array (by section)
     * @return array<int, array<string, mixed>> The modified config fields array
     */
    protected function applyPresetToConfigFields(
        array $fields
    ): array {
        // Check if the fieldtype defines presets
        if (
            method_exists(
                $this,
                'getPresets'
            )
        ) {
            // Retrieve the preset definitions
            /** @var array<string, mixed> $presets */
            $presets = $this->getPresets();

            // Extract the preset options array
            /** @var array<array-key, array<string, mixed>> $options */
            $options = \is_array($presets['options'] ?? null) ? $presets['options'] : [];

            // ---
            // Only proceed if there are preset options defined
            // ---

            // Check if preset options are defined
            if (! empty($options)) {
                // Build the options array for the selector UI
                $selectorOptions = [];

                // ---
                // Populate the selector options with labels
                // ---

                // Loop through preset options to build selector options
                foreach ($options as $key => $preset) {
                    // Set selector option label from preset or generate from key
                    $selectorOptions[$key] = $preset['label'] ?? \ucfirst((string) $key);
                }

                // Determine the default preset key
                $defaultPreset = $presets['default'] ?? '';

                // ---
                // Add the preset selector as the first field in the Input Behavior section
                // ---

                // Check if the first section fields array exists and is valid
                if (
                    isset($fields[0]['fields'])
                    && \is_array($fields[0]['fields'])
                ) {
                    // Start with the preset field
                    $newFields = ['preset' => [
                        'display' => $presets['display'] ?? 'Preset',
                        'instructions' => $presets['instructions'] ?? 'Choose a preset for this field.',
                        'type' => 'select',
                        'options' => $selectorOptions,
                        'default' => $defaultPreset,
                        'required' => true,
                        'internal' => true,
                    ]];

                    // Merge with existing fields (additional config fields are now handled elsewhere)
                    $fields[0]['fields'] = \array_merge(
                        $newFields,
                        $fields[0]['fields']
                    );
                }

                // Get the cached config
                $config = $this->getCachedConfig();

                // Determine the currently selected preset (from cached config or default)
                $currentPreset = $config['preset'] ?? $defaultPreset;

                // Coerce the preset key to a valid array-key type for option lookup
                $currentPreset = \is_scalar($currentPreset) ? (string) $currentPreset : '';

                // ---
                // If the selected preset exists, apply its config overrides
                // ---

                // Check if the current preset exists in options
                if (isset($options[$currentPreset])) {
                    // Get the config overrides for the selected preset
                    /** @var array<string, mixed> $configOverrides */
                    $configOverrides = \is_array($options[$currentPreset]['config'] ?? null)
                        ? $options[$currentPreset]['config']
                        : [];

                    // ---
                    // For each override, set the default and hide the field
                    // ---

                    // Loop through config overrides for the selected preset
                    foreach ($configOverrides as $key => $value) {
                        // Loop through field sections to find matching fields
                        foreach ($fields as &$section) {
                            // Check if the field exists in this section
                            if (
                                \is_array($section['fields'] ?? null)
                                && isset($section['fields'][$key])
                                && \is_array($section['fields'][$key])
                            ) {
                                // Set the default value for the field
                                $section['fields'][$key]['default'] = $value;

                                // Hide the field in the UI
                                $section['fields'][$key]['visibility'] = 'hidden';
                            }
                        }

                        // Unset reference to avoid accidental reuse in the next iteration
                        unset(
                            $section
                        );
                    }
                }
            }
        }

        // Return the modified config fields array with preset logic applied
        return $fields;
    }

    /**
     * Provide default config for all input types.
     *
     * Note: The following attributes are excluded from config as they are either:
     *   - Handled automatically by Statamic (name, class, style, tabindex, title)
     *   - Not commonly needed in CMS contexts (autofocus, autoselect, disabled,
     *     readonly, size)
     *   - Too specific for general use (form, list, multiple)
     *   - Accessibility concerns (accesskey - may conflict with browser shortcuts)
     *
     * Note: Only attributes valid for email, month, password, tel, url, and week
     * input types are included.
     *
     * @return array<int, array<string, mixed>>
     */
    public function configFieldItems(): array
    {
        // Get config field overrides from child class
        /** @var array<string, array<string, mixed>> $overrides */
        $overrides = $this->getConfigFieldOverrides();

        // Build the raw field definitions from each responsibility group
        $fields = \array_merge(
            $this->htmlAttributeConfigFields($overrides),
            $this->statamicConfigFields(),
            $this->inputMaskConfigFields($overrides)
        );

        // Apply per-field overrides and child-driven unset/hide filtering
        $fields = $this->applyConfigFieldFilters(
            $fields,
            $overrides
        );

        // Group the fields into ordered Control Panel sections
        $result = $this->groupConfigFieldsIntoSections($fields);

        // All preset logic is handled in applyPresetToConfigFields
        $result = $this->applyPresetToConfigFields($result);

        // Always inject additional config fields after preset (if present)
        return $this->injectAdditionalConfigFields($result);
    }

    /**
     * Build the HTML attribute config fields (global and input-specific).
     *
     * Produces one config field per supported HTML attribute. Each entry carries
     * a `section` key consumed downstream by {@see groupConfigFieldsIntoSections()}.
     *
     * @param  array<string, array<string, mixed>>  $overrides  Per-field attribute overrides
     * @return array<string, array<string, mixed>>
     */
    protected function htmlAttributeConfigFields(array $overrides): array
    {
        return ['accept' => [
            'display' => $overrides['accept']['display'] ?? 'Accepted File Types (accept attribute)',
            'instructions' => $overrides['accept']['instructions'] ?? 'Specify allowed file types for file uploads. Use this attribute to restrict file selection to specific MIME types or file extensions.',
            'type' => $overrides['accept']['type'] ?? 'text',
            'default' => $overrides['accept']['default'] ?? null,
            'validate' => $overrides['accept']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any file',
            ],
            'section' => 'constraints',
        ], 'accesskey' => [
            'display' => $overrides['accesskey']['display'] ?? 'Keyboard Shortcut Key (accesskey)',
            'instructions' => $overrides['accesskey']['instructions'] ?? <<<'EOT'
                    The `accesskey` attribute specifies a shortcut key to activate/focus an element. The `accesskey` attribute value must be a single character (a letter or a digit).

                    **Warning:** Using `accesskey`s is difficult because they may conflict with other key standards in the browser. To avoid this problem, most browsers will use `accesskey`s only if pressed together with the Alt key.

                    **Concerns:** Adapting `accesskey`s to all international languages are difficult. The `accesskey` value may not be present on all keyboards. Because of these concerns, it is advised not to use `accesskey`s.
                    EOT,
            'type' => $overrides['accesskey']['type'] ?? 'text',
            'default' => $overrides['accesskey']['default'] ?? null,
            'character_limit' => $overrides['accesskey']['character_limit'] ?? 1,
            'validate' => $overrides['accesskey']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'alt' => [
            'display' => $overrides['alt']['display'] ?? 'Alternative Text (alt attribute)',
            'instructions' => $overrides['alt']['instructions'] ?? 'Provide alternative text for image inputs. Use this attribute to describe the image for accessibility and when the image cannot be displayed.',
            'type' => $overrides['alt']['type'] ?? 'text',
            'default' => $overrides['alt']['default'] ?? null,
            'validate' => $overrides['alt']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image',
            ],
            'section' => 'accessibility',
        ], 'anchor' => [
            'display' => $overrides['anchor']['display'] ?? 'Anchor Element ID (anchor attribute)',
            'instructions' => $overrides['anchor']['instructions'] ?? 'Set the ID of the element to anchor this positioned element to.',
            'type' => $overrides['anchor']['type'] ?? 'text',
            'default' => $overrides['anchor']['default'] ?? null,
            'validate' => $overrides['anchor']['validate'] ?? null,
            'section' => 'experimental',
        ], 'autocapitalize' => [
            'display' => $overrides['autocapitalize']['display'] ?? 'Enable Autocapitalize',
            'instructions' => $overrides['autocapitalize']['instructions'] ?? 'Control whether and how text input is automatically capitalized.',
            'type' => $overrides['autocapitalize']['type'] ?? 'select',
            'default' => $overrides['autocapitalize']['default'] ?? 'null',
            'options' => $overrides['autocapitalize']['options'] ?? [
                'on' => 'On',
                'off' => 'Off',
                'null' => 'Not Specified',
            ],
            'if' => [
                'input_type' => 'contains_any button,checkbox,color,date,datetime-local,file,hidden,image,month,number,radio,range,reset,search,submit,tel,text,time,week',
            ],
            'validate' => $overrides['autocapitalize']['validate'] ?? null,
            'internal' => true, // Only internal until we have support for this prop
            'section' => 'accessibility',
        ], 'autocapitalize_setting' => [
            'display' => $overrides['autocapitalize_setting']['display'] ?? 'Autocapitalize Setting',
            'instructions' => $overrides['autocapitalize_setting']['instructions'] ?? 'Choose how text should be automatically capitalized when autocapitalize is enabled.',
            'type' => $overrides['autocapitalize_setting']['type'] ?? 'select',
            'default' => $overrides['autocapitalize_setting']['default'] ?? null,
            'options' => $overrides['autocapitalize_setting']['options'] ?? [
                'sentences' => 'Sentences',
                'words' => 'Words',
                'characters' => 'Characters',
            ],
            'if' => [
                'autocapitalize' => 'on',
            ],
            'validate' => $overrides['autocapitalize_setting']['validate'] ?? null,
            'section' => 'behavior',
        ], 'autocomplete_query' => [
            'display' => $overrides['autocomplete_query']['display'] ?? 'Enable Autocomplete',
            'instructions' => $overrides['autocomplete_query']['instructions'] ?? 'Control whether the browser should automatically fill in this field based on previously entered values.',
            'type' => $overrides['autocomplete_query']['type'] ?? 'select',
            'default' => $overrides['autocomplete_query']['default'] ?? 'null',
            'options' => [
                'on' => 'On',
                'off' => 'Off',
                'null' => 'Not Specified',
            ],
            'if' => [
                'input_type' => 'contains_any color,date,datetime-local,email,file,hidden,image,month,number,password,range,reset,search,submit,tel,text,time,url,week',
            ],
            'validate' => $overrides['autocomplete_query']['validate'] ?? null,
            'internal' => true, // Flag for automatic stripping before passing to Vue
            'section' => 'suggestions',
        ], 'autocomplete' => [
            'display' => $overrides['autocomplete']['display'] ?? 'Autocomplete Value',
            'instructions' => $overrides['autocomplete']['instructions'] ?? 'Specify the type of data this field contains to help browsers provide appropriate autocomplete suggestions.',
            'type' => $overrides['autocomplete']['type'] ?? 'select',
            'clearable' => $overrides['autocomplete']['clearable'] ?? false,
            'options' => $this->getAutocompleteOptions(),
            'default' => $overrides['autocomplete']['default'] ?? 'null',
            // 'visibility' => $hasSingleAutocompleteKey ? 'hidden' : 'visible',
            'if' => [
                'autocomplete_query' => 'on',
            ],
            'validate' => $overrides['autocomplete']['validate'] ?? null,
            'section' => 'suggestions',
        ], 'autocorrect' => [
            'display' => $overrides['autocorrect']['display'] ?? 'Enable Autocorrect (autocorrect attribute)',
            'instructions' => $overrides['autocorrect']['instructions'] ?? 'Control whether the browser should automatically correct spelling and grammar errors.',
            'type' => $overrides['autocorrect']['type'] ?? 'select',
            'default' => $overrides['autocorrect']['default'] ?? 'null',
            'options' => $overrides['autocorrect']['options'] ?? [
                'on' => 'On',
                'off' => 'Off',
                'null' => 'Not Specified',
            ],
            'internal' => true, // Only internal until we have support for this prop
            'validate' => $overrides['autocorrect']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'focus' => [
            'display' => $overrides['focus']['display'] ?? 'Auto Focus on Load (autofocus attribute)',
            'instructions' => $overrides['focus']['instructions'] ?? <<<'EOT'
                    Control whether this field automatically receives focus when the page loads.

                    For text fields, `title` and `alt` fields focus by default unless explicitly disabled.

                    Setting this to "Disable" will prevent focus even for `title`/`alt` fields.
                    EOT,
            'type' => $overrides['focus']['type'] ?? 'select',
            'default' => $overrides['focus']['default'] ?? 'null',
            'options' => $overrides['focus']['options'] ?? [
                'true' => 'Enable: Always focus this field',
                'false' => 'Disable: Never focus this field (even if title/alt)',
                'null' => 'Default: Use default behavior (title/alt fields focus automatically)',
            ],
            'validate' => $overrides['focus']['validate'] ?? null,
            'section' => 'behavior',
        ], 'debounce' => [
            'display' => $overrides['debounce']['display'] ?? 'Debounce Updates',
            'instructions' => $overrides['debounce']['instructions'] ?? 'When enabled, the stored value updates after a short delay when typing stops, instead of on every keystroke.',
            'type' => $overrides['debounce']['type'] ?? 'toggle',
            'default' => $overrides['debounce']['default'] ?? false,
            'validate' => $overrides['debounce']['validate'] ?? null,
            'section' => 'behavior',
        ], 'capture' => [
            'display' => $overrides['capture']['display'] ?? 'Media Capture Source (capture attribute)',
            'instructions' => $overrides['capture']['instructions'] ?? 'Specify which camera to use for file inputs. Use this attribute to control whether the front-facing or back-facing camera is used for media capture.',
            'type' => $overrides['capture']['type'] ?? 'select',
            'default' => $overrides['capture']['default'] ?? 'null',
            'options' => $overrides['capture']['options'] ?? [
                'user' => 'User: The user-facing camera and/or microphone should be used.',
                'environment' => 'Environment: The outward-facing camera and/or microphone should be used.',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['capture']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any file',
            ],
            'section' => 'behavior',
        ], 'checked' => [
            'display' => $overrides['checked']['display'] ?? 'Default Checked State (checked attribute)',
            'instructions' => $overrides['checked']['instructions'] ?? 'Set the initial checked state for checkboxes and radio buttons. Use this attribute to pre-select this input when the form loads.',
            'type' => $overrides['checked']['type'] ?? 'toggle',
            'default' => $overrides['checked']['default'] ?? false,
            'validate' => $overrides['checked']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any checkbox,radio',
            ],
            'section' => 'value_and_state',
        ], 'class' => [
            'display' => $overrides['class']['display'] ?? 'CSS Classes (class attribute)',
            'instructions' => $overrides['class']['instructions'] ?? 'Set one or more CSS class names for this element. Separate multiple classes with spaces.',
            'type' => $overrides['class']['type'] ?? 'text',
            'default' => $overrides['class']['default'] ?? null,
            'validate' => $overrides['class']['validate'] ?? null,
            'section' => 'identification',
        ], 'contenteditable' => [
            'display' => $overrides['contenteditable']['display'] ?? 'Content Editable',
            'instructions' => $overrides['contenteditable']['instructions'] ?? 'Control whether the field content is editable. Enumerated: `true`, `false`, or `not-specified`.',
            'type' => $overrides['contenteditable']['type'] ?? 'hidden',
            'default' => $overrides['contenteditable']['default'] ?? null,
            'validate' => $overrides['contenteditable']['validate'] ?? null,
            'section' => 'appearance',
        ], 'dir' => [
            'display' => $overrides['dir']['display'] ?? 'Text Direction (dir attribute)',
            'instructions' => $overrides['dir']['instructions'] ?? 'Set the text direction for this element.',
            'type' => $overrides['dir']['type'] ?? 'select',
            'default' => $overrides['dir']['default'] ?? 'null',
            'options' => $overrides['dir']['options'] ?? [
                'ltr' => "LTR: The element's text is laid out from left to right",
                'rtl' => "RTL: The element's text is laid out from right to left",
                'auto' => 'Auto: Let the user agent decide',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['dir']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'dirname' => [
            'display' => $overrides['dirname']['display'] ?? 'Text Direction Field Name (dirname attribute)',
            'instructions' => $overrides['dirname']['instructions'] ?? 'Submit the text direction with form data. Use this attribute to include the text direction when the form is submitted.',
            'type' => $overrides['dirname']['type'] ?? 'hidden',
            'default' => $overrides['dirname']['default'] ?? null,
            'validate' => $overrides['dirname']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,hidden,search,tel,text,url',
            ],
            'section' => 'accessibility',
        ], 'draggable' => [
            'display' => $overrides['draggable']['display'] ?? 'Enable Dragging (draggable attribute)',
            'instructions' => $overrides['draggable']['instructions'] ?? 'Set whether this element can be dragged by the user.',
            'type' => $overrides['draggable']['type'] ?? 'select',
            'default' => $overrides['draggable']['default'] ?? 'null',
            'options' => $overrides['draggable']['options'] ?? [
                'true' => 'True: The element can be dragged',
                'false' => 'False: The element cannot be dragged',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['draggable']['validate'] ?? null,
            'section' => 'behavior',
        ], 'enterkeyhint' => [
            'display' => $overrides['enterkeyhint']['display'] ?? 'Enter Key Action Label (enterkeyhint attribute)',
            'instructions' => $overrides['enterkeyhint']['instructions'] ?? 'Set the action label for the enter key on virtual keyboards.',
            'type' => $overrides['enterkeyhint']['type'] ?? 'select',
            'default' => $overrides['enterkeyhint']['default'] ?? 'null',
            'options' => $overrides['enterkeyhint']['options'] ?? [
                'enter' => 'Enter: Typically inserting a new line.',
                'done' => 'Done: Typically meaning there is nothing more to input and the input method editor will be closed.',
                'go' => 'Go: Typically meaning to take the user to the target of the text they typed.',
                'next' => 'Next: Typically taking the user to the next field that will accept text.',
                'previous' => 'Previous: Typically taking the user to the previous field that will accept text.',
                'search' => 'Search: Typically taking the user to the results of searching for the text they have typed.',
                'send' => 'Send: Typically delivering the text to its target.',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['enterkeyhint']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'exportparts' => [
            'display' => $overrides['exportparts']['display'] ?? 'Shadow DOM Part Exports (exportparts attribute)',
            'instructions' => $overrides['exportparts']['instructions'] ?? 'Expose shadow DOM parts for styling from outside the component. List part names to export, separated by commas.',
            'type' => $overrides['exportparts']['type'] ?? 'text',
            'default' => $overrides['exportparts']['default'] ?? null,
            'validate' => $overrides['exportparts']['validate'] ?? null,
            'section' => 'advanced',
        ], 'form' => [
            'display' => $overrides['form']['display'] ?? 'Associated Form ID (form attribute)',
            'instructions' => $overrides['form']['instructions'] ?? 'Associate this input with a specific form. Use this attribute to link inputs to forms outside their natural form container.',
            'type' => $overrides['form']['type'] ?? 'text',
            'default' => $overrides['form']['default'] ?? null,
            'validate' => $overrides['form']['validate'] ?? null,
            'section' => 'form',
        ], 'formaction' => [
            'display' => $overrides['formaction']['display'] ?? 'Form Submission URL (formaction attribute)',
            'instructions' => $overrides['formaction']['instructions'] ?? "Override the form's action URL for this submit button. Use this attribute to send form data to a different endpoint when this specific button is clicked.",
            'type' => $overrides['formaction']['type'] ?? 'text',
            'default' => $overrides['formaction']['default'] ?? null,
            'validate' => $overrides['formaction']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image,submit',
            ],
            'section' => 'form',
        ], 'formenctype' => [
            'display' => $overrides['formenctype']['display'] ?? 'Form Data Encoding Type (formenctype attribute)',
            'instructions' => $overrides['formenctype']['instructions'] ?? "Override the form's encoding type for this submit button. Use this attribute to specify how form data should be encoded when submitted.",
            'type' => $overrides['formenctype']['type'] ?? 'text',
            'default' => $overrides['formenctype']['default'] ?? null,
            'validate' => $overrides['formenctype']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image,submit',
            ],
            'section' => 'form',
        ], 'formmethod' => [
            'display' => $overrides['formmethod']['display'] ?? 'Form Submission Method (formmethod attribute)',
            'instructions' => $overrides['formmethod']['instructions'] ?? "Override the form's HTTP method for this submit button. Use this attribute to specify how form data should be sent (GET, POST, etc.).",
            'type' => $overrides['formmethod']['type'] ?? 'select',
            'default' => $overrides['formmethod']['default'] ?? 'null',
            'options' => $overrides['formmethod']['options'] ?? [
                'dialog' => 'DIALOG',
                'get' => 'GET',
                'post' => 'POST',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['formmethod']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image,submit',
            ],
            'section' => 'form',
        ], 'formnovalidate' => [
            'display' => $overrides['formnovalidate']['display'] ?? 'Bypass Form Validation (formnovalidate attribute)',
            'instructions' => $overrides['formnovalidate']['instructions'] ?? 'Disable form validation for this submit button. Use this attribute to bypass client-side validation when this specific button is clicked.',
            'type' => $overrides['formnovalidate']['type'] ?? 'toggle',
            'default' => $overrides['formnovalidate']['default'] ?? false,
            'validate' => $overrides['formnovalidate']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image,submit',
            ],
            'section' => 'form',
        ], 'formtarget' => [
            'display' => $overrides['formtarget']['display'] ?? 'Form Submission Target (formtarget attribute)',
            'instructions' => $overrides['formtarget']['instructions'] ?? "Override the form's target for this submit button. Use this attribute to specify where the form response should be displayed.",
            'type' => $overrides['formtarget']['type'] ?? 'text',
            'default' => $overrides['formtarget']['default'] ?? null,
            'validate' => $overrides['formtarget']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image,submit',
            ],
            'section' => 'form',
        ], 'height' => [
            'display' => $overrides['height']['display'] ?? 'Image Height (height attribute)',
            'instructions' => $overrides['height']['instructions'] ?? 'Set the height of image input buttons. Use this attribute to specify the vertical size of the image when used as a submit button.',
            'type' => $overrides['height']['type'] ?? 'integer',
            'default' => $overrides['height']['default'] ?? null,
            'validate' => $overrides['height']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image',
            ],
            'section' => 'appearance',
        ], 'id' => [
            'display' => $overrides['id']['display'] ?? 'Unique Identifier (id attribute)',
            'instructions' => $overrides['id']['instructions'] ?? 'Set a unique identifier for this element.',
            'type' => $overrides['id']['type'] ?? 'text',
            'default' => $overrides['id']['default'] ?? null,
            'validate' => $overrides['id']['validate'] ?? null,
            'section' => 'identification',
        ], 'inert' => [
            'display' => $overrides['inert']['display'] ?? 'Disable Interaction (inert attribute)',
            'instructions' => $overrides['inert']['instructions'] ?? 'Disable interaction with this element and its descendants. Use this attribute to prevent focus, clicks, and accessibility tree inclusion.',
            'type' => $overrides['inert']['type'] ?? 'toggle',
            'default' => $overrides['inert']['default'] ?? false,
            'validate' => $overrides['inert']['validate'] ?? null,
            'section' => 'behavior',
        ], 'inputmode' => [
            'display' => $overrides['inputmode']['display'] ?? 'Input Mode (inputmode attribute)',
            'instructions' => $overrides['inputmode']['instructions'] ?? 'Set the type of virtual keyboard to display. Use this attribute to optimize the keyboard for specific input types like email, numeric, or search.',
            'type' => $overrides['inputmode']['type'] ?? 'select',
            'default' => $overrides['inputmode']['default'] ?? 'null',
            'options' => $overrides['inputmode']['options'] ?? [
                'none' => 'None: No virtual keyboard',
                'text' => "Text: Standard input keyboard for the user's current locale",
                'decimal' => "Decimal: Fractional numeric input keyboard containing the digits and decimal separator for the user's locale",
                'numeric' => 'Numeric: Numeric input keyboard, but only requires the digits 0–9',
                'tel' => 'Tel: A telephone keypad input, including the digits 0–9, the asterisk (*), and the pound (#) key',
                'search' => 'Search: A virtual keyboard optimized for search input',
                'email' => 'Email: A virtual keyboard optimized for entering email addresses',
                'url' => 'URL: A keypad optimized for entering URLs',
                'null' => 'Not Specified',
            ],
            'internal' => true, // Only internal until we have support for this prop
            'validate' => $overrides['inputmode']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'is' => [
            'display' => $overrides['is']['display'] ?? 'Custom Element Name (is attribute)',
            'instructions' => $overrides['is']['instructions'] ?? 'Specify that this element should behave like a custom built-in element. Use this attribute to extend standard HTML elements with custom behavior.',
            'type' => $overrides['is']['type'] ?? 'text',
            'default' => $overrides['is']['default'] ?? null,
            'validate' => $overrides['is']['validate'] ?? null,
            'section' => 'advanced',
        ], 'itemid' => [
            'display' => $overrides['itemid']['display'] ?? 'Microdata Item ID (itemid attribute)',
            'instructions' => $overrides['itemid']['instructions'] ?? 'Set a unique global identifier for this microdata item.',
            'type' => $overrides['itemid']['type'] ?? 'text',
            'default' => $overrides['itemid']['default'] ?? null,
            'validate' => $overrides['itemid']['validate'] ?? null,
            'section' => 'microdata',
        ], 'itemprop' => [
            'display' => $overrides['itemprop']['display'] ?? 'Microdata Item Property (itemprop attribute)',
            'instructions' => $overrides['itemprop']['instructions'] ?? 'Define a property of the microdata item.',
            'type' => $overrides['itemprop']['type'] ?? 'text',
            'default' => $overrides['itemprop']['default'] ?? null,
            'validate' => $overrides['itemprop']['validate'] ?? null,
            'section' => 'microdata',
        ], 'itemref' => [
            'display' => $overrides['itemref']['display'] ?? 'Microdata Item Reference (itemref attribute)',
            'instructions' => $overrides['itemref']['instructions'] ?? 'Reference additional properties from other elements by their IDs. Use this attribute to associate properties that are not descendants of the current item.',
            'type' => $overrides['itemref']['type'] ?? 'text',
            'default' => $overrides['itemref']['default'] ?? null,
            'validate' => $overrides['itemref']['validate'] ?? null,
            'section' => 'microdata',
        ], 'itemscope' => [
            'display' => $overrides['itemscope']['display'] ?? 'Microdata Item Scope (itemscope attribute)',
            'instructions' => $overrides['itemscope']['instructions'] ?? 'Create a new microdata item scope. Use this attribute to define a group of related properties for structured data.',
            'type' => $overrides['itemscope']['type'] ?? 'toggle',
            'default' => $overrides['itemscope']['default'] ?? false,
            'validate' => $overrides['itemscope']['validate'] ?? null,
            'section' => 'microdata',
        ], 'itemtype' => [
            'display' => $overrides['itemtype']['display'] ?? 'Microdata Item Type (itemtype attribute)',
            'instructions' => $overrides['itemtype']['instructions'] ?? 'Set the vocabulary URL for microdata properties. Use this attribute to specify the schema that defines the structure of your data.',
            'type' => $overrides['itemtype']['type'] ?? 'text',
            'default' => $overrides['itemtype']['default'] ?? null,
            'validate' => $overrides['itemtype']['validate'] ?? ['url'],
            'section' => 'microdata',
        ], 'lang' => [
            'display' => $overrides['lang']['display'] ?? 'Language (lang attribute)',
            'instructions' => $overrides['lang']['instructions'] ?? 'Set the language of this element using a BCP 47 language tag. Use this attribute to help screen readers and other tools provide correct pronunciation.',
            'type' => $overrides['lang']['type'] ?? 'hidden',
            'default' => $overrides['lang']['default'] ?? null,
            'validate' => $overrides['lang']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'list' => [
            'display' => $overrides['list']['display'] ?? 'Datalist ID (list attribute)',
            'instructions' => $overrides['list']['instructions'] ?? 'Reference a datalist for input suggestions. Use this attribute to connect this input to a datalist element that provides predefined options.',
            'type' => $overrides['list']['type'] ?? 'text',
            'default' => $overrides['list']['default'] ?? null,
            'validate' => $overrides['list']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any color,date,datetime-local,email,file,image,month,number,range,reset,search,submit,tel,text,time,url,week',
            ],
            'section' => 'suggestions',
        ], 'min' => [
            'display' => $overrides['min']['display'] ?? 'Minimum Value',
            'instructions' => $overrides['min']['instructions'] ?? 'Set the minimum allowed value for this field.',
            'type' => $overrides['min']['type'] ?? 'text',
            'default' => $overrides['min']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any date,datetime-local,month,number,range,time,week',
            ],
            'validate' => $overrides['min']['validate'] ?? null,
            'section' => 'constraints',
        ], 'minlength' => [
            'display' => $overrides['minlength']['display'] ?? 'Minimum Input Length',
            'instructions' => $overrides['minlength']['instructions'] ?? 'Set the minimum number of enterable characters.',
            'type' => $overrides['minlength']['type'] ?? 'integer',
            'default' => $overrides['minlength']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,password,search,tel,text,url',
            ],
            'internal' => true, // Only internal until we have support for this prop
            'validate' => $overrides['minlength']['validate'] ?? null,
            'section' => 'constraints',
        ], 'max' => [
            'display' => $overrides['max']['display'] ?? 'Maximum Value',
            'instructions' => $overrides['max']['instructions'] ?? 'Set the maximum allowed value for this field.',
            'type' => $overrides['max']['type'] ?? 'text',
            'default' => $overrides['max']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any date,datetime-local,month,number,range,time,week',
            ],
            'internal' => true, // Only internal until we have support for this prop
            'validate' => $overrides['max']['validate'] ?? null,
            'section' => 'constraints',
        ], 'maxlength' => [
            'display' => $overrides['maxlength']['display'] ?? 'Character Limit',
            'instructions' => $overrides['maxlength']['instructions'] ?? 'Set the maximum number of enterable characters.',
            'type' => $overrides['maxlength']['type'] ?? 'integer',
            'default' => $overrides['maxlength']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,password,search,tel,text,url',
            ],
            'internal' => true, // Only internal until we have support for this prop
            'validate' => $overrides['maxlength']['validate'] ?? null,
            'section' => 'constraints',
        ], 'multiple' => [
            'display' => $overrides['multiple']['display'] ?? 'Allow Multiple Values (multiple attribute)',
            'instructions' => $overrides['multiple']['instructions'] ?? 'Allow multiple values to be selected. Use this attribute to enable users to select multiple files or email addresses in a single input.',
            'type' => $overrides['multiple']['type'] ?? 'toggle',
            'default' => $overrides['multiple']['default'] ?? false,
            'validate' => $overrides['multiple']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,file',
            ],
            'section' => 'behavior',
        ], 'name' => [
            'display' => $overrides['name']['display'] ?? 'Form Input Name (name)',
            'instructions' => $overrides['name']['instructions'] ?? 'Set the name for this form control. Use this attribute to identify the input when the form is submitted and processed by the server.',
            'type' => $overrides['name']['type'] ?? 'text',
            'default' => $overrides['name']['default'] ?? null,
            'validate' => $overrides['name']['validate'] ?? null,
            'section' => 'identification',
        ], 'nonce' => [
            'display' => $overrides['nonce']['display'] ?? 'Cryptographic Nonce (nonce attribute)',
            'instructions' => $overrides['nonce']['instructions'] ?? 'Set a cryptographic nonce for Content Security Policy. Use this attribute to allowlist specific inline scripts or styles while avoiding unsafe-inline.',
            'type' => $overrides['nonce']['type'] ?? 'hidden',
            'default' => $overrides['nonce']['default'] ?? null,
            'validate' => $overrides['nonce']['validate'] ?? null,
            'section' => 'advanced',
        ], 'part' => [
            'display' => $overrides['part']['display'] ?? 'Shadow DOM Part Name (part attribute)',
            'instructions' => $overrides['part']['instructions'] ?? 'Set part names for shadow DOM styling. Use this attribute to allow CSS to select and style specific elements via the `::part` pseudo-element.',
            'type' => $overrides['part']['type'] ?? 'text',
            'default' => $overrides['part']['default'] ?? null,
            'validate' => $overrides['part']['validate'] ?? null,
            'section' => 'advanced',
        ], 'pattern' => [
            'display' => $overrides['pattern']['display'] ?? 'Regular Expression Pattern (pattern attribute)',
            'instructions' => $overrides['pattern']['instructions'] ?? <<<EOT
                    Set a regular expression pattern that the input value must match.

                    Example: `^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$` for email validation.
                    EOT,
            'type' => $overrides['pattern']['type'] ?? 'text',
            'default' => $overrides['pattern']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,password,search,tel,text,url',
            ],
            'validate' => $overrides['pattern']['validate'] ?? null,
            'internal' => true, // Only internal until we have support for this prop
            'section' => 'constraints',
        ], 'popover' => [
            'display' => $overrides['popover']['display'] ?? 'Popover Behavior (popover attribute)',
            'instructions' => $overrides['popover']['instructions'] ?? 'Designate this element as a popover.',
            'type' => $overrides['popover']['type'] ?? 'select',
            'default' => $overrides['popover']['default'] ?? 'null',
            'options' => $overrides['popover']['options'] ?? [
                'auto' => 'Auto State: popovers can be "light dismissed"',
                'manual' => 'Manual State: popover cannot be "light dismissed", although declarative show/hide/toggle buttons will still work',
                'hint' => 'Hint State: popover does not close `auto` popovers when they are displayed, but will close other `hint` popovers',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['popover']['validate'] ?? null,
            'section' => 'behavior',
        ], 'popovertarget' => [
            'display' => $overrides['popovertarget']['display'] ?? 'Popover Target ID (popovertarget attribute)',
            'instructions' => $overrides['popovertarget']['instructions'] ?? 'Reference a popover element to control. Use this attribute to specify which popover this button should show, hide, or toggle.',
            'type' => $overrides['popovertarget']['type'] ?? 'text',
            'default' => $overrides['popovertarget']['default'] ?? null,
            'validate' => $overrides['popovertarget']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any button',
            ],
            'section' => 'behavior',
        ], 'popovertargetaction' => [
            'display' => $overrides['popovertargetaction']['display'] ?? 'Popover Action (popovertargetaction attribute)',
            'instructions' => $overrides['popovertargetaction']['instructions'] ?? 'Set the action for popover control. Use this attribute to specify whether this button should show, hide, or toggle the target popover.',
            'type' => $overrides['popovertargetaction']['type'] ?? 'select',
            'default' => $overrides['popovertargetaction']['default'] ?? 'null',
            'options' => $overrides['popovertargetaction']['options'] ?? [
                'hide' => 'Hide: The button will hide a shown popover',
                'show' => 'Show: The button will show a hidden popover',
                'toggle' => 'Toggle: The button will toggle a popover between showing and hidden',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['popovertargetaction']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any button',
            ],
            'section' => 'behavior',
        ], 'role' => [
            'display' => $overrides['role']['display'] ?? 'ARIA Role (role attribute)',
            'instructions' => $overrides['role']['instructions'] ?? "Set an ARIA role to define the element's purpose for assistive technologies. Use this attribute to improve accessibility and semantic meaning.",
            'type' => $overrides['role']['type'] ?? 'text',
            'default' => $overrides['role']['default'] ?? null,
            'validate' => $overrides['role']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'size' => [
            'display' => $overrides['size']['display'] ?? 'Visible Width (size attribute)',
            'instructions' => $overrides['size']['instructions'] ?? 'Set the visible width of text inputs. Use this attribute to control how many characters are visible in the input field at once.',
            'type' => $overrides['size']['type'] ?? 'integer',
            'default' => $overrides['size']['default'] ?? null,
            'validate' => $overrides['size']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,password,search,tel,text,url',
            ],
            'section' => 'appearance',
        ], 'slot' => [
            'display' => $overrides['slot']['display'] ?? 'Shadow DOM Slot Name (slot attribute)',
            'instructions' => $overrides['slot']['instructions'] ?? 'Assign this element to a named slot in a shadow DOM. Use this attribute to control where content appears in web components.',
            'type' => $overrides['slot']['type'] ?? 'text',
            'default' => $overrides['slot']['default'] ?? null,
            'validate' => $overrides['slot']['validate'] ?? null,
            'section' => 'advanced',
        ], 'spellcheck' => [
            'display' => $overrides['spellcheck']['display'] ?? 'Enable Spell Checking (spellcheck attribute)',
            'instructions' => $overrides['spellcheck']['instructions'] ?? 'Enable or disable browser spell checking for this field.',
            'type' => $overrides['spellcheck']['type'] ?? 'select',
            'default' => $overrides['spellcheck']['default'] ?? 'null',
            'options' => [
                'true' => 'True',
                'false' => 'False',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['spellcheck']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'src' => [
            'display' => $overrides['src']['display'] ?? 'Image Source (src attribute)',
            'instructions' => $overrides['src']['instructions'] ?? 'Set the image source for image inputs. Use this attribute to specify the URL of the image that will be used as a submit button.',
            'type' => $overrides['src']['type'] ?? 'text',
            'default' => $overrides['src']['default'] ?? null,
            'validate' => $overrides['src']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image',
            ],
            'section' => 'appearance',
        ], 'step' => [
            'display' => $overrides['step']['display'] ?? 'Step Interval (step attribute)',
            'instructions' => $overrides['step']['instructions'] ?? 'Set the step interval for this field.',
            'type' => $overrides['step']['type'] ?? 'float',
            'default' => $overrides['step']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any date,datetime-local,month,number,range,time,week',
            ],
            'validate' => $overrides['step']['validate'] ?? ['gt:0'],
            'section' => 'constraints',
        ], 'style' => [
            'display' => $overrides['style']['display'] ?? 'Inline CSS Styles (style attribute)',
            'instructions' => $overrides['style']['instructions'] ?? 'Add inline CSS styles to this element. Use this attribute for quick or test styling, but prefer external stylesheets for maintainability.',
            'type' => $overrides['style']['type'] ?? 'text',
            'default' => $overrides['style']['default'] ?? null,
            'validate' => $overrides['style']['validate'] ?? null,
            'section' => 'appearance',
        ], 'tabindex' => [
            'display' => $overrides['tabindex']['display'] ?? 'Tab Order (tabindex attribute)',
            'instructions' => $overrides['tabindex']['instructions'] ?? 'Set the tab order for keyboard navigation. Use this attribute to make this element focusable and control its position in the tab sequence.',
            'type' => $overrides['tabindex']['type'] ?? 'integer',
            'default' => $overrides['tabindex']['default'] ?? null,
            'validate' => $overrides['tabindex']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'title' => [
            'display' => $overrides['title']['display'] ?? 'Tooltip (title attribute)',
            'instructions' => $overrides['title']['instructions'] ?? 'Add advisory text for this element. Use this attribute to provide extra information, shown as a tooltip in most browsers.',
            'type' => $overrides['title']['type'] ?? 'text',
            'default' => $overrides['title']['default'] ?? null,
            'validate' => $overrides['title']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'translate' => [
            'display' => $overrides['translate']['display'] ?? 'Automatic Translation (translate attribute)',
            'instructions' => $overrides['translate']['instructions'] ?? "Control whether this element's content is translated. Use this attribute to allow or prevent automatic translation by browsers and tools.",
            'type' => $overrides['translate']['type'] ?? 'select',
            'default' => $overrides['translate']['default'] ?? 'null',
            'options' => $overrides['translate']['options'] ?? [
                'yes' => 'Yes',
                'no' => 'No',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['translate']['validate'] ?? null,
            'section' => 'accessibility',
        ], 'input_type' => [
            'display' => $overrides['input_type']['display'] ?? 'Input Type',
            'type' => $overrides['input_type']['type'] ?? 'hidden',
            'default' => $overrides['input_type']['default'] ?? $this->getInputType(),
            'validate' => $overrides['input_type']['validate'] ?? null,
            'section' => 'value_and_state',
        ], 'calculation_query' => [
            'display' => 'Enable Dynamic Default Value',
            'instructions' => 'Pre-populate the field with contextual or dynamic data based on the current page, user, or other factors.',
            'type' => 'toggle',
            'section' => 'value_and_state',
        ], 'calculation_type' => [
            'display' => 'Dynamic Default Value Type',
            'instructions' => 'Dynamic **math** values only use numbers in the calculation, and any included math operations will be applied. Dynamic **text** values are combined literally.',
            'type' => 'radio',
            'options' => [
                'math' => 'Math',
                'text' => 'Text',
            ],
            'default' => 'math',
            'if' => [
                'input_type' => 'contains_any button,checkbox,color,date,datetime-local,email,file,hidden,month,number,password,radio,range,reset,search,submit,tel,text,time,url,week',
                'calculation_query' => 'equals true',
            ],
            'section' => 'value_and_state',
        ], 'calculation_formula' => [
            'display' => 'Calculation Formula',
            'type' => 'code',
            'mode' => 'javascript',
            'mode_selectable' => false,
            'visibility' => 'hidden',
            'section' => 'value_and_state',
        ], 'calculation_formula_math' => [
            'display' => 'Dynamic Default Value Calculation Formula',
            'instructions' => <<<'EOT'
                    Enter a math expression that calculates the value. Formulas are evaluated by a sandboxed math engine (mathjs) — no arbitrary JavaScript runs.

                    **Field References:** Use the field handle wrapped in square brackets to reference other field values (e.g., `[price]`, `[quantity]`, `[tax_rate]`).

                    **URL Parameters:** Use `[get_param:param_name]` to reference URL parameters from the current page (e.g., `[get_param:quantity]`) if they return numeric values.

                    **Common Operators:**
                    - `+` Addition: `[price] + [tax]`
                    - `-` Subtraction: `[total] - [discount]`
                    - `*` Multiplication: `[price] * [quantity]`
                    - `/` Division: `[total] / [items]`
                    - `%` Modulo: `[total] % 10` (follows the divisor's sign — e.g. `-1 % 3` is `2`)
                    - `^` Exponentiation: `[base] ^ 2`
                    - `()` Grouping: `([price] + [tax]) * [quantity]`

                    **Math Functions:**
                    - `Math.round()` Round to nearest integer, halves away from zero: `Math.round([price] * 1.1)`
                    - `Math.floor()` Round down to integer: `Math.floor([total] / 100)`
                    - `Math.ceil()` Round up to integer: `Math.ceil([amount] / 10)`
                    - `Math.abs()` Absolute value: `Math.abs([profit] - [loss])`
                    - `Math.min()` Minimum of values: `Math.min([price1], [price2])`
                    - `Math.max()` Maximum of values: `Math.max([value1], [value2])`

                    **Examples:**
                    - `[price] * [quantity]` (subtotal)
                    - `[price] * [get_param:quantity]` (subtotal based on URL parameter)
                    - `[subtotal] * ([tax_rate] / 100)` (tax amount)
                    - `[subtotal] + [tax_amount]` (total)
                    - `Math.round(([price] * [quantity]) * 1.08)` (with tax)
                    EOT,
            'type' => 'code',
            'mode' => 'javascript',
            'mode_selectable' => false,
            'if' => [
                'calculation_query' => 'equals true',
                'calculation_type' => 'equals math',
            ],
            'section' => 'value_and_state',
        ], 'calculation_formula_text' => [
            'display' => 'Dynamic Default Value Text',
            'instructions' => <<<'EOT'
                    Enter the text in the manner you would like it to be displayed.

                    **Field References:** Use the field handle wrapped in square brackets to reference other field values (e.g., `[order_number]`, `[item_count]`, `[first_name]`).

                    **URL Parameters:** Use `[get_param:param_name]` to reference URL parameters from the current page (e.g., `[get_param:editor_email_address]`).

                    **Examples:**
                    - `Order #[order_number] - [customer_name]` to display a value like "Order #123456 - John Doe"
                    - `Total: $[amount] for [item_count] items` to display a value like "Total: $12.34 for 5 items"
                    - `[first_name] [last_name]` to display a value like "John Doe"
                    - `[get_param:editor_email_address]` to display a value like "user@example.com" (from URL parameter)
                    EOT,
            'type' => 'code',
            'mode' => 'handlebars',
            'mode_selectable' => false,
            'if' => [
                'calculation_query' => 'equals true',
                'calculation_type' => 'equals text',
            ],
            'section' => 'value_and_state',
        ], 'calculation_decimals' => [
            'display' => 'Decimal Places of Calculated Value',
            'instructions' => 'Number of decimal places to display',
            'type' => 'integer',
            'if' => [
                'calculation_query' => 'equals true',
                'calculation_type' => 'equals math',
            ],
            'section' => 'value_and_state',
        ], 'default' => [
            'display' => $overrides['default']['display'] ?? __('Default Value'),
            'instructions' => $overrides['default']['instructions'] ?? __('statamic::messages.fields_default_instructions'),
            'type' => $overrides['default']['type'] ?? 'text',
            'default' => $overrides['default']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any button,checkbox,color,date,datetime-local,email,file,hidden,month,number,password,radio,range,reset,search,submit,tel,text,time,url,week',
                'calculation_query' => 'equals false',
            ],
            'validate' => $overrides['default']['validate'] ?? null,
            'section' => 'value_and_state',
        ], 'placeholder' => [
            'display' => $overrides['placeholder']['display'] ?? __('Placeholder'),
            'instructions' => $overrides['placeholder']['instructions'] ?? __('statamic::fieldtypes.text.config.placeholder'),
            'type' => $overrides['placeholder']['type'] ?? 'text',
            'default' => $overrides['placeholder']['default'] ?? null,
            'if' => [
                'input_type' => 'contains_any email,number,password,search,tel,text,url',
                'calculation_query' => 'not true',
            ],
            'validate' => $overrides['placeholder']['validate'] ?? null,
            'section' => 'value_and_state',
        ], 'virtualkeyboardpolicy' => [
            'display' => $overrides['virtualkeyboardpolicy']['display'] ?? 'Virtual Keyboard Policy (virtualkeyboardpolicy attribute)',
            'instructions' => $overrides['virtualkeyboardpolicy']['instructions'] ?? 'Control virtual keyboard behavior on mobile devices.',
            'type' => $overrides['virtualkeyboardpolicy']['type'] ?? 'select',
            'default' => $overrides['virtualkeyboardpolicy']['default'] ?? 'null',
            'options' => $overrides['virtualkeyboardpolicy']['options'] ?? [
                'auto' => 'Auto: automatically show the virtual keyboard when the element is focused or tapped',
                'manual' => "Manual: decouple focus and tap on the element from the virtual keyboard's state",
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['virtualkeyboardpolicy']['validate'] ?? null,
            'section' => 'experimental',
        ], 'width' => [
            'display' => $overrides['width']['display'] ?? 'Image Width (width attribute)',
            'instructions' => $overrides['width']['instructions'] ?? 'Set the width of image input buttons. Use this attribute to specify the horizontal size of the image when used as a submit button.',
            'type' => $overrides['width']['type'] ?? 'integer',
            'default' => $overrides['width']['default'] ?? null,
            'validate' => $overrides['width']['validate'] ?? null,
            'if' => [
                'input_type' => 'contains_any image',
            ],
            'section' => 'appearance',
        ], 'writingsuggestions' => [
            'display' => $overrides['writingsuggestions']['display'] ?? 'Writing Suggestions (writingsuggestions attribute)',
            'instructions' => $overrides['writingsuggestions']['instructions'] ?? 'Enable or disable browser writing suggestions. Use this attribute to control whether the browser provides text completion suggestions.',
            'type' => $overrides['writingsuggestions']['type'] ?? 'select',
            'default' => $overrides['writingsuggestions']['default'] ?? 'null',
            'options' => $overrides['writingsuggestions']['options'] ?? [
                'true' => 'True',
                'false' => 'False',
                'null' => 'Not Specified',
            ],
            'validate' => $overrides['writingsuggestions']['validate'] ?? null,
            'section' => 'accessibility',
        ]];
    }

    /**
     * Build the Statamic-specific config fields (`prepend`, `append`, `antlers`).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function statamicConfigFields(): array
    {
        return ['prepend' => [
            'display' => __('Prepend'),
            'instructions' => __('statamic::fieldtypes.text.config.prepend'),
            'type' => 'text',
            'section' => 'appearance',
        ], 'append' => [
            'display' => __('Append'),
            'instructions' => __('statamic::fieldtypes.text.config.append'),
            'type' => 'text',
            'section' => 'appearance',
        ], 'antlers' => [
            'display' => __('Allow Antlers'),
            'instructions' => __('statamic::fieldtypes.any.config.antlers'),
            'type' => 'toggle',
            'section' => 'antlers',
        ]];
    }

    /**
     * Build the input-mask config fields (transform + IMask masking options).
     *
     * @param  array<string, array<string, mixed>>  $overrides  Per-field attribute overrides
     * @return array<string, array<string, mixed>>
     */
    protected function inputMaskConfigFields(array $overrides): array
    {
        return ['transform_case' => [
            'display' => $overrides['transform_case']['display'] ?? 'Transform Case',
            'type' => $overrides['transform_case']['type'] ?? 'select',
            'instructions' => $overrides['transform_case']['instructions'] ?? 'Choose whether to transform input characters to uppercase, lowercase, or leave them unchanged. If not set, no transformation occurs.',
            'options' => $overrides['transform_case']['options'] ?? [
                'uppercase' => 'Uppercase',
                'lowercase' => 'Lowercase',
                'null' => 'Not Specified',
            ],
            'default' => $overrides['transform_case']['default'] ?? 'null',
            'if' => [
                'input_type' => 'contains_any email,number,search,tel,text,url',
                'input_mask' => 'equals false', // transform_case is only available if input_mask is disabled
            ],
            'section' => 'appearance',
        ], 'input_mask' => [
            'display' => $overrides['input_mask']['display'] ?? 'Enable Input Mask',
            'type' => $overrides['input_mask']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask']['instructions'] ?? 'Enables input masking to control and validate user input according to specific patterns and rules.',
            'default' => $overrides['input_mask']['default'] ?? false,
            'if' => [
                'input_type' => 'contains_any email,number,search,tel,text,url',
            ],
            'section' => 'input_mask',
        ], 'input_mask_class' => [
            'display' => $overrides['input_mask_class']['display'] ?? 'Input Mask Class',
            'type' => $overrides['input_mask_class']['type'] ?? 'select',
            'instructions' => $overrides['input_mask_class']['instructions'] ?? 'Select the type of input mask to apply. Each class provides different validation and formatting behavior.',
            'options' => $overrides['input_mask_class']['options'] ?? [
                'MaskedDate' => 'Date',
                'MaskedDynamic' => 'Dynamic',
                'MaskedEnum' => 'Enum',
                'MaskedNumber' => 'Number',
                'MaskedPattern' => 'Pattern',
                'MaskedRange' => 'Range',
                'MaskedRegExp' => 'Regular Expression',
                'RepeatBlock' => 'Repeat Block',
            ],
            'default' => $overrides['input_mask_class']['default'] ?? 'MaskedPattern',
            'if' => [
                'input_mask' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_mask' => [
            'display' => $overrides['input_mask_mask']['display'] ?? 'Core Mask Definition (mask attribute)',
            'type' => $overrides['input_mask_mask']['type'] ?? 'hidden',
            'instructions' => $overrides['input_mask_mask']['instructions'] ?? 'Defines the core mask structure and behavior.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedDynamic,MaskedPattern,MaskedRegExp', // `mask` is always `Number` for `MaskedNumber`; always `Date` for `MaskedDate`; dynamically generated for `MaskedEnum`, `MaskedRange`, `RepeatBlock`; their values are handled in resources/js/components/fieldtypes/mixins/IMaskMixin.js
            ],
            'section' => 'input_mask',
        ], 'input_mask_mask_MaskedDynamic' => [
            'display' => $overrides['input_mask_mask_MaskedDynamic']['display'] ?? 'Core Mask Definition (mask attribute; MaskedDynamic class)',
            'type' => $overrides['input_mask_mask_MaskedDynamic']['type'] ?? 'code',
            'mode' => $overrides['input_mask_mask_MaskedDynamic']['mode'] ?? 'javascript',
            'mode_selectable' => $overrides['input_mask_mask_MaskedDynamic']['mode_selectable'] ?? false,
            'indent_type' => $overrides['input_mask_mask_MaskedDynamic']['indent_type'] ?? 'spaces',
            'instructions' => $overrides['input_mask_mask_MaskedDynamic']['instructions'] ?? <<<'EOT'
                            Defines the core mask structure and behavior.

                            Provide a JavaScript array of mask configurations. Each element can be any valid mask configuration.

                            **Example:**
                            ```javascript
                            [
                              { mask: '0000-0000-0000-0000', placeholderChar: '_' },
                              { mask: Number, scale: 2 },
                              { mask: Date, pattern: 'd{.}`m{.}`Y' },
                              { mask: /[0-9a-f]/i, placeholderChar: '_' }
                            ]
                            ```

                            **Note:** The `dispatch` function will automatically choose the most appropriate mask based on the input value.
                            EOT,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedDynamic',
            ],
            'section' => 'input_mask',
        ], 'input_mask_mask_MaskedPattern' => [
            'display' => $overrides['input_mask_mask_MaskedPattern']['display'] ?? 'Core Mask Definition (mask attribute; MaskedPattern class)',
            'type' => $overrides['input_mask_mask_MaskedPattern']['type'] ?? 'textarea',
            'instructions' => $overrides['input_mask_mask_MaskedPattern']['instructions'] ?? <<<EOT
                            Defines the core mask structure and behavior.

                            Provide a pattern string that defines the input format.

                            **Pattern Characters:**
                            - `a`: Any letter (A-Z, a-z)
                            - `0`: Any digit (0-9)
                            - `#`: Any letter or digit (A-Z, a-z, 0-9)
                            - `@`: Any letter, digit, dash, or underscore (A-Z, a-z, 0-9, -, _)
                            - `*`: Any character

                            **Special Syntax Characters:**
                            - `[]`: Makes the enclosed input optional
                            - `{}`: Includes the enclosed fixed part in the unmasked value
                            - `` ` ``: Prevents symbols from shifting back
                            - `\`: Escapes pattern characters to treat them as fixed characters (e.g., `\0` for literal "0")

                            **Examples:**
                            - `0000-0000-0000-0000` (accepts values like `1234567890123456`, masking it as `1234-5678-9012-3456`)
                            - `(000) 000-0000` (accepts values like `5551234567`, masking it as `(555) 123-4567`)
                            - `aa-0000` (accepts values like `Ab-1234`, masking it as `Ab-1234`)
                            - `###_####` (accepts values like `aBc1234`, masking it as `aBc_1234`)
                            - ``###_`####`` (accepts values like `aBc1234`, masking it as `aBc_1234`; deleting the `c` will not shift the `1` back to the left of the underscore)
                            - `@@@@` (accepts values like `_A-1`)
                            - `00000[-0000]` (accepts values like `12345` and `12345-6789`, masking them as `12345` and `12345-6789`, respectively)
                            - `+{7}(000)000-00-00` (accepts values like `1234567890`, includes the `7` in the _unmasked_ value as `1234567890`, masking it as `+7(123)456-78-90`)
                            - `0 s\almon` (accepts values like `9`, masking it as `9 salmon`)
                            EOT,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedPattern',
            ],
            'section' => 'input_mask',
        ], 'input_mask_mask_MaskedRegExp' => [
            'display' => $overrides['input_mask_mask_MaskedRegExp']['display'] ?? 'Core Mask Definition (mask attribute; MaskedRegExp class)',
            'type' => $overrides['input_mask_mask_MaskedRegExp']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_mask_MaskedRegExp']['instructions'] ?? <<<'EOT'
                            Defines the core mask structure and behavior.

                            Provide a regular expression pattern. You can include the slashes or not - both formats are supported.

                            **Examples:**
                            ```
                            [A-Za-z]+
                            /^[A-Za-z]+$/
                            [0-9a-fA-F]{6}
                            ```

                            **Note:** The pattern will be used to validate input. Only characters that match the pattern will be accepted.
                            EOT,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedRegExp',
            ],
            'section' => 'input_mask',
        ], 'input_mask_store_unmasked' => [
            'display' => $overrides['input_mask_store_unmasked']['display'] ?? 'Store Unmasked Value',
            'type' => $overrides['input_mask_store_unmasked']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_store_unmasked']['instructions'] ?? <<<'EOT'
                            Controls whether to store the masked (formatted) value or the unmasked (raw) value.

                            **When enabled (true):**
                            - Stores the raw, unmasked value (e.g., `1234.5` for a number)
                            - Preserves the original data without formatting
                            - Useful for data processing, calculations, or when you need the raw value

                            **When disabled (false):**
                            - Stores the formatted, masked value (e.g., `1,234.50` for a number)
                            - Preserves the exact display format
                            - Useful for consistent display or when formatting is part of the data

                            **Examples:**
                            - Phone: `(555) 123-4567` (masked) vs `5551234567` (unmasked)
                            - Number: `1,234.50` (masked) vs `1234.5` (unmasked)
                            - Date: `12/31/2024` (masked) vs `2024-12-31` (unmasked)
                            EOT,
            'default' => $overrides['input_mask_store_unmasked']['default'] ?? false,
            'if' => [
                'input_mask' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_prepend_text_enabled' => [
            'display' => $overrides['input_mask_prepend_text_enabled']['display'] ?? 'Prepend Text to the Display Value',
            'type' => $overrides['input_mask_prepend_text_enabled']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_prepend_text_enabled']['instructions'] ?? 'When enabled, adds text before the masked value in the display. This is useful for adding currency symbols, prefixes, or other text that should appear before the input value.',
            'default' => $overrides['input_mask_prepend_text_enabled']['default'] ?? false,
            'if' => [
                'input_mask' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_prepend_text' => [
            'display' => $overrides['input_mask_prepend_text']['display'] ?? 'Prepended Text',
            'type' => $overrides['input_mask_prepend_text']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_prepend_text']['instructions'] ?? 'The text to prepend to the display value. This text will appear before the masked value in the input field.',
            'default' => $overrides['input_mask_prepend_text']['default'] ?? null,
            'if' => [
                'input_mask' => 'equals true',
                'input_mask_prepend_text_enabled' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_append_text_enabled' => [
            'display' => $overrides['input_mask_append_text_enabled']['display'] ?? 'Append Text to the Display Value',
            'type' => $overrides['input_mask_append_text_enabled']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_append_text_enabled']['instructions'] ?? 'When enabled, adds text after the masked value in the display. This is useful for adding suffixes, units, or other text that should appear after the input value.',
            'default' => $overrides['input_mask_append_text_enabled']['default'] ?? false,
            'if' => [
                'input_mask' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_append_text' => [
            'display' => $overrides['input_mask_append_text']['display'] ?? 'Appended Text',
            'type' => $overrides['input_mask_append_text']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_append_text']['instructions'] ?? 'The text to append to the display value. This text will appear after the masked value in the input field.',
            'default' => $overrides['input_mask_append_text']['default'] ?? null,
            'if' => [
                'input_mask' => 'equals true',
                'input_mask_append_text_enabled' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_autofix' => [
            'display' => $overrides['input_mask_autofix']['display'] ?? 'Automatically Correct Invalid Input (autofix attribute)',
            'type' => $overrides['input_mask_autofix']['type'] ?? 'radio',
            'instructions' => $overrides['input_mask_autofix']['instructions'] ?? <<<'EOT'
                            **Date:** Validates and corrects date components.

                            **Dynamic:** Delegates autofix behavior to the currently active mask.

                            **Enum:** Filters input to match valid enum values and auto-completes partial matches.

                            **Function:** Uses custom function to determine valid input and applies corrections.

                            **Number:** Adjusts numeric values to fit within min/max bounds.

                            **Pattern:** Replaces invalid characters with valid pattern matches.

                            **Range:** Adjusts values to fit within the specified range bounds.

                            **Regular Expression:** Filters input to match the regular expression pattern.

                            **Repeat Block:** Applies autofix to each repeated block in the pattern.
                            EOT,
            'options' => $overrides['input_mask_autofix']['options'] ?? [
                'true' => 'Enabled',
                'false' => 'Disabled',
                'pad' => 'Pad the Value With Leading Zeros to Match the Pattern Length (Range only)',
            ],
            'cast_booleans' => $overrides['input_mask_autofix']['cast_booleans'] ?? true,
            'default' => $overrides['input_mask_autofix']['default'] ?? 'false',
            'if' => [
                'input_mask' => 'equals true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_blocks' => [
            'display' => $overrides['input_mask_blocks']['display'] ?? 'Object Defining Pattern Blocks for Mask Construction (blocks attribute)',
            'type' => $overrides['input_mask_blocks']['type'] ?? 'code',
            'mode' => $overrides['input_mask_blocks']['mode'] ?? 'javascript',
            'mode_selectable' => $overrides['input_mask_blocks']['mode_selectable'] ?? false,
            'indent_type' => $overrides['input_mask_blocks']['indent_type'] ?? 'spaces',
            'instructions' => $overrides['input_mask_blocks']['instructions'] ?? <<<'EOT'
                            **Date:** Uses predefined blocks for day (`d`), month (`m`), and year (`Y`) with range constraints.

                            **Enum:** Uses generated blocks based on enum values and lengths.

                            **Pattern:** Defines custom pattern blocks for mask construction.

                            **Range:** Uses generated blocks based on range bounds and maxLength.

                            **Repeat Block:** Uses blocks for repeating pattern structures.
                            EOT,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedPattern,MaskedDate,MaskedEnum,MaskedRange,RepeatBlock',
            ],
            'section' => 'input_mask',
        ], 'input_mask_compiledMasks' => [
            'display' => $overrides['input_mask_compiledMasks']['display'] ?? 'Mask Objects That Can Be Switched Between Based on Input (compiledMasks attribute)',
            'type' => $overrides['input_mask_compiledMasks']['type'] ?? 'code',
            'mode' => $overrides['input_mask_compiledMasks']['mode'] ?? 'javascript',
            'mode_selectable' => $overrides['input_mask_compiledMasks']['mode_selectable'] ?? false,
            'indent_type' => $overrides['input_mask_compiledMasks']['indent_type'] ?? 'spaces',
            'instructions' => $overrides['input_mask_compiledMasks']['instructions'] ?? 'Array of compiled mask instances that can be dynamically switched between based on input content.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedDynamic',
            ],
            'section' => 'input_mask',
        ], 'input_mask_currentMask' => [
            'display' => $overrides['input_mask_currentMask']['display'] ?? 'Mask That is Currently Processing Input (currentMask attribute)',
            'type' => $overrides['input_mask_currentMask']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_currentMask']['instructions'] ?? 'Reference to the currently active mask instance that is processing input.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedDynamic',
            ],
            'section' => 'input_mask',
        ], 'input_mask_definitions' => [
            'display' => $overrides['input_mask_definitions']['display'] ?? 'Object Defining Input Definitions for Pattern Masks (definitions attribute)',
            'type' => $overrides['input_mask_definitions']['type'] ?? 'code',
            'mode' => $overrides['input_mask_definitions']['mode'] ?? 'javascript',
            'mode_selectable' => $overrides['input_mask_definitions']['mode_selectable'] ?? false,
            'indent_type' => $overrides['input_mask_definitions']['indent_type'] ?? 'spaces',
            'instructions' => $overrides['input_mask_definitions']['instructions'] ?? 'A JSON object mapping each pattern character to a regular-expression source string. Parsed as JSON (not executed) — each value is compiled to a RegExp. Example: `{"#": "[0-9X]"}` makes the `#` slot accept a digit or an uppercase X.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedPattern,MaskedDate,MaskedEnum,MaskedRange,RepeatBlock',
            ],
            'section' => 'input_mask',
        ], 'input_mask_displayChar' => [
            'display' => $overrides['input_mask_displayChar']['display'] ?? 'Character Displayed for Filled Input Positions (displayChar attribute)',
            'type' => $overrides['input_mask_displayChar']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_displayChar']['instructions'] ?? 'Used by pattern-based classes to show a different character when input is complete vs. placeholder.',
            'placeholder' => $overrides['input_mask_displayChar']['placeholder'] ?? 'e.g., #',
            'character_limit' => $overrides['input_mask_displayChar']['character_limit'] ?? 1,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedPattern,MaskedDate,MaskedEnum,MaskedRange,RepeatBlock',
            ],
            'section' => 'input_mask',
        ], 'input_mask_eager' => [
            'display' => $overrides['input_mask_eager']['display'] ?? 'Eager Placeholder Behavior (eager attribute)',
            'type' => $overrides['input_mask_eager']['type'] ?? 'radio',
            'instructions' => $overrides['input_mask_eager']['instructions'] ?? 'When enabled, placeholders are shown immediately. For `remove` mode, removes placeholders when not needed. For `append` mode, appends placeholders eagerly.',
            'options' => $overrides['input_mask_eager']['options'] ?? [
                'true' => 'Enabled',
                'false' => 'Disabled',
                'remove' => 'Remove Placeholders When Not Needed',
                'append' => 'Append Placeholders Eagerly',
            ],
            'cast_booleans' => $overrides['input_mask_eager']['cast_booleans'] ?? true,
            'default' => $overrides['input_mask_eager']['default'] ?? 'false',
            'if' => [
                'input_mask' => 'true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_enum' => [
            'display' => $overrides['input_mask_enum']['display'] ?? 'Valid Enum Values (enum attribute)',
            'type' => $overrides['input_mask_enum']['type'] ?? 'list',
            'instructions' => $overrides['input_mask_enum']['instructions'] ?? 'Array of valid enum values that the mask will accept and validate against. Input must match one of these values.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedEnum',
            ],
            'section' => 'input_mask',
        ], 'input_mask_exposeMask' => [
            'display' => $overrides['input_mask_exposeMask']['display'] ?? 'Exposed Mask Instance (exposeMask attribute)',
            'type' => $overrides['input_mask_exposeMask']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_exposeMask']['instructions'] ?? 'Provides access to the currently active mask instance for external manipulation and control.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedDynamic',
            ],
            'section' => 'input_mask',
        ], 'input_mask_from' => [
            'display' => $overrides['input_mask_from']['display'] ?? 'Minimum Bound for Range Validation (from attribute)',
            'type' => $overrides['input_mask_from']['type'] ?? 'float',
            'instructions' => $overrides['input_mask_from']['instructions'] ?? 'Defines the minimum acceptable value in the range. Input values below this will be rejected.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedRange',
            ],
            'section' => 'input_mask',
        ], 'input_mask_lazy' => [
            'display' => $overrides['input_mask_lazy']['display'] ?? 'Only Display Placeholder When Needed (lazy attribute)',
            'type' => $overrides['input_mask_lazy']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_lazy']['instructions'] ?? 'When enabled, placeholders are only shown when needed. When disabled, placeholders are always visible.',
            'default' => $overrides['input_mask_lazy']['default'] ?? true,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedPattern,MaskedDate,MaskedEnum,MaskedRange,RepeatBlock',
            ],
            'section' => 'input_mask',
        ], 'input_mask_mapToRadix' => [
            'display' => $overrides['input_mask_mapToRadix']['display'] ?? 'Characters That Should Be Mapped to the Decimal Separator (mapToRadix attribute)',
            'type' => $overrides['input_mask_mapToRadix']['type'] ?? 'list',
            'instructions' => $overrides['input_mask_mapToRadix']['instructions'] ?? 'Array of characters that should be treated as decimal separators. Useful for locale-specific number formatting (e.g., comma vs period).',
            'character_limit' => $overrides['input_mask_mapToRadix']['character_limit'] ?? 1,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber',
            ],
            'section' => 'input_mask',
        ], 'input_mask_max' => [
            'display' => $overrides['input_mask_max']['display'] ?? 'Maximum Bound for Validation (max attribute)',
            'type' => $overrides['input_mask_max']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_max']['instructions'] ?? <<<'EOT'
                            **Date:** Date object defining the latest acceptable date. Enter the date in ISO format (YYYY-MM-DD) or any valid JavaScript date string (e.g., `2024-12-31`, `Dec 31, 2024`, `2024/12/31`).

                            **Number:** Integer or float defining the maximum acceptable numeric value.

                            **Range:** Integer or float defining the maximum acceptable value in the range.
                            EOT,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber,MaskedDate,MaskedRange',
            ],
            'section' => 'input_mask',
        ], 'input_mask_maxLength' => [
            'display' => $overrides['input_mask_maxLength']['display'] ?? 'Maximum Length Constraint (maxLength attribute)',
            'type' => $overrides['input_mask_maxLength']['type'] ?? 'float',
            'instructions' => $overrides['input_mask_maxLength']['instructions'] ?? 'Defines the maximum length for range values. Shorter numbers will be padded with leading zeros to match this length.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedRange',
            ],
            'section' => 'input_mask',
        ], 'input_mask_min' => [
            'display' => $overrides['input_mask_min']['display'] ?? 'Minimum Bound for Validation (min attribute)',
            'type' => $overrides['input_mask_min']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_min']['instructions'] ?? <<<'EOT'
                            **Number:** Integer or float defining the minimum acceptable numeric value.

                            **Date:** Date object defining the earliest acceptable date. Enter the date in ISO format (YYYY-MM-DD) or any valid JavaScript date string (e.g., `2024-01-01`, `Jan 1, 2024`, `2024/01/01`).

                            **Range:** Integer or float defining the minimum acceptable value in the range.
                            EOT,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber,MaskedDate,MaskedRange',
            ],
            'section' => 'input_mask',
        ], 'input_mask_normalizeZeros' => [
            'display' => $overrides['input_mask_normalizeZeros']['display'] ?? 'Normalize Zeros (normalizeZeros attribute)',
            'type' => $overrides['input_mask_normalizeZeros']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_normalizeZeros']['instructions'] ?? 'When enabled, leading and trailing zeros are removed from the input.',
            'default' => $overrides['input_mask_normalizeZeros']['default'] ?? false,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber',
            ],
            'section' => 'input_mask',
        ], 'input_mask_overwrite' => [
            'display' => $overrides['input_mask_overwrite']['display'] ?? 'Character Overwriting (overwrite attribute)',
            'type' => $overrides['input_mask_overwrite']['type'] ?? 'radio',
            'instructions' => $overrides['input_mask_overwrite']['instructions'] ?? 'When enabled, new characters overwrite existing ones. `shift` mode shifts existing characters instead of overwriting.',
            'options' => $overrides['input_mask_overwrite']['options'] ?? [
                'true' => 'Enabled',
                'false' => 'Disabled',
                'shift' => 'Shift Existing Characters Instead of Overwriting',
            ],
            'cast_booleans' => $overrides['input_mask_overwrite']['cast_booleans'] ?? true,
            'default' => $overrides['input_mask_overwrite']['default'] ?? 'false',
            'if' => [
                'input_mask' => 'true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_padFractionalZeros' => [
            'display' => $overrides['input_mask_padFractionalZeros']['display'] ?? 'Pad Fractional Zeros (padFractionalZeros attribute)',
            'type' => $overrides['input_mask_padFractionalZeros']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_padFractionalZeros']['instructions'] ?? 'When enabled, pads trailing zeros after the decimal point at the end of editing.',
            'default' => $overrides['input_mask_padFractionalZeros']['default'] ?? false,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber',
            ],
            'section' => 'input_mask',
        ], 'input_mask_parent' => [
            'display' => $overrides['input_mask_parent']['display'] ?? 'Parent Masked Instance (parent attribute)',
            'type' => $overrides['input_mask_parent']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_parent']['instructions'] ?? 'Reference to parent masked instance for nested mask structures and delegation of operations.',
            'if' => [
                'input_mask' => 'true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_pattern' => [
            'display' => $overrides['input_mask_pattern']['display'] ?? 'Date Pattern (pattern attribute)',
            'type' => $overrides['input_mask_pattern']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_pattern']['instructions'] ?? 'Used to define the date input structure.',
            'placeholder' => $overrides['input_mask_pattern']['placeholder'] ?? 'e.g., d{.}`m{.}`Y',
            'default' => $overrides['input_mask_pattern']['default'] ?? 'd{.}`m{.}`Y',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedDate',
            ],
            'section' => 'input_mask',
        ], 'input_mask_placeholderChar' => [
            'display' => $overrides['input_mask_placeholderChar']['display'] ?? 'Placeholder Character (placeholderChar attribute)',
            'type' => $overrides['input_mask_placeholderChar']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_placeholderChar']['instructions'] ?? 'Used by to indicate where input is expected.',
            'placeholder' => $overrides['input_mask_placeholderChar']['placeholder'] ?? 'e.g., _',
            'default' => $overrides['input_mask_placeholderChar']['default'] ?? '_',
            'character_limit' => $overrides['input_mask_placeholderChar']['character_limit'] ?? 1,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedPattern,MaskedDate,MaskedEnum,MaskedRange,RepeatBlock',
            ],
            'section' => 'input_mask',
        ], 'input_mask_prepare_case' => [
            'display' => $overrides['input_mask_prepare_case']['display'] ?? 'Transform Case While Typing (prepareChar attribute)',
            'type' => $overrides['input_mask_prepare_case']['type'] ?? 'select',
            'instructions' => $overrides['input_mask_prepare_case']['instructions'] ?? 'Automatically transform each character to upper- or lowercase as it is typed into the mask. This is a declarative replacement for the former free-form `prepareChar` JavaScript — no code is executed.',
            'options' => $overrides['input_mask_prepare_case']['options'] ?? [
                'uppercase' => 'Uppercase',
                'lowercase' => 'Lowercase',
            ],
            'clearable' => $overrides['input_mask_prepare_case']['clearable'] ?? true,
            'if' => [
                'input_mask' => 'true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_radix' => [
            'display' => $overrides['input_mask_radix']['display'] ?? 'Decimal Separator Character (radix attribute)',
            'type' => $overrides['input_mask_radix']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_radix']['instructions'] ?? 'Defines the character that separates integer and fractional parts of numbers.',
            'placeholder' => $overrides['input_mask_radix']['placeholder'] ?? 'e.g., .',
            'default' => $overrides['input_mask_radix']['default'] ?? '.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber',
            ],
            'section' => 'input_mask',
        ], 'input_mask_repeat' => [
            'display' => $overrides['input_mask_repeat']['display'] ?? 'Repetition Configuration (repeat attribute)',
            'type' => $overrides['input_mask_repeat']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_repeat']['instructions'] ?? 'Can be a number (fixed repetitions) or `[min, max]` array (variable repetitions). Controls how many times a block pattern repeats.',
            'placeholder' => $overrides['input_mask_repeat']['placeholder'] ?? 'e.g., 3 or [1, 5]',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any RepeatBlock',
            ],
            'section' => 'input_mask',
        ], 'input_mask_scale' => [
            'display' => $overrides['input_mask_scale']['display'] ?? 'Number of Digits After Decimal Point (scale attribute)',
            'type' => $overrides['input_mask_scale']['type'] ?? 'float',
            'instructions' => $overrides['input_mask_scale']['instructions'] ?? 'Controls the precision of decimal numbers.',
            'placeholder' => $overrides['input_mask_scale']['placeholder'] ?? 'e.g., 0',
            'default' => $overrides['input_mask_scale']['default'] ?? 0,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber',
            ],
            'section' => 'input_mask',
        ], 'input_mask_skipInvalid' => [
            'display' => $overrides['input_mask_skipInvalid']['display'] ?? 'Skip Invalid Characters (skipInvalid attribute)',
            'type' => $overrides['input_mask_skipInvalid']['type'] ?? 'toggle',
            'instructions' => $overrides['input_mask_skipInvalid']['instructions'] ?? "When enabled, characters that don't match the mask pattern are ignored rather than causing errors.",
            'default' => $overrides['input_mask_skipInvalid']['default'] ?? true,
            'if' => [
                'input_mask' => 'true',
            ],
            'section' => 'input_mask',
        ], 'input_mask_thousandsSeparator' => [
            'display' => $overrides['input_mask_thousandsSeparator']['display'] ?? 'Character Used to Separate Thousands (thousandsSeparator attribute)',
            'type' => $overrides['input_mask_thousandsSeparator']['type'] ?? 'text',
            'instructions' => $overrides['input_mask_thousandsSeparator']['instructions'] ?? 'Used for number formatting with locale-specific separators.',
            'placeholder' => $overrides['input_mask_thousandsSeparator']['placeholder'] ?? 'e.g., ,',
            'default' => $overrides['input_mask_thousandsSeparator']['default'] ?? ',',
            'character_limit' => $overrides['input_mask_thousandsSeparator']['character_limit'] ?? 1,
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedNumber',
            ],
            'section' => 'input_mask',
        ], 'input_mask_to' => [
            'display' => $overrides['input_mask_to']['display'] ?? 'Maximum Bound for Range Validation (to attribute)',
            'type' => $overrides['input_mask_to']['type'] ?? 'float',
            'instructions' => $overrides['input_mask_to']['instructions'] ?? 'Defines the maximum acceptable value in the range. Input values above this will be rejected.',
            'if' => [
                'input_mask' => 'true',
                'input_mask_class' => 'contains_any MaskedRange',
            ],
            'section' => 'input_mask',
        ]];
    }

    /**
     * Apply per-field overrides and child-driven unset/hide filtering.
     *
     * @param  array<string, array<string, mixed>>  $fields  The assembled field definitions
     * @param  array<string, array<string, mixed>>  $overrides  Per-field attribute overrides
     * @return array<string, array<string, mixed>>
     */
    protected function applyConfigFieldFilters(
        array $fields,
        array $overrides
    ): array {
        // ---

        // Loop through config field overrides to apply them
        foreach ($overrides as $fieldName => $overrideAttrs) {
            // Check if the field exists in the fields array
            if (isset($fields[$fieldName])) {
                // Loop through override attributes to apply them
                foreach ($overrideAttrs as $attr => $value) {
                    // Apply override attribute to field
                    $fields[$fieldName][$attr] = $value;
                }
            }
        }

        // ---
        // Apply field filtering based on child class implementations
        // ---

        // Get list of fields to unset from child class
        $unsetFields = $this->getUnsetConfigFields();

        // Get list of fields to hide from child class
        $hiddenFields = $this->getHiddenConfigFields();

        // ---
        // Remove fields that should be unset
        // ---

        // Loop through fields that should be unset
        foreach ($unsetFields as $fieldName) {
            // Remove field from fields array
            unset($fields[$fieldName]);
        }

        // ---
        // Hide fields that should be hidden
        // ---

        // Loop through fields that should be hidden
        foreach ($hiddenFields as $fieldName) {
            // Check if the field exists in the fields array
            if (isset($fields[$fieldName])) {
                // Set the field type to hidden
                $fields[$fieldName]['type'] = 'hidden';

                // Remove attributes that are not needed for hidden fields
                unset(
                    $fields[$fieldName]['options'], // Remove the options attribute if it exists
                    $fields[$fieldName]['validate'], // Remove the validate attribute if it exists
                    $fields[$fieldName]['visibility'] // Remove the visibility attribute if it exists
                );
            }
        }

        // Return the filtered fields
        return $fields;
    }

    /**
     * Group the field definitions into ordered Control Panel sections.
     *
     * Fields are bucketed by their `section` attribute (defaulting to `behavior`,
     * with any unrecognized section also falling back to `behavior`), except
     * `hidden`-typed fields which are always collected into the hidden section.
     * Sections are emitted in a fixed display order, omitting any with no fields.
     *
     * @param  array<string, array<string, mixed>>  $fields  The filtered field definitions
     * @return array<int, array<string, mixed>>
     */
    protected function groupConfigFieldsIntoSections(array $fields): array
    {
        // Section display metadata, keyed in Control Panel render order
        $sections = [
            'behavior' => [
                'display' => 'Input Behavior',
                'instructions' => 'Settings that control user interactions, editing, and dynamic responses.',
            ],
            'identification' => [
                'display' => 'Input Identification',
                'instructions' => 'Options to uniquely identify and classify the input for scripting or styling.',
            ],
            'value_and_state' => [
                'display' => 'Input Value and State',
                'instructions' => 'Configurations for initial values, types, and default states of the input.',
            ],
            'form' => [
                'display' => 'Form Association and Submission',
                'instructions' => 'Links the input to forms and customizes data submission behaviors.',
            ],
            'input_mask' => [
                'display' => 'Input Mask',
            ],
            'constraints' => [
                'display' => 'Constraints',
                'instructions' => 'Rules to enforce data format, range, and length requirements.',
            ],
            'suggestions' => [
                'display' => 'Autocomplete and Suggestions',
                'instructions' => 'Features for providing or managing input suggestions and autocompletion.',
            ],
            'accessibility' => [
                'display' => 'Accessibility',
                'instructions' => 'Enhancements for better usability across devices, languages, and users.',
            ],
            'appearance' => [
                'display' => 'Appearance',
                'instructions' => 'Visual properties like dimensions, visibility, and editable appearance.',
            ],
            'microdata' => [
                'display' => 'Microdata',
                'instructions' => 'Markup for embedding structured data and semantic information.',
            ],
            'advanced' => [
                'display' => 'Advanced Features',
                'instructions' => 'Advanced options for extended functionality like Web Components.',
            ],
            'experimental' => [
                'display' => 'Experimental and Limited Availability',
                'instructions' => 'Features with partial browser support or restrictions.',
            ],
            'antlers' => [
                'display' => 'Antlers',
                'instructions' => 'Features for using Antlers templates within the input.',
            ],
            'hidden' => [],
        ];

        // Initialize one empty bucket per section
        $buckets = \array_fill_keys(
            \array_keys($sections),
            []
        );

        // Sections a field may target via its `section` attribute; `hidden` is
        // reserved for hidden-typed fields and is never matched by name
        $sectionableKeys = \array_diff(
            \array_keys($sections),
            ['hidden']
        );

        // Split the fields into section buckets
        foreach ($fields as $key => $field) {
            // Hidden-typed fields always collect into the hidden section
            if (($field['type'] ?? null) === 'hidden') {
                $buckets['hidden'][$key] = $field;

                continue;
            }

            // Resolve the target section, defaulting to behavior
            $section = $field['section'] ?? 'behavior';

            // Unrecognized sections fall back to behavior
            if (! \in_array($section, $sectionableKeys, true)) {
                $section = 'behavior';
            }

            // Add the field to its section bucket
            $buckets[$section][$key] = $field;
        }

        // Initialize the result array
        $result = [];

        // Emit each non-empty section in display order
        foreach ($sections as $section => $meta) {
            // Skip sections that received no fields
            if (empty($buckets[$section])) {
                continue;
            }

            // Append the section metadata together with its fields
            $result[] = $meta + ['fields' => $buckets[$section]];
        }

        // Return the grouped sections
        return $result;
    }
}
