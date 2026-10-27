<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\BackfillLocationRegion;
use App\Services\Locations\LocationRegionBackfiller;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;

/**
 * Queues a location region backfill after a `clinical_locations` entry is saved,
 * moving the Census reverse-geocode off the synchronous save path (UAMS-Web/uams-statamic-archive#1112).
 *
 * This replaces the inline `EntrySaving` compute (the old
 * `ComputeLocationRegions` listener). It runs a cheap, no-outbound gate
 * ({@see LocationRegionBackfiller::shouldBackfill()}) and dispatches
 * {@see BackfillLocationRegion} only for a location that has a coordinate and no
 * region yet, so a bulk import enqueues the lookups instead of serializing them
 * on the save path. Firing on `EntrySaved` (after persist) also guarantees the
 * entry already has an id to dispatch by.
 *
 * No loop guard is needed: the job persists with `saveQuietly()` (no `EntrySaved`
 * re-fire) and the empty-only gate short-circuits once `regions` is set.
 */
final class DispatchLocationRegionBackfill
{
    public function __construct(private readonly LocationRegionBackfiller $backfiller) {}

    /**
     * Dispatch a backfill job for a qualifying location save.
     */
    public function handle(EntrySaved $event): void
    {
        $entry = $event->entry;

        if (! $entry instanceof Entry) {
            return;
        }

        if (! $this->backfiller->shouldBackfill($entry)) {
            return;
        }

        $id = $entry->id();

        if (! \is_string($id)) {
            return;
        }

        BackfillLocationRegion::dispatch($id);
    }
}
