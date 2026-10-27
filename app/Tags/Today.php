<?php

declare(strict_types=1);

namespace App\Tags;

use App\Enums\ApStyleGregorianMonth;
use Carbon\Carbon;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Emulates Gravity Forms {today} merge tags.
 *
 * Provides various date/time formats matching Gravity Forms functionality.
 * Uses AP Style rules for month abbreviations and time formatting.
 *
 * Usage:
 *   - {{ today }} - Default format (F j, Y with month abbreviations)
 *   - {{ today:time }} - Time format (g:i a with a.m./p.m.)
 *   - {{ today:timestamp }} - Unix timestamp
 *   - {{ today format="Y-m-d" }} - Custom format
 */
class Today extends Tags
{
    /**
     * Emulates Gravity Forms {today} merge tags.
     *
     * Provides various date/time formats matching Gravity Forms functionality.
     *
     * Usage:
     *   - {{ today }} - Default format (F j, Y with month abbreviations)
     *   - {{ today:time }} - Time format (g:i a with a.m./p.m.)
     *   - {{ today:timestamp }} - Unix timestamp
     *   - {{ today format="Y-m-d" }} - Custom format
     *
     * @return string The formatted date/time string
     */
    public function index(): string
    {
        // Get current date/time using Carbon
        $now = Carbon::now();

        // Check if format parameter is provided
        /** @var Parameters $params */
        $params = $this->params;
        if ($format = $params->get('format')) {
            // Coerce the parameter to a string, matching PHP's implicit casting.
            // RemoveStringCast stays untested under PCOV when the param is already
            // a string (the covering path runs; the cast node is not attributed).
            // @pest-mutate-ignore: RemoveStringCast
            $formatString = \is_scalar($format) || $format instanceof \Stringable ? (string) $format : '';

            // Return the formatted date/time string
            return $now->format($formatString);
        }

        // Default format: F j, Y with month abbreviations based on AP Style rules
        return $this->formatDefaultDate($now);
    }

    /**
     * Handle time format: {{ today:time }}
     *
     * @return string The formatted time string
     */
    public function time(): string
    {
        // Get current date/time using Carbon
        $now = Carbon::now();

        // Format time based on AP Style rules
        return $this->formatTime($now);
    }

    /**
     * Handle timestamp format: {{ today:timestamp }}
     *
     * @return int The Unix timestamp
     */
    public function timestamp(): int
    {
        // Return the Unix timestamp. Carbon::timestamp is already int; the cast
        // is a typed-API guard with no observable delta under mutation.
        // @pest-mutate-ignore: RemoveIntegerCast
        return (int) Carbon::now()->timestamp;
    }

    /**
     * Format default date with month abbreviations based on AP Style rules
     *
     * @param  Carbon  $date  The date to format
     * @return string The formatted date string
     */
    private function formatDefaultDate(
        Carbon $date
    ): string {
        // Extract full month name (e.g., "January")
        $month = $date->format('F');

        // Extract day without leading zero (e.g., "1", "15")
        $day = $date->format('j');

        // Extract full year (e.g., "2025")
        $year = $date->format('Y');

        // Resolve running-text AP month label from the full English name.
        // Carbon's `F` always matches an enum case, so the nullsafe never sees
        // null on this path (defensive only).
        // @pest-mutate-ignore: RemoveNullSafeOperator
        $monthAbbr = ApStyleGregorianMonth::tryFrom($month)?->runningTextLabel(true) ?? $month;

        // Return formatted date string
        return sprintf('%s %s, %s', $monthAbbr, $day, $year);
    }

    /**
     * Format time with a.m./p.m. and conditional minutes based on AP Style rules
     *
     * @param  Carbon  $date  The date to format
     * @return string The formatted time string
     */
    private function formatTime(
        Carbon $date
    ): string {
        // Extract hour in 12-hour format without leading zero (e.g., "1", "12")
        $hour = $date->format('g');

        // Extract minutes with leading zero (e.g., "00", "23")
        $minute = $date->format('i');

        // Extract period in uppercase (e.g., "AM", "PM")
        $period = $date->format('A');

        // Convert AM/PM to a.m./p.m.
        $period = \str_replace(
            [
                'AM',
                'PM',
            ],
            [
                'a.m.',
                'p.m.',
            ],
            $period
        );

        // ---
        // Don't include minutes if they're 00
        // ---

        // Check if the minutes are 00
        if ($minute === '00') {
            // Return time without minutes
            return sprintf('%s %s', $hour, $period);
        }

        // Return time with minutes included
        return sprintf('%s:%s %s', $hour, $minute, $period);
    }
}
