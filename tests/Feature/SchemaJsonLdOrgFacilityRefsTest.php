<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Schema JSON-LD coverage for the `organizations` (UAMS-Web/uams-statamic-archive#1123) and `facilities`
 * (UAMS-Web/uams-statamic-archive#1124) collection templates, and the `clinical_locations` `brand` /
 * `containedInPlace` refs they make resolvable.
 *
 * Before this wiring, a location's `brand`/`sponsor` (`thing_organization`) and
 * `containedInPlace` (`thing_*`) refs could not materialize: the organizations
 * and facilities collections carried no schema template, so
 * `SchemaThingFromRelationshipResolver::resolveTemplateIdForTarget()` returned
 * null and UAMS-Web/uams-statamic-archive#967 correctly emitted nothing rather than a dangling `@id`. UAMS-Web/uams-statamic-archive#1123
 * wires the existing `Organization` base as the organizations collection's
 * `main_content_entry`; UAMS-Web/uams-statamic-archive#1124 adds a `Place` base for the facilities collection;
 * and both populate the location type-base so a location that sets a
 * `brand_organization` / `facility` now links the corresponding node.
 *
 * Content-dependent (the schema_object templates + collection configs ship in the
 * `content/` submodule), so this mirrors `SchemaJsonLdLocationTest`: `uses(TestCase)`,
 * real content, in-memory fixtures, and — for the cross-entity refs — the
 * `Entry::partialMock()->find()` stub pattern from
 * `SchemaJsonLdLocationAvailableServiceTest`.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdOrgFacilityRefsTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The location `MedicalOrganization`/`LocalBusiness` base node — the no-subtype fallback. */
const ORGFAC_LOCATION_NODE = '0b9f1bee-6c0b-4ba5-9ddc-850794f08c8d';

/** The reused `Organization` base wired as the organizations collection main_content_entry (UAMS-Web/uams-statamic-archive#1123). */
const ORGFAC_ORGANIZATION_TEMPLATE = 'bf9f4263-e3eb-4c45-ab9d-126f769eda38';

/** The `Place` base authored for the facilities collection (UAMS-Web/uams-statamic-archive#1124). */
const ORGFAC_FACILITY_TEMPLATE = '6e5cf3d7-e318-4517-82c8-5f9af79df71a';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/**
 * Build an in-memory `organizations` fixture entry.
 *
 * @param  array<string, mixed>  $data
 */
function orgFixture(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('organizations');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('organization');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($data['id'] ?? 'fixture-org');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($data['slug'] ?? 'fixture-org');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return $entry;
}

/**
 * Build an in-memory `facilities` fixture entry (building blueprint).
 *
 * @param  array<string, mixed>  $data
 */
function facilityFixture(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('facilities');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('building');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('default');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($data['id'] ?? 'fixture-facility');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($data['slug'] ?? 'fixture-facility');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return $entry;
}

/**
 * Build an in-memory `clinical_locations` fixture entry.
 *
 * @param  array<string, mixed>  $data
 */
function orgFacLocationFixture(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('location');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($data['id'] ?? 'fixture-location');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($data['slug'] ?? 'fixture-location');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return $entry;
}

/** Resolve the collection's cascade `main_content_entry` for an entry (proves the wiring). */
function cascadeMainContentEntry(Statamic\Contracts\Entries\Entry $entry): ?string
{
    $resolved = app(SchemaTemplatesCascadeService::class)->resolve($entry)['main_content_entry'];

    return \is_string($resolved) && $resolved !== '' ? $resolved : null;
}

/**
 * Build the location's routed `main_content` node for an in-memory location entry.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>|null
 */
function buildOrgFacLocationNode(array $data): ?array
{
    $location = orgFacLocationFixture($data);
    $resolved = cascadeMainContentEntry($location);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        $resolved ?? ORGFAC_LOCATION_NODE,
        $location
    );
}

/**
 * Normalize a node `@type` (scalar or list) to a list for `toContain` assertions.
 *
 * @return array<array-key, mixed>
 */
function typeList(mixed $type): array
{
    return \is_array($type) ? $type : [$type];
}

// ---
// UAMS-Web/uams-statamic-archive#1123 — organizations collection template
// ---

it('wires the reused Organization base as the organizations collection main_content_entry', function (): void {
    expect(cascadeMainContentEntry(orgFixture(['title' => 'UAMS Health'])))
        ->toBe(ORGFAC_ORGANIZATION_TEMPLATE);
});

it('emits a real Organization node from an organizations entry', function (): void {
    $org = orgFixture([
        'id' => 'org-uams-health',
        'title' => 'UAMS Health',
        'name' => 'UAMS Health',
        'description' => 'The clinical enterprise of UAMS.',
    ]);

    /** @var array<string, mixed> $node */
    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        ORGFAC_ORGANIZATION_TEMPLATE,
        $org
    );

    expect($node)->not->toBeNull();
    expect(typeList($node['@type'] ?? null))->toContain('Organization');
    expect($node['name'])->toBe('UAMS Health');
    expect($node['description'])->toBe('The clinical enterprise of UAMS.');
});

// ---
// UAMS-Web/uams-statamic-archive#1124 — facilities collection template
// ---

it('wires the Place base as the facilities collection main_content_entry for every facility blueprint', function (): void {
    expect(cascadeMainContentEntry(facilityFixture(['title' => 'Outpatient Center'])))
        ->toBe(ORGFAC_FACILITY_TEMPLATE);
});

