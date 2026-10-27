<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier that formats an integer or float duration (seconds) into
 * common formats.
 *
 * Usage examples:
 *
 * In Antlers, each `:` after the modifier name adds the next positional
 * parameter: `display`, then `precision`. The form `display="..."` in the
 * pipe chain is not supported (use a single segment per parameter). To
 * change only `precision` while keeping default ISO output, pass
 * `iso_8601` as the first segment.
 *
 * Examples at `3723.5` seconds (1h 2m 3.5s input), default `precision`
 * `0`:
 *
 * - `{{ duration | format_duration }}` -> `PT1H2M4S`
 * - `{{ duration | format_duration:hms }}` -> `01:02:04`
 * - `{{ duration | format_duration:minutes_seconds }}` -> `62:04`
 * - `{{ duration | format_duration:human_short }}` -> `1h 2m 4s`
 * - `{{ duration | format_duration:human_long }}` -> `1 hour, 2 minutes, 4 seconds`
 *
 * `precision` only (explicit default `display`, then `precision`; input
 * `3723.5` seconds except where noted):
 *
 * - `{{ duration | format_duration:iso_8601:-1 }}` -> `PT1H2M3.5S`
 * - `{{ duration | format_duration:iso_8601:1 }}` (input `3723.556`) -> `PT1H2M3.6S`
 *
 * `display` and `precision` (second segment is `precision`):
 *
 * - `{{ duration | format_duration:human_long:1 }}` (input `3723.5`) -> `1 hour, 2 minutes, 3.5 seconds`
 * - `{{ duration | format_duration:hms:-1 }}` (input `3723.5`) -> `01:02:03.5`
 *
 * Parameters:
 *
 * - `display` (string, default: `iso_8601`)
 *   - `iso_8601`: ISO 8601 duration (e.g. `PT1H2M4S` at `3723.5`s, `precision` `0`)
 *   - `hms`: clock format (e.g. `01:02:04` at `3723.5`s, `precision` `0`)
 *   - `minutes_seconds`: minutes:seconds (e.g. `62:04` at `3723.5`s, `precision` `0`)
 *   - `human_short`: short text (e.g. `1h 2m 4s` at `3723.5`s, `precision` `0`)
 *   - `human_long`: long text (e.g. `1 hour, 2 minutes, 4 seconds` at `3723.5`s, `precision` `0`)
 *
 * - `precision` (int, default: `0`)
 *   - Applies to all display modes, including `iso_8601`
 *   - `-1` disables rounding and preserves available decimal precision
 *   - Trailing zeros are removed; whole values drop decimal places
 *
 * Calendar `PnYnMnD` strings (date designators only) are produced by
 * `App\Support\Iso8601\Iso8601DesignatorDuration` and
 * `App\Support\People\CertificationActivityValidity`, not this seconds-based
 * modifier.
 */
class FormatDuration extends Modifier
{
    /**
     * Format seconds according to modifier parameters.
     *
     * @param  mixed  $value  Duration in seconds
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @param  array<int|string, mixed>  $context  Modifier context (unused)
     * @return string Formatted duration string
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Cast input value to a float duration
        $seconds = is_numeric($value) ? (float) $value : 0.0;

        // Resolve display mode from parameters
        $display = $this->resolveDisplay($params);

        // Resolve precision from parameters
        $precision = $this->resolvePrecision($params);

        // Check if ISO 8601 output was requested
        if ($display === 'iso_8601') {
            // Return the ISO 8601 duration representation
            return $this->formatIso8601Duration(
                $seconds,
                $precision
            );
        }

        // Build normalized time components for non-ISO displays
        $components = $this->buildNormalizedComponents(
            $seconds,
            $precision
        );

        // Resolve final formatted output by display mode
        $result = $this->formatDisplayValue(
            $display,
            $components
        );

        // Free temporary variables before returning
        unset(
            $components,
            $display,
            $precision,
            $seconds
        );

        // Return the formatted output
        return $result;
    }

    /**
     * Resolve display mode from modifier parameters.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @return string Canonical display mode
     */
    private function resolveDisplay(
        array $params
    ): string {
        // Resolve raw display parameter from named or positional input
        $rawDisplay = $this->getParam(
            $params,
            'display',
            0,
            'iso_8601'
        );

        // Normalize display text for alias matching
        $normalizedDisplay = strtolower(
            str_replace(
                '-',
                '_',
                trim(is_scalar($rawDisplay) ? (string) $rawDisplay : '')
            )
        );

        // Build aliases for supported display modes
        $aliases = [
            'iso' => 'iso_8601',
            'iso8601' => 'iso_8601',
            'iso_8601' => 'iso_8601',
            'duration' => 'iso_8601',
            'hms' => 'hms',
            'clock' => 'hms',
            'hh_mm_ss' => 'hms',
            'minutes_seconds' => 'minutes_seconds',
            'mm_ss' => 'minutes_seconds',
            'human_short' => 'human_short',
            'short' => 'human_short',
            'human_long' => 'human_long',
            'long' => 'human_long',
        ];

        // Return default display mode when alias is unknown
        return $aliases[$normalizedDisplay] ?? 'iso_8601';
    }

