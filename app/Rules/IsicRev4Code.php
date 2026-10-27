<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating International Standard of Industrial
 * Classification of All Economic Activities (ISIC), Revision 4 Codes.
 *
 * This rule ensures ISIC Rev 4 Codes conform to the standard format, which is
 * either a single letter from A to U (e.g., `A`, `B`) or a two- to four-digit
 * number (e.g., `01`, `123`, `1234`). It rejects invalid formats, values outside
 * the `A`-`U` letter range, numbers with fewer than two or more than four digits,
 * or those containing non-alphanumeric characters beyond the allowed set.
 *
 * The regular expression pattern is /^([A-U]|\d{2,4})$/.
 *
 * This pattern ensures:
 *   - Either a single uppercase letter from A to U ([A-U]),
 *   - Or two to four digits (\d{2,4}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class IsicRev4Code extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the ISIC Rev 4 Code format using a
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
        // Check if the value matches the ISIC Rev 4 Code format
        if (
            ! \preg_match(
                '/^([A-U]|\d{2,4})$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.isic_rev4_code'));
        }
    }
}
