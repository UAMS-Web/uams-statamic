<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1444 — route a `clinical_locations` entry's `main_content` schema node by its
 * `schema_medicalorganization_subtype`, so each subtype emits from its OWN node
 * (a real per-subtype structured-data surface) instead of one shared
 * `MedicalOrganization`/`LocalBusiness` node whose `@type` is relabeled at render.
 *
 * These assert the cascade routing (`SchemaTemplatesCascadeService::resolve`
 * returning the mapped `main_content_entry`), that each per-subtype node inherits
 * the location identity from the shared base, and that `availableService` is
 * gated onto only the `Hospital`/`MedicalClinic` nodes. They need the `content/`
 * submodule (the schema_object nodes + tree) and self-skip without it.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdLocationSubtypeRoutingTest.php
 */
uses(TestCase::class);

/** The shared `MedicalOrganization`/`LocalBusiness` node — the no-subtype fallback. */
const LOC_SUBTYPE_FALLBACK_NODE = '0b9f1bee-6c0b-4ba5-9ddc-850794f08c8d';

/** Subtype → per-subtype schema node id (mirrors `clinical_locations.yaml`). */
const LOC_SUBTYPE_NODES = [
    'Hospital' => '4f0cdac8-1a2d-482b-886d-513e206712be',
    'MedicalClinic' => 'ae674876-4929-468d-8dc3-ee5a668ec593',
    'Pharmacy' => '391cf4a2-b70c-4a2b-9da2-9e2c786afdc6',
    'Dentist' => '68fcf124-1f72-4d2e-b77b-22405a7a43e6',
    'DiagnosticLab' => 'cce8dd6e-6813-4b08-9215-c4eded19335e',
];

beforeEach(function (): void {
    if (! EntryFacade::find(LOC_SUBTYPE_NODES['Hospital'])) {
        ContentSubmodule::reportMissing('per-subtype schema nodes');
    }

    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/**
 * Build an in-memory `clinical_locations` fixture with an optional subtype.
 *
 * @param  array<string, mixed>  $extra
 */
function makeSubtypeLocation(?string $subtype, array $extra = []): Statamic\Contracts\Entries\Entry
{
    $data = array_merge(['title' => 'UAMS Location Fixture'], $extra);

    if ($subtype !== null) {
        $data['schema_medicalorganization_subtype'] = $subtype;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_locations');
    $entry->locale('health');
    $entry->id('subtype-fixture-location');
    $entry->slug('subtype-fixture-location');
    $entry->data($data);

    return $entry;
}

/**
 * The effective schema (type + inherited/overlaid properties) for a schema node.
 *
 * @return array{type: mixed, properties: array<string, mixed>}
 */
function effectiveSchemaFor(string $nodeId): array
{
    $service = app(SchemaOutputService::class);
    $method = new ReflectionMethod($service, 'getEffectiveSchema');
    $method->setAccessible(true);

    /** @var array{type: mixed, properties: array<string, mixed>}|null $effective */
    $effective = $method->invoke($service, EntryFacade::find($nodeId), []);

    expect($effective)->not->toBeNull();

    /** @var array{type: mixed, properties: array<string, mixed>} $effective */
    return $effective;
}

it('routes each location subtype to its own schema node', function (string $subtype, string $expectedNodeId): void {
    $resolved = app(SchemaTemplatesCascadeService::class)->resolve(makeSubtypeLocation($subtype));

    expect($resolved['main_content_entry'])->toBe($expectedNodeId);
})->with([
    ['Hospital', LOC_SUBTYPE_NODES['Hospital']],
    ['MedicalClinic', LOC_SUBTYPE_NODES['MedicalClinic']],
    ['Pharmacy', LOC_SUBTYPE_NODES['Pharmacy']],
    ['Dentist', LOC_SUBTYPE_NODES['Dentist']],
    ['DiagnosticLab', LOC_SUBTYPE_NODES['DiagnosticLab']],
]);

it('routes a location with no subtype to the shared MedicalOrganization node', function (): void {
    $resolved = app(SchemaTemplatesCascadeService::class)->resolve(makeSubtypeLocation(null));

    expect($resolved['main_content_entry'])->toBe(LOC_SUBTYPE_FALLBACK_NODE);
});

it('declares the expected @type list on each per-subtype node', function (string $subtype, array $expectedType): void {
    expect(effectiveSchemaFor(LOC_SUBTYPE_NODES[$subtype])['type'])->toBe($expectedType);
})->with([
    ['Hospital', ['Hospital']],
    ['MedicalClinic', ['MedicalClinic']],
    ['Pharmacy', ['Pharmacy']],
    ['Dentist', ['Dentist']],
    ['DiagnosticLab', ['DiagnosticLab', 'LocalBusiness']],
]);

it('inherits the base identity properties onto every per-subtype node', function (string $subtype): void {
    // name / address / parentOrganization come from the shared base node
    // (7b3e9f42), so the lean per-subtype nodes are not missing the location
    // identity.
    expect(effectiveSchemaFor(LOC_SUBTYPE_NODES[$subtype])['properties'])
        ->toHaveKeys(['name', 'address', 'parentOrganization']);
})->with(['Hospital', 'MedicalClinic', 'Pharmacy', 'Dentist', 'DiagnosticLab']);

it('carries availableService on the Hospital and MedicalClinic nodes', function (string $subtype): void {
    expect(effectiveSchemaFor(LOC_SUBTYPE_NODES[$subtype])['properties'])->toHaveKey('availableService');
})->with(['Hospital', 'MedicalClinic']);

it('gates availableService off the Pharmacy, Dentist, and DiagnosticLab nodes', function (string $subtype): void {
    expect(effectiveSchemaFor(LOC_SUBTYPE_NODES[$subtype])['properties'])->not->toHaveKey('availableService');
})->with(['Pharmacy', 'Dentist', 'DiagnosticLab']);

it('gates availableService off the shared no-subtype fallback node', function (): void {
    // UAMS-Web/uams-statamic-archive#1444 moved availableService off the shared node onto the clinic/hospital
    // per-subtype nodes, so a no-subtype location no longer over-emits it.
    expect(effectiveSchemaFor(LOC_SUBTYPE_FALLBACK_NODE)['properties'])->not->toHaveKey('availableService');
});

it('renders the inherited location name on a per-subtype node', function (): void {
    $location = makeSubtypeLocation('Hospital', ['title' => 'UAMS Medical Center North']);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        LOC_SUBTYPE_NODES['Hospital'],
        $location
    );

    expect($node)->not->toBeNull();

    /** @var array<string, mixed> $node */
    expect($node['@type'])->toBe('Hospital');
    expect($node['name'])->toBe('UAMS Medical Center North');
});
