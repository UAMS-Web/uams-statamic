<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled scan
    |--------------------------------------------------------------------------
    |
    | The recurring full scan registered in `routes/console.php` (UAMS-Web/uams-statamic-archive#1772). A cron
    | expression rather than a named frequency, because the useful knob here is
    | "when, exactly" — a scan issues outbound requests to every external host in
    | the estate, and the time of day it does that is an operational choice.
    |
    | `enabled` exists so an environment can carry the code without the schedule.
    | A deploy to somewhere without a queue worker should be able to ship this
    | slice and turn the recurring scan on separately (UAMS-Web/uams-statamic-archive#2297).
    |
    */

    'schedule' => [
        'enabled' => (bool) env('LINK_HEALTH_SCAN_ENABLED', true),
        'cron' => (string) env('LINK_HEALTH_SCAN_CRON', '0 3 * * *'),
    ],

    /*
    |--------------------------------------------------------------------------
    | On-publish scan
    |--------------------------------------------------------------------------
    |
    | The narrow scan of one entry's links when it is published. `debounce` is
    | the window within which repeated saves of the SAME entry coalesce into one
    | scan: the normal editing rhythm is save-read-save, and a scan per save
    | would issue outbound requests at the speed someone presses a button.
    |
    */

    'on_publish' => [
        'enabled' => (bool) env('LINK_HEALTH_SCAN_ON_PUBLISH', true),
        'debounce_seconds' => (int) env('LINK_HEALTH_SCAN_DEBOUNCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intranet hosts
    |--------------------------------------------------------------------------
    |
    | Exact host names the link checker may fetch even though they resolve to a
    | private address (UAMS-Web/uams-statamic-archive#1768). `inside.uams.edu` resolves to RFC1918 space on the
    | UAMS network, which is where production runs the scan, and the outbound
    | guard would otherwise refuse it and report every link to it as broken.
    |
    | This is a REACHABILITY list, kept apart from `config/first_party.php`,
    | which is about OWNERSHIP: `inside.uams.edu` is first-party either way.
    | Exact names only — no wildcards, no subdomains, no IP addresses — and only
    | the link checker reads it; every other outbound fetch keeps the guard as
    | strict as ever. A listed host the scan cannot reach is reported
    | `unverifiable`, never broken.
    |
    | Comma-separated in the environment. Set it empty to list nothing.
    |
    */

    'intranet_hosts' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('LINK_HEALTH_INTRANET_HOSTS', 'inside.uams.edu'))),
        static fn (string $host): bool => $host !== '',
    )),

];
