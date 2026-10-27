<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listeners\ScanLinksOnPublish;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Scans one entry's links when it is published (UAMS-Web/uams-statamic-archive#1772).
 *
 * Dispatched by {@see ScanLinksOnPublish}, never run in the save path. An
 * editor pressing Save must not wait on outbound probes to every host that entry links to.
 *
 * The entry is carried as an ID rather than as an object. A serialized entry is a snapshot
 * that can be stale by the time the job runs, and the scan should read what is stored now
 * — which is also why the job re-reads rather than trusting anything the listener saw.
 *
 * Scope: the scan is restricted to this ENTRY, not merely to its site and collection, so
 * its outcomes merge into the stored report without disturbing any other entry's findings
 * (AC2). The narrower option is what makes that true: scoped to a collection, publishing
 * one entry would re-probe every other entry in it, which is both a far larger crawl than
 * a save should cause and a rewrite of findings this publish has no business touching.
 * Site and collection are still passed, so the scope is expressed the same way the
 * scheduled scan expresses its own.
 */
final class ScanEntryLinks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $entryId,
        public string $site,
        public string $collection,
    ) {}

    public function handle(): void
    {
        // Every failure mode is swallowed here rather than at the listener: a probe error,
        // a guard refusal, or a missing entry must not put a job in the failed queue that
        // someone has to clear before the next publish scan runs. The listener's own
        // try/catch covers the DISPATCH failing; this covers the WORK failing.
        try {
            Artisan::call('link-health:scan', [
                '--entry' => $this->entryId,
                '--site' => $this->site,
                '--collection' => $this->collection,
            ]);
        } catch (\Throwable $throwable) {
            Log::warning('On-publish link scan failed.', [
                'entry' => $this->entryId,
                'site' => $this->site,
                'collection' => $this->collection,
                'exception' => $throwable->getMessage(),
            ]);
        }
    }
}
