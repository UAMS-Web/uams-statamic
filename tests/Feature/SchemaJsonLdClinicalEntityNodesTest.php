<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1442 — the Conditions / Services / Medical Specialty schema_object ENTITY nodes must
 * exist and carry the correct @type so the UAMS-Web/uams-statamic-archive#1067 D9 emission resolves against them.
 *
 * Builds each entity node for an in-memory entry (no content saved) and asserts its @type:
 * a clinical_expertise entry → MedicalSpecialty (retyped from MedicalIntangible), a
 * clinical_services entry → MedicalProcedure (new node), a clinical_conditions entry →
 * MedicalCondition (already correct).
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdClinicalEntityNodesTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

const SPECIALTY_ENTITY_NODE = '351f89ed-5148-43e2-bf93-c6025fcc789e';
const CONDITION_ENTITY_NODE = 'eb185cba-9b3d-4592-88bf-bfc39f60518a';
const SERVICE_ENTITY_NODE = '14420a0e-c07a-4e17-8be5-000000001442';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>|null
 */
function buildEntityNode(string $collection, string $blueprint, string $nodeId, array $data): ?array
{
    /** @var string $title */
    $title = $data['title'] ?? 'fixture';

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection($collection);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($data['id'] ?? 'fixture-'.$collection);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($data['slug'] ?? Str::slug($title));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($nodeId, $entry);
}

it('resolves a clinical_expertise entry to a MedicalSpecialty entity node', function () {
    $node = buildEntityNode('clinical_expertise', 'clinical_expertise', SPECIALTY_ENTITY_NODE, [
        'title' => 'Endocrinology',
    ]);

    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe('MedicalSpecialty');
});

it('resolves a clinical_services entry to a MedicalProcedure entity node', function () {
    $node = buildEntityNode('clinical_services', 'clinical_service', SERVICE_ENTITY_NODE, [
        'title' => 'Insulin Pump Therapy',
    ]);

    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe('MedicalProcedure');
});

it('resolves a location @type to its routed per-subtype node from schema_medicalorganization_subtype (#1444)', function (string $subtype) {
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    /** @var Statamic\Entries\Entry $location */
    $location = $location->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->blueprint('location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->locale('health');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->id('fixture-clinical_locations');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->slug('uams-location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->data([
        'title' => 'UAMS Location',
        'schema_medicalorganization_subtype' => $subtype,
    ]);

    // UAMS-Web/uams-statamic-archive#1444 routes the location's main-content node to its per-subtype schema node,
    // whose OWN declared @type is the subtype — the shared base node no longer
    // relabels @type in place. availableService (UAMS-Web/uams-statamic-archive#1067 slice 4) is valid on it.
    /** @var string $routedNodeId */
    $routedNodeId = app(SchemaTemplatesCascadeService::class)->resolve($location)['main_content_entry'];

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($routedNodeId, $location);

    $type = $node['@type'] ?? null;
    $types = is_array($type) ? $type : [$type];
    expect($types)->toContain($subtype);
})->with(['MedicalClinic', 'Hospital']);

it('resolves a clinical_conditions entry to a MedicalCondition entity node (baseline)', function () {
    $node = buildEntityNode('clinical_conditions', 'clinical_condition', CONDITION_ENTITY_NODE, [
        'title' => 'Type 2 Diabetes',
    ]);

    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe('MedicalCondition');
});
