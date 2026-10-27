<?php

declare(strict_types=1);

use App\Fieldtypes\PartialDate;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Assert;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @command  php artisan test --compact tests/Feature/Fieldtypes/PartialDateFieldtypeTest.php
 *
 * Mutation: 100% on App\Fieldtypes\PartialDate (523 mutants, --covered-only).
 */

/**
 * Build a configured PartialDate fieldtype bound to a `pd` field.
 *
 * @param  array<string, mixed>  $config  Blueprint config overrides.
 */
function partialDateFieldtype(array $config = []): PartialDate
{
    $defaults = [
        'type' => 'partial_date',
    ];

    $field = new Field('pd', \array_replace($defaults, $config));
    $ft = $field->fieldtype();

    Assert::assertInstanceOf(PartialDate::class, $ft);

    return $ft;
}

/**
 * Full Statamic/Laravel rule map for the field (mirrors `Field::rules()` merge).
 *
 * @return array<string, mixed>
 */
function partialDateValidationRules(PartialDate $ft): array
{
    $field = $ft->field();

    Assert::assertNotNull($field);

    /** @var array<string, mixed> $rules */
    $rules = $field->rules();

    return $rules;
}

it('serializes array payloads to strings on process', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    expect($ft->process([
        'year' => '2020',
        'month' => '6',
        'day' => '15',
    ]))->toBe('2020-06-15');

    $ftNoYear = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_MONTH_DAY,
        'required_parts_month_day' => PartialDate::GRANULARITY_MONTH_DAY,
    ]);

    expect($ftNoYear->process([
        'year' => 1999,
        'month' => '4',
        'day' => '2',
    ]))->toBe('XXXX-04-02');

    $ftNoDay = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH,
        'required_parts_year_month' => PartialDate::GRANULARITY_YEAR_MONTH,
    ]);

    expect($ftNoDay->process([
        'year' => 2001,
        'month' => 8,
        'day' => 31,
    ]))->toBe('2001-08');

    $ftYearOnly = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_ONLY,
    ]);

    expect($ftYearOnly->process([
        'year' => '1999',
        'month' => '6',
        'day' => '3',
    ]))->toBe('1999');
});

it('returns null when process receives empty input', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    expect($ft->process(null))->toBeNull();
    expect($ft->process([]))->toBeNull();
    expect($ft->process(['year' => null, 'month' => null, 'day' => null]))->toBeNull();
});

it('pre-processes legacy arrays and canonical strings', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    expect($ft->preProcess('2021-03-15'))->toBe([
        'year' => 2021,
        'month' => 3,
        'day' => 15,
    ]);

    expect($ft->preProcess('2021-XX-05'))->toBe([
        'year' => 2021,
        'month' => null,
        'day' => 5,
    ]);

    expect($ft->preProcess('XXXX-06-10'))->toBe([
        'year' => null,
        'month' => 6,
        'day' => 10,
    ]);

    expect($ft->preProcess([
        'year' => 2005,
        'month' => 8,
        'day' => null,
    ]))->toBe([
        'year' => 2005,
        'month' => 8,
        'day' => null,
    ]);
});

it('round-trips process and pre-process', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    $payload = [
        'year' => 2019,
        'month' => 11,
        'day' => null,
    ];

    $stored = $ft->process($payload);

    expect($stored)->toBe('2019-11');
    expect($ft->preProcess($stored))->toBe($payload);
});

