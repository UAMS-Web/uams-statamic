<?php

declare(strict_types=1);

namespace App\Modifiers;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;
use Statamic\Statamic;

/**
 * Antlers modifier for civil calendar dates (no time-of-day semantics).
 *
 * Statamic `format` runs values through `CoreModifiers::carbon()`, which when
 * `statamic.system.localize_dates_in_modifiers` is true shifts instants into
 * `display_timezone`. Raw `Y-m-d` strings parse as UTC midnight, so zones west
 * of UTC can show the previous calendar day. This modifier bypasses that path
 * so schema and copy match stored date-only fields.
 *
 * - Raw `Y-m-d` strings: parsed as start-of-day in `Statamic::displayTimezone()`
 *   (audience wall calendar).
 * - `CarbonInterface` values from Statamic date fields: formatted in
 *   `config('app.timezone')` so the wall date matches `Date` fieldtype
 *   `parseSaved()` / `augment()` behavior.
 *
 * Usage:
 *
 * - `{{ activity_date_issued | format_civil_date }}` → `Y-m-d` default
 * - `{{ activity_date_issued | civil_date }}` (alias)
 * - `{{ activity_date_issued | format_civil_date:F j, Y }}`
 *
 * @see https://statamic.dev/modifiers/format
 * @see https://statamic.dev/modifiers/timezone
 */
class FormatCivilDate extends Modifier
{
    /**
     * Register shorthand alias for templates.
     *
     * @return array<int, string>
     */
    #[\Override]
    public static function aliases(): array
    {
        return [
            'civil_date',
        ];
    }

    /**
     * Format a date-only value for output without UTC/localize day shifts.
     *
     * @param  mixed  $value  Raw `Y-m-d` string, `CarbonInterface`, or `Value`
     * @param  array<int|string, mixed>  $params  Optional PHP date format (default `Y-m-d`)
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return mixed Empty string when input is unusable; otherwise formatted string
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): mixed {
        // Unwrap Statamic Value wrappers when present
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Preserve explicit empty inputs for optional date fields
        if (in_array($value, [null, '', false], true)) {
            return $value;
        }

        // Resolve the PHP date format string from the first parameter
        $formatParam = $params[0] ?? 'Y-m-d';
        $format = \is_scalar($formatParam) ? (string) $formatParam : 'Y-m-d';
        unset($formatParam);

        // Format raw date-only strings using the Statamic display timezone
        if (\is_string($value)) {
            // Trim whitespace so YAML date strings match the civil-date pattern
            $trimmed = trim($value);

            // Check if the string is a bare calendar date (no time component)
            if (
                preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $trimmed
                ) === 1
            ) {
                // Parse as start-of-day in the configured display timezone
                $out = Carbon::parse(
                    $trimmed,
                    Statamic::displayTimezone()
                )
                    ->startOfDay()
                    ->format($format);

                unset(
                    $format,
                    $trimmed
                );

                return $out;
            }

            unset($trimmed);
        }

        // Format augmented Statamic date instances using the app timezone wall clock
        if ($value instanceof CarbonInterface) {
            // Resolve the application timezone used by Statamic date parsing
            $appTzConfig = config('app.timezone');
            $appTz = \is_string($appTzConfig) && $appTzConfig ? $appTzConfig : 'UTC';
            unset($appTzConfig);

            // Convert the instant to the app timezone before formatting the date
            $out = Carbon::instance($value)
                ->copy()
                ->timezone($appTz)
                ->format($format);

            unset(
                $appTz,
                $format
            );

            return $out;
        }

        unset($format);

        return '';
    }
}
