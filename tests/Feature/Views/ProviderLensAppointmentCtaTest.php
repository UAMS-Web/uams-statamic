<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Tests\TestCase;

/**
 * Render coverage for the clinical provider lens's appointment CTA (UAMS-Web/uams-statamic-archive#1676) — the
 * WordPress `blocks/appointment-provider.php` port, whose nine copy branches are the
 * whole substance of the ticket.
 *
 * The facts the branches key on come from the `appointment_context` computed, so
 * these fixtures inject that array directly and the branch table is exercised
 * without any content. `AppointmentContextResolverTest` covers the resolution.
 *
 * @command php artisan test --compact tests/Feature/Views/ProviderLensAppointmentCtaTest.php
 */
uses(TestCase::class);

/**
 * A full `appointment_context` with the given overrides applied.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function appointmentContext(array $overrides = []): array
{
    return $overrides + [
        'eligible' => true,
        'location_valid' => true,
        'location_count' => 1,
        'primary_location_id' => 'sickbay',
        'primary_location_title' => 'Sickbay Clinic',
        'primary_location_url' => '/health/locations/sickbay',
        'direct_url' => '/health/locations/sickbay',
        'direct_is_anchor' => false,
        'show_portal' => false,
        'portal_name' => '',
        'portal_url' => '',
        'referral_required' => false,
        'accepting_patients' => true,
        'main_line_enabled' => true,
        'phone_tel' => '5016868000',
        'phone_display' => '(501) 686-8000',
        'phone_name' => 'the UAMS Health appointment line',
    ];
}

/**
 * A context describing a provider whose primary location carries a patient portal.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function appointmentContextWithPortal(array $overrides = []): array
{
    return appointmentContext($overrides + [
        'show_portal' => true,
        'portal_name' => 'UAMS Health MyChart',
        'portal_url' => 'https://mychart.uamshealth.com/',
    ]);
}

/**
 * Render the CTA partial alone with the given context.
 *
 * @param  array<string, mixed>  $context
 */
function renderAppointmentCta(array $context, string $instance = '1'): string
{
    return (string) Antlers::parse('{{ partial:people/provider/appointment_cta instance="'.$instance.'" }}', [
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'appointment_context' => $context,
    ]);
}

// --- The gate ----------------------------------------------------------------

it('renders nothing for a provider who is not appointment-eligible', function (): void {
    $html = renderAppointmentCta(appointmentContext(['eligible' => false]));

    expect(trim($html))->toBe('');
});

it('renders the CTA for an eligible provider, headed with the short name', function (): void {
    $html = renderAppointmentCta(appointmentContext());

    expect($html)->toContain('profile-appointments')
        ->and($html)->toContain('Make an Appointment With Dr. McCoy')
        ->and($html)->toContain('id="appointment-info-1"');
});

it('gives each of its two renders a distinct id', function (): void {
    expect(renderAppointmentCta(appointmentContext(), '1'))->toContain('id="appointment-info-1"')
        ->and(renderAppointmentCta(appointmentContext(), '2'))->toContain('id="appointment-info-2"')
        ->and(renderAppointmentCta(appointmentContext(), '2'))->not->toContain('id="appointment-info-1"');
});

// --- The assembled sentence --------------------------------------------------
//
// Every other assertion in this file is a mid-sentence `toContain`, and that is
// exactly how a real defect shipped past a green suite: `Statamic\Tags\Partial`
// does not trim a partial's output, so each fragment emitted its surrounding
// newline and every branch rendered "contacting the clinic directly ." — a space
// before the punctuation, on every appointment-eligible profile, twice per page.
// These assert the COLLAPSED sentence, which is what a browser shows.

/**
 * The rendered text with tags stripped and runs of whitespace collapsed — what a
 * browser actually displays inside a `<p>`.
 */
