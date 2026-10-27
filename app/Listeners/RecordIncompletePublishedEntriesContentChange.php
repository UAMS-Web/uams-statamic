<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Widgets\IncompletePublishedEntries;
use Illuminate\Support\Facades\Cache;
use Statamic\Events\EntrySaved;

/**
 * Records when content last changed, for the Incomplete Published Entries widget (UAMS-Web/uams-statamic-archive#2506).
 *
 * This used to invalidate the widget's cached scan on every save. The scan costs
 * over a minute to rebuild, so under that behavior one publish sent the next
 * dashboard load into a scan it could not finish inside the request limit. Now a
 * save only records the time; the scan payload stays put until the scheduled
 * `cp:scan-incomplete-published-entries` run replaces it, and the widget says the
 * content has changed since that run rather than discarding it.
 */
class RecordIncompletePublishedEntriesContentChange
{
    /**
     * Handle the entry saved event.
     *
     * @param  EntrySaved  $event  The entry saved event
     */
    public function handle(EntrySaved $event): void
    {
        // Record when content last changed, leaving the scan payload in place
        Cache::forever(IncompletePublishedEntries::CACHE_CONTENT_CHANGED_KEY, time());
    }
}
