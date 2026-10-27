<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\LogosUnrestricted\AltFormatter;
use Tests\TestCase;

uses(TestCase::class);

covers(AltFormatter::class);

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

/**
 * Tests {@see AltFormatter} title-to-alt behavior.
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Assets/LogosUnrestricted/AltFormatterTest.php
 */
it('formats the alt from the title', function (mixed $title, ?string $expected): void {
    expect(AltFormatter::fromTitle($title))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty string' => ['', null],
    'whitespace only' => ['   ', null],
    'non-string' => [123, null],
    'array' => [['UAMS'], null],
    'organization name' => ['UAMS', 'Logo of UAMS'],
    'trimmed' => ['  ACME Corp  ', 'Logo of ACME Corp'],
]);
