<?php

declare(strict_types=1);

use App\Modifiers\ApStyleDate;
use Carbon\Carbon;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Covers partial-date storage strings and AP calendar output for
 * App\Modifiers\ApStyleDate: partial-storage slot grammar, compact digit tokens,
 * Value unwrap, empty passthrough, the Carbon prose/ISO instant path, and
 * tabular month forms.
 *
 * @command php artisan test --compact tests/Feature/Modifiers/ApStyleDateTest.php
 */
it('formats a partial storage string', function (string|int $input, ?string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'full_date' => ['1986-05-14', 'May 14, 1986'],
    'day_has_x' => ['1986-05-1X', 'May 1986'],
    'day_has_hyphen_suffix' => ['1986-05-1-', 'May 1986'],
    'day_xx' => ['1986-05-XX', 'May 1986'],
    'day_triple_hyphen' => ['1986-05---', 'May 1986'],
    'day_x_hyphen' => ['1986-05-X-', 'May 1986'],
    'day_hyphen_x' => ['1986-05--X', 'May 1986'],
    'day_empty_after_month_hyphen' => ['1986-05-', 'May 1986'],
    'year_month_only' => ['1986-05', 'May 1986'],
    'year_month_january' => ['1986-01', 'January 1986'],
    'month_has_x' => ['1986-0X-14', '1986'],
    'month_double_hyphen' => ['1986-0--14', '1986'],
    'month_xx' => ['1986-XX-14', '1986'],
    'month_four_hyphens' => ['1986----14', '1986'],
    'month_empty_with_day' => ['1986--5-14', '1986'],
    'month_x5' => ['1986-X5-14', '1986'],
    'year_trailing_hyphen' => ['1986-', '1986'],
    'year_only' => ['1986', '1986'],
    'year_all_x_full' => ['XXXX-05-1X', 'May'],
    'year_all_x_hyphen_day' => ['XXXX-05-1-', 'May'],
    'year_all_x_xx' => ['XXXX-05-XX', 'May'],
    'year_all_x_triple' => ['XXXX-05---', 'May'],
    'year_all_x_x_hyphen' => ['XXXX-05-X-', 'May'],
    'year_all_x_hyphen_x' => ['XXXX-05--X', 'May'],
    'year_all_x_month_hyphen' => ['XXXX-05-', 'May'],
    'year_leading_full' => ['-----05-14', 'May 14'],
    'year_198x_full' => ['198X-05-14', 'May 14'],
    'year_198_double_month_day' => ['198--05-14', 'May 14'],
    'year_all_x_month_only' => ['XXXX-05', 'May'],
    'year_all_x_january_month_only' => ['XXXX-01', 'January'],
    'year_leading_month' => ['-----05', 'May'],
    'year_198x_month' => ['198X-05', 'May'],
    'year_198_double_month' => ['198--05', null],
    'year_all_x_only' => ['XXXX', null],
    'year_four_hyphens_only' => ['----', null],
    'year_198x_only' => ['198X', null],
    'year_198_hyphen_only' => ['198-', null],
    'invalid_double_hyphen_after_known_year' => ['1986--05', '1986'],
    'compact_ymd' => ['19860514', 'May 14, 1986'],
    'compact_ym' => ['198605', 'May 1986'],
    'compact_ym_january' => ['198601', 'January 1986'],
    'compact_ymd_as_int' => [19_860_514, 'May 14, 1986'],
]);

it('returns an empty string for an explicit empty string', function () {
    expect((new ApStyleDate)->index('', [], []))->toBe('');
});

it('unwraps a Value wrapper before formatting', function () {
    expect((new ApStyleDate)->index(new Value('1986-05-14'), [], []))->toBe('May 14, 1986');
});

it('formats a prose date string through the Carbon path', function () {
    expect((new ApStyleDate)->index('January 2, 2026', [], []))->toBe('Jan. 2, 2026');
});

