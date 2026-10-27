<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Location JSON-LD: `MedicalWebPage`, organization relations and subtype-bleed guards (UAMS-Web/uams-statamic-archive#967).
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
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdLocationRelationsTest.php
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

it('emits the MedicalWebPage node with name, headline and description', function (): void {
    // `name`/`headline`/`datePublished`/`dateModified` inherit from the shared
    // WebPage → CreativeWork base; `description` is a location-specific override
    // (the base sources it from excerpt/description, which locations lack). The
    // `mainEntity` → business node link and populated `primaryImageOfPage`
    // resolve only in the full-graph render (the schema slots and Asset objects),
    // so they are exercised by the pre-merge real-import smoke test.
    /** @var array<string, mixed> $node */
    $node = buildLocationWebpageNode([
        'title' => 'UAMS Family Medical Center',
        'about' => 'A multispecialty clinic serving central Arkansas.',
    ]);

    expect($node)->not->toBeNull();
    expect($node['@type'])->toBe('MedicalWebPage');
    expect($node['name'])->toBe('UAMS Family Medical Center');
    expect($node['headline'])->toBe('UAMS Family Medical Center');
    expect($node['description'])->toBe('A multispecialty clinic serving central Arkansas.');
    expect($node)->toHaveKey('datePublished');
    expect($node)->toHaveKey('dateModified');
});

it('links parentOrganization to the UAMS organization node', function (): void {
    /** @var array{parentOrganization: array<string, mixed>} $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'schema_medicalorganization_subtype' => 'MedicalClinic',
    ]);

    expect($node['parentOrganization']['@id'])->toContain('58185f50-22a4-48e7-9575-b86a1fdffef9');
});

it('emits areaServed as an AdministrativeArea from the regions taxonomy', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'regions' => ['central-arkansas'],
    ]);

    // The regions field is max_items: 1, so the single-element array is
    // flattened by the engine to the bare AdministrativeArea node.
    expect($node['areaServed'])->toBe([
        '@type' => 'https://schema.org/AdministrativeArea',
        'name' => 'Central Arkansas',
    ]);
});

it('omits areaServed when no region is selected', function (): void {
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
    ]);

    expect($node)->not->toHaveKey('areaServed');
});

it('does not bleed the location subtype onto the nested parentOrganization node', function (): void {
    // Regression: the @type rule must scope the location subtype to the
    // location's own node; a nested thing_* ref (the UAMS MedicalOrganization
    // node) must keep its own declared @type, not inherit MedicalClinic.
    /** @var array{'@type': mixed, parentOrganization: array<string, mixed>} $node */
    $node = buildLocationNode([
        'title' => 'UAMS Family Medical Center',
        'schema_medicalorganization_subtype' => 'MedicalClinic',
    ]);

    // The location's own node still carries its subtype.
    expect($node['@type'])->toBe('MedicalClinic');

    // The parent organization keeps its own multi-type, never the subtype.
    $parentType = $node['parentOrganization']['@type'];
    expect($parentType)->toBeArray();
    expect($parentType)->toContain('MedicalOrganization');
    expect($parentType)->not->toContain('MedicalClinic');
});

it('does not bleed the location subtype onto the shared UAMS organization subject node (compose path)', function (): void {
    // Regression (UAMS-Web/uams-statamic-archive#967): on a location page the compose loop builds the shared
    // site-default `subject_entry` (the UAMS MedicalOrganization) under the
    // location's own context, alongside the location's own business node. The
    // subtype-derived @type must drive ONLY the location's own node — never
    // clobber the shared org node, whose declared multi-type identifies UAMS
    // itself. The standalone single-node path the other cases use never builds
    // the subject slot, so only the compose path exercises this.
    $graph = composeLocationGraph([
        'title' => 'UAMS Diagnostic Lab',
        'schema_medicalorganization_subtype' => 'DiagnosticLab',
    ]);

    // With a subtype set, the location's own business node is now the routed
    // per-subtype (DiagnosticLab) node, not the shared MedicalOrganization shell.
    /** @var array<string, mixed> $locationNode */
    $locationNode = graphNodeForEntryId($graph, SCHEMA_LD_LOCATION_DIAGNOSTIC_LAB_ENTRY_ID);
    /** @var array<string, mixed> $orgNode */
    $orgNode = graphNodeForEntryId($graph, UAMS_ORGANIZATION_SUBJECT_ENTRY_ID);

    expect($locationNode)->not->toBeNull();
    expect($orgNode)->not->toBeNull();

    // The location's own business node refines to its selected subtype.
    expect($locationNode['@type'])->toBe(['DiagnosticLab', 'LocalBusiness']);

    // The shared UAMS org keeps its full declared multi-type, never the subtype.
    expect($orgNode['@type'])->toBeArray();
    expect($orgNode['@type'])->toContain('MedicalOrganization');
    expect($orgNode['@type'])->toContain('CollegeOrUniversity');
    expect($orgNode['@type'])->not->toContain('DiagnosticLab');
    expect($orgNode['@type'])->not->toContain('MedicalClinic');
});
