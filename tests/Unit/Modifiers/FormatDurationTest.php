<?php

declare(strict_types=1);

use App\Modifiers\FormatDuration;

/**
 * Behavior of the `format_duration` Antlers modifier (app/Modifiers/FormatDuration.php):
 * a seconds value rendered as ISO 8601, hms/minutes:seconds clocks, or short/long
 * human text, across precision rules, sign handling, rounding overflow, component
 * omission, display aliases, and input guards.
 *
 * Mutation: 100% on App\Modifiers\FormatDuration (240 mutants, 240 tested, without
 * --covered-only; macOS, UAMS-Web/uams-statamic#824). Under `strict_types` the input
 * and param coercions are load-bearing, and the narrowing rows pin them. Two mutants are
 * annotated in the class: the minute divisor's `IncrementInteger`, which the seconds carry
 * restores, and `number_format()`'s thousands separator, which a remainder under 60 never uses.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/FormatDurationTest.php
 */
it('formats a duration for the given configuration', function (mixed $value, array $params, string $expected) {
    expect((new FormatDuration)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Original cases (behavior-preserving port of the PHPUnit class) ──
    'default ISO 8601' => [3723.5, [], 'PT1H2M4S'],
    'ISO precision -1 keeps fraction' => [3723.5, ['precision' => -1], 'PT1H2M3.5S'],
    'hms clock' => [3723.5, ['display' => 'hms'], '01:02:04'],
    'minutes_seconds' => [3723.5, ['display' => 'minutes_seconds'], '62:04'],
    'human_short' => [3723.5, ['display' => 'human_short'], '1h 2m 4s'],
    'human_long' => [3723.5, ['display' => 'human_long'], '1 hour, 2 minutes, 4 seconds'],
    'non-ISO precision defaults to 0' => [9.94, ['display' => 'hms'], '00:00:10'],
    'precision -1 non-ISO keeps fraction' => [9.87654, ['display' => 'hms', 'precision' => -1], '00:00:09.87654'],
    'trailing zeros trimmed' => [10.0, ['display' => 'minutes_seconds', 'precision' => 3], '00:10'],
    'rounding overflow carries seconds into minutes' => [59.96, ['display' => 'hms', 'precision' => 1], '00:01:00'],
    'negative ISO keeps sign' => [-90.5, [], '-PT1M31S'],
    'negative human_short keeps sign' => [-90.5, ['display' => 'human_short'], '-1m 31s'],

    // ── ISO 8601 edges: per-component zero-omission + all-zero fallback ──
    'ISO zero is PT0S' => [0, [], 'PT0S'],
    'ISO hours only omits M and S' => [3600, [], 'PT1H'],
    'ISO minutes only' => [60, [], 'PT1M'],
    'ISO rounds fraction into seconds' => [3723.556, ['precision' => 1], 'PT1H2M3.6S'],

    // ── human_long singular vs plural + zero-omission + sign ──
    'human_long all singular' => [3661, ['display' => 'human_long'], '1 hour, 1 minute, 1 second'],
    'human_long one second' => [1, ['display' => 'human_long'], '1 second'],
    'human_long omits zero seconds' => [3600, ['display' => 'human_long'], '1 hour'],
    'human_long negative' => [-90.5, ['display' => 'human_long'], '-1 minute, 31 seconds'],

    // ── human_short edges ──
    'human_short omits zero seconds' => [3600, ['display' => 'human_short'], '1h'],
    'human_short all zero is 0s' => [0, ['display' => 'human_short'], '0s'],

    // ── hms / minutes_seconds edges ──
    'hms precision -1 fractional' => [3723.5, ['display' => 'hms', 'precision' => -1], '01:02:03.5'],
    'hms negative' => [-3723.5, ['display' => 'hms'], '-01:02:04'],
    'minutes_seconds negative' => [-90.5, ['display' => 'minutes_seconds'], '-01:31'],
    'minutes overflow carries into hour' => [3599.6, ['display' => 'hms', 'precision' => 0], '01:00:00'],
    'negative sub-second keeps sign' => [-0.5, ['display' => 'hms'], '-00:00:01'],
    'hour extraction floors the fractional hour' => [5400, ['display' => 'hms'], '01:30:00'],
    'higher-magnitude fractional hour floors' => [9000, ['display' => 'hms'], '02:30:00'],
    'exact one-minute boundary' => [60, ['display' => 'hms'], '00:01:00'],
    'seconds at 59 do not carry' => [59, ['display' => 'hms'], '00:00:59'],
    'minutes at 59 do not carry' => [3540, ['display' => 'hms'], '00:59:00'],

    // ── Display aliases that map to a non-default mode (deleting one falls to ISO) ──
    "alias 'clock' -> hms" => [3723.5, ['display' => 'clock'], '01:02:04'],
    "alias 'hh_mm_ss' -> hms" => [3723.5, ['display' => 'hh_mm_ss'], '01:02:04'],
    "alias 'mm_ss' -> minutes_seconds" => [3723.5, ['display' => 'mm_ss'], '62:04'],
    "alias 'short' -> human_short" => [3723.5, ['display' => 'short'], '1h 2m 4s'],
    "alias 'long' -> human_long" => [3723.5, ['display' => 'long'], '1 hour, 2 minutes, 4 seconds'],

    // ── Display normalization (strtolower / hyphen->underscore / trim) ──
    'uppercase display alias' => [3723.5, ['display' => 'HMS'], '01:02:04'],
    'hyphenated display alias' => [3723.5, ['display' => 'human-short'], '1h 2m 4s'],
    'whitespace-padded display alias' => [3723.5, ['display' => '  hms  '], '01:02:04'],

    // ── Fallbacks / input guards ──
    'unknown display falls back to ISO' => [3723.5, ['display' => 'bogus'], 'PT1H2M4S'],
    'non-numeric value is zero' => ['abc', [], 'PT0S'],
    'null value is zero' => [null, [], 'PT0S'],
    'precision below -1 clamps' => [9.87654, ['display' => 'hms', 'precision' => -5], '00:00:09.87654'],

    // ── Positional params (display, then precision) ──
    'positional display param' => [3723.5, ['hms'], '01:02:04'],
    'positional precision param' => [3723.5, ['iso_8601', -1], 'PT1H2M3.5S'],
]);

it('narrows params and reaches each boundary the same way (UAMS-Web/uams-statamic#824)', function (mixed $value, array $params, string $expected) {
    expect((new FormatDuration)->index($value, $params, []))->toBe($expected);
})->with([
    // strict_types makes each `(float)`, `(string)` and `(int)` coercion load-bearing
    'a numeric-string value' => ['90', ['display' => 'hms'], '00:01:30'],
    'a numeric-string value in human text' => ['3723', ['display' => 'human_short'], '1h 2m 3s'],
    'an integer display reads as the default' => [90, ['display' => 1], 'PT1M30S'],
    'a non-scalar display reads as the default' => [90, ['display' => ['x']], 'PT1M30S'],
    'a numeric-string precision' => [1.234, ['display' => 'hms', 'precision' => '2'], '00:00:01.23'],
    'a non-numeric precision rounds to whole seconds' => [90.4, ['display' => 'hms', 'precision' => 'abc'], '00:01:30'],
    // Every ISO alias is the default display
    'the iso alias' => [90, ['display' => 'iso'], 'PT1M30S'],
    'the iso8601 alias' => [90, ['display' => 'iso8601'], 'PT1M30S'],
    'the duration alias' => [90, ['display' => 'duration'], 'PT1M30S'],
    'the hyphenated, upper-case name' => [90, ['display' => 'ISO-8601'], 'PT1M30S'],
    // The clamp: -1 means no rounding, and anything below it means the same
    'precision -1 does not round' => [59.4, ['display' => 'hms', 'precision' => -1], '00:00:59.4'],
    'precision below -1 clamps to no rounding' => [59.4, ['display' => 'hms', 'precision' => -2], '00:00:59.4'],
    // The hour and minute boundaries
    'one second short of an hour' => [3599, ['display' => 'hms'], '00:59:59'],
    'two whole hours' => [7200, ['display' => 'hms'], '02:00:00'],
    'two whole minutes' => [120, ['display' => 'hms'], '00:02:00'],
    'thousands of hours, far past the overflow carry' => [26280000, ['display' => 'hms'], '7300:00:00'],
    // A rounded-up second that carries through the minutes into the hour
    'a carry into the hour, as a clock' => [3599.6, ['display' => 'hms'], '01:00:00'],
    'a carry into the hour, in short text' => [3599.6, ['display' => 'human_short'], '1h'],
    'a carry into the hour, in long text' => [3599.6, ['display' => 'human_long'], '1 hour'],
    'a carry into the hour, in ISO' => [3599.6, [], 'PT1H'],
    'a carry into the second hour, as minutes' => [7199.6, ['display' => 'minutes_seconds'], '120:00'],
    'whole units in long text' => [3723, ['display' => 'human_long'], '1 hour, 2 minutes, 3 seconds'],
    // Zero and near-zero values
    'zero as a clock' => [0, ['display' => 'hms'], '00:00:00'],
    'a value rounding to zero' => [0.0001, ['display' => 'hms', 'precision' => 2], '00:00:00'],
    'a tiny value unrounded' => [0.0001, ['display' => 'human_long', 'precision' => -1], '0.0001 seconds'],
    'a fractional second unrounded' => [7.25, ['display' => 'hms', 'precision' => -1], '00:00:07.25'],
]);
