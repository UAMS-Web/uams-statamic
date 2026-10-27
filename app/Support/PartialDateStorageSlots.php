<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates year, month, and day segments from partial-date storage tokens.
 *
 * Segments come from `explode('-', $trimmed, 3)` so trailing placeholder runs
 * stay in the day slot. Used by `FormatSchemaDate` and `ApStyleDate` so slot
 * rules stay aligned.
 */
final class PartialDateStorageSlots
{
    /**
     * Whether the year segment is a four-digit Gregorian year token.
     *
     * @param  mixed  $part  First `explode` segment, or null when absent
     * @return bool True when the slot is a usable four-digit year token
     */
    public static function isValidYearPart(
        mixed $part
    ): bool {
        // Treat absent or non-string year slots as invalid
        if (
            ! \is_string($part)
            || $part === ''
        ) {
            return false;
        }

        // Reject placeholders and non-digits before the fixed-width year check
        if (strpbrk($part, 'X-') !== false) {
            return false;
        }

        // Require exactly four ASCII digits for the year field
        return preg_match(
            '/^\d{4}$/',
            $part
        ) === 1;
    }

    /**
     * Whether the month segment is a two-digit month token.
     *
     * @param  mixed  $part  Second `explode` segment, or null when not set
     * @return bool True when the slot is a usable two-digit month field
     */
    public static function isValidMonthPart(
        mixed $part
    ): bool {
        // Treat absent or non-string month slots as invalid
        if (
            ! \is_string($part)
            || $part === ''
        ) {
            return false;
        }

        // Reject placeholders and stray hyphens inside the month slot
        if (strpbrk($part, 'X-') !== false) {
            return false;
        }

        // Require exactly two ASCII digits for the month field
        return preg_match(
            '/^\d{2}$/',
            $part
        ) === 1;
    }

    /**
     * Whether the day segment is a two-digit day-of-month token.
     *
     * @param  mixed  $part  Third `explode` segment, or null when not set
     * @return bool True when the slot is a usable two-digit day field
     */
    public static function isValidDayPart(
        mixed $part
    ): bool {
        // Treat absent or non-string day slots as invalid
        if (
            ! \is_string($part)
            || $part === ''
        ) {
            return false;
        }

        // Reject placeholders and hyphen padding inside the day slot
        if (strpbrk($part, 'X-') !== false) {
            return false;
        }

        // Require exactly two ASCII digits for the day field
        return preg_match(
            '/^\d{2}$/',
            $part
        ) === 1;
    }
}
