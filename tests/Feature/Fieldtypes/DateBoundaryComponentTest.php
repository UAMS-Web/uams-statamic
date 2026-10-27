<?php

declare(strict_types=1);

use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#193 — App\Fieldtypes\Date routes only fields that configure a Boundaries &
 * Limits constraint to the enhanced `custom_date` CP component, so the override's
 * blast radius stays off every unconstrained date field (entry publish dates,
 * plain form fields, and the fieldtype's own date-typed config sub-fields).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/DateBoundaryComponentTest.php
 */
uses(TestCase::class);

/**
 * @param  array<string, mixed>  $config
 */
function dateComponentFor(array $config): string
{
    /** @var Fieldtype $fieldtype */
    $fieldtype = (new Field('when', array_merge(['type' => 'date'], $config)))->fieldtype();

    return $fieldtype->component();
}

it('routes fields with a boundary constraint to the custom_date component', function (array $config): void {
    /** @var array<string, mixed> $config */
    expect(dateComponentFor($config))->toBe('custom_date');
})->with([
    'custom earliest' => [['earliest_date_type' => 'custom']],
    'today earliest' => [['earliest_date_type' => 'today']],
    'field earliest' => [['earliest_date_type' => 'field']],
    'custom latest' => [['latest_date_type' => 'custom']],
    'today latest' => [['latest_date_type' => 'today']],
    'restricted weekdays' => [['days_of_week' => ['1', '2', '3', '4', '5']]],
    'a blackout date' => [['date_exceptions' => [['type' => 'single_date', 'item' => '2026-01-01']]]],
]);

it('keeps the base date component for unconstrained fields', function (array $config): void {
    /** @var array<string, mixed> $config */
    expect(dateComponentFor($config))->toBe('date');
})->with([
    'no boundary config' => [[]],
    'boundaries explicitly none' => [['earliest_date_type' => 'none', 'latest_date_type' => 'none']],
    'all weekdays allowed' => [['days_of_week' => ['0', '1', '2', '3', '4', '5', '6']]],
    'empty weekdays' => [['days_of_week' => []]],
    'empty exceptions' => [['date_exceptions' => []]],
]);

it('keeps the base date component for time-enabled fields even with a boundary', function (array $config): void {
    // The custom picker is date-only (its value round-trips as `Y-m-d` through a
    // reka CalendarDate); time-bearing boundary fields stay on the base picker.
    /** @var array<string, mixed> $config */
    expect(dateComponentFor($config))->toBe('date');
})->with([
    'custom earliest + time' => [['earliest_date_type' => 'custom', 'time_enabled' => true]],
    'blackout + time' => [['date_exceptions' => [['type' => 'single_date', 'item' => '2026-01-01']], 'time_enabled' => true]],
]);
