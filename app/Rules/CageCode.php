<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for Commercial and Government Entity (CAGE) codes.
 *
 * This rule ensures CAGE codes conform to the standard format: one digit,
 * three characters from the set that excludes I and O (to avoid confusion
 * with 1 and 0), then one digit (e.g., `1QJY4`).
 *
 * The regular expression pattern is /^[0-9][A-HJ-NP-Z0-9]{3}[0-9]$/.
 *
 * This pattern ensures:
 *   - First character is a digit (0-9),
 *   - Characters two through four are from A-H, J-N, P-Z, or 0-9 (no I, no O),
 *   - Fifth character is a digit (0-9).
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class CageCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the CAGE code format using a
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
        // Check if the value matches the CAGE code format
        if (
            ! \preg_match(
                '/^\d[A-HJ-NP-Z0-9]{3}\d$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.cage_code'));
        }
    }
}
