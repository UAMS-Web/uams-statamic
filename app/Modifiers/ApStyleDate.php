<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\PartialDateStorageSlots;
use Carbon\Carbon;
use Carbon\Month;
use Carbon\WeekDay;
use DateTimeInterface;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;
use Throwable;
use UAMSWeb\Aether\Enums\ApStyleGregorianMonth;

/**
 * Formats a date value as AP Style.
 *
 * Supports Statamic partial-date storage strings (hyphen slots, placeholders),
 * compact digit-only tokens (`YYYYMM`, `YYYYMMDD`), plain `YYYY`, and
 * Carbon/datetime values. Returns null when a partial token cannot be shown.
 *
 * Month labels (running text and tabular material) are defined on
 * `ApStyleGregorianMonth` per the AP Stylebook. In running text, abbreviated
 * months (Jan., Feb., Aug., Sept., Oct., Nov., Dec.) apply only when the
 * formatted phrase includes a specific calendar day; month-only and month-year
 * phrases use spelled-out month names. Tabular material always uses the
 * three-letter forms without periods.
 *
 * Optional parameters:
 *   - First parameter: Include time (if enabled, separates date from time with
 *     ', at ' and formats time using the AP Style time formatting modifier).
 *     Applies only to datetime-capable values, not partial storage strings.
 *   - Second parameter: Tabular material (if enabled, uses 3-letter month
 *     abbreviations without periods: Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep,
 *     Oct, Nov, Dec)
 *
 * @example
 * {{ date | ap_style_date }}
 * {{ date | ap_style_date:true }}
 * {{ date | ap_style_date:false:true }}
 * {{ date | ap_style_date:true:true }}
 */
class ApStyleDate extends Modifier
{
    /**
     * Modify a value.
     *
     * @param  mixed  $value  The value to be modified
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @param  array<string, mixed>  $context  Contextual values
     * @return string|null AP Style date string, empty string when input empty,
     *                     or null when a partial token cannot be formatted
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): ?string {
        // Unwrap Statamic Value wrappers when present
        if ($value instanceof Value) {
            // Replace wrapper with underlying raw value
            $value = $value->value();
        }

        // Preserve explicit empty inputs
        if (
            in_array($value, [null, ''], true)
        ) {
            // Return empty string for optional empty fields
            return '';
        }

        // Normalize compact numeric scalars into strings before routing grammar
        if (\is_int($value) || \is_float($value)) {
            // Cast digits-only scalars so compact tokens hit partial parsing
            $value = (string) $value;
        }

        // Check if first parameter (time inclusion) is enabled
        $includeTime = isset($params[0])
            && (
                in_array($params[0], [true, 'true', 1, '1'], true)
            );

        // Check if second parameter (tabular material) is enabled
        $isTabular = isset($params[1])
            && (
                in_array($params[1], [true, 'true', 1, '1'], true)
            );

        // Resolve partial-storage strings without Carbon.parse semantics, unless
        // the string carries a time of day
        if (
            \is_string($value)
            && ! $this->shouldParseAsDateTimeInstant($value)
            && $this->isPartialStorageCandidate($value)
        ) {
            // Attempt AP formatting from slot grammar
            return $this->formatPartialStorageString(
                trim($value),
                $isTabular
            );
        }

        // Use Carbon for objects, instant-like strings and prose dates
        return $this->formatFromCarbonValue(
            $value,
            $includeTime,
            $isTabular,
            $context
        );
    }

    /**
     * Whether the value should be parsed as an instant (time-capable) string.
     *
     * @param  string  $value  Raw modifier string
     * @return bool True when the string carries explicit time-of-day semantics
     */
    private function shouldParseAsDateTimeInstant(
        string $value
    ): bool {
        // Trim whitespace before matching instant markers
        $trimmed = trim($value);

        // Detect ISO-8601 time separator usage
        if (
            preg_match(
                '/T\d{2}:/',
                $trimmed
            ) === 1
        ) {
            return true;
        }

        // Detect calendar date followed by a clock field
        return preg_match(
            '/^\d{4}-\d{2}-\d{2}\s+\d/',
            $trimmed
        ) === 1;
    }

