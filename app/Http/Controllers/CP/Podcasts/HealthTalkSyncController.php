<?php

declare(strict_types=1);

namespace App\Http\Controllers\CP\Podcasts;

use App\Jobs\SyncHealthTalkEpisodes;
use App\Providers\HealthTalkServiceProvider;
use Illuminate\Http\RedirectResponse;
use Statamic\Http\Controllers\CP\CpController;

/**
 * The dashboard widget's "Run sync now" button (UAMS-Web/uams-statamic-archive#1803).
 *
 * It starts the same sync the schedule runs, after the response is sent, so an
 * editor who has just completed a speaker mapping sees the result on the next
 * dashboard load rather than waiting for the nightly run.
 */
class HealthTalkSyncController extends CpController
{
    public function __invoke(): RedirectResponse
    {
        $this->authorize(HealthTalkServiceProvider::PERMISSION);

        SyncHealthTalkEpisodes::dispatchAfterResponse();

        return back()->with('success', __('The UAMS Health Talk sync has started. Reload the dashboard in a minute to see its result.'));
    }
}
