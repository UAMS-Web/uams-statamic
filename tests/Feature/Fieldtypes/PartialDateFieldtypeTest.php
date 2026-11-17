<?php

declare(strict_types=1);

use App\Fieldtypes\PartialDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Assert;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @command  php artisan test --compact tests/Feature/Fieldtypes/PartialDateFieldtypeTest.php
 *
 * Brings App\Fieldtypes\PartialDate to full line coverage and mutation-clean, without
 * --covered-only (UAMS-Web/uams-statamic#312). PCOV gives the $categories, $keywords and
 * $selectableInForms defaults no coverage, so their mutants are applied by hand: the
 * fieldtype listing test kills each.
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

/**
 * Run the root payload rule from `rules()` and collect every failure message.
 *
 * @return list<string>
 */
function partialDateRootRuleFailures(PartialDate $ft, mixed $value): array
{
    $rule = $ft->rules()[1];

    Assert::assertInstanceOf(Closure::class, $rule);

    $messages = [];

    $rule('pd', $value, function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

/**
 * Call a protected PartialDate method by name.
 */
function partialDateCallProtected(PartialDate $ft, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($ft, $method))->invoke($ft, ...$arguments);
}

/**
 * The twelve English month names, keyed 1 to 12.
 *
 * @return array<int, string>
 */
function partialDateEnglishMonths(): array
{
    return [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
}

it('uses the date fieldtype icon', function () {
    expect(partialDateFieldtype()->icon())->toBe('fieldtype-date');
});

it('defines the blueprint config fields, their defaults and their visibility conditions', function () {
    $requireYearOnly = 'Year only';
    $hasYear = ['included_parts' => 'contains_any year_only,year_month,year_month_day'];
    $hasMonth = ['included_parts' => 'contains_any year_month,year_month_day,month_only,month_day'];
    $hasDay = ['included_parts' => 'contains_any year_month_day,month_day'];

    expect(partialDateCallProtected(partialDateFieldtype(), 'configFieldItems'))->toBe([
        [
            'display' => 'Date Parts',
            'fields' => [
                'included_parts' => [
                    'type' => 'select',
                    'display' => 'Included Parts',
                    'instructions' => 'Which parts editors see and which are persisted as one canonical partial-date string in YAML.',
                    'default' => 'year_month_day',
                    'options' => [
                        'year_only' => 'Year only',
                        'year_month' => 'Year and month',
                        'year_month_day' => 'Year, month, and day',
                        'month_only' => 'Month only',
                        'month_day' => 'Month and day',
                    ],
                ],
                'required_parts_year_month_day' => [
                    'type' => 'select',
                    'display' => 'Required Parts',
                    'instructions' => 'When this field is marked required in the blueprint, which saved parts must be filled. Only applies to modes that include year, month, and day.',
                    'default' => 'year_only',
                    'options' => [
                        'year_only' => $requireYearOnly,
                        'year_month' => 'Year and month',
                        'year_month_day' => 'Year, month, and day',
                    ],
                    'if' => ['included_parts' => 'equals year_month_day'],
                ],
                'required_parts_year_month' => [
                    'type' => 'select',
                    'display' => 'Required Parts',
                    'instructions' => 'When this field is marked required, which saved parts must be filled. Only applies when including year and month (no day).',
                    'default' => 'year_only',
                    'options' => [
                        'year_only' => $requireYearOnly,
                        'year_month' => 'Year and month',
                    ],
                    'if' => ['included_parts' => 'equals year_month'],
                ],
                'required_parts_month_day' => [
                    'type' => 'select',
                    'display' => 'Required Parts',
                    'instructions' => 'When this field is marked required, which saved parts must be filled. Only applies when including month and day (no year).',
                    'default' => 'month_only',
                    'options' => [
                        'month_only' => 'Month only',
                        'month_day' => 'Month and day',
                    ],
                    'if' => ['included_parts' => 'equals month_day'],
                ],
                'year_default' => [
                    'type' => 'integer',
                    'display' => 'Default Year',
                    'instructions' => 'Pre-filled year for new entries when the blueprint has no default for this field. Ignored when included parts do not include year.',
                    'if' => $hasYear,
                ],
                'year_min' => [
                    'type' => 'integer',
                    'display' => 'Minimum Year',
                    'instructions' => 'Optional minimum year when year is included.',
                    'if' => $hasYear,
                ],
                'year_max' => [
                    'type' => 'integer',
                    'display' => 'Maximum Year',
                    'instructions' => 'Optional maximum year when year is included.',
                    'if' => $hasYear,
                ],
                'month_default' => [
                    'type' => 'select',
                    'display' => 'Default Month',
                    'instructions' => 'Pre-filled month for new entries when the blueprint has no default for this field. Ignored when included parts do not include month.',
                    'clearable' => true,
                    'options' => partialDateEnglishMonths(),
                    'if' => $hasMonth,
                ],
                'day_default' => [
                    'type' => 'integer',
                    'display' => 'Default Day',
                    'instructions' => 'Pre-filled day of month for new entries when the blueprint has no default for this field. Ignored when included parts do not include day.',
                    'if' => $hasDay,
                ],
            ],
        ],
        [
            'display' => 'Display',
            'fields' => [
                'subfields_layout' => [
                    'type' => 'radio',
                    'display' => 'Layout',
                    'instructions' => 'Inline shows year, month, and day inputs in a row (wrapping when needed). Stack shows each input full width, one per line.',
                    'default' => 'inline',
                    'options' => [
                        'inline' => 'Inline',
                        'stack' => 'Stack',
                    ],
                ],
            ],
        ],
    ]);
});

it('gives the rendered form the month names in the current locale', function () {
    $ft = partialDateFieldtype();

    expect($ft->extraRenderableFieldData())->toBe(['month_options' => partialDateEnglishMonths()]);

    app()->setLocale('fr');

    try {
        /** @var array<int, string> $months */
        $months = $ft->extraRenderableFieldData()['month_options'];
        $french = static fn (int $month): string => (new Carbon(sprintf('2020-%02d-01', $month)))->settings(['locale' => 'fr'])->translatedFormat('F');

        expect($months[1])->toBe($french(1))->not->toBe('January')
            ->and($months[12])->toBe($french(12))->not->toBe('December');
    } finally {
        app()->setLocale('en');
    }
});

it('preloads the config, the month options and which subfields are required', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_MONTH,
    ]);

    $months = [];

    foreach (partialDateEnglishMonths() as $value => $label) {
        $months[] = ['value' => $value, 'label' => $label];
    }

    $preload = $ft->preload();

    // The blueprint config passes through, its defaults included
    expect($preload['subfields_layout'])->toBe('inline')
        ->and($preload['validate'])->toBe(['required'])
        ->and($preload)->toMatchArray([
            'month_options' => $months,
            'effective_granularity' => 'year_month',
            'included_parts' => 'year_month_day',
            'include_year' => true,
            'include_month' => true,
            'include_day' => true,
            'subfield_required' => ['year' => true, 'month' => true, 'day' => false],
        ]);
});

