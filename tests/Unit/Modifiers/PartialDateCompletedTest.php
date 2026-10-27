<?php

declare(strict_types=1);

use App\Modifiers\PartialDateCompleted;
use Illuminate\Support\Carbon;
use Statamic\Fields\Value;

/**
 * Behavior of the `partial_date_completed` Antlers modifier
 * (app/Modifiers/PartialDateCompleted.php): decide whether a partial-date storage
 * token is complete as of today — present dates count, partial precision (year or
 * year-month, incl. the current year) always counts, and only a fully-specified
 * date strictly after today is excluded. The segment-validation rules live in
 * PartialDateStorageSlots (covered by its own test); this covers the modifier's
 * unwrap/trim/explode/precision decisions, with "now" pinned to 2026-06-05.
 *
 * Mutation: every killable mutant is dead (score ~88%). The residual survivors are
 * equivalents — the empty-token `=== ''` guard (an empty year segment already fails
 * isValidYearPart, so the result is false either way) and the `array_key_exists` index
 * mutants for the month/day slots (the year segment always exists, and a missing month
 * or day resolves to null with or without the off-by-one). Left clean rather than
 * annotated with `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/PartialDateCompletedTest.php
 */
beforeEach(fn () => Carbon::setTestNow(Carbon::create(2026, 6, 5)));
afterEach(fn () => Carbon::setTestNow());

it('reports whether a partial date is completed as of today', function (mixed $value, bool $expected) {
    expect((new PartialDateCompleted)->index($value, [], []))->toBe($expected);
})->with([
    // ── Original data-provider cases ──
    'empty string is in-progress' => ['', false],
    'non-string is in-progress' => [null, false],
    'invalid year is in-progress' => ['XXXX-05-14', false],
    'past full date is completed' => ['2015-05-14', true],
    'today full date is completed' => ['2026-06-05', true],
    'future full date is excluded' => ['2026-06-06', false],
    'far future full date is excluded' => ['2030-01-01', false],
    'past year-only is completed' => ['2015', true],
    'current year-only is completed' => ['2026', true],
    'future year-only is treated as completed' => ['2030', true],
    'past year-month is completed' => ['2015-05', true],
    'current-year future month is treated as completed' => ['2026-12', true],
    'partial day placeholder is completed' => ['2015-05-XX', true],

    // ── Added: Value unwrap + whitespace trim branches ──
    'a Value-wrapped past date is completed' => [new Value('2015-05-14'), true],
    'whitespace-padded year is completed' => ['  2015  ', true],
    'whitespace-only token is in-progress' => ["  \n  ", false],

    // ── Added: mutation-driven branch coverage ──
    'a non-string array is in-progress' => [['x'], false],
    'invalid year-only token is in-progress' => ['XXXX', false],
    'future date with placeholder day is completed (year-month precision)' => ['2030-01-01-XX', true],
    'future date with placeholder month is completed (year precision)' => ['2030-XX-05', true],
]);
