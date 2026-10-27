<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flattens configured string fields from replicator set rows into one search string.
 *
 * Shared engine for the per-field search transformers in `App\SearchTransformers`
 * (`SameAsTransformer` reads `url` rows, `AdditionalTypeTransformer` reads `uri`
 * rows, `AlternateNameTransformer` reads `text` rows) so the row-walking logic
 * lives in exactly one place.
 */
final class ReplicatorSetFlattener
{
    /**
     * Collect trimmed string values from replicator rows for search indexing.
     *
     * For each row, values are taken in `$fieldHandles` order; only non-empty
     * trimmed strings are kept. Rows that are not arrays are skipped.
     *
     * @param  mixed  $value  Raw replicator value (list of set rows)
     * @param  list<string>  $fieldHandles  Row keys to read per set, in order
     * @param  string  $separator  Glue between collected values
     * @return string|null Joined values, or null when none are present
     */
    public static function flatten(
        mixed $value,
        array $fieldHandles,
        string $separator = ' ',
    ): ?string {
        // Check whether the replicator value is a non-empty list
        if (
            ! \is_array($value)
            || $value === []
        ) {
            return null;
        }

        // Check whether any field handles were configured
        if ($fieldHandles === []) {
            return null;
        }

        // ---
        // Collect trimmed strings from each configured handle on each row
        // ---

        // Initialize collected parts
        $parts = [];

        // Walk each replicator row
        foreach ($value as $row) {
            // Skip rows that are not associative arrays
            if (! \is_array($row)) {
                continue;
            }

            // Read each configured handle on this row in order
            foreach ($fieldHandles as $handle) {
                // Read the raw value when the handle exists on the row
                $raw = $row[$handle] ?? null;

                // Skip values that are not strings
                if (! \is_string($raw)) {
                    continue;
                }

                // Trim whitespace from the string value
                $trimmed = trim($raw);

                // Skip empty strings after trimming
                if ($trimmed === '') {
                    continue;
                }

                // Append the trimmed value to the collected parts
                $parts[] = $trimmed;
            }
        }

        // Return null when no usable values were collected
        if ($parts === []) {
            return null;
        }

        // Join all parts with the configured separator
        return implode($separator, $parts);
    }
}