    /**
     * Whether the trimmed string should use partial-date slot parsing first.
     *
     * @param  string  $trimmed  Trimmed input string
     * @return bool True when grammar matches partial storage or fixed-width dates
     */
    private function isPartialStorageCandidate(
        string $trimmed
    ): bool {
        // Treat bare four-digit years as partial slots (year-only output)
        if (
            preg_match(
                '/^\d{4}$/',
                $trimmed
            ) === 1
        ) {
            return true;
        }

        // Treat compact digit-only month or full-date tokens as partial slots
        if (
            preg_match(
                '/^\d{6}$/',
                $trimmed
            ) === 1
            || preg_match(
                '/^\d{8}$/',
                $trimmed
            ) === 1
        ) {
            return true;
        }

        // Hyphenated tokens participate in explode(slot) grammar
        if (str_contains($trimmed, '-')) {
            return true;
        }

        // Placeholder letters exclude naive Carbon parsing paths
        return preg_match(
            '/[Xx]/',
            $trimmed
        ) === 1;
    }

    /**
     * Format using Carbon for augmented dates and legacy prose timestamps.
     *
     * @param  mixed  $value  Carbon-parseable scalar or object
     * @param  bool  $includeTime  Whether to append AP Style time
     * @param  bool  $isTabular  Whether tabular month forms apply
     * @param  array<string, mixed>  $context  Modifier context for time helper
     * @return string AP Style output or empty string when parsing fails
     */
    private function formatFromCarbonValue(
        mixed $value,
        bool $includeTime,
        bool $isTabular,
        array $context
    ): string {
        // Attempt to parse into an immutable instant for formatting
        try {
            // Normalize objects through Carbon for formatting helpers; $value is a
            // genuinely mixed modifier input whose unparseable shapes are caught below
            /** @var Month|WeekDay|DateTimeInterface|float|int|string|null $value */
            $date = Carbon::parse($value);
        } catch (Throwable) {
            // Treat broken prose timestamps as missing output
            return '';
        }

        // Resolve AP month case from the English month name, which backs each case
        $apMonth = ApStyleGregorianMonth::from($date->format('F'));

        // Extract day without leading zero (e.g., "1", "15")
        $day = $date->format('j');

        // Extract full Gregorian year for AP output
        $year = $date->format('Y');

        // Pick tabular vs running-text month label (always day-inclusive here)
        $monthAbbr = $isTabular
            ? $apMonth->tabularLabel()
            : $apMonth->runningTextLabel(true);

        // Initialize composed calendar AP string with comma after day
        $dateString = sprintf('%s %s, %s', $monthAbbr, $day, $year);

        // Append clock prose only when callers opt into time output
        if ($includeTime) {
            // Build AP Style clock helper for the same parsed instant
            $timeModifier = new ApStyleTime;

            // Render AP clock phrase from the parsed instant
            $timeString = $timeModifier->index(
                $value,
                [],
                $context
            );

            // Append separator matching AP dateline conventions
            $dateString .= ', at '.$timeString;
        }

        return $dateString;
    }

