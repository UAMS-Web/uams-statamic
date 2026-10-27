<?php

declare(strict_types=1);

use App\Services\SchemaObject\SchemaPropertyReplicatorSets;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Memoization tests for SchemaPropertyReplicatorSets::setsForSingleType.
 *
 * Mutation: this file covers only the memo path. The whole class is mutation-clean together
 * with tests/Feature/Services/SchemaObject/SchemaPropertyReplicatorSetsTest.php.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaObject/SchemaPropertyReplicatorSetsRequestMemoTest.php
 */
it('returns the same memoized array instance for repeated builds of the same type', function () {
    $service = new SchemaPropertyReplicatorSets;
    $method = new ReflectionMethod(
        SchemaPropertyReplicatorSets::class,
        'setsForSingleType'
    );
    $method->setAccessible(true);

    // Repeated builds for the same type name reuse one memoized array instance.
    $first = $method->invoke($service, 'Number');
    $second = $method->invoke($service, 'Number');

    expect($first)->toBeArray();
    expect($first)->toHaveKey('number');
    expect($first === $second)->toBeTrue();
});
