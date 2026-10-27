<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for Unique Entity ID (UEI) from SAM.gov.
 *
 * This rule ensures UEIs conform to the standard format of exactly 12
 * alphanumeric characters (e.g., `J1XYZ12345A1`). It rejects invalid
 * formats or values with incorrect length or character set.
 *
 * The regular expression pattern is /^[0-9A-Za-z]{12}$/.
 *
 * This pattern ensures:
 *   - Exactly 12 characters,
 *   - Only digits (0-9) and letters (A-Z, a-z) are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class Uei extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the UEI format using a
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
        // Check if the value matches the UEI format
        if (
            ! \preg_match(
                '/^[0-9A-Za-z]{12}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.uei'));
        }
    }
}
