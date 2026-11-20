<?php

declare(strict_types=1);

use App\Modifiers\ReadTimeUams;

/**
 * Behavior of the `read_time_uams` Antlers modifier (app/Modifiers/ReadTimeUams.php):
 * estimate whole minutes (default) or whole seconds to read text, resolving WPM and
 * the return unit from named (`wpm`/`unit`) and positional params, with strip-tags
 * preprocessing and case-insensitive unit aliases. The word counting/timing lives in
 * WhitespaceDelimitedWordMetrics (covered by its own test); this covers the modifier's
 * param resolution + unit normalization. Default WPM is 200; "one two three four" is
 * four words -> ceil(4/200*60) = 2 true seconds -> ceil(2/60) = 1 minute.
 *
 * Mutation: clean on Linux without `--covered-only` (#828). The `(int)` and
 * `(string)` casts are not redundant: the modifier declares `strict_types`, so the
 * string params Antlers passes would raise a `TypeError` at the `int`-typed metrics
 * call and at `trim()` without them, which the string and integer cases pin. The one
 * annotation covers the non-scalar `wpm` fallback of `0`, which the metrics clamp to
 * one word per minute exactly as they clamp `-1` and `1`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/ReadTimeUamsTest.php
 */
it('estimates read time for the given configuration', function (mixed $value, array $params, int $expected) {
    expect((new ReadTimeUams)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Original cases ──
    'strips tags before counting words' => ['<p>one two three four</p>', [], 1],
    'custom WPM via first positional' => ['one two three four', [2], 2],
    'seconds via second positional returns true seconds' => ['one two three four', [2, 'seconds'], 120],
    'named unit seconds uses default WPM' => ['one two three four', ['unit' => 'seconds'], 2],
    'single non-numeric positional is the unit' => ['one two three four', ['seconds'], 2],

    // ── WPM/unit precedence ──
    'named wpm only stays in minutes' => ['one two three four', ['wpm' => 2], 2],
    'named wpm + named seconds' => ['one two three four', ['wpm' => 2, 'unit' => 'seconds'], 120],
    'named wpm + non-numeric positional unit' => ['one two three four', ['wpm' => 2, 'seconds'], 120],
    'unknown unit falls back to minutes' => ['one two three four', ['unit' => 'fortnight'], 1],
    'whitespace + case unit normalizes to seconds' => ['one two three four', ['unit' => '  Seconds  '], 2],

    // ── Non-scalar param fallbacks (defensive) ──
    'non-scalar wpm falls back to the clamped default rate' => ['one two three four', ['wpm' => ['x']], 4],
    'non-scalar unit falls back to minutes' => ['one two three four', ['unit' => ['x']], 1],

    // ── Seconds unit aliases (case-insensitive) -> 2 true seconds ──
    'alias s' => ['one two three four', ['unit' => 's'], 2],
    'alias S' => ['one two three four', ['unit' => 'S'], 2],
    'alias sec' => ['one two three four', ['unit' => 'sec'], 2],
    'alias SEC' => ['one two three four', ['unit' => 'SEC'], 2],
    'alias second' => ['one two three four', ['unit' => 'second'], 2],
    'alias Second' => ['one two three four', ['unit' => 'Second'], 2],
    'alias seconds' => ['one two three four', ['unit' => 'seconds'], 2],

    // ── Minutes unit aliases (case-insensitive) -> 1 minute ──
    'alias m' => ['one two three four', ['unit' => 'm'], 1],
    'alias M' => ['one two three four', ['unit' => 'M'], 1],
    'alias min' => ['one two three four', ['unit' => 'min'], 1],
    'alias MIN' => ['one two three four', ['unit' => 'MIN'], 1],
    'alias minute' => ['one two three four', ['unit' => 'minute'], 1],
    'alias Minute' => ['one two three four', ['unit' => 'Minute'], 1],
    'alias minutes' => ['one two three four', ['unit' => 'minutes'], 1],

    // ── Scalar params as Antlers passes them (strings), and other scalars ──
    'string named wpm' => ['one two three four', ['wpm' => '2'], 2],
    'string first positional wpm' => ['one two three four', ['2'], 2],
    'string first positional wpm + seconds' => ['one two three four', ['2', 'seconds'], 120],
    'integer named unit falls back to minutes' => ['one two three four', ['unit' => 1], 1],
    'integer second positional unit falls back to minutes' => ['one two three four', [2, 5], 2],
    'boolean single positional falls back to minutes' => ['one two three four', [true], 1],
    'non-scalar second positional falls back to minutes' => ['one two three four', [2, ['x']], 2],

    // ── Positional unit precedence ──
    'second positional unit wins over a non-numeric first' => ['one two three four', ['minutes', 'seconds'], 2],
    'second positional minutes wins over a first seconds' => ['one two three four', ['seconds', 'minutes'], 1],
    'null first positional with second positional seconds' => ['one two three four', [null, 'seconds'], 2],

    // ── Labels outside the alias list ──
    'hyphenated label falls back to minutes' => ['one two three four', ['unit' => 'per-second'], 1],
    'underscored label falls back to minutes' => ['one two three four', ['unit' => 'per_second'], 1],
]);