it('requires no subfield in preload when the field itself is optional', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_MONTH_DAY,
    ]);

    expect($ft->preload()['subfield_required'])->toBe(['year' => false, 'month' => false, 'day' => false]);
});

it('preloads month-and-day inclusion without a year, requiring the day when the granularity asks for it', function () {
    $ft = partialDateFieldtype([
        'validate' => 'required',
        'included_parts' => PartialDate::INCLUDED_MONTH_DAY,
        'required_parts_month_day' => PartialDate::GRANULARITY_MONTH_DAY,
    ]);

    expect($ft->preload())->toMatchArray([
        'effective_granularity' => 'month_day',
        'included_parts' => 'month_day',
        'include_year' => false,
        'include_month' => true,
        'include_day' => true,
        'subfield_required' => ['year' => false, 'month' => true, 'day' => true],
    ]);
});

it('requires only the year in preload for a required year-only field', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_YEAR_ONLY,
    ]);

    expect($ft->preload())->toMatchArray([
        'include_year' => true,
        'include_month' => false,
        'include_day' => false,
        'subfield_required' => ['year' => true, 'month' => false, 'day' => false],
    ]);
});

it('ignores part defaults for parts the inclusion mode does not store', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_ONLY,
        'year_default' => '1999',
        'month_default' => 5,
        'day_default' => 20,
    ]);

    expect($ft->defaultValue())->toBe('1999');
});

it('treats an empty part default as no default', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH,
        'year_default' => '',
        'month_default' => 5,
    ]);

    expect($ft->defaultValue())->toBe('XXXX-05');
});