function appointmentCtaSentences(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

it('puts no space before the terminal period in any branch', function (): void {
    $cases = [
        'direct + main' => appointmentContext(),
        'direct only' => appointmentContext(['main_line_enabled' => false]),
        'portal, direct and main' => appointmentContextWithPortal(['referral_required' => true]),
        'portal or direct' => appointmentContextWithPortal(['referral_required' => true, 'main_line_enabled' => false]),
        'portal, new patients' => appointmentContextWithPortal(),
        'main only' => appointmentContext(['location_valid' => false, 'location_count' => 0]),
        'not accepting' => appointmentContext(['accepting_patients' => false]),
        'not accepting with portal' => appointmentContextWithPortal(['accepting_patients' => false]),
        'catch-all' => appointmentContext(['referral_required' => true, 'accepting_patients' => false]),
    ];

    foreach ($cases as $label => $context) {
        $sentences = appointmentCtaSentences(renderAppointmentCta($context));

        expect($sentences)->not->toContain(' .', "branch: {$label}")
            ->and($sentences)->not->toContain(' ,', "branch: {$label}");
    }
});

it('renders each assembled sentence exactly as WordPress writes it', function (): void {
    // The full strings, not fragments — the only shape that pins the punctuation.
    expect(appointmentCtaSentences(renderAppointmentCta(appointmentContext())))
        ->toContain('New and existing patients can make an appointment by contacting the clinic directly or by calling the UAMS Health appointment line at (501) 686-8000.');

    expect(appointmentCtaSentences(renderAppointmentCta(appointmentContext(['main_line_enabled' => false]))))
        ->toContain('New and existing patients can make an appointment by contacting the clinic directly.');

    expect(appointmentCtaSentences(renderAppointmentCta(appointmentContextWithPortal(['referral_required' => true]))))
        ->toContain('Appointments for new patients are by referral only.')
        ->toContain('Existing patients can make an appointment by requesting an appointment online through UAMS Health MyChart, by contacting the clinic directly or by calling the UAMS Health appointment line at (501) 686-8000.');

    expect(appointmentCtaSentences(renderAppointmentCta(appointmentContextWithPortal(['accepting_patients' => false, 'main_line_enabled' => false]))))
        ->toContain('This provider is not currently accepting new patients.')
        ->toContain('Existing patients can make an appointment by requesting an appointment online through UAMS Health MyChart or by contacting the clinic directly.');

    expect(appointmentCtaSentences(renderAppointmentCta(appointmentContext(['location_valid' => false, 'location_count' => 0]))))
        ->toContain('New and existing patients can make an appointment by calling the UAMS Health appointment line at (501) 686-8000.');
});

// --- The nine branches -------------------------------------------------------
//
// Named for the four booleans WordPress switches on: location_valid (L),
// referral_required (R), accepting_patients (A), show_portal (P). `show_portal`
// implies `location_valid`, so no `!L && P` combination exists to test.

it('branch 1 — L R A P: referral lead, then portal, direct and main for existing patients', function (): void {
    $html = renderAppointmentCta(appointmentContextWithPortal(['referral_required' => true]));

    expect($html)->toContain('Appointments for new patients are by referral only.')
        ->and($html)->toContain('Existing patients can make an appointment by')
        ->and($html)->toContain('requesting an appointment online')
        ->and($html)->toContain('contacting the clinic')
        ->and($html)->toContain('by calling the UAMS Health appointment line at');
});

it('branch 2 — no location, R, A: the main line regardless of the site-wide toggle', function (): void {
    // The branch most easily lost in a port. WordPress carries an explicit comment:
    // with no valid location there would otherwise be no way to make an appointment
    // at all, so the main line is offered even when the toggle says not to.
    $html = renderAppointmentCta(appointmentContext([
        'location_valid' => false,
        'location_count' => 0,
        'referral_required' => true,
        'main_line_enabled' => false,
    ]));

    expect($html)->toContain('Appointments for new patients are by referral only.')
        ->and($html)->toContain('by calling the UAMS Health appointment line at')
        ->and($html)->not->toContain('contacting the clinic');
});

it('branch 3 — L R A, no portal: referral lead, then direct and main', function (): void {
    $html = renderAppointmentCta(appointmentContext(['referral_required' => true]));

    expect($html)->toContain('Appointments for new patients are by referral only.')
        ->and($html)->toContain('contacting the clinic')
        ->and($html)->toContain('by calling the UAMS Health appointment line at')
        ->and($html)->not->toContain('requesting an appointment online');
});

it('branch 4 — L A P, no referral: new patients direct, existing patients also the portal', function (): void {
    $html = renderAppointmentCta(appointmentContextWithPortal());

    expect($html)->toContain('New patients can make an appointment')
        ->and($html)->toContain('Existing patients also have the option of')
        ->and($html)->toContain('requesting an appointment online')
        ->and($html)->not->toContain('New and existing patients')
        ->and($html)->not->toContain('by referral only');
});

it('branch 5 — no location, no referral, A: new and existing patients, main line regardless of the toggle', function (): void {
    $html = renderAppointmentCta(appointmentContext([
        'location_valid' => false,
        'location_count' => 0,
        'main_line_enabled' => false,
    ]));

    expect($html)->toContain('New and existing patients can make an appointment')
        ->and($html)->toContain('by calling the UAMS Health appointment line at')
        ->and($html)->not->toContain('contacting the clinic');
});

it('branch 6 — L A, no referral, no portal: new and existing patients, direct and main', function (): void {
    $html = renderAppointmentCta(appointmentContext());

    expect($html)->toContain('New and existing patients can make an appointment')
        ->and($html)->toContain('contacting the clinic')
        ->and($html)->toContain('by calling the UAMS Health appointment line at')
        ->and($html)->not->toContain('requesting an appointment online');
});

it('branch 7 — L P, not accepting: the not-accepting lead, then portal, direct and main', function (): void {
    $html = renderAppointmentCta(appointmentContextWithPortal(['accepting_patients' => false]));

    expect($html)->toContain('This provider is not currently accepting new patients.')
        ->and($html)->toContain('Existing patients can make an appointment by')
        ->and($html)->toContain('requesting an appointment online')
        ->and($html)->toContain('contacting the clinic');
});

it('branch 8 — no location, not accepting: the main line regardless of the toggle', function (): void {
    $html = renderAppointmentCta(appointmentContext([
        'location_valid' => false,
        'location_count' => 0,
        'accepting_patients' => false,
        'main_line_enabled' => false,
    ]));

    expect($html)->toContain('This provider is not currently accepting new patients.')
        ->and($html)->toContain('by calling the UAMS Health appointment line at')
        ->and($html)->not->toContain('contacting the clinic');
});

it('branch 9 — the catch-all drops the referral sentence for a referral-required, not-accepting provider', function (): void {
    // WordPress's final `else` claims one combination in its comment but absorbs
    // every one the eight explicit conditions miss — including this. Reproduced
    // deliberately: #1228 owns any revision to the copy, and a port that "fixed" it
    // here would diverge silently.
    $html = renderAppointmentCta(appointmentContext([
        'referral_required' => true,
        'accepting_patients' => false,
    ]));

    expect($html)->toContain('This provider is not currently accepting new patients.')
        ->and($html)->not->toContain('by referral only')
        ->and($html)->toContain('contacting the clinic');
});

// --- The main-line toggle ----------------------------------------------------

it('drops the main line from every branch that honors the toggle', function (): void {
    $withLocation = renderAppointmentCta(appointmentContext(['main_line_enabled' => false]));
    $withPortal = renderAppointmentCta(appointmentContextWithPortal(['main_line_enabled' => false]));
    $notAccepting = renderAppointmentCta(appointmentContext([
        'main_line_enabled' => false,
        'accepting_patients' => false,
    ]));

    expect($withLocation)->not->toContain('by calling')
        ->and($withLocation)->toContain('contacting the clinic')
        ->and($withPortal)->not->toContain('by calling')
        ->and($withPortal)->toContain('requesting an appointment online')
        ->and($notAccepting)->not->toContain('by calling')
        ->and($notAccepting)->toContain('contacting the clinic');
});

// --- The link targets --------------------------------------------------------

it('points "contacting the clinic directly" at the single location', function (): void {
    $html = renderAppointmentCta(appointmentContext());

    expect($html)->toContain('href="/health/locations/sickbay"')
        ->and($html)->not->toContain('href="#locations"');
});

it('points "contacting the clinic directly" at the locations anchor for a multi-location provider', function (): void {
    $html = renderAppointmentCta(appointmentContext([
        'location_count' => 2,
        'direct_url' => '#locations',
        'direct_is_anchor' => true,
    ]));

    expect($html)->toContain('href="#locations"')
        ->and($html)->toContain('aria-label="Jump to list of locations for this provider"');
});

it('emits the portal text with no anchor when the portal has no URL', function (): void {
    // WordPress builds the whole portal fragment as '' when the term has no URL, so
    // the one thing that must never appear is `<a href="">`.
    $html = renderAppointmentCta(appointmentContextWithPortal(['portal_url' => '']));

    expect($html)->toContain('requesting an appointment online')
        ->and($html)->toContain('UAMS Health MyChart')
        ->and($html)->not->toContain('href=""');
});

it('dials the appointment line the portal resolved, not a hard-coded UAMS number', function (): void {
    $html = renderAppointmentCta(appointmentContextWithPortal([
        'phone_tel' => '5013644000',
        'phone_display' => '(501) 364-4000',
        'phone_name' => "the main Arkansas Children's Hospital appointment line",
    ]));

    expect($html)->toContain('href="tel:5013644000"')
        ->and($html)->toContain('(501) 364-4000')
        ->and($html)->toContain('Arkansas Children&#039;s Hospital appointment line')
        ->and($html)->not->toContain('686-8000');
});

// --- Escaping ----------------------------------------------------------------

it('escapes the editor-controlled portal name, portal URL, and provider name', function (): void {
    $html = (string) Antlers::parse('{{ partial:people/provider/appointment_cta instance="1" }}', [
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy<svg/onload=alert(1)></svg>'],
        'appointment_context' => appointmentContextWithPortal([
            // A DOUBLE QUOTE, not just a `<`. Every one of these values also lands in a
            // `data-typetitle="…"` attribute, where `strip_tags` alone is no defense —
            // a `<`-only payload leaves the attribute breakout wide open while the test
            // stays green, so the payload has to carry the character that breaks out.
            'portal_name' => '" onmouseover=alert(1) x="<img src=x onerror=alert(1)>MyChart',
            'portal_url' => 'javascript:alert(1)',
            'phone_name' => '" onfocus=alert(1) x="the UAMS Health appointment line',
            'primary_location_title' => '" onclick=alert(1) x="Sickbay',
        ]),
    ]);

    expect($html)->toContain('profile-appointments')
        ->and($html)->not->toContain('<svg/onload')
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('href="javascript:alert(1)"')
        // The inert text may appear — it is data inside an attribute value, and
        // harmless there. What must NOT appear is the BREAKOUT form: a literal
        // quote closing the attribute and the handler becoming live markup.
        ->and($html)->not->toContain('" onmouseover=alert(1)')
        ->and($html)->not->toContain('" onfocus=alert(1)')
        ->and($html)->not->toContain('" onclick=alert(1)')
        // The quote was encoded rather than dropped, so the payload is visibly neutered.
        ->and($html)->toContain('&quot;')
        ->and($html)->toContain('MyChart');
});

// --- The de-duplicated statement (UAMS-Web/uams-statamic-archive#1676) -------------------------------------

it('states "not accepting new patients" once on a rendered profile, not twice', function (): void {
    $html = (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'accepting_patients_query' => new LabeledValue(false, 'No'),
        'appointment_context' => appointmentContext(['accepting_patients' => false]),
        // A body section rendered, so the foot-of-page CTA repeats (UAMS-Web/uams-statamic-archive#1695) and the
        // statement is expected twice — which is what makes this a de-duplication
        // assertion rather than an accident of the empty fixture.
        'provider_profile_body_rendered' => true,
    ]);

    // The CTA owns the statement, so the At a Glance row stands down. The CTA renders
    // twice on this profile, which is why this counts against 2 rather than 1.
    expect(substr_count($html, 'not currently accepting new patients'))->toBe(2)
        ->and($html)->not->toContain('provider-accepting-patients');
});

