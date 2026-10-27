<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseSubfieldsRule;
use Closure;

/**
 * Validation rule that requires at least one subfield of the current field to
 * have a value.
 *
 * This rule checks if at least one subfield within the current field group has a
 * non-null, non-empty value.
 *
 * Usage:
 * 'field' => [new SubfieldsAny()]
 */
class SubfieldsAny extends BaseSubfieldsRule
{
    /**
     * Fire on empty values so a group with all-blank subfields still fails.
     *
     * Without this, Laravel's `InvokableValidationRule::make()` skips the rule
     * when the attribute is missing or empty, defeating the rule's purpose.
     */
    public bool $implicit = true;

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
        // Check if at least one subfield has a value
        // ---

        // Initialize flag to track if at least one subfield has a value
        $hasValue = false;

        // Initialize array to store subfield validation results
        $subfieldResults = [];

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
            $fail(__('statamic::validation.subfields.any.one_subfield', ['attribute' => $attribute]));
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