it('has no default when no part default is configured', function () {
    expect(partialDateFieldtype()->defaultValue())->toBeNull();
});

it('falls back to the parent default when no field is bound', function () {
    $ft = new class extends PartialDate
    {
        /** @var string */
        protected $defaultValue = 'parent default';
    };

    expect($ft->defaultValue())->toBe('parent default');
});

it('stores a blueprint default through the same pipeline as an entry value', function (mixed $default, string $included, ?string $expected) {
    $ft = partialDateFieldtype([
        'included_parts' => $included,
        'default' => $default,
        'year_default' => 1980,
    ]);

    expect($ft->defaultValue())->toBe($expected);
})->with([
    'a canonical string' => ['2015-07', PartialDate::INCLUDED_YEAR_MONTH_DAY, '2015-07'],
    'a legacy keyed array' => [['year' => 2015, 'month' => 7, 'day' => 4], PartialDate::INCLUDED_YEAR_MONTH_DAY, '2015-07-04'],
    'parts the mode does not store, stripped' => ['2015-07-04', PartialDate::INCLUDED_YEAR_ONLY, '2015'],
    'a malformed string' => ['July 2015', PartialDate::INCLUDED_YEAR_MONTH_DAY, null],
    'an explicit null, not the part default' => [null, PartialDate::INCLUDED_YEAR_MONTH_DAY, null],
]);

it('lets an empty or array payload through the root rule without a failure', function () {
    $ft = partialDateFieldtype();

    expect(partialDateRootRuleFailures($ft, null))->toBe([])
        ->and(partialDateRootRuleFailures($ft, []))->toBe([])
        ->and(partialDateRootRuleFailures($ft, ['year' => 2020, 'month' => 2, 'day' => 29]))->toBe([]);
});

it('fails a payload that is not an array, and stops there', function () {
    expect(partialDateRootRuleFailures(partialDateFieldtype(), '2020-01-01'))
        ->toBe(['The partial date value must be structured data from the publisher.']);
});

it('fails a payload marked as unparseable storage, and only when the marker is true', function () {
    $ft = partialDateFieldtype();

    expect(partialDateRootRuleFailures($ft, ['year' => 2020, 'month' => 13, '_partial_date_invalid_storage' => true]))
        ->toBe(['The partial date string is not valid for this field.'])
        ->and(partialDateRootRuleFailures($ft, ['year' => 2020, 'month' => 2, '_partial_date_invalid_storage' => 1]))
        ->toBe([]);
});

it('fails a month outside 1 to 12 and skips the day check after it', function (int $month) {
    expect(partialDateRootRuleFailures(partialDateFieldtype(), ['year' => 2020, 'month' => $month, 'day' => 99]))
        ->toBe(['The month must be between 1 and 12.']);
})->with([0, 13]);

it('accepts the first and last months', function (int $month) {
    expect(partialDateRootRuleFailures(partialDateFieldtype(), ['year' => 2020, 'month' => $month, 'day' => 31]))
        ->toBe([]);
})->with([1, 12]);

it('checks the day against the length of the selected month', function () {
    $ft = partialDateFieldtype();

    expect(partialDateRootRuleFailures($ft, ['year' => 2021, 'month' => 4, 'day' => 31]))
        ->toBe(['The day must be between 1 and 30 for the selected month.'])
        ->and(partialDateRootRuleFailures($ft, ['year' => 2021, 'month' => 4, 'day' => 30]))->toBe([])
        ->and(partialDateRootRuleFailures($ft, ['year' => 2021, 'month' => 4, 'day' => 0]))
        ->toBe(['The day must be between 1 and 30 for the selected month.'])
        ->and(partialDateRootRuleFailures($ft, ['year' => 2021, 'month' => 4, 'day' => 1]))->toBe([]);
});

it('caps the day at 31 when the month is unknown, from the root rule', function () {
    $ft = partialDateFieldtype();

    expect(partialDateRootRuleFailures($ft, ['year' => 2021, 'month' => null, 'day' => 31]))->toBe([])
        ->and(partialDateRootRuleFailures($ft, ['year' => 2021, 'month' => null, 'day' => 32]))
        ->toBe(['The day must be between 1 and 31 for the selected month.']);
});

