<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listeners\DispatchLocationRegionBackfill;
use App\Services\Locations\LocationRegionBackfiller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;

/**
 * Derives a `clinical_locations` entry's Arkansas `regions` from its coordinate
 * off the synchronous save path (UAMS-Web/uams-statamic-archive#1112).
 *
 * Dispatched by {@see DispatchLocationRegionBackfill} on
 * `EntrySaved` (and by the `content:backfill-location-regions` command). The
 * geocode runs here rather than inline in `EntrySaving`, so a large bulk import
 * of distinct coordinates no longer serialize-stalls the save path on the Census
 * outbound call — on a real queue connection the lookups drain in the background.
 *
 * The entry is re-loaded (not carried across the queue boundary) and re-gated by
 * {@see LocationRegionBackfiller::backfill()}, so a race — the entry deleted, or
 * its region filled by another save between dispatch and run — is a clean no-op.
 * A failed lookup is logged and swallowed, never retried into a stall.
 */
final class BackfillLocationRegion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $entryId  The `clinical_locations` entry id to backfill
     */
    public function __construct(
        public string $entryId
    ) {}

    /**
     * Re-load the entry and fill its region, when it still qualifies.
     */
    public function handle(LocationRegionBackfiller $backfiller): void
    {
        try {
            $entry = Entry::find($this->entryId);

            if (! $entry instanceof EntryModel) {
                return;
            }

            $backfiller->backfill($entry);
        } catch (\Throwable $throwable) {
            Log::warning('BackfillLocationRegion: failed to backfill region', [
                'entry' => $this->entryId,
                'message' => $throwable->getMessage(),
            ]);
        }
    }
}
