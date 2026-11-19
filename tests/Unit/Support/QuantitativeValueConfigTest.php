<?php

declare(strict_types=1);

use App\Support\QuantitativeValueConfig;

/**
 * Unit tests for normalizing the QuantitativeValue fieldtype's blueprint config.
 *
 * @covers \App\Support\QuantitativeValueConfig
 *
 * Mutation: no survivors on App\Support\QuantitativeValueConfig, measured on macOS without
 * --covered-only, against this file, QuantitativeValueDurationTotalYearsTest,
 * QuantitativeValueFieldtypeTest, QuantitativeValueDurationComputedsRegistrarTest and
 * FormatDurationTest (#1085).
 *
 * @command  php artisan test --compact tests/Unit/Support/QuantitativeValueConfigTest.php
 */

/**
 * A config built through the constructor, for the queries that read its lists directly.
 *
 * @param  list<string>  $majors  Enabled major components
 * @param  list<string>  $units  Enabled duration units
 */
function quantitativeValueConfigWith(array $majors, array $units): QuantitativeValueConfig
{
    return new QuantitativeValueConfig(
        mode: QuantitativeValueConfig::MODE_NUMBER,
        numberMode: QuantitativeValueConfig::NUMBER_INTEGER,
        majors: $majors,
        units: $units,
        numberSingleStorage: QuantitativeValueConfig::STORAGE_NESTED,
        durationSingleUnitPresentation: QuantitativeValueConfig::STORAGE_NESTED,
        unitCodeEnabled: false,
        unitTextEnabled: false,
        unitCodeRequired: false,
        unitTextRequired: false,
        valueRequired: false,
        minRequired: false,
        maxRequired: false,
        unitRequired: [],
    );
}

it('falls back to the default for each unrecognized choice', function (): void {
    $config = QuantitativeValueConfig::from([
        'mode' => 'bogus',
        'number_mode' => 'bogus',
        'number_single_storage' => 'bogus',
        'duration_single_unit_presentation' => 'bogus',
    ]);

    expect($config->mode)->toBe(QuantitativeValueConfig::MODE_NUMBER)
        ->and($config->numberMode)->toBe(QuantitativeValueConfig::NUMBER_INTEGER)
        ->and($config->numberSingleStorage)->toBe(QuantitativeValueConfig::STORAGE_NESTED)
        ->and($config->durationSingleUnitPresentation)->toBe(QuantitativeValueConfig::STORAGE_NESTED);
});

it('keeps each recognized non-default choice', function (): void {
    $config = QuantitativeValueConfig::from([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'number_mode' => QuantitativeValueConfig::NUMBER_FLOAT,
        'number_single_storage' => QuantitativeValueConfig::STORAGE_FLAT,
        'duration_single_unit_presentation' => QuantitativeValueConfig::STORAGE_FLAT,
    ]);

    expect($config->mode)->toBe(QuantitativeValueConfig::MODE_DURATION)
        ->and($config->numberMode)->toBe(QuantitativeValueConfig::NUMBER_FLOAT)
        ->and($config->numberSingleStorage)->toBe(QuantitativeValueConfig::STORAGE_FLAT)
        ->and($config->durationSingleUnitPresentation)->toBe(QuantitativeValueConfig::STORAGE_FLAT);
});

it('leaves every flag off when the config sets none', function (): void {
    $config = QuantitativeValueConfig::from([]);

    expect([
        $config->unitCodeEnabled, $config->unitTextEnabled, $config->unitCodeRequired, $config->unitTextRequired,
        $config->valueRequired, $config->minRequired, $config->maxRequired,
    ])->toBe([false, false, false, false, false, false, false])
        ->and($config->unitRequired)->toBe(array_fill_keys(QuantitativeValueConfig::UNIT_KEYS, false));
});

