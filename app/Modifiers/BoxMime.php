<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\BoxSharedStaticDirectUrl;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier that returns the MIME type string for a Box.com shared static
 * direct URL, or an empty string when the value is not a valid Box direct link.
 *
 * Usage: `{{ video_url | box_mime }}` or `{{ audio_url | box_mime }}`
 */
class BoxMime extends Modifier
{
    /**
     * Resolve the MIME type for a Box direct URL string.
     *
     * @param  mixed  $value  URL or other value from the template
     * @param  array<int|string, mixed>  $params  Unused modifier parameters
     * @param  array<int|string, mixed>  $context  Unused Antlers context
     * @return string MIME type when parseable, otherwise an empty string
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Return an empty MIME when the piped value is not a string; trim and
        // blank rejection live in BoxSharedStaticDirectUrl::parse().
        if (! is_string($value)) {
            return '';
        }

        // Delegate MIME resolution to the shared Box URL parser
        return BoxSharedStaticDirectUrl::mimeTypeString($value);
    }
}
