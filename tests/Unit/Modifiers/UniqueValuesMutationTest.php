<?php

declare(strict_types=1);

use App\Modifiers\UniqueValues;
use Tests\TestCase;

uses(TestCase::class);

covers(UniqueValues::class);

/**
 * Narrow mutate coverage for UniqueValues.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/UniqueValuesMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('deduplicates scalars and keeps later distinct items after a duplicate', function (): void {
    // ContinueToBreak on the duplicate arm would drop 'c'.
    expect((new UniqueValues)->index(['a', 'b', 'a', 'c'], [], []))->toBe(['a', 'b', 'c']);
});

it('distinguishes typed scalars and null', function (): void {
    expect((new UniqueValues)->index([1, '1', 1, true, 'true', true, null, null, 0, '0'], [], []))
        ->toBe([1, '1', true, 'true', null, 0, '0']);
});

it('deduplicates arrays by serialized value', function (): void {
    expect((new UniqueValues)->index([[1, 2], [1, 2], [3]], [], []))->toBe([[1, 2], [3]]);
});

it('deduplicates objects by instance identity', function (): void {
    $objectA = new stdClass;
    $objectB = new stdClass;

    expect((new UniqueValues)->index([$objectA, $objectA, $objectB], [], []))->toBe([$objectA, $objectB]);
});

it('returns an empty array for empty input', function (): void {
    expect((new UniqueValues)->index([], [], []))->toBe([]);
});
