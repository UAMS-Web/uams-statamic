<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\TestCase;

/**
 * Location JSON-LD: gated identifiers and `contactPoint` phone routing (UAMS-Web/uams-statamic-archive#967).
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
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdLocationIdentifiersContactTest.php
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

it('emits a single NPI identifier as a bare PropertyValue when npi_query is on', function (): void {
    /** @var array{identifier: array<string, mixed>} $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'npi_query' => true,
        'npi' => [
            ['type' => 'new_set', 'item' => '1234567890', 'enabled' => true],
        ],
    ]);

    // A single identifier is flattened by the engine to the bare PropertyValue.
    expect($node['identifier']['@type'])->toBe('PropertyValue');
    expect($node['identifier']['name'])->toBe('National Provider Identifier');
    expect($node['identifier']['propertyID'])->toBe('https://www.wikidata.org/wiki/Property:P9450');
    expect($node['identifier']['value'])->toBe('1234567890');
});

it('emits every gated identifier as a PropertyValue array', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Medical Center',
        'schema_medicalorganization_subtype' => 'Hospital',
        'npi_query' => true,
        'npi' => [
            ['type' => 'new_set', 'item' => '1234567890', 'enabled' => true],
            ['type' => 'new_set', 'item' => '9876543210', 'enabled' => true],
        ],
        'va_substation_id' => '123ABC',
        'ahaid' => '1234567',
        'hospital_ccn_id' => '044001',
    ]);

    /** @var array<int, array{name: string, value: string}> $identifiers */
    $identifiers = $node['identifier'];
    $names = array_map(
        /** @param  array{name: string, value: string}  $id */
        fn (array $id): string => $id['name'],
        $identifiers,
    );
    expect($names)->toBe([
        'National Provider Identifier',
        'National Provider Identifier',
        'STA6A',
        'American Hospital Association Hospital Identifier',
        'CMS Certification Number',
    ]);

    $values = array_map(fn (array $id) => $id['value'], $identifiers);
    expect($values)->toBe(['1234567890', '9876543210', '123ABC', '1234567', '044001']);
});

it('suppresses NPI when npi_query is off but still emits the other identifiers', function (): void {
    /** @var array{identifier: array<string, mixed>} $node */
    $node = buildLocationNode([
        'title' => 'UAMS Medical Center',
        'schema_medicalorganization_subtype' => 'Hospital',
        'npi_query' => false,
        'npi' => [
            ['type' => 'new_set', 'item' => '1234567890', 'enabled' => true],
        ],
        'ahaid' => '1234567',
    ]);

    // The lingering NPI is gated out; only the AHAID survives (single → bare object).
    expect($node['identifier']['@type'])->toBe('PropertyValue');
    expect($node['identifier']['name'])->toBe('American Hospital Association Hospital Identifier');
    expect($node['identifier']['value'])->toBe('1234567');
});

it('omits identifier when the location has no identifiers', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('identifier');
});

it('emits contactPoint nodes from appointment and additional phone numbers', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'phone_appointment_query' => true,
        'phone_appointment_new' => '501-555-1000',
        'phone_appointment_return' => '501-555-2000',
        'phone_additional' => [
            ['type' => 'new_set', 'text' => 'After-hours nurse line', 'phone' => '501-555-0003', 'description' => 'Staffed 24/7', 'enabled' => true],
        ],
    ]);

    expect($node['contactPoint'])->toBe([
        [
            '@type' => 'https://schema.org/ContactPoint',
            'contactType' => 'appointments',
            'name' => 'New Patient Appointments',
            'telephone' => '501-555-1000',
        ],
        [
            '@type' => 'https://schema.org/ContactPoint',
            'contactType' => 'appointments',
            'name' => 'Returning Patient Appointments',
            'telephone' => '501-555-2000',
        ],
        [
            '@type' => 'https://schema.org/ContactPoint',
            'name' => 'After-hours nurse line',
            'telephone' => '501-555-0003',
            'description' => 'Staffed 24/7',
        ],
    ]);
});

it('emits the Arkansas Children\'s appointment ContactPoint from the location override', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'Arkansas Children\'s Clinic',
        'ac_appointments_query' => true,
        'ac_appointments_primary' => '501-555-3000',
    ]);

    // Single ContactPoint is flattened by the engine to the bare object.
    expect($node['contactPoint'])->toBe([
        '@type' => 'https://schema.org/ContactPoint',
        'contactType' => 'appointments',
        'name' => 'Primary Care Appointments',
        'telephone' => '501-555-3000',
    ]);
});

it('falls back to the Find-a-Doc global default for an empty AC appointment number', function (): void {
    // Exercise the real GlobalSet-read fallback path (the production wiring
    // passes no fallback parameter). The Find-a-Doc global is rooted at the
    // `default` site (UAMS-Web/uams-statamic-archive#1698), where the tag reads it; mutate that row in memory
    // and restore it so no content is persisted.
    /** @var Statamic\Globals\GlobalSet $globalSet */
    $globalSet = GlobalSet::findByHandle('uams_fad_settings');
    /** @var Variables $variables */
    $variables = $globalSet->inDefaultSite();
    $originalPrimary = $variables->get('location_ac_appointments_primary');
    $originalSpecialty = $variables->get('location_ac_appointments_specialty');

    try {
        $variables->set('location_ac_appointments_primary', '501-555-9001');
        $variables->set('location_ac_appointments_specialty', '501-555-9002');

        /** @var array<string, mixed> $node */
        $node = buildLocationNode([
            'title' => 'Arkansas Children\'s Clinic',
            'ac_appointments_query' => true,
        ]);

        // Both AC ContactPoints resolve from the global default (no location override).
        expect($node['contactPoint'])->toBe([
            [
                '@type' => 'https://schema.org/ContactPoint',
                'contactType' => 'appointments',
                'name' => 'Primary Care Appointments',
                'telephone' => '501-555-9001',
            ],
            [
                '@type' => 'https://schema.org/ContactPoint',
                'contactType' => 'appointments',
                'name' => 'Specialty Care Appointments',
                'telephone' => '501-555-9002',
            ],
        ]);
    } finally {
        $variables->set('location_ac_appointments_primary', $originalPrimary);
        $variables->set('location_ac_appointments_specialty', $originalSpecialty);
    }
});

it('does not leak the AC global fallback onto a location with the AC gate closed', function (): void {
    // Security-relevant invariant: the site-wide Arkansas Children's default
    // must never surface on a non-AC location (gate closed / unset).
    /** @var Statamic\Globals\GlobalSet $globalSet */
    $globalSet = GlobalSet::findByHandle('uams_fad_settings');
    /** @var Variables $variables */
    $variables = $globalSet->inDefaultSite();
    $originalPrimary = $variables->get('location_ac_appointments_primary');

    try {
        $variables->set('location_ac_appointments_primary', '501-555-9001');

        $node = buildLocationNode([
            'title' => 'UAMS Family Medical Center',
            'ac_appointments_query' => false,
        ]);

        expect($node)->not->toHaveKey('contactPoint');
    } finally {
        $variables->set('location_ac_appointments_primary', $originalPrimary);
    }
});

it('omits contactPoint when the location has no appointment or additional phones', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('contactPoint');
});

it('suppresses stale appointment numbers when the appointment gate is off (engine path)', function (): void {
    // The blueprint hides but does not clear phone_appointment_* when the toggle
    // flips off; the emitted contactPoint must honor the gate (LabeledValue).
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'phone_appointment_query' => false,
        'phone_appointment_new' => '501-555-1000',
        'phone_appointment_return' => '501-555-2000',
    ]);

    expect($node)->not->toHaveKey('contactPoint');
});
