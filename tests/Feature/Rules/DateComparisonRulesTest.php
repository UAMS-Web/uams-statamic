<?php

declare(strict_types=1);

use App\Rules\After;
use App\Rules\AfterOrEqual;
use App\Rules\BaseRules\BaseDateComparisonRule;
use App\Rules\Before;
use App\Rules\BeforeOrEqual;
use App\Rules\DateEquals;
use Illuminate\Translation\Translator;
use Tests\TestCase;

uses(TestCase::class);

covers(After::class, Before::class, BaseDateComparisonRule::class);

/**
 * Behavior of the date/time comparison rules (`After`, `Before`) and their shared
 * base `BaseDateComparisonRule`: the empty and non-string guards, unparseable
 * input on either side, strict ordering for the `date`, `time` and `datetime`
 * types, the strict `datetime` format gate, the UTC re-read of a `datetime`
 * value, and how `message()` picks a type-specific or generic language line.
 *
 * @command  php artisan test --compact tests/Feature/Rules/DateComparisonRulesTest.php
 */
it('fails when the value or the comparison date is empty', function (BaseDateComparisonRule $rule, mixed $value): void {
    expect($rule->passes('when', $value))->toBeFalse();
})->with([
    'After, empty string value' => [new After('2024-01-01'), ''],
    'After, null value' => [new After('2024-01-01'), null],
    'After, string zero value' => [new After('2024-01-01'), '0'],
    'Before, empty string value' => [new Before('2030-01-01'), ''],
    'Before, null value' => [new Before('2030-01-01'), null],
    'After, empty comparison date' => [new After(''), '2024-06-01'],
    'After, string zero comparison date' => [new After('0'), '2024-06-01'],
    'Before, empty comparison date' => [new Before(''), '2024-06-01'],
    'Before, string zero comparison date' => [new Before('0'), '2024-06-01'],
]);

it('fails a non-empty value that is not a string', function (BaseDateComparisonRule $rule, mixed $value): void {
    expect($rule->passes('when', $value))->toBeFalse();
})->with([
    'After, integer timestamp' => [new After('2000-01-01'), 1_900_000_000],
    'After, array' => [new After('2000-01-01'), ['2024-06-01']],
    'Before, integer timestamp' => [new Before('2030-01-01'), 1_000_000_000],
    'Before, boolean true' => [new Before('2030-01-01'), true],
]);

it('fails when either side cannot be parsed as a date', function (BaseDateComparisonRule $rule, string $value): void {
    expect($rule->passes('when', $value))->toBeFalse();
})->with([
    'After, unparseable value' => [new After('2000-01-01'), 'not a date'],
    'After, unparseable comparison date' => [new After('not a date'), '2024-06-01'],
    'Before, unparseable value' => [new Before('2030-01-01'), 'not a date'],
    'Before, unparseable comparison date' => [new Before('not a date'), '2024-06-01'],
    'After, invalid month' => [new After('2000-01-01'), '2024-13-01'],
]);

it('compares dates strictly for the default date type', function (string $value, bool $after, bool $before): void {
    expect((new After('2024-06-15'))->passes('when', $value))->toBe($after)
        ->and((new Before('2024-06-15'))->passes('when', $value))->toBe($before)
        ->and((new After('2024-06-15', 'date'))->passes('when', $value))->toBe($after)
        ->and((new Before('2024-06-15', 'date'))->passes('when', $value))->toBe($before);
})->with([
    'one day later' => ['2024-06-16', true, false],
    'one day earlier' => ['2024-06-14', false, true],
    'the same day' => ['2024-06-15', false, false],
    'one second later' => ['2024-06-15 00:00:01', true, false],
    'one second earlier' => ['2024-06-14 23:59:59', false, true],
]);

