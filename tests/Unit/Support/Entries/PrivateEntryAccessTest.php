<?php

declare(strict_types=1);

use App\Support\Entries\PrivateEntryAccess;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Auth\User;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\User as UserFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PrivateEntryAccess::class);

/**
 * Who may view a `private` entry, per App\Support\Entries\PrivateEntryAccess.
 *
 * The user and entry are doubles, so these never touch the Stache.
 *
 * @command  php artisan test --compact tests/Unit/Support/Entries/PrivateEntryAccessTest.php
 */

/**
 * Make `$user` the current user.
 */
function privateEntryUser(mixed $user): void
{
    UserFacade::shouldReceive('current')->andReturn($user);
}

/**
 * A user double that is or is not a super user, holding only `$permissions`.
 *
 * @param  list<string>  $permissions
 */
function privateEntryViewer(bool $super, array $permissions = []): User
{
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isSuper')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturnUsing(
        fn (string $permission): bool => in_array($permission, $permissions, true)
    );

    return $user;
}

/**
 * An entry double in a collection with the given handle, or in no collection.
 */
function privateEntryIn(mixed $handle): Entry
{
    /** @var Entry&MockInterface $entry */
    $entry = Mockery::mock(Entry::class);

    if ($handle === false) {
        $entry->shouldReceive('collection')->andReturnNull();

        return $entry;
    }

    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($handle);
    $entry->shouldReceive('collection')->andReturn($collection);

    return $entry;
}

it('denies a missing entry without looking up the user', function () {
    UserFacade::shouldReceive('current')->never();

    expect((new PrivateEntryAccess)->canView(null))->toBeFalse();
});

it('denies a guest', function () {
    privateEntryUser(null);

    expect((new PrivateEntryAccess)->canView(privateEntryIn('news')))->toBeFalse();
});

it('allows a super user whatever the collection', function () {
    privateEntryUser(privateEntryViewer(true));

    expect((new PrivateEntryAccess)->canView(privateEntryIn(false)))->toBeTrue();
});

it('allows a user who may publish the entry collection', function () {
    privateEntryUser(privateEntryViewer(false, ['publish news entries']));

    expect((new PrivateEntryAccess)->canView(privateEntryIn('news')))->toBeTrue();
});

it('denies a user who may only view or edit the collection, or publish another', function () {
    privateEntryUser(privateEntryViewer(false, ['view news entries', 'edit news entries', 'publish pages entries']));

    expect((new PrivateEntryAccess)->canView(privateEntryIn('news')))->toBeFalse();
});

it('denies a non-super user when the entry has no collection', function () {
    privateEntryUser(privateEntryViewer(false, ['publish news entries']));

    expect((new PrivateEntryAccess)->canView(privateEntryIn(false)))->toBeFalse();
});

it('denies a collection whose handle is not a string', function () {
    // hasPermission() is never consulted: `publish  entries` must not be checkable.
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isSuper')->andReturnFalse();
    $user->shouldReceive('hasPermission')->never();
    privateEntryUser($user);

    expect((new PrivateEntryAccess)->canView(privateEntryIn(null)))->toBeFalse();
});
