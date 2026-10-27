<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating UAMS video URLs: YouTube, Vimeo, or a
 * Box.com HTTPS direct file link under `/shared/static/` with a video MIME type.
 *
 * Supported shapes include:
 * - YouTube:
 *     - `https://www.youtube.com/watch?v=VIDEO_ID`
 *       (e.g., `https://www.youtube.com/watch?v=dQw4w9WgXcQ`)
 *     - `https://youtu.be/VIDEO_ID` (e.g., `https://youtu.be/dQw4w9WgXcQ`)
 * - Vimeo:
 *     - `https://vimeo.com/VIDEO_ID` (e.g., `https://vimeo.com/123456789`)
 * - Box.com direct link (shared static path, video extension), for example:
 *     - `https://app.box.com/shared/static/....mp4`
 *
 * It rejects invalid formats, unsupported platforms, malformed IDs, or non-video
 * Box links.
 */
class UamsVideoUrl extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the UAMS Video URL format for YouTube
     * and Vimeo.
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
        /**
         * Create instances of the YouTube, Vimeo, and Box shared static video URL
         * rules to delegate validation to specialized classes.
         */
        $youtubeRule = new YouTubeUrl;
        $vimeoRule = new VimeoUrl;
        $boxVideoRule = new BoxSharedStaticVideoUrl;

        /**
         * Check if the value passes YouTube, Vimeo, or Box direct video URL
         * validation using each rule's `isValid` helper.
         */
        $isValid = $youtubeRule->isValid($value)
            || $vimeoRule->isValid($value)
            || $boxVideoRule->isValid($value);

        /**
         * If none of the supported video URL formats matched, fail with the UAMS
         * combined message.
         */
        if (! $isValid) {
            $fail(__('statamic::validation.url_video_.uams'));
        }
    }
}
