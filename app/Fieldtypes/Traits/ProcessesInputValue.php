<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

use Statamic\Fields\Field;

/**
 * Handles value processing and validation for input-based fieldtypes.
 *
 *   - Normalizes and formats values on save/display and exposes pattern-derived
 *     validation rules.
 *   - Routes save/pre-validation processing through the shared input-mask aware
 *     pipeline so both paths stay consistent.
 *
 * Extracted from the former monolithic {@see Input} trait (issue UAMS-Web/uams-statamic-archive#873).
 */
trait ProcessesInputValue
{
    /**
     * Provides base validation rules for input-based fieldtypes.
     *
     *   - Adds a regex rule if a 'pattern' is set in the config.
     *   - Intended to be called by child fieldtypes to merge with their own rules.
     *
     * @param  mixed  $value  The value to validate
     * @param  array<string, mixed>  $config  The field config
     * @return list<string> Array of validation rules (Laravel format)
     */
    public function getInputValidationRules(
        mixed $value,
        array $config = []
    ): array {
        // Initialize rules array
        $rules = [];

        // ---
        // If a pattern is set in config, add a regex rule for it
        // ---

        // Check if a pattern is set in config
        if (! empty($config['pattern'])) {
            // ---
            // Extract the pattern and ensure it is wrapped in delimiters
            // ---

            // Get the pattern from config
            $rawPattern = $config['pattern'];
            $pattern = \is_scalar($rawPattern) ? (string) $rawPattern : '';

            // Remove existing delimiters from pattern
            $pattern = \trim(
                $pattern,
                '/'
            );

            // Add regex rule with pattern wrapped in delimiters
            $rules[] = 'regex:/'.$pattern.'/';
        }

        // Return the merged rules
        return $rules;
    }

    /**
     * Extension point: Normalize the value before saving to storage.
     * Child fieldtypes can override to apply package-based normalization.
     *
     * @param  mixed  $value  The value to normalize
     * @param  array<string, mixed>  $config  The field config
     * @return mixed The normalized value
     */
    public function normalizeValue(
        mixed $value,
        array $config = []
    ): mixed {
        // Default: return as-is
        return $value;
    }

    /**
     * Extension point: Format the value for display in the Control Panel or frontend.
     * Child fieldtypes can override to apply package-based formatting.
     *
     * @param  mixed  $value  The value to format
     * @param  array<string, mixed>  $config  The field config
     * @return mixed The formatted value
     */
    public function formatValueForDisplay(
        mixed $value,
        array $config = []
    ): mixed {
        // Default: return as-is
        return $value;
    }

    /**
     * Processes the value before saving to storage.
     *
     * This method is called by Statamic to normalize the value using the field's
     * config. It ensures the config is hydrated with defaults and then delegates to
     * normalizeValue().
     *
     * @param  mixed  $value  The value to process
     * @return mixed The normalized value
     */
    public function process(
        mixed $value
    ): mixed {
        // Use the helper method to process the value
        return $this->processValueWithConfig($value);
    }

    /**
     * Pre-process the value before validation.
     *
     * This method is called by Statamic before validation to ensure the value
     * is processed consistently with the same logic used during save.
     *
     * @param  mixed  $value  The value to pre-process
     * @return mixed The processed value for validation
     */
    public function preProcessValidatable(
        mixed $value
    ): mixed {
        // Use the helper method to process the value before validation
        return $this->processValueWithConfig($value);
    }

    /**
     * Helper method to process a value using the field's configuration.
     *
     * This method centralizes the value processing logic used by both
     * process() and preProcessValidatable() to ensure consistency.
     *
     * @param  mixed  $value  The value to process
     * @return mixed The processed value
     */
    protected function processValueWithConfig(
        mixed $value
    ): mixed {
        // Retrieve the config from the field instance
        /** @var Field $field */
        $field = $this->field();
        /** @var array<string, mixed> $config */
        $config = $field->config();

        // Normalize config with defaults to ensure all required keys are present
        $config = $this->normalizeConfigWithDefaults($config);

        // Check if input mask is enabled in the field configuration
        if ($this->isInputMaskEnabled($config)) {
            // Get the field handle
            $handle = $field->handle();
            $fieldHandle = is_scalar($handle) ? (string) $handle : '';

            // Process the value for input mask storage
            $processedValue = $this->processInputMaskValue(
                $value,
                $config,
                $fieldHandle
            );

            // Return the processed value
            return $processedValue;
        }

        // Normalize the value using the hydrated config (standard field processing)
        return $this->normalizeValue(
            $value,
            $config
        );
    }
}
