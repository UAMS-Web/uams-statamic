<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Global Location Numbers (GLNs).
 *
 * First, the input value is validated to ensure it is exactly 13 digits using a
 * regular expression. If not, the validation fails with a specific message and
 * returns early to prevent further validation.
 *
 * Then, the input value is validated to ensure it passes the GS1 modulo-10 check
 * digit algorithm using the Gs1Modulo10 rule. If not, the validation fails with a
 * specific message.
 *
 * The GS1 modulo-10 check digit algorithm is implemented in the Gs1Modulo10 rule.
 *
 * Valid examples:
 *   - 0865321000104
 *   - 3700470700000
 *   - 0860484000404
 *
 * @see  https://ref.gs1.org/standards/genspecs/
 */
class GlobalLocationNumber extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Global Location Number format and
     * passes the GS1 modulo-10 check digit algorithm.
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
        // Check if the value matches the GLN format using a regular expression
        if (
            ! \preg_match(
                '/^\d{13}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.global_location_number.format'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Validate the GLN using the GS1 modulo-10 check digit algorithm. This ensures
        // the check digit is correct according to GS1 standards.
        // ---
        if (! Gs1Modulo10::isValidGs1Modulo10($value)) {
            // If the value fails GS1 modulo-10 check digit validation, fail validation with a specific message
            $fail(__('statamic::validation.global_location_number.invalid'));
        }
    }
}
