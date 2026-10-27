<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Dun & Bradstreet Data Universal Numbering
 * System (D-U-N-S) Numbers.
 *
 * This rule ensures D-U-N-S numbers conform to the standard format of exactly nine
 * digits (e.g., `123456789`). It rejects invalid formats, non-numeric values, or
 * numbers with incorrect digit counts (fewer or more than nine digits).
 *
 * The regular expression pattern is /^\d{9}$/
 *
 * This pattern ensures:
 *   - Exactly nine digits (0-9),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class DunsNumber extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the D-U-N-S number format using a
     * regular expression.
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
        // Check if the value matches the D-U-N-S number format
        if (
            ! \preg_match(
                '/^\d{9}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.duns_number'));
        }
    }
}
