<?php

declare(strict_types=1);

namespace App\Support\Classifieds;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Statamic\Statamic;

/**
 * The one definition of when a `classifieds` listing has expired (UAMS-Web/uams-statamic-archive#2096).
 *
 * A listing stays live through its `expiration_date` and expires the day after.
 * "Today" is the audience's calendar day in `statamic.system.display_timezone`,
 * not the application's UTC day: a listing expiring on the 15th is still live at
 * 21:00 on the 15th in Little Rock, when UTC has already reached the 16th.
 *
 * Both readers use this class, so they cannot disagree: `App\Scopes\LiveClassifieds`
 * keeps a listing on the board index, and the `is_expired` computed field decides
 * what a listing's own page shows.
 */
final class ClassifiedExpiry
{
    /**
     * Today's calendar date in the display timezone, as `Y-m-d`.
     */
    public function today(): string
    {
        return Carbon::now(Statamic::displayTimezone())->toDateString();
    }

    /**
     * The expiration date a newly posted listing gets, as `Y-m-d`: today in the display
     * timezone plus `classifieds.listing_days`.
     */
    public function defaultExpirationDate(): string
    {
        // Read the configured listing period, falling back to 90 days
        $days = config('classifieds.listing_days', 90);

        return Carbon::now(Statamic::displayTimezone())
            ->addDays(is_int($days) ? $days : 90)
            ->toDateString();
    }

    /**
     * Whether a listing with this stored expiration date has expired.
     *
     * @param  mixed  $expirationDate  The stored `expiration_date`: a `Y-m-d` string as the
     *                                 date fieldtype saves it, a Carbon, or empty
     * @return bool True when the date is before today; false when it is today, later, or absent
     */
    public function isExpired(mixed $expirationDate): bool
    {
        // Normalize the stored value to a civil `Y-m-d` date
        $date = $this->civilDate($expirationDate);

        // Treat a listing with no usable date as live, matching the board index
        if ($date === null) {
            return false;
        }

        // Compare civil dates as strings, which order correctly in `Y-m-d` form
        return $date < $this->today();
    }

    /**
     * Read a stored date as its civil `Y-m-d` calendar date, without a timezone shift.
     *
     * @param  mixed  $value  A `Y-m-d` string, a Carbon, or anything else
     * @return string|null The `Y-m-d` date, or null when the value holds no date
     */
    public function civilDate(mixed $value): ?string
    {
        // A Carbon from the date fieldtype sits at midnight in the app timezone
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        // A stored date-only string is already civil; keep only its date part
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
