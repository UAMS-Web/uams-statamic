<?php

declare(strict_types=1);

use App\Tags\LocationModifiedHoursNotice;
use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

// Pin "today" so the fixed future/past date fixtures below are deterministic
// against the auto-expiry filter regardless of the real calendar date.
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));
});

/**
 * Unit tests for the visitor-facing `location_modified_hours_notice` tag (UAMS-Web/uams-statamic-archive#1328):
 * the human-readable companion to the `schema_location_hours` JSON-LD emission.
 *
 * @command  php artisan test --compact tests/Feature/Tags/LocationModifiedHoursNoticeTest.php
 */
const NOTICE_TAG = '{{ location_modified_hours_notice :special_hours="special_hours" }}';

/**
 * @param  array<int, mixed>  $special
 */
function renderHoursNotice(array $special): string
{
    return trim((string) Antlers::parse(NOTICE_TAG, ['special_hours' => $special]));
}

it('exposes location_modified_hours_notice as the tag handle', function (): void {
    expect(LocationModifiedHoursNotice::handle())->toBe('location_modified_hours_notice');
});

it('renders nothing when there are no special hours', function (): void {
    expect(renderHoursNotice([]))->toBe('');
});

it('renders an upcoming holiday closure as a human-readable notice', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('location-hours-notice')
        ->toContain('Modified Hours')
        ->toContain('Thanksgiving')
        ->toContain('Closed')
        ->toContain('Nov 26, 2026');
});

