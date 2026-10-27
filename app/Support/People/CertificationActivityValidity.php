<?php

declare(strict_types=1);

namespace App\Support\People;

use App\Support\Iso8601\Iso8601DesignatorDuration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection as IlluminateCollection;

/**
 * Derives certification activity validity metadata from plain calendar dates.
 *
 * Uses `config('statamic.system.display_timezone')` for interpreting inputs and
 * for the “today” comparison used by `activity_active_query`.
 */
final class CertificationActivityValidity
{
    /**
     * Merge virtual `activity_valid_for` and `activity_active_query` into each
     * certifications replicator row (people collection).
     *
     * An assessment-based certificate row takes only its issued date: it has no
     * `activity_valid_for`, it is active from that date onward, and without an
     * issued date its `activity_active_query` is null rather than false (UAMS-Web/uams-statamic-archive#2973).
     *
     * @param  mixed  $stored  Raw `certifications` field value from the entry
     * @return mixed Augmented list or the original value when not a list of rows
     */
    public function mergeIntoCertificationsRows(mixed $stored): mixed
    {
        // Normalize replicator payload to a plain list of rows
        $rows = $this->normalizeRows($stored);

        // Return the original payload when it is not a replicator list
        if ($rows === null) {
            return $stored;
        }

        // Resolve “today” once in the display timezone for active checks
        $today = $this->todayDateInDisplayTimezone();

        // Build augmented rows with computed keys merged per row
        $out = [];

        foreach ($rows as $row) {
            // Merge computed fields from issued/expires when present
            $out[] = $this->mergeComputedIntoRow(
                $row,
                $today
            );
        }

        return $out;
    }

    /**
     * Parse a Statamic-stored date value into a calendar date at start of day.
     *
     * Accepts `Y-m-d` strings, arrays with a `date` key, or `CarbonInterface`.
     * Datetimes are interpreted in the display timezone; the returned value uses
     * that calendar date at 00:00:00.
     *
     * A string that is not a strict `Y-m-d` date falls back to
     * `CarbonImmutable::parse` only when it starts with an ISO 8601 date, either
     * extended (`2024-05-01`) or basic followed by a `T` (`20240501T0900`), and
     * anything after that date is a time, a `Z` or `z`, or a `+` or `-` offset,
     * in that order. The time follows a `T` (`2024-05-01T09:00`) or a space and
     * carries minutes (`2024-05-01 09:00`); the zone or offset (`Z`, `+05:00`,
     * `-0500`) ends the string. So a time, a zone letter or an offset counts only
     * when it is attached to a date, and text whose only date-like part is a
     * time, a relative word or a zone letter (`Tuesday 10:30`, `tomorrow 10:30`,
     * `Zulu`) is not read as a date taken from the clock, nor can a zone letter
     * admit a date that is refused as ambiguous on its own (`05/01/2024 Z`)
     * (UAMS-Web/uams-statamic-archive#3246). Such a string returns null on either of two routes: it does not
     * have that shape, which also avoids reading an ambiguous locale-specific
     * date, or it does and `CarbonImmutable::parse` throws
     * `InvalidFormatException`, which is read as no date (UAMS-Web/uams-statamic-archive#3010).
     *
     * @param  mixed  $raw  Raw field value from the entry
     * @return ?CarbonImmutable Start of the calendar day in the display timezone
     */
    public function parsePlainDate(mixed $raw): ?CarbonImmutable
    {
        // Resolve the configured Statamic display timezone
        $tz = $this->displayTimezone();

        // Treat null and empty scalar as absent
        // null and '' both fall through to a return null below
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($raw === null || $raw === '') {
            return null;
        }

        // Unwrap single-element wrappers commonly used by Statamic
        if ($raw instanceof IlluminateCollection) {
            $raw = $raw->all();
        }

        // Extract a string date from array shapes Statamic may store
        if (\is_array($raw)) {
            if (! isset($raw['date']) || ! \is_string($raw['date'])) {
                return null;
            }

            $raw = $raw['date'];
        }

        if (! \is_string($raw)) {
            // Accept Carbon-like objects at runtime
            if ($raw instanceof CarbonInterface) {
                return CarbonImmutable::instance($raw)
                    ->timezone($tz)
                    ->startOfDay();
            }

            return null;
        }

        $trimmed = trim($raw);

        // '' matches none of the formats below and also returns null
        // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($trimmed === '') {
            return null;
        }

