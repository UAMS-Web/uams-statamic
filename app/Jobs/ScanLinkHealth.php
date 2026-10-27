<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs the recurring full link-health scan out of band (UAMS-Web/uams-statamic-archive#1772).
 *
 * Dispatched by the schedule in `routes/console.php` rather than run there with
 * `Schedule::command()`, so the scan leaves the scheduler process immediately instead of
 * occupying it for the length of an estate-wide crawl.
 *
 * It delegates to `link-health:scan` rather than re-orchestrating `LinkExtractor`,
 * `InternalResolver`, `ExternalProber`, `HostThrottle` and `RunStore` itself. A second
 * orchestration would be a second thing to keep in step with UAMS-Web/uams-statamic-archive#1766's, and the two would
 * drift in exactly the way the CI-parity mirrors do.
 *
 * Caveat: on a `sync` connection this runs inline in the scheduler, which is the same shape
 * {@see WarmPubMedSummaries} documents. That is the SAFER of the two states: under
 * `database` with no worker, a dispatched job is written to the table and never executed,
 * silently (UAMS-Web/uams-statamic-archive#2297).
 */
final class ScanLinkHealth implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $exitCode = Artisan::call('link-health:scan');

        // A non-zero exit is logged rather than thrown. The scan is a report, not a
        // transaction: a failed run should surface where runs are read, and must not put
        // a job in the failed queue that someone has to clear before the next scan.
        if ($exitCode !== 0) {
            Log::warning('Scheduled link-health scan exited non-zero.', ['exit_code' => $exitCode]);
        }
    }
}
