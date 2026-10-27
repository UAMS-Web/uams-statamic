<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating ORCID iDs (Open Researcher and
 * Contributor IDs).
 *
 * This rule ensures ORCID iDs conform to the standard format of four groups of
 * four digits separated by hyphens, with the last group consisting of three
 * digits followed by either a digit or an `X` (e.g., `0000-0000-0000-0000` or
 * `0000-0000-0000-000X`). It rejects invalid formats, numbers, special
 * characters or missing hyphens.
 *
 * The regular expression pattern is /^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/.
 *
 * This pattern ensures:
 *   - Four groups of exactly four characters (\d{4} and \d{3}[\dX]),
 *   - Hyphens (-) separating each group,
 *   - The first three groups have exactly four digits (\d{4}),
 *   - The last group has three digits followed by either a digit (\d) or 'X',
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class Orcid extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the ORCID iD format using a regular
     * expression.
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
        // Check if the value matches the ORCID iD format
        if (
            ! \preg_match(
                '/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.orcid'));
        }
    }
}