    /**
     * Format a partial-date storage token using slot validity rules.
     *
     * @param  string  $trimmed  Trimmed storage token
     * @param  bool  $isTabular  Whether tabular month forms apply
     * @return string|null AP Style output or null when nothing meaningful remains
     */
    private function formatPartialStorageString(
        string $trimmed,
        bool $isTabular
    ): ?string {
        // Normalize compact digit-only tokens into hyphen explode semantics
        $slotSource = $this->normalizeCompactDigitToken($trimmed) ?? $trimmed;

        // Split on hyphens while preserving trailing placeholder runs in day slot
        $segments = explode(
            '-',
            $slotSource,
            3
        );

        // Resolve year, month, and day slots from exploded segments
        $yearPart = $segments[0];
        $monthPart = $segments[1] ?? null;
        $dayPart = $segments[2] ?? null;

        // Classify exploded slots using Schema.org-compatible predicates
        $yearValid = PartialDateStorageSlots::isValidYearPart($yearPart);
        $monthValid = PartialDateStorageSlots::isValidMonthPart($monthPart);
        $dayValid = PartialDateStorageSlots::isValidDayPart($dayPart);

        // Compose output when explode yields a usable month slot directly. A `--`
        // gap leaves the month slot empty, which is never a valid month
        if (
            $monthValid
            && \is_string($monthPart)
        ) {
            // Emit AP string when month digits calendar-validated
            return $this->composeApFromSlotValidity(
                $yearValid,
                $yearPart,
                $monthPart,
                $dayValid,
                $dayPart,
                $isTabular
            );
        }

        // Recover trailing MM-DD after uncommon year prefixes when explode fails month
        $trailMatch = null;

        // Attempt regex capture only when month stayed unusable after explode
        if (
            preg_match(
                '/^(.+)-(\d{2})-(\d{2})$/',
                $slotSource,
                $trailMatch
            ) === 1
        ) {
            // Parse numeric month and day candidates from captured groups
            $trailMonth = (int) $trailMatch[2];
            $trailDay = (int) $trailMatch[3];

            // Validate calendar month/day pairing against a neutral leap year
            if (
                $this->isCalendarMonthAndDayPlausible(
                    $trailMonth,
                    $trailDay
                )
            ) {
                // Decide unknown-year output based on year slot validity
                return $this->composeMonthDayWithoutYear(
                    $trailMonth,
                    $trailDay,
                    $isTabular
                );
            }
        }

        // Recover leading-dash compact tokens (`-----05`, `-----05-14`)
        $leadMatch = null;

        // Match compact unknown-year tokens starting with hyphen runs
        if (
            preg_match(
                '/^-+(\d{2})(?:-(\d{2}))?$/',
                $slotSource,
                $leadMatch
            ) === 1
        ) {
            // Parse leading-match month (required) and optional day fields
            $leadMonth = (int) $leadMatch[1];
            $leadDay = isset($leadMatch[2])
                ? (int) $leadMatch[2]
                : null;

            // Validate optional day against the resolved month when present
            if (
                $leadDay === null
                || $this->isCalendarMonthAndDayPlausible(
                    $leadMonth,
                    $leadDay
                )
            ) {
                // Emit month-only or month-day AP strings without a known year
                return $this->composeMonthDayWithoutYear(
                    $leadMonth,
                    $leadDay,
                    $isTabular
                );
            }
        }

        // Emit year-only tail when explode preserved a known calendar year
        if ($yearValid) {
            // Return bare Gregorian year when higher slots stayed unknown
            return $yearPart;
        }

        // Signal callers that nothing meaningful could be rendered
        return null;
    }

    /**
     * Normalize six- or eight-digit digit-only tokens into hyphen slot strings.
     *
     * @param  string  $trimmed  Trimmed storage token
     * @return string|null Hyphenated pseudo-token or null when not compact digits
     */
    private function normalizeCompactDigitToken(
        string $trimmed
    ): ?string {
        // Expand eight-digit `YYYYMMDD` tokens into hyphen slots
        if (
            preg_match(
                '/^\d{8}$/',
                $trimmed
            ) === 1
        ) {
            // Slice fixed-width Gregorian fields without locale parsing
            return substr($trimmed, 0, 4)
                .'-'
                .substr($trimmed, 4, 2)
                .'-'
                .substr($trimmed, 6);
        }

        // Expand six-digit `YYYYMM` tokens into year-month hyphen slots
        if (
            preg_match(
                '/^\d{6}$/',
                $trimmed
            ) === 1
        ) {
            // Slice fixed-width year and month segments
            return substr($trimmed, 0, 4)
                .'-'
                .substr($trimmed, 4);
        }

        // Indicate the caller should keep the original hyphen token
        return null;
    }

    /**
     * Whether month/day integers describe a real calendar day in some leap year.
     *
     * @param  int  $month  Gregorian month number (1-12)
     * @param  int  $day  Gregorian day-of-month number (1-31)
     * @return bool True when `checkdate` accepts the pair on year 2000 (leap-capable)
     */
    private function isCalendarMonthAndDayPlausible(
        int $month,
        int $day
    ): bool {
        // Delegate leap-aware validation using a neutral leap-capable anchor year
        return checkdate($month, $day, 2000);
    }

