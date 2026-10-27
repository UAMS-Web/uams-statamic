<?php

declare(strict_types=1);

use App\Rules\DunsNumber;
use Tests\TestCase;

uses(TestCase::class);

covers(DunsNumber::class);

/**
 * @covers \App\Rules\DunsNumber
 *
 * @command  php artisan test --compact tests/Feature/Rules/DunsNumberTest.php
 */
it('accepts exactly nine digits', function (): void {
    expect(validator(['id' => '150483782'], ['id' => [new DunsNumber]])->passes())->toBeTrue();
});

it('rejects values that are not exactly nine digits', function (string $value): void {
    expect(validator(['id' => $value], ['id' => [new DunsNumber]])->passes())->toBeFalse();
})->with([
    'too short' => ['12345678'],
    'too long' => ['1234567890'],
    'non-digit' => ['15048378a'],
    'trailing newline' => ["150483782\n"],
]);
