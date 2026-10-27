<?php

declare(strict_types=1);

use App\Listeners\PreventDeletingMounts;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Entries\Collection as StatamicCollectionContract;
use Statamic\Entries\Entry;
use Statamic\Events\EntryDeleting;
use Statamic\Facades\Collection as CollectionFacade;
use Tests\TestCase;

// The Dutch message is Statamic's own line, quoted verbatim from lang/vendor/statamic/nl.
// cspell:ignore Gekoppelde bevat collectie niet pagina verwijderd

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\PreventDeletingMounts
 *
 * The thrown message is asserted exactly. A bare `toThrow(Exception::class)` also passes on
 * Mockery's own `BadMethodCallException` for an unstubbed method, which is how an earlier
 * version of this file stayed green while the listener rendered the raw translation key.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/PreventDeletingMountsTest.php
 */

/**
 * A mock entry whose `get('title')` returns the given value.
 *
 * @param  mixed  $title  What the entry's title field holds.
 */
function mountedEntryWithTitle(mixed $title): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('title')->andReturn($title);

    return $entry;
}

/**
 * Route `findByMount()` for the given entry to a mounted collection.
 */
function mountCollectionOn(Entry $entry): void
{
    CollectionFacade::shouldReceive('findByMount')
        ->once()
        ->with($entry)
        ->andReturn(Mockery::mock(StatamicCollectionContract::class));
}

it('allows the delete when the entry is not mounted', function (): void {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->never();

    CollectionFacade::shouldReceive('findByMount')
        ->once()
        ->with($entry)
        ->andReturn(null);

    // Called directly rather than through ->not->toThrow(Exception::class), which would let an
    // Error such as a TypeError through: anything thrown here fails the test (UAMS-Web/uams-statamic-archive#2964). The
    // once() and never() expectations above are what this case asserts.
    (new PreventDeletingMounts)->handle(new EntryDeleting($entry));
});

it('refuses the delete with the translated message naming the entry', function (): void {
    $entry = mountedEntryWithTitle('Mounted Page');
    mountCollectionOn($entry);

    expect(fn () => (new PreventDeletingMounts)->handle(new EntryDeleting($entry)))
        ->toThrow(Exception::class, "Your entry Mounted Page has a collection mounted and can't be deleted.");
});

it('renders the message in the active locale', function (): void {
    app()->setLocale('nl');

    $entry = mountedEntryWithTitle('Gekoppelde pagina');
    mountCollectionOn($entry);

    expect(fn () => (new PreventDeletingMounts)->handle(new EntryDeleting($entry)))
        ->toThrow(Exception::class, 'Je entry Gekoppelde pagina bevat een collectie en kan niet worden verwijderd.');
});

it('casts a scalar title to text', function (): void {
    $entry = mountedEntryWithTitle(42);
    mountCollectionOn($entry);

    expect(fn () => (new PreventDeletingMounts)->handle(new EntryDeleting($entry)))
        ->toThrow(Exception::class, "Your entry 42 has a collection mounted and can't be deleted.");
});

it('leaves the title blank when it is not a scalar', function (mixed $title): void {
    $entry = mountedEntryWithTitle($title);
    mountCollectionOn($entry);

    expect(fn () => (new PreventDeletingMounts)->handle(new EntryDeleting($entry)))
        ->toThrow(Exception::class, "Your entry  has a collection mounted and can't be deleted.");
})->with([
    'null' => [null],
    'array' => [['Mounted Page']],
]);
