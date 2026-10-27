<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Health Care Provider Taxonomy Codes.
 *
 * This rule ensures Health Care Provider Taxonomy Codes conform to the standard
 * format of three digits, followed by two alphanumeric characters (0-9 or A-Z),
 * followed by four digits, and ending with 'X' (e.g., `123AB4567X`). It rejects
 * invalid formats, incorrect character counts, or missing the required 'X' suffix.
 *
 * The regular expression pattern is /^[0-9A-Z]{2}\d{4}X$/.
 *
 * This pattern ensures:
 *   - Exactly two alphanumeric characters ([0-9A-Z]{2}),
 *   - Followed by exactly four digits (\d{4}),
 *   - Ending with 'X',
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 *
 * @see  https://nucc.org/index.php/code-sets-mainmenu-41/provider-taxonomy-mainmenu-40
 * @see  https://taxonomy.nucc.org/
 */
class HealthCareProviderTaxonomyCode extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Health Care Provider Taxonomy
     * Code format using a regular expression.
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
        // Check if the value matches the Health Care Provider Taxonomy Code format
        if (
            ! \preg_match(
                '/^\d{3}[0-9A-Z]{2}\d{4}X$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.health_care_provider_taxonomy_code'));
        }
    }
}
