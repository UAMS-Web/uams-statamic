<?php

declare(strict_types=1);

use App\Enums\SchemaType;
use App\Services\SchemaTemplates\SchemaTypeOptions;
use Tests\TestCase;

uses(TestCase::class);

covers(SchemaTypeOptions::class);

/**
 * Narrow mutate coverage for App\Services\SchemaTemplates\SchemaTypeOptions.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaTypeOptionsMutationTest.php
 */

/**
 * Clear process-lifetime static caches so each example starts cold.
 */
function clearSchemaTypeOptionsCaches(): void
{
    $reflection = new ReflectionClass(SchemaTypeOptions::class);

    $reflection->getProperty('allTypeNames')->setValue(null, null);
    $reflection->getProperty('schemaObjectTypes')->setValue(null, null);
}

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    clearSchemaTypeOptionsCaches();

    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

afterEach(function (): void {
    clearSchemaTypeOptionsCaches();
});

it('maps every SchemaType case to a name=>label option pair', function (): void {
    $options = SchemaTypeOptions::schemaObjectTypes();

    expect($options)->toHaveCount(count(SchemaType::cases()))
        ->and($options)->toHaveKey('Thing')
        ->and($options['Thing'])->toBe('Thing')
        ->and($options)->toHaveKey('Person')
        ->and($options['Person'])->toBe('Person')
        ->and($options)->toHaveKey('Organization')
        ->and($options['Organization'])->toBe('Organization');
});

it('returns schema object type keys in enum declaration order', function (): void {
    $keys = array_keys(SchemaTypeOptions::schemaObjectTypes());
    $fromEnum = array_map(
        fn (SchemaType $case): string => $case->value,
        SchemaType::cases(),
    );

    expect($keys)->toBe($fromEnum);

    $sorted = $keys;
    sort($sorted);
    expect($keys)->toBe($sorted);
});

it('honors a warm schemaObjectTypes cache via coalesce-assign', function (): void {
    $prop = (new ReflectionClass(SchemaTypeOptions::class))
        ->getProperty('schemaObjectTypes');

    SchemaTypeOptions::schemaObjectTypes();
    $prop->setValue(null, ['SENTINEL' => 'SENTINEL']);

    // ??= must return the planted sentinel; plain = would rebuild.
    expect(SchemaTypeOptions::schemaObjectTypes())->toBe(['SENTINEL' => 'SENTINEL']);
});

it('honors a warm allTypeNames cache via early return', function (): void {
    $reflection = new ReflectionClass(SchemaTypeOptions::class);
    $namesProp = $reflection->getProperty('allTypeNames');
    $optionsProp = $reflection->getProperty('schemaObjectTypes');

    SchemaTypeOptions::schemaObjectTypes();
    $namesProp->setValue(null, ['Zebra']);
    $optionsProp->setValue(null, null);

    // Early return must keep the poisoned names; RemoveEarlyReturn rebuilds.
    expect(SchemaTypeOptions::schemaObjectTypes())->toBe(['Zebra' => 'Zebra']);
});
