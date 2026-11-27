<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the `clinical_locations` profile's status sections
 * (UAMS-Web/uams-statamic#1576): the closure and telemedicine banner
 * ({@see clinical_locations/_status_banner.antlers.html}), and the Alert, Closing
 * Information, After Hours and Telemedicine sections of
 * {@see clinical_locations/_profile_details.antlers.html}. Each is driven
 * hermetically through a fixture scope; the Find-a-Doc Settings global is passed
 * in that scope as `uams_fad_settings`, the handle the cascade gives it on a page.
 *
 * "Today" is pinned to 2026-07-11 so the dated closures resolve the same way on
 * every run.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocationStatusSectionsTest.php
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));
});

/**
 * Render the status banner partial with the given location scope.
 *
 * @param  array<string, mixed>  $location
 */
function renderLocationStatusBanner(array $location): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/status_banner }}', $location);
}

/**
 * Render the profile-details partial with the given location scope.
 *
 * @param  array<string, mixed>  $location
 */
function renderLocationStatusDetails(array $location): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/profile_details }}', $location);
}

/**
 * Collapse runs of whitespace so a sentence split across template lines can be matched.
 */
function squashLocationStatusWhitespace(string $html): string
{
    return (string) preg_replace('/\s+/', ' ', $html);
}

it('renders every status block for a location that sets them all, and none for a location that sets none', function (): void {
    $full = squashLocationStatusWhitespace(renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => true,
        'reopening_date' => Carbon::parse('2026-09-01'),
        'closing_info' => '<p>Moving to a new building.</p>',
        'telemed_query' => true,
        'closing_telemed_available_query' => true,
    ]).renderLocationStatusDetails([
        'alert_modification' => 'override',
        'alert_heading' => 'Parking Garage Closed',
        'alert_body' => '<p>Use the north lot.</p>',
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => true,
        'reopening_date' => Carbon::parse('2026-09-01'),
        'closing_info' => '<p>Moving to a new building.</p>',
        'closing_telemed_available_query' => true,
        'after_hours_description' => '<p>Call the nurse line.</p>',
        'telemed_query' => true,
        'telemed_patients' => 'existing',
        'phone_appointment_new' => '+15016868998',
    ]));

    expect($full)
        ->toContain('location-closure-banner')
        ->toContain('Parking Garage Closed')
        ->toContain('<h2>Closing Information</h2>')
        ->toContain('<h2>After Hours</h2>')
        ->toContain('<h2>Telemedicine</h2>');

    $empty = renderLocationStatusBanner([]).renderLocationStatusDetails([]);

    expect($empty)
        ->not->toContain('location-closure-banner')
        ->and($empty)->not->toContain('location-alert')
        ->and($empty)->not->toContain('Closing Information')
        ->and($empty)->not->toContain('After Hours')
        ->and($empty)->not->toContain('Telemedicine')
        ->and($empty)->not->toContain('<h2');
});

it('announces a future temporary closure with its reopening date', function (): void {
    $html = squashLocationStatusWhitespace(renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => true,
        'reopening_date' => Carbon::parse('2026-09-01'),
    ]));

    expect($html)
        ->toContain('location-closure-banner-temporary')
        ->toContain('This location will be closing temporarily beginning on August 1, 2026.')
        ->toContain('It is scheduled to reopen on September 1, 2026.')
        ->not->toContain('permanently');
});

it('says a temporary closure with no known reopening date lasts until further notice', function (): void {
    $html = squashLocationStatusWhitespace(renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-07-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => false,
    ]));

    expect($html)
        ->toContain('This location is temporarily closed.')
        ->toContain('It will remain closed until further notice.');
});

it('shows a permanent closure differently from a dated temporary one', function (): void {
    $future = squashLocationStatusWhitespace(renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => true,
    ]));
    $past = squashLocationStatusWhitespace(renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-07-01'),
        'closing_permanent_query' => true,
    ]));

    expect($future)
        ->toContain('location-closure-banner-permanent')
        ->toContain('This location will be closing permanently on August 1, 2026.')
        ->not->toContain('reopen')
        ->not->toContain('further notice')
        ->and($past)->toContain('This location is permanently closed.');
});

