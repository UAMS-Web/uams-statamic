<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Julian-style duration totals for {@see QuantitativeValue} duration mode.
 *
 * Virtual `total_years` is merged at augment time; it is not persisted.
 */
final class QuantitativeValueDurationTotalYears
{
    private const float DAYS_PER_JULIAN_YEAR = 365.25;

    /**
     * Sum unit subfields into fractional years (365.25-day year; months as twelfths).
     *
     * @param  array<string, mixed>  $majorData  One major's slice (value, min, or max)
     */
    public static function totalYearsForMajorSlice(array $majorData): float
    {
        $years = 0.0;
        $months = 0.0;
        $weeks = 0.0;
        $days = 0.0;
        $hours = 0.0;
        $minutes = 0.0;
        $seconds = 0.0;

        foreach (QuantitativeValueConfig::UNIT_KEYS as $unit) {
            if (! \array_key_exists($unit, $majorData)) {
                continue;
            }

            $raw = $majorData[$unit];
            if ($raw === null) {
                continue;
            }

            if ($raw === '') {
                continue;
            }

            $n = \is_scalar($raw) ? (float) $raw : 0.0;

            match ($unit) {
                'years' => $years += $n,
                'months' => $months += $n,
                'weeks' => $weeks += $n,
                'days' => $days += $n,
                'hours' => $hours += $n,
                'minutes' => $minutes += $n,
                default => $seconds += $n,
            };
        }

        if (
            $years === 0.0
            && $months === 0.0
            && $weeks === 0.0
            && $days === 0.0
            && $hours === 0.0
            && $minutes === 0.0
            && $seconds === 0.0
        ) {
            return 0.0;
        }

        $dayFraction = $weeks * 7
            + $days
            + $hours / 24
            + $minutes / 1440
            + $seconds / 86400;

        return $years + ($months / 12) + ($dayFraction / self::DAYS_PER_JULIAN_YEAR);
    }

    /**
     * Merge virtual `total_years` under each enabled major; preserves other top-level keys.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function mergeIntoDurationStored(array $stored, QuantitativeValueConfig $c): array
    {
        $out = $stored;

        foreach ($c->majors as $major) {
            $slice = $out[$major] ?? [];

            if (! \is_array($slice)) {
                $slice = [];
            }

            $base = [];

            foreach ($slice as $key => $val) {
                if ($key === 'total_years') {
                    continue;
                }

                $base[$key] = $val;
            }

            /** @var array<string, mixed> $slice */
            $totalYears = self::totalYearsForMajorSlice($slice);
            $out[$major] = \array_merge($base, ['total_years' => \round($totalYears, 2)]);
        }

        return $out;
    }
}
