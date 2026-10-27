<?php

declare(strict_types=1);

use App\Rules\VaStationId;
use Tests\TestCase;

uses(TestCase::class);

covers(VaStationId::class);

/**
 * @covers \App\Rules\VaStationId
 *
 * @command  php artisan test --compact tests/Feature/Rules/VaStationIdTest.php
 */
it('accepts exactly three digits', function (): void {
    expect(validator(['id' => '358'], ['id' => [new VaStationId]])->passes())->toBeTrue();
});

it('rejects values that are not exactly three digits', function (string $value): void {
    expect(validator(['id' => $value], ['id' => [new VaStationId]])->passes())->toBeFalse();
})->with([
    'too short' => ['35'],
    'too long' => ['3580'],
    'non-digit' => ['35a'],
    'trailing newline' => ["358\n"],
]);