it('starts a closure on its own calendar date in Central time, not at midnight UTC', function (): void {
    $location = [
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => true,
    ];

    // 2026-08-01 03:00 UTC is still July 31 in Chicago.
    $this->travelTo(Carbon::parse('2026-08-01 03:00:00', 'UTC'));
    $eve = squashLocationStatusWhitespace(renderLocationStatusBanner($location));

    // 2026-08-01 06:00 UTC is August 1 in Chicago.
    $this->travelTo(Carbon::parse('2026-08-01 06:00:00', 'UTC'));
    $day = squashLocationStatusWhitespace(renderLocationStatusBanner($location));

    expect($eve)->toContain('This location will be closing permanently on August 1, 2026.')
        ->and($day)->toContain('This location is permanently closed.');
});

it('withdraws the banner once a temporary closure has reopened', function (): void {
    $html = renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-06-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => true,
        'reopening_date' => Carbon::parse('2026-07-01'),
    ]);

    expect($html)->not->toContain('location-closure-banner');
});

it('links the banner to the closing information and to telemedicine when they apply', function (): void {
    $with = renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => false,
        'closing_info' => '<p>Renovation.</p>',
        'telemed_query' => true,
        'closing_telemed_available_query' => true,
    ]);
    $without = renderLocationStatusBanner([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => false,
    ]);

    expect($with)
        ->toContain('href="#closing-info"')
        ->toContain('Telemedicine will still be available.')
        ->toContain('href="#telemedicine-info"')
        ->and($without)->not->toContain('#closing-info')
        ->and($without)->not->toContain('Telemedicine will still be available.');
});

it('renders the system alert from the Find-a-Doc Settings global with its color', function (): void {
    $html = renderLocationStatusDetails([
        'alert_modification' => 'nothing',
        'uams_fad_settings' => [
            'location_alert_heading' => 'Masks Required',
            'location_alert_body' => '<p>Wear a mask in every clinic.</p>',
            'location_alert_color' => 'alert-danger',
        ],
    ]);

    expect($html)
        ->toContain('location-alert-danger')
        ->toContain('<h2 id="location-alert-heading">Masks Required</h2>')
        ->toContain('Wear a mask in every clinic.');
});

it('hides an empty alert heading and defaults the color to warning', function (): void {
    $html = renderLocationStatusDetails([
        'uams_fad_settings' => ['location_alert_body' => '<p>Flu shots available.</p>'],
    ]);

    expect($html)
        ->toContain('location-alert-warning')
        ->toContain('<h2 id="location-alert-heading" class="sr-only">Alert</h2>')
        ->toContain('Flu shots available.');
});

it('overrides the system alert with the location heading, body and color', function (): void {
    $html = renderLocationStatusDetails([
        'alert_modification' => 'override',
        'alert_heading' => 'Elevator Out',
        'alert_body' => '<p>Use the east stairs.</p>',
        'alert_color' => 'alert-info',
        'uams_fad_settings' => [
            'location_alert_heading' => 'Masks Required',
            'location_alert_body' => '<p>Wear a mask in every clinic.</p>',
            'location_alert_color' => 'alert-danger',
        ],
    ]);

    expect($html)
        ->toContain('location-alert-info')
        ->toContain('Elevator Out')
        ->toContain('Use the east stairs.')
        ->not->toContain('Masks Required')
        ->not->toContain('Wear a mask in every clinic.');
});

it('inserts the location body before or after the system alert body', function (string $modification, string $first, string $second): void {
    $html = renderLocationStatusDetails([
        'alert_modification' => $modification,
        'alert_body' => '<p>LOCAL</p>',
        'uams_fad_settings' => ['location_alert_body' => '<p>SYSTEM</p>'],
    ]);

    // Both bodies render, and in the stated order.
    $firstAt = strpos($html, $first);
    $secondAt = strpos($html, $second);

    expect($firstAt)->toBeInt()
        ->and($secondAt)->toBeInt();

    if (\is_int($firstAt) && \is_int($secondAt)) {
        expect($secondAt)->toBeGreaterThan($firstAt);
    }
})->with([
    'prepend' => ['prepend', 'LOCAL', 'SYSTEM'],
    'append' => ['append', 'SYSTEM', 'LOCAL'],
]);

it('renders no alert when the location suppresses it', function (): void {
    $html = renderLocationStatusDetails([
        'alert_modification' => 'suppress',
        'uams_fad_settings' => ['location_alert_heading' => 'Masks Required', 'location_alert_body' => '<p>Wear a mask.</p>'],
    ]);

    expect($html)->not->toContain('location-alert');
});