it('makes no accepting-patients claim for a provider who gets no CTA at all', function (): void {
    // WordPress renders its whole "Accepting New Patients" row only when the provider
    // is appointment-eligible, and `accepting_patients_query` is itself blueprint-gated
    // on that eligibility — so an ineligible provider has no answer to report, and
    // claiming "not accepting new patients" for someone who takes no scheduled
    // appointments at all would mislead rather than inform.
    $html = (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'accepting_patients_query' => new LabeledValue(false, 'No'),
        'appointment_context' => appointmentContext(['eligible' => false, 'accepting_patients' => false]),
    ]);

    expect($html)->not->toContain('profile-appointments')
        ->and($html)->not->toContain('Not accepting new patients')
        ->and($html)->not->toContain('provider-accepting-patients');
});

// --- The foot-of-page repeat (UAMS-Web/uams-statamic-archive#1695) -----------------------------------------

/**
 * Render the whole provider profile with the given extra fixture data.
 *
 * @param  array<string, mixed>  $data
 */
function renderProviderProfile(array $data = []): string
{
    return (string) Antlers::parse('{{ partial:people/provider/profile }}', $data + [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'appointment_context' => appointmentContext(),
    ]);
}

it('renders the appointment CTA once for a provider with nothing between the two', function (): void {
    // WordPress gates the second instance on an intervening section having rendered.
    // A provider with an appointment CTA and nothing else — no bio, clinical focus,
    // expertise, conditions, treatments, locations, podcast, resources, credentials,
    // or awards — otherwise got the same block twice, back to back.
    $html = renderProviderProfile(['provider_profile_body_rendered' => false]);

    expect($html)->toContain('id="appointment-info-1"')
        ->and($html)->not->toContain('id="appointment-info-2"')
        ->and(substr_count($html, 'profile-appointments'))->toBe(1);
});

