<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled sync
    |--------------------------------------------------------------------------
    |
    | `podcasts:sync-health-talk` reconciles the `audio` entries with the
    | DoctorPodcasting feed (UAMS-Web/uams-statamic-archive#1803): new episodes are created, changed ones
    | updated, withdrawn ones unpublished and returned ones re-published. Daily,
    | because a withdrawn clinician's episode should come down within a day and
    | the series publishes roughly monthly, so a tighter cadence buys nothing.
    |
    | `enabled` lets an environment carry the code without the schedule.
    |
    */

    'schedule' => [
        'enabled' => (bool) env('HEALTH_TALK_SYNC_ENABLED', true),
        'cron' => (string) env('HEALTH_TALK_SYNC_CRON', '30 4 * * *'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source
    |--------------------------------------------------------------------------
    |
    | Both endpoints are public and unauthenticated; see
    | `docs/specs/uams-health-talk-podcast.md`. `item_url` takes the feed item's
    | `<customid>`, the stable key joining the feed to the item page. `pause_ms`
    | spaces item-page requests, since a first run fetches one per episode.
    |
    */

    'source' => [
        'feed_url' => (string) env('HEALTH_TALK_FEED_URL', 'https://support.doctorpodcasting.com/podcastfeed/287'),
        'item_url' => (string) env('HEALTH_TALK_ITEM_URL', 'https://support.doctorpodcasting.com/client/uams/item/%s'),
        'timeout' => (int) env('HEALTH_TALK_TIMEOUT', 30),
        'pause_ms' => (int) env('HEALTH_TALK_PAUSE_MS', 500),

        // An enclosure outside this prefix is not stored: the URL is rendered as a
        // player source, so only the vendor's own download host is trusted.
        'audio_url_prefix' => 'https://support.doctorpodcasting.com/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Destination
    |--------------------------------------------------------------------------
    |
    | Episodes are `clinical_resources` `audio` entries on the `health` site, in
    | the `uams-health-talk` `audio_series` term, whose `subtype: [podcast]`
    | switches on the episode fields and the podcast JSON-LD (UAMS-Web/uams-statamic-archive#1611).
    |
    */

    'destination' => [
        'collection' => 'clinical_resources',
        'blueprint' => 'audio',
        'site' => 'health',
        'taxonomy' => 'audio_series',
        'series' => 'uams-health-talk',
    ],

    /*
    |--------------------------------------------------------------------------
    | Speaker mapping
    |--------------------------------------------------------------------------
    |
    | The global set whose replicator maps a raw feed speaker string to a
    | `people` entry, or to "no entry expected". The sync adds a row for every
    | speaker it cannot resolve, so the backlog maintains itself.
    |
    */

    'speaker_mappings' => [
        'global' => 'podcast_speaker_mappings',
        'site' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation safety
    |--------------------------------------------------------------------------
    |
    | A run that would unpublish more than this many entries aborts and reports
    | rather than proceeding. Withdrawals happen one clinician at a time, so a
    | larger drop is far more likely a truncated or broken feed.
    |
    */

    'max_unpublish_per_run' => (int) env('HEALTH_TALK_MAX_UNPUBLISH', 5),

];