        // Parse strict calendar `Y-m-d` without time ambiguity
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $trimmed
            ) === 1
        ) {
            /** @var CarbonImmutable $parsed */
            $parsed = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $trimmed,
                $tz
            );

            return $parsed->startOfDay();
        }

        // Allow a datetime only when it starts with an ISO 8601 date, and its
        // time, `Z` or offset follows that date. Text whose only date-like part
        // is a time, a relative word or a zone letter would otherwise be read as
        // a date taken from the clock (UAMS-Web/uams-statamic-archive#3246). A bare date is left to the strict
        // `Y-m-d` branch above
        if (
            preg_match(
                '/^(?:\d{4}-\d{2}-\d{2}|\d{8}(?=T))(?!$)(?:T\d{2}(?::?\d{2}(?::?\d{2}(?:[.,]\d+)?)?)?|\s+\d{2}:\d{2}(?::\d{2}(?:[.,]\d+)?)?)?\s*(?:[zZ]|[+\-]\d{2}:?\d{2})?$/',
                $trimmed
            ) === 1
        ) {
            // Read text Carbon cannot parse as no date, so one such row does not
            // stop the computed fields for the whole person (UAMS-Web/uams-statamic-archive#3010)
            try {
                $parsed = CarbonImmutable::parse(
                    $trimmed,
                    $tz
                )->timezone($tz);
            } catch (InvalidFormatException) {
                return null;
            }

            return $parsed->startOfDay();
        }

        // Reject ambiguous date-only strings that are not ISO `Y-m-d`
        return null;
    }

    /**
     * Build ISO 8601 `PnYnMnD` for [issued, expires] inclusive using end-exclusive
     * date arithmetic.
     *
     * @return ?string Duration string or null when inputs are unusable
     */
    public function activityValidForIso(
        ?CarbonImmutable $issued,
        ?CarbonImmutable $expires
    ): ?string {
        // Require both endpoints for a duration
        if (! $issued instanceof CarbonImmutable || ! $expires instanceof CarbonImmutable) {
            return null;
        }

        // Reject inverted or empty windows for duration output
        if ($issued->gt($expires)) {
            return null;
        }

        // Use the first invalid day (exclusive) as the interval end anchor
        $endExclusive = $expires->addDay()->startOfDay();

        // Reject a non-positive span after normalization
        if (! $issued->lt($endExclusive)) {
            return null;
        }

        return $this->calendarPnYnMnD(
            $issued,
            $endExclusive
        );
    }

    /**
     * Whether “today” in the display timezone falls on an inclusive calendar day
     * between issued and expires (inclusive).
     */
    public function activityActiveQuery(
        ?CarbonImmutable $issued,
        ?CarbonImmutable $expires,
        ?CarbonImmutable $today = null
    ): bool {
        // Resolve today in the display timezone when the caller did not supply it
        $today ??= $this->todayDateInDisplayTimezone();

        // Missing endpoints cannot be active
        if (! $issued instanceof CarbonImmutable || ! $expires instanceof CarbonImmutable) {
            return false;
        }

        // Treat inverted windows as inactive
        // An inverted window has today after the expiry date whenever it is on or after the issue date, so the check below is false too
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($issued->gt($expires)) {
            return false;
        }

        // Compare calendar ordering on the date line only
        return ! $today->lt($issued) && ! $today->gt($expires);
    }

    /**
     * Compute calendar `PnYnMnD` from start to end-exclusive using year, then month,
     * then remaining whole days (Carbon no-overflow stepping).
     */
    public function calendarPnYnMnD(
        CarbonImmutable $start,
        CarbonImmutable $endExclusive
    ): string {
        // Anchor the walk at the inclusive start date
        $cur = $start;

        // Count whole calendar years that fit before the exclusive end
        $years = 0;

        while (true) {
            // Advance one calendar year without overflowing past shorter months
            $next = $cur->addYearsNoOverflow(1);

            // Stop when the next year boundary reaches or passes the exclusive end
            if (! $next->lt($endExclusive)) {
                break;
            }

            $cur = $next;
            $years++;
        }

        // Count whole calendar months after the year phase
        $months = 0;

        while (true) {
            // Advance one calendar month with day clamping (e.g. Jan 31 → Feb 28)
            $next = $cur->addMonthsNoOverflow(1);

            // Stop when the next month boundary reaches or passes the exclusive end
            if (! $next->lt($endExclusive)) {
                break;
            }

            $cur = $next;
            $months++;
        }

        // Count remaining whole days to the exclusive end
        $days = (int) $cur->diffInDays(
            $endExclusive,
            false
        );

        // Build the canonical uppercase designator string
        return Iso8601DesignatorDuration::formatCalendarDateDesignators(
            $years,
            $months,
            $days
        );
    }

    /**
     * Read Statamic display timezone from configuration.
     *
     * @return string IANA timezone identifier
     */
    public function displayTimezone(): string
    {
        $tz = config('statamic.system.display_timezone');

        return \is_string($tz) && $tz !== ''
            ? $tz
            : 'UTC';
    }

    /**
     * Current calendar date at start of day in the display timezone.
     */
    public function todayDateInDisplayTimezone(): CarbonImmutable
    {
        return CarbonImmutable::now($this->displayTimezone())->startOfDay();
    }

    /**
     * Normalize stored replicator rows to a list of arrays, or null when not applicable.
     *
     * @return ?list<array<string, mixed>>
     */
    private function normalizeRows(mixed $stored): ?array
    {
        if ($stored instanceof IlluminateCollection) {
            $stored = $stored->all();
        }

        if (! \is_array($stored)) {
            return null;
        }

        // array_is_list([]) is true, so [] is returned below as well
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($stored === []) {
            return [];
        }

        if (! array_is_list($stored)) {
            return null;
        }

        /** @var list<array<string, mixed>> $stored */
        return $stored;
    }

    /**
     * Merge computed keys into a single certifications row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mergeComputedIntoRow(
        array $row,
        CarbonImmutable $today
    ): array {
        // Parse issued and expires calendar anchors from the row
        $issued = $this->parsePlainDate($row['activity_date_issued'] ?? null);
        $expires = $this->parsePlainDate($row['activity_date_expires'] ?? null);

        // Check whether the row is an assessment-based certificate (UAMS-Web/uams-statamic-archive#2973)
        if (CertificationRowKind::isCertificate($row)) {
            // Ignore any stored expiration: a certificate is issued, not expiring
            $validFor = null;

            // Treat the certificate as active from its issued date onward, and
            // report no status at all without an issued date
            $active = $issued instanceof CarbonImmutable
                ? ! $today->lt($issued)
                : null;
        } else {
            // Compute duration when the pair is valid for encoding
            $validFor = $this->activityValidForIso(
                $issued,
                $expires
            );

            // Compute active flag (false when inputs are missing or invalid)
            $active = $this->activityActiveQuery(
                $issued,
                $expires,
                $today
            );
        }

        // Start from the stored row payload
        $merged = $row;

        // Attach or remove the virtual duration field
        if ($validFor !== null) {
            $merged['activity_valid_for'] = $validFor;
        } else {
            unset($merged['activity_valid_for']);
        }

        // Expose the query flag for templates (null only for an undated certificate)
        $merged['activity_active_query'] = $active;

        return $merged;
    }
}
