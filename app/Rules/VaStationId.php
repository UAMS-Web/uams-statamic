<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating United States Department of Veterans
 * Affairs Station Identifiers (STA3N).
 *
 * This rule ensures VA Station Identifiers conform to the standard format of a
 * three-digit numeric string (e.g., `123`). It rejects invalid formats,
 * non-numeric values, or strings with lengths other than three digits.
 *
 * The regular expression pattern is /^\d{3}$/
 *
 * This pattern ensures:
 *   - Exactly three digits (0-9),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class VaStationId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the VA Station Identifier format
     * using a regular expression.
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
        // Check if the value matches the VA Station Identifier format
        if (
            ! \preg_match(
                '/^\d{3}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.veterans_affairs.station_id'));
        }
    }
}