    /**
     * Resolve precision from modifier parameters.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @return int Precision for non-ISO formatting
     */
    private function resolvePrecision(
        array $params
    ): int {
        // Resolve raw precision parameter from named or positional input
        $rawPrecision = $this->getParam(
            $params,
            'precision',
            1,
            0
        );

        // Cast raw precision to integer
        $precision = is_numeric($rawPrecision) ? (int) $rawPrecision : 0;

        // Check if precision is below minimum supported value
        if ($precision < -1) {
            // Clamp precision to no-rounding sentinel value
            $precision = -1;
        }

        // Free temporary parameter value
        unset($rawPrecision);

        // Return normalized precision
        return $precision;
    }

    /**
     * Build normalized duration components for non-ISO formatting.
     *
     * @param  float  $seconds  Duration in seconds
     * @param  int  $precision  Decimal precision or -1 for no rounding
     * @return array<string, int|string> Component map
     */
    private function buildNormalizedComponents(
        float $seconds,
        int $precision
    ): array {
        // Resolve sign prefix for negative durations
        $sign = $seconds < 0 ? '-' : '';

        // Resolve absolute duration value for component calculations
        $absoluteSeconds = abs($seconds);

        // Resolve whole-hour component from absolute seconds
        $hours = (int) floor($absoluteSeconds / 3600);

        // Resolve remaining seconds after extracting hours
        $remainingAfterHours = $absoluteSeconds - ($hours * 3600);

        // Resolve whole-minute component from remaining seconds
        $minutes = (int) floor($remainingAfterHours / 60);

        // Resolve seconds remainder after extracting minutes
        $secondsValue = $remainingAfterHours - ($minutes * 60);

        // Check if rounding should be applied
        if ($precision !== -1) {
            // Round seconds remainder using configured precision
            $secondsValue = round(
                $secondsValue,
                $precision
            );
        }

        // Check if rounded seconds overflowed into the next minute
        if ($secondsValue >= 60) {
            // Carry one minute from seconds overflow
            $secondsValue -= 60;
            $minutes++;
        }

        // Check if minutes overflowed into the next hour
        if ($minutes >= 60) {
            // Carry one hour from minutes overflow
            $hours += (int) floor($minutes / 60);
            $minutes %= 60;
        }

        // Resolve total minutes component for minutes:seconds display
        $totalMinutes = ($hours * 60) + $minutes;

        // Resolve normalized seconds text using precision rules
        $secondsText = $this->formatNumberString(
            $secondsValue,
            $precision
        );

        // Resolve padded seconds text for clock-like displays
        $paddedSecondsText = $this->padSecondsText($secondsText);

        // Resolve padded minutes text for hms display
        $paddedMinutesText = str_pad(
            (string) $minutes,
            2,
            '0',
            STR_PAD_LEFT
        );

        // Resolve padded hours text for hms display
        $paddedHoursText = str_pad(
            (string) $hours,
            2,
            '0',
            STR_PAD_LEFT
        );

        // Resolve padded total-minutes text for minutes:seconds display
        $paddedTotalMinutesText = str_pad(
            (string) $totalMinutes,
            2,
            '0',
            STR_PAD_LEFT
        );

        // Return normalized component map for display formatting
        return [
            'hours' => $hours,
            'minutes' => $minutes,
            'padded_hours' => $paddedHoursText,
            'padded_minutes' => $paddedMinutesText,
            'padded_total_minutes' => $paddedTotalMinutesText,
            'padded_seconds' => $paddedSecondsText,
            'seconds' => $secondsText,
            'sign' => $sign,
        ];
    }

