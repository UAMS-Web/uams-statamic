<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\ClassifiedsComputedsRegistrar;
use App\Support\Classifieds\ClassifiedExpiry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

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
 * @covers \App\Services\ComputedFields\Registration\ClassifiedsComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ClassifiedsComputedsRegistrarTest.php
 */
it('registers is_expired on classifieds, computed from the stored expiration date', function (): void {
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[] = [$collection, $handle, $callback];
        }
    );

    app(ClassifiedsComputedsRegistrar::class)->register();

    expect($captured)->toHaveCount(1)
        ->and($captured[0][0])->toBe('classifieds')
        ->and($captured[0][1])->toBe('is_expired');

    $callback = $captured[0][2];

    $lapsed = Mockery::mock(Entry::class);
    $lapsed->shouldReceive('get')->with('expiration_date')->andReturn('2000-01-01');

    $current = Mockery::mock(Entry::class);
    $current->shouldReceive('get')->with('expiration_date')->andReturn('2999-12-31');

    // Each answer agrees with the shared expiry definition and the two differ.
    $expiry = app(ClassifiedExpiry::class);

    expect($callback($lapsed))->toBeTrue()
        ->and($expiry->isExpired('2000-01-01'))->toBeTrue()
        ->and($callback($current))->toBeFalse()
        ->and($expiry->isExpired('2999-12-31'))->toBeFalse();
});
