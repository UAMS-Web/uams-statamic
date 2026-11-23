<?php

declare(strict_types=1);

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
 * Exact-markup tests for the `location_modified_hours_notice` tag (UAMS-Web/uams-statamic#808).
 *
 * The behavior tests beside this file assert with `toContain`, which cannot see
 * the order, spacing or escaping of the markup. These compare the whole rendered
 * string, so a reordered fragment, a dropped separator or a missing `e()` fails.
 *
 * @command  php artisan test --compact tests/Feature/Tags/LocationModifiedHoursNoticeMarkupTest.php
 */

/**
 * Render the tag over the given `special_hours` rows.
 *
 * @param  array<int, mixed>  $special
 */
function renderExactHoursNotice(array $special): string
{
    return trim((string) Antlers::parse(
        '{{ location_modified_hours_notice :special_hours="special_hours" }}',
        ['special_hours' => $special]
    ));
}

/**
 * The full notice section around the given list items.
 */
function hoursNoticeSection(string $items): string
{
    return '<section class="location-hours-notice" aria-label="Modified hours">'
        .'<h2>Modified Hours</h2>'
        .'<ul class="location-hours-notice-list">'.$items.'</ul>'
        .'</section>';
}

/**
 * One list item, with the mode label only when one is given.
 */
function hoursNoticeItem(string $title, string $status, string $dates, ?string $mode = null): string
{
    return '<li class="location-hours-notice-item">'
        .'<strong class="location-hours-notice-title">'.$title.'</strong>'
        .($mode === null ? '' : ' <span class="location-hours-notice-mode">'.$mode.'</span>')
        .' <span class="location-hours-notice-status">'.$status.'</span>'
        .' <span class="location-hours-notice-dates">'.$dates.'</span>'
        .'</li>';
}

it('renders the exact markup for a single in-person holiday closure', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Thanksgiving', 'Closed', 'Nov 26, 2026')));
});

it('renders the exact markup for a telemedicine holiday rule, with its mode label', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Video visits paused',
            'rules' => [
                ['date' => '2026-12-25', 'through' => null, 'service_mode' => 'telemedicine', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('Video visits paused', 'Closed', 'Dec 25, 2026', 'Telemedicine')
    ));
});

it('renders several entries as consecutive items, in order', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Christmas',
            'rules' => [
                ['date' => '2026-12-24', 'through' => '2026-12-26', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
        [
            'type' => 'temporary_change',
            'title' => 'Renovation',
            'valid_from' => '2026-08-01',
            'valid_through' => '2026-09-15',
            'rules' => [
                ['service_mode' => 'in_person', 'status' => 'open'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('Christmas', 'Closed', 'Dec 24 – Dec 26, 2026')
        .hoursNoticeItem('Renovation', 'Modified hours', 'Aug 1 – Sep 15, 2026')
    ));
});

it('escapes the editor-supplied title', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Staff & "Family" Day',
            'rules' => [
                ['date' => '2026-10-12', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('Staff &amp; &quot;Family&quot; Day', 'Closed', 'Oct 12, 2026')
    ));
});

it('falls back to a generic title when the entry has none', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Modified hours', 'Closed', 'Nov 26, 2026')));
});

it('skips a holiday rule with no date while keeping the dated one', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Winter break',
            'rules' => [
                ['date' => null, 'through' => '2026-12-31', 'service_mode' => 'in_person', 'status' => 'closed'],
                ['date' => '2026-12-31', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Winter break', 'Closed', 'Dec 31, 2026')));
});

it('skips a temporary change with no start date and still renders the entries after it', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Undated change',
            'valid_from' => null,
            'valid_through' => '2026-09-15',
            'rules' => [
                ['service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Thanksgiving', 'Closed', 'Nov 26, 2026')));
});

it('skips an elapsed temporary change and still renders the entries after it', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Spring renovation',
            'valid_from' => '2026-03-01',
            'valid_through' => '2026-04-30',
            'rules' => [
                ['service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
        [
            'type' => 'holiday',
            'title' => 'Thanksgiving',
            'rules' => [
                ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Thanksgiving', 'Closed', 'Nov 26, 2026')));
});

it('ignores an entry of an unknown set type', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'something_else',
            'title' => 'Not a notice',
            'valid_from' => '2026-08-01',
            'rules' => [
                ['date' => '2026-08-01', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe('');
});

it('renders an ongoing temporary change as open-ended from its start date', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'New Saturday hours',
            'valid_from' => '2026-07-01',
            'valid_through' => null,
            'rules' => [
                ['service_mode' => 'in_person', 'status' => 'open'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('New Saturday hours', 'Modified hours', 'from Jul 1, 2026')
    ));
});

it('keeps a temporary change whose end date is still ahead', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Closed for repairs',
            'valid_from' => '2026-07-01',
            'valid_through' => '2026-07-20',
            'rules' => [
                ['service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('Closed for repairs', 'Closed', 'Jul 1 – Jul 20, 2026')
    ));
});

it('reads a rule-less temporary change as modified hours, not a closure', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Hours under review',
            'valid_from' => '2026-07-15',
            'valid_through' => '2026-07-15',
            'rules' => [],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('Hours under review', 'Modified hours', 'Jul 15, 2026')
    ));
});

it('renders an unparseable single date as the raw value', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Typo holiday',
            'rules' => [
                ['date' => 'not-a-date', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Typo holiday', 'Closed', 'not-a-date')));
});

it('renders an unparseable range as the raw dates joined by a dash', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Typo range',
            'rules' => [
                ['date' => 'not-a-date', 'through' => 'also-not-a-date', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(
        hoursNoticeItem('Typo range', 'Closed', 'not-a-date – also-not-a-date')
    ));
});

it('renders an unparseable range whose ends match as one raw date', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'holiday',
            'title' => 'Typo same day',
            'rules' => [
                ['date' => 'not-a-date', 'through' => 'not-a-date', 'service_mode' => 'in_person', 'status' => 'closed'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Typo same day', 'Closed', 'not-a-date')));
});

it('renders an ongoing temporary change with an unparseable start as the raw value', function (): void {
    $html = renderExactHoursNotice([
        [
            'type' => 'temporary_change',
            'title' => 'Typo change',
            'valid_from' => 'not-a-date',
            'valid_through' => null,
            'rules' => [
                ['service_mode' => 'in_person', 'status' => 'open'],
            ],
        ],
    ]);

    expect($html)->toBe(hoursNoticeSection(hoursNoticeItem('Typo change', 'Modified hours', 'not-a-date')));
});
