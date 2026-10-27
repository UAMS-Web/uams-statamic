<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\PartialDateStorageSlots;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: coerce partial-date storage strings into Schema.org Date
 * strings at year, year-month, or full-date precision.
 *
 * Year, month, and day segments are taken from the first two hyphen boundaries
 * (`explode` limit 3) so trailing placeholder runs stay in the day slot.
 *
 * A `date` field reaches a template augmented to a date object rather than its
 * stored `Y-m-d` string, so a `DateTimeInterface` is emitted as a full date.
 *
 * Usage:
 *
 * - `{{ partial_date | format_schema_date }}`
 * - `{{ partial_date | schema_date }}` (alias)
 */
class FormatSchemaDate extends Modifier
{
    /**
     * Register shorthand alias for templates.
     *
     * @return array<int, string> Alternate Antlers handles for this modifier
     */
    #[\Override]
    public static function aliases(): array
    {
        return [
            'schema_date',
        ];
    }

    /**
     * Normalize a partial-date token to `YYYY`, `YYYY-MM`, `YYYY-MM-DD`, or null.
     *
     * @param  mixed  $value  Partial-date string, date object, or `Value`
     *                        wrapping either
     * @param  array<int|string, mixed>  $params  Unused modifier parameters
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return ?string Schema.org-style date string, or null when the year is invalid
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): ?string {
        // Unwrap Statamic Value wrappers when present
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Emit a full calendar date for an augmented `date` field
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Reject non-string values after unwrap
        if (! \is_string($value)) {
            return null;
        }

        // Trim whitespace from the storage token
        $trimmed = trim($value);

        // Return null when nothing remains to format; an empty token also fails
        // the year check below, so this only skips the split
        // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($trimmed === '') {
            return null;
        }

        // Split on hyphens while keeping the remainder in the third segment
        $segments = explode(
            '-',
            $trimmed,
            3
        );

        // Resolve year, month, and day slots from the exploded segments
        $yearPart = $segments[0];
        $monthPart = $segments[1] ?? null;
        $dayPart = $segments[2] ?? null;

        // Classify each slot as valid for Schema.org output
        $yearValid = PartialDateStorageSlots::isValidYearPart($yearPart);
        $monthValid = PartialDateStorageSlots::isValidMonthPart($monthPart);
        $dayValid = PartialDateStorageSlots::isValidDayPart($dayPart);

        // Return null when the calendar year cannot be expressed numerically
        if (! $yearValid) {
            return null;
        }

        // Emit year-only precision when the month slot is unusable
        if (! $monthValid) {
            return $yearPart;
        }

        // Emit year-month precision when the day slot is unusable
        if (! $dayValid) {
            return sprintf('%s-%s', $yearPart, $monthPart);
        }

        // Emit a full ISO calendar date when every slot is usable
        return sprintf('%s-%s-%s', $yearPart, $monthPart, $dayPart);
    }
}
