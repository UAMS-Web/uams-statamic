<?php

declare(strict_types=1);

namespace App\Modifiers;

use Carbon\Carbon;
use Statamic\Modifiers\Modifier;

/**
 * Formats a time value as AP Style.
 *
 * - 12:00 pm → 'noon'
 * - 12:00 am → 'midnight'
 * - If minutes are '00', uses 'h a' format (e.g., "3 p.m.")
 * - Otherwise uses 'h:i a' format (e.g., "3:15 p.m.")
 * - Uses 'a.m.' and 'p.m.' instead of 'am' and 'pm'
 * - Separates 'a.m.'/'p.m.' from preceding values with a non-breaking space
 */
class ApStyleTime extends Modifier
{
    /**
     * Modify a value.
     *
     * @param  mixed  $value  The value to be modified
     * @param  list<string>  $params  Any parameters used in the modifier
     * @param  array<string, mixed>  $context  Contextual values
     * @return string The formatted time string
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Check if value is empty
        if (empty($value)) {
            // Return empty string if value is empty
            return '';
        }

        // Narrow the mixed value to a type Carbon::parse() accepts
        $parsable = $value instanceof \DateTimeInterface
            || is_string($value)
            || is_int($value)
            || is_float($value) ? $value : null;

        // Parse the value as a Carbon date instance
        $date = Carbon::parse($parsable);

        // Extract hour in 12-hour format without leading zero (e.g., "1", "12")
        $hour = (int) $date->format('g');

        // Extract minutes with leading zero (e.g., "00", "23")
        $minute = $date->format('i');

        // Extract period in lowercase (e.g., "am", "pm")
        $period = $date->format('a');

        // ---
        // Handle special cases: noon and midnight
        // ---

        // Check if time is 12:00 pm (noon)
        if (
            $hour === 12
            && $minute === '00'
            && $period === 'pm'
        ) {
            // Return 'noon' for 12:00 pm
            return 'noon';
        }

        // Check if time is 12:00 am (midnight)
        if (
            $hour === 12
            && $minute === '00'
            && $period === 'am'
        ) {
            // Return 'midnight' for 12:00 am
            return 'midnight';
        }

        // ---
        // Format standard times
        // ---

        // Convert period to AP Style (a.m./p.m.)
        $apPeriod = $period === 'am' ? 'a.m.' : 'p.m.';

        // Use non-breaking space between time and period
        $nonBreakingSpace = "\u{00A0}";

        // Check if minutes are '00' to determine format
        if ($minute === '00') {
            // Return time without minutes (h a format)
            return $hour.$nonBreakingSpace.$apPeriod;
        }

        // Return time with minutes (h:i a format)
        return sprintf('%s:%s%s%s', $hour, $minute, $nonBreakingSpace, $apPeriod);
    }
}
