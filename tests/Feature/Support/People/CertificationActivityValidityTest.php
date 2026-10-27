<?php

declare(strict_types=1);

use App\Support\People\CertificationActivityValidity;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// cspell:ignore Mondayz

uses(TestCase::class);

/**
 * Unit tests for calendar parsing, duration encoding, and active-window logic.
 *
 * Uses inline fixtures, except the UAMS-Web/uams-statamic-archive#2973 certificate cases, which classify a row
 * through its certifications term and so read that term from content.
 *
 * Mutation: with tests/Feature/PeopleCertificationsComputedTest.php, every mutant of the class is
 * killed or times out in an endless loop. The equivalent ones are annotated in the source.
 *
 * @command  php artisan test --compact tests/Feature/Support/People/CertificationActivityValidityTest.php
 */
beforeEach(function () {
    config()->set('statamic.system.display_timezone', 'America/Chicago');
});

it('yields P0Y0M1D for the same issue and expiry day', function () {
    $svc = new CertificationActivityValidity;

    $issued = $svc->parsePlainDate('2020-06-10');
    $expires = $svc->parsePlainDate('2020-06-10');

    expect($issued)->not->toBeNull();
    expect($expires)->not->toBeNull();
    expect($svc->activityValidForIso($issued, $expires))->toBe('P0Y0M1D');
});

it('omits duration on merged rows when a date is missing', function () {
    $svc = new CertificationActivityValidity;

    $merged = $svc->mergeIntoCertificationsRows([
        [
            'type' => 'board_certification',
            'activity_date_issued' => '2020-01-01',
        ],
    ]);

    expect($merged)->toBeArray();
    expect(data_get($merged, '0'))->not->toHaveKey('activity_valid_for');
    expect(data_get($merged, '0.activity_active_query'))->toBeFalse();
});

it('yields no duration and inactive for an inverted issued/expires order', function () {
    $svc = new CertificationActivityValidity;

    $issued = $svc->parsePlainDate('2025-01-01');
    $expires = $svc->parsePlainDate('2020-01-01');

    expect($svc->activityValidForIso($issued, $expires))->toBeNull();
    expect($svc->activityActiveQuery(
        $issued,
        $expires,
        CarbonImmutable::parse('2022-06-01', 'America/Chicago')->startOfDay()
    ))->toBeFalse();
});

it('rejects ambiguous slash-only date strings', function () {
    $svc = new CertificationActivityValidity;

    expect($svc->parsePlainDate('01/02/2020'))->toBeNull();
});

it('strips the time from a datetime string in the display timezone', function () {
    $svc = new CertificationActivityValidity;

    $parsed = $svc->parsePlainDate('1994-07-15T15:30:00');

    expect($parsed?->toDateString())->toBe('1994-07-15');
});

it('returns stored values it cannot read as rows unchanged', function (mixed $stored): void {
    expect((new CertificationActivityValidity)->mergeIntoCertificationsRows($stored))->toBe($stored);
})->with([
    'text' => ['not rows'],
    'null' => [null],
    'a keyed array' => [['first' => ['activity_date_issued' => '2020-01-01']]],
]);

