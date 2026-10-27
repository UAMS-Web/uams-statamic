<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Google Scholar Author IDs (as used in
 * Wikidata Property:P1960).
 *
 * This rule ensures Google Scholar Author IDs conform to the standard format of a
 * 12-character string consisting of hyphens (-), underscores (_), digits (0-9),
 * or letters (A-Z, a-z) (e.g., `ABC123-def45` or `xyz_789-0123`). It rejects
 * invalid formats, lengths other than 12 characters, special characters outside
 * the allowed set, or spaces.
 *
 * The regular expression pattern is /^[-_0-9A-Za-z]{12}$/
 *
 * This pattern ensures:
 *   - Exactly 12 characters,
 *   - Each character is a hyphen (-), underscore (_), digit (0-9) or letter (A-Z,
 *     a-z),
 *   - No spaces, special symbols outside the allowed set, or other characters are
 *     allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class GoogleScholarAuthorId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Google Scholar Author ID format
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
        // Check if the value matches the Google Scholar Author ID format
        if (
            ! \preg_match(
                '/^[-_0-9A-Za-z]{12}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.google_scholar_author_id'));
        }
    }
}