it('formats the calendar slice of an ISO datetime string', function () {
    expect((new ApStyleDate)->index('1986-05-14T12:30:00Z', [], []))->toBe('May 14, 1986');
});

it('formats a space-separated datetime through the instant path', function () {
    expect((new ApStyleDate)->index('1986-05-14 12:30:00', [], []))->toBe('May 14, 1986');
});

it('appends the AP time when the first parameter enables it', function () {
    expect((new ApStyleDate)->index('1986-05-14T12:30:00Z', [true], []))
        ->toBe("May 14, 1986, at 12:30\u{00A0}p.m.");
});

it('uses three-letter months for tabular material', function () {
    expect((new ApStyleDate)->index('1986-03-14', [false, true], []))->toBe('Mar 14, 1986');
});

it('uses three-letter months for tabular month-year', function () {
    expect((new ApStyleDate)->index('1986-01', [false, true], []))->toBe('Jan 1986');
});

it('returns an empty string for null and false', function (mixed $input) {
    expect((new ApStyleDate)->index($input, [], []))->toBe('');
})->with([
    'null' => [null],
    'false' => [false],
    'Value wrapping an empty string' => [new Value('')],
]);

it('returns an empty string for a value Carbon cannot parse', function (mixed $input) {
    expect((new ApStyleDate)->index($input, [], []))->toBe('');
})->with([
    'prose that is not a date' => ['not a date'],
    'boolean true' => [true],
    'array' => [[1]],
]);

it('casts numeric scalars to strings before routing them', function (int|float $input, string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'integer year' => [1986, '1986'],
    'float year-month' => [198605.0, 'May 1986'],
]);

it('treats a padded ISO datetime as an instant', function () {
    expect((new ApStyleDate)->index(' 1986-05-14T12:30:00Z ', [], []))->toBe('May 14, 1986');
});

it('enables time only for the accepted truthy first parameters', function (mixed $flag, string $expected) {
    expect((new ApStyleDate)->index('1986-05-14T12:30:00Z', [$flag], []))->toBe($expected);
})->with([
    'true' => [true, "May 14, 1986, at 12:30\u{00A0}p.m."],
    'string true' => ['true', "May 14, 1986, at 12:30\u{00A0}p.m."],
    'integer one' => [1, "May 14, 1986, at 12:30\u{00A0}p.m."],
    'string one' => ['1', "May 14, 1986, at 12:30\u{00A0}p.m."],
    'false' => [false, 'May 14, 1986'],
    'string false' => ['false', 'May 14, 1986'],
    'integer zero' => [0, 'May 14, 1986'],
    'string yes' => ['yes', 'May 14, 1986'],
    'integer two' => [2, 'May 14, 1986'],
]);

it('enables tabular months only for the accepted truthy second parameters', function (mixed $flag, string $expected) {
    expect((new ApStyleDate)->index('1986-01', [false, $flag], []))->toBe($expected);
})->with([
    'true' => [true, 'Jan 1986'],
    'string true' => ['true', 'Jan 1986'],
    'integer one' => [1, 'Jan 1986'],
    'string one' => ['1', 'Jan 1986'],
    'false' => [false, 'January 1986'],
    'string false' => ['false', 'January 1986'],
    'integer zero' => [0, 'January 1986'],
    'integer two' => [2, 'January 1986'],
]);

it('uses three-letter months for tabular material on the Carbon path', function () {
    expect((new ApStyleDate)->index('1986-03-14T12:00:00Z', [false, true], []))->toBe('Mar 14, 1986');
});

it('drops or rejects impossible month and day slots', function (string $input, ?string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'leading-dash month 13' => ['-----13', null],
    'leading-dash month 13 with day' => ['-----13-01', null],
    'leading-dash impossible day' => ['-----02-30', null],
    'unknown year impossible month' => ['198X-13-01', null],
    'unknown year impossible day keeps the month' => ['198X-02-30', 'February'],
    'empty year double hyphen month' => ['--05', 'May'],
]);

