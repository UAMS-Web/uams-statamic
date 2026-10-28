<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating YouTube URLs
 *
 * This rule ensures URLs conform to the standard formats for YouTube videos:
 *   - `https://www.youtube.com/watch?v=VIDEO_ID`
 *     (e.g., `https://www.youtube.com/watch?v=dQw4w9WgXcQ`)
 *   - `https://youtu.be/VIDEO_ID` (e.g., `https://youtu.be/dQw4w9WgXcQ`)
 *
 * It rejects invalid formats, malformed video IDs, or unsupported YouTube URL
 * patterns.
 *
 * The regular expression pattern is /^(?:https?:\/\/)(?:www\.)?(?:(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)(?:&.*)?)$/
 *
 * This pattern ensures:
 *   - Starts with `http://` or `https://`,
 *   - Optionally includes `www.` (e.g., `www.youtube.com`),
 *   - Matches `youtube.com/watch?v=VIDEO_ID` or `youtu.be/VIDEO_ID`,
 *     where VIDEO_ID is one or more alphanumeric characters, underscores or
 *     hyphens, optionally followed by query parameters (e.g., `&t=10`),
 *   - No extra characters, spaces or unsupported platforms are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class YouTubeUrl extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the YouTube URL format.
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

        // Check if the value matches the URL format for YouTube using a regular expression
        if (
            ! \preg_match(
                '/^(?:https?:\/\/)(?:www\.)?(?:(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)(?:&.*)?)$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.url_video.youtube'));
        }
    }
}
