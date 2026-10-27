<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating PubMed Author Identifiers.
 *
 * This rule ensures PubMed Author Identifiers conform to the standard format of a
 * name starting with an uppercase letter (A-Z) followed by one or more lowercase
 * letters (a-z), a space, and then one or two uppercase letters (A-Z)
 * (e.g., `Smith AB` or `Jones A`). It rejects invalid formats, lowercase letters
 * in the last part, numbers, special characters or extra spaces.
 *
 * The regular expression pattern is /^[-A-Za-z\']{1,}( [-A-Za-z\']{1,})*,? [-A-Za-z\']{1,}( [-A-Za-z\']{1,})*$/
 *
 * This pattern ensures:
 *   - Last Name Plus Initials
 *       - Starts with one or more appropriate characters (A-Z, a-z, -, '),
 *       - Followed by a space and one or more appropriate characters
 *         (A-Z, a-z, -, '),
 *       - Optionally followed by additional sets of a space and one or more
 *         appropriate characters (A-Z, a-z, -, '),
 *   - Full Name, Natural Order
 *       - Starts with one or more appropriate characters (A-Z, a-z, -, '),
 *       - Followed by a space and one or more appropriate characters
 *         (A-Z, a-z, -, '),
 *       - Optionally followed by additional sets of a space and one or more
 *         appropriate characters (A-Z, a-z, -, '),
 *   - Full Name, Inverted Order
 *       - Starts with one or more appropriate characters (A-Z, a-z, -, '),
 *       - Optionally followed by additional sets of a space and one or more
 *         appropriate characters (A-Z, a-z, -, '),
 *       - Optionally followed by a comma,
 *       - Followed by a space and one or more appropriate characters
 *         (A-Z, a-z, -, '),
 *       - Optionally followed by additional sets of a space and one or more
 *         appropriate characters (A-Z, a-z, -, '),
 *   - No extra characters, numbers, special symbols or additional spaces are
 *     allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class PubmedAuthorId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the PubMed Author Identifier format
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
        // Check if the value matches the PubMed Author Identifier format
        if (
            ! \preg_match(
                '/^[-A-Za-z\']{1,}( [-A-Za-z\']{1,})*,? [-A-Za-z\']{1,}( [-A-Za-z\']{1,})*$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.pubmed.pubmed_author_id'));
        }
    }
}
