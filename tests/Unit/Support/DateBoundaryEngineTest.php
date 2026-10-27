<?php

declare(strict_types=1);

use App\Support\DateBoundaryEngine;
use Carbon\CarbonImmutable;

/**
 * Unit tests for the server-side date-boundary engine — the PHP mirror of
 * `resources/js/date-boundary-engine.test.js`. Every case here has a counterpart
 * there so the two engines stay in lockstep (UAMS-Web/uams-statamic-archive#1394).
 *
 * @command  php artisan test --compact tests/Unit/Support/DateBoundaryEngineTest.php
 */

/** Format a nullable calendar date to `Y-m-d` (or null) for terse assertions. */
function boundaryIso(?CarbonImmutable $date): ?string
{
    return $date?->format('Y-m-d');
}

/** A fixed "today" for deterministic boundary resolution. */
function boundaryToday(): CarbonImmutable
{
    /** @var CarbonImmutable $today */
    $today = CarbonImmutable::create(2026, 6, 1, 0, 0, 0, 'UTC');

    return $today;
}

describe('parseDay', function (): void {
    it('parses a plain ISO date', function (): void {
        expect(boundaryIso(DateBoundaryEngine::parseDay('2026-01-15')))->toBe('2026-01-15');
    });

    it('takes the date part of an ISO datetime (with Z)', function (): void {
        expect(boundaryIso(DateBoundaryEngine::parseDay('2026-01-15T09:30:00.000Z')))->toBe('2026-01-15');
    });

    it('reads the calendar day from a Carbon / DateTime in its own frame', function (): void {
        expect(boundaryIso(DateBoundaryEngine::parseDay(CarbonImmutable::parse('2026-03-04T00:00:00Z'))))->toBe('2026-03-04');
        expect(boundaryIso(DateBoundaryEngine::parseDay(new DateTimeImmutable('2026-03-04 23:00:00'))))->toBe('2026-03-04');
    });

    it('returns null for non-strings and unparseable values', function (): void {
        expect(DateBoundaryEngine::parseDay(null))->toBeNull();
        expect(DateBoundaryEngine::parseDay(1234))->toBeNull();
        expect(DateBoundaryEngine::parseDay('not-a-date'))->toBeNull();
        expect(DateBoundaryEngine::parseDay('2026-13-40'))->toBeNull();
        expect(DateBoundaryEngine::parseDay('2026-02-30'))->toBeNull();
    });
});

describe('firstOffsetRow', function (): void {
    it('returns the first row of a stacked-grid list', function (): void {
        expect(DateBoundaryEngine::firstOffsetRow([['operator' => 'add', 'number' => 1, 'unit' => 'day']]))
            ->toBe(['operator' => 'add', 'number' => 1, 'unit' => 'day']);
    });

    it('accepts a bare offset row', function (): void {
        $row = ['operator' => 'subtract', 'number' => 2, 'unit' => 'week'];

        expect(DateBoundaryEngine::firstOffsetRow($row))->toBe($row);
    });

    it('returns null for empty / missing / malformed grids', function (): void {
        expect(DateBoundaryEngine::firstOffsetRow([]))->toBeNull();
        expect(DateBoundaryEngine::firstOffsetRow(null))->toBeNull();
        expect(DateBoundaryEngine::firstOffsetRow(['number' => 1]))->toBeNull();
        expect(DateBoundaryEngine::firstOffsetRow('nope'))->toBeNull();
    });
});

describe('applyOffset', function (): void {
    $base = CarbonImmutable::create(2026, 1, 15, 0, 0, 0, 'UTC');

    it('adds each unit', function () use ($base): void {
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => 3, 'unit' => 'day'])))->toBe('2026-01-18');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => 2, 'unit' => 'week'])))->toBe('2026-01-29');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => 1, 'unit' => 'month'])))->toBe('2026-02-15');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => 1, 'unit' => 'year'])))->toBe('2027-01-15');
    });

    it('subtracts', function () use ($base): void {
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'subtract', 'number' => 5, 'unit' => 'day'])))->toBe('2026-01-10');
    });

    it('clamps a month/year offset to the end of the target month (parity with the reka client engine)', function (): void {
        // Carbon overflows by default (Jan 31 + 1 month => Mar 3); reka clamps to
        // 2026-02-28. Both engines MUST agree or the server disagrees with the
        // picker. Anchors on the 29th–31st (and Feb 29 for +year) are the cases.
        $jan31 = CarbonImmutable::create(2026, 1, 31, 0, 0, 0, 'UTC');
        $feb29 = CarbonImmutable::create(2028, 2, 29, 0, 0, 0, 'UTC');
        $mar31 = CarbonImmutable::create(2026, 3, 31, 0, 0, 0, 'UTC');

        expect(boundaryIso(DateBoundaryEngine::applyOffset($jan31, ['operator' => 'add', 'number' => 1, 'unit' => 'month'])))->toBe('2026-02-28');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($feb29, ['operator' => 'add', 'number' => 1, 'unit' => 'year'])))->toBe('2029-02-28');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($mar31, ['operator' => 'subtract', 'number' => 1, 'unit' => 'month'])))->toBe('2026-02-28');
    });

    it('is a no-op for zero / negative / unknown / missing', function () use ($base): void {
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => 0, 'unit' => 'day'])))->toBe('2026-01-15');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => -2, 'unit' => 'day'])))->toBe('2026-01-15');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, ['operator' => 'add', 'number' => 2, 'unit' => 'fortnight'])))->toBe('2026-01-15');
        expect(boundaryIso(DateBoundaryEngine::applyOffset($base, null)))->toBe('2026-01-15');
        expect(DateBoundaryEngine::applyOffset(null, ['operator' => 'add', 'number' => 1, 'unit' => 'day']))->toBeNull();
    });
});

