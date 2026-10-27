<?php

declare(strict_types=1);

use App\Fieldtypes\Date;
use App\Rules\DateBoundary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\MessageBag;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1394 — the custom Date fieldtype's Boundaries & Limits config is enforced
 * SERVER-SIDE (App\Rules\DateBoundary, wired via App\Fieldtypes\Date::rules()),
 * backstopping the client-side picker so a bypassing POST cannot persist an
 * out-of-range value.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/DateBoundaryValidationTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    // Freeze "today" at 2026-06-01 (a Monday) in UTC for deterministic `today`
    // and `exclude_before_today` boundaries.
    Config::set('statamic.system.display_timezone', 'UTC');
    Config::set('app.timezone', 'UTC');
    Carbon::setTestNow(Carbon::create(2026, 6, 1, 12, 0, 0, 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Run App\Rules\DateBoundary directly and return the failure message, or null when
 * the value passes.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $data
 */
function runDateBoundary(array $config, mixed $value, array $data = [], string $attribute = 'when'): ?string
{
    $message = null;

    (new DateBoundary($config))
        ->setData($data)
        ->validate($attribute, $value, function (string $msg) use (&$message): void {
            $message = $msg;
        });

    return $message;
}

/**
 * The Laravel validator errors for a submission against a one-off blueprint whose
 * `when` field carries the given date config (end-to-end through the Statamic
 * fields validator, exercising preprocessing + rule wiring).
 *
 * @param  array<string, mixed>  $whenConfig
 * @param  array<string, mixed>  $values
 * @param  array<int, array<string, mixed>>  $extraFields
 */
function boundaryBlueprintErrors(array $whenConfig, array $values, array $extraFields = []): MessageBag
{
    $fields = array_merge([
        ['handle' => 'when', 'field' => array_merge(['type' => 'date', 'mode' => 'single', 'time_enabled' => false, 'format' => 'Y-m-d'], $whenConfig)],
    ], $extraFields);

    $blueprint = Blueprint::make();
    $blueprint->setContents(['fields' => $fields]);

    /** @var Fields $withValues */
    $withValues = $blueprint->fields()->addValues($values);
    /** @var Validator $statamicValidator */
    $statamicValidator = $withValues->validator();
    /** @var Illuminate\Validation\Validator $laravelValidator */
    $laravelValidator = $statamicValidator->validator();

    return $laravelValidator->errors();
}

// ---------------------------------------------------------------------------
// Date::rules() wiring — the rule attaches only when a boundary is configured.
// ---------------------------------------------------------------------------

it('attaches the DateBoundary rule when a boundary is configured', function (array $config): void {
    /** @var Date $fieldtype */
    $fieldtype = (new Date)->setField(new Field('when', array_merge(['type' => 'date'], $config)));

    expect($fieldtype->rules())->toContainOnlyInstancesOf(DateBoundary::class);
    expect($fieldtype->rules())->not->toBeEmpty();
})->with([
    'custom earliest' => [['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-01-01']],
    'today latest' => [['latest_date_type' => 'today']],
    'restricted weekdays' => [['days_of_week' => ['1', '2', '3', '4', '5']]],
    'one weekday excluded' => [['days_of_week' => ['0', '1', '2', '3', '4', '5']]],
    'a blackout date' => [['date_exceptions' => [['type' => 'single_date', 'item' => '2026-01-01']]]],
    'time-enabled with a boundary' => [['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-01-01', 'time_enabled' => true]],
]);

it('attaches no boundary rule to an unconstrained date field', function (array $config): void {
    /** @var Date $fieldtype */
    $fieldtype = (new Date)->setField(new Field('when', array_merge(['type' => 'date'], $config)));

    $hasBoundaryRule = collect($fieldtype->rules())->contains(static fn ($rule): bool => $rule instanceof DateBoundary);

    expect($hasBoundaryRule)->toBeFalse();
})->with([
    'no boundary' => [[]],
    'boundaries explicitly none' => [['earliest_date_type' => 'none', 'latest_date_type' => 'none']],
    'all weekdays allowed' => [['days_of_week' => ['0', '1', '2', '3', '4', '5', '6']]],
    'empty exceptions' => [['date_exceptions' => []]],
]);

// ---------------------------------------------------------------------------
// Earliest / latest boundaries (direct rule).
// ---------------------------------------------------------------------------

it('rejects a value before a custom earliest boundary', function (): void {
    $config = ['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-06-15'];

    expect(runDateBoundary($config, Carbon::parse('2026-06-10')))->toContain('on or after 2026-06-15');
    expect(runDateBoundary($config, Carbon::parse('2026-06-15')))->toBeNull();
    expect(runDateBoundary($config, Carbon::parse('2026-06-20')))->toBeNull();
});

it('rejects a value after a custom latest boundary', function (): void {
    $config = ['latest_date_type' => 'custom', 'latest_date_custom' => '2026-06-15'];

    expect(runDateBoundary($config, Carbon::parse('2026-06-20')))->toContain('on or before 2026-06-15');
    expect(runDateBoundary($config, Carbon::parse('2026-06-15')))->toBeNull();
    expect(runDateBoundary($config, Carbon::parse('2026-06-10')))->toBeNull();
});

it('rejects a past value under a today earliest with exclude_before_today', function (): void {
    // A custom earliest in the past, floored at today (2026-06-01) by the toggle.
    $config = [
        'earliest_date_type' => 'custom',
        'earliest_date_custom' => '2020-01-01',
        'earliest_date_exclude_before_today' => true,
    ];

    expect(runDateBoundary($config, Carbon::parse('2026-05-31')))->toContain('on or after 2026-06-01');
    expect(runDateBoundary($config, Carbon::parse('2026-06-01')))->toBeNull();
});

it('resolves a today boundary with an offset', function (): void {
    $config = ['latest_date_type' => 'today', 'latest_date_today_offset' => [['operator' => 'add', 'number' => 7, 'unit' => 'day']]];

    expect(runDateBoundary($config, Carbon::parse('2026-06-09')))->toContain('on or before 2026-06-08');
    expect(runDateBoundary($config, Carbon::parse('2026-06-08')))->toBeNull();
});

// ---------------------------------------------------------------------------
// field-relative boundary — reads the sibling's submitted value from the bag.
// ---------------------------------------------------------------------------

it('validates a field-relative earliest against the sibling submitted value', function (): void {
    $config = ['earliest_date_type' => 'field', 'earliest_date_field' => 'start'];

    // end before start → rejected; on/after start → accepted.
    expect(runDateBoundary($config, Carbon::parse('2026-06-05'), ['start' => Carbon::parse('2026-06-10'), 'when' => Carbon::parse('2026-06-05')]))
        ->toContain('on or after 2026-06-10');
    expect(runDateBoundary($config, Carbon::parse('2026-06-12'), ['start' => Carbon::parse('2026-06-10'), 'when' => Carbon::parse('2026-06-12')]))
        ->toBeNull();
});

it('applies the field offset to a field-relative boundary', function (): void {
    $config = ['earliest_date_type' => 'field', 'earliest_date_field' => 'start', 'earliest_date_field_offset' => [['operator' => 'add', 'number' => 1, 'unit' => 'day']]];

    // earliest = start + 1 day = 2026-06-11
    expect(runDateBoundary($config, Carbon::parse('2026-06-10'), ['start' => Carbon::parse('2026-06-10')]))
        ->toContain('on or after 2026-06-11');
    expect(runDateBoundary($config, Carbon::parse('2026-06-11'), ['start' => Carbon::parse('2026-06-10')]))
        ->toBeNull();
});

it('imposes no field boundary when the referenced sibling is empty', function (): void {
    $config = ['earliest_date_type' => 'field', 'earliest_date_field' => 'start'];

    expect(runDateBoundary($config, Carbon::parse('1990-01-01'), ['start' => null]))->toBeNull();
});

it('resolves a field boundary from a sibling in the same nested set (dotted attribute)', function (): void {
    $config = ['earliest_date_type' => 'field', 'earliest_date_field' => 'start'];
    $data = ['window' => [['start' => Carbon::parse('2026-06-10'), 'when' => Carbon::parse('2026-06-05')]]];

    expect(runDateBoundary($config, Carbon::parse('2026-06-05'), $data, 'window.0.when'))
        ->toContain('on or after 2026-06-10');
});

// ---------------------------------------------------------------------------
// days_of_week + date_exceptions (direct rule).
// ---------------------------------------------------------------------------

it('rejects a value on a disallowed weekday', function (): void {
    // Weekdays only (Mon–Fri). 2026-06-06 is a Saturday.
    $config = ['days_of_week' => ['1', '2', '3', '4', '5']];

    expect(runDateBoundary($config, Carbon::parse('2026-06-06')))->toContain('day of the week');
    expect(runDateBoundary($config, Carbon::parse('2026-06-01')))->toBeNull();
});

it('rejects a value inside a single-date blackout', function (): void {
    $config = ['date_exceptions' => [['type' => 'single_date', 'item' => '2026-07-04']]];

    expect(runDateBoundary($config, Carbon::parse('2026-07-04')))->toContain('not available');
    expect(runDateBoundary($config, Carbon::parse('2026-07-05')))->toBeNull();
});

it('rejects a value inside a blackout range (inclusive)', function (): void {
    $config = ['date_exceptions' => [['type' => 'date_range', 'item' => ['start' => '2026-07-01', 'end' => '2026-07-07']]]];

    expect(runDateBoundary($config, Carbon::parse('2026-07-01')))->toContain('not available');
    expect(runDateBoundary($config, Carbon::parse('2026-07-07')))->toContain('not available');
    expect(runDateBoundary($config, Carbon::parse('2026-07-08')))->toBeNull();
});

// ---------------------------------------------------------------------------
// Range-mode values — both endpoints are checked.
// ---------------------------------------------------------------------------

it('checks both endpoints of a range value', function (): void {
    $config = ['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-06-01', 'latest_date_type' => 'custom', 'latest_date_custom' => '2026-06-30'];

    // start before earliest
    expect(runDateBoundary($config, ['start' => Carbon::parse('2026-05-20'), 'end' => Carbon::parse('2026-06-10')]))->toContain('on or after 2026-06-01');
    // end after latest
    expect(runDateBoundary($config, ['start' => Carbon::parse('2026-06-10'), 'end' => Carbon::parse('2026-07-10')]))->toContain('on or before 2026-06-30');
    // both in range
    expect(runDateBoundary($config, ['start' => Carbon::parse('2026-06-10'), 'end' => Carbon::parse('2026-06-20')]))->toBeNull();
});

// ---------------------------------------------------------------------------
// Empty / time-enabled.
// ---------------------------------------------------------------------------

it('never rejects an empty value (required is a separate rule)', function (mixed $value): void {
    $config = ['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-06-15'];

    expect(runDateBoundary($config, $value))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty array' => [[]],
    'partial range' => [['start' => null, 'end' => null]],
]);

it('compares a date-only value by its own-frame calendar day', function (): void {
    // A date-only value (no time_enabled) is a UTC-anchored instant; its own-frame
    // day is the calendar day, so a day-granularity boundary compares directly.
    $config = ['latest_date_type' => 'custom', 'latest_date_custom' => '2026-06-15'];

    expect(runDateBoundary($config, Carbon::parse('2026-06-15T23:30:00Z')))->toBeNull();
    expect(runDateBoundary($config, Carbon::parse('2026-06-16T00:30:00Z')))->toContain('on or before 2026-06-15');
});

it('reads a time-enabled value calendar day in the display timezone, not UTC', function (): void {
    // The picker/boundaries/today all use the display timezone; a time-bearing
    // value reaches the rule as a UTC instant of the display wall clock. Reading it
    // in UTC would push an evening pick onto the next day (UAMS-Web/uams-statamic-archive#1394 review finding).
    Config::set('statamic.system.display_timezone', 'America/Chicago');

    // 2026-06-14 23:30 America/Chicago == 2026-06-15 04:30 UTC. The picked DAY is
    // June 14 — before earliest June 15 — so it must be REJECTED (bypass fixed).
    $earliest = ['time_enabled' => true, 'earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-06-15'];
    expect(runDateBoundary($earliest, Carbon::parse('2026-06-15T04:30:00Z')))->toContain('on or after 2026-06-15');

    // 2026-06-15 23:30 America/Chicago == 2026-06-16 04:30 UTC. The picked DAY is
    // June 15 — within latest June 15 — so it must be ACCEPTED (false-reject fixed).
    $latest = ['time_enabled' => true, 'latest_date_type' => 'custom', 'latest_date_custom' => '2026-06-15'];
    expect(runDateBoundary($latest, Carbon::parse('2026-06-16T04:30:00Z')))->toBeNull();
});

// ---------------------------------------------------------------------------
// End-to-end through the Statamic fields validator (proves the wiring fires).
// ---------------------------------------------------------------------------

it('rejects an out-of-range submission end-to-end through the fields validator', function (): void {
    $errors = boundaryBlueprintErrors(
        ['latest_date_type' => 'custom', 'latest_date_custom' => '2026-06-15'],
        ['when' => '2026-06-20'],
    );

    expect($errors->has('when'))->toBeTrue();
});

it('accepts an in-range submission end-to-end', function (): void {
    $errors = boundaryBlueprintErrors(
        ['latest_date_type' => 'custom', 'latest_date_custom' => '2026-06-15'],
        ['when' => '2026-06-10'],
    );

    expect($errors->has('when'))->toBeFalse();
});

it('enforces a field-relative boundary end-to-end against the sibling submitted value', function (): void {
    $errors = boundaryBlueprintErrors(
        ['earliest_date_type' => 'field', 'earliest_date_field' => 'start'],
        ['start' => '2026-06-10', 'when' => '2026-06-05'],
        [['handle' => 'start', 'field' => ['type' => 'date', 'mode' => 'single', 'time_enabled' => false, 'format' => 'Y-m-d']]],
    );

    expect($errors->has('when'))->toBeTrue();
});

it('leaves an unconstrained date field unvalidated end-to-end', function (): void {
    $errors = boundaryBlueprintErrors([], ['when' => '1990-01-01']);

    expect($errors->has('when'))->toBeFalse();
});
