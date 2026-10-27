<?php

declare(strict_types=1);

use App\Modifiers\FormatCivilDate;
use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Covers `FormatCivilDate` / `civil_date` (App\Modifiers\FormatCivilDate) for
 * date-only values: raw `Y-m-d` strings parse as start-of-day in the display
 * timezone (avoiding the localized core `format` day-shift), CarbonInterface
 * values format in the app timezone, the optional format parameter, Value unwrap,
 * empty passthrough, and the `civil_date` alias.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/FormatCivilDateTest.php
 */
beforeEach(function () {
    config()->set('statamic.system.display_timezone', 'America/Chicago');
    config()->set('statamic.system.localize_dates_in_modifiers', true);
    config()->set('app.timezone', 'UTC');
});

it('preserves the calendar day for a raw Y-m-d string under localize + Chicago display', function () {
    $out = trim((string) Antlers::parse('{{ activity_date_issued | format_civil_date }}', [
        'activity_date_issued' => '1994-07-15',
    ], true));

    expect($out)->toBe('1994-07-15');
});

it('matches the civil_date alias for raw strings', function () {
    $out = trim((string) Antlers::parse('{{ d | civil_date }}', ['d' => '2034-12-31'], true));

    expect($out)->toBe('2034-12-31');
});

it('accepts a custom PHP date format parameter', function () {
    $out = trim((string) Antlers::parse('{{ d | format_civil_date:m/d/Y }}', ['d' => '1994-07-15'], true));

    expect($out)->toBe('07/15/1994');
});

it('keeps the stored day for a UTC-midnight Carbon while core format would shift it', function () {
    $augmented = Carbon::parse('1994-07-15', 'UTC')->startOfDay()->utc();

    $wrongCore = trim((string) Antlers::parse("{{ d | format('Y-m-d') }}", ['d' => $augmented], true));
    $civil = trim((string) Antlers::parse('{{ d | format_civil_date }}', ['d' => $augmented], true));

    expect($wrongCore)->toBe('1994-07-14');
    expect($civil)->toBe('1994-07-15');
});

it('keeps the stored day for a Carbon parsed in the app Chicago timezone', function () {
    config()->set('app.timezone', 'America/Chicago');

    $augmented = Carbon::parse('1994-07-15', 'America/Chicago')->startOfDay()->utc();

    $out = trim((string) Antlers::parse('{{ d | format_civil_date }}', ['d' => $augmented], true));

    expect($out)->toBe('1994-07-15');
});

it('passes empty values through unchanged in Antlers', function () {
    $nullOut = trim((string) Antlers::parse('{{ d | format_civil_date }}', ['d' => null], true));
    $emptyOut = trim((string) Antlers::parse('{{ d | format_civil_date }}', ['d' => ''], true));

    expect($nullOut)->toBe('');
    expect($emptyOut)->toBe('');
});

// Rigor: direct-call coverage for paths the Antlers integration cases cannot reach.

it('returns each empty sentinel unchanged', function (mixed $value, mixed $expected) {
    expect((new FormatCivilDate)->index($value, [], []))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty string' => ['', ''],
    'false' => [false, false],
]);

it('unwraps a Value wrapper before formatting', function () {
    expect((new FormatCivilDate)->index(new Value('1994-07-15'), [], []))->toBe('1994-07-15');
});

it('returns an empty string for an unparseable non-date string', function () {
    expect((new FormatCivilDate)->index('not-a-date', [], []))->toBe('');
});

it('falls back to the default format for a non-scalar format parameter', function () {
    expect((new FormatCivilDate)->index('1994-07-15', [['array']], []))->toBe('1994-07-15');
});
