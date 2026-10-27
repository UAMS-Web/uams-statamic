<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\LogosUnrestricted;

/**
 * Stateless formatter for unrestricted logo alt text: `Logo of {title}`, or
 * null when the title is missing or not a non-empty string.
 *
 * Used by App\Support\Statamic\Assets\LogosUnrestricted\AugmentedAsset for the
 * computed `alt` field; kept separate so rules stay unit-testable without booting
 * Statamic assets.
 */
final class AltFormatter
{
    /**
     * Build alt text from a blueprint-style title string.
     *
     * @param  mixed  $title  Typically the asset blueprint `title` field
     * @return string|null `Logo of {trimmed title}`, or null if not a non-empty string
     */
    public static function fromTitle(
        mixed $title
    ): ?string {
        // Check if the title is not a string
        if (! is_string($title)) {
            // Return null if the title is not a string
            return null;
        }

        // Trim the title
        $trimmed = trim($title);

        // Check if the trimmed title is an empty string
        if ($trimmed === '') {
            // Return null if the trimmed title is an empty string
            return null;
        }

        // Return the formatted alt text
        return 'Logo of '.$trimmed;
    }
}
