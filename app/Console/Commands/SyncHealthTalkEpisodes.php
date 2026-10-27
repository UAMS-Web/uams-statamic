<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reconciles the UAMS Health Talk `audio` entries with the DoctorPodcasting feed (UAMS-Web/uams-statamic-archive#1803).
 *
 * The schedule runs the same sync through {@see \App\Jobs\SyncHealthTalkEpisodes};
 * this is the on-demand form, and the first run is the initial backfill. It
 * exits non-zero when the run aborted without changing anything.
 */
#[Description('Sync UAMS Health Talk episodes from the DoctorPodcasting feed')]
#[Signature('podcasts:sync-health-talk')]
final class SyncHealthTalkEpisodes extends Command
{
    public function handle(
        HealthTalkEpisodeSync $sync
    ): int {
        set_time_limit(0);

        $report = $sync->run();

        if (! $report->completed()) {
            $this->error((string) $report->aborted);

            return self::FAILURE;
        }

        $this->info(\sprintf(
            '%d feed items: %d created, %d updated, %d unpublished, %d re-published, %d deferred on %d unmapped speakers, %d item pages failed.',
            $report->feedItems,
            \count($report->created),
            \count($report->updated),
            \count($report->unpublished),
            \count($report->republished),
            \count($report->deferred),
            \count($report->unresolvedSpeakers()),
            \count($report->pageErrors)
        ));

        return self::SUCCESS;
    }
}
