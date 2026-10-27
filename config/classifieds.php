<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Listing period
    |--------------------------------------------------------------------------
    |
    | How many days a listing posted from the front end stays on the board (UAMS-Web/uams-statamic-archive#2519).
    | 90 is what production WP Adverts ran: 226 of the 229 exported listings expire
    | exactly 90 days after they were posted. The plugin's own default is 30, so the
    | board was configured to 90 deliberately, and this keeps that.
    |
    */

    'listing_days' => (int) env('CLASSIFIEDS_LISTING_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Where a poster's uploaded images land, and how many a listing may carry. The
    | first image becomes the featured image and the rest the gallery, in upload
    | order. 12 is the most any exported listing carried. The file types and the
    | per-file size come from the `images` container's own `validate` rules, so an
    | upload here is held to exactly what a Control Panel upload is.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    |
    | How many listings one account may post, and how many edits it may save, per
    | hour. They are separate limits, so editing never uses up the posting allowance.
    | Production WP Adverts saw at most 16 new listings in a month across everyone,
    | so 20 an hour for one poster is far above honest use.
    |
    */

    'throttle' => [
        'posts_per_hour' => (int) env('CLASSIFIEDS_POSTS_PER_HOUR', 20),
        'edits_per_hour' => (int) env('CLASSIFIEDS_EDITS_PER_HOUR', 60),
    ],

    'images' => [
        'container' => 'images',
        'folder' => 'classifieds',
        'max_files' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry schedule
    |--------------------------------------------------------------------------
    |
    | The recurring job registered in `routes/console.php` that unpublishes
    | listings whose `expiration_date` has passed (UAMS-Web/uams-statamic-archive#2520).
    |
    | The cron expression is read in `statamic.system.display_timezone`, not the
    | application's UTC, because a listing expires at the start of the audience's
    | calendar day (`App\Support\Classifieds\ClassifiedExpiry`). The default runs
    | just after local midnight, so a listing is unpublished within minutes of the
    | board index starting to hide it, on either side of a daylight-saving change.
    |
    | `enabled` lets an environment carry the code without the schedule, as the
    | link-health scan's does (UAMS-Web/uams-statamic-archive#2297).
    |
    */

    'expiry' => [
        'enabled' => (bool) env('CLASSIFIEDS_EXPIRY_ENABLED', true),
        'cron' => (string) env('CLASSIFIEDS_EXPIRY_CRON', '5 0 * * *'),
    ],

];