it('ignores the month and day the inclusion mode does not store', function () {
    expect(partialDateRootRuleFailures(partialDateFieldtype(['included_parts' => PartialDate::INCLUDED_YEAR_ONLY]), ['year' => 2020, 'month' => 13, 'day' => 99]))
        ->toBe([])
        ->and(partialDateRootRuleFailures(partialDateFieldtype(['included_parts' => PartialDate::INCLUDED_YEAR_MONTH]), ['year' => 2020, 'month' => 2, 'day' => 99]))
        ->toBe([]);
});

it('caps the day at 31 when the mode stores a day but no month', function () {
    // Month is not stored for this mode, so its value is ignored and the day is capped at 31
    $ft = partialDateFieldtype(['included_parts' => 'day_only_unsupported']);

    expect(partialDateRootRuleFailures($ft, ['month' => 2, 'day' => 31]))->toBe([]);
});

it('names the nested subfields for validation messages', function () {
    expect(partialDateFieldtype()->extraValidationAttributes())->toBe([
        'pd.year' => 'Year',
        'pd.month' => 'Month',
        'pd.day' => 'Day',
    ]);
});

it('returns no nested attributes or rules when no field is bound', function () {
    $ft = new PartialDate;

    expect($ft->extraValidationAttributes())->toBe([])
        ->and($ft->extraRules())->toBe([]);
});

it('builds nested rules with the year bounds when they are set', function () {
    $ft = partialDateFieldtype([
        'validate' => ['required'],
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'required_parts_year_month_day' => PartialDate::GRANULARITY_YEAR_MONTH_DAY,
        'year_min' => '1900',
        'year_max' => 2100,
    ]);

    expect($ft->extraRules())->toBe([
        'pd.year' => ['required', 'integer', 'min:1900', 'max:2100'],
        'pd.month' => ['required', 'integer', 'min:1', 'max:12'],
        'pd.day' => ['required', 'integer', 'min:1'],
    ]);
});

it('builds optional nested rules and leaves out empty year bounds', function () {
    $ft = partialDateFieldtype([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
        'year_min' => '',
        'year_max' => '',
    ]);

    expect($ft->extraRules())->toBe([
        'pd.year' => ['nullable', 'integer'],
        'pd.month' => ['nullable', 'integer', 'min:1', 'max:12'],
        'pd.day' => ['nullable', 'integer', 'min:1'],
    ]);
});

it('marks the parts a mode does not store as nullable only', function () {
    expect(partialDateFieldtype(['included_parts' => PartialDate::INCLUDED_YEAR_ONLY, 'validate' => ['required']])->extraRules())->toBe([
        'pd.year' => ['required', 'integer'],
        'pd.month' => ['nullable'],
        'pd.day' => ['nullable'],
    ])->and(partialDateFieldtype(['included_parts' => PartialDate::INCLUDED_MONTH_ONLY, 'validate' => ['required']])->extraRules())->toBe([
        'pd.year' => ['nullable'],
        'pd.month' => ['required', 'integer', 'min:1', 'max:12'],
        'pd.day' => ['nullable'],
    ]);
});

it('returns 31 days for a month number outside 1 to 12', function () {
    $ft = partialDateFieldtype();

    expect($ft->maxDayInMonth(2021, 0))->toBe(31)
        ->and($ft->maxDayInMonth(2021, 13))->toBe(31)
        ->and($ft->maxDayInMonth(2021, 11))->toBe(30)
        ->and($ft->maxDayInMonth(2021, 2))->toBe(28);
});

it('reads the inclusion mode and falls back to year, month and day', function () {
    $ft = partialDateFieldtype();

    expect($ft->includedPartsFromConfig([]))->toBe('year_month_day')
        ->and($ft->includedPartsFromConfig(['included_parts' => 'month_only']))->toBe('month_only');
});

it('defaults the required parts to the full inclusion when the select is absent', function (string $included, string $expected) {
    expect(partialDateFieldtype()->resolveEffectiveGranularity(['included_parts' => $included]))->toBe($expected);
})->with([
    'year and month' => [PartialDate::INCLUDED_YEAR_MONTH, PartialDate::GRANULARITY_YEAR_MONTH],
    'year, month and day' => [PartialDate::INCLUDED_YEAR_MONTH_DAY, PartialDate::GRANULARITY_YEAR_MONTH_DAY],
    'month and day' => [PartialDate::INCLUDED_MONTH_DAY, PartialDate::GRANULARITY_MONTH_DAY],
    'month only' => [PartialDate::INCLUDED_MONTH_ONLY, PartialDate::GRANULARITY_MONTH_ONLY],
    'an unknown mode' => ['week', PartialDate::GRANULARITY_YEAR_MONTH_DAY],
]);

