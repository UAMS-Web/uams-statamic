<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates year, month, and day segments from partial-date storage tokens.
 *
 * Segments come from `explode('-', $trimmed, 3)` so trailing placeholder runs
 * stay in the day slot. Used by `FormatSchemaDate` and `ApStyleDate` so slot
 * rules stay aligned.
 *
 * Each slot is valid only when it is a string of exactly the slot's width in
 * ASCII digits. That one pattern also rejects an empty slot, an `X`
 * placeholder and a stray hyphen, so no separate check is needed for them.
 * A slot followed by a newline is refused too (#1155).
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
        return self::isDigits($part, 4);
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
        return self::isDigits($part, 2);
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
        return self::isDigits($part, 2);
    }

    /**
     * Whether a slot is a string of exactly the given number of ASCII digits.
     *
     * @param  mixed  $part  The `explode` segment, or null when absent
     * @param  int  $width  The number of digits the slot holds
     */
    private static function isDigits(mixed $part, int $width): bool
    {
        // Only a string can hold a date token; anything else is an absent slot.
        // `\z` rather than `$`, which would also match before a final newline
        return \is_string($part)
            && preg_match('/^\d{'.$width.'}\z/', $part) === 1;
    }
}
