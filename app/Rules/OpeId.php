<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for Office of Postsecondary Education (OPE) ID
 * numbers.
 *
 * This rule ensures OPE ID numbers conform to the standard format of exactly
 * eight digits (e.g., `00110900`). It rejects invalid formats, non-numeric
 * values, or numbers with incorrect digit counts.
 *
 * The regular expression pattern is /^\d{8}$/.
 *
 * This pattern ensures:
 *   - Exactly eight digits (0-9),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class OpeId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the OPE ID format using a
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
        // Check if the value matches the OPE ID format
        if (
            ! \preg_match(
                '/^\d{8}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.ope_id'));
        }
    }
}
