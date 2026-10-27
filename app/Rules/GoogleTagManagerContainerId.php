<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Google Tag Manager (GTM) Container IDs.
 *
 * This rule ensures GTM Container IDs conform to the format `GTM-` followed by
 * six to eight uppercase alphanumeric characters (e.g., `GTM-ABC123`,
 * `GTM-1234ABC` or `GTM-ABCDEFGH`). It rejects invalid formats, values with
 * fewer than six or more than eight characters after the prefix, or those
 * containing lowercase letters or special symbols.
 *
 * The regular expression pattern is /^GTM-[A-Z0-9]{6,8}$/
 *
 * This pattern ensures:
 *   - Starts with `GTM-` exactly,
 *   - Followed by six to eight uppercase alphanumeric characters ([A-Z0-9]{6,8}),
 *   - No extra characters, spaces, lowercase letters or special symbols are
 *     allowed.
 *
 * The error message is defined in lang/vendor/statamic/en/validation.php under
 * the key `google_tag_manager_container_id`.
 */
class GoogleTagManagerContainerId extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Google Tag Manager Container ID
     * format using a regular expression.
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
        // Check if the value matches the GTM Container ID format
        if (
            ! \preg_match(
                '/^GTM-[A-Z0-9]{6,8}$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.google_tag_manager_container_id'));
        }
    }
}
