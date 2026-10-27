<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\PartialDateStorageSlots;
use Illuminate\Support\Carbon;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: decide whether a partial-date completion token represents
 * training that is complete as of today.
 *
 * Gates residency/fellowship credential emission. A present completion date is
 * treated as completed; partial precision (year-only or year-month, including
 * the current year) always counts as completed, and only a fully-specified
 * date strictly after today is excluded as future. An absent or unusable date
 * (no usable year) means in-progress training, which emits no credential.
 *
 * Usage: `{{ if ( activity_end_date | partial_date_completed ) }}…{{ /if }}`
 */
class PartialDateCompleted extends Modifier
{
    /**
     * Whether the partial-date token is on or before today at its given precision.
     *
     * @param  mixed  $value  Partial-date storage token, or `Value` wrapping one
     * @param  array<int|string, mixed>  $params  Unused modifier parameters
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return bool True when the date is present and not strictly in the future
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): bool {
        // Unwrap Statamic Value wrappers when present
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Treat non-string tokens as in-progress
        if (! \is_string($value)) {
            return false;
        }

        // Trim whitespace from the storage token
        $trimmed = trim($value);

        // Treat an empty token as in-progress (no completion date)
        if ($trimmed === '') {
            return false;
        }

        // Split on hyphens while keeping the remainder in the third segment
        $segments = explode(
            '-',
            $trimmed,
            3
        );

        // ---
        // Resolve year, month, and day slots from the exploded segments
        // ---

        // Year part
        $yearPart = $segments[0];

        // Month part
        $monthPart = $segments[1] ?? null;

        // Day part
        $dayPart = $segments[2] ?? null;

        // Require a usable calendar year to count as completed
        if (! PartialDateStorageSlots::isValidYearPart($yearPart)) {
            return false;
        }

        // Treat year-only precision as completed
        if (! PartialDateStorageSlots::isValidMonthPart($monthPart)) {
            return true;
        }

        // Treat year-month precision as completed
        if (! PartialDateStorageSlots::isValidDayPart($dayPart)) {
            return true;
        }

        // Compare a fully-specified date against today (zero-padded ISO order)
        return sprintf('%s-%s-%s', $yearPart, $monthPart, $dayPart) <= Carbon::now()->format('Y-m-d');
    }
}
