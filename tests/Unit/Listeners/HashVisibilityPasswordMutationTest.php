<?php

declare(strict_types=1);

use App\Listeners\HashVisibilityPassword;
use Illuminate\Support\Facades\Hash;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(HashVisibilityPassword::class);

/**
 * Narrow mutate coverage for HashVisibilityPassword.
 *
 * @command  php artisan test --compact tests/Unit/Listeners/HashVisibilityPasswordMutationTest.php
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

/**
 * @param  array<string, mixed>  $data
 */
function hashVisibilityEntry(array $data): StatamicEntry
{
    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return '.StatamicEntry::class.'.');
    }

    $entry->data($data);
    (new HashVisibilityPassword)->handle(new EntrySaving($entry));

    return $entry;
}

it('hashes a newly typed password and flags it', function (): void {
    $entry = hashVisibilityEntry([
        'visibility_password' => 'correct horse battery staple',
    ]);

    $stored = mustBeString($entry->get('visibility_password'), 'the stored visibility password');

    expect($stored)->not->toBe('correct horse battery staple')
        ->and(Hash::isHashed($stored))->toBeTrue()
        ->and($entry->get('is_password_hashed'))->toBeTrue();
});

it('leaves an already-hashed password untouched and ensures the flag', function (): void {
    $hash = Hash::make('enterprise-ncc-1701');

    $entry = hashVisibilityEntry([
        'visibility_password' => $hash,
    ]);

    expect($entry->get('visibility_password'))->toBe($hash)
        ->and($entry->get('is_password_hashed'))->toBeTrue();
});

it('hashes a new password typed over an existing hash even when the flag is true', function (): void {
    $entry = hashVisibilityEntry([
        'visibility_password' => 'shore-leave-omicron',
        'is_password_hashed' => true,
    ]);

    $stored = mustBeString($entry->get('visibility_password'), 'the stored visibility password');

    expect($stored)->not->toBe('shore-leave-omicron')
        ->and(Hash::isHashed($stored))->toBeTrue()
        ->and($entry->get('is_password_hashed'))->toBeTrue();
});

it('clears a stale hashed flag when the password is empty or missing', function (): void {
    $cleared = hashVisibilityEntry([
        'visibility_password' => '',
        'is_password_hashed' => true,
    ]);
    $missing = hashVisibilityEntry([
        'title' => 'Unprotected',
        'is_password_hashed' => true,
    ]);

    expect($cleared->has('is_password_hashed'))->toBeFalse()
        ->and($missing->has('is_password_hashed'))->toBeFalse();
});

it('leaves a non-string password alone without hashing', function (): void {
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('get')->with('visibility_password')->andReturn(12345);
    $entry->shouldReceive('has')->with('is_password_hashed')->andReturn(false);
    $entry->shouldReceive('remove')->never();
    $entry->shouldReceive('set')->never();

    (new HashVisibilityPassword)->handle(new EntrySaving($entry));
});

it('returns early when the event entry is not an Entry instance', function (): void {
    $event = Mockery::mock(EntrySaving::class);
    $event->entry = 'not-an-entry';

    expect(throwableFrom(fn () => (new HashVisibilityPassword)->handle($event)))->toBeNull();
});
