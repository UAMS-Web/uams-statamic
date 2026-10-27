<?php

declare(strict_types=1);

use App\Support\People\CertificationActivityValidity;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Asserts certification computed-field merge logic using in-memory fixture rows that
 * mirror `content/collections/people/default/c-lowry-barnes.md` (no repository entries).
 *
 * @command  php artisan test --compact tests/Feature/PeopleCertificationsComputedTest.php
 *
 * Mutation: 100% on App\Support\People\CertificationActivityValidity (81 mutants, --covered-only).
 */
uses(TestCase::class);

// Reset Carbon test clock after each test.
afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Stored-shape certifications list mirroring the first set from c-lowry-barnes.md.
 *
 * @return list<array<string, mixed>>
 */
function peopleCertificationsComputedTestCLowryBarnesCertificationsFixture(): array
{
    return [
        [
            'id' => 'bUTfObYBRwQZV2FRkFlOP',
            'activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
            'type' => 'board_certification',
            'enabled' => true,
            'activity_date_issued' => '1994-07-15',
            'activity_date_audit' => '2025-01-01',
            'activity_date_expires' => '2034-12-31',
        ],
    ];
}

it('reports the fixture activity valid-for duration and active within the window', function (): void {
    // Pin Statamic display timezone for deterministic "today"
    config()->set(
        'statamic.system.display_timezone',
        'America/Chicago'
    );

    // Freeze "now" inside the inclusive expiry window from the fixture
    Carbon::setTestNow(
        Carbon::parse(
            '2025-06-15 12:00:00',
            'America/Chicago'
        )
    );

    // Merge computed flags using stored-shape rows (same as c-lowry-barnes sample row)
    $svc = new CertificationActivityValidity;

    $certs = $svc->mergeIntoCertificationsRows(
        peopleCertificationsComputedTestCLowryBarnesCertificationsFixture()
    );

    expect($certs)->toBeArray();
    expect($certs)->not->toBeEmpty();

    /** @var array<int, array<string, mixed>> $certs */
    $first = $certs[0];

    expect($first['activity_valid_for'] ?? null)->toBe('P40Y5M17D');
    expect($first['activity_active_query'] ?? false)->toBeTrue();
});

it('keeps the duration stable but marks the fixture inactive after expiry', function (): void {
    config()->set(
        'statamic.system.display_timezone',
        'America/Chicago'
    );

    Carbon::setTestNow(
        Carbon::parse(
            '2035-01-01 12:00:00',
            'America/Chicago'
        )
    );

    $svc = new CertificationActivityValidity;

    $certs = $svc->mergeIntoCertificationsRows(
        peopleCertificationsComputedTestCLowryBarnesCertificationsFixture()
    );

    /** @var array<int, array<string, mixed>> $certs */
    $first = $certs[0];

    expect($first['activity_valid_for'] ?? null)->toBe('P40Y5M17D');
    expect($first['activity_active_query'] ?? true)->toBeFalse();
});
