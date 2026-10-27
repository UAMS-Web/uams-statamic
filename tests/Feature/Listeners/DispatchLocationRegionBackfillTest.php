<?php

declare(strict_types=1);

use App\Jobs\BackfillLocationRegion;
use App\Listeners\DispatchLocationRegionBackfill;
use App\Services\Locations\LocationRegionBackfiller;
use Illuminate\Support\Facades\Queue;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;
use Tests\TestCase;

/**
 * Coverage for the EntrySaved dispatch listener that moves the location region
 * derivation off the synchronous save path (UAMS-Web/uams-statamic-archive#1112). The real backfiller is used
 * (its `shouldBackfill` gate makes no outbound call), and the queue is faked so
 * the assertion is purely "did a qualifying save enqueue exactly one backfill".
 *
 * @command  php artisan test --compact tests/Feature/Listeners/DispatchLocationRegionBackfillTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Build a mock location entry the EntrySaved listener can gate on.
 *
 * @param  mixed  $regions  The value `get('regions')` returns
 * @param  mixed  $map  The value `get('map')` returns
 */
function savedLocationMock(?string $collectionHandle, $regions, $map, ?string $id = 'loc-1'): Entry
{
    $collection = null;

    if ($collectionHandle !== null) {
        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('handle')->andReturn($collectionHandle);
    }

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('get')->with('regions')->andReturn($regions);
    $entry->shouldReceive('get')->with('map')->andReturn($map);
    $entry->shouldReceive('id')->andReturn($id);
    // EntrySaved's constructor resolves an initiator from the entry's root.
    $entry->shouldReceive('root')->andReturn($entry);

    return $entry;
}

/**
 * Fire the listener over a saved entry.
 */
function fireSaved(Entry $entry): void
{
    (new DispatchLocationRegionBackfill(app(LocationRegionBackfiller::class)))
        ->handle(new EntrySaved($entry));
}

it('dispatches a backfill job for a qualifying location save', function (): void {
    Queue::fake();

    fireSaved(savedLocationMock('clinical_locations', null, ['lat' => 34.7465, 'lng' => -92.2896], 'loc-42'));

    Queue::assertPushed(BackfillLocationRegion::class, 1);
    Queue::assertPushed(fn (BackfillLocationRegion $job): bool => $job->entryId === 'loc-42');
});

it('dispatches nothing when the location already has a region', function (): void {
    Queue::fake();

    fireSaved(savedLocationMock('clinical_locations', 'central-arkansas', ['lat' => 34.7465, 'lng' => -92.2896]));

    Queue::assertNotPushed(BackfillLocationRegion::class);
});

it('dispatches nothing when the location has no usable coordinate', function (): void {
    Queue::fake();

    fireSaved(savedLocationMock('clinical_locations', null, null));

    Queue::assertNotPushed(BackfillLocationRegion::class);
});

it('dispatches nothing for a non-location collection', function (): void {
    Queue::fake();

    fireSaved(savedLocationMock('posts', null, ['lat' => 34.7465, 'lng' => -92.2896]));

    Queue::assertNotPushed(BackfillLocationRegion::class);
});

it('ignores a saved payload that is not an entry', function (): void {
    Queue::fake();

    // EntrySaved's payload is untyped; a listener reading it cannot assume an Entry.
    $event = new EntrySaved(savedLocationMock('clinical_locations', null, ['lat' => 34.7465, 'lng' => -92.2896]));
    $event->entry = new stdClass;

    (new DispatchLocationRegionBackfill(app(LocationRegionBackfiller::class)))->handle($event);

    Queue::assertNotPushed(BackfillLocationRegion::class);
});

it('dispatches nothing for a qualifying location that has no id yet', function (): void {
    Queue::fake();

    fireSaved(savedLocationMock('clinical_locations', null, ['lat' => 34.7465, 'lng' => -92.2896], null));

    Queue::assertNotPushed(BackfillLocationRegion::class);
});
