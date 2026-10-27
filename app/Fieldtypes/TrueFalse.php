<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fieldtypes\ButtonGroup;
use Statamic\Fieldtypes\ButtonGroup as BaseButtonGroup;

/**
 * True/False fieldtype that extends ButtonGroup to return boolean values.
 *
 * This fieldtype pre-defines two options (True and False) that return actual
 * boolean values (true/false) rather than boolean strings. The options
 * configuration is hidden since the values are hardcoded.
 *
 * @see  ButtonGroup
 */
class TrueFalse extends BaseButtonGroup
{
    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * Resolves to true_false-fieldtype in the CP; that component delegates to
     * button_group-fieldtype so the same UI is used.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'true_false';

    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'True/False';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-toggle';
    }

    /**
     * Get the predefined options for this fieldtype.
     *
     * Returns two options with boolean values (true/false) instead of strings.
     * Optionally uses custom labels and reverses the order based on config options.
     *
     * @return list<array{value: bool, label: string}> Array of option arrays with 'value' and 'label' keys
     */
    #[\Override]
    protected function getOptions(): array
    {
        // ---
        // Get custom labels from config, or use defaults
        // ---

        // Get the custom label for the true option from config
        $labelTrue = $this->config('label_true');

        // Get the custom label for the false option from config
        $labelFalse = $this->config('label_false');

        // ---
        // Use custom labels if set and non-empty, otherwise use defaults
        // ---

        // Use custom label if set and non-empty, otherwise use default 'True'
        $trueLabel = (! empty($labelTrue) && (is_scalar($labelTrue) || $labelTrue instanceof \Stringable)) ? (string) $labelTrue : 'True';

        // Use custom label if set and non-empty, otherwise use default 'False'
        $falseLabel = (! empty($labelFalse) && (is_scalar($labelFalse) || $labelFalse instanceof \Stringable)) ? (string) $labelFalse : 'False';

        // Build the options array with boolean values and custom or default labels
        $options = [
            [
                'value' => true,
                'label' => $trueLabel,
            ],
            [
                'value' => false,
                'label' => $falseLabel,
            ],
        ];

        // ---
        // Reverse the order if the config option is enabled
        // ---

        // Check if reverse order is enabled in config
        if (
            $this->config(
                'reverse_order',
                false
            )
        ) {
            // Reverse the order of options array
            $options = array_reverse($options);
        }

        // Clean up variables to free up memory
        unset(
            $labelTrue,
            $labelFalse,
            $trueLabel,
            $falseLabel
        );

        // Return the options array
        return $options;
    }

    /**
     * Extend the config to remove the options field and keep only clearable
     * and default value fields.
     *
     * @return list<array{display: string, fields: array<string, array<string, mixed>>}> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Return the configuration field items array
        return [
            [
                'display' => __('Options'),
                'fields' => [
                    // Define the custom label for true option config field
                    'label_true' => [
                        'display' => 'Custom Label for True Option',
                        'instructions' => 'Override the default label for the "True" option.',
                        'type' => 'text',
                        'default' => null,
                    ],
                    // Define the custom label for false option config field
                    'label_false' => [
                        'display' => 'Custom Label for False Option',
                        'instructions' => 'Override the default label for the "False" option.',
                        'type' => 'text',
                        'default' => null,
                    ],
                    // Define the clearable config field
                    'clearable' => [
                        'display' => __('Clearable'),
                        'instructions' => __('statamic::fieldtypes.select.config.clearable'),
                        'type' => 'toggle',
                        'default' => false,
                    ],
                    // Define the default value config field
                    'default' => [
                        'display' => __('Default Value'),
                        'instructions' => __('statamic::messages.fields_default_instructions'),
                        'type' => 'toggle',
                        'default' => false,
                    ],
                    // Define the reverse order config field
                    'reverse_order' => [
                        'display' => 'Reverse Order',
                        'instructions' => 'Reverse the order of the options (False/True instead of True/False).',
                        'type' => 'toggle',
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Process the value before saving to storage.
     *
     * Converts string representations of booleans to actual boolean values.
     *
     * @param  mixed  $value  The value to process
     * @return ?bool The processed boolean value, or null if cleared
     */
    #[\Override]
    public function process(
        mixed $value
    ): ?bool {
        // Normalize and return the boolean value
        return $this->normalizeBooleanValue($value);
    }

    /**
     * Pre-process the value before it is displayed in the control panel.
     *
     * Ensures stored values are converted to boolean values.
     *
     * @param  mixed  $value  The value to pre-process
     * @return ?bool The pre-processed boolean value, or null
     */
    #[\Override]
    public function preProcess(
        mixed $value
    ): ?bool {
        // Normalize and return the boolean value
        return $this->normalizeBooleanValue($value);
    }

    /**
     * Normalize a value to a boolean or null.
     *
     * Converts string representations of booleans to actual boolean values.
     * Handles null values, string 'true'/'false', boolean values, and other
     * types by casting to boolean.
     *
     * @param  mixed  $value  The value to normalize
     * @return ?bool The normalized boolean value, or null
     */
    private function normalizeBooleanValue(
        mixed $value
    ): ?bool {
        // ---
        // Handle null values (when clearable is enabled)
        // ---

        // Check if the value is null
        if ($value === null) {
            // Return null for null values
            return null;
        }

        // ---
        // Convert string 'true'/'false' to boolean if needed
        // ---

        // Check if value is true (string 'true' or boolean true)
        if (
            $value === 'true'
            || $value === true
        ) {
            // Return true for true values
            return true;
        }

        // Check if value is false (string 'false' or boolean false)
        if (
            $value === 'false'
            || $value === false
        ) {
            // Return false for false values
            return false;
        }

        // For any other value, convert to boolean
        return (bool) $value;
    }
}
