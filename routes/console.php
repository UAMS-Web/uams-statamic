<?php

declare(strict_types=1);

use App\Jobs\ApplyScheduledPublishing;
use App\Jobs\RefreshProviderEligibilityForDatedLocations;
use App\Jobs\ScanLinkHealth;
use App\Jobs\SyncHealthTalkEpisodes;
use App\Jobs\UnpublishExpiredClassifieds;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Statamic\Statamic;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Warm the PubMed E-summary cache out of band so the request-time
// research_publication_select computed field reads cache-only (UAMS-Web/uams-statamic-archive#865).
Schedule::command('pubmed:warm')->daily();

// Run the Incomplete Published Entries audit out of band, so the dashboard widget reads
// its result instead of scanning inside the request (UAMS-Web/uams-statamic-archive#2506). The scan takes over a minute,
// so it runs in the background and never overlaps itself. Hourly, because the widget keeps
// the last result through entry saves and only needs to catch up with them.
Schedule::command('cp:scan-incomplete-published-entries')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Compare the Google Business Profile category taxonomy with the Business Information API
// (UAMS-Web/uams-statamic-archive#2617), so nobody has to remember the check (UAMS-Web/uams-statamic-archive#2091). Twice a month rather than once:
// the dashboard widget flags a check overdue after 30 days, which a run on the 1st would
// trip for a day before each run that follows a 31-day month. A failed fetch is logged and
// leaves the last good check in place, so a lasting failure surfaces as that flag.
Schedule::command('gbp:check-categories --api')
    ->twiceMonthly(1, 15, '06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Scan content links on a recurring schedule (UAMS-Web/uams-statamic-archive#1772). `Schedule::job()` rather than
// `Schedule::command()` so the scan is dispatched and the scheduler process is not held
// for the length of an estate-wide crawl — AC1's "dispatches rather than running inline".
//
// The cadence is a cron expression in `config/link_health.php` rather than `->daily()`,
// because a scan issues outbound requests to every external host in the estate and the
// hour it does that is an operational choice rather than a code one.
if (config('link_health.schedule.enabled')) {
    Schedule::job(new ScanLinkHealth)->cron((string) config('link_health.schedule.cron'));
}

// Unpublish classified listings whose expiration date has passed (UAMS-Web/uams-statamic-archive#2520), on the same
// `Schedule::job()` pattern. The cadence is read in the display timezone because a listing
// expires at the start of the audience's calendar day, not UTC's.
if (config('classifieds.expiry.enabled')) {
    Schedule::job(new UnpublishExpiredClassifieds)
        ->cron((string) config('classifieds.expiry.cron'))
        ->timezone(Statamic::displayTimezone());
}

// Publish and unpublish people (UAMS-Web/uams-statamic-archive#2552) and pages (UAMS-Web/uams-statamic-archive#2564) on their `publish_at` /
// `unpublish_at` times, on the same `Schedule::job()` pattern. Both collections are undated,
// so this job, not a `date_behavior`, is what holds a scheduled entry back.
if (config('scheduled_publishing.schedule.enabled')) {
    Schedule::job(new ApplyScheduledPublishing)
        ->cron((string) config('scheduled_publishing.schedule.cron'))
        ->timezone(Statamic::displayTimezone());
}

// Re-index provider eligibility for the people linked to practice locations whose date has
// passed since the last run (UAMS-Web/uams-statamic-archive#3178): a scheduled location counts toward eligibility only from
// its date (UAMS-Web/uams-statamic-archive#3176), and nothing is saved when that date arrives. The cadence is
// `clinical_provider_profile.location_date_refresh.cron`, every fifteen minutes by default, read
// in the display timezone as the scheduled publishing cadence is.
if (config('clinical_provider_profile.location_date_refresh.enabled')) {
    Schedule::job(new RefreshProviderEligibilityForDatedLocations)
        ->cron(config()->string('clinical_provider_profile.location_date_refresh.cron'))
        ->timezone(Statamic::displayTimezone());
}

// Reconcile the UAMS Health Talk episodes with the DoctorPodcasting feed (UAMS-Web/uams-statamic-archive#1803): new
// episodes are created, changed ones updated, withdrawn ones unpublished and returned ones
// re-published. Daily, so a withdrawn clinician's episode comes down within a day.
if (config('uams_health_talk.schedule.enabled')) {
    Schedule::job(new SyncHealthTalkEpisodes)->cron(config()->string('uams_health_talk.schedule.cron'));
}