it('clamps a required-parts value the inclusion mode cannot store to its coarsest allowed value', function () {
    $ft = partialDateFieldtype();

    expect($ft->resolveEffectiveGranularity([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH,
        'required_parts_year_month' => PartialDate::GRANULARITY_YEAR_MONTH_DAY,
    ]))->toBe(PartialDate::GRANULARITY_YEAR_MONTH)
        ->and($ft->resolveEffectiveGranularity([
            'included_parts' => PartialDate::INCLUDED_MONTH_DAY,
            'required_parts_month_day' => PartialDate::GRANULARITY_YEAR_ONLY,
        ]))->toBe(PartialDate::GRANULARITY_MONTH_DAY)
        ->and($ft->resolveEffectiveGranularity([
            'included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY,
            'required_parts_year_month_day' => PartialDate::GRANULARITY_MONTH_DAY,
        ]))->toBe(PartialDate::GRANULARITY_YEAR_MONTH_DAY);
});

it('stores nothing for blank, malformed or non-string scalar input', function (mixed $input) {
    expect(partialDateFieldtype()->process($input))->toBeNull();
})->with([
    'whitespace' => ['   '],
    'malformed' => ['2020/01/02'],
    'an integer' => [2020],
    'a boolean' => [true],
]);

it('trims a stored string before reading it', function () {
    expect(partialDateFieldtype()->preProcess(" 2020-05 \n"))->toBe(['year' => 2020, 'month' => 5, 'day' => null]);
});

it('serializes each partial combination of each inclusion mode', function (?int $year, ?int $month, ?int $day, string $included, ?string $expected) {
    expect(partialDateFieldtype()->serializePartialDate(['year' => $year, 'month' => $month, 'day' => $day], $included))->toBe($expected);
})->with([
    'year only, empty' => [null, 4, 5, PartialDate::INCLUDED_YEAR_ONLY, null],
    'year only, padded' => [5, null, null, PartialDate::INCLUDED_YEAR_ONLY, '0005'],
    'month only, empty' => [2020, null, 5, PartialDate::INCLUDED_MONTH_ONLY, null],
    'year and month, year alone' => [2020, null, null, PartialDate::INCLUDED_YEAR_MONTH, '2020'],
    'year and month, month alone' => [null, 3, null, PartialDate::INCLUDED_YEAR_MONTH, 'XXXX-03'],
    'year and month, empty' => [null, null, 9, PartialDate::INCLUDED_YEAR_MONTH, null],
    'month and day, both' => [2020, 4, 7, PartialDate::INCLUDED_MONTH_DAY, 'XXXX-04-07'],
    'month and day, month alone' => [null, 4, null, PartialDate::INCLUDED_MONTH_DAY, 'XXXX-04'],
    'month and day, day alone' => [null, null, 7, PartialDate::INCLUDED_MONTH_DAY, 'XXXX-XX-07'],
    'month and day, empty' => [2020, null, null, PartialDate::INCLUDED_MONTH_DAY, null],
    'full date, empty' => [null, null, null, PartialDate::INCLUDED_YEAR_MONTH_DAY, null],
    'an unknown mode' => [2020, 1, 1, 'week', null],
]);

it('fills empty validation slots for null, blank and unexpected input', function (mixed $input) {
    expect(partialDateFieldtype()->preProcessValidatable($input))->toBe(['year' => null, 'month' => null, 'day' => null]);
})->with([
    'null' => [null],
    'an empty string' => [''],
    'whitespace' => ["  \t"],
    'an integer' => [2020],
]);

it('reads a padded stored string into validation slots', function () {
    expect(partialDateFieldtype()->preProcessValidatable(' XXXX-03-04 '))->toBe(['year' => null, 'month' => 3, 'day' => 4]);
});

it('reads numbers and numeric strings as parts, and nothing else', function () {
    $ft = partialDateFieldtype(['included_parts' => PartialDate::INCLUDED_YEAR_MONTH_DAY]);

    expect($ft->preProcess(['year' => '2020', 'month' => 6.9, 'day' => '07']))->toBe(['year' => 2020, 'month' => 6, 'day' => 7])
        ->and($ft->preProcess(['year' => true, 'month' => false, 'day' => 'tenth']))->toBe(['year' => null, 'month' => null, 'day' => null])
        ->and($ft->preProcess(['year' => '', 'month' => null, 'day' => 0]))->toBe(['year' => null, 'month' => null, 'day' => 0]);
});

