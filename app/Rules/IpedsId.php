<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for Integrated Postsecondary Education Data System
 * (IPEDS) ID numbers.
 *
 * This rule ensures IPEDS ID numbers (Unit ID) conform to the standard
 * format of exactly six digits (e.g., `106397`). It rejects invalid
 * formats, non-numeric values, or numbers with incorrect digit counts.
 *
 * The regular expression pattern is /^\d{6}$/.
 *
 * This pattern ensures:
 *   - Exactly six digits (0-9),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class IpedsId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the IPEDS ID format using a
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
        // Check if the value matches the IPEDS ID format
        if (
            ! \preg_match(
                '/^\d{6}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.ipeds_id'));
        }
    }
}
