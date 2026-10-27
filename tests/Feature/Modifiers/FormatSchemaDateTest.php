<?php

declare(strict_types=1);

use App\Modifiers\FormatSchemaDate;
use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Covers `FormatSchemaDate` / `schema_date` (App\Modifiers\FormatSchemaDate) for
 * Schema.org-style partial dates: year / year-month / full-date precision from
 * partial-storage tokens, Value unwrap, a full date for an augmented `date`
 * field's date object (UAMS-Web/uams-statamic-archive#3040), null for empty / whitespace / non-string /
 * invalid-year input, and the `schema_date` Antlers alias.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/FormatSchemaDateTest.php
 */
it('formats a partial date string', function (string $input, ?string $expected) {
    expect((new FormatSchemaDate)->index($input, [], []))->toBe($expected);
})->with([
    'full_date' => ['1986-05-14', '1986-05-14'],
    'full_date_padded' => [" 1986-05-14\t", '1986-05-14'],
    'day_full_with_hyphen_suffix' => ['1986-05-14-', '1986-05'],
    'day_has_x' => ['1986-05-1X', '1986-05'],
    'day_has_hyphen_suffix' => ['1986-05-1-', '1986-05'],
    'day_xx' => ['1986-05-XX', '1986-05'],
    'day_triple_hyphen' => ['1986-05---', '1986-05'],
    'day_x_hyphen' => ['1986-05-X-', '1986-05'],
    'day_hyphen_x' => ['1986-05--X', '1986-05'],
    'day_empty_after_month_hyphen' => ['1986-05-', '1986-05'],
    'year_month_only' => ['1986-05', '1986-05'],
    'month_has_x' => ['1986-0X-14', '1986'],
    'month_double_hyphen' => ['1986-0--14', '1986'],
    'month_xx' => ['1986-XX-14', '1986'],
    'month_four_hyphens' => ['1986----14', '1986'],
    'month_empty_with_day' => ['1986--5-14', '1986'],
    'month_x5' => ['1986-X5-14', '1986'],
    'year_trailing_hyphen' => ['1986-', '1986'],
    'year_only' => ['1986', '1986'],
    'year_all_x' => ['XXXX-05-14', null],
    'year_leading_hyphens' => ['-----05-14', null],
    'year_198x' => ['198X-05-14', null],
    'year_198_double_hyphen' => ['198--05-14', null],
    'year_all_x_month' => ['XXXX-05', null],
    'year_leading_hyphens_month' => ['-----05', null],
    'year_198x_month' => ['198X-05', null],
    'year_198_double_month' => ['198--05', null],
    'year_all_x_only' => ['XXXX', null],
    'year_four_hyphens_only' => ['----', null],
    'year_198x_only' => ['198X', null],
    'year_198_hyphen_only' => ['198-', null],
]);

it('unwraps a Value wrapper before formatting', function () {
    expect((new FormatSchemaDate)->index(new Value('1986-05-14'), [], []))->toBe('1986-05-14');
});

it('formats a date object as a full date', function (DateTimeInterface $input) {
    expect((new FormatSchemaDate)->index($input, [], []))->toBe('2034-12-31');
})->with([
    'carbon' => [Carbon::parse('2034-12-31')],
    'carbon_with_time' => [Carbon::parse('2034-12-31 23:59:59')],
    'immutable' => [new DateTimeImmutable('2034-12-31 00:00:00')],
    'other_zone' => [new DateTimeImmutable('2034-12-31 22:00:00', new DateTimeZone('Asia/Tokyo'))],
]);

it('unwraps a Value wrapping a date object', function () {
    expect((new FormatSchemaDate)->index(new Value(Carbon::parse('2025-01-01')), [], []))->toBe('2025-01-01');
});

it('returns null for empty and whitespace-only strings', function (string $input) {
    expect((new FormatSchemaDate)->index($input, [], []))->toBeNull();
})->with([
    'empty string' => [''],
    'whitespace only' => ["  \t\n"],
]);

it('returns null for non-string values', function (mixed $value) {
    expect((new FormatSchemaDate)->index($value, [], []))->toBeNull();
})->with([
    'null' => [null],
    'false' => [false],
    'integer' => [19860514],
]);

it('matches the schema_date alias in Antlers', function () {
    $payload = ['d' => '1986-05'];

    $primary = trim((string) Antlers::parse('{{ d | format_schema_date }}', $payload, true));
    $alias = trim((string) Antlers::parse('{{ d | schema_date }}', $payload, true));

    expect($primary)->toBe('1986-05');
    expect($alias)->toBe($primary);
});

it('renders an empty string in Antlers when the modifier returns null', function () {
    $out = trim((string) Antlers::parse('{{ d | format_schema_date }}', ['d' => 'XXXX-05-14'], true));

    expect($out)->toBe('');
});
