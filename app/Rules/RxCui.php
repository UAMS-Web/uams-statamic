<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating National Library of Medicine RxNorm
 * concept unique identifiers (RxCUI).
 *
 * This rule ensures RxCUI values conform to the format of a numeric identifier
 * consisting of one or more digits (e.g., `1`, `123`, `123456789`), as used by
 * the RxNorm terminology system maintained by the U.S. National Library of
 * Medicine. It rejects invalid formats, non-numeric values, or strings containing
 * letters, spaces or special symbols.
 *
 * The regular expression pattern is /^\d+$/
 *
 * This pattern ensures:
 *   - One or more digits (\d+),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class RxCui extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the RxNorm concept unique identifier
     * (RxCUI) format using a regular expression.
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
        // Check if the value matches the RxCUI format
        if (
            ! \preg_match(
                '/^\d+$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.rxcui'));
        }
    }
}
