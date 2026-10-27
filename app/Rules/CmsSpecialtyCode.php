<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Centers for Medicare & Medicaid Services
 * (CMS) Specialty Codes.
 *
 * This rule ensures CMS Specialty Codes conform to the standard format of one
 * alphanumeric character (0-9 or A-Z), followed by exactly one digit (e.g., `A1`,
 * `B2`, `C3`). It rejects invalid formats, incorrect character counts, or
 * non-alphanumeric first characters.
 *
 * The regular expression pattern is /^[0-9A-Z]\d$/.
 *
 * This pattern ensures:
 *   - Exactly one alphanumeric character ([0-9A-Z]),
 *   - Followed by exactly one digit (\d),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class CmsSpecialtyCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the CMS Specialty Code format
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
        // Check if the value matches the CMS Specialty Code format
        if (
            ! \preg_match(
                '/^[0-9A-Z]\d$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.cms_specialty_code'));
        }
    }
}