it('renders no alert while the location is closed', function (): void {
    $html = renderLocationStatusDetails([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-07-01'),
        'closing_permanent_query' => true,
        'uams_fad_settings' => ['location_alert_heading' => 'Masks Required', 'location_alert_body' => '<p>Wear a mask.</p>'],
    ]);

    expect($html)->not->toContain('location-alert');
});

it('keeps the alert while a closure is only announced', function (): void {
    $html = renderLocationStatusDetails([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => true,
        'uams_fad_settings' => ['location_alert_body' => '<p>Wear a mask.</p>'],
    ]);

    expect($html)->toContain('location-alert');
});

it('renders the closing information only while the closure is in effect', function (): void {
    $active = renderLocationStatusDetails([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-08-01'),
        'closing_permanent_query' => true,
        'closing_info' => '<p>Services move to the main campus.</p>',
    ]);
    $reopened = renderLocationStatusDetails([
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-06-01'),
        'closing_permanent_query' => false,
        'reopening_date_query' => true,
        'reopening_date' => Carbon::parse('2026-07-01'),
        'closing_info' => '<p>Services move to the main campus.</p>',
    ]);

    expect($active)
        ->toContain('id="closing-info"')
        ->toContain('Services move to the main campus.')
        ->and($reopened)->not->toContain('Closing Information');
});

it('renders the location after-hours text, falling back to the system copy', function (): void {
    $own = renderLocationStatusDetails([
        'after_hours_description' => '<p>Call the nurse line.</p>',
        'uams_fad_settings' => ['location_after_hours_description' => '<p>Call 911.</p>'],
    ]);
    $system = renderLocationStatusDetails([
        'uams_fad_settings' => ['location_after_hours_description' => '<p>Call 911.</p>'],
    ]);

    expect($own)
        ->toContain('<h2>After Hours</h2>')
        ->toContain('Call the nurse line.')
        ->not->toContain('Call 911.')
        ->and($system)->toContain('Call 911.');
});

it('renders the telemedicine section with patients and the number to call', function (): void {
    $html = squashLocationStatusWhitespace(renderLocationStatusDetails([
        'telemed_query' => true,
        'telemed_patients' => 'new',
        'phone_appointment_new' => '+15016868998',
        'uams_fad_settings' => ['location_telemed_description' => '<p>Visit from home.</p>'],
    ]));

    expect($html)
        ->toContain('id="telemedicine-info"')
        ->toContain('Visit from home.')
        ->toContain('This service is available to new patients only.')
        ->toContain('Patients should call <a href="tel:+15016868998">501-686-8998</a> to schedule a telemedicine appointment.');
});

it('renders no telemedicine section when the location offers none', function (): void {
    $html = renderLocationStatusDetails([
        'telemed_query' => false,
        'telemed_patients' => 'all',
    ]);

    expect($html)->not->toContain('Telemedicine');
});

it('says telemedicine is unavailable during a closure that does not keep it', function (): void {
    $html = squashLocationStatusWhitespace(renderLocationStatusDetails([
        'telemed_query' => true,
        'telemed_patients' => 'all',
        'closing_query' => true,
        'closing_date' => Carbon::parse('2026-07-01'),
        'closing_permanent_query' => true,
    ]));

    expect($html)
        ->toContain('Telemedicine is not available while this location is permanently closed.')
        ->not->toContain('This service is available');
});

it('reads radio fields as the labeled values a page augments them to', function (): void {
    $html = squashLocationStatusWhitespace(renderLocationStatusDetails([
        'alert_modification' => new LabeledValue('override', 'Override alert'),
        'alert_heading' => 'Elevator Out',
        'alert_color' => new LabeledValue('alert-info', 'Info'),
        'telemed_query' => true,
        'telemed_patients' => new LabeledValue('existing', 'Existing Patients Only'),
        'uams_fad_settings' => [
            'location_alert_body' => '<p>Wear a mask.</p>',
            'location_alert_color' => new LabeledValue('alert-danger', 'Danger'),
        ],
    ]));

    expect($html)
        ->toContain('location-alert-info')
        ->toContain('Elevator Out')
        ->toContain('This service is available to existing patients only.');
});
