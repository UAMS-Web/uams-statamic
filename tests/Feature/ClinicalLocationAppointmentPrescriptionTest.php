<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the Appointment Information and Prescription Information
 * sections of the `clinical_locations` profile (UAMS-Web/uams-statamic#1577), in
 * {@see clinical_locations/_profile_details.antlers.html}. Each case is driven
 * hermetically through a fixture scope; the Find-a-Doc Settings global is passed
 * in that scope as `uams_fad_settings`, the handle the cascade gives it on a page.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocationAppointmentPrescriptionTest.php
 */

/**
 * Render the profile-details partial with the given location scope.
 *
 * @param  array<array-key, mixed>  $location  The location's fields, keyed by handle.
 */
function renderLocationAppointmentDetails(array $location): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/profile_details }}', $location);
}

/**
 * The scope of a location that takes appointments and lets patients schedule them.
 *
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function schedulingLocation(array $fields = []): array
{
    return ['appointments_required_query' => true, 'appointments_schedule_query' => true, ...$fields];
}

/**
 * A radio field's augmented value.
 */
function prescriptionTypeValue(string $key): LabeledValue
{
    return new LabeledValue($key, $key);
}

/**
 * The headings and part bodies of the appointment section, in document order.
 *
 * @return list<string>
 */
function appointmentSectionOutline(string $html): array
{
    // Read only the appointment section, then its headings and part classes in order.
    if (preg_match('~<section class="location-appointment-info".*?</section>~s', $html, $section) !== 1) {
        return [];
    }
    preg_match_all('~<(h[23])>([^<]*)</h[23]>|class="location-appointment-(setup|bring|expect)"~', $section[0], $matches, PREG_SET_ORDER);

    return array_map(
        static fn (array $match): string => ($match[3] ?? '') !== '' ? 'body:'.$match[3] : ($match[1] ?? '').':'.($match[2] ?? ''),
        $matches
    );
}

it('renders all three appointment parts in the Find-a-Doc order under one heading', function (): void {
    $html = renderLocationAppointmentDetails(schedulingLocation([
        'setting_up_appointment_info' => '<p>Call to set up a visit.</p>',
        'bring_to_appointment_info' => '<p>Bring your insurance card.</p>',
        'expect_at_appointment_info' => '<p>Arrive 15 minutes early.</p>',
    ]));

    expect(appointmentSectionOutline($html))->toBe([
        'h2:Appointment Information',
        'body:setup',
        'h3:What to Bring to Your Appointment',
        'body:bring',
        'h3:What to Expect at Your Appointment',
        'body:expect',
    ])
        ->and($html)->toContain('id="appointment-info"')
        ->toContain('Call to set up a visit.')
        ->toContain('Bring your insurance card.')
        ->toContain('Arrive 15 minutes early.');
});

it('groups what to bring and what to expect under Appointment Information without the setting-up text', function (): void {
    $html = renderLocationAppointmentDetails([
        'bring_to_appointment_info' => '<p>Bring your insurance card.</p>',
        'expect_at_appointment_info' => '<p>Arrive 15 minutes early.</p>',
    ]);

    expect(appointmentSectionOutline($html))->toBe([
        'h2:Appointment Information',
        'h3:What to Bring to Your Appointment',
        'body:bring',
        'h3:What to Expect at Your Appointment',
        'body:expect',
    ]);
});

it('renders a single part under its own heading', function (array $location, array $outline, string $text): void {
    $html = renderLocationAppointmentDetails($location);

    expect(appointmentSectionOutline($html))->toBe($outline)
        ->and($html)->toContain($text);
})->with([
    'setting up only' => [
        schedulingLocation(['setting_up_appointment_info' => '<p>Call to set up a visit.</p>']),
        ['h2:Appointment Information', 'body:setup'],
        'Call to set up a visit.',
    ],
    'what to bring only' => [
        ['bring_to_appointment_info' => '<p>Bring your insurance card.</p>'],
        ['h2:What to Bring to Your Appointment', 'body:bring'],
        'Bring your insurance card.',
    ],
    'what to expect only' => [
        ['expect_at_appointment_info' => '<p>Arrive 15 minutes early.</p>'],
        ['h2:What to Expect at Your Appointment', 'body:expect'],
        'Arrive 15 minutes early.',
    ],
]);

