<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating U.S. SMS short codes.
 *
 * This rule ensures SMS short codes conform to the standard U.S. format of either
 * five or six digits, with the first digit restricted to `0` or `2`-`9`
 * (e.g., `01234`, `012345`). It is intended specifically for U.S. short codes, as
 * defined by conventions in the United States telecommunications industry. The
 * first digit must not be `1` to avoid reserved or invalid ranges. It rejects
 * invalid formats, non-numeric values, or strings with digit counts outside the
 * `5`-`6` range or starting with `1`.
 *
 * The regular expression pattern is /^(0|[2-9])\d{4,5}$/
 *
 * This pattern ensures:
 *   - First digit is `0` or `2`-`9` ((0|[2-9])),
 *   - Followed by four to five digits (\d{4,5}), making a total of five or six
 *     digits,
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class SmsShortCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the U.S. SMS short code format using a
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
        // Check if the value matches the U.S. SMS short code format
        if (
            ! \preg_match(
                '/^(0|[2-9])\d{4,5}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.sms_short_code'));
        }
    }
}
