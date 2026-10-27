<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Statamic\Facades\Antlers;
use Tests\TestCase;

// Returned to the parallel band on UAMS-Web/uams-statamic-archive#2291. This file was gated to the serial tail on
// "the shared, paratest-non-isolated compiled-view cache" (the UAMS-Web/uams-statamic-archive#1300 / UAMS-Web/uams-statamic-archive#1310 / UAMS-Web/uams-statamic-archive#1447 /
// UAMS-Web/uams-statamic-archive#1452 render-flake class). That directory is per worker under Laravel's `TestViews`
// -- `storage/framework/views/test_<token>`, the one UAMS-Web/uams-statamic-archive#2442 patched for concurrent bands
// -- so the hazard the gate named does not exist. Three parity-matched parallel bands
// with this file in them produced zero failures (macOS, 2026-09-16); runs refute and
// never establish, and tests/README.md carries the audit row.
uses(TestCase::class);

/**
 * Behavioral render coverage for the Group HH `clinical_locations` profile detail
 * sections (Waves 100/101/102), driven hermetically with a fixture scope through
 * {@see clinical_locations/_profile_details.antlers.html}. Each section is
 * optional; the empty-state cases prove a sparsely-filled location renders no
 * stray heading or markup.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocationProfileDetailsTest.php
 */

/**
 * Render the profile-details partial with the given location scope.
 *
 * @param  array<string, mixed>  $location
 */
function renderLocationProfileDetails(array $location): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/profile_details }}', $location);
}

it('renders the What to Expect After Your Appointment section when the field has content', function (): void {
    $html = renderLocationProfileDetails([
        'what_to_expect_after' => '<p>Expect a follow-up call within two business days.</p>',
    ]);

    expect($html)
        ->toContain('What to Expect After Your Appointment')
        ->toContain('Expect a follow-up call within two business days.')
        ->toContain('location-appointment-after');
});

it('renders no What to Expect After section when the field is empty', function (): void {
    $html = renderLocationProfileDetails([
        'what_to_expect_after' => '',
    ]);

    expect($html)
        ->not->toContain('What to Expect After Your Appointment')
        ->and($html)->not->toContain('location-appointment-after');
});

it('renders the interior wayfinding image when one is set', function (): void {
    $html = renderLocationProfileDetails([
        'interior_wayfinding_image' => [['url' => '/img/lobby.jpg', 'alt' => 'Main lobby check-in']],
    ]);

    expect($html)
        ->toContain('Finding Your Way Inside')
        ->toContain('src="/img/lobby.jpg"')
        ->toContain('alt="Main lobby check-in"')
        ->toContain('location-wayfinding-interior');
});

it('renders no interior wayfinding section when the image is absent', function (): void {
    $html = renderLocationProfileDetails([
        'interior_wayfinding_image' => '',
    ]);

    expect($html)
        ->not->toContain('Finding Your Way Inside')
        ->and($html)->not->toContain('location-wayfinding-interior');
});

it('renders the no-show/cancellation/late-arrival policy when it has content', function (): void {
    $html = renderLocationProfileDetails([
        'no_show_cancellation_late_policy' => '<p>Cancel at least 24 hours in advance to avoid a fee.</p>',
    ]);

    expect($html)
        ->toContain('No Show, Cancellation and Late Arrival Policy')
        ->toContain('Cancel at least 24 hours in advance to avoid a fee.')
        ->toContain('location-appointment-policy');
});

it('renders no policy section when the field is empty', function (): void {
    $html = renderLocationProfileDetails([
        'no_show_cancellation_late_policy' => '',
    ]);

    expect($html)
        ->not->toContain('No Show, Cancellation and Late Arrival Policy')
        ->and($html)->not->toContain('location-appointment-policy');
});

it('renders a toll-free appointment number in AP style as a dialable link', function (): void {
    $html = renderLocationProfileDetails([
        'phone_appointment_new' => '+18004321000',
    ]);

    expect($html)
        ->toContain('location-contact')
        ->toContain('href="tel:+18004321000"')
        ->toContain('>800-432-1000</a>');
});

it('renders a standard local appointment number unchanged in AP style', function (): void {
    $html = renderLocationProfileDetails([
        'phone_appointment_new' => '+15016868998',
    ]);

    expect($html)
        ->toContain('href="tel:+15016868998"')
        ->toContain('>501-686-8998</a>');
});

it('renders no contact section when there is no appointment number', function (): void {
    $html = renderLocationProfileDetails([
        'phone_appointment_new' => '',
    ]);

    expect($html)->not->toContain('location-contact');
});

it('renders the eligibility-restriction notice with its message when access is restricted', function (): void {
    $html = renderLocationProfileDetails([
        'restricted_access' => true,
        'restricted_access_message' => '<p>Open only to enrolled Razorback student-athletes.</p>',
    ]);

    expect($html)
        ->toContain('location-restricted-access')
        ->toContain('Restricted Access')
        ->toContain('Open only to enrolled Razorback student-athletes.');
});

it('renders no eligibility notice when access is not restricted (default)', function (): void {
    $html = renderLocationProfileDetails([
        'restricted_access' => false,
        'restricted_access_message' => '<p>Should never appear.</p>',
    ]);

    expect($html)
        ->not->toContain('location-restricted-access')
        ->and($html)->not->toContain('Restricted Access')
        ->and($html)->not->toContain('Should never appear.');
});

