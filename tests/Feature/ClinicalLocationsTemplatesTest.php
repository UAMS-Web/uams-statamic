<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, RequiresContentSubmodule::class);

/**
 * Feature coverage for the clinical_locations index (UAMS-Web/uams-statamic-archive#574) and the foundational
 * show (UAMS-Web/uams-statamic-archive#575) templates. The full profile is Group HH's charter; these
 * templates are the base it extends.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocationsTemplatesTest.php
 */

/**
 * Render the location card partial with the given entry scope.
 *
 * @param  array<string, mixed>  $location
 */
function renderLocationCard(array $location): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/card }}', $location);
}

it('renders a location card with image, name, description, and a profile link', function (): void {
    $html = renderLocationCard([
        'title' => 'UAMS Family Medical Center',
        'url' => '/health/locations/family-medical-center',
        'featured_image' => [['url' => '/img/fmc.jpg', 'alt' => 'Building']],
        'short_desc' => 'Primary care in Little Rock.',
    ]);

    expect($html)
        ->toContain('UAMS Family Medical Center')
        ->toContain('Primary care in Little Rock.')
        ->toContain('<img src="/img/fmc.jpg"')
        ->toContain('href="/health/locations/family-medical-center"')
        ->toContain('schema.org/MedicalClinic');
});

it('shows the generic name on the card when set, in place of the disambiguated title', function (): void {
    $html = renderLocationCard([
        'title' => 'UAMS Family Medical Center (Cedar & Capitol — 3rd Floor)',
        'generic_name' => 'Family Medical Center',
        'url' => '/health/locations/family-medical-center',
        'short_desc' => 'Primary care in Little Rock.',
    ]);

    expect($html)
        ->toContain('>Family Medical Center<')
        ->toContain('View location: Family Medical Center');
    expect($html)->not->toContain('Cedar & Capitol');
});

it('falls back to the entry title on the card when the generic name is blank', function (): void {
    $html = renderLocationCard([
        'title' => 'UAMS Family Medical Center',
        'generic_name' => '',
        'url' => '/health/locations/family-medical-center',
        'short_desc' => 'Primary care in Little Rock.',
    ]);

    expect($html)
        ->toContain('>UAMS Family Medical Center<')
        ->toContain('View location: UAMS Family Medical Center');
});

it('renders the 3:4 no-image-available fallback when the card has no featured image', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'short_desc' => 'Deep-space care.',
    ]);

    // The grey no-image-available_3-4 SVG placeholder (viewBox + fill) stands in
    // for the missing image; no <img> is emitted (UAMS-Web/uams-statamic-archive#63).
    expect($html)->toContain('viewBox="0 0 245 327"')
        ->and($html)->toContain('#dcdadb')
        ->and($html)->not->toContain('<img');
});

it('renders the address block from the resolved address computeds', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'resolved_street_address' => '1701 Starfleet Blvd',
        'resolved_city' => 'Riverside',
        'resolved_state' => 'IA',
        'resolved_postal_code' => '52327',
    ]);

    expect($html)->toContain('1701 Starfleet Blvd')
        ->and($html)->toContain('Riverside')
        ->and($html)->toContain('IA')
        ->and($html)->toContain('52327')
        ->and($html)->toContain('schema.org/PostalAddress');
});

it('omits the address block entirely when the location has no address', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
    ]);

    expect($html)->not->toContain('card-address')
        ->and($html)->not->toContain('PostalAddress');
});

it('emits the facet data-id anchor only when fad_facet is passed (the #1242 contract)', function (): void {
    // UAMS-Web/uams-statamic-archive#63 emits the robust `data-id` anchor; the region / location-type facet
    // attributes are UAMS-Web/uams-statamic-archive#1242's (it verifies the augmented term shapes against real
    // content — the collections are empty today).
    $data = [
        'id' => 'loc-1',
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
    ];

    expect(renderLocationCard($data + ['fad_facet' => true]))->toContain('data-id="loc-1"');

    // The index card (no fad_facet) stays clean of the facet payload.
    expect(renderLocationCard($data))->not->toContain('data-id="loc-1"');
});

