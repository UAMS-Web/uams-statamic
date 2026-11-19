<?php

declare(strict_types=1);

use App\Support\PartialDateStorageSlots;

/**
 * @covers \App\Support\PartialDateStorageSlots
 *
 * The `$` anchor in each pattern also matches before one trailing newline, so
 * a digit run followed by `\n` passes. The `trailing_newline` rows pin that
 * behavior as it stands; they do not endorse it.
 *
 * @command php artisan test --compact tests/Unit/Support/PartialDateStorageSlotsTest.php
 */
covers(PartialDateStorageSlots::class);

it('validates the year slot per Schema.org partial-date rules', function (mixed $part, bool $expected) {
    expect(PartialDateStorageSlots::isValidYearPart($part))->toBe($expected);
})->with([
    'four_digits' => ['1986', true],
    'placeholder_x' => ['198X', false],
    'all_placeholders' => ['XXXX', false],
    'hyphen_inside' => ['19-86', false],
    'leading_hyphen' => ['-986', false],
    'too_short' => ['986', false],
    'too_long' => ['19860', false],
    'non_digit' => ['19a6', false],
    'trailing_newline' => ["1986\n", true],
    'empty' => ['', false],
    'null' => [null, false],
    'integer' => [1986, false],
]);

it('validates the month slot', function (mixed $part, bool $expected) {
    expect(PartialDateStorageSlots::isValidMonthPart($part))->toBe($expected);
})->with([
    'two_digits' => ['05', true],
    'placeholder' => ['0X', false],
    'all_placeholders' => ['XX', false],
    'hyphen_inside' => ['0-', false],
    'one_digit' => ['5', false],
    'three_digits' => ['005', false],
    'non_digit' => ['0a', false],
    'trailing_newline' => ["05\n", true],
    'empty' => ['', false],
    'null' => [null, false],
    'integer' => [5, false],
]);

it('validates the day slot', function (mixed $part, bool $expected) {
    expect(PartialDateStorageSlots::isValidDayPart($part))->toBe($expected);
})->with([
    'two_digits' => ['14', true],
    'placeholder_run' => ['1X', false],
    'all_placeholders' => ['XX', false],
    'hyphen_padding' => ['1-', false],
    'one_digit' => ['4', false],
    'three_digits' => ['014', false],
    'non_digit' => ['1a', false],
    'trailing_newline' => ["14\n", true],
    'empty' => ['', false],
    'null' => [null, false],
    'integer' => [14, false],
]);
