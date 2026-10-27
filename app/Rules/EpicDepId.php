<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating UAMS Health Epic Department (DEP) ID
 * values.
 *
 * Epic Department (DEP) IDs are integer-only identifiers used by UAMS Health's
 * Epic / MyChart systems to identify a scheduling department. This rule ensures
 * a DEP ID value conforms to the format of one or more digits (e.g., `1`, `123`,
 * `10502`). It rejects non-numeric values, whitespace, and strings containing
 * any non-digit characters.
 *
 * The regular expression pattern is /\A\d+\z/
 *
 * This pattern ensures:
 *   - One or more digits (\d+),
 *   - No extra characters, spaces or special symbols are allowed,
 *   - The whole string is anchored with \A and \z (not ^ and $), so a trailing
 *     newline — which PCRE's $ would otherwise accept before end-of-string — is
 *     rejected.
 *
 * Values are treated as strings and never coerced to int, so any leading zeros
 * are preserved. Empty values are skipped (delegated to the `required` rule),
 * mirroring the sibling identifier rules (e.g. RxCui). This rule backs the
 * `epic_dep_id` identifier fieldtype preset.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class EpicDepId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the UAMS Health Epic Department
     * (DEP) ID format using a regular expression.
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
        // Check if the value matches the Epic DEP ID format (digits only)
        if (
            ! \preg_match(
                '/\A\d+\z/',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.epic_dep_id'));
        }
    }
}
