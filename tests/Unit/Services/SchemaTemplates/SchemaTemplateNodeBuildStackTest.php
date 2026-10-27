<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaTemplateNodeBuildStack;

covers(SchemaTemplateNodeBuildStack::class);

/**
 * Reset the static build stack so cases do not leak across examples.
 */
afterEach(function (): void {
    $property = (new ReflectionClass(SchemaTemplateNodeBuildStack::class))
        ->getProperty('entryIds');
    $property->setValue(null, []);
});

/**
 * @covers \App\Services\SchemaTemplates\SchemaTemplateNodeBuildStack
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaTemplateNodeBuildStackTest.php
 */
it('reports an entry id only while it is on the stack', function (): void {
    expect(SchemaTemplateNodeBuildStack::contains('entry-a'))->toBeFalse();

    // Push the outer build
    SchemaTemplateNodeBuildStack::push('entry-a');
    expect(SchemaTemplateNodeBuildStack::contains('entry-a'))->toBeTrue()
        ->and(SchemaTemplateNodeBuildStack::contains('entry-b'))->toBeFalse();

    // Push a nested build
    SchemaTemplateNodeBuildStack::push('entry-b');
    expect(SchemaTemplateNodeBuildStack::contains('entry-a'))->toBeTrue()
        ->and(SchemaTemplateNodeBuildStack::contains('entry-b'))->toBeTrue();

    // Pop only the innermost id
    SchemaTemplateNodeBuildStack::pop();
    expect(SchemaTemplateNodeBuildStack::contains('entry-b'))->toBeFalse()
        ->and(SchemaTemplateNodeBuildStack::contains('entry-a'))->toBeTrue();

    // Pop the outer id
    SchemaTemplateNodeBuildStack::pop();
    expect(SchemaTemplateNodeBuildStack::contains('entry-a'))->toBeFalse();
});

it('compares entry ids strictly so loosely equal values do not match', function (): void {
    // Seed a non-string id that loosely equals "1" but is not identical
    $property = (new ReflectionClass(SchemaTemplateNodeBuildStack::class))
        ->getProperty('entryIds');
    $property->setValue(null, [1]);

    expect(SchemaTemplateNodeBuildStack::contains('1'))->toBeFalse();
});