    /**
     * Format output string for the selected display mode.
     *
     * @param  string  $display  Canonical display mode
     * @param  array<string, int|string>  $components  Normalized components
     * @return string Formatted duration output
     */
    private function formatDisplayValue(
        string $display,
        array $components
    ): string {
        // Resolve sign prefix for output
        $sign = (string) $components['sign'];

        // Check if hms clock display was requested
        if ($display === 'hms') {
            // Build the hms clock string
            $result = \sprintf(
                '%s%s:%s:%s',
                $sign,
                $components['padded_hours'],
                $components['padded_minutes'],
                $components['padded_seconds']
            );

            // Return hms clock output
            return $result;
        }

        // Check if minutes:seconds display was requested
        if ($display === 'minutes_seconds') {
            // Build the minutes:seconds clock string
            $result = \sprintf(
                '%s%s:%s',
                $sign,
                $components['padded_total_minutes'],
                $components['padded_seconds']
            );

            // Return minutes:seconds output
            return $result;
        }

        // Check if short human display was requested
        if ($display === 'human_short') {
            // Build short human-readable duration
            return $this->formatHumanShort(
                $sign,
                (int) $components['hours'],
                (int) $components['minutes'],
                (string) $components['seconds']
            );
        }

        // Build long human-readable duration as fallback
        return $this->formatHumanLong(
            $sign,
            (int) $components['hours'],
            (int) $components['minutes'],
            (string) $components['seconds']
        );
    }

    /**
     * Format ISO 8601 duration output.
     *
     * @param  float  $seconds  Duration in seconds
     * @param  int  $precision  Decimal precision or -1 for no rounding
     * @return string ISO 8601 duration string
     */
    private function formatIso8601Duration(
        float $seconds,
        int $precision
    ): string {
        // Build normalized duration components using precision rules
        $components = $this->buildNormalizedComponents(
            $seconds,
            $precision
        );

        // Resolve sign prefix from normalized components
        $sign = (string) $components['sign'];

        // Resolve hour component from normalized components
        $hours = (int) $components['hours'];

        // Resolve minute component from normalized components
        $minutes = (int) $components['minutes'];

        // Resolve seconds text from normalized components
        $secondsText = (string) $components['seconds'];

        // Initialize ISO 8601 time parts collection
        $parts = [];

        // Add hour component when non-zero
        if ($hours > 0) {
            $parts[] = $hours.'H';
        }

        // Add minute component when non-zero
        if ($minutes > 0) {
            $parts[] = $minutes.'M';
        }

        // Add seconds when non-zero or when no larger units exist
        if (
            $secondsText !== '0'
            || $parts === []
        ) {
            $parts[] = $secondsText.'S';
        }

        // Build and return the full ISO 8601 duration
        return $sign.'PT'.implode('', $parts);
    }

    /**
     * Format short human-readable duration.
     *
     * @param  string  $sign  Sign prefix
     * @param  int  $hours  Hour component
     * @param  int  $minutes  Minute component
     * @param  string  $seconds  Seconds component text
     * @return string Short human-readable duration
     */
    private function formatHumanShort(
        string $sign,
        int $hours,
        int $minutes,
        string $seconds
    ): string {
        // Initialize short-format parts collection
        $parts = [];

        // Add hour part when non-zero
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        // Add minute part when non-zero
        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        // Add seconds part when non-zero or when all parts are empty
        if (
            $seconds !== '0'
            || $parts === []
        ) {
            $parts[] = $seconds.'s';
        }

        // Build and return short human-readable duration
        return $sign.implode(' ', $parts);
    }

