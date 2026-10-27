<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating North American Industry
 * Classification System (NAICS) codes.
 *
 * This rule ensures NAICS codes conform to the standard format of two to six
 * digits (e.g., `12`, `123`, `12345`, `123456`). It rejects invalid formats,
 * non-numeric values, or numbers with digit counts outside the two to six range
 * (fewer than two or more than six digits).
 *
 * The regular expression pattern is /^\d{2,6}$/.
 *
 * This pattern ensures:
 *   - Between two and six digits inclusive (\d{2,6}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class NaicsCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the NAICS code format using a
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
        // Check if the value matches the NAICS code format
        if (
            ! \preg_match(
                '/^\d{2,6}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.naics_code'));
        }
    }
}