it('lists the fieldtype under the special category, with its search keywords, and allows it in forms', function () {
    $ft = partialDateFieldtype();

    expect($ft->categories())->toBe(['special'])
        ->and($ft->keywords())->toBe(['date', 'year', 'month', 'day', 'partial', 'calendar'])
        ->and($ft->selectableInForms())->toBeTrue();
});

it('reads a non-string inclusion mode as its string form', function () {
    expect(partialDateFieldtype()->includedPartsFromConfig(['included_parts' => 5]))->toBe('5');
});

it('preloads the default inclusion mode when the blueprint sets it to null', function () {
    expect(partialDateFieldtype(['included_parts' => null])->preload()['included_parts'])->toBe('year_month_day');
});

it('counts only the literal required rule as making the field required', function () {
    $ft = partialDateFieldtype(['validate' => [true]]);

    expect($ft->preload()['subfield_required'])->toBe(['year' => false, 'month' => false, 'day' => false])
        ->and($ft->extraRules()['pd.year'])->toBe(['nullable', 'integer']);
});

it('reads no part default for a part the mode does not store, even when one is configured', function () {
    $ft = partialDateFieldtype();

    expect(partialDateCallProtected($ft, 'optionalConfiguredPartInt', ['year_default' => 1999], false, 'year_default'))->toBeNull()
        ->and(partialDateCallProtected($ft, 'optionalConfiguredPartInt', ['year_default' => '1999'], true, 'year_default'))->toBe(1999);
});

it('prefixes nested attributes with nothing when the field has no handle', function () {
    $ft = new PartialDate;
    $ft->setField(new Field(null, ['type' => 'partial_date']));

    expect(array_keys($ft->extraValidationAttributes()))->toBe(['.year', '.month', '.day'])
        ->and(array_keys($ft->extraRules()))->toBe(['.year', '.month', '.day']);
});

it('returns 31 days for months that Carbon would otherwise roll into a shorter month', function () {
    $ft = partialDateFieldtype();

    // Month -1 would roll to November (30) and month 14 to February (28)
    expect($ft->maxDayInMonth(2021, -1))->toBe(31)
        ->and($ft->maxDayInMonth(2021, 14))->toBe(31);
});

it('keeps a coarser required-parts value the year-and-month mode allows', function () {
    expect(partialDateFieldtype()->resolveEffectiveGranularity([
        'included_parts' => PartialDate::INCLUDED_YEAR_MONTH,
        'required_parts_year_month' => PartialDate::GRANULARITY_YEAR_ONLY,
    ]))->toBe(PartialDate::GRANULARITY_YEAR_ONLY);
});

it('clamps a non-string required-parts value to the mode fallback', function (string $included, string $key, string $expected) {
    expect(partialDateFieldtype()->resolveEffectiveGranularity([
        'included_parts' => $included,
        $key => 5,
    ]))->toBe($expected);
})->with([
    'year and month' => [PartialDate::INCLUDED_YEAR_MONTH, 'required_parts_year_month', PartialDate::GRANULARITY_YEAR_MONTH],
    'year, month and day' => [PartialDate::INCLUDED_YEAR_MONTH_DAY, 'required_parts_year_month_day', PartialDate::GRANULARITY_YEAR_MONTH_DAY],
    'month and day' => [PartialDate::INCLUDED_MONTH_DAY, 'required_parts_month_day', PartialDate::GRANULARITY_MONTH_DAY],
]);

it('truncates fractional year bounds to whole years', function () {
    expect(partialDateFieldtype(['year_min' => '1900.7', 'year_max' => '2100.2'])->extraRules()['pd.year'])
        ->toBe(['nullable', 'integer', 'min:1900', 'max:2100']);
});

it('reads null, empty and whitespace input as empty slots', function (mixed $input) {
    expect(partialDateFieldtype()->preProcess($input))->toBe(['year' => null, 'month' => null, 'day' => null])
        ->and(partialDateFieldtype()->process($input))->toBeNull();
})->with([
    'null' => [null],
    'an empty string' => [''],
    'an empty array' => [[]],
]);
