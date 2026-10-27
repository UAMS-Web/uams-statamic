<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The address and parking sections of a `clinical_locations` profile (UAMS-Web/uams-statamic-archive#2389).
 *
 * The RESOLUTION behind every value here — own, containing facility, or parent location —
 * belongs to `LocationInheritedAddressService` and `FacilityInheritedParkingService` and is
 * covered by their own suites. What is checked here is what the partial does with the values
 * it is handed, and the property the ticket is most specific about: a location missing a
 * component renders no empty section and no stray heading for it.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocations/LocationProfileAddressParkingTest.php
 */

/**
 * Render the profile-details partial with a given location context.
 *
 * @param  array<string, mixed>  $context  Values as the computeds yield them
 * @return string The rendered markup
 */
function locationProfileRender(array $context = []): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/profile_details }}', $context, true);
}

it('renders the address from the resolved computeds', function () {
    $html = locationProfileRender([
        'resolved_street_address' => '4301 W. Markham St.',
        'resolved_city' => 'Little Rock',
        'resolved_state' => 'AR',
        'resolved_postal_code' => '72205',
    ]);

    expect($html)->toContain('location-address')
        ->and($html)->toContain('4301 W. Markham St.')
        ->and($html)->toContain('Little Rock')
        ->and($html)->toContain('72205');
});

it('renders an inherited address identically, because the template cannot tell', function () {
    // The point of reading the computeds: a child location or one inside a facility hands
    // the partial the same shape, so there is no second code path to keep in step.
    $own = locationProfileRender(['resolved_city' => 'Little Rock']);
    $inherited = locationProfileRender(['resolved_city' => 'Little Rock']);

    expect($own)->toBe($inherited)
        ->and($own)->toContain('Little Rock');
});

it('shows where in the building a contained location sits', function () {
    // The facility, the level and the suite are what a patient needs once they are at the
    // right street. Each is a resolved computed, so a child inheriting any of them from its
    // parent hands the partial the same shape (UAMS-Web/uams-statamic-archive#2608).
    $html = locationProfileRender([
        'resolved_facility_name' => 'Jackson T. Stephens Spine Institute',
        'resolved_facility_level' => 'Second Floor',
        'resolved_suite' => 'Suite 2A',
    ]);

    expect($html)->toContain('location-facility-name">Jackson T. Stephens Spine Institute')
        ->and($html)->toContain('location-facility-level">Second Floor')
        ->and($html)->toContain('location-suite">Suite 2A');
});

it('shows a unit with no facility, for a child that overrides only its floor and suite', function () {
    // UAMS-Web/uams-statamic-archive#2608's first criterion: the unit is the child's own, the street is the parent's.
    $html = locationProfileRender([
        'resolved_street_address' => '4301 W. Markham St.',
        'resolved_facility_level' => 'Third Floor',
        'resolved_suite' => 'Suite 3B',
    ]);

    expect($html)->toContain('location-within-facility')
        ->and($html)->toContain('Third Floor')
        ->and($html)->toContain('Suite 3B')
        ->and($html)->toContain('4301 W. Markham St.')
        ->and($html)->not->toContain('location-facility-name');
});

it('ignores the stored unit fields, which a child may carry without owning them', function () {
    // A child with the unit group off still has whatever it stored; only the resolved
    // values may render, or the override toggles would mean nothing on the page.
    $html = locationProfileRender([
        'resolved_city' => 'Little Rock',
        'facility_query' => true,
        'facility_level' => 'STORED-LEVEL',
        'suite' => 'STORED-SUITE',
    ]);

    expect($html)->not->toContain('STORED-LEVEL')
        ->and($html)->not->toContain('STORED-SUITE')
        ->and($html)->not->toContain('location-within-facility');
});

it('escapes the facility, level and suite', function () {
    $html = locationProfileRender([
        'resolved_facility_name' => 'Hall <script>alert(3)</script>',
        'resolved_suite' => 'Suite <img src=x onerror=alert(4)>',
    ]);

    expect($html)->not->toContain('<script>alert(3)')
        ->and($html)->not->toContain('<img src=x');
});

it('renders no address section at all when nothing resolves', function () {
    $html = locationProfileRender();

    expect($html)->not->toContain('location-address')
        ->and($html)->not->toContain('>Address<');
});

it('renders the parking section from the resolved computeds', function () {
    $html = locationProfileRender([
        'resolved_parking_instructions' => '<p>Park in deck 3.</p>',
        'resolved_directions_from_parking' => '<p>Take the elevator to level 2.</p>',
    ]);

    expect($html)->toContain('location-parking')
        ->and($html)->toContain('Park in deck 3.')
        ->and($html)->toContain('Take the elevator to level 2.');
});

it('names the parking facility the location resolves to (#3172)', function () {
    $html = locationProfileRender([
        'resolved_parking_facility' => 'facility-id',
        'resolved_parking_facility_name' => '3805 McCain Park',
    ]);

    expect($html)->toContain('<p class="location-parking-facility">3805 McCain Park</p>')
        ->and($html)->toContain('Parking and Directions');
});

it('renders no facility paragraph when no parking facility resolves (#3172)', function () {
    $html = locationProfileRender([
        'resolved_parking_instructions' => '<p>Park in deck 3.</p>',
    ]);

    expect($html)->toContain('Park in deck 3.')
        ->and($html)->not->toContain('location-parking-facility');
});

it('escapes the parking facility name (#3172)', function () {
    $html = locationProfileRender([
        'resolved_parking_facility_name' => 'Deck <script>alert(5)</script>',
    ]);

    expect($html)->toContain('location-parking-facility')
        ->and($html)->not->toContain('<script>alert(5)');
});

it('renders no parking section when nothing resolves', function () {
    $html = locationProfileRender(['resolved_city' => 'Little Rock']);

    expect($html)->not->toContain('location-parking')
        ->and($html)->not->toContain('Parking and Directions');
});

it('renders a partial address without empty elements for the missing parts', function () {
    // A location with only a city must not emit a street or postal span.
    $html = locationProfileRender(['resolved_city' => 'Little Rock']);

    expect($html)->toContain('location-city')
        ->and($html)->not->toContain('location-street')
        ->and($html)->not->toContain('location-postal-code')
        ->and($html)->not->toContain('location-state"');
});

it('escapes address values carrying markup', function () {
    // Editor-entered, and Antlers is raw by default.
    $html = locationProfileRender([
        'resolved_street_address' => '1 Main <img src=x onerror=alert(1)>',
        'resolved_city' => 'Town <script>alert(2)</script>',
    ]);

    expect($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('<script>alert(2)');
});
