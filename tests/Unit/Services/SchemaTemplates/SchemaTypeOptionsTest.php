<?php

declare(strict_types=1);

use App\Enums\SchemaType;
use App\Services\SchemaTemplates\SchemaTypeOptions;

/**
 * Unit tests for SchemaTypeOptions (Schema.org type discovery from spatie/schema-org).
 *
 * Mutation: 100% on App\Services\SchemaTemplates\SchemaTypeOptions (10 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaTypeOptionsTest.php
 */
beforeEach(function (): void {
    $reflection = new ReflectionClass(SchemaTypeOptions::class);
    $reflection->getProperty('allTypeNames')->setValue(null, null);
    $reflection->getProperty('schemaObjectTypes')->setValue(null, null);
});

it('returns an array of common schema.org types from schema object types', function () {
    // Get the schema object types.
    $options = SchemaTypeOptions::schemaObjectTypes();

    // Assert Thing type is present.
    expect($options)->toHaveKey('Thing');

    // Assert Person type is present.
    expect($options)->toHaveKey('Person');

    // Assert Organization type is present.
    expect($options)->toHaveKey('Organization');

    // Assert Thing label equals type name.
    expect($options['Thing'])->toBe('Thing');

    // Assert Person label equals type name.
    expect($options['Person'])->toBe('Person');
});

it('returns schema object type keys sorted alphabetically', function () {
    // Get the schema object types.
    $options = SchemaTypeOptions::schemaObjectTypes();

    // Get the keys from the options.
    $keys = array_keys($options);

    // Copy keys and sort for comparison.
    $sorted = $keys;
    sort($sorted);

    // Assert keys are in alphabetical order.
    expect($keys)->toBe($sorted);
});

it('returns the phpdoc description for known schema types', function () {
    // Get the thing description.
    expect(SchemaType::typeDescriptionFor('Thing'))->toBe('The most generic type of item.');

    // Get the person description.
    expect(SchemaType::typeDescriptionFor('Person'))->toBe('A person (alive, dead, undead, or fictional).');
});

it('returns null for an empty or unknown schema type description', function (?string $typeName) {
    expect(SchemaType::typeDescriptionFor($typeName))->toBeNull();
})->with([
    'empty type' => [''],
    'unknown type' => ['UnknownTypeThatDoesNotExist'],
]);

it('returns the phpdoc description for a known property', function () {
    $desc = SchemaType::propertyDescriptionFor('Thing', 'additionalType');
    expect($desc)->not->toBeNull();
    expect($desc)->toContain('additional type for the item');

    $alt = SchemaType::propertyDescriptionFor('Thing', 'alternateName');
    expect($alt)->toBe('An alias for the item.');
});

it('returns null for an empty or unknown property description', function (?string $typeName, ?string $propertyName) {
    expect(SchemaType::propertyDescriptionFor($typeName, $propertyName))->toBeNull();
})->with([
    'empty type name' => ['', 'name'],
    'empty property name' => ['Thing', ''],
    'unknown type' => ['UnknownType', 'name'],
    'unknown property' => ['Thing', 'NonExistentProperty'],
]);
