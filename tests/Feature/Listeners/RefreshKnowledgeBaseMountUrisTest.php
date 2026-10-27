<?php

declare(strict_types=1);

use App\Listeners\RefreshKnowledgeBaseMountUris;
use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Tests\TestCase;

covers(RefreshKnowledgeBaseMountUris::class);

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
 * Build the listener with a refresher whose refreshAll() expectation is set.
 *
 * @param  int  $times  Expected number of refreshAll() calls
 */
function refreshKbListenerExpectingRefreshes(int $times): RefreshKnowledgeBaseMountUris
{
    // A refresher double asserting how many times refreshAll() is invoked.
    $refresher = Mockery::mock(KnowledgeBaseMountUriRefresher::class);
    $refresher->shouldReceive('refreshAll')->times($times);

    return new RefreshKnowledgeBaseMountUris($refresher);
}

/**
 * Build an entry double in the given collection.
 */
function refreshKbEntryIn(string $collection): Entry
{
    // An entry double exposing its collection handle plus the identity the
    // EntrySaved event's InitiatorStack reads during construction.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('root')->andReturnSelf();
    $entry->shouldReceive('id')->andReturn('test-entry-id');

    return $entry;
}

/**
 * Mutation: tracked under App\Listeners\RefreshKnowledgeBaseMountUris (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Listeners/RefreshKnowledgeBaseMountUrisTest.php
 */
it('refreshes when a pages entry is saved', function () {
    // A saved pages entry triggers exactly one refresh.
    refreshKbListenerExpectingRefreshes(1)
        ->handle(new EntrySaved(refreshKbEntryIn('pages')));
});

it('does not refresh for non-pages entries', function () {
    // A saved knowledge base entry must not trigger a refresh.
    refreshKbListenerExpectingRefreshes(0)
        ->handle(new EntrySaved(refreshKbEntryIn('knowledge_bases')));
});

it('refreshes when the pages tree is saved', function () {
    // A pages tree double whose handle() resolves to 'pages'.
    $tree = Mockery::mock();
    $tree->shouldReceive('handle')->andReturn('pages');

    // Saving the pages structure triggers exactly one refresh.
    refreshKbListenerExpectingRefreshes(1)
        ->handle(new CollectionTreeSaved($tree));
});

it('does not refresh for other collection trees', function () {
    // A knowledge_bases tree double.
    $tree = Mockery::mock();
    $tree->shouldReceive('handle')->andReturn('knowledge_bases');

    // Saving another collection's structure must not trigger a refresh.
    refreshKbListenerExpectingRefreshes(0)
        ->handle(new CollectionTreeSaved($tree));
});

it('refreshes when a pages entry is deleted', function (): void {
    refreshKbListenerExpectingRefreshes(1)
        ->handle(new EntryDeleted(refreshKbEntryIn('pages')));
});

it('does not refresh when the collection tree is null', function (): void {
    // Nullsafe on $tree?->handle(): a null tree must not refresh.
    $event = Mockery::mock(CollectionTreeSaved::class);
    $event->tree = null;

    refreshKbListenerExpectingRefreshes(0)->handle($event);
});

it('does not refresh when the entry is null', function (): void {
    $event = Mockery::mock(EntrySaved::class);
    $event->entry = null;

    refreshKbListenerExpectingRefreshes(0)->handle($event);
});