it('hides the setting-up text while the location does not let patients schedule', function (array $toggles): void {
    $html = renderLocationAppointmentDetails([
        ...$toggles,
        'setting_up_appointment_info' => '<p>Call to set up a visit.</p>',
    ]);

    expect($html)->not->toContain('location-appointment-info')
        ->and($html)->not->toContain('Call to set up a visit.');
})->with([
    'scheduling off' => [['appointments_required_query' => true, 'appointments_schedule_query' => false]],
    'appointments not required' => [['appointments_required_query' => false, 'appointments_schedule_query' => true]],
]);

it('falls back to the Find-a-Doc Settings copy for what to bring, and the location copy wins', function (): void {
    $settings = ['uams_fad_settings' => ['location_bring_to_appointment_info' => '<p>Bring a photo ID.</p>']];

    $fallback = renderLocationAppointmentDetails($settings);
    $own = renderLocationAppointmentDetails([...$settings, 'bring_to_appointment_info' => '<p>Bring your insurance card.</p>']);

    expect(appointmentSectionOutline($fallback))->toBe(['h2:What to Bring to Your Appointment', 'body:bring'])
        ->and($fallback)->toContain('Bring a photo ID.')
        ->and($own)->toContain('Bring your insurance card.')
        ->and($own)->not->toContain('Bring a photo ID.');
});

it('renders the prescription text the type selects', function (?string $type, string $text): void {
    $location = [
        'prescription_query' => true,
        'prescription' => '<p>Refills are handled by the front desk.</p>',
        'uams_fad_settings' => [
            'location_prescription_clinic' => '<p>Call the clinic for refills.</p>',
            'location_prescription_pharmacy' => '<p>Call your pharmacy for refills.</p>',
        ],
    ];
    if ($type !== null) {
        $location['prescription_type'] = prescriptionTypeValue($type);
    }

    $html = renderLocationAppointmentDetails($location);

    expect($html)->toContain('<section class="location-prescription-info" id="prescription-info">')
        ->toContain('<h2>Prescription Information</h2>')
        ->toContain($text);
    foreach (['Refills are handled by the front desk.', 'Call the clinic for refills.', 'Call your pharmacy for refills.'] as $other) {
        if ($other !== $text) {
            expect($html)->not->toContain($other);
        }
    }
})->with([
    'custom' => ['custom', 'Refills are handled by the front desk.'],
    'clinic' => ['clinic', 'Call the clinic for refills.'],
    'pharmacy' => ['pharm', 'Call your pharmacy for refills.'],
    'unset, the field default' => [null, 'Call your pharmacy for refills.'],
]);

it('omits the prescription section when it is off or has no text', function (array $location): void {
    $html = renderLocationAppointmentDetails($location);

    expect($html)->not->toContain('Prescription Information')
        ->and($html)->not->toContain('prescription-info');
})->with([
    'off, with text' => [[
        'prescription_query' => false,
        'prescription_type' => prescriptionTypeValue('custom'),
        'prescription' => '<p>Refills are handled by the front desk.</p>',
    ]],
    'on, custom with no text' => [[
        'prescription_query' => true,
        'prescription_type' => prescriptionTypeValue('custom'),
    ]],
    'on, preset with no settings copy' => [[
        'prescription_query' => true,
        'prescription_type' => prescriptionTypeValue('clinic'),
    ]],
]);

it('renders neither section for a location with none of the fields', function (): void {
    $html = renderLocationAppointmentDetails([]);

    expect($html)->not->toContain('location-appointment-info')
        ->and($html)->not->toContain('location-prescription-info');
});