describe('resolveBoundary', function (): void {
    it('is null when disabled', function (): void {
        expect(DateBoundaryEngine::resolveBoundary('none', null, null, boundaryToday(), null))->toBeNull();
        expect(DateBoundaryEngine::resolveBoundary(null, null, null, boundaryToday(), null))->toBeNull();
        expect(DateBoundaryEngine::resolveBoundary('mystery', null, null, boundaryToday(), null))->toBeNull();
    });

    it('resolves a custom date', function (): void {
        expect(boundaryIso(DateBoundaryEngine::resolveBoundary('custom', '2026-12-25', null, boundaryToday(), null)))->toBe('2026-12-25');
    });

    it('resolves today with an offset', function (): void {
        expect(boundaryIso(DateBoundaryEngine::resolveBoundary('today', null, null, boundaryToday(), null)))->toBe('2026-06-01');
        expect(boundaryIso(DateBoundaryEngine::resolveBoundary('today', null, null, boundaryToday(), ['operator' => 'add', 'number' => 1, 'unit' => 'week'])))->toBe('2026-06-08');
    });

    it('resolves a referenced field value with an offset', function (): void {
        expect(boundaryIso(DateBoundaryEngine::resolveBoundary('field', null, '2026-06-10T00:00:00Z', boundaryToday(), ['operator' => 'subtract', 'number' => 2, 'unit' => 'day'])))->toBe('2026-06-08');
    });

    it('is null when the referenced field is empty', function (): void {
        expect(DateBoundaryEngine::resolveBoundary('field', null, null, boundaryToday(), null))->toBeNull();
    });
});

describe('resolveEarliest / resolveLatest (config-shaped, mirrors the Vue)', function (): void {
    it('resolves a custom earliest', function (): void {
        expect(boundaryIso(DateBoundaryEngine::resolveEarliest(['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-05-01'], boundaryToday(), null)))->toBe('2026-05-01');
    });

    it('resolves a today latest with its offset', function (): void {
        $config = ['latest_date_type' => 'today', 'latest_date_today_offset' => [['operator' => 'add', 'number' => 30, 'unit' => 'day']]];

        expect(boundaryIso(DateBoundaryEngine::resolveLatest($config, boundaryToday(), null)))->toBe('2026-07-01');
    });

    it('resolves a field earliest against the referenced value with its field offset', function (): void {
        $config = ['earliest_date_type' => 'field', 'earliest_date_field' => 'start', 'earliest_date_field_offset' => [['operator' => 'add', 'number' => 1, 'unit' => 'day']]];

        expect(boundaryIso(DateBoundaryEngine::resolveEarliest($config, boundaryToday(), '2026-06-10')))->toBe('2026-06-11');
    });

    it('floors a past custom earliest at today when exclude_before_today is set', function (): void {
        $config = ['earliest_date_type' => 'custom', 'earliest_date_custom' => '2020-01-01', 'earliest_date_exclude_before_today' => true];

        expect(boundaryIso(DateBoundaryEngine::resolveEarliest($config, boundaryToday(), null)))->toBe('2026-06-01');
    });

    it('keeps a future custom earliest above the today floor', function (): void {
        $config = ['earliest_date_type' => 'custom', 'earliest_date_custom' => '2026-08-01', 'earliest_date_exclude_before_today' => true];

        expect(boundaryIso(DateBoundaryEngine::resolveEarliest($config, boundaryToday(), null)))->toBe('2026-08-01');
    });

    it('applies the today-offset to the exclude_before_today floor', function (): void {
        $config = [
            'earliest_date_type' => 'none',
            'earliest_date_exclude_before_today' => true,
            'earliest_date_today_offset' => [['operator' => 'add', 'number' => 2, 'unit' => 'day']],
        ];

        expect(boundaryIso(DateBoundaryEngine::resolveEarliest($config, boundaryToday(), null)))->toBe('2026-06-03');
    });

    it('is null when no earliest/latest boundary is configured', function (): void {
        expect(DateBoundaryEngine::resolveEarliest([], boundaryToday(), null))->toBeNull();
        expect(DateBoundaryEngine::resolveLatest([], boundaryToday(), null))->toBeNull();
    });
});

