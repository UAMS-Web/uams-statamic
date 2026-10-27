<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Content-usage display fields (UAMS-Web/uams-statamic-archive#1375)
|--------------------------------------------------------------------------
|
| The usage computeds surface read-only in the CP via the shared
| `uamsweb_computed_content_usage` fieldset, imported into a Computed tab on each
| transcludable source blueprint. These assert the fieldset defines the two
| read-only computed fields, and that every source blueprint imports it — together
| proving the fields land on reusable_content, tables, and calculators.
|
| @command php artisan test --compact tests/Feature/Blueprints/ContentUsageFieldsTest.php
*/

/**
 * Find a field's config block by handle in the content-usage fieldset.
 *
 * @return array<string, mixed>|null
 */
function contentUsageField(string $handle): ?array
{
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(resource_path('fieldsets/uamsweb_computed_content_usage.yaml'));

    /** @var array<int, array<string, mixed>> $fields */
    $fields = $parsed['fields'] ?? [];

    foreach ($fields as $field) {
        if (($field['handle'] ?? null) === $handle && is_array($field['field'] ?? null)) {
            /** @var array<string, mixed> $fieldConfig */
            $fieldConfig = $field['field'];

            return $fieldConfig;
        }
    }

    return null;
}

/** Whether a blueprint imports the given fieldset in any of its tab sections. */
function blueprintImportsFieldset(string $blueprintPath, string $fieldset): bool
{
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(resource_path("blueprints/collections/{$blueprintPath}.yaml"));

    /** @var array<int, array<string, mixed>> $tabs */
    $tabs = $parsed['tabs'] ?? [];

    foreach ($tabs as $tab) {
        /** @var array<int, array<string, mixed>> $sections */
        $sections = $tab['sections'] ?? [];

        foreach ($sections as $section) {
            /** @var array<int, array<string, mixed>> $fields */
            $fields = $section['fields'] ?? [];

            foreach ($fields as $field) {
                if (($field['import'] ?? null) === $fieldset) {
                    return true;
                }
            }
        }
    }

    return false;
}

it('defines in_use as a read-only computed toggle', function (): void {
    /** @var array<string, mixed> $field */
    $field = contentUsageField('in_use');

    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('toggle')
        ->and($field['visibility'])->toBe('computed')
        ->and($field['instructions'] ?? '')->not->toBe('');
});

it('defines used_by as a read-only computed entries field', function (): void {
    /** @var array<string, mixed> $field */
    $field = contentUsageField('used_by');

    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        ->and($field['instructions'] ?? '')->not->toBe('');
});

it('imports the content-usage fieldset on every transcludable source blueprint', function (string $blueprintPath): void {
    expect(blueprintImportsFieldset($blueprintPath, 'uamsweb_computed_content_usage'))->toBeTrue();
})->with([
    'reusable_content/reusable_content_basic_page',
    'reusable_content/reusable_content_landing_page',
    'tables/table',
    'calculators/calculator',
]);