    /**
     * Compose AP output once explode classified slot validity flags.
     *
     * @param  bool  $yearValid  Whether year digits fully known
     * @param  mixed  $yearPart  Raw year segment text when valid four-digit numeric
     * @param  string  $monthPart  Two-digit month segment text
     * @param  bool  $dayValid  Whether day digits fully known
     * @param  mixed  $dayPart  Raw day segment text when two-digit numeric
     * @param  bool  $isTabular  Tabular month abbreviation toggle
     * @return string|null Rendered AP calendar fragment or null when unusable
     */
    private function composeApFromSlotValidity(
        bool $yearValid,
        mixed $yearPart,
        string $monthPart,
        bool $dayValid,
        mixed $dayPart,
        bool $isTabular
    ): ?string {
        // Resolve numeric month for calendar checks
        $monthNum = (int) $monthPart;

        // Resolve AP month case from the numeric month slot
        $apMonth = ApStyleGregorianMonth::fromMonthNumber($monthNum);

        // Two digits alone do not make a month (`00`, `13`)
        if (! $apMonth instanceof ApStyleGregorianMonth) {
            return null;
        }

        // Known year + month + day yields comma-separated AP calendar dates
        if (
            $yearValid
            && \is_string($yearPart)
            && $dayValid
            && \is_string($dayPart)
            && checkdate(
                $monthNum,
                (int) $dayPart,
                (int) $yearPart
            )
        ) {
            // Resolve month label when a specific day appears in the phrase
            $monthAbbr = $this->monthLabel(
                $apMonth,
                $isTabular,
                true
            );

            // Emit AP calendar date with comma between day and year
            return $monthAbbr
                .' '
                .(int) $dayPart
                .', '
                .$yearPart;
        }

        // Known year + month yields AP month-year pair without comma after month
        if (
            $yearValid
            && \is_string($yearPart)
        ) {
            // Resolve month label for month-year precision without a day
            $monthAbbr = $this->monthLabel(
                $apMonth,
                $isTabular,
                false
            );

            // Emit AP month plus Gregorian year without trailing comma
            return sprintf('%s %s', $monthAbbr, $yearPart);
        }

        // Unknown year but known month + day yields AP month-day pair
        if (
            $dayValid
            && \is_string($dayPart)
            && $this->isCalendarMonthAndDayPlausible(
                $monthNum,
                (int) $dayPart
            )
        ) {
            // Resolve month label when pairing month with a specific day
            $monthAbbr = $this->monthLabel(
                $apMonth,
                $isTabular,
                true
            );

            // Emit AP month-day pair without year anchor
            return $monthAbbr.' '.(int) $dayPart;
        }

        // Unknown year with month-only precision yields bare month label
        return $this->monthLabel(
            $apMonth,
            $isTabular,
            false
        );
    }

    /**
     * Compose AP month/day strings when no calendar year is known.
     *
     * @param  int  $monthNum  Gregorian month number (1-12)
     * @param  int|null  $dayNum  Day-of-month already checked against the month,
     *                            or null when unknown
     * @param  bool  $isTabular  Tabular month abbreviation toggle
     * @return string|null Rendered AP fragment or null when validation fails
     */
    private function composeMonthDayWithoutYear(
        int $monthNum,
        ?int $dayNum,
        bool $isTabular
    ): ?string {
        // Resolve AP month case from the month number
        $apMonth = ApStyleGregorianMonth::fromMonthNumber($monthNum);

        // Reject a month number outside the calendar, independent of day precision
        if (! $apMonth instanceof ApStyleGregorianMonth) {
            return null;
        }

        // Emit month-only AP fragment when day stays unspecified
        if ($dayNum === null) {
            return $this->monthLabel(
                $apMonth,
                $isTabular,
                false
            );
        }

        // Emit month-day pair with abbreviated month once a day is present; callers
        // have already checked the day against the month
        return $this->monthLabel(
            $apMonth,
            $isTabular,
            true
        ).' '.$dayNum;
    }

    /**
     * Pick the AP Style label for a month.
     *
     * @param  ApStyleGregorianMonth  $apMonth  Month to label
     * @param  bool  $isTabular  Tabular month abbreviation toggle
     * @param  bool  $includesSpecificDayInOutput  True when the formatted phrase
     *                                             includes a calendar day number
     * @return string Month label for AP copy or tables
     */
    private function monthLabel(
        ApStyleGregorianMonth $apMonth,
        bool $isTabular,
        bool $includesSpecificDayInOutput
    ): string {
        // Prefer tabular labels whenever tabular material is requested
        if ($isTabular) {
            return $apMonth->tabularLabel();
        }

        // Otherwise pick spelled-out vs abbreviated running text by day presence
        return $apMonth->runningTextLabel($includesSpecificDayInOutput);
    }
}
