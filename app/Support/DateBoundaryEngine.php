<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Server-side mirror of the client date-boundary engine
 * (`resources/js/date-boundary-engine.js`, UAMS-Web/uams-statamic-archive#193/UAMS-Web/uams-statamic-archive#1394).
 *
 * `App\Fieldtypes\Date` adds a "Boundaries & Limits" config section the base CP
 * picker ignores. The Vue component enforces it client-side; this class turns the
 * SAME config into the SAME constraints server-side so a value that bypasses the
 * picker (a scripted or malicious public-form POST, or any non-reka client) is
 * rejected too — `App\Rules\DateBoundary` orchestrates the checks.
 *
 * Everything here is DAY granularity: a "calendar date" is a {@see CarbonImmutable}
 * anchored at UTC midnight ({@see self::parseDay()}), so all comparisons are
 * calendar-day comparisons. Time-of-day is out of scope, matching the client
 * engine — a time-bearing value is compared by its calendar day.
 *
 * The method set intentionally parallels the JS module one-for-one so the two
 * stay in lockstep; keep them aligned when either changes.
 */
final class DateBoundaryEngine
{
    /**
     * Offset units (from the config grid) → Carbon duration keys.
     *
     * @var array<string, string>
     */
    private const array OFFSET_UNIT_TO_DURATION_KEY = [
        'day' => 'days',
        'week' => 'weeks',
        'month' => 'months',
        'year' => 'years',
    ];

    /**
     * Parse an ISO date/datetime string (`2026-01-15`, `2026-01-15T09:30:00Z`), a
     * {@see DateTimeInterface}, or a Carbon to a day-granularity CalendarDate
     * (UTC midnight), or null when it has no valid leading `Y-m-d`.
     *
     * @param  mixed  $value  Raw stored/submitted value
     * @return CarbonImmutable|null The calendar day, or null when unparseable
     */
    public static function parseDay(mixed $value): ?CarbonImmutable
    {
        // A concrete instant carries a calendar day in whatever frame it is in.
        // Date-only values are anchored so their own-frame day is correct; a
        // time-bearing value must be shifted to the display timezone FIRST by the
        // caller ({@see \App\Rules\DateBoundary::dayOf()}), because a raw UTC instant
        // of an evening wall clock would otherwise read as the next UTC day.
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        // Only strings can carry a leading `Y-m-d`; anything else is unparseable.
        if (! \is_string($value)) {
            return null;
        }

        // Require a leading `Y-m-d`; ignore any trailing time/zone portion.
        if (\preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match) !== 1) {
            return null;
        }

        $year = (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];

        // Reject impossible calendar dates (month 13, day 40, Feb 30, …).
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC');
    }

    /**
     * The single offset row from a stacked-grid (`max_rows: 1`) offset config,
     * which may arrive as a one-element list or a bare associative row, or null
     * when unset.
     *
     * @param  mixed  $grid  Stored offset-grid value
     * @return array<string, mixed>|null The offset row, or null
     */
    public static function firstOffsetRow(mixed $grid): ?array
    {
        if (\is_array($grid) && array_is_list($grid)) {
            /** @var array<string, mixed>|null $first */
            $first = $grid[0] ?? null;

            return \is_array($first) ? $first : null;
        }

        if (\is_array($grid) && \array_key_exists('operator', $grid)) {
            /** @var array<string, mixed> $grid */
            return $grid;
        }

        return null;
    }

    /**
     * Add or subtract an offset row from a calendar date. A missing/zero/negative
     * amount or an unknown unit is a no-op (returns the input date).
     *
     * Month/year math is applied WITHOUT overflow (the `false` third argument), so
     * a short-month anchor clamps to the last valid day — `2026-01-31 + 1 month`
     * yields `2026-02-28`, not `2026-03-03`. This mirrors the client engine's reka
     * `CalendarDate.add()/.subtract()`, which clamps; Carbon's default overflows,
     * which would let the server disagree with the picker (a silent bypass or a
     * false-reject) for any 29th–31st or Feb-29 anchor. Days/weeks are exact and
     * unaffected by the flag.
     *
     * @param  CarbonImmutable|null  $date  The date to offset
     * @param  array<string, mixed>|null  $offset  Offset row (`operator`/`number`/`unit`)
     * @return CarbonImmutable|null The offset date, the input date, or null
     */
    public static function applyOffset(?CarbonImmutable $date, ?array $offset): ?CarbonImmutable
    {
        if (! $date instanceof CarbonImmutable || $offset === null) {
            return $date;
        }

        $unit = $offset['unit'] ?? null;
        $durationKey = \is_string($unit) ? (self::OFFSET_UNIT_TO_DURATION_KEY[$unit] ?? null) : null;
        $amountRaw = $offset['number'] ?? null;
        $amount = is_numeric($amountRaw) ? (int) $amountRaw : 0;

        if ($durationKey === null || $amount <= 0) {
            return $date;
        }

        return ($offset['operator'] ?? null) === 'subtract'
            ? $date->sub($durationKey, $amount, false)
            : $date->add($durationKey, $amount, false);
    }

    /**
     * The effective boundary date for one side (`earliest`/`latest`), or null when
     * the boundary is disabled or unresolvable.
     *
     * @param  mixed  $type  none | custom | today | field
     * @param  mixed  $customValue  ISO string for the `custom` type
     * @param  mixed  $fieldValue  the referenced sibling field's value for `field`
     * @param  CarbonImmutable  $today  today's calendar date (injected for testability)
     * @param  array<string, mixed>|null  $offset  add/subtract offset (today/field types)
     * @return CarbonImmutable|null The boundary date, or null
     */
    public static function resolveBoundary(
        mixed $type,
        mixed $customValue,
        mixed $fieldValue,
        CarbonImmutable $today,
        ?array $offset
    ): ?CarbonImmutable {
        if ($type === null || $type === 'none') {
            return null;
        }

        if ($type === 'custom') {
            return self::parseDay($customValue);
        }

        if ($type === 'today') {
            return self::applyOffset($today, $offset);
        }

        if ($type === 'field') {
            $referenced = self::parseDay($fieldValue);

            return $referenced instanceof CarbonImmutable ? self::applyOffset($referenced, $offset) : null;
        }

        return null;
    }

    /**
     * The later of two calendar dates, ignoring nulls (or null when both are null).
     * Used to floor the earliest boundary at "today (+offset)" when
     * `earliest_date_exclude_before_today` is set alongside a custom/field date.
     *
     * @param  CarbonImmutable|null  $a  First date
     * @param  CarbonImmutable|null  $b  Second date
     * @return CarbonImmutable|null The later date, or null
     */
    public static function laterOf(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if (! $a instanceof CarbonImmutable) {
            return $b;
        }

        if (! $b instanceof CarbonImmutable) {
            return $a;
        }

        return $a->greaterThanOrEqualTo($b) ? $a : $b;
    }

    /**
     * The effective earliest boundary from the field config, mirroring the Vue
     * component's `resolveBoundaryFor('earliest')`.
     *
     * @param  array<string, mixed>  $config  Field config
     * @param  CarbonImmutable  $today  today's calendar date
     * @param  mixed  $fieldValue  the referenced sibling field's value for `field`
     * @return CarbonImmutable|null The earliest allowed date, or null
     */
    public static function resolveEarliest(array $config, CarbonImmutable $today, mixed $fieldValue): ?CarbonImmutable
    {
        $type = $config['earliest_date_type'] ?? 'none';

        $boundary = self::resolveBoundary(
            $type,
            $config['earliest_date_custom'] ?? null,
            $fieldValue,
            $today,
            self::firstOffsetRow(
                $type === 'today'
                    ? ($config['earliest_date_today_offset'] ?? null)
                    : ($config['earliest_date_field_offset'] ?? null)
            )
        );

        // `earliest_date_exclude_before_today` floors the earliest boundary at
        // today (+ its offset), whichever is later than the custom/field date.
        if (($config['earliest_date_exclude_before_today'] ?? null) === true) {
            $floor = self::applyOffset($today, self::firstOffsetRow($config['earliest_date_today_offset'] ?? null));

            return self::laterOf($boundary, $floor);
        }

        return $boundary;
    }

    /**
     * The effective latest boundary from the field config, mirroring the Vue
     * component's `resolveBoundaryFor('latest')`.
     *
     * @param  array<string, mixed>  $config  Field config
     * @param  CarbonImmutable  $today  today's calendar date
     * @param  mixed  $fieldValue  the referenced sibling field's value for `field`
     * @return CarbonImmutable|null The latest allowed date, or null
     */
    public static function resolveLatest(array $config, CarbonImmutable $today, mixed $fieldValue): ?CarbonImmutable
    {
        $type = $config['latest_date_type'] ?? 'none';

        return self::resolveBoundary(
            $type,
            $config['latest_date_custom'] ?? null,
            $fieldValue,
            $today,
            self::firstOffsetRow(
                $type === 'today'
                    ? ($config['latest_date_today_offset'] ?? null)
                    : ($config['latest_date_field_offset'] ?? null)
            )
        );
    }

    /**
     * Normalize the `days_of_week` config to a list of allowed weekday keys
     * ('0' Sunday … '6' Saturday), or null when every weekday is allowed (no
     * restriction — empty, absent, or the full seven).
     *
     * @param  mixed  $days  Stored `days_of_week` value
     * @return list<string>|null Allowed weekday keys, or null when unrestricted
     */
    public static function normalizeAllowedDays(mixed $days): ?array
    {
        if (! \is_array($days) || $days === [] || \count($days) >= 7) {
            return null;
        }

        return array_values(array_map(static fn ($day): string => is_scalar($day) ? (string) $day : '', $days));
    }

    /**
     * Normalize the `date_exceptions` replicator value to a list of inclusive
     * blackout ranges (a single date becomes a one-day range). Unparseable or
     * malformed rows are dropped.
     *
     * @param  mixed  $rows  Stored `date_exceptions` replicator value
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}> Blackout ranges
     */
    public static function normalizeExceptions(mixed $rows): array
    {
        if (! \is_array($rows)) {
            return [];
        }

        $ranges = [];

        foreach ($rows as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $type = $row['type'] ?? null;

            if ($type === 'single_date') {
                $date = self::parseDay($row['item'] ?? null);

                if ($date instanceof CarbonImmutable) {
                    $ranges[] = ['start' => $date, 'end' => $date];
                }

                continue;
            }

            if ($type === 'date_range' && \is_array($row['item'] ?? null)) {
                /** @var array<string, mixed> $item */
                $item = $row['item'];
                $start = self::parseDay($item['start'] ?? null);
                $end = self::parseDay($item['end'] ?? null);

                if ($start instanceof CarbonImmutable && $end instanceof CarbonImmutable) {
                    $ranges[] = ['start' => $start, 'end' => $end];
                }
            }
        }

        return $ranges;
    }

    /**
     * Whether a calendar day is unavailable — its weekday is not allowed, or it
     * falls within a blackout exception range (inclusive).
     *
     * @param  CarbonImmutable  $date  The calendar day to test
     * @param  list<string>|null  $allowedDays  Allowed weekday keys, or null when unrestricted
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $exceptions  Blackout ranges
     * @return bool True when the day is unavailable
     */
    public static function isDateUnavailable(CarbonImmutable $date, ?array $allowedDays, array $exceptions): bool
    {
        if ($allowedDays !== null && ! \in_array((string) $date->dayOfWeek, $allowedDays, true)) {
            return true;
        }

        return \array_any(
            $exceptions,
            fn (array $range): bool => $date->greaterThanOrEqualTo($range['start']) && $date->lessThanOrEqualTo($range['end']),
        );
    }
}