it('parses and serializes canonical variants', function () {
    $cases = [
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => '2020-01-02',
            'parts' => ['year' => 2020, 'month' => 1, 'day' => 2],
        ],
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => '2020-01',
            'parts' => ['year' => 2020, 'month' => 1, 'day' => null],
        ],
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => '2020',
            'parts' => ['year' => 2020, 'month' => null, 'day' => null],
        ],
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => '2020-XX-08',
            'parts' => ['year' => 2020, 'month' => null, 'day' => 8],
        ],
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => 'XXXX-03-14',
            'parts' => ['year' => null, 'month' => 3, 'day' => 14],
        ],
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => 'XXXX-03',
            'parts' => ['year' => null, 'month' => 3, 'day' => null],
        ],
        [
            'included' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'string' => 'XXXX-XX-09',
            'parts' => ['year' => null, 'month' => null, 'day' => 9],
        ],
        [
            'included' => PartialDate::INCLUDED_MONTH_ONLY,
            'string' => 'XXXX-07',
            'parts' => ['year' => null, 'month' => 7, 'day' => null],
        ],
    ];

    foreach ($cases as $case) {
        $field = partialDateFieldtype(['included_parts' => $case['included']]);

        expect($field->parsePartialDateString($case['string']))
            ->toBe($case['parts'], 'parse '.$case['string']);

        expect($field->serializePartialDate($case['parts'], $case['included']))
            ->toBe($case['string'], 'serialize '.$case['string']);
    }
});

it('throws when parsing an invalid partial date string', function () {
    $ft = partialDateFieldtype();

    expect(fn () => $ft->parsePartialDateString('not-a-date'))
        ->toThrow(InvalidArgumentException::class);
});

it('resolves effective granularity from included parts and required selects', function () {
    $ft = partialDateFieldtype();

    expect($ft->resolveEffectiveGranularity([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH,
        'required_parts_year_month' => PartialDate::GRANULARITY_YEAR_MONTH,
    ]))->toBe(PartialDate::GRANULARITY_YEAR_MONTH);

    expect($ft->resolveEffectiveGranularity([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_ONLY,
    ]))->toBe(PartialDate::GRANULARITY_YEAR_ONLY);

    expect($ft->resolveEffectiveGranularity([
        'included_parts' => PartialDate::INCLUDED_MONTH_DAY,
        'required_parts_month_day' => PartialDate::GRANULARITY_MONTH_ONLY,
    ]))->toBe(PartialDate::GRANULARITY_MONTH_ONLY);

    expect($ft->resolveEffectiveGranularity([
        'included_parts' => PartialDate::INCLUDED_YEAR_ONLY,
    ]))->toBe(PartialDate::GRANULARITY_YEAR_ONLY);
});

it('computes max day in month for leap years and February without a year', function () {
    $ft = partialDateFieldtype();

    expect($ft->maxDayInMonth(2024, 2))->toBe(29);
    expect($ft->maxDayInMonth(2023, 2))->toBe(28);
    expect($ft->maxDayInMonth(null, 2))->toBe(29);
    expect($ft->maxDayInMonth(null, 4))->toBe(30);
});

it('rejects an invalid February day in validation', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_MONTH_DAY,
    ]);

    $v = Validator::make(
        ['pd' => ['year' => 2023, 'month' => 2, 'day' => 29]],
        partialDateValidationRules($ft)
    );

    expect($v->fails())->toBeTrue();
});

it('accepts a leap-year February day in validation', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_MONTH_DAY,
    ]);

    $v = Validator::make(
        ['pd' => ['year' => 2024, 'month' => 2, 'day' => 29]],
        partialDateValidationRules($ft)
    );

    expect($v->fails())->toBeFalse();
});

