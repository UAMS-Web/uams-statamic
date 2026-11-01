<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating names, ensuring they only contain English
 * letters (A-Z, a-z) plus diacritics (e.g., é, ü, ç), spaces, periods (restricted
 * to initials, e.g., "J.R. Smith"), hyphens, and apostrophes.
 *
 * This rule checks that names conform to a specific format suitable for
 * English-based human names, allowing letters with diacritics, spaces, periods
 * for initials (e.g., `J.R.`), hyphens (e.g., `Jean-Luc`), and apostrophes
 * (e.g., `O'Connor`), while preventing letters from non-Latin alphabets
 * (e.g., Cyrillic, Arabic), numbers, special characters or other punctuation not
 * explicitly allowed.
 *
 * The regular expression pattern is /^((?:[a-zA-Z\x{00C0}-\x{017F}]\.\s?){0,2}[a-zA-Z\x{00C0}-\x{017F}]+(?:[\s\-'][a-zA-Z\x{00C0}-\x{017F}]+)*)$/uD
 *
 * This pattern allows:
 *   - English letters (a-z, A-Z),
 *   - Diacritics (Unicode range \x{00C0}-\x{017F}, covering characters like é, ü,
 *     and ç),
 *   - Periods (.) only in up to two initials opening the name, each a letter
 *     followed by a period and an optional space (e.g., `J. Smith`, `J.R. Smith`,
 *     `J.R.Smith`),
 *   - Spaces (\s), hyphens (-), and apostrophes (') for name components,
 *   - But rejects non-Latin letters (e.g., Cyrillic, Arabic), numbers, special
 *     symbols, periods outside opening initials (e.g., `John.Doe`, `.Smith`,
 *     `Smith Jr.`) and initials with no name after them (e.g., `J.R.`).
 *
 * The 'u' modifier ensures Unicode support for \x{} escapes.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class Name extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the allowed name format using a
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
        // Check if the value matches the allowed name format
        if (
            ! \preg_match(
                '/^((?:[a-zA-Z\x{00C0}-\x{017F}]\.\s?){0,2}[a-zA-Z\x{00C0}-\x{017F}]+(?:[\s\-\'][a-zA-Z\x{00C0}-\x{017F}]+)*)$/uD',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.name'));
        }
    }
}