it('escapes editor-controlled name, description, and address text (no raw HTML sink)', function (): void {
    $html = renderLocationCard([
        'generic_name' => '<script>alert("n")</script>',
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'short_desc' => '<img src=x onerror=alert("d")>',
        'resolved_city' => '<script>alert("a")</script>',
    ]);

    // Antlers is raw by default; the card must encode every editor/import sink —
    // the <h2> name, the description, and the address subfields.
    expect($html)->not->toContain('<script>alert("n")</script>')
        ->and($html)->not->toContain('<img src=x onerror=alert("d")>')
        ->and($html)->not->toContain('<script>alert("a")</script>')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;n&quot;)')
        ->and($html)->toContain('&lt;img src=x onerror');
});

it('ignores the stored address, which a child location may carry without owning it', function (): void {
    // A child that inherits its street keeps whatever it stored; only the resolved
    // computeds may render, in the card and in its directions link (UAMS-Web/uams-statamic-archive#2608). The
    // preset-specific state handles are read by `resolved_state` itself.
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'address' => [
            'street_address' => 'STORED-STREET',
            'city' => 'STORED-CITY',
            'state_province_region_american' => 'XX',
        ],
        'resolved_city' => 'Riverside',
        'resolved_state' => 'IA',
    ]);

    expect($html)->toContain('>IA<')
        ->and($html)->toContain('Riverside')
        ->and($html)->not->toContain('STORED-STREET')
        ->and($html)->not->toContain('STORED-CITY')
        ->and($html)->not->toContain('>XX<');
});

it('renders the appointment phone as a dialable link, preferring the appointment number', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'phone_appointment_new' => '+15016868998',
        'phone' => '+15012964000',
    ]);

    // The dedicated appointment number wins over the general-information line,
    // dialable in a tel: link (raw E.164) and shown AP-national via format_phone (UAMS-Web/uams-statamic-archive#1465).
    expect($html)->toContain('href="tel:+15016868998"')
        ->and($html)->toContain('501-686-8998')
        ->and($html)->not->toContain('501-296-4000');
});

it('falls back to the general-information phone on the card when no appointment phone is set', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'phone' => '+15012964000',
    ]);

    expect($html)->toContain('href="tel:+15012964000"')
        ->and($html)->toContain('501-296-4000');
});

it('uses the single shared phone on the card when shared_phone_number is on (#1185 precedence)', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'shared_phone_number' => true,
        'shared_phone' => '+18007270000',
        'phone_appointment_new' => '+15016868998',
        'phone' => '+15012964000',
    ]);

    expect($html)->toContain('href="tel:+18007270000"')
        ->and($html)->toContain('800-727-0000')
        ->and($html)->not->toContain('501-686-8998');
});

it('omits the phone line entirely when the location has no phone', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
    ]);

    expect($html)->not->toContain('tel:')
        ->and($html)->not->toContain('card-phone');
});

it('renders a Get Directions link built from the address', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'resolved_street_address' => '1701 Starfleet Blvd',
        'resolved_city' => 'Riverside',
        'resolved_state' => 'IA',
        'resolved_postal_code' => '52327',
    ]);

    // A Google Maps directions URL with the full address rawurlencoded as the
    // destination; the ampersand is HTML-escaped in the attribute (UAMS-Web/uams-statamic-archive#1465).
    expect($html)->toContain('Get Directions')
        ->and($html)->toContain('https://www.google.com/maps/dir/?api=1&amp;destination=')
        ->and($html)->toContain(rawurlencode('1701 Starfleet Blvd'))
        ->and($html)->toContain(rawurlencode('52327'));
});

it('omits the Get Directions link when the location has no address', function (): void {
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
    ]);

    expect($html)->not->toContain('Get Directions')
        ->and($html)->not->toContain('maps/dir');
});