it('uses the single shared number for both information and appointments when enabled', function (): void {
    $html = renderLocationProfileDetails([
        'shared_phone_number' => true,
        'shared_phone' => '+15012221234',
        'phone' => '+15019999999',
        'phone_appointment_new' => '+15018888888',
    ]);

    // Both slots show the shared number; the separate numbers do not appear.
    expect(substr_count($html, '501-222-1234'))->toBe(2);
    expect($html)
        ->toContain('Information')
        ->toContain('Appointments')
        ->and($html)->not->toContain('501-999-9999')
        ->and($html)->not->toContain('501-888-8888');
});

it('renders separate information and appointment numbers when not shared (default)', function (): void {
    $html = renderLocationProfileDetails([
        'shared_phone_number' => false,
        'phone' => '+15019999999',
        'phone_appointment_new' => '+15018888888',
    ]);

    expect($html)
        ->toContain('>501-999-9999</a>')
        ->toContain('>501-888-8888</a>');
});

it('labels the fax for referrals when takes_referrals is on', function (): void {
    $html = renderLocationProfileDetails([
        'fax' => '+15017778888',
        'takes_referrals' => true,
    ]);

    expect($html)
        ->toContain('Referrals Fax')
        ->toContain('501-777-8888')
        ->and($html)->not->toContain('Records Fax');
});

it('labels the fax for medical records at the default (takes_referrals off)', function (): void {
    $html = renderLocationProfileDetails([
        'fax' => '+15017778888',
        'takes_referrals' => false,
    ]);

    expect($html)
        ->toContain('Records Fax')
        ->toContain('501-777-8888')
        ->and($html)->not->toContain('Referrals Fax');
});

/**
 * Regular opening hours (UAMS-Web/uams-statamic-archive#2390). The hours section renders the stored
 * `regular_hours` rules for a reader, honors the variable-hours pair, and
 * renders alongside the UAMS-Web/uams-statamic-archive#1328 modified-hours notice rather than instead of it.
 *
 * @param  list<string>  $days
 * @return array<string, mixed>
 */
function locationOpenRule(array $days, string $open = '08:00', string $close = '17:00'): array
{
    return [
        'type' => 'custom',
        'days' => $days,
        'service_mode' => 'in_person',
        'status' => 'open',
        'time_span' => [['open' => $open, 'close' => $close]],
    ];
}

it('renders the hours section from regular_hours on the profile', function (): void {
    $html = renderLocationProfileDetails([
        'hours_variable_query' => false,
        'regular_hours' => [locationOpenRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
    ]);

    expect($html)
        ->toContain('location-hours')
        ->toContain('Hours')
        ->toContain('Monday &ndash; Friday');
});

/**
 * Criterion: a location with a telemedicine-mode rule renders those hours
 * somewhere a reader can find them (UAMS-Web/uams-statamic-archive#2405).
 *
 * Asserted on the assembled profile rather than on the tag alone, because
 * "somewhere a reader can find them" is a claim about the page. The tag could
 * render perfectly into a branch the template never takes -- which is close to
 * what the defect was.
 */
it('renders the telemedicine hours on the profile, labeled and beside the in-person hours', function (): void {
    $html = renderLocationProfileDetails([
        'hours_variable_query' => false,
        'regular_hours' => [
            locationOpenRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            [
                'type' => 'custom',
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'service_mode' => 'telemedicine',
                'status' => 'open',
                'time_span' => [['open' => '20:00', 'close' => '22:00']],
            ],
        ],
    ]);

    expect($html)
        ->toContain('location-hours-group-in-person')
        ->toContain('location-hours-group-telemedicine')
        ->toContain('>In-Person<')
        ->toContain('>Telemedicine<');
});

it('renders a telemedicine-only location on the profile rather than an empty hours area', function (): void {
    // The blueprint permits rules in one mode only, and this is the shape that
    // rendered nothing at all before UAMS-Web/uams-statamic-archive#2405
    $html = renderLocationProfileDetails([
        'hours_variable_query' => false,
        'regular_hours' => [
            [
                'type' => 'custom',
                'days' => ['monday'],
                'service_mode' => 'telemedicine',
                'status' => 'open',
                'time_span' => [['open' => '20:00', 'close' => '22:00']],
            ],
        ],
    ]);

    expect($html)
        ->toContain('location-hours-group-telemedicine')
        ->toContain('Monday');
});

it('renders the variable-hours description instead of a grid it cannot fill', function (): void {
    $html = renderLocationProfileDetails([
        'hours_variable_query' => true,
        'variable_hours_description' => '<p>Clinics run only during scheduled outreach weeks.</p>',
        'regular_hours' => [],
    ]);

    expect($html)
        ->toContain('Clinics run only during scheduled outreach weeks.')
        ->toContain('location-hours-variable');

    // The rules grid is not rendered for a variable-hours location
    expect($html)->not->toContain('location-hours-list');
});

it('renders no hours section and no heading when the location stores no hours', function (): void {
    $html = renderLocationProfileDetails([
        'hours_variable_query' => false,
        'regular_hours' => [],
    ]);

    expect($html)
        ->not->toContain('location-hours')
        ->and($html)->not->toContain('Hours');
});

it('renders an active modified-hours notice alongside the regular hours, not instead of them', function (): void {
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));

    $html = renderLocationProfileDetails([
        'hours_variable_query' => false,
        'regular_hours' => [locationOpenRule(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
        'special_hours' => [
            [
                'type' => 'holiday',
                'title' => 'Thanksgiving',
                'rules' => [
                    ['date' => '2026-11-26', 'through' => null, 'service_mode' => 'in_person', 'status' => 'closed'],
                ],
            ],
        ],
    ]);

    // Both surfaces render: the regular week and the exception against it
    expect($html)
        ->toContain('Monday &ndash; Friday')
        ->toContain('location-hours-list')
        ->toContain('Modified Hours')
        ->toContain('Thanksgiving');
});
