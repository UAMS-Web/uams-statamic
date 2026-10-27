<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | UAMS Alert site buckets
    |--------------------------------------------------------------------------
    |
    | The shared UAMS alert feed (www.uams.edu/web/alert) categorizes each
    | alert by a coarse "site" bucket — the suffix in its `alert-<key>`,
    | `urgent-<key>`, and `fyi-<key>` category slugs (e.g. `uams`,
    | `uamshealth`). There are only a handful of buckets but ~255 Statamic
    | sites, so the mapping is many-to-few and must be declared explicitly
    | here: a name-prefix rule would mis-bucket academic sites such as
    | `health_literacy` (the Center for Health Literacy) into the clinical
    | `uamshealth` bucket.
    |
    |   Key   = alert bucket (must match the feed's category suffix exactly).
    |   Value = the Statamic site handles whose pages should display that
    |           bucket's alerts.
    |
    | Any site handle not listed below falls back to `default`. The resolved
    | bucket is emitted as `data-uams-alert-site` on `<body>` and read by
    | `resources/js/uams_2020/uamsalert.js`.
    |
    */
    'buckets' => [
        // 'uamshealth' => ['health', 'health_nicu', ...],
        // 'inside'     => [...],
        // 'northwest'  => [...],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default bucket
    |--------------------------------------------------------------------------
    |
    | Alert bucket for any site handle not listed in `buckets`. The main UAMS
    | site (`uams`) is the safe default — it shows the shared UAMS-wide alert.
    |
    */
    'default' => 'uams',

];
