<?php

declare(strict_types=1);

use App\Rules\ZipCode;
use Tests\TestCase;

uses(TestCase::class);

covers(ZipCode::class);

/**
 * @covers \App\Rules\ZipCode
 *
 * @command  php artisan test --compact tests/Feature/Rules/ZipCodeTest.php
 */
it('accepts five digits or ZIP+4', function (string $value): void {
    expect(validator(['id' => $value], ['id' => [new ZipCode]])->passes())->toBeTrue();
})->with([
    'five digits' => ['72205'],
    'zip plus four' => ['72205-1234'],
]);
it('rejects values that are not exactly five digits', function (string $value): void {
    expect(validator(['id' => $value], ['id' => [new ZipCode]])->passes())->toBeFalse();
})->with([
    'too short' => ['7220'],
    'too long' => ['722050'],
    'non-digit' => ['7220a'],
    'trailing newline' => ["72205\n"],
]);
