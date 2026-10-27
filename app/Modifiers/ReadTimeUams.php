<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\Statamic\Modifiers\WhitespaceDelimitedWordMetrics;
use App\Support\Statamic\Modifiers\WordMetricsModifierInput;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: estimated read time using whitespace-delimited word counts.
 *
 * Same preprocessing and WPM parameter shape as Statamic `read_time`; differs
 * in whitespace tokenization and in how minutes/seconds are rounded (see
 * `WhitespaceDelimitedWordMetrics`).
 *
 * Antlers usage:
 *
 * In Antlers pipes, use colon segments for positional arguments only; forms like
 * `unit="seconds"` or `wpm="150"` in the chain are not passed as named keys
 * (see `FormatDuration` / `format_bytes` class docs for the same constraint). Use
 * a non-numeric first segment for unit with default WPM, or parenthetical
 * modifier syntax for multiple arguments.
 *
 * - `{{ $text | read_time_uams }}` - minutes, default 200 WPM
 * - `{{ $text | read_time_uams:150 }}` - minutes, 150 WPM (first positional,
 *   same as core `read_time`)
 * - `{{ $text | read_time_uams:seconds }}` - seconds, default 200 WPM
 * - `{{ $text | read_time_uams:150:seconds }}` - `ceil(words / wpm * 60)` seconds
 * - `{{ $text | read_time_uams:150:s }}` - same, using a unit alias
 * - `{{ $text | read_time_uams(150, "seconds") }}` - parenthetical args; see https://statamic.dev/frontend/antlers#modifiers
 *
 * Modifier arguments (`$params` in `index()`):
 *
 * Precedence: when `$params` contains string keys `wpm` or `unit`
 * (e.g., non-template PHP calls with an associative array), those values override
 * positional resolution for that field. Antlers colon pipes supply numeric
 * indices only, so use `read_time_uams:seconds` or `read_time_uams:150:seconds`
 * instead of `unit="..."` in the pipe.
 *
 * Words per minute: positive integer; default
 * `WhitespaceDelimitedWordMetrics::DEFAULT_WORDS_PER_MINUTE` (`200`). Sources,
 * in order: named `wpm`, else first positional `$params[0]` when `is_numeric`.
 * Matches core `read_time` when only a number is passed. If `wpm` is named,
 * the first positional is never interpreted as WPM (it may still supply
 * `unit` when non-numeric; see below).
 *
 * Return unit: canonical values are `minutes` (default) and `seconds`.
 * Seconds use `ceil(words / wpm × 60)`; minutes use `ceil(seconds / 60)` for the
 * same text and WPM. Sources, in order:
 * named `unit`, else second positional `$params[1]`, else first positional
 * when it is non-numeric (e.g. `read_time_uams:seconds` uses default WPM).
 * Labels are case-insensitive; hyphens become underscores before matching.
 * Accepted aliases: minutes - `m`, `min`, `minute`, `minutes`; seconds -
 * `s`, `sec`, `second`, `seconds`. Any other `unit` string falls back to
 * `minutes`.
 */
class ReadTimeUams extends Modifier
{
    /**
     * Estimate whole minutes or whole seconds to read the normalized text.
     *
     * @param  mixed  $value  String, `Statamic\Fields\Value`, or Bard text-node array of
     *                        `Statamic\Fields\Values` (see class docblock)
     * @param  array<int|string, mixed>  $params  `wpm` / `0`, optional `unit` / `1` (see class docblock)
     * @param  array<int|string, mixed>  $context  Antlers context (unused)
     * @return int Whole minutes or seconds per `unit`
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): int {
        // Strip tags and flatten Bard arrays like core `read_time`
        $text = WordMetricsModifierInput::normalizeForReadTime($value);

        // Resolve WPM and return unit from named and positional parameters
        $resolved = $this->resolveWordsPerMinuteAndReturnUnit($params);

        // Words per minute
        $wordsPerMinute = $resolved['wpm'];

        // Return unit
        $returnUnit = $resolved['unit'];

        // Compute duration in the requested unit
        $out = $returnUnit === 'seconds'
            ? WhitespaceDelimitedWordMetrics::readingTimeSecondsFromText(
                $text,
                $wordsPerMinute
            )
            : WhitespaceDelimitedWordMetrics::readingTimeMinutesFromText(
                $text,
                $wordsPerMinute
            );

        // Unset local variables to free memory
        unset(
            $resolved,
            $returnUnit,
            $text,
            $wordsPerMinute
        );

        // Return the output
        return $out;
    }

    /**
     * Parse words-per-minute and return unit from modifier parameters.
     *
     * @param  array<int|string, mixed>  $params  Raw modifier arguments
     * @return array{wpm: int, unit: string} `unit` is `minutes` or `seconds`
     */
    private function resolveWordsPerMinuteAndReturnUnit(
        array $params
    ): array {
        // Words per minute
        $wordsPerMinute = WhitespaceDelimitedWordMetrics::DEFAULT_WORDS_PER_MINUTE;

        // Return unit
        $returnUnit = 'minutes';

        // First positional argument
        $pos0 = $params[0] ?? null;

        // Second positional argument
        $pos1 = $params[1] ?? null;

        // Apply named `wpm` when present
        if (\array_key_exists('wpm', $params)) {
            // Named `wpm` value
            $namedWpm = $params['wpm'];

            $wordsPerMinute = is_scalar($namedWpm) ? (int) $namedWpm : 0;
        } elseif (is_numeric($pos0)) {
            // First positional numeric argument is WPM (core `read_time` behavior)
            $wordsPerMinute = (int) $pos0;
        }

        // ---
        // Apply named `unit` when present
        // ---

        // Check if the named `unit` parameter is present
        if (\array_key_exists('unit', $params)) {
            // Named `unit` value
            $namedUnit = $params['unit'];

            // Normalize the return unit
            $returnUnit = $this->normalizeReturnUnit(
                is_scalar($namedUnit) ? (string) $namedUnit : ''
            );
        } elseif ($pos1 !== null) {
            // Normalize the return unit
            $returnUnit = $this->normalizeReturnUnit(
                is_scalar($pos1) ? (string) $pos1 : ''
            );
        } elseif ($pos0 !== null && ! is_numeric($pos0)) {
            // Single non-numeric positional (e.g. `read_time_uams:seconds`)
            $returnUnit = $this->normalizeReturnUnit(
                is_scalar($pos0) ? (string) $pos0 : ''
            );
        }

        // Unset local variables to free memory
        unset(
            $pos0,
            $pos1
        );

        // Return the resolved words per minute and return unit
        return [
            'unit' => $returnUnit,
            'wpm' => $wordsPerMinute,
        ];
    }

    /**
     * Normalize a return-unit string to `minutes` or `seconds`.
     *
     * Accepts case-insensitive aliases: `m`, `min`, `minute`, `minutes`, `s`,
     * `sec`, `second`, `seconds`. Hyphens are converted to underscores before
     * matching. Any other label is treated as minutes.
     *
     * @param  string  $raw  User-supplied unit label
     * @return string `minutes` or `seconds`
     */
    private function normalizeReturnUnit(
        string $raw
    ): string {
        // Normalize the return unit
        $normalized = strtolower(
            str_replace(
                '-',
                '_',
                trim($raw)
            )
        );

        // Unset local variable to free memory
        unset(
            $raw
        );

        // Return the normalized return unit
        return match ($normalized) {
            'm',
            'min',
            'minute',
            'minutes' => 'minutes',
            's',
            'sec',
            'second',
            'seconds' => 'seconds',
            default => 'minutes',
        };
    }
}