describe('normalizeAllowedDays', function (): void {
    it('returns null when every weekday is allowed', function (): void {
        expect(DateBoundaryEngine::normalizeAllowedDays(['0', '1', '2', '3', '4', '5', '6']))->toBeNull();
        expect(DateBoundaryEngine::normalizeAllowedDays([]))->toBeNull();
        expect(DateBoundaryEngine::normalizeAllowedDays(null))->toBeNull();
    });

    it('returns the restricted set as strings', function (): void {
        expect(DateBoundaryEngine::normalizeAllowedDays([1, 2, 3, 4, 5]))->toBe(['1', '2', '3', '4', '5']);
    });
});

describe('normalizeExceptions', function (): void {
    it('turns a single date into a one-day range', function (): void {
        $ranges = DateBoundaryEngine::normalizeExceptions([['type' => 'single_date', 'item' => '2026-07-04']]);

        expect(boundaryIso($ranges[0]['start']))->toBe('2026-07-04');
        expect(boundaryIso($ranges[0]['end']))->toBe('2026-07-04');
    });

    it('keeps a date range', function (): void {
        $ranges = DateBoundaryEngine::normalizeExceptions([
            ['type' => 'date_range', 'item' => ['start' => '2026-07-01', 'end' => '2026-07-07']],
        ]);

        expect(boundaryIso($ranges[0]['start']))->toBe('2026-07-01');
        expect(boundaryIso($ranges[0]['end']))->toBe('2026-07-07');
    });

    it('drops malformed rows', function (): void {
        expect(DateBoundaryEngine::normalizeExceptions([
            ['type' => 'single_date', 'item' => 'nope'],
            ['type' => 'date_range', 'item' => ['start' => '2026-07-01']],
            ['type' => 'date_range', 'item' => 'not-an-array'],
            ['type' => 'mystery'],
            'not-a-row',
            null,
        ]))->toBe([]);
    });

    it('returns [] for non-arrays', function (): void {
        expect(DateBoundaryEngine::normalizeExceptions(null))->toBe([]);
    });
});

describe('isDateUnavailable', function (): void {
    // 2026-06-01 is a Monday; 2026-06-06 is a Saturday; 2026-06-07 is a Sunday.
    /** @var CarbonImmutable $monday */
    $monday = CarbonImmutable::create(2026, 6, 1, 0, 0, 0, 'UTC');
    /** @var CarbonImmutable $saturday */
    $saturday = CarbonImmutable::create(2026, 6, 6, 0, 0, 0, 'UTC');

    it('disables weekdays outside the allowed set (weekends off)', function () use ($monday, $saturday): void {
        $weekdays = ['1', '2', '3', '4', '5'];

        expect(DateBoundaryEngine::isDateUnavailable($monday, $weekdays, []))->toBeFalse();
        expect(DateBoundaryEngine::isDateUnavailable($saturday, $weekdays, []))->toBeTrue();
    });

    it('disables dates inside an exception range (inclusive) and the endpoints', function (): void {
        /** @var CarbonImmutable $jun1 */
        $jun1 = CarbonImmutable::create(2026, 6, 1, 0, 0, 0, 'UTC');
        /** @var CarbonImmutable $jun2 */
        $jun2 = CarbonImmutable::create(2026, 6, 2, 0, 0, 0, 'UTC');
        /** @var CarbonImmutable $jun3 */
        $jun3 = CarbonImmutable::create(2026, 6, 3, 0, 0, 0, 'UTC');
        /** @var CarbonImmutable $jun4 */
        $jun4 = CarbonImmutable::create(2026, 6, 4, 0, 0, 0, 'UTC');

        /** @var list<array{start: CarbonImmutable, end: CarbonImmutable}> $exceptions */
        $exceptions = [['start' => $jun1, 'end' => $jun3]];

        expect(DateBoundaryEngine::isDateUnavailable($jun1, null, $exceptions))->toBeTrue();
        expect(DateBoundaryEngine::isDateUnavailable($jun2, null, $exceptions))->toBeTrue();
        expect(DateBoundaryEngine::isDateUnavailable($jun3, null, $exceptions))->toBeTrue();
        expect(DateBoundaryEngine::isDateUnavailable($jun4, null, $exceptions))->toBeFalse();
    });

    it('is available with no constraints', function () use ($monday): void {
        expect(DateBoundaryEngine::isDateUnavailable($monday, null, []))->toBeFalse();
    });
});

describe('laterOf', function (): void {
    it('returns the later of two dates', function (): void {
        $a = CarbonImmutable::create(2026, 6, 1, 0, 0, 0, 'UTC');
        $b = CarbonImmutable::create(2026, 6, 10, 0, 0, 0, 'UTC');

        expect(boundaryIso(DateBoundaryEngine::laterOf($a, $b)))->toBe('2026-06-10');
        expect(boundaryIso(DateBoundaryEngine::laterOf($b, $a)))->toBe('2026-06-10');
    });

    it('ignores nulls', function (): void {
        $a = CarbonImmutable::create(2026, 6, 1, 0, 0, 0, 'UTC');

        expect(boundaryIso(DateBoundaryEngine::laterOf($a, null)))->toBe('2026-06-01');
        expect(boundaryIso(DateBoundaryEngine::laterOf(null, $a)))->toBe('2026-06-01');
        expect(DateBoundaryEngine::laterOf(null, null))->toBeNull();
    });
});
