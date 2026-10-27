<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Scopus Author IDs.
 *
 * This rule ensures Scopus Author IDs conform to the standard format of a number
 * starting with a digit from `1` to `9`, followed by nine to 11 additional digits
 * (`0`-`9`), resulting in a total length of 10 to 12 digits (e.g., `1234567890` or
 * `123456789012`). It rejects invalid formats, non-digits, numbers starting with
 * 0, or lengths outside 10 to 12 digits.
 *
 * The regular expression pattern is /^[1-9]\d{9,11}$/
 *
 * This pattern ensures:
 *   - Starts with a digit from `1` to `9` (not `0`),
 *   - Followed by nine to 11 additional digits (`0`-`9`),
 *   - Resulting in a total length of 10 to 12 digits,
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class ScopusAuthorId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Scopus Author ID format using a
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
        // Check if the value matches the Scopus Author ID format
        if (
            ! \preg_match(
                '/^[1-9]\d{9,11}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.scopus_author_id'));
        }
    }
}
