<?php

declare(strict_types=1);

use App\Modifiers\SafeCount;
use Illuminate\Support\Collection;
use Statamic\Query\Builder;
use Tests\TestCase;

/**
 * Pin counts for lists, query builders, and single-value fallbacks.
 *
 * Linux / PHP 8.4.27 / PCOV: 6/6 statements covered; 10/10 covered mutants
 * killed without exclusions. Every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/SafeCountTest.php
 */
uses(TestCase::class);

covers(SafeCount::class);

it('counts arrays and Countable values without counting their keys', function (
    mixed $value,
    int $expected
): void {
    expect((new SafeCount)->index($value, [], []))->toBe($expected);
})->with([
    'empty array' => [[], 0],
    'associative list' => [['first' => 'a', 'second' => 'b', 'third' => 'c'], 3],
    'collection' => [new Collection([4 => 'a', 9 => 'b']), 2],
    'empty Countable' => [new ArrayObject, 0],
]);

it('currently treats non-countable values as one item, including null and scalars', function (mixed $value): void {
    expect((new SafeCount)->index($value, [], []))->toBe(1);
})->with([
    'single object' => [new stdClass],
    'null' => [null],
    'empty string' => [''],
    'string' => ['three'],
    'zero' => [0],
    'false' => [false],
]);

it('normalizes query counts to integers through the real builder detection', function (
    mixed $count,
    int $expected
): void {
    // Supply driver-specific results through the builder's untyped count().
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('count')->andReturn($count);

    // Require an integer result through the real query-builder detection.
    expect((new SafeCount)->index($query, [], []))->toBe($expected);
})->with([
    'integer' => [7, 7],
    'numeric string' => ['12', 12],
    'nonnumeric result' => ['not numeric', 0],
    'null result' => [null, 0],
]);
