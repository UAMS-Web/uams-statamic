<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default (or site-wide) Scheme
    |--------------------------------------------------------------------------
    |
    | The default scheme will be applied to every page of the site.
    | By default, you probably won't want to protect anything
    | at all, but you are free to select one if necessary.
    |
    */

    'default' => null,

    /*
    |--------------------------------------------------------------------------
    | Protection Schemes
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the protection schemes for your application
    | as well as their drivers. You may even define multiple schemes for
    | the same driver to easily protect different types of pages.
    |
    | Supported drivers: "ip_address", "auth", "password"
    |
    */

    'schemes' => [

        'ip_address' => [
            'driver' => 'ip_address',
            'allowed' => ['127.0.0.1'],
        ],

        'logged_in' => [
            'driver' => 'auth',
            'login_url' => '/login',
            'append_redirect' => true,
        ],

        'password' => [
            'driver' => 'password',
            // Sourced from env so a default-and-public password (`secret`) never
            // ships in committed config. When `STATAMIC_PROTECT_PASSWORD` is
            // unset or empty, `array_filter` collapses the list to `[]` and
            // the scheme blocks all access — failing closed beats failing open.
            // No production content uses this scheme today (`grep "^protect:
            // password" content/` returns nothing); set the env var explicitly
            // on the environment that needs it, and rotate per CP-access policy.
            'allowed' => array_filter([env('STATAMIC_PROTECT_PASSWORD')]),
            'field' => null,
            'form_url' => null,
        ],

    ],

];
