<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating U.S. ZIP Codes, including both five-digit
 * ZIP codes and ZIP+4 codes.
 *
 * This rule ensures ZIP codes conform to the U.S. postal format, allowing:
 * - five-digit ZIP codes (e.g., `12345`),
 * - ZIP+4 codes (e.g., `12345-6789`).
 *
 * It rejects invalid formats, non-numeric values (except the hyphen in ZIP+4),
 * or codes outside the valid ranges (five digits for ZIP, or five digits +
 * hyphen + four digits for ZIP+4).
 *
 * The regular expression pattern is /^\d{5}(?:-\d{4})?$/.
 *
 * This pattern ensures:
 *   - Exactly five digits (\d{5}),
 *   - Optionally followed by a hyphen and exactly four digits (?:-\d{4})?,
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class ZipCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the U.S. ZIP Code format.
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
        /**
         * Check if the value matches the ZIP Code format (5 digits or 5 digits + hyphen +
         * 4 digits) using a regular expression.
         *
         * The pattern /^\d{5}(?:-\d{4})?$/ ensures:
         * - Exactly five digits (\d{5}),
         * - Optionally followed by a hyphen and exactly four digits (?:-\d{4})?,
         * - No extra characters, spaces or special symbols are allowed.
         *
         * If the pattern doesn't match, the validation fails with a specific message.
         */
        if (
            ! \preg_match(
                '/^\d{5}(?:-\d{4})?$/D',
                $value
            )
        ) {
            $fail(__('statamic::validation.zip_code'));
        }
    }
}