it('compares only the time of day for the time type', function (string $value, bool $after, bool $before): void {
    expect((new After('2030-01-01 09:00:00', 'time'))->passes('when', $value))->toBe($after)
        ->and((new Before('2030-01-01 09:00:00', 'time'))->passes('when', $value))->toBe($before);
})->with([
    'later time on an earlier date' => ['2000-01-01 09:00:01', true, false],
    'earlier time on a later date' => ['2099-12-31 08:59:59', false, true],
    'the same time on another date' => ['2000-01-01 09:00:00', false, false],
    'bare later time' => ['17:30', true, false],
    'bare earlier time' => ['06:15', false, true],
]);

it('ignores the date part for the time type where the date type would not', function (): void {
    // The value is years earlier, so a date comparison fails After, while the
    // time-of-day comparison sees 10:00 after 09:00 and passes.
    expect((new After('2030-01-01 09:00:00', 'date'))->passes('when', '2000-01-01 10:00:00'))->toBeFalse()
        ->and((new After('2030-01-01 09:00:00', 'time'))->passes('when', '2000-01-01 10:00:00'))->toBeTrue()
        ->and((new Before('2000-01-01 10:00:00', 'date'))->passes('when', '2030-01-01 09:00:00'))->toBeFalse()
        ->and((new Before('2000-01-01 10:00:00', 'time'))->passes('when', '2030-01-01 09:00:00'))->toBeTrue();
});

it('compares well-formed datetime values strictly', function (string $value, bool $after, bool $before): void {
    expect((new After('2024-06-15 12:00:00', 'datetime'))->passes('when', $value))->toBe($after)
        ->and((new Before('2024-06-15 12:00:00', 'datetime'))->passes('when', $value))->toBe($before);
})->with([
    'one second later' => ['2024-06-15 12:00:01', true, false],
    'one second earlier' => ['2024-06-15 11:59:59', false, true],
    'the same instant' => ['2024-06-15 12:00:00', false, false],
    'a year later' => ['2025-06-15 12:00:00', true, false],
]);

it('rejects a datetime value or comparison date outside the strict format', function (BaseDateComparisonRule $rule, string $value): void {
    expect($rule->passes('when', $value))->toBeFalse();
})->with([
    'After, ISO T separator' => [new After('2000-01-01 00:00:00', 'datetime'), '2024-06-15T12:00:00'],
    'After, missing seconds' => [new After('2000-01-01 00:00:00', 'datetime'), '2024-06-15 12:00'],
    'After, date only' => [new After('2000-01-01 00:00:00', 'datetime'), '2024-06-15'],
    'After, leading space' => [new After('2000-01-01 00:00:00', 'datetime'), ' 2024-06-15 12:00:00'],
    'After, date-only comparison date' => [new After('2000-01-01', 'datetime'), '2024-06-15 12:00:00'],
    'Before, ISO T separator' => [new Before('2030-01-01 00:00:00', 'datetime'), '2024-06-15T12:00:00'],
    'Before, date-only comparison date' => [new Before('2030-01-01', 'datetime'), '2024-06-15 12:00:00'],
    'Before, trailing newline' => [new Before('2030-01-01 00:00:00', 'datetime'), "2024-06-15 12:00:00\n"],
]);

it('accepts the same off-format values under the date type that datetime rejects', function (): void {
    // Control for the format gate: these parse fine and are later, so only the
    // datetime type's strict format check can be what rejects them above.
    expect((new After('2000-01-01 00:00:00'))->passes('when', '2024-06-15T12:00:00'))->toBeTrue()
        ->and((new After('2000-01-01'))->passes('when', '2024-06-15 12:00'))->toBeTrue()
        ->and((new Before('2030-01-01'))->passes('when', '2024-06-15 12:00:00'))->toBeTrue();
});

