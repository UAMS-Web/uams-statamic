<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1067 slice 2 — `relevantSpecialty` on the Condition/Service entity nodes.
 *
 * A `clinical_conditions` (`MedicalCondition`) / `clinical_services` (`MedicalProcedure`)
 * entity node emits `relevantSpecialty` over its related `clinical_expertise` (Medical
 * Specialty) entries — the exact-domain property `MedicalEntity → MedicalSpecialty`. The
 * specialty resolves to the `MedicalSpecialty` node (retyped in UAMS-Web/uams-statamic-archive#1442).
 *
 * C/T + expertise content is import-purged, so the related specialty is provided by mocking
 * `Entry::find()` (partial mock) to return an in-memory `clinical_expertise` entry.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdClinicalRelevantSpecialtyTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

const CONDITION_NODE = 'eb185cba-9b3d-4592-88bf-bfc39f60518a';
const SERVICE_NODE = '14420a0e-c07a-4e17-8be5-000000001442';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/** An in-memory `clinical_expertise` (Medical Specialty) entry for the traversal target. */
function relSpecFixtureSpecialty(string $id, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_expertise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('clinical_expertise');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(Str::slug($title));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $title]);

    return $entry;
}

/**
 * Build a C/T entity node for an in-memory entry carrying a `clinical_expertise`
 * relationship, with `Entry::find()` mocked to return the specialty fixtures.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, Statamic\Entries\Entry>  $findable
 * @return array<string, mixed>|null
 */
function buildCtNodeWithSpecialty(string $collection, string $blueprint, string $nodeId, array $data, array $findable): ?array
{
    $find = Entry::partialMock();
    foreach ($findable as $id => $entry) {
        $find->shouldReceive('find')->with($id)->andReturn($entry);
    }

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
    $entry = $entry->slug($data['slug'] ?? 'fixture');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($nodeId, $entry);
}

it('emits relevantSpecialty over the related clinical_expertise, resolving to a MedicalSpecialty node', function (string $collection, string $blueprint, string $nodeId, string $expectedType) {
    $specialty = relSpecFixtureSpecialty('spec-endo', 'Endocrinology');

    $node = buildCtNodeWithSpecialty($collection, $blueprint, $nodeId, [
        'clinical_expertise' => ['spec-endo'],
    ], ['spec-endo' => $specialty]);

    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe($expectedType);

    /** @var array<array-key, mixed> $rel */
    $rel = $node['relevantSpecialty'] ?? null;
    expect($rel)->not->toBeNull();

    $relNodes = array_is_list($rel) ? $rel : [$rel];
    $types = array_map(fn ($n): mixed => is_array($n) ? ($n['@type'] ?? null) : null, $relNodes);
    expect($types)->toContain('MedicalSpecialty');
})->with([
    'condition' => ['clinical_conditions', 'clinical_condition', CONDITION_NODE, 'MedicalCondition'],
    'service' => ['clinical_services', 'clinical_service', SERVICE_NODE, 'MedicalProcedure'],
]);

it('emits no relevantSpecialty when the C/T has no related specialty', function (string $collection, string $blueprint, string $nodeId) {
    $node = buildCtNodeWithSpecialty($collection, $blueprint, $nodeId, ['clinical_expertise' => []], []);

    expect($node)->toBeArray()
        ->and($node['relevantSpecialty'] ?? null)->toBeIn([null, [], '']);
})->with([
    'condition' => ['clinical_conditions', 'clinical_condition', CONDITION_NODE],
    'service' => ['clinical_services', 'clinical_service', SERVICE_NODE],
]);