it('repeats the appointment CTA at the foot once a section renders between them', function (): void {
    $html = renderProviderProfile(['provider_profile_body_rendered' => true]);

    expect($html)->toContain('id="appointment-info-1"')
        ->and($html)->toContain('id="appointment-info-2"')
        ->and(substr_count($html, 'profile-appointments'))->toBe(2);
});

it('drives the repeat gate correctly through the augmented Value a real entry produces', function (): void {
    // Production never hands the gate a raw PHP bool — a computed handle arrives as a
    // `Statamic\Fields\Value`. Every other fixture here injects a literal, so this is
    // the one shape the gate actually sees and the one no other test exercises.
    $wrappedFalse = renderProviderProfile([
        'provider_profile_body_rendered' => new Value(false, 'provider_profile_body_rendered'),
    ]);
    $wrappedTrue = renderProviderProfile([
        'provider_profile_body_rendered' => new Value(true, 'provider_profile_body_rendered'),
    ]);

    expect($wrappedFalse)->not->toContain('id="appointment-info-2"')
        ->and($wrappedTrue)->toContain('id="appointment-info-2"');
});

it('renders no appointment CTA at all for an ineligible provider, gate or no gate', function (): void {
    // The repeat gate narrows the second instance; it must never widen the first.
    $html = renderProviderProfile([
        'appointment_context' => appointmentContext(['eligible' => false]),
        'provider_profile_body_rendered' => true,
    ]);

    expect($html)->not->toContain('profile-appointments');
});

