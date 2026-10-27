<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | RSS feed tag cache TTL
    |--------------------------------------------------------------------------
    |
    | Parsed feed items for the Statamic rss_feed tag are cached for this many
    | seconds to avoid fetching external feeds on every page request. Set
    | RSS_FEED_CACHE_TTL in the environment to override the default.
    |
    */

    'cache_ttl_seconds' => (int) env('RSS_FEED_CACHE_TTL', 300),

];