it('emits a real Place node from a facilities entry', function (): void {
    $facility = facilityFixture([
        'id' => 'fac-outpatient',
        'title' => 'Outpatient Center',
        'name' => 'Outpatient Center',
        'map' => ['lat' => 34.7492719, 'lng' => -92.3198281, 'zoom' => 16],
        'google_business_profile_cid' => '12345678901234567',
        'globallocationnumber' => '0614141000012',
    ]);
    // The facilities `resolved_street_address` computed walks the structure
    // ancestors (Entry::findOrFail) when the entry has no own street address; the
    // in-memory fixture is not persisted, so make it findable for that walk.
    Entry::partialMock()->shouldReceive('find')->with('fac-outpatient')->andReturn($facility);

    /** @var array<string, mixed> $node */
    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        ORGFAC_FACILITY_TEMPLATE,
        $facility
    );

    expect($node)->not->toBeNull();
    expect(typeList($node['@type'] ?? null))->toContain('Place');
    expect($node['name'])->toBe('Outpatient Center');
    expect($node['geo'])->toBe([
        '@type' => 'https://schema.org/GeoCoordinates',
        'latitude' => 34.7492719,
        'longitude' => -92.3198281,
    ]);
    expect($node['hasMap'])->toBe('https://www.google.com/maps?cid=12345678901234567');
    expect($node['globalLocationNumber'])->toBe('0614141000012');
});

it('emits a nested PostalAddress on the facility Place from the flat address fields', function (): void {
    $facility = facilityFixture([
        'id' => 'fac-addr',
        'title' => 'Outpatient Center',
        'streetaddress' => '4301 W. Markham St.',
        'city' => 'Little Rock',
        'state' => 'AR',
        'zip' => '72205',
    ]);
    Entry::partialMock()->shouldReceive('find')->with('fac-addr')->andReturn($facility);

    /** @var array{address: array<string, mixed>} $node */
    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        ORGFAC_FACILITY_TEMPLATE,
        $facility
    );

    /** @var array<string, mixed> $postal */
    $postal = $node['address']['address'];
    expect($postal['@type'])->toBe('https://schema.org/PostalAddress');
    expect($postal['streetAddress'])->toBe('4301 W. Markham St.');
    expect($postal['addressLocality'])->toBe('Little Rock');
    expect($postal['postalCode'])->toBe('72205');
    expect($postal['addressCountry'])->toBe('US');
});

// ---
// UAMS-Web/uams-statamic-archive#1123 + UAMS-Web/uams-statamic-archive#1124 — the location refs the templates make resolvable
// ---

it('links a location brand to the resolved organization node', function (): void {
    $org = orgFixture([
        'id' => 'org-uams-health',
        'title' => 'UAMS Health',
        'name' => 'UAMS Health',
    ]);
    Entry::partialMock()->shouldReceive('find')->with('org-uams-health')->andReturn($org);

    /** @var array{brand: array<string, mixed>} $node */
    $node = buildOrgFacLocationNode([
        'title' => 'UAMS Family Medical Center',
        'brand_organization_query' => true,
        'brand_organization' => ['org-uams-health'],
    ]);

    expect($node)->not->toBeNull();
    expect($node)->toHaveKey('brand');
    expect(typeList($node['brand']['@type'] ?? null))->toContain('Organization');
    expect($node['brand']['name'])->toBe('UAMS Health');
});

it('omits location brand when the brand-organization query is off', function (): void {
    // The blueprint hides but does not clear brand_organization when the toggle
    // flips off; the emitted node must honor the gate.
    $org = orgFixture(['id' => 'org-uams-health', 'title' => 'UAMS Health', 'name' => 'UAMS Health']);
    Entry::partialMock()->shouldReceive('find')->with('org-uams-health')->andReturn($org);

    $node = buildOrgFacLocationNode([
        'title' => 'UAMS Family Medical Center',
        'brand_organization_query' => false,
        'brand_organization' => ['org-uams-health'],
    ]);

    expect($node)->not->toHaveKey('brand');
});

it('links a location containedInPlace to the resolved facility node', function (): void {
    $facility = facilityFixture([
        'id' => 'fac-outpatient',
        'title' => 'Outpatient Center',
        'name' => 'Outpatient Center',
    ]);
    Entry::partialMock()->shouldReceive('find')->with('fac-outpatient')->andReturn($facility);

    /** @var array{containedInPlace: array<string, mixed>} $node */
    $node = buildOrgFacLocationNode([
        'title' => 'UAMS Family Medical Center',
        'facility_query' => true,
        'facility' => ['fac-outpatient'],
    ]);

    expect($node)->not->toBeNull();
    expect($node)->toHaveKey('containedInPlace');
    expect(typeList($node['containedInPlace']['@type'] ?? null))->toContain('Place');
    expect($node['containedInPlace']['name'])->toBe('Outpatient Center');
});

it('omits location containedInPlace when the facility query is off', function (): void {
    $facility = facilityFixture(['id' => 'fac-outpatient', 'title' => 'Outpatient Center', 'name' => 'Outpatient Center']);
    Entry::partialMock()->shouldReceive('find')->with('fac-outpatient')->andReturn($facility);

    $node = buildOrgFacLocationNode([
        'title' => 'UAMS Family Medical Center',
        'facility_query' => false,
        'facility' => ['fac-outpatient'],
    ]);

    expect($node)->not->toHaveKey('containedInPlace');
});