it('reads every flag as a boolean, including a stored 1', function (): void {
    // Blueprint YAML can carry a toggle as 1, so each flag is cast rather than passed through
    $config = QuantitativeValueConfig::from([
        'unit_code_enabled' => 1,
        'unit_text_enabled' => 1,
        'unit_code_required' => 1,
        'unit_text_required' => 1,
        'value_required' => 1,
        'min_required' => 1,
        'max_required' => 1,
        'unit_years_required' => 1,
        'unit_months_required' => 1,
        'unit_weeks_required' => 1,
        'unit_days_required' => 1,
        'unit_hours_required' => 1,
        'unit_minutes_required' => 1,
        'unit_seconds_required' => 1,
    ]);

    expect([
        $config->unitCodeEnabled, $config->unitTextEnabled, $config->unitCodeRequired, $config->unitTextRequired,
        $config->valueRequired, $config->minRequired, $config->maxRequired,
    ])->toBe([true, true, true, true, true, true, true])
        ->and($config->unitRequired)->toBe(array_fill_keys(QuantitativeValueConfig::UNIT_KEYS, true));
});

it('normalizes majors into canonical order, falling back to value alone', function (): void {
    expect(QuantitativeValueConfig::normalizeMajors(null))->toBe(['value', 'min', 'max'])
        ->and(QuantitativeValueConfig::normalizeMajors([]))->toBe(['value', 'min', 'max'])
        ->and(QuantitativeValueConfig::normalizeMajors(['max', 'value']))->toBe(['value', 'max'])
        ->and(QuantitativeValueConfig::normalizeMajors(['bogus']))->toBe(['value']);
});

it('matches configured majors strictly, so a stray boolean enables nothing', function (): void {
    // Raw blueprint config is untyped, and `true` loosely equals every major key
    expect(QuantitativeValueConfig::from(['major_components' => [true]])->majors)->toBe(['value']);
});

it('normalizes units into canonical order, falling back to years, months and days', function (): void {
    expect(QuantitativeValueConfig::normalizeUnits(null))->toBe(['years', 'months', 'days'])
        ->and(QuantitativeValueConfig::normalizeUnits(['bogus']))->toBe(['years', 'months', 'days'])
        ->and(QuantitativeValueConfig::normalizeUnits(['seconds', 'hours', 'years', 'minutes', 'weeks', 'days', 'months']))
        ->toBe(['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds']);
});

it('matches configured units strictly, so a stray boolean enables nothing', function (): void {
    // Raw blueprint config is untyped, and `true` loosely equals every unit key
    expect(QuantitativeValueConfig::from(['duration_units' => [true]])->units)->toBe(['years', 'months', 'days']);
});

it('stores a number as a flat scalar only when every condition holds', function (array $config, bool $flat): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(QuantitativeValueConfig::from($typedConfig)->storesNumberAsFlatScalar())->toBe($flat);
})->with([
    'all hold' => [['major_components' => ['value'], 'number_single_storage' => 'flat'], true],
    'duration mode' => [['mode' => 'duration', 'major_components' => ['value'], 'number_single_storage' => 'flat'], false],
    'two majors' => [['major_components' => ['value', 'min'], 'number_single_storage' => 'flat'], false],
    'nested storage' => [['major_components' => ['value'], 'number_single_storage' => 'nested'], false],
    'unit code on' => [['major_components' => ['value'], 'number_single_storage' => 'flat', 'unit_code_enabled' => true], false],
    'unit text on' => [['major_components' => ['value'], 'number_single_storage' => 'flat', 'unit_text_enabled' => true], false],
]);

it('reports an enabled major or unit by exact key', function (): void {
    $config = quantitativeValueConfigWith(['value', '1'], ['days']);

    expect($config->majorEnabled('value'))->toBeTrue()
        ->and($config->majorEnabled('min'))->toBeFalse()
        // A numeric string would equal '1' under a loose comparison
        ->and($config->majorEnabled('01'))->toBeFalse()
        ->and($config->unitEnabled('days'))->toBeTrue()
        ->and($config->unitEnabled('years'))->toBeFalse();
});

it('matches enabled units by exact key', function (): void {
    expect(quantitativeValueConfigWith(['value'], ['1'])->unitEnabled('01'))->toBeFalse();
});

it('requires no validation for a unit or major it does not know', function (): void {
    $config = QuantitativeValueConfig::from([
        'value_required' => true,
        'min_required' => true,
        'max_required' => true,
        'unit_years_required' => true,
    ]);

    expect($config->unitRequiresValidation('years'))->toBeTrue()
        ->and($config->unitRequiresValidation('fortnights'))->toBeFalse()
        ->and($config->majorRequiresValidation('value'))->toBeTrue()
        ->and($config->majorRequiresValidation('bogus'))->toBeFalse();
});
