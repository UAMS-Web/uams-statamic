<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating International Organization for
 * Standardization (ISO) 6523 codes.
 *
 * This rule ensures ISO 6523 codes conform to the standard format of a
 * four-digit number followed by a colon and one to 35 uppercase alphanumeric
 * characters, optionally followed by another colon and one to 35 uppercase
 * alphanumeric characters (e.g., `1234:ABC`, `1234:ABC123:XYZ789`). It rejects
 * invalid formats, non-numeric prefixes, lowercase characters, missing colons, or
 * sections with incorrect character counts (fewer than one or more than 35 after
 * colons).
 *
 * The regular expression pattern is /^\d{4}:[A-Z0-9]{1,35}(:[A-Z0-9]{1,35})?$/.
 *
 * This pattern ensures:
 *   - Exactly four digits (\d{4}),
 *   - Followed by a colon (:),
 *   - One to 35 uppercase alphanumeric characters ([A-Z0-9]{1,35}),
 *   - Optionally followed by another colon and one to 35 uppercase alphanumeric
 *     characters (:[A-Z0-9]{1,35})?,
 *   - No extra characters, spaces or special symbols (except colons) are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class Iso6523Code extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the ISO 6523 code format using a
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
        // Check if the value matches the ISO 6523 code format
        if (
            ! \preg_match(
                '/^\d{4}:[A-Z0-9]{1,35}(:[A-Z0-9]{1,35})?$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.iso6523_code'));
        }
    }
}
