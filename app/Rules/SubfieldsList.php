<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseSubfieldsRule;
use Closure;

/**
 * Validation rule that requires at least one of the specified subfields to have a
 * value.
 *
 * This rule checks if at least one of the specified subfields within the current
 * field group has a non-null, non-empty value.
 *
 * Usage:
 * 'field' => [new SubfieldsList(['subfield1', 'subfield2'])]
 */
class SubfieldsList extends BaseSubfieldsRule
{
    /**
     * Create a new rule instance.
     *
     * @param  array<string>  $subfields  Array of subfield names to check
     */
    public function __construct(
        /**
         * The subfields to check for values.
         */
        protected array $subfields
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // ---
        // Normalize field data to array
        // ---

        // Normalize the value to an array, or return early if it can't be normalized
        $fieldData = $this->normalizeFieldData($value);
        if ($fieldData === null) {
            // Return early since value can't have subfields
            return;
        }

        // ---
        // Check if at least one of the specified subfields has a value
        // ---

        // Initialize flag to track if at least one subfield has a value
        $hasValue = false;

        // Initialize array to store subfield validation results
        $subfieldResults = [];

        // Loop through each specified subfield
        foreach ($this->subfields as $subfieldKey) {
            // Get the subfield value from field data, or null if not present
            $subfieldValue = $fieldData[$subfieldKey] ?? null;

            // Check if the subfield has a non-empty value
            $subfieldHasValue = $this->subfieldHasValue($subfieldValue);

            // Add subfield result to the results array
            $subfieldResults[] = [
                'subfield' => $subfieldKey,
                'value' => $subfieldValue,
                'has_value' => $subfieldHasValue,
            ];

            // Check if the subfield has a value
            if ($subfieldHasValue) {
                // Mark that at least one subfield has a value
                $hasValue = true;

                // Exit loop early since we found a subfield with a value
                break; // Found a subfield with a value, no need to check others
            }
        }

        // ---
        // If no subfield has a value, validation fails
        // ---

        // Check if no subfield has a value
        if (! $hasValue) {
            // Fail validation with a specific message
            $fail(__('statamic::validation.subfields.list.one_of_subfields', [
                'attribute' => $attribute,
                'subfields' => \implode(', ', $this->subfields),
            ]));
        }

        // Clean up variables to free up memory
        unset(
            $fieldData,
            $hasValue,
            $subfieldResults,
            $subfieldKey,
            $subfieldValue,
            $subfieldHasValue
        );
    }
}
