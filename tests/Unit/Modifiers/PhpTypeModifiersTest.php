<?php

declare(strict_types=1);

use App\Modifiers\IsBoolean;
use App\Modifiers\IsFloat;
use App\Modifiers\IsInteger;
use App\Modifiers\IsNull;
use App\Modifiers\IsObject;

covers(IsBoolean::class);
covers(IsFloat::class);
covers(IsInteger::class);
covers(IsObject::class);

/**
 * Behavior of the strict PHP-type Antlers modifiers (app/Modifiers/Is*.php): each
 * wraps a single `is_*()` predicate over the piped value, and the registered
 * aliases (is_int / is_decimal / is_obj / is_bool).
 *
 * Mutation: 100% for IsInteger/IsFloat/IsObject/IsBoolean (the aliases lists are the
 * only mutable surface, killed by the aliases test); IsNull has no mutable surface
 * (a bare is_null() with no aliases).
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/PhpTypeModifiersTest.php
 */
it('is_integer is true only for ints', function (mixed $value, bool $expected) {
    expect((new IsInteger)->index($value, [], []))->toBe($expected);
})->with([
    'zero' => [0, true],
    'negative int' => [-1, true],
    'float' => [1.0, false],
    'numeric string' => ['1', false],
    'null' => [null, false],
]);

it('is_float is true only for floats', function (mixed $value, bool $expected) {
    expect((new IsFloat)->index($value, [], []))->toBe($expected);
})->with([
    'float' => [1.5, true],
    'int' => [1, false],
    'numeric string' => ['1.0', false],
    'null' => [null, false],
]);

it('is_object is true only for objects', function (mixed $value, bool $expected) {
    expect((new IsObject)->index($value, [], []))->toBe($expected);
})->with([
    'stdClass' => [new stdClass, true],
    'array' => [[], false],
    'string' => ['x', false],
    'null' => [null, false],
]);

it('is_boolean is true only for bools', function (mixed $value, bool $expected) {
    expect((new IsBoolean)->index($value, [], []))->toBe($expected);
})->with([
    'true' => [true, true],
    'false' => [false, true],
    'int 0' => [0, false],
    'int 1' => [1, false],
    'string "true"' => ['true', false],
    'null' => [null, false],
]);

it('is_null is true only for null', function (mixed $value, bool $expected) {
    expect((new IsNull)->index($value, [], []))->toBe($expected);
})->with([
    'null' => [null, true],
    'int 0' => [0, false],
    'empty string' => ['', false],
]);

it('registers the expected aliases', function () {
    expect(IsInteger::aliases())->toBe(['is_int']);
    expect(IsFloat::aliases())->toBe(['is_decimal']);
    expect(IsObject::aliases())->toBe(['is_obj']);
    expect(IsBoolean::aliases())->toBe(['is_bool']);
});
