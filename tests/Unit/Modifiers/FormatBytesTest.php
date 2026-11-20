<?php

declare(strict_types=1);

use App\Modifiers\FormatBytes;

/**
 * Behavior of the `format_bytes` Antlers modifier (app/Modifiers/FormatBytes.php).
 *
 * Exhaustive coverage: every unit tier (decimal + binary) via auto-selection and
 * full_unit singular/plural labels, every alias in `buildUnitAliases()` (with
 * adversarial values so the explicit unit forces a different tier than auto would —
 * otherwise deleting an alias is masked by auto-selection), every display-mode
 * synonym, and each control-flow branch (positional params, exact tier boundaries,
 * case-insensitive system/display, hyphen normalization, the unit/display/precision
 * fallbacks, negative magnitudes, and the `-0` guard).
 *
 * Mutation (UAMS-Web/uams-statamic#822): every mutant is killed or annotated. The
 * second test below kills the ones the first left alive: a byte count one below each
 * tier's divisor selects the tier under it, so a divisor moved by one changes the
 * unit; integer and string parameters reach the casts; and the remaining aliases,
 * the default precision and the `-2` clamp boundary are each pinned. What is left is
 * annotated in the modifier: display aliases that map to the default mode, defensive
 * non-scalar and empty-string guards, and loop or clamp bounds no input can tell apart.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/FormatBytesTest.php
 */
