<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating U.S. Taxpayer Identification Numbers
 * (TINs), limited to Employer Identification Numbers (EINs).
 *
 * This rule ensures TINs conform to the EIN format of nine digits in `XX-XXXXXXX`
 * format (e.g., `12-3456789`).
 *
 * It excludes other U.S. TIN types such as Social Security Number (SSN),
 * Individual Taxpayer Identification Number (ITIN), Taxpayer Identification
 * Number for Pending U.S. Adoptions (ATIN), and Preparer Taxpayer
 * Identification Number (PTIN), as these are considered personally
 * identifiable information (PII) that directly identify individuals:
 *   - SSN: A unique identifier for individuals for tax and social security
 *     purposes.
 *   - ITIN: Issued to individuals ineligible for an SSN (e.g., nonresident aliens)
 *     for tax filing, identifying a person.
 *   - ATIN: A temporary number for children in adoption processes, identifying an
 *     individual.
 *   - PTIN: Issued to tax preparers, uniquely identifying a person in their
 *     professional capacity.
 *
 * EINs identify businesses and organizations, not individuals, and are thus
 * not PII. This rule rejects invalid formats, non-matching patterns, or
 * values that don't conform to the EIN format.
 *
 * The regular expression pattern is /^\d{2}-\d{7}$/
 *
 * This pattern ensures:
 *   - Two digits (\d{2}),
 *   - Followed by a hyphen (\-),
 *   - Followed by seven digits (\d{7}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class TaxpayerIdentificationNumber extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the U.S. Employer Identification
     * Number (EIN) format using a regular expression.
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
        // Check if the value matches the U.S. Employer Identification Number format
        if (
            ! \preg_match(
                '/^\d{2}-\d{7}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.taxpayer_identification_number'));
        }
    }
}