    /**
     * Format long human-readable duration with pluralized labels.
     *
     * @param  string  $sign  Sign prefix
     * @param  int  $hours  Hour component
     * @param  int  $minutes  Minute component
     * @param  string  $seconds  Seconds component text
     * @return string Long human-readable duration
     */
    private function formatHumanLong(
        string $sign,
        int $hours,
        int $minutes,
        string $seconds
    ): string {
        // Initialize long-format parts collection
        $parts = [];

        // Add hour part when non-zero
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        // Add minute part when non-zero
        if ($minutes > 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }

        // Resolve whether the seconds component is singular
        $isSingularSeconds = $seconds === '1';

        // Add seconds part when non-zero or when all parts are empty
        if (
            $seconds !== '0'
            || $parts === []
        ) {
            $parts[] = $seconds.' '.($isSingularSeconds ? 'second' : 'seconds');
        }

        // Build and return long human-readable duration
        return $sign.implode(', ', $parts);
    }

    /**
     * Pad seconds text for clock-like displays.
     *
     * @param  string  $secondsText  Normalized seconds text
     * @return string Padded seconds text
     */
    private function padSecondsText(
        string $secondsText
    ): string {
        // Check if seconds include a decimal fraction
        if (str_contains($secondsText, '.')) {
            // Split seconds into whole and fractional parts
            $parts = explode(
                '.',
                $secondsText,
                2
            );

            // Pad the whole seconds part to two digits
            $wholePart = str_pad(
                $parts[0],
                2,
                '0',
                STR_PAD_LEFT
            );

            // Return padded seconds with fractional suffix
            return sprintf('%s.%s', $wholePart, $parts[1]);
        }

        // Return whole seconds padded to two digits
        return str_pad(
            $secondsText,
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Format numeric text using precision and zero-trimming rules.
     *
     * @param  float  $value  Numeric value to format
     * @param  int  $precision  Decimal places or -1 for no rounding
     * @return string Normalized numeric text
     */
    private function formatNumberString(
        float $value,
        int $precision
    ): string {
        // Render numeric text with or without rounding
        $renderedNumber = $precision === -1
            ? \sprintf(
                '%.14F',
                $value
            )
            : number_format(
                $value,
                $precision,
                '.',
                ''
            );

        // Initialize trimmed output with rendered numeric text
        $trimmedNumber = $renderedNumber;

        // Check if the rendered value has a decimal point
        if (str_contains($trimmedNumber, '.')) {
            // Trim trailing zeros from the decimal portion
            $trimmedNumber = rtrim(
                $trimmedNumber,
                '0'
            );

            // Trim trailing decimal point when decimals are removed
            $trimmedNumber = rtrim(
                $trimmedNumber,
                '.'
            );
        }

        // Check if trimming produced empty or negative-zero output
        if (
            $trimmedNumber === ''
            || $trimmedNumber === '-0'
        ) {
            // Return normalized zero output
            return '0';
        }

        // Return normalized numeric text
        return $trimmedNumber;
    }

    /**
     * Resolve named or positional parameter with a default fallback.
     *
     * @param  array<int|string, mixed>  $params  Modifier parameters
     * @param  string  $name  Named key
     * @param  int  $index  Positional index fallback
     * @param  mixed  $default  Default value
     * @return mixed Resolved parameter value
     */
    private function getParam(
        array $params,
        string $name,
        int $index,
        mixed $default
    ): mixed {
        // Check if named parameter was provided
        if (\array_key_exists($name, $params)) {
            // Return named parameter value
            return $params[$name];
        }

        // Check if positional parameter was provided
        if (\array_key_exists($index, $params)) {
            // Return positional parameter value
            return $params[$index];
        }

        // Return default value when parameter is missing
        return $default;
    }
}
