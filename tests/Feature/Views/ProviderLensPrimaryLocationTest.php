<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

/**
 * Render coverage for the clinical provider lens's Primary Appointment Location
 * block (UAMS-Web/uams-statamic-archive#1676) — the location detail WordPress puts immediately under the `<h1>`
 * and we rendered no equivalent of.
 *
 * @command php artisan test --compact tests/Feature/Views/ProviderLensPrimaryLocationTest.php
 */
uses(TestCase::class);

/**
 * A `clinical_locations` relation row shaped like the augmented entry the block
 * walks.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function primaryLocationRow(array $overrides = []): array
{
    return $overrides + [
        'id' => 'sickbay',
        'title' => 'Sickbay',
        'url' => '/health/locations/sickbay',
        'resolved_street_address' => '4301 W Markham St',
        'resolved_address_line_2' => 'Building C',
        'resolved_city' => 'Little Rock',
        'resolved_state' => 'AR',
        'resolved_postal_code' => '72205',
        'phone' => '501-686-7000',
    ];
}

/**
 * Render the primary-location block with the given locations and context.
 *
 * @param  list<array<string, mixed>>  $locations
 * @param  array<string, mixed>  $context
 */
function renderPrimaryLocation(array $locations, array $context = []): string
{
    return (string) Antlers::parse('{{ partial:people/provider/primary_location }}', [
        'clinical_locations' => $locations,
        'appointment_context' => $context + ['eligible' => true, 'location_count' => \count($locations)],
    ]);
}

it('renders the primary location name, address, and contact number', function (): void {
    $html = renderPrimaryLocation([primaryLocationRow()]);

    expect($html)->toContain('profile-primary-location')
        ->and($html)->toContain('Sickbay')
        ->and($html)->toContain('4301 W Markham St')
        ->and($html)->toContain('primary-location-address-2">Building C')
        ->and($html)->toContain('Little Rock')
        ->and($html)->toContain('AR')
        ->and($html)->toContain('72205')
        ->and($html)->toContain('href="tel:501-686-7000"')
        ->and($html)->toContain('View Location');
});

// The single word that tells a patient whether this address is somewhere they can
// book — and the detail most easily lost in a port.
it('heads the block "Primary Appointment Location" only for an appointment-eligible provider', function (): void {
    $eligible = renderPrimaryLocation([primaryLocationRow()], ['eligible' => true]);
    $ineligible = renderPrimaryLocation([primaryLocationRow()], ['eligible' => false]);

    expect($eligible)->toContain('Primary Appointment Location')
        ->and($ineligible)->toContain('Primary Location')
        ->and($ineligible)->not->toContain('Primary Appointment Location');
});

it('renders no block at all for a provider with no published location', function (): void {
    expect(renderPrimaryLocation([]))->not->toContain('profile-primary-location');
});

it('shows only the first location, whatever else the provider has', function (): void {
    $html = renderPrimaryLocation([
        primaryLocationRow(),
        primaryLocationRow(['id' => 'annex', 'title' => 'Neurology Annex', 'url' => '/health/locations/annex']),
    ]);

    expect($html)->toContain('Sickbay')
        ->and($html)->not->toContain('Neurology Annex');
});

it('offers "View All Locations" only when the provider has more than one', function (): void {
    $single = renderPrimaryLocation([primaryLocationRow()]);

    $several = renderPrimaryLocation(
        [primaryLocationRow(), primaryLocationRow(['id' => 'annex'])],
        ['location_count' => 2]
    );

    expect($single)->not->toContain('View All Locations')
        ->and($single)->toContain('View Location')
        ->and($several)->toContain('View All Locations')
        ->and($several)->toContain('href="#locations"');
});

it('renders the "Part of" line for a child location, and omits it for a top-level one', function (): void {
    $child = renderPrimaryLocation([primaryLocationRow([
        'parent_location_name' => 'Jackson T. Stephens Spine Institute',
        'parent_location_url' => '/health/locations/spine-institute',
    ])]);

    expect($child)->toContain('Part of')
        ->and($child)->toContain('Jackson T. Stephens Spine Institute')
        ->and($child)->toContain('href="/health/locations/spine-institute"')
        ->and(renderPrimaryLocation([primaryLocationRow()]))->not->toContain('Part of');
});

// The location's OWN number, unlike the system appointment line in the CTA — with
// the card's precedence (UAMS-Web/uams-statamic-archive#1185 / UAMS-Web/uams-statamic-archive#1167): the shared number wins, then the dedicated
// appointment number, then the general-information line.
it('resolves the location contact number by the shared-then-appointment-then-general precedence', function (): void {
    $shared = renderPrimaryLocation([primaryLocationRow([
        'shared_phone_number' => true,
        'shared_phone' => '501-364-1100',
        'phone_appointment_new' => '501-686-8000',
    ])]);

    $appointment = renderPrimaryLocation([primaryLocationRow([
        'phone_appointment_new' => '501-686-8000',
    ])]);

    expect($shared)->toContain('tel:501-364-1100')
        ->and($shared)->not->toContain('tel:501-686-8000')
        ->and($appointment)->toContain('tel:501-686-8000')
        ->and($appointment)->not->toContain('tel:501-686-7000');
});

it('prefers the clean generic name over the disambiguated entry title', function (): void {
    $html = renderPrimaryLocation([primaryLocationRow([
        'title' => 'Sickbay (Deck 7, Enterprise)',
        'generic_name' => 'Sickbay',
    ])]);

    expect($html)->toContain('Sickbay<')
        ->and($html)->not->toContain('Deck 7');
});

it('escapes the editor-controlled location name, parent name, and parent href', function (): void {
    $html = renderPrimaryLocation([primaryLocationRow([
        'title' => '<img src=x onerror=alert(1)>Sickbay',
        'parent_location_name' => '<svg/onload=alert(1)></svg>Spine Institute',
        'parent_location_url' => 'javascript:alert(1)',
    ])]);

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('<svg/onload')
        ->and($html)->not->toContain('href="javascript:alert(1)"')
        ->and($html)->toContain('Sickbay')
        ->and($html)->toContain('Spine Institute');
});
