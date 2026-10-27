<?php

declare(strict_types=1);

namespace App\Support\Iso8601;

/**
 * Builds ISO 8601 duration strings using literal date designators (PnYnMnD).
 *
 * Shared with calendar-based certification validity and available for other
 * callers that need uppercase P/Y/M/D segments (distinct from second-based
 * `PT…HMS` assembly in `FormatDuration`).
 */
final class Iso8601DesignatorDuration
{
    /**
     * Format a calendar duration with year, month, and day designators.
     *
     * @param  int  $years  Non-negative year count
     * @param  int  $months  Non-negative month count
     * @param  int  $days  Non-negative day count
     * @return string e.g. `P40Y5M17D`, `P0Y0M1D`
     */
    public static function formatCalendarDateDesignators(
        int $years,
        int $months,
        int $days
    ): string {
        return 'P'.$years.'Y'.$months.'M'.$days.'D';
    }
}
