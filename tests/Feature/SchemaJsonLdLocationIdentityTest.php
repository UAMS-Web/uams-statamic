<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Location JSON-LD: `@type` routing, core identity, contact, geo, address and map (UAMS-Web/uams-statamic-archive#967).
 *
 * One of five suites split from the former single `SchemaJsonLdLocationTest`
 * (UAMS-Web/uams-statamic-archive#1797) — that class ran 1085s of a 1306s parallel band, and paratest
 * distributes by class, so it bounded the band on its own. Shared fixtures and
 * node builders live in `tests/Support/SchemaLocationFixtures.php`.
 *
 * Renders the node directly via
 * `SchemaOutputService::buildStandaloneSchemaObjectNode()` against an in-test
 * fixture location entry built in memory, so no content is saved. The
 * schema-template entries it resolves ship in the `content/` submodule, so this
 * suite is content-dependent: it runs green in CI / locally where `content/` is
 * present.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdLocationIdentityTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();

    // Bind a request so @id resolution has a host to anchor on.
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));

    // Pin "today" so the fixed future-dated special-hours fixtures below stay
    // deterministic against the UAMS-Web/uams-statamic-archive#1328 auto-expiry filter regardless of the real
    // calendar date (a fixture dated 2026-11-26 must not silently expire once
    // that day passes).
    $this->travelTo(Carbon::parse('2026-07-11 09:00:00'));
});

it('emits the DiagnosticLab multi-type from the location subtype', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Diagnostic Lab',
        'schema_medicalorganization_subtype' => 'DiagnosticLab',
    ]);

    expect($node)->not->toBeNull();
    expect($node['@type'])->toBe(['DiagnosticLab', 'LocalBusiness']);
});

it('emits a single @type for a LocalBusiness-descendant subtype', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'schema_medicalorganization_subtype' => 'Hospital',
    ]);

    expect($node['@type'])->toBe('Hospital');
});

it('falls back to the declared MedicalOrganization/LocalBusiness type with no subtype', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Clinic',
    ]);

    expect($node['@type'])->toBe(['MedicalOrganization', 'LocalBusiness']);
});

it('emits core identity and contact properties from the location fields', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'schema_medicalorganization_subtype' => 'MedicalClinic',
        'about' => 'A multispecialty clinic serving central Arkansas.',
        'alternate_name' => [['text' => 'UAMS FMC']],
        'same_as' => [['url' => 'https://www.wikidata.org/wiki/Q123']],
        'phone' => '501-555-1234',
        'fax' => '501-555-9999',
        'external_website_url' => 'https://example.org/clinic',
        'schema_additional_type' => [['uri' => 'https://www.wikidata.org/wiki/Q16917']],
    ]);

    expect($node['@type'])->toBe('MedicalClinic');
    expect($node['name'])->toBe('UAMS Family Medical Center');
    expect($node['description'])->toBe('A multispecialty clinic serving central Arkansas.');
    expect($node['telephone'])->toBe('501-555-1234');
    expect($node['faxNumber'])->toBe('501-555-9999');
    expect($node['url'])->toBe('https://example.org/clinic');
    expect($node['sameAs'])->toBe('https://www.wikidata.org/wiki/Q123');
    expect($node['alternateName'])->toBe('UAMS FMC');
    expect($node['additionalType'])->toBe('https://www.wikidata.org/wiki/Q16917');
});

it('emits geo as a nested GeoCoordinates node from the map field', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'map' => ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
    ]);

    expect($node['geo'])->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 34.7492719,
        'longitude' => -92.3198281,
    ]);
});

it('omits geo when the map field has no coordinate', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('geo');
});

it('emits address as a nested Place with a PostalAddress from the address field', function (): void {
    /** @var array{address: array<string, mixed>} $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'address' => [
            'preset' => 'american',
            'street_address' => '4301 W. Markham St.',
            'city' => 'Little Rock',
            'state_province_region_american' => 'AR',
            'postal_code' => '72205',
        ],
    ]);

    expect($node['address']['@type'])->toBe('https://schema.org/Place');

    /** @var array<string, mixed> $postal */
    $postal = $node['address']['address'];
    expect($postal['@type'])->toBe('https://schema.org/PostalAddress');
    expect($postal['streetAddress'])->toBe('4301 W. Markham St.');
    expect($postal['addressLocality'])->toBe('Little Rock');
    expect($postal['addressRegion'])->toBe([
        '@type' => 'https://schema.org/State',
        'name' => 'Arkansas',
    ]);
    expect($postal['addressCountry'])->toBe('US');
    expect($postal['postalCode'])->toBe('72205');
});

it('omits address when the address field is empty', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('address');
});

it('emits hasMap as a Google Maps cid URL from the GBP cid', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'google_business_profile_cid' => '12345678901234567',
    ]);

    expect($node['hasMap'])->toBe('https://www.google.com/maps?cid=12345678901234567');
});

it('omits hasMap when there is no GBP cid', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('hasMap');
});
