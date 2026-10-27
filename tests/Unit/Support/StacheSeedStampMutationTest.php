<?php

declare(strict_types=1);

use App\Support\StacheSeedStamp;
use Illuminate\Filesystem\Filesystem;
use Mockery\MockInterface;
use Tests\TestCase;

covers(StacheSeedStamp::class);

uses(TestCase::class);

/**
 * Narrow mutate coverage for App\Support\StacheSeedStamp.
 *
 * Uses a mocked Filesystem so mutant children never touch the shared stamp file.
 *
 * @command  php artisan test --compact tests/Unit/Support/StacheSeedStampMutationTest.php
 */

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

it('resolves the stamp beside the Stache snapshot directory', function (): void {
    expect(StacheSeedStamp::path())->toBe(storage_path('framework/stache-seed.root'));
});

it('writes the content root into the stamp path', function (): void {
    /** @var Filesystem&MockInterface $files */
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('put')
        ->once()
        ->with(
            StacheSeedStamp::path(),
            '/tmp/content-mirror',
        )
        ->andReturnTrue();

    StacheSeedStamp::write(
        $files,
        '/tmp/content-mirror',
    );
});

it('deletes an existing stamp and no-ops when absent', function (): void {
    /** @var Filesystem&MockInterface $present */
    $present = Mockery::mock(Filesystem::class);
    $present->shouldReceive('exists')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturnTrue();
    $present->shouldReceive('delete')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturnTrue();

    StacheSeedStamp::clear($present);

    /** @var Filesystem&MockInterface $absent */
    $absent = Mockery::mock(Filesystem::class);
    $absent->shouldReceive('exists')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturnFalse();
    $absent->shouldReceive('delete')->never();

    StacheSeedStamp::clear($absent);
});

it('matches only when the stamp exists and equals the root after trim', function (): void {
    /** @var Filesystem&MockInterface $missing */
    $missing = Mockery::mock(Filesystem::class);
    $missing->shouldReceive('exists')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturnFalse();
    $missing->shouldReceive('get')->never();

    expect(StacheSeedStamp::matches(
        $missing,
        '/tmp/content-mirror',
    ))->toBeFalse();

    /** @var Filesystem&MockInterface $match */
    $match = Mockery::mock(Filesystem::class);
    $match->shouldReceive('exists')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturnTrue();
    $match->shouldReceive('get')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturn(" /tmp/content-mirror \n");

    expect(StacheSeedStamp::matches(
        $match,
        '/tmp/content-mirror',
    ))->toBeTrue();

    /** @var Filesystem&MockInterface $mismatch */
    $mismatch = Mockery::mock(Filesystem::class);
    $mismatch->shouldReceive('exists')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturnTrue();
    $mismatch->shouldReceive('get')
        ->once()
        ->with(StacheSeedStamp::path())
        ->andReturn('/other/root');

    expect(StacheSeedStamp::matches(
        $mismatch,
        '/tmp/content-mirror',
    ))->toBeFalse();
});
