<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled publish and unpublish (UAMS-Web/uams-statamic-archive#2552, UAMS-Web/uams-statamic-archive#2564)
    |--------------------------------------------------------------------------
    |
    | `App\Jobs\ApplyScheduledPublishing` publishes an entry whose `publish_at`
    | has passed and unpublishes one whose `unpublish_at` has passed, in the
    | collections it names (`people` and `pages`). Those collections are undated,
    | so this job is what holds a scheduled entry back; there is no
    | `date_behavior` doing it.
    |
    | Both fields carry a time, so the default runs every fifteen minutes: a
    | schedule applies within a quarter hour of the time an editor chose. The
    | cron expression is read in `statamic.system.display_timezone`, the zone
    | the Control Panel shows those times in.
    |
    | `enabled` lets an environment carry the code without the schedule, as the
    | classifieds expiry and the link-health scan do. The `PEOPLE_PUBLISHING_*`
    | names this config had while it served only `people` are still read, so an
    | environment that set them keeps its setting.
    |
    */

    'schedule' => [
        'enabled' => (bool) env('SCHEDULED_PUBLISHING_SCHEDULE_ENABLED', env('PEOPLE_PUBLISHING_SCHEDULE_ENABLED', true)),
        'cron' => (string) env('SCHEDULED_PUBLISHING_SCHEDULE_CRON', env('PEOPLE_PUBLISHING_SCHEDULE_CRON', '*/15 * * * *')),
    ],

];