// --- The no-location link target (UAMS-Web/uams-statamic-archive#1695) -------------------------------------

it('offers the main line rather than a dead locations anchor for a referral-required, not-accepting provider with no location', function (): void {
    // The one combination that reached the catch-all with no published location. The
    // catch-all emits the `direct` fragment, which links `#locations` for a provider
    // who is not at exactly one location — and with no published location the
    // Locations section does not render, so the anchor is not on the page.
    $html = renderAppointmentCta(appointmentContext([
        'location_valid' => false,
        'location_count' => 0,
        'direct_url' => '#locations',
        'direct_is_anchor' => true,
        'referral_required' => true,
        'accepting_patients' => false,
    ]));

    expect($html)->toContain('This provider is not currently accepting new patients.')
        ->and($html)->not->toContain('href="#locations"')
        ->and($html)->not->toContain('contacting the clinic')
        ->and($html)->toContain('by calling the UAMS Health appointment line at')
        // The copy still drops the referral sentence, exactly as the catch-all does —
        // only the dead link changed. #1228 owns any revision to the copy itself.
        ->and($html)->not->toContain('by referral only');
});

it('offers the main line to that provider even when the site-wide toggle is off', function (): void {
    // The same reasoning the three other no-location branches carry: with no location
    // there would otherwise be no way to make an appointment at all.
    $html = renderAppointmentCta(appointmentContext([
        'location_valid' => false,
        'location_count' => 0,
        'direct_url' => '#locations',
        'direct_is_anchor' => true,
        'referral_required' => true,
        'accepting_patients' => false,
        'main_line_enabled' => false,
    ]));

    expect($html)->toContain('by calling the UAMS Health appointment line at')
        ->and($html)->not->toContain('href="#locations"');
});

it('leaves the catch-all link intact for a not-accepting provider who does have a location', function (): void {
    // The split branch must not swallow the location-carrying cases the catch-all
    // still owns — there the `#locations` anchor has a section to jump to.
    $html = renderAppointmentCta(appointmentContext([
        'location_count' => 2,
        'direct_url' => '#locations',
        'direct_is_anchor' => true,
        'referral_required' => true,
        'accepting_patients' => false,
    ]));

    expect($html)->toContain('href="#locations"')
        ->and($html)->toContain('contacting the clinic')
        ->and($html)->not->toContain('by referral only');
});

it('puts no space before the terminal period in the split no-location branch', function (): void {
    $sentences = appointmentCtaSentences(renderAppointmentCta(appointmentContext([
        'location_valid' => false,
        'location_count' => 0,
        'referral_required' => true,
        'accepting_patients' => false,
    ])));

    expect($sentences)->not->toContain(' .')
        ->and($sentences)->not->toContain(' ,')
        ->toContain('Existing patients can make an appointment by calling the UAMS Health appointment line at (501) 686-8000.');
});

it('still states the affirmative in the facts list, which duplicates nothing', function (): void {
    $html = (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'accepting_patients_query' => new LabeledValue(true, 'Yes'),
        'appointment_context' => appointmentContext(),
    ]);

    expect($html)->toContain('provider-accepting-patients')
        ->and($html)->toContain('Accepting new patients');
});
