<?php

declare(strict_types=1);

use App\Modifiers\IsBoolean;

/**
 * Pin strict boolean detection and the registered alias.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/IsBooleanTest.php
 */
it('registers is_bool as an alias', function (): void {
    expect(IsBoolean::aliases())->toBe(['is_bool']);
});

it('accepts only native booleans', function (mixed $value, bool $expected): void {
    expect((new IsBoolean)->index($value, [], []))->toBe($expected);
})->with([
    'true' => [true, true],
    'false' => [false, true],
    'boolean-like integer' => [1, false],
    'boolean-like text' => ['false', false],
    'null' => [null, false],
]);
