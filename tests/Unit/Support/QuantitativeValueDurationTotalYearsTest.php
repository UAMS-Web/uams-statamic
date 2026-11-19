<?php

declare(strict_types=1);

use App\Support\QuantitativeValueConfig;
use App\Support\QuantitativeValueDurationTotalYears;

/**
 * @covers \App\Support\QuantitativeValueDurationTotalYears
 *
 * Mutation: no survivors on App\Support\QuantitativeValueDurationTotalYears, measured on macOS
 * without --covered-only, against this file, QuantitativeValueConfigTest,
 * QuantitativeValueFieldtypeTest, QuantitativeValueDurationComputedsRegistrarTest and
 * FormatDurationTest (#1085).
 *
 * @command  php artisan test --compact tests/Unit/Support/QuantitativeValueDurationTotalYearsTest.php
 */
it('matches the documented total-years examples', function () {
    $value = [
        'years' => 1, 'months' => 2, 'weeks' => 3, 'days' => 4,
        'hours' => 5, 'minutes' => 6, 'seconds' => 7,
    ];

    $min = [
        'years' => 8, 'months' => 9, 'weeks' => 0, 'days' => 1,
        'hours' => 2, 'minutes' => 3, 'seconds' => 4,
    ];

    $max = [
        'years' => 5, 'months' => 6, 'weeks' => 7, 'days' => 8,
        'hours' => 9, 'minutes' => 0, 'seconds' => 1,
    ];

    expect(round(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice($value), 2))->toBe(1.24);
    expect(round(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice($min), 2))->toBe(8.75);
    expect(round(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice($max), 2))->toBe(5.66);
});

it('returns zero total years for an empty slice', function () {
    expect(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice([]))->toBe(0.0);
});

it('ignores a total_years key already present in the slice', function () {
    $slice = [
        'years' => 1,
        'total_years' => 999.0,
    ];

    expect(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice($slice))->toEqualWithDelta(1.0, 0.0001);
});

it('adds total_years per major component when merging into stored duration', function () {
    $c = QuantitativeValueConfig::from([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => ['value', 'min'],
        'duration_units' => ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'],
    ]);

    $stored = [
        'value' => [
            'years' => 1, 'months' => 2, 'weeks' => 3, 'days' => 4,
            'hours' => 5, 'minutes' => 6, 'seconds' => 7,
        ],
        'min' => [
            'years' => 8, 'months' => 9, 'weeks' => 0, 'days' => 1,
            'hours' => 2, 'minutes' => 3, 'seconds' => 4,
        ],
    ];

    $out = QuantitativeValueDurationTotalYears::mergeIntoDurationStored($stored, $c);

    expect(data_get($out, 'value.total_years'))->toEqualWithDelta(1.24, 0.001);
    expect(data_get($out, 'min.total_years'))->toEqualWithDelta(8.75, 0.001);
    expect($out)->not->toHaveKey('max');
});

it('gives an empty major component a zero total_years', function () {
    $c = QuantitativeValueConfig::from([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => ['value'],
        'duration_units' => ['years', 'months'],
    ]);

    $out = QuantitativeValueDurationTotalYears::mergeIntoDurationStored([], $c);

    expect($out['value'])->toBe(['total_years' => 0.0]);
});

it('converts each unit on its own at its exact rate', function (string $unit, int $amount, float $years): void {
    expect(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice([$unit => $amount]))->toEqualWithDelta($years, 1e-12);
})->with([
    'years' => ['years', 2, 2.0],
    'months as twelfths' => ['months', 6, 0.5],
    'weeks as seven days' => ['weeks', 1, 7 / 365.25],
    'days over a Julian year' => ['days', 1, 1 / 365.25],
    'hours over 24' => ['hours', 24, 1 / 365.25],
    'minutes over 1440' => ['minutes', 1440, 1 / 365.25],
    'seconds over 86400' => ['seconds', 86400, 1 / 365.25],
]);

it('skips a null, blank or non-scalar unit and keeps reading the units after it', function (mixed $raw): void {
    expect(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice(['years' => $raw, 'days' => 365.25]))->toEqualWithDelta(1.0, 1e-12);
})->with([
    'null' => [null],
    'blank' => [''],
    'an array' => [['x']],
]);

it('reads a numeric string and treats a non-numeric one as zero', function (): void {
    expect(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice(['years' => '2', 'months' => 'abc']))->toEqualWithDelta(2.0, 1e-12);
});

it('returns zero for a slice whose units are all zero or unusable', function (): void {
    expect(QuantitativeValueDurationTotalYears::totalYearsForMajorSlice(['years' => 0, 'months' => null, 'days' => '', 'hours' => ['x']]))->toBe(0.0);
});

it('replaces a stale total_years while keeping the keys after it', function (): void {
    $c = QuantitativeValueConfig::from([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => ['value'],
        'duration_units' => ['years', 'months'],
    ]);

    $out = QuantitativeValueDurationTotalYears::mergeIntoDurationStored(['value' => ['total_years' => 99.0, 'years' => 3, 'months' => 6]], $c);

    expect($out['value'])->toBe(['years' => 3, 'months' => 6, 'total_years' => 3.5]);
});