it('reads datetime values as UTC rather than in the default timezone', function (): void {
    $previous = date_default_timezone_get();
    date_default_timezone_set('America/Chicago');

    try {
        // 02:30 on 2024-03-10 does not exist in Chicago (the clocks jump from 02:00
        // to 03:00), so a local parse lands on 03:30 and sorts after 03:00. Read as
        // UTC the two values keep their written order: 02:30 is before 03:00.
        $value = '2024-03-10 02:30:00';
        $comparison = '2024-03-10 03:00:00';

        expect((new After($comparison, 'datetime'))->passes('when', $value))->toBeFalse()
            ->and((new Before($comparison, 'datetime'))->passes('when', $value))->toBeTrue()
            ->and((new After($comparison, 'date'))->passes('when', $value))->toBeTrue()
            ->and((new Before($comparison, 'date'))->passes('when', $value))->toBeFalse();
    } finally {
        date_default_timezone_set($previous);
    }
});

it('reads the datetime comparison date as UTC as well as the value', function (): void {
    $previous = date_default_timezone_get();
    date_default_timezone_set('America/Chicago');

    try {
        // Chicago is UTC-6 in January. Read as UTC, 10:00 is after 08:00. Were the
        // comparison date read in the default timezone instead, 08:00 would become
        // 14:00 UTC and the 10:00 UTC value would sort before it.
        $value = '2024-01-15 10:00:00';
        $comparison = '2024-01-15 08:00:00';

        expect((new After($comparison, 'datetime'))->passes('when', $value))->toBeTrue()
            ->and((new Before($comparison, 'datetime'))->passes('when', $value))->toBeFalse();
    } finally {
        date_default_timezone_set($previous);
    }
});

// UAMS-Web/uams-statamic-archive#2945: the type-specific lines are the app's own, under Statamic's namespace. This test used
// to register its own `validation.<prefix>.<type>` lines, the key the rule read and no
// language file holds, so it passed while every real message fell back to the generic date line.
it('uses the app\'s type-specific language line for each type', function (BaseDateComparisonRule $rule, string $expected): void {
    expect($rule->message())->toBe($expected);
})->with([
    'After, date' => [new After('2024-06-15'), 'Must be a date after 2024-06-15.'],
    'After, time' => [new After('09:00', 'time'), 'Must be a time after 09:00.'],
    'After, datetime' => [new After('2024-06-15 12:00:00', 'datetime'), 'Must be a date and time after 2024-06-15 12:00:00.'],
    'Before, date' => [new Before('2024-06-15'), 'Must be a date before 2024-06-15.'],
    'Before, time' => [new Before('17:30', 'time'), 'Must be a time before 17:30.'],
    'Before, datetime' => [new Before('2024-06-15 12:00:00', 'datetime'), 'Must be a date and time before 2024-06-15 12:00:00.'],
    'AfterOrEqual, time' => [new AfterOrEqual('09:00', 'time'), 'Must be a time after or equal to 09:00.'],
    'BeforeOrEqual, time' => [new BeforeOrEqual('17:30', 'time'), 'Must be a time before or equal to 17:30.'],
    'DateEquals, time' => [new DateEquals('12:00', 'time'), 'Must be a time equal to 12:00.'],
]);

it('falls back to the generic language line when no type-specific one exists', function (BaseDateComparisonRule $rule, string $genericKey, string $date): void {
    $message = $rule->message();

    expect($message)->toBe(mustBeString(trans($genericKey, ['date' => $date]), 'generic line'))
        ->and($message)->not->toBe($genericKey)
        ->and($message)->toContain($date);
})->with([
    'After, unknown type' => [new After('2024-06-15', 'fortnight'), 'validation.after', '2024-06-15'],
    'Before, unknown type' => [new Before('2031-02-03', 'fortnight'), 'validation.before', '2031-02-03'],
]);

it('chooses the generic line only for the rule whose prefix it belongs to', function (): void {
    // Register lines that differ per prefix, so a rule reporting the wrong prefix
    // cannot produce the expected text.
    $translator = mustBe(Translator::class, app('translator'), 'translator');
    $translator->addLines([
        'validation.after' => 'After :date.',
        'validation.before' => 'Before :date.',
    ], 'en');

    expect((new After('2024-06-15', 'fortnight'))->message())->toBe('After 2024-06-15.')
        ->and((new Before('2024-06-15', 'fortnight'))->message())->toBe('Before 2024-06-15.');
});
