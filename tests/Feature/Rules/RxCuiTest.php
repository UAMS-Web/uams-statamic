<?php

declare(strict_types=1);

use App\Rules\RxCui;
use Tests\TestCase;

uses(TestCase::class);

covers(RxCui::class);

/**
 * @covers \App\Rules\RxCui
 *
 * @command  php artisan test --compact tests/Feature/Rules/RxCuiTest.php
 */
it('accepts one or more digits', function (string $value): void {
    expect(validator(['id' => $value], ['id' => [new RxCui]])->passes())->toBeTrue();
})->with([
    'single digit' => ['1'],
    'multi digit' => ['12345'],
]);

it('rejects non-digit values', function (string $value): void {
    expect(validator(['id' => $value], ['id' => [new RxCui]])->passes())->toBeFalse();
})->with([
    'letters' => ['12a'],
    'trailing newline' => ["12345\n"],
]);