it('requires only the year for year-only granularity when the parent is required', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_ONLY,
    ]);

    $pass = Validator::make(
        ['pd' => ['year' => 2005, 'month' => null, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($pass->fails())->toBeFalse();

    $fail = Validator::make(
        ['pd' => ['year' => null, 'month' => null, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($fail->fails())->toBeTrue();
});

it('requires the month for month-only validation', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_MONTH_ONLY,
    ]);

    $fail = Validator::make(
        ['pd' => ['year' => null, 'month' => null, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($fail->fails())->toBeTrue();

    $pass = Validator::make(
        ['pd' => ['year' => null, 'month' => 7, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($pass->fails())->toBeFalse();
});

it('allows a day without a month when the year is set', function () {
    $ft = partialDateFieldtype([
        'validate' => [],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_MONTH,
    ]);

    $v = Validator::make(
        ['pd' => ['year' => 2020, 'month' => null, 'day' => 5]],
        partialDateValidationRules($ft)
    );

    expect($v->fails())->toBeFalse();
});

it('allows unknown year placeholders for year-month-day hierarchy', function () {
    $ft = partialDateFieldtype([
        'validate' => [],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    $monthNoYear = Validator::make(
        ['pd' => ['year' => null, 'month' => 6, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($monthNoYear->fails())->toBeFalse();

    $dayWithMonthNoYear = Validator::make(
        ['pd' => ['year' => null, 'month' => 3, 'day' => 15]],
        partialDateValidationRules($ft)
    );

    expect($dayWithMonthNoYear->fails())->toBeFalse();

    $empty = Validator::make(
        ['pd' => ['year' => null, 'month' => null, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($empty->fails())->toBeFalse();

    $full = Validator::make(
        ['pd' => ['year' => 2021, 'month' => 6, 'day' => 10]],
        partialDateValidationRules($ft)
    );

    expect($full->fails())->toBeFalse();
});

it('allows a month without a year for year-month hierarchy', function () {
    $ft = partialDateFieldtype([
        'validate' => [],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH,
        'required_parts_year_month' => PartialDate::GRANULARITY_YEAR_MONTH,
    ]);

    $monthOnlyNoYear = Validator::make(
        ['pd' => ['year' => null, 'month' => 8, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($monthOnlyNoYear->fails())->toBeFalse();

    $empty = Validator::make(
        ['pd' => ['year' => null, 'month' => null, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($empty->fails())->toBeFalse();

    $both = Validator::make(
        ['pd' => ['year' => 2005, 'month' => 8, 'day' => null]],
        partialDateValidationRules($ft)
    );

    expect($both->fails())->toBeFalse();
});

it('allows a day without a month for month-day hierarchy', function () {
    $ft = partialDateFieldtype([
        'validate' => [],
        'included_parts' => PartialDate::INCLUDED_MONTH_DAY,
        'required_parts_month_day' => PartialDate::GRANULARITY_MONTH_DAY,
    ]);

    $dayOnly = Validator::make(
        ['pd' => ['year' => null, 'month' => null, 'day' => 12]],
        partialDateValidationRules($ft)
    );

    expect($dayOnly->fails())->toBeFalse();

    $monthDay = Validator::make(
        ['pd' => ['year' => null, 'month' => 4, 'day' => 12]],
        partialDateValidationRules($ft)
    );

    expect($monthDay->fails())->toBeFalse();
});

it('caps the day at 31 when the month is unknown', function () {
    $ft = partialDateFieldtype([
        'validate' => [],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    $v = Validator::make(
        ['pd' => ['year' => 2025, 'month' => null, 'day' => 32]],
        partialDateValidationRules($ft)
    );

    expect($v->fails())->toBeTrue();
});

it('fills validatable keys and handles strings in pre-process', function () {
    $ft = partialDateFieldtype();

    expect($ft->preProcessValidatable([]))->toBe([
        'year' => null,
        'month' => null,
        'day' => null,
    ]);

    expect($ft->preProcessValidatable(['year' => 1, 'month' => 2, 'day' => 3, 'extra' => 'x']))->toBe([
        'year' => 1,
        'month' => 2,
        'day' => 3,
    ]);

    expect($ft->preProcessValidatable('2025'))->toBe([
        'year' => 2025,
        'month' => null,
        'day' => null,
    ]);

    expect($ft->preProcessValidatable('not-valid'))->toBe([
        'year' => null,
        'month' => null,
        'day' => null,
        '_partial_date_invalid_storage' => true,
    ]);
});

it('fails validation when pre-process detects a bad string', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
    ]);

    $v = Validator::make(
        ['pd' => ['year' => null, 'month' => null, 'day' => null, '_partial_date_invalid_storage' => true]],
        partialDateValidationRules($ft)
    );

    expect($v->fails())->toBeTrue();
});

it('builds the default value from fieldtype config', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'year_default' => 2010,
        'month_default' => 5,
        'day_default' => 20,
    ]);

    expect($ft->defaultValue())->toBe('2010-05-20');
});
