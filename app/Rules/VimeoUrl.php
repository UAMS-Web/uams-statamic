<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating Vimeo URLs
 *
 * This rule ensures URLs conform to the standard formats for Vimeo videos:
 *   - `https://vimeo.com/VIDEO_ID` (e.g., `https://vimeo.com/123456789`)
 *   - `https://vimeo.com/VIDEO_ID/TITLE`
 *     (e.g., `https://vimeo.com/123456789/my-video`)
 *
 * It rejects invalid formats, malformed video IDs, or unsupported Vimeo URL
 * patterns.
 *
 * First, the input value is validated to ensure it is a valid URL using Laravel's
 * built-in URL validation rule. If not, the validation fails with a specific
 * message and returns early to prevent further validation.
 *
 * Then, the input value is validated to ensure it matches the Vimeo URL format
 * using a regular expression. If not, the validation fails with a specific
 * message.
 *
 * The regular expression pattern is /^(?:https?:\/\/)vimeo\.com\/\d+(?:\/[a-zA-Z0-9]+)?$/
 *
 * This pattern ensures:
 *   - Starts with `http://` or `https://`,
 *   - Matches `vimeo.com/VIDEO_ID`, where VIDEO_ID is one or more digits,
 *   - Optionally matches `vimeo.com/VIDEO_ID/TITLE`, where TITLE is one or more
 *     alphanumeric characters,
 *   - No extra characters, spaces or unsupported platforms are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class VimeoUrl extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the Vimeo URL format.
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

        // Check if the value matches the URL format for Vimeo using a regular expression
        if (
            ! \preg_match(
                '/^(?:https?:\/\/)vimeo\.com\/\d+(?:\/[a-zA-Z0-9]+)?$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.url_video.vimeo'));
        }
    }
}
