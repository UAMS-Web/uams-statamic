<?php

declare(strict_types=1);

use Tests\Support\MutatePadding;

/**
 * @covers \Tests\Support\MutatePadding
 *
 * Pins the once-per-file-per-process padding (#1051). Each padded suite used to keep
 * its guard in a `static` inside its `beforeEach` closure, which Pest rebinds for
 * every test, so a 59-test file slept 59 times under `--mutate`. The guard now lives
 * on `MutatePadding`, and these cases drive it with a recording sleeper.
 *
 * @command php artisan test --compact tests/Unit/Support/MutatePaddingTest.php
 */

/**
 * A padding object whose `--mutate` answer is fixed and whose sleeps are recorded.
 *
 * @param  list<int>  $sleeps  Receives each sleep's length, in microseconds.
 */
function recordingMutatePadding(bool $underMutate, array &$sleeps): MutatePadding
{
    return new MutatePadding(
        static fn (): bool => $underMutate,
        static function (int $microseconds) use (&$sleeps): void {
            $sleeps[] = $microseconds;
        },
    );
}

it('sleeps only on the first call for a file under --mutate', function (): void {
    $sleeps = [];
    $padding = recordingMutatePadding(true, $sleeps);

    expect($padding->pad('tests/Feature/EnterpriseTest.php', 20_000_000))->toBeTrue()
        ->and($padding->pad('tests/Feature/EnterpriseTest.php', 20_000_000))->toBeFalse()
        ->and($padding->pad('tests/Feature/EnterpriseTest.php', 20_000_000))->toBeFalse()
        ->and($sleeps)->toBe([20_000_000]);
});

it('sleeps once for each file, for the length that file asks', function (): void {
    $sleeps = [];
    $padding = recordingMutatePadding(true, $sleeps);

    $padding->pad('tests/Feature/KirkTest.php', 20_000_000);
    $padding->pad('tests/Feature/SpockTest.php', 12_000_000);
    $padding->pad('tests/Feature/KirkTest.php', 20_000_000);

    expect($sleeps)->toBe([20_000_000, 12_000_000]);
});

it('never sleeps without --mutate', function (): void {
    $sleeps = [];
    $padding = recordingMutatePadding(false, $sleeps);

    expect($padding->pad('tests/Feature/EnterpriseTest.php', 20_000_000))->toBeFalse()
        ->and($padding->pad('tests/Feature/EnterpriseTest.php', 20_000_000))->toBeFalse()
        ->and($sleeps)->toBe([]);
});

it('keeps one process-wide instance, so the guard outlives each test closure', function (): void {
    expect(MutatePadding::shared())->toBe(MutatePadding::shared());
});
