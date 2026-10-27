<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Legal Entity Identifiers (LEIs).
 *
 * This rule ensures LEIs conform to the standard format of exactly 20 characters:
 * the first 18 characters must be uppercase alphanumeric (A-Z, 0-9), followed by
 * two digits (0-9) (e.g., `ABC123XYZ45678901234`). It rejects invalid formats,
 * values with incorrect character counts, lowercase characters in the first 18
 * positions, or non-digit characters in the last two positions.
 *
 * The regular expression pattern is /^[0-9A-Z]{18}[0-9]{2}$/.
 *
 * This pattern ensures:
 *   - Exactly 18 uppercase alphanumeric characters ([0-9A-Z]{18}),
 *   - Followed by exactly two digits ([0-9]{2}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class LegalEntityIdentifier extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the LEI format using a regular
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
        // Check if the value matches the LEI format
        if (
            ! \preg_match(
                '/^[0-9A-Z]{18}\d{2}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.legal_entity_identifier'));
        }
    }
}