it('formats a date object through the Carbon path', function (array $params, string $expected) {
    expect((new ApStyleDate)->index(Carbon::parse('1986-01-14T12:30:00Z'), $params, []))->toBe($expected);
})->with([
    'default' => [[], 'Jan. 14, 1986'],
    'with time' => [[true], "Jan. 14, 1986, at 12:30\u{00A0}p.m."],
    'tabular' => [[false, true], 'Jan 14, 1986'],
]);

it('resolves every month on the Carbon path', function (int $month, string $expected) {
    expect((new ApStyleDate)->index(sprintf('1986-%02d-09T00:00:00Z', $month), [], []))->toBe($expected);
})->with([
    'January' => [1, 'Jan. 9, 1986'],
    'February' => [2, 'Feb. 9, 1986'],
    'March' => [3, 'March 9, 1986'],
    'April' => [4, 'April 9, 1986'],
    'May' => [5, 'May 9, 1986'],
    'June' => [6, 'June 9, 1986'],
    'July' => [7, 'July 9, 1986'],
    'August' => [8, 'Aug. 9, 1986'],
    'September' => [9, 'Sept. 9, 1986'],
    'October' => [10, 'Oct. 9, 1986'],
    'November' => [11, 'Nov. 9, 1986'],
    'December' => [12, 'Dec. 9, 1986'],
]);

it('keeps the English month name under another locale', function () {
    $locale = Carbon::getLocale();
    Carbon::setLocale('es');

    try {
        expect((new ApStyleDate)->index('1986-01-14T12:00:00Z', [], []))->toBe('Jan. 14, 1986');
    } finally {
        Carbon::setLocale($locale);
    }
});

it('trims a padded string before reading it', function (string $input, string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'partial token' => [' 1986-05-14 ', 'May 14, 1986'],
    'space-separated datetime' => [' 1986-05-14 12:30:00 ', 'May 14, 1986'],
]);

it('reads compact digits as slots rather than as a Carbon date', function (string $input, ?string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'impossible month' => ['19861301', null],
    'impossible day keeps the month' => ['19860230', 'February 1986'],
    'last day of a month' => ['19860131', 'Jan. 31, 1986'],
]);

it('treats more than three hyphen slots as an unusable day', function () {
    expect((new ApStyleDate)->index('1986-05-14-99', [], []))->toBe('May 1986');
});

it('checks a day with no known year against a leap year', function (string $input, ?string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'unknown year' => ['198X-02-29', 'Feb. 29'],
    'leading dashes' => ['-----02-29', 'Feb. 29'],
    'known common year' => ['1986-02-29', 'February 1986'],
    'known leap year' => ['1988-02-29', 'Feb. 29, 1988'],
]);

it('abbreviates the month only beside a day, and drops the day zero', function (string $input, string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'known year' => ['1986-01-04', 'Jan. 4, 1986'],
    'unknown year' => ['XXXX-01-04', 'Jan. 4'],
    'leading dashes' => ['-----01-04', 'Jan. 4'],
    'leading dashes, month only' => ['-----01', 'January'],
]);

it('rejects two-digit slots that are not months', function (string $input, ?string $expected) {
    expect((new ApStyleDate)->index($input, [], []))->toBe($expected);
})->with([
    'month 00' => ['1986-00', null],
    'month 00 with day' => ['1986-00-14', null],
    'month 13' => ['1986-13', null],
    'month 12' => ['1986-12', 'December 1986'],
    'leading-dash month 00' => ['-----00', null],
]);

it('uses tabular months without a known year', function (string $input, string $expected) {
    expect((new ApStyleDate)->index($input, [false, true], []))->toBe($expected);
})->with([
    'unknown year with day' => ['XXXX-01-04', 'Jan 4'],
    'unknown year, month only' => ['XXXX-01', 'Jan'],
    'leading dashes with day' => ['-----01-04', 'Jan 4'],
    'leading dashes, month only' => ['-----01', 'Jan'],
]);
