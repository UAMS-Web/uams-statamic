<?php

declare(strict_types=1);

use App\Modifiers\FormatDuration;

/**
 * Behavior of the `format_duration` Antlers modifier (app/Modifiers/FormatDuration.php):
 * a seconds value rendered as ISO 8601, hms/minutes:seconds clocks, or short/long
 * human text, across precision rules, sign handling, rounding overflow, component
 * omission, display aliases, and input guards.
 *
 * Mutation: every killable mutant is dead (score ~83%). The residual survivors are
 * equivalent mutants no input can distinguish — display aliases that map to the ISO
 * default, the precision-clamp no-op, the defensive casts the `int|string` component
 * shape requires, and the unreachable `-0` guard — so the modifier is left clean
 * rather than annotated with `@pest-mutate-ignore`.
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
