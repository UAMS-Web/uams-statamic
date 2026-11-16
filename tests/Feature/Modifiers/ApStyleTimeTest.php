<?php

declare(strict_types=1);

use App\Modifiers\ApStyleTime;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Tests\TestCase;

/**
 * Pin AP time output, including special hours and non-breaking spacing.
 *
 * Linux / PHP 8.4.27 / PCOV: 23/23 statements covered; 39/39 covered mutants
 * killed without exclusions. Every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/ApStyleTimeTest.php
 */
uses(TestCase::class);

covers(ApStyleTime::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-02-03 14:45:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('formats times without confusing noon, midnight, or their neighboring minutes', function (
    mixed $value,
    string $expected
): void {
    expect((new ApStyleTime)->index($value, [], []))->toBe($expected);
})->with([
    'noon' => ['2026-02-03 12:00:00', 'noon'],
    'midnight' => ['2026-02-03 00:00:00', 'midnight'],
    'after noon' => ['2026-02-03 12:01:00', "12:01\u{00A0}p.m."],
    'after midnight' => ['2026-02-03 00:01:00', "12:01\u{00A0}a.m."],
    'whole morning hour' => ['2026-02-03 09:00:00', "9\u{00A0}a.m."],
    'whole afternoon hour' => ['2026-02-03 15:00:00', "3\u{00A0}p.m."],
    'padded minute' => ['2026-02-03 15:09:00', "3:09\u{00A0}p.m."],
    'last minute' => ['2026-02-03 23:59:00', "11:59\u{00A0}p.m."],
    'date object' => [new DateTimeImmutable('2026-02-03 10:27:00'), "10:27\u{00A0}a.m."],
    'integer timestamp' => [3600, "1\u{00A0}a.m."],
    'fractional timestamp' => [3600.5, "1\u{00A0}a.m."],
]);

it('keeps empty values empty rather than formatting the current time', function (mixed $value): void {
    expect((new ApStyleTime)->index($value, [], []))->toBe('');
})->with([
    'null' => [null],
    'empty string' => [''],
    'zero string' => ['0'],
    'zero' => [0],
    'false' => [false],
    'empty array' => [[]],
]);

it('currently uses the frozen current time for unsupported nonempty values', function (mixed $value): void {
    expect((new ApStyleTime)->index($value, [], []))->toBe("2:45\u{00A0}p.m.");
})->with([
    'true' => [true],
    'array' => [['15:00']],
    'object' => [new stdClass],
]);

it('propagates an invalid time instead of silently substituting the current time', function (): void {
    expect(fn (): string => (new ApStyleTime)->index('not-a-time-value', [], []))
        ->toThrow(InvalidFormatException::class);
});
