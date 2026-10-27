<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseSubfieldsRule;
use Closure;

/**
 * Validation rule that requires all subfields of the current field to have values.
 *
 * This rule checks if all subfields within the current field group have non-null,
 * non-empty values.
 *
 * Usage:
 * 'field' => [new SubfieldsAll()]
 */
class SubfieldsAll extends BaseSubfieldsRule
{
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
        // Check if all subfields have values
        // ---

        // Initialize flag to track if all subfields have values
        $allHaveValue = true;

        // Initialize array to store subfield validation results
        $subfieldResults = [];

        // Initialize array to collect empty subfield keys
        $emptySubfields = [];

        // Loop through each subfield in the field data
        foreach ($fieldData as $subfieldKey => $subfieldValue) {
            // Check if the subfield has a non-empty value
            $subfieldHasValue = $this->subfieldHasValue($subfieldValue);

            // Add subfield result to the results array
            $subfieldResults[] = [
                'subfield' => $subfieldKey,
                'value' => $subfieldValue,
                'has_value' => $subfieldHasValue,
            ];

            // Check if the subfield does not have a value
            if (! $subfieldHasValue) {
                // Mark that not all subfields have values
                $allHaveValue = false;

                // Add empty subfield key to the collection
                $emptySubfields[] = $subfieldKey;
            }
        }

        // ---
        // If not all subfields have values, validation fails
        // ---

        // Check if not all subfields have values
        if (! $allHaveValue) {
            // Fail validation with a specific message
            $fail(
                'All subfields of :attribute must have values. Missing values for: '.\implode(
                    ', ',
                    $emptySubfields
                )
            );
        }

        // Clean up variables to free up memory
        unset(
            $fieldData,
            $allHaveValue,
            $subfieldResults,
            $emptySubfields,
            $subfieldKey,
            $subfieldValue,
            $subfieldHasValue
        );
    }
}
