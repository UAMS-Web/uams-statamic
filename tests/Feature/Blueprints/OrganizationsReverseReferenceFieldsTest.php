<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Organizations reverse-reference display fields (UAMS-Web/uams-statamic-archive#1373)
|--------------------------------------------------------------------------
|
| The reverse computeds surface read-only in the CP via `visibility: computed`
| entries fields — no editor-editable stored field. These parse the blueprint
| YAML and assert each field exists and is a read-only computed entries field.
|
| @command php artisan test --compact tests/Feature/Blueprints/OrganizationsReverseReferenceFieldsTest.php
*/

/**
 * Find a field's config block by handle anywhere in the organization blueprint's tabs.
 *
 * @return array<string, mixed>|null
 */
function orgBlueprintField(string $handle): ?array
{
    $path = resource_path('blueprints/collections/organizations/organization.yaml');
    /** @var array{tabs?: array<int, array{sections?: array<int, array{fields?: array<int, array<string, mixed>>}>}>} $parsed */
    $parsed = Yaml::parseFile($path);

    foreach ($parsed['tabs'] ?? [] as $tab) {
        foreach ($tab['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['handle'] ?? null) === $handle && is_array($field['field'] ?? null)) {
                    /** @var array<string, mixed> $config */
                    $config = $field['field'];

                    return $config;
                }
            }
        }
    }

    return null;
}

it('exposes each reverse computed as a read-only computed entries field with instructions', function (string $handle): void {
    /** @var array<string, mixed> $field */
    $field = orgBlueprintField($handle);

    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        ->and($field['instructions'] ?? '')->not->toBe('');
})->with([
    'service_line_members',
    'academic_department_residents',
    'academic_department_fellows',
]);
