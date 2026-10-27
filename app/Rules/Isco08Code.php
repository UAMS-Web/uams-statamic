<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating International Standard Classification
 * of Occupations (ISCO-08) Codes.
 *
 * This rule ensures ISCO-08 Codes conform to the standard format of one to four
 * digits (e.g., `1`, `12`, `123`, `1234`). It rejects invalid formats,
 * non-numeric characters, or codes with more than four digits.
 *
 * The regular expression pattern is /^[0-9]{1,4}$/.
 *
 * This pattern ensures:
 *   - One to four digits ([0-9]{1,4}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class Isco08Code extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the ISCO-08 Code format using a
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
        // Check if the value matches the ISCO-08 Code format
        if (
            ! \preg_match(
                '/^\d{1,4}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.isco_08_code'));
        }
    }
}
