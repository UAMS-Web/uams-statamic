<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| People reverse-reference display fields (UAMS-Web/uams-statamic-archive#1374)
|--------------------------------------------------------------------------
|
| The reverse computeds surface read-only in the CP via `visibility: computed`
| entries fields in the `uamsweb_computed_person` fieldset (imported by the
| person blueprint's Computed Fields tab). These parse the fieldset YAML and
| assert each field is a read-only computed entries field, including the 1:1
| max_items on the clinical-provider link.
|
| @command php artisan test --compact tests/Feature/Blueprints/PeopleReverseReferenceFieldsTest.php
*/

/**
 * Find a field's config block by handle in the computed-person fieldset.
 *
 * @return array<string, mixed>|null
 */
function computedPersonField(string $handle): ?array
{
    $path = resource_path('fieldsets/uamsweb_computed_person.yaml');
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile($path);

    /** @var list<array<string, mixed>> $fields */
    $fields = $parsed['fields'] ?? [];

    foreach ($fields as $field) {
        if (($field['handle'] ?? null) === $handle && is_array($field['field'] ?? null)) {
            /** @var array<string, mixed> $config */
            $config = $field['field'];

            return $config;
        }
    }

    return null;
}

it('exposes credited_content as a read-only computed entries field with instructions', function (): void {
    $field = computedPersonField('credited_content');

    expect($field)->not->toBeNull();

    /** @var array<string, mixed> $field */
    expect($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        ->and($field['instructions'] ?? '')->not->toBe('')
        // No max_items — a person can be credited on many pieces of content.
        ->and($field)->not->toHaveKey('max_items');
});

it('exposes clinical_provider_entry as a read-only single (max_items 1) computed entries field', function (): void {
    $field = computedPersonField('clinical_provider_entry');

    expect($field)->not->toBeNull();

    /** @var array<string, mixed> $field */
    expect($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        // The provider link is 1:1, so the field must be capped at a single entry.
        ->and($field['max_items'])->toBe(1)
        ->and($field['instructions'] ?? '')->not->toBe('');
});
