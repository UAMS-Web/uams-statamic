<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating ResearcherIDs (as used in Wikidata
 * Property:P1053).
 *
 * This rule ensures ResearcherIDs conform to the standard format of one to three
 * uppercase letters followed by a hyphen, four digits, another hyphen, and a
 * four-digit year starting with `19` or `20` (e.g., `ABC-1234-1990` or
 * `X-5678-2023`). It rejects invalid formats, lowercase letters, numbers outside
 * the specified ranges, special characters or missing hyphens.
 *
 * The regular expression pattern is /^[A-Z]{1,3}-\d{4}-(19|20)\d\d$/
 *
 * This pattern ensures:
 *   - One to three uppercase letters (`A`-`Z`) at the start,
 *   - A hyphen (`-`),
 *   - Exactly four digits (`0`-`9`),
 *   - Another hyphen (`-`),
 *   - A four-digit year starting with `19` or `20` (e.g., `1990` or `2023`),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class ResearcherId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the ResearcherID format using a
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
        // Check if the value matches the ResearcherID format
        if (
            ! \preg_match(
                '/^[A-Z]{1,3}-\d{4}-(19|20)\d\d$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.researcher_id'));
        }
    }
}