it('renders a "Part of {parent}" subtitle linking to the parent location', function (): void {
    // The parent name / URL are provided by the structure-tree computeds
    // (parent_location_name / parent_location_url); the card renders them. The
    // real-tree resolution of those computeds is covered in FadLocationsBlockTest.
    $html = renderLocationCard([
        'title' => 'Cancer Institute (3rd Floor)',
        'generic_name' => 'Cancer Institute',
        'url' => '/health/locations/cancer-institute',
        'parent_location_name' => 'UAMS Medical Center',
        'parent_location_url' => '/health/locations/uams-medical-center',
    ]);

    expect($html)->toContain('Part of')
        ->and($html)->toContain('UAMS Medical Center')
        ->and($html)->toContain('href="/health/locations/uams-medical-center"');
});

it('renders the parent name without a link when the parent URL is unavailable', function (): void {
    $html = renderLocationCard([
        'title' => 'Cancer Institute',
        'url' => '/health/locations/cancer-institute',
        'parent_location_name' => 'UAMS Medical Center',
    ]);

    expect($html)->toContain('Part of')
        ->and($html)->toContain('UAMS Medical Center')
        ->and($html)->not->toContain('<a href="">');
});

it('omits the "Part of" subtitle for a top-level location with no parent', function (): void {
    $html = renderLocationCard([
        'title' => 'UAMS Medical Center',
        'url' => '/health/locations/uams-medical-center',
    ]);

    expect($html)->not->toContain('Part of')
        ->and($html)->not->toContain('card-parent');
});

it('escapes editor-controlled phone and parent name on the card (no raw HTML sink)', function (): void {
    // format_phone returns its raw input on an unparseable value, so the phone
    // display must be sanitized; the parent name is editor/import text too.
    $html = renderLocationCard([
        'title' => 'Enterprise Medical Center',
        'url' => '/health/locations/enterprise',
        'phone' => '"><script>alert(1)</script>',
        'parent_location_name' => '<script>alert("p")</script>',
        'parent_location_url' => '/x',
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('<script>alert("p")</script>');
});

it('wires the locations listing query, empty state, and pagination', function (): void {
    $listing = File::get(resource_path('views/clinical_locations/_listing.antlers.html'));
    $body = File::get(resource_path('views/clinical_locations/_list_body.antlers.html'));

    // The listing owns the query and delegates the card grid to the shared body
    // (which the Find-a-Doc locations block reuses too — UAMS-Web/uams-statamic-archive#63).
    expect($listing)
        ->toContain('collection:clinical_locations')
        ->toContain('sort="title:asc"')
        ->toContain('partial:clinical_locations/list_body');

    // The shared body owns the empty state and pagination.
    expect($body)
        ->toContain('no_results')
        ->toContain('locations-empty')
        ->toContain('paginate:total_pages > 1');
});

it('renders the map, website, and related entities in the location show', function (): void {
    $show = File::get(resource_path('views/clinical_locations/show.antlers.html'));

    expect($show)
        // Interactive map from the resolved coordinate, via the shared viewer: a child that
        // does not own its map group shows its parent's pin (UAMS-Web/uams-statamic-archive#2608).
        ->toContain('{{ if resolved_map }}')
        ->toContain('partial:map :coordinate="resolved_map"')
        ->not->toContain(':coordinate="map"')
        ->toContain('short_desc')
        ->toContain('external_website_url')
        // Related Find-a-Doc entities. Providers at this location are filtered to
        // clinical-provider-eligible, searchable people once, then reused (UAMS-Web/uams-statamic-archive#919).
        ->toContain('eligible_providers = physician_locations | clinical_provider_eligible')
        ->toContain(':entries="eligible_providers"')
        ->toContain(':entries="expertise"')
        // The full profile is deferred to Group HH — recorded in the template.
        ->toContain('Group HH');
});

it('renders the locations listing structure without error against real content', function (): void {
    $this->requireContentCollection('clinical_locations');

    $html = (string) Antlers::parse('{{ partial:clinical_locations/listing }}', []);

    expect($html)->toContain('class="container-fluid"');
    expect($html)->not->toContain('{{');
});
