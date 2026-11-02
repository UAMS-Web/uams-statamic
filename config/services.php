<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // O*NET Web Services
    'onet_web_services' => [
        'api_key' => env('ONET_WEB_SERVICES_API_KEY'),
        'base_url' => env('ONET_WEB_SERVICES_BASE_URL', 'https://api-v2.onetcenter.org'),
        'cache_ttl' => (int) env('ONET_WEB_SERVICES_CACHE_TTL', 86400),
        'delay_ms' => (int) env('ONET_WEB_SERVICES_DELAY', 0),
        'rate_limit_store' => env('ONET_WEB_SERVICES_RATE_LIMIT_STORE', 'redis'),
        'max_requests_per_second' => (int) env('ONET_WEB_SERVICES_MAX_REQUESTS_PER_SECOND', 5),
    ],

    // HUD USPS Crosswalk API (e.g. `generate:zip-enum`)
    'hud' => [
        'usps_api_token' => env('US_HUD_API_TOKEN'),
    ],

    // GitHub REST API (e.g. `docs:generate` for remote trees/blobs)
    'github' => [
        'token' => env('GITHUB_TOKEN'),
    ],

    // Google Business Information API `categories.list` (`gbp:check-categories --api`,
    // UAMS-Web/uams-statamic-archive#2617). The refresh token is issued for the `business.manage` scope; an access
    // token is minted from it on each run and never stored.
    'google_business_profile' => [
        'client_id' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_BUSINESS_PROFILE_REFRESH_TOKEN'),
    ],

];