it('formats a byte count for the given configuration', function (mixed $value, array $params, int|float|string $expected) {
    expect((new FormatBytes)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Decimal tiers: auto-selection (abbr + divisor) and full_unit labels ──
    'decimal auto-selects B' => [2, ['precision' => 0], '2 B'],
    'decimal full_unit singular B' => [1, ['display' => 'full_unit', 'precision' => 0], '1 byte'],
    'decimal full_unit plural B' => [2, ['display' => 'full_unit', 'precision' => 0], '2 bytes'],
    'decimal auto-selects kB' => [2000, ['precision' => 0], '2 kB'],
    'decimal full_unit singular kB' => [1000, ['display' => 'full_unit', 'precision' => 0], '1 kilobyte'],
    'decimal full_unit plural kB' => [2000, ['display' => 'full_unit', 'precision' => 0], '2 kilobytes'],
    'decimal auto-selects MB' => [2000000, ['precision' => 0], '2 MB'],
    'decimal full_unit singular MB' => [1000000, ['display' => 'full_unit', 'precision' => 0], '1 megabyte'],
    'decimal full_unit plural MB' => [2000000, ['display' => 'full_unit', 'precision' => 0], '2 megabytes'],
    'decimal auto-selects GB' => [2000000000, ['precision' => 0], '2 GB'],
    'decimal full_unit singular GB' => [1000000000, ['display' => 'full_unit', 'precision' => 0], '1 gigabyte'],
    'decimal full_unit plural GB' => [2000000000, ['display' => 'full_unit', 'precision' => 0], '2 gigabytes'],
    'decimal auto-selects TB' => [2000000000000, ['precision' => 0], '2 TB'],
    'decimal full_unit singular TB' => [1000000000000, ['display' => 'full_unit', 'precision' => 0], '1 terabyte'],
    'decimal full_unit plural TB' => [2000000000000, ['display' => 'full_unit', 'precision' => 0], '2 terabytes'],
    'decimal auto-selects PB' => [2000000000000000, ['precision' => 0], '2 PB'],
    'decimal full_unit singular PB' => [1000000000000000, ['display' => 'full_unit', 'precision' => 0], '1 petabyte'],
    'decimal full_unit plural PB' => [2000000000000000, ['display' => 'full_unit', 'precision' => 0], '2 petabytes'],
    'decimal auto-selects EB' => [2000000000000000000, ['precision' => 0], '2 EB'],
    'decimal full_unit singular EB' => [1000000000000000000, ['display' => 'full_unit', 'precision' => 0], '1 exabyte'],
    'decimal full_unit plural EB' => [2000000000000000000, ['display' => 'full_unit', 'precision' => 0], '2 exabytes'],

    // ── Binary tiers ──
    'binary auto-selects B' => [2, ['system' => 'binary', 'precision' => 0], '2 B'],
    'binary full_unit singular B' => [1, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 byte'],
    'binary full_unit plural B' => [2, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 bytes'],
    'binary auto-selects KiB' => [2048, ['system' => 'binary', 'precision' => 0], '2 KiB'],
    'binary full_unit singular KiB' => [1024, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 kibibyte'],
    'binary full_unit plural KiB' => [2048, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 kibibytes'],
    'binary auto-selects MiB' => [2097152, ['system' => 'binary', 'precision' => 0], '2 MiB'],
    'binary full_unit singular MiB' => [1048576, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 mebibyte'],
    'binary full_unit plural MiB' => [2097152, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 mebibytes'],
    'binary auto-selects GiB' => [2147483648, ['system' => 'binary', 'precision' => 0], '2 GiB'],
    'binary full_unit singular GiB' => [1073741824, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 gibibyte'],
    'binary full_unit plural GiB' => [2147483648, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 gibibytes'],
    'binary auto-selects TiB' => [2199023255552, ['system' => 'binary', 'precision' => 0], '2 TiB'],
    'binary full_unit singular TiB' => [1099511627776, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 tebibyte'],
    'binary full_unit plural TiB' => [2199023255552, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 tebibytes'],
    'binary auto-selects PiB' => [2251799813685248, ['system' => 'binary', 'precision' => 0], '2 PiB'],
    'binary full_unit singular PiB' => [1125899906842624, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 pebibyte'],
    'binary full_unit plural PiB' => [2251799813685248, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 pebibytes'],
    'binary auto-selects EiB' => [2305843009213693952, ['system' => 'binary', 'precision' => 0], '2 EiB'],
    'binary full_unit singular EiB' => [1152921504606846976, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '1 exbibyte'],
    'binary full_unit plural EiB' => [2305843009213693952, ['system' => 'binary', 'display' => 'full_unit', 'precision' => 0], '2 exbibytes'],

    // ── Decimal unit aliases (buildUnitAliases): adversarial value forces the
    //    explicit unit to differ from auto-selection, so deleting the alias shows ──
    "unit alias 'b' forces B" => [1000, ['unit' => 'b', 'precision' => 0], '1000 B'],
    "unit alias 'byte' forces B" => [1000, ['unit' => 'byte', 'precision' => 0], '1000 B'],
    "unit alias 'bytes' forces B" => [1000, ['unit' => 'bytes', 'precision' => 0], '1000 B'],
    "unit alias 'kb' forces kB" => [1000000, ['unit' => 'kb', 'precision' => 0], '1000 kB'],
    "unit alias 'kbyte' forces kB" => [1000000, ['unit' => 'kbyte', 'precision' => 0], '1000 kB'],
    "unit alias 'kbytes' forces kB" => [1000000, ['unit' => 'kbytes', 'precision' => 0], '1000 kB'],
    "unit alias 'kilobyte' forces kB" => [1000000, ['unit' => 'kilobyte', 'precision' => 0], '1000 kB'],
    "unit alias 'kilobytes' forces kB" => [1000000, ['unit' => 'kilobytes', 'precision' => 0], '1000 kB'],
    "unit alias 'mb' forces MB" => [1000000000, ['unit' => 'mb', 'precision' => 0], '1000 MB'],
    "unit alias 'mbyte' forces MB" => [1000000000, ['unit' => 'mbyte', 'precision' => 0], '1000 MB'],
    "unit alias 'mbytes' forces MB" => [1000000000, ['unit' => 'mbytes', 'precision' => 0], '1000 MB'],
    "unit alias 'megabyte' forces MB" => [1000000000, ['unit' => 'megabyte', 'precision' => 0], '1000 MB'],
    "unit alias 'megabytes' forces MB" => [1000000000, ['unit' => 'megabytes', 'precision' => 0], '1000 MB'],
    "unit alias 'gb' forces GB" => [1000000000000, ['unit' => 'gb', 'precision' => 0], '1000 GB'],
    "unit alias 'gbyte' forces GB" => [1000000000000, ['unit' => 'gbyte', 'precision' => 0], '1000 GB'],
    "unit alias 'gbytes' forces GB" => [1000000000000, ['unit' => 'gbytes', 'precision' => 0], '1000 GB'],
    "unit alias 'gigabyte' forces GB" => [1000000000000, ['unit' => 'gigabyte', 'precision' => 0], '1000 GB'],
    "unit alias 'gigabytes' forces GB" => [1000000000000, ['unit' => 'gigabytes', 'precision' => 0], '1000 GB'],
    "unit alias 'tb' forces TB" => [1000000000000000, ['unit' => 'tb', 'precision' => 0], '1000 TB'],
    "unit alias 'terabyte' forces TB" => [1000000000000000, ['unit' => 'terabyte', 'precision' => 0], '1000 TB'],
    "unit alias 'terabytes' forces TB" => [1000000000000000, ['unit' => 'terabytes', 'precision' => 0], '1000 TB'],
    "unit alias 'pb' forces PB" => [1000000000000000000, ['unit' => 'pb', 'precision' => 0], '1000 PB'],
    "unit alias 'petabyte' forces PB" => [1000000000000000000, ['unit' => 'petabyte', 'precision' => 0], '1000 PB'],
    "unit alias 'petabytes' forces PB" => [1000000000000000000, ['unit' => 'petabytes', 'precision' => 0], '1000 PB'],
    "unit alias 'eb' forces EB" => [500000000000000000, ['unit' => 'eb', 'precision' => 0], '1 EB'],
    "unit alias 'exabyte' forces EB" => [500000000000000000, ['unit' => 'exabyte', 'precision' => 0], '1 EB'],
    "unit alias 'exabytes' forces EB" => [500000000000000000, ['unit' => 'exabytes', 'precision' => 0], '1 EB'],

    // ── Binary unit aliases (adversarial values, system = binary) ──
    "binary unit alias 'kib' forces KiB" => [1048576, ['system' => 'binary', 'unit' => 'kib', 'precision' => 0], '1024 KiB'],
    "binary unit alias 'kibibyte' forces KiB" => [1048576, ['system' => 'binary', 'unit' => 'kibibyte', 'precision' => 0], '1024 KiB'],
    "binary unit alias 'kibibytes' forces KiB" => [1048576, ['system' => 'binary', 'unit' => 'kibibytes', 'precision' => 0], '1024 KiB'],
    "binary unit alias 'mib' forces MiB" => [1073741824, ['system' => 'binary', 'unit' => 'mib', 'precision' => 0], '1024 MiB'],
    "binary unit alias 'mebibyte' forces MiB" => [1073741824, ['system' => 'binary', 'unit' => 'mebibyte', 'precision' => 0], '1024 MiB'],
    "binary unit alias 'mebibytes' forces MiB" => [1073741824, ['system' => 'binary', 'unit' => 'mebibytes', 'precision' => 0], '1024 MiB'],
    "binary unit alias 'gib' forces GiB" => [1099511627776, ['system' => 'binary', 'unit' => 'gib', 'precision' => 0], '1024 GiB'],
    "binary unit alias 'gibibyte' forces GiB" => [1099511627776, ['system' => 'binary', 'unit' => 'gibibyte', 'precision' => 0], '1024 GiB'],
    "binary unit alias 'gibibytes' forces GiB" => [1099511627776, ['system' => 'binary', 'unit' => 'gibibytes', 'precision' => 0], '1024 GiB'],
    "binary unit alias 'tib' forces TiB" => [1125899906842624, ['system' => 'binary', 'unit' => 'tib', 'precision' => 0], '1024 TiB'],
    "binary unit alias 'tebibyte' forces TiB" => [1125899906842624, ['system' => 'binary', 'unit' => 'tebibyte', 'precision' => 0], '1024 TiB'],
    "binary unit alias 'tebibytes' forces TiB" => [1125899906842624, ['system' => 'binary', 'unit' => 'tebibytes', 'precision' => 0], '1024 TiB'],
    "binary unit alias 'pib' forces PiB" => [1152921504606846976, ['system' => 'binary', 'unit' => 'pib', 'precision' => 0], '1024 PiB'],
    "binary unit alias 'pebibyte' forces PiB" => [1152921504606846976, ['system' => 'binary', 'unit' => 'pebibyte', 'precision' => 0], '1024 PiB'],
    "binary unit alias 'pebibytes' forces PiB" => [1152921504606846976, ['system' => 'binary', 'unit' => 'pebibytes', 'precision' => 0], '1024 PiB'],
    "binary unit alias 'eib' forces EiB" => [576460752303423488, ['system' => 'binary', 'unit' => 'eib', 'precision' => 0], '1 EiB'],
    "binary unit alias 'exbibyte' forces EiB" => [576460752303423488, ['system' => 'binary', 'unit' => 'exbibyte', 'precision' => 0], '1 EiB'],
    "binary unit alias 'exbibytes' forces EiB" => [576460752303423488, ['system' => 'binary', 'unit' => 'exbibytes', 'precision' => 0], '1 EiB'],

    // ── Display-mode synonyms (resolveDisplay alias map) ──
    'display synonym noint' => [1500, ['display' => 'noint'], 1.5],
    'display synonym integer' => [1500, ['display' => 'integer'], 1.5],
    'display synonym int' => [1500, ['display' => 'int'], 1.5],
    'display synonym nostring' => [1500, ['display' => 'nostring'], '1.5'],
    'display synonym string' => [1500, ['display' => 'string'], '1.5'],
    'display synonym str' => [1500, ['display' => 'str'], '1.5'],
    'display synonym full' => [1500, ['display' => 'full'], '1.5 kilobytes'],
    'display synonym long' => [1500, ['display' => 'long'], '1.5 kilobytes'],
    'display synonym abbr_with_space' => [1500, ['display' => 'abbr_with_space'], '1.5 kB'],
    'display synonym abbr_space' => [1500, ['display' => 'abbr_space'], '1.5 kB'],
    'display synonym abbr_without_space' => [1500, ['display' => 'abbr_without_space'], '1.5kB'],
    'display synonym abbr_nospace' => [1500, ['display' => 'abbr_nospace'], '1.5kB'],

    // ── Canonical abbreviated-without-space + control-flow & edge branches ──
    'canonical abbreviated_unit_without_space' => [42, ['display' => 'abbreviated_unit_without_space', 'unit' => 'B', 'precision' => 0], '42B'],
    'positional system param' => [1536, ['binary'], '1.5 KiB'],
    'positional display param' => [1500, ['decimal', 'full_unit'], '1.5 kilobytes'],
    'positional precision and unit params' => [1234, ['decimal', 'abbreviated_unit_with_space', -1, 'kB'], '1.234 kB'],
    'positional unit param alias' => [3000000, ['decimal', 'abbreviated_unit_with_space', 0, 'Megabytes'], '3 MB'],
    'auto exact kB boundary' => [1000, ['precision' => 0], '1 kB'],
    'auto exact MB boundary' => [1000000, ['precision' => 0], '1 MB'],
    'uppercase binary system' => [1536, ['system' => 'BINARY'], '1.5 KiB'],
    'uppercase display alias' => [1500, ['display' => 'FULL_UNIT'], '1.5 kilobytes'],
    'hyphenated display no-unit-string' => [1500, ['display' => 'no-unit-string'], '1.5'],
    'empty unit falls back to auto' => [1500, ['unit' => ''], '1.5 kB'],
    'whitespace-padded unit forces kB over auto MB' => [1000000, ['unit' => '  kb  '], '1000 kB'],
    'float value cast to int bytes' => [1999.9, ['unit' => 'B', 'precision' => 0], '1999 B'],
    'non-scalar value falls back to zero' => [[1, 2, 3], [], '0 B'],
    'no_unit_integer int branch for whole number' => [1000, ['display' => 'no_unit_integer', 'unit' => 'B'], 1000],
    'no_unit_integer float branch' => [1500, ['display' => 'no_unit_integer', 'precision' => 1], 1.5],
    'no_unit_string trims trailing zeros' => [1500, ['unit' => 'kB', 'precision' => 3, 'display' => 'no_unit_string'], '1.5'],
    'negative bytes auto-select via abs' => [-1500, [], '-1.5 kB'],
    'negative-zero normalizes to 0' => [-1, ['unit' => 'EB', 'precision' => -1, 'display' => 'no_unit_string'], '0'],
    'precision below -1 clamps to no rounding' => [1234, ['unit' => 'kB', 'precision' => -5], '1.234 kB'],
    'unknown display falls back to default' => [1500, ['display' => 'bogus'], '1.5 kB'],
    'unknown unit falls back to auto' => [1500, ['unit' => 'zzz'], '1.5 kB'],
    'known unit absent from system falls back to auto' => [1500, ['unit' => 'MiB'], '1.5 kB'],
]);

it('kills the mutants the configuration dataset leaves alive', function (mixed $value, array $params, int|float|string $expected) {
    expect((new FormatBytes)->index($value, $params, []))->toBe($expected);
})->with([
    // One byte below each tier's divisor stays in the tier below, so a divisor
    // one smaller would select the higher tier and print "1 <unit>" instead.
    'one byte below 1 MB stays in kB' => [999999, ['precision' => 0], '1000 kB'],
    'one byte below 1 GB stays in MB' => [999999999, ['precision' => 0], '1000 MB'],
    'one byte below 1 TB stays in GB' => [999999999999, ['precision' => 0], '1000 GB'],
    'one byte below 1 PB stays in TB' => [999999999999999, ['precision' => 0], '1000 TB'],
    'one byte below 1 EB stays in PB' => [999999999999999999, ['precision' => 0], '1000 PB'],
    'one byte below 1 MiB stays in KiB' => [1048575, ['system' => 'binary', 'precision' => 0], '1024 KiB'],
    'one byte below 1 GiB stays in MiB' => [1073741823, ['system' => 'binary', 'precision' => 0], '1024 MiB'],
    'one byte below 1 TiB stays in GiB' => [1099511627775, ['system' => 'binary', 'precision' => 0], '1024 GiB'],
    'one byte below 1 PiB stays in TiB' => [1125899906842623, ['system' => 'binary', 'precision' => 0], '1024 TiB'],
    'one byte below 1 EiB stays in PiB' => [1152921504606846975, ['system' => 'binary', 'precision' => 0], '1024 PiB'],
    // The remaining binary system aliases, and an integer system that reaches the cast
    'base2 system alias' => [1536, ['system' => 'base2'], '1.5 KiB'],
    'base-2 system alias' => [1536, ['system' => 'base-2'], '1.5 KiB'],
    '1024 system alias' => [1536, ['system' => '1024'], '1.5 KiB'],
    'integer system is cast to its string alias' => [1536, ['system' => 1024], '1.5 KiB'],
    // Integer display and unit parameters reach their string casts
    'integer display falls back to the default mode' => [1500, ['display' => 5], '1.5 kB'],
    'integer unit falls back to auto' => [1500, ['unit' => 1], '1.5 kB'],
    // Precision: the default, a numeric string, a non-scalar, and the clamp boundary
    'default precision is one decimal place' => [1234, [], '1.2 kB'],
    'numeric-string precision is cast to an integer' => [1234, ['precision' => '0'], '1 kB'],
    'non-scalar precision falls back to zero' => [1234, ['precision' => [2]], '1 kB'],
    'precision of -2 clamps to no rounding' => [1234, ['unit' => 'kB', 'precision' => -2], '1.234 kB'],
    // A positional unit is read from the fourth segment and overrides auto
    'positional unit overrides auto-selection' => [3000000, ['decimal', 'abbreviated_unit_with_space', 0, 'kB'], '3000 kB'],
]);
