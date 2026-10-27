<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating American Hospital Association Hospital
 * Identifiers (AHAIDs).
 *
 * This rule ensures AHAIDs conform to the standard format of a seven-digit numeric
 * string (e.g., `1234567`). It rejects invalid formats, non-numeric values, or
 * strings with lengths other than seven digits.
 *
 * The regular expression pattern is /^\d{7}$/
 *
 * This pattern ensures:
 *   - Exactly seven digits (0-9),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class Ahaid extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the AHAID format using a regular
     * expression.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  string  $value  The prepared string value to validate
     * @param  Closure  $fail  The closure to call if validation fails
     */
    protected function validateValue(
        string $attribute,
        string $value,
        Closure $fail
    ): void {
        // Check if the value matches the AHAID format
        if (
            ! \preg_match(
                '/^\d{7}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.ahaid'));
        }
    }
}
