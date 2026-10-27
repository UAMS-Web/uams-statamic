<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Trait for handling input mask value storage in IMask fields.
 *
 * This trait provides functionality to store values from fields that use IMask
 * for input masking, with support for both masked and unmasked storage.
 */
trait InputMaskValueStorage
{
    /**
     * Check if input mask is enabled in the field configuration.
     *
     * @param  array<string, mixed>  $config  The field configuration
     * @return bool True if input mask is enabled
     */
    protected function isInputMaskEnabled(
        array $config
    ): bool {
        return ($config['input_mask'] ?? false) === true;
    }

    /**
     * Check if a value contains structured data from the Vue component.
     *
     * @param  mixed  $value  The value to check
     * @return bool True if the value contains structured data with masked/unmasked keys
     */
    protected function isStructuredData(
        mixed $value
    ): bool {
        return \is_array($value)  // Must be an array to contain structured data
            && isset(
                $value['masked'],
                $value['unmasked']
            );  // Must have both required keys
    }

    /**
     * Check if a field should store unmasked values based on its configuration and
     * value structure.
     *
     * @param  mixed  $value  The value to check
     * @param  array<string, mixed>  $config  The field configuration
     * @return bool True if unmasked values should be stored
     */
    protected function shouldStoreUnmaskedValues(
        mixed $value,
        array $config
    ): bool {
        // ---
        // Check if this is structured data from our Vue component (contains both masked
        // and unmasked values)
        // ---
        $isStructuredData = $this->isStructuredData($value);

        // Check if field is configured for unmasked storage
        $isConfiguredForUnmasked = ($config['input_mask_store_unmasked'] ?? false) === true;

        // ---
        // Only store unmasked values if we have structured data AND field is configured
        // for it
        // ---
        return $isStructuredData && $isConfiguredForUnmasked;
    }

    /**
     * Process a value that contains both masked and unmasked data.
     *
     * @param  mixed  $value  The value to process (must be array with masked/unmasked)
     * @param  array<string, mixed>  $config  The field configuration
     * @param  string  $fieldHandle  The field handle
     * @return mixed The value to store (masked or unmasked based on config)
     */
    protected function processInputMaskValue(
        mixed $value,
        array $config,
        string $fieldHandle
    ): mixed {
        // ---
        // Check if this is structured data from our Vue component (contains both masked
        // and unmasked values)
        // ---
        $isStructuredData = $this->isStructuredData($value);

        // Check if this is not structured data
        if (! $isStructuredData) {
            // Return the value as-is
            return $this->formatValueForStorage(
                $value,
                $config
            );
        }

        // ---
        // This method is only called with valid structured data from Vue component.
        // Decide which value to store based on field configuration.
        // ---
        /** @var array<string, mixed> $value */
        return $this->shouldStoreUnmaskedValues(
            $value,
            $config
        )
            ? $this->formatValueForStorage(
                $value['unmasked'],
                $config
            )  // Store unmasked value (e.g., `1234.5`)
            : $this->formatValueForStorage(
                $value['masked'],
                $config
            );  // Store masked value (e.g., `1,234.50`)
    }

    /**
     * Format a value for storage.
     *
     * @param  mixed  $value  The value to format
     * @param  array<string, mixed>  $config  The field configuration
     * @return mixed The formatted value for storage
     */
    protected function formatValueForStorage(
        mixed $value,
        array $config
    ): mixed {
        // Check if this is a numeric mask type
        $isNumericMask = \in_array(
            $config['input_mask_class'] ?? '',
            [
                'MaskedNumber',
                'MaskedRange',
            ]
        );

        // Check if this is a numeric mask type and the value is a string
        // Check if the string is numeric
        if (
            $isNumericMask && \is_string($value) && \is_numeric($value)
        ) {
            // Convert the numeric string to a float
            return (float) $value;
        }

        // Default: return as-is
        return $value;
    }
}
