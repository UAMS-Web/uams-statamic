<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

/**
 * Parse Antlers tag `field` parameters that may list fallback handles separated by
 * `|` (first non-empty field wins in the resolver).
 */
final class SchemaTagFieldHandles
{
    /**
     * Build an ordered list of non-empty field handles from a tag parameter.
     *
     * @param  mixed  $field  Raw `field` value (string with optional `|` fallbacks)
     * @return list<string> Trimmed handles; empty when missing or invalid
     */
    public static function listFromParam(
        mixed $field
    ): array {
        // Check if the raw parameter is not a usable string
        if (! is_string($field)) {
            // Return an empty array to signal no usable field handles
            return [];
        }

        // Split on pipe so callers can pass primary|fallback|… handles.
        // Per-segment trim below also covers a blank whole parameter, so an
        // outer trim + empty early-return would be observationally identical.
        $parts = explode(
            '|',
            $field
        );

        // Initialize the ordered handle list
        $handles = [];

        // Collect each non-empty trimmed segment
        foreach ($parts as $part) {
            // Trim whitespace from this segment
            $handle = trim($part);

            // Check if the handle is empty after trimming
            if ($handle === '') {
                // Skip empty segments from leading or doubled delimiters
                continue;
            }

            // Append this handle to the ordered list
            $handles[] = $handle;
        }

        // Return the ordered list of non-empty handles
        return $handles;
    }
}