it('omits a holiday whose window is entirely in the past', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving 2025',
            'rules' => [
                ['date' => '2025-11-27', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe('');
});

it('shows only the still-active entry when a block mixes past and future', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Old Holiday',
            'rules' => [
                ['date' => '2025-01-01', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
        [
            'type' => 'holiday',
            'title' => 'Winter Break',
            'rules' => [
                ['date' => '2026-12-24', 'through' => '2026-12-26', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Winter Break')
        ->toContain('Dec 24 – Dec 26, 2026');
    expect($html)->not->toContain('Old Holiday');
});

it('keeps an ongoing temporary change with a blank valid_through', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Reduced Hours',
            'valid_from' => '2025-06-01',
            'valid_through' => null,
            'rules' => [
                ['type' => 'custom', 'days' => ['monday'], 'service_mode' => 'in_person', 'status' => 'open', 'time_span' => [['open' => '09:00', 'close' => '12:00']]],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Reduced Hours')
        ->toContain('>Modified hours</span>');
});

it('omits a temporary change whose valid_through has passed', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Old Renovation',
            'valid_from' => '2026-06-01',
            'valid_through' => '2026-06-30',
            'rules' => [
                ['type' => 'custom', 'days' => ['monday'], 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe('');
});

it('summarizes a non-closed holiday as "Modified hours" rather than "Closed"', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Christmas Eve',
            'rules' => [
                ['date' => '2026-12-24', 'through' => null, 'service_mode' => 'in_person', 'status' => 'open', 'time_span' => [['open' => '08:00', 'close' => '12:00']]],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Christmas Eve')
        ->toContain('>Modified hours</span>');
    expect($html)->not->toContain('>Closed</span>');
});

it('formats a single-day holiday as one date, not a range', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'New Year',
            'rules' => [
                ['date' => '2026-12-31', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toContain('Dec 31, 2026');
    expect($html)->not->toContain('–');
});

it('spells out both years for a holiday window that crosses the new year', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Winter Closure',
            'rules' => [
                ['date' => '2026-12-30', 'through' => '2027-01-02', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toContain('Dec 30, 2026 – Jan 2, 2027');
});

it('emits one notice per holiday rule rather than folding disjoint dates into one window', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Federal Holidays',
            'rules' => [
                ['date' => '2026-09-07', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
                ['date' => '2026-12-25', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    // Two separate single-day notices — NOT a folded "Sep 7 – Dec 25" window.
    expect(substr_count($html, 'location-hours-notice-item'))->toBe(2);
    expect($html)
        ->toContain('Sep 7, 2026')
        ->toContain('Dec 25, 2026');
    expect($html)->not->toContain('Sep 7, 2026 – Dec 25, 2026');
    expect($html)->not->toContain('Sep 7 – Dec 25, 2026');
});

it('drops only the expired rules of a holiday, keeping the active ones', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Holidays',
            'rules' => [
                ['date' => '2025-01-01', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
                ['date' => '2026-12-25', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    // Only the future rule survives.
    expect(substr_count($html, 'location-hours-notice-item'))->toBe(1);
    expect($html)->toContain('Dec 25, 2026');
});

it('keeps a holiday whose window ends exactly today (boundary, not expired)', function (): void {
    // "today" is pinned to 2026-07-11; a closure ending today must still show.
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Ends Today',
            'rules' => [
                ['date' => '2026-07-09', 'through' => '2026-07-11', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Ends Today')
        ->toContain('Jul 9 – Jul 11, 2026');
});

it('summarizes an all-closed temporary change as "Closed"', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Full Closure',
            'valid_from' => '2026-08-01',
            'valid_through' => '2026-08-05',
            'rules' => [
                ['type' => 'custom', 'days' => ['monday'], 'service_mode' => 'in_person', 'status' => 'closed'],
                ['type' => 'custom', 'days' => ['tuesday'], 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    // Target the per-item status span; the section's aria-label is always
    // "Modified hours" and would otherwise mask the assertion.
    expect($html)
        ->toContain('Full Closure')
        ->toContain('>Closed</span>');
    expect($html)->not->toContain('>Modified hours</span>');
});

it('trims surrounding whitespace from the notice title', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => '   Juneteenth   ',
            'rules' => [
                ['date' => '2026-06-19', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    // 2026-06-19 is before the pinned today (2026-07-11) — expired, so no notice.
    // Use a future single-day holiday to assert the trimmed title instead.
    $future = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => '   Labor Day   ',
            'rules' => [
                ['date' => '2026-09-07', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($future)->toContain('>Labor Day</strong>');
    expect($future)->not->toContain('   Labor Day   ');
});

/**
 * Service-mode handling (UAMS-Web/uams-statamic-archive#2466). `special_hours` rules carry the same
 * `service_mode` the weekly `regular_hours` rules do, and this tag read it
 * nowhere -- so a telemedicine exception rendered as a premises exception, and
 * `allRulesClosed()` was wrong in BOTH directions.
 *
 * The overstated case is the one that matters most: a closed telemedicine-only
 * entry made premises that were open all week read as "Closed", which sends a
 * reader away from a clinic that would have seen them.
 */
it('does not let an open telemedicine rule soften a premises closure', function (): void {
    // The UNDERSTATED case. Every in-person rule is closed, so the premises are
    // shut; an unscoped array_all sees the open telemedicine rule and reports
    // "Modified hours".
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'System upgrade',
            'valid_from' => '2026-08-01',
            'valid_through' => '2026-08-03',
            'rules' => [
                ['days' => ['monday'], 'service_mode' => 'in_person', 'status' => 'closed'],
                ['days' => ['monday'], 'service_mode' => 'telemedicine', 'status' => 'open'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Closed')
        ->not->toContain('Modified hours<');
});

it('does not report a premises closure for a telemedicine-only temporary change', function (): void {
    // The OVERSTATED case, and the dangerous direction.
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Virtual visits paused',
            'valid_from' => '2026-08-01',
            'valid_through' => '2026-08-03',
            'rules' => [
                ['days' => ['monday'], 'service_mode' => 'telemedicine', 'status' => 'closed'],
            ],
        ],
    ]);

    // It still renders -- the exception is real -- but it is labeled as the
    // remote service rather than presented as the building being shut
    expect($html)
        ->toContain('Virtual visits paused')
        ->toContain('location-hours-notice-mode')
        ->toContain('Telemedicine');
});

it('labels a telemedicine-only holiday rule rather than claiming the premises are closed', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'telemedicine', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Thanksgiving')
        ->toContain('location-hours-notice-mode')
        ->toContain('Telemedicine');
});

it('keeps a premises holiday rule unlabeled, exactly as before', function (): void {
    // The regression guard for the overwhelming majority of entries
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Closed')
        ->not->toContain('location-hours-notice-mode')
        ->not->toContain('Telemedicine');
});

it('renders a mixed-mode holiday as two rows, one labeled and one not', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'telemedicine', 'status' => 'open'],
            ],
        ],
    ]);

    expect(substr_count($html, 'location-hours-notice-item'))->toBe(2);
    expect(substr_count($html, 'location-hours-notice-mode'))->toBe(1);
});

it('treats a temporary change carrying any premises rule as a premises exception', function (): void {
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Reduced hours',
            'valid_from' => '2026-08-01',
            'valid_through' => '2026-08-03',
            'rules' => [
                ['days' => ['monday'], 'service_mode' => 'telemedicine', 'status' => 'closed'],
                ['days' => ['monday'], 'service_mode' => 'in_person', 'status' => 'open'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Reduced hours')
        ->not->toContain('location-hours-notice-mode');
});

it('treats an absent service_mode as in-person', function (): void {
    // Matching UAMS-Web/uams-statamic-archive#2405 and the other three consumers: only an explicit
    // telemedicine is remote, so a stored row predating the field stays visible
    // as a premises exception rather than being relabeled
    $html = renderHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)
        ->toContain('Closed')
        ->not->toContain('location-hours-notice-mode');
});

it('treats a rule-less temporary change as a premises exception', function (): void {
    // Nothing in the data says "remote", so nothing should assert it
    $html = renderHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Building works',
            'valid_from' => '2026-08-01',
            'valid_through' => '2026-08-03',
            'rules' => [],
        ],
    ]);

    expect($html)
        ->toContain('Building works')
        ->toContain('Modified hours')
        ->not->toContain('location-hours-notice-mode');
});
