<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Google CID (customer ID) numbers.
 *
 * This rule ensures Google CID numbers conform to the standard format of 14 to 20
 * digits (e.g., `12345678901234` to `12345678901234567890`). It rejects invalid
 * formats, non-numeric values, or numbers with digit counts of fewer than 14 or
 * more than 20 digits.
 *
 * The regular expression pattern is /^\d{14,20}$/
 *
 * This pattern ensures:
 *   - Between 14 and 20 digits inclusive (\d{14,20}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class GoogleCid extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Google CID number format using a
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
        // Check if the value matches the Google CID number format
        if (
            ! \preg_match(
                '/^\d{14,20}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.google_business_profile_cid'));
        }
    }
}
