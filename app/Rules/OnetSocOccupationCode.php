<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating O*Net-SOC Occupation Codes.
 *
 * This rule ensures O*Net-SOC Occupation Codes conform to the standard format of
 * two digits, followed by a hyphen, followed by four digits, followed by a
 * period, and ending with two digits (e.g., `11-1011.00`, `13-2011.01`). It
 * rejects invalid formats, incorrect character counts, or missing required
 * separators.
 *
 * The regular expression pattern is /^\d{2}-\d{4}\.\d{2}$/.
 *
 * This pattern ensures:
 *   - Exactly two digits (\d{2}),
 *   - Followed by a hyphen (-),
 *   - Followed by exactly four digits (\d{4}),
 *   - Followed by a period (.),
 *   - Ending with exactly two digits (\d{2}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class OnetSocOccupationCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the O*Net-SOC Occupation Code
     * format using a regular expression.
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
        // Check if the value matches the O*Net-SOC Occupation Code format
        if (
            ! \preg_match(
                '/^\d{2}-\d{4}\.\d{2}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.onet_soc_occupation_code'));
        }
    }
}
