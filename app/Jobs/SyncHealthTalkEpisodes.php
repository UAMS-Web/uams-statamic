<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one UAMS Health Talk sync out of band (UAMS-Web/uams-statamic-archive#1803).
 *
 * The schedule dispatches it, and so does the dashboard widget's "Run sync now"
 * button after its response is sent -- the identical code path, so an editor
 * who has just mapped a speaker sees the episode import the same way the
 * nightly run would import it. A first run fetches one item page per episode,
 * which is minutes rather than a request's worth of time.
 *
 * A failed run is not thrown: it is logged and cached as the run's report,
 * where the widget shows it, so no failed job has to be cleared before the
 * next run.
 */
#[Timeout(1800)]
final class SyncHealthTalkEpisodes implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(
        HealthTalkEpisodeSync $sync
    ): void {
        set_time_limit(0);

        $sync->run();
    }
}
