<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Facilities reverse-reference display fields (UAMS-Web/uams-statamic-archive#1372)
|--------------------------------------------------------------------------
|
| The reverse computeds surface read-only in the CP via `visibility: computed`
| entries fields — no editor-editable stored field. These parse the blueprint
| YAML and assert each field exists, is a read-only computed entries field, and
| (for parking) documents the direct-only semantics in its instructions.
|
| @command php artisan test --compact tests/Feature/Blueprints/FacilitiesReverseReferenceFieldsTest.php
*/

/**
 * Find a field's config block by handle anywhere in a parsed blueprint's tabs.
 *
 * @return array<array-key, mixed>|null
 */
function facBlueprintField(string $blueprint, string $handle): ?array
{
    $path = resource_path("blueprints/collections/facilities/{$blueprint}.yaml");
    /** @var array{tabs?: array<array-key, array{sections?: array<array-key, array{fields?: array<array-key, array{handle?: string, field?: mixed}>}>}>} $parsed */
    $parsed = Yaml::parseFile($path);

    foreach ($parsed['tabs'] ?? [] as $tab) {
        foreach ($tab['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $config = $field['field'] ?? null;
                if (($field['handle'] ?? null) === $handle && is_array($config)) {
                    return $config;
                }
            }
        }
    }

    return null;
}

it('exposes areas_served_for_parking as a read-only computed entries field documenting direct-only parking', function (string $blueprint): void {
    /** @var array{type: string, visibility: string, instructions?: string} $field */
    $field = facBlueprintField($blueprint, 'areas_served_for_parking');

    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        // The direct-vs-inherited choice must be documented in the instructions.
        ->and(strtolower((string) ($field['instructions'] ?? '')))->toContain('inherit');
})->with(['parking_facility', 'parking_division']);

it('exposes contained_locations as a read-only computed entries field', function (string $blueprint): void {
    /** @var array{type: string, visibility: string, instructions?: string} $field */
    $field = facBlueprintField($blueprint, 'contained_locations');

    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        ->and($field['instructions'] ?? '')->not->toBe('');
})->with(['building', 'building_division']);
