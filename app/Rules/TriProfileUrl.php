<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating UAMS Translational Research Institute
 * Profiles URLs.
 *
 * This rule ensures TriProfile URLs conform to the standard format starting with
 * `https://uams-triprofiles.uams.edu/profiles/display/` followed by a numeric
 * string (e.g., `https://uams-triprofiles.uams.edu/profiles/display/12345`). It
 * rejects invalid formats, non-numeric identifiers, incorrect paths, domains or
 * extra characters.
 *
 * First, the input value is validated to ensure it is a valid URL using Laravel's
 * built-in URL validation rule. If not, the validation fails with a specific
 * message and returns early to prevent further validation.
 *
 * Then, the URL's host is confirmed to be a UAMS domain via the single shared
 * UAMS-domain source ({@see FirstPartyUrl::isFirstPartyUrl()}, backed by `config/first_party.php`),
 * so the UAMS knowledge is centralized rather than hard-coded here.
 *
 * Finally, the input value is validated to ensure it matches the UAMS Translational
 * Research Institute Profiles URL format using a regular expression. If not, the
 * validation fails with a specific message.
 *
 * The regular expression pattern is /^https:\/\/uams-triprofiles\.uams\.edu\/profiles\/display\/\d+$/
 *
 * This pattern ensures:
 *   - Starts with `https://uams-triprofiles.uams.edu/profiles/display/`,
 *   - Followed by one or more digits (0-9) as the profile identifier,
 *   - No extra characters, spaces, special symbols or non-numeric identifiers are
 *     allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class TriProfileUrl extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the UAMS Translational Research
     * Institute Profiles URL format.
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
        // ---
        // Validate that the value is a valid URL using Laravel's built-in URL validation
        // rule
        // ---
        if (
            ! filter_var(
                $value,
                FILTER_VALIDATE_URL
            )
        ) {
            // If the value is not a valid URL, fail validation with a specific message
            $fail(__('statamic::validation.url'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Confirm the URL belongs to a UAMS domain using the single shared UAMS-domain
        // source (config/first_party.php via FirstPartyUrl), centralizing the UAMS knowledge instead
        // of hard-coding it here. The TRI profiles host `uams-triprofiles.uams.edu` is a
        // subdomain of the configured `uams.edu`, so this preserves existing behavior.
        // ---
        if (! FirstPartyUrl::isFirstPartyUrl($value)) {
            // If the host is not a UAMS domain, fail validation with a specific message
            $fail(__('statamic::validation.url_tri_profile'));

            // Return early; the format check below is meaningless off a non-UAMS host
            return;
        }

        // ---
        // Check if the value matches the UAMS Translational Research Institute Profiles
        // URL format using a regular expression. The `uams-triprofiles` subdomain prefix
        // and the `/profiles/display/{id}` path are TRI-specific format details, kept
        // here; only the UAMS-domain knowledge is centralized above.
        // ---
        if (
            ! \preg_match(
                '/^https:\/\/uams-triprofiles\.uams\.edu\/profiles\/display\/\d+$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.url_tri_profile'));
        }
    }
}
