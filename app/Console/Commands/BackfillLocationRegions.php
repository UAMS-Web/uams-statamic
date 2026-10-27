<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackfillLocationRegion;
use App\Listeners\DispatchLocationRegionBackfill;
use App\Services\Locations\LocationRegionBackfiller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * One-shot backfill for UAMS-Web/uams-statamic-archive#1112: queues a region derivation for every
 * `clinical_locations` entry that has a coordinate but no region yet.
 *
 * Now that the region compute runs off the save path
 * ({@see DispatchLocationRegionBackfill}), this command is the
 * operator escape hatch for locations imported while compute was disabled
 * (`MAP_CENSUS_GEOCODER_ENABLED=false`, the recommended bulk-import posture): it
 * enqueues one {@see BackfillLocationRegion} job per qualifying entry, which the
 * queue drains without blocking any request. Run with `--dry-run` first to
 * preview which locations would be queued.
 */
#[Description('Queue region derivation for clinical_locations entries missing a region (UAMS-Web/uams-statamic-archive#1112)')]
#[Signature('content:backfill-location-regions {--dry-run : Report the locations without queueing}')]
class BackfillLocationRegions extends Command
{
    /**
     * Queue a backfill job for each location missing a region.
     */
    public function handle(LocationRegionBackfiller $backfiller): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $queued = 0;

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', 'clinical_locations');
        /** @var Collection<int, EntryModel> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            // Skip locations that already have a region or lack a coordinate — the
            // same empty-only, coordinate-present gate the save path uses.
            if (! $backfiller->shouldBackfill($entry)) {
                continue;
            }

            $id = $entry->id();

            if (! \is_string($id)) {
                continue;
            }

            $queued++;

            if (! $dryRun) {
                BackfillLocationRegion::dispatch($id);
            }

            $this->line(sprintf('%s %s', $dryRun ? '[dry-run]' : 'Queued', $id));
        }

        $this->info(sprintf(
            '%s %d location%s.',
            $dryRun ? 'Would queue' : 'Queued',
            $queued,
            $queued === 1 ? '' : 's'
        ));

        return self::SUCCESS;
    }
}
