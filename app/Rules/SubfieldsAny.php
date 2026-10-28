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

        // Loop through each subfield in the field data
        foreach ($fieldData as $subfieldValue) {
            // Check if the subfield has a non-empty value
            if ($this->subfieldHasValue($subfieldValue)) {
                // Return early, since one subfield with a value satisfies the rule
                return;
            }
        }

        // ---
        // No subfield has a value, so validation fails
        // ---

        // Fail validation with a specific message
        $fail(__('statamic::validation.subfields.any.one_subfield', ['attribute' => $attribute]));
    }
}
