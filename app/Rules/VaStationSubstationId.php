<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating values that can be either a United States
 * Department of Veterans Affairs Station Identifier (STA3N) or a Substation
 * Identifier (STA6A).
 *
 * This rule ensures VA Station and Substation Identifiers conform to the standard
 * format of a three-digit numeric string optionally followed by three
 * alphanumeric characters (A-Z, 0-9) (e.g., `123`, `123ABC` or `123456`). It
 * rejects invalid formats, non-alphanumeric values (except as allowed), or
 * strings with incorrect lengths.
 *
 * The regular expression pattern is /^\d{3}(?:[A-Z0-9]{3})?$/
 *
 * This pattern ensures:
 *   - Exactly three digits (0-9) at the start,
 *   - Optionally followed by three uppercase alphanumeric characters (A-Z, 0-9),
 *   - No extra characters, spaces or special symbols (except as allowed) are
 *     permitted.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class VaStationSubstationId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the VA Substation Identifier format
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
        // Check if the value matches the VA Station or Substation Identifier format
        if (
            ! \preg_match(
                '/^\d{3}(?:[A-Z0-9]{3})?$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.veterans_affairs.station_substation_id'));
        }
    }
}
