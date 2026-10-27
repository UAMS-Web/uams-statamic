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
 * Mutation: every killable mutant is dead (score ~68%); the modifier is mostly
 * is_scalar-guarded coercion over delegated metrics, so the residual survivors are
 * all equivalents — the `(int)`/`(string)` casts are redundant (the metrics wpm
 * param is `int`-typed and normalizeReturnUnit takes `string`, so PHP coerces
 * identically), the `: 0` / `: ''` fallbacks for non-scalar params resolve to the
 * same output (wpm < 1 is clamped to 1; a non-alias unit defaults to minutes), the
 * `&&`->`||` on the single-positional-unit branch only fires for numeric/null pos0
 * (which normalizes to the minutes default anyway), and the `str_replace('-','_')`
 * is inert (no unit alias contains a hyphen or underscore). Left clean rather than
 * annotated with `@pest-mutate-ignore`.
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
]);
