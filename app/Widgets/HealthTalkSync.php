<?php

declare(strict_types=1);

namespace App\Widgets;

use App\Providers\HealthTalkServiceProvider;
use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use App\Services\Podcasts\HealthTalk\HealthTalkSpeakerMappings;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Statamic\Auth\User;
use Statamic\Facades\User as UserFacade;
use Statamic\Widgets\Widget;

/**
 * CP dashboard widget surfacing the UAMS Health Talk speaker-mapping backlog (UAMS-Web/uams-statamic-archive#1803).
 *
 * An episode whose speakers are not all mapped is deferred -- it has no entry
 * at all -- so the backlog is invisible in the collection by construction and
 * has to be shown here. The widget reports how many episodes the last
 * completed sync deferred, the speaker strings still waiting on a mapping, a
 * link to the global set where they are mapped, and a button that runs the
 * sync now. It never fetches the feed itself: it reads the report the last run
 * cached, following the `IncompletePublishedEntries` pattern (UAMS-Web/uams-statamic-archive#2506).
 *
 * Shown only to users who can run the sync, since the button is the widget's point.
 */
class HealthTalkSync extends Widget
{
    /**
     * Render the widget, or nothing for a user who cannot run the sync.
     */
    #[\Override]
    public function html(): string|View
    {
        /** @var User|null $user */
        $user = UserFacade::current();

        if ($user === null || ! $user->can(HealthTalkServiceProvider::PERMISSION)) {
            return '';
        }

        $mappings = app(HealthTalkSpeakerMappings::class);
        $completed = Cache::get(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY);
        $last = Cache::get(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY);
        $syncUrl = cp_route('health-talk.sync');

        return view('widgets.health_talk_sync', [
            'title' => $this->config('title', __('UAMS Health Talk')),
            'completed' => \is_array($completed) ? $completed : null,
            'deferred_count' => \is_array($completed) && \is_array($completed['deferred'] ?? null) ? \count($completed['deferred']) : 0,
            'failure' => \is_array($last) && \is_string($last['aborted'] ?? null) ? $last['aborted'] : null,
            'unmapped' => $mappings->unmappedSpeakers(),
            'mapping_url' => $mappings->editUrl(),
            // cp_route() always returns a string here; the empty fallback is defensive.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            'sync_url' => \is_string($syncUrl) ? $syncUrl : '',
        ]);
    }
}