it('merges the computed fields into every row, from a list or a collection', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2021-06-15 12:00', 'America/Chicago'));
    $rows = [
        ['type' => 'board_certification', 'activity_date_issued' => '2020-01-31', 'activity_date_expires' => '2022-02-28'],
        ['activity_date_issued' => '2021-07-01', 'activity_date_expires' => '2021-06-01', 'activity_valid_for' => 'P9Y'],
    ];
    $expected = [
        ['type' => 'board_certification', 'activity_date_issued' => '2020-01-31', 'activity_date_expires' => '2022-02-28', 'activity_valid_for' => 'P2Y1M1D', 'activity_active_query' => true],
        ['activity_date_issued' => '2021-07-01', 'activity_date_expires' => '2021-06-01', 'activity_active_query' => false],
    ];

    try {
        expect((new CertificationActivityValidity)->mergeIntoCertificationsRows($rows))->toBe($expected)
            ->and((new CertificationActivityValidity)->mergeIntoCertificationsRows(collect($rows)))->toBe($expected)
            ->and((new CertificationActivityValidity)->mergeIntoCertificationsRows([]))->toBe([]);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('reads a plain date from text, a date array, a collection or a date object', function (mixed $raw, ?string $date): void {
    expect((new CertificationActivityValidity)->parsePlainDate($raw)?->format('Y-m-d H:i:s e'))->toBe($date);
})->with([
    'null' => [null, null],
    'empty text' => ['', null],
    'blank text' => ['   ', null],
    'a padded plain date' => [' 2020-02-29 ', '2020-02-29 00:00:00 America/Chicago'],
    'a date array' => [['date' => '2020-03-01'], '2020-03-01 00:00:00 America/Chicago'],
    'a date array with no date' => [['time' => '10:00'], null],
    'a date array with a date that is not text' => [['date' => 20200301], null],
    'a collection' => [collect(['date' => '2020-03-02']), '2020-03-02 00:00:00 America/Chicago'],
    'a date object in another timezone' => [CarbonImmutable::parse('2020-03-03 03:00', 'UTC'), '2020-03-02 00:00:00 America/Chicago'],
    'a number' => [20200303, null],
    'a time without a T' => ['2020-03-04 10:30', '2020-03-04 00:00:00 America/Chicago'],
    'a UTC designator, the evening before in Chicago' => ['2020-03-05Z', '2020-03-04 00:00:00 America/Chicago'],
    'an offset' => ['2020-03-06 +0500', '2020-03-05 00:00:00 America/Chicago'],
    'text that is not a date' => ['someday', null],
    'a placeholder with a capital T' => ['TBD', null],
    'a weekday name' => ['Tuesday', null],
    'text with a time that Carbon cannot read' => ['TBD 10:30', null],
    'an impossible ISO 8601 datetime' => ['2020-13-45T10:00', null],
    'a basic-format ISO 8601 datetime, with no colon' => ['20200307T013000', '2020-03-07 00:00:00 America/Chicago'],
    'an ISO 8601 datetime with an offset' => ['2020-03-07T01:00:00+00:00', '2020-03-06 00:00:00 America/Chicago'],
]);

it('reads a time, a zone letter or an offset as part of a date only when it follows an ISO date (#3246)', function (string $raw, ?string $date): void {
    // Pin the clock to a Tuesday, so text read from the clock would return a date
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2021-06-15 12:00', 'America/Chicago'));

    try {
        expect((new CertificationActivityValidity)->parsePlainDate($raw)?->format('Y-m-d H:i:s e'))->toBe($date);
    } finally {
        CarbonImmutable::setTestNow();
    }
})->with([
    'a weekday with a time' => ['Tuesday 10:30', null],
    'a relative day with a time' => ['tomorrow 10:30', null],
    'a relative week with a time' => ['next week 10:30', null],
    'a weekday ending in a zone letter' => ['Mondayz', null],
    'a month-first date with a zone letter' => ['05/01/2024 Z', null],
    'a day-first date with a zone letter' => ['01/05/2024 Z', null],
    'a month-first date with a time' => ['05/01/2024 10:30', null],
    'a lone zone letter' => ['z', null],
    'a word starting with a zone letter' => ['Zulu', null],
    'a relative week with a zone letter' => ['next week z', null],
    'a relative word with an offset' => ['tomorrow +05:00', null],
    'a placeholder' => ['TBD', null],
    'a weekday name' => ['Tuesday', null],
    'a relative word' => ['now', null],
    'a placeholder with a time' => ['TBD 10:30', null],
    'a note with a time' => ['see notes 09:00', null],
    'a date followed by a relative word' => ['2020-03-04 tomorrow', null],
    'a date with an hour and no minutes after a space' => ['2020-03-04 10', null],
    'a basic date with no time' => ['20200304Z', null],
    'a date with a time and text after it' => ['2020-03-04 10:30 or later', null],
    'a strict date' => ['1994-07-15', '1994-07-15 00:00:00 America/Chicago'],
    'an extended datetime' => ['1994-07-15T15:30:00', '1994-07-15 00:00:00 America/Chicago'],
    'an extended datetime with fractional seconds' => ['1994-07-15T15:30:00.250', '1994-07-15 00:00:00 America/Chicago'],
    'an extended datetime with only an hour' => ['1994-07-15T15', '1994-07-15 00:00:00 America/Chicago'],
    'a basic datetime' => ['19940715T153000', '1994-07-15 00:00:00 America/Chicago'],
    'a basic datetime with a zone letter' => ['19940715T153000Z', '1994-07-15 00:00:00 America/Chicago'],
    'an extended datetime with a zone letter' => ['1994-07-15T15:30:00Z', '1994-07-15 00:00:00 America/Chicago'],
    'an extended datetime with a lowercase zone letter' => ['1994-07-15T15:30:00z', '1994-07-15 00:00:00 America/Chicago'],
    'an extended datetime with an offset' => ['1994-07-15T15:30:00-05:00', '1994-07-15 00:00:00 America/Chicago'],
    'a date and time with a space and seconds' => ['1994-07-15 15:30:45', '1994-07-15 00:00:00 America/Chicago'],
    'a UTC midnight, the evening before in Chicago' => ['1994-07-15T00:30:00Z', '1994-07-14 00:00:00 America/Chicago'],
]);

it('computes the other rows when one row holds a date it cannot read', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2021-06-15 12:00', 'America/Chicago'));
    $rows = [
        ['activity_date_issued' => 'TBD', 'activity_date_expires' => '2022-02-28'],
        ['activity_date_issued' => '2020-01-31', 'activity_date_expires' => '2022-02-28'],
    ];

    try {
        expect((new CertificationActivityValidity)->mergeIntoCertificationsRows($rows))->toBe([
            ['activity_date_issued' => 'TBD', 'activity_date_expires' => '2022-02-28', 'activity_active_query' => false],
            ['activity_date_issued' => '2020-01-31', 'activity_date_expires' => '2022-02-28', 'activity_valid_for' => 'P2Y1M1D', 'activity_active_query' => true],
        ]);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('gives no duration unless both dates are set and in order', function (?string $issued, ?string $expires, ?string $duration): void {
    $svc = new CertificationActivityValidity;

    expect($svc->activityValidForIso($svc->parsePlainDate($issued), $svc->parsePlainDate($expires)))->toBe($duration);
})->with([
    'no issue date' => [null, '2020-01-01', null],
    'no expiry date' => ['2020-01-01', null, null],
    'one day apart' => ['2020-01-01', '2020-01-02', 'P0Y0M2D'],
    'over a year and a month' => ['2019-01-15', '2020-02-20', 'P1Y1M6D'],
    'month ends' => ['2020-01-31', '2020-02-29', 'P0Y1M1D'],
]);

it('is active from the issue day through the expiry day', function (string $today, bool $active): void {
    $svc = new CertificationActivityValidity;

    expect($svc->activityActiveQuery(
        $svc->parsePlainDate('2020-01-10'),
        $svc->parsePlainDate('2020-01-20'),
        $svc->parsePlainDate($today)
    ))->toBe($active);
})->with([
    'the day before' => ['2020-01-09', false],
    'the issue day' => ['2020-01-10', true],
    'the expiry day' => ['2020-01-20', true],
    'the day after' => ['2020-01-21', false],
]);

it('is inactive without both dates, and checks against today by default', function (): void {
    $svc = new CertificationActivityValidity;
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2020-01-15 23:30', 'America/Chicago'));

    try {
        expect($svc->activityActiveQuery(null, $svc->parsePlainDate('2020-01-20')))->toBeFalse()
            ->and($svc->activityActiveQuery($svc->parsePlainDate('2020-01-10'), null))->toBeFalse()
            ->and($svc->activityActiveQuery($svc->parsePlainDate('2020-01-10'), $svc->parsePlainDate('2020-01-15')))->toBeTrue()
            ->and($svc->activityActiveQuery($svc->parsePlainDate('2020-01-16'), $svc->parsePlainDate('2020-01-20')))->toBeFalse()
            ->and($svc->todayDateInDisplayTimezone()->format('Y-m-d H:i:s e'))->toBe('2020-01-15 00:00:00 America/Chicago');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('falls back to UTC when the display timezone is not set to text', function (mixed $timezone, string $resolved): void {
    config()->set('statamic.system.display_timezone', $timezone);

    expect((new CertificationActivityValidity)->displayTimezone())->toBe($resolved);
})->with([
    'a timezone' => ['America/New_York', 'America/New_York'],
    'empty' => ['', 'UTC'],
    'null' => [null, 'UTC'],
    'not text' => [['America/Chicago'], 'UTC'],
]);

it('gives no duration when the issue time falls after an expiry earlier the same day', function (): void {
    expect((new CertificationActivityValidity)->activityValidForIso(
        CarbonImmutable::parse('2020-01-01 10:00', 'America/Chicago'),
        CarbonImmutable::parse('2020-01-01 00:00', 'America/Chicago')
    ))->toBeNull();
});

it('counts the days back as negative when the end comes before the start', function (): void {
    expect((new CertificationActivityValidity)->calendarPnYnMnD(
        CarbonImmutable::parse('2020-01-05', 'America/Chicago'),
        CarbonImmutable::parse('2020-01-01', 'America/Chicago')
    ))->toBe('P0Y0M-4D');
});

it('treats an assessment-based certificate as issued, not expiring (#2973)', function (string $today, bool $active): void {
    // Freeze today in the display timezone
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse($today.' 12:00', 'America/Chicago'));

    try {
        // Merge one certificate row whose stored expiration has already passed
        $merged = mustBeArray((new CertificationActivityValidity)->mergeIntoCertificationsRows([
            [
                'type' => 'other_certification',
                'activity' => 'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
                'activity_date_issued' => '2020-03-01',
                'activity_date_audit' => '2020-06-01',
                'activity_date_expires' => '2020-12-31',
                'activity_valid_for' => 'P9Y',
            ],
        ]), 'the merged rows');
    } finally {
        Carbon\Carbon::setTestNow();
    }

    $row = mustBeArray($merged[0], 'the certificate row');

    // The certificate reports no validity window, is active from its issued date, and keeps its stored dates
    expect($row)->not->toHaveKey('activity_valid_for')
        ->and($row['activity_active_query'])->toBe($active)
        ->and($row['activity_date_audit'])->toBe('2020-06-01')
        ->and($row['activity_date_expires'])->toBe('2020-12-31');
})->with([
    'the day before issue' => ['2020-02-29', false],
    'the issue day' => ['2020-03-01', true],
    'long after the stored expiration' => ['2026-10-13', true],
]);

it('reports no status for a certificate with no issued date (#2973)', function (): void {
    $row = mustBeArray(mustBeArray((new CertificationActivityValidity)->mergeIntoCertificationsRows([
        [
            'type' => 'other_certification',
            'activity' => 'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
            'activity_date_expires' => '2099-12-31',
        ],
    ]), 'the rows')[0], 'the row');

    expect($row)->toHaveKey('activity_active_query')
        ->and($row['activity_active_query'])->toBeNull()
        ->and($row)->not->toHaveKey('activity_valid_for');
});
