<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaBlueprintDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSectionDefaultsRepository;
use App\Services\SchemaTemplates\SchemaSiteDefaultsRepository;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;

uses(MockeryPHPUnitIntegration::class);

/**
 * Subtype-routed main content node resolution (UAMS-Web/uams-statamic-archive#1444): when the winning cascade
 * level carries a `main_content_entry_by_subtype` map and the entity's
 * MedicalOrganization subtype has a resolvable entry in it, that per-subtype node
 * is routed instead of the flat `main_content_entry`. A level with no map, no
 * subtype, or an unmapped/empty subtype falls back to `main_content_entry` — so
 * the routing is generic and opt-in and never regresses non-location collections.
 *
 * `resolveMainContentEntry` / `resolveSubtype` are protected/private and pure, so
 * they are exercised directly via reflection with stub repositories.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaTemplatesCascadeSubtypeRoutingTest.php
 */
function cascadeService(): SchemaTemplatesCascadeService
{
    return new SchemaTemplatesCascadeService(
        Mockery::mock(SchemaSiteDefaultsRepository::class),
        Mockery::mock(SchemaSectionDefaultsRepository::class),
        Mockery::mock(SchemaBlueprintDefaultsRepository::class),
    );
}

/**
 * Invoke the protected resolveMainContentEntry with an explicit subtype.
 *
 * @param  array<string, mixed>  $site
 * @param  array<string, mixed>  $section
 * @param  array<string, mixed>  $blueprint
 * @param  array<string, mixed>  $entry
 */
function resolveMainContent(array $site, array $section, array $blueprint, array $entry, ?string $subtype): ?string
{
    /** @var string|null $resolved */
    $resolved = (new ReflectionMethod(cascadeService(), 'resolveMainContentEntry'))
        ->invoke(cascadeService(), $site, $section, $blueprint, $entry, $subtype);

    return $resolved;
}

/**
 * A blueprint-level config with a subtype map, the level a location lands on.
 *
 * @return array<string, mixed>
 */
function locationBlueprintLevel(): array
{
    return [
        'inherit_main_content_entry' => false,
        'main_content_entry' => 'fallback-node',
        'main_content_entry_by_subtype' => [
            'Hospital' => 'hospital-node',
            'MedicalClinic' => 'clinic-node',
            'Pharmacy' => 'pharmacy-node',
            'Dentist' => 'dentist-node',
            'DiagnosticLab' => 'lab-node',
        ],
    ];
}

it('routes to the subtype-mapped node when the subtype matches', function () {
    $resolved = resolveMainContent([], [], locationBlueprintLevel(), [], 'Hospital');

    expect($resolved)->toBe('hospital-node');
});

it('routes each subtype to its own node', function (string $subtype, string $expected) {
    expect(resolveMainContent([], [], locationBlueprintLevel(), [], $subtype))->toBe($expected);
})->with([
    ['Hospital', 'hospital-node'],
    ['MedicalClinic', 'clinic-node'],
    ['Pharmacy', 'pharmacy-node'],
    ['Dentist', 'dentist-node'],
    ['DiagnosticLab', 'lab-node'],
]);

it('falls back to main_content_entry when the subtype is not in the map', function () {
    expect(resolveMainContent([], [], locationBlueprintLevel(), [], 'UnmappedType'))->toBe('fallback-node');
});

it('routes a selectable subtype descendant to its nearest mapped ancestor node', function () {
    // `schema_type` single mode also offers descendants of the starting types;
    // CovidTestingFacility is a MedicalClinic descendant, so it must route to the
    // clinic node (keeping availableService) rather than fall to the shared node.
    expect(resolveMainContent([], [], locationBlueprintLevel(), [], 'CovidTestingFacility'))->toBe('clinic-node');
});

it('falls back to main_content_entry when there is no subtype', function () {
    expect(resolveMainContent([], [], locationBlueprintLevel(), [], null))->toBe('fallback-node');
});

it('falls back to main_content_entry when the mapped value is empty', function () {
    $level = [
        'inherit_main_content_entry' => false,
        'main_content_entry' => 'fallback-node',
        'main_content_entry_by_subtype' => ['Hospital' => ''],
    ];

    expect(resolveMainContent([], [], $level, [], 'Hospital'))->toBe('fallback-node');
});

it('coerces a non-string scalar mapped node id to a string', function () {
    // singleEntryId normalizes any scalar id to a string; under strict_types a
    // dropped cast would raise a TypeError instead of returning '12345'.
    $level = [
        'inherit_main_content_entry' => false,
        'main_content_entry' => 'fallback-node',
        'main_content_entry_by_subtype' => ['Hospital' => 12345],
    ];

    try {
        $result = resolveMainContent([], [], $level, [], 'Hospital');
    } catch (Throwable $e) {
        $result = 'threw: '.$e->getMessage();
    }

    expect($result)->toBe('12345');
});

it('is unaffected for a level with no subtype map (non-location collections)', function () {
    $blueprint = ['inherit_main_content_entry' => false, 'main_content_entry' => 'plain-node'];

    // A subtype is present but the level has no map: the flat node is returned.
    expect(resolveMainContent([], [], $blueprint, [], 'Hospital'))->toBe('plain-node');
});

it('routes at the winning cascade level, not a shallower one', function () {
    // Blueprint carries the subtype map; entry inherits — the blueprint wins.
    $entryInherits = [];

    expect(resolveMainContent([], [], locationBlueprintLevel(), $entryInherits, 'Pharmacy'))->toBe('pharmacy-node');
});

it('lets an explicit entry-level override beat subtype routing', function () {
    // The editor picked a specific node at the entry level (no map there): that
    // explicit choice wins over the blueprint-level subtype map.
    $entryOverride = ['inherit_main_content_entry' => false, 'main_content_entry' => 'editor-pick'];

    expect(resolveMainContent([], [], locationBlueprintLevel(), $entryOverride, 'Hospital'))->toBe('editor-pick');
});

/*
|--------------------------------------------------------------------------
| resolveSubtype — extracting the editor-selected subtype from the entity
|--------------------------------------------------------------------------
*/

/** Invoke the private resolveSubtype against a mock entry returning $stored. */
function resolveSubtypeFor(mixed $stored): ?string
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('schema_medicalorganization_subtype')->andReturn($stored);

    /** @var string|null $resolved */
    $resolved = (new ReflectionMethod(cascadeService(), 'resolveSubtype'))->invoke(cascadeService(), $entry);

    return $resolved;
}

it('reads a plain-string subtype', function () {
    expect(resolveSubtypeFor('Hospital'))->toBe('Hospital');
});

it('unwraps a Value-wrapped subtype', function () {
    $value = Mockery::mock(Value::class);
    $value->shouldReceive('value')->andReturn('Pharmacy');

    expect(resolveSubtypeFor($value))->toBe('Pharmacy');
});

it('trims surrounding whitespace from the subtype', function () {
    expect(resolveSubtypeFor('  Dentist  '))->toBe('Dentist');
});

it('returns null for an unset subtype', function () {
    expect(resolveSubtypeFor(null))->toBeNull();
});

it('returns null for an empty-string subtype', function () {
    expect(resolveSubtypeFor(''))->toBeNull();
});

it('returns null for a non-string subtype', function () {
    expect(resolveSubtypeFor(['not', 'a', 'string']))->toBeNull();
});
