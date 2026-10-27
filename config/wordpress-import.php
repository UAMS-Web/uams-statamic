<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WordPress Import Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for the WordPress import process.
    |
    */

    // ---
    // Asset upload settings
    // ---

    // Toggle image asset uploads on/off (default: true)
    'upload_images' => filter_var(
        env(
            'WORDPRESS_IMPORT_IMAGES',
            true
        ),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Toggle file asset uploads on/off (default: true)
    'upload_files' => filter_var(
        env(
            'WORDPRESS_IMPORT_FILES',
            true
        ),
        FILTER_VALIDATE_BOOLEAN
    ),

    // ---
    // Asset download settings
    // ---

    // Skip failed asset downloads and continue with original URL (default: false)
    'skip_failed_assets' => filter_var(
        env(
            'WORDPRESS_IMPORT_SKIP_FAILED_ASSETS',
            false
        ),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Timeout in seconds for asset downloads (default: 60)
    'asset_timeout' => (int) env(
        'WORDPRESS_IMPORT_ASSET_TIMEOUT',
        60
    ),

    // Maximum number of retry attempts for failed downloads (default: 3)
    'asset_retries' => (int) env(
        'WORDPRESS_IMPORT_ASSET_RETRIES',
        3
    ),

    // ---
    // Tag import
    // ---

    // Import post and knowledge base tags (default: true when not set).
    // `post_tags` (UAMS-Web/uams-statamic-archive#2532), `knowledge_base_tags` (UAMS-Web/uams-statamic-archive#2534) and the Logo List
    // groupings, `logos_item_categories` (UAMS-Web/uams-statamic-archive#1863) and `logos_item_labels`
    // (UAMS-Web/uams-statamic-archive#2533), are structured collections. The importer writes tags and labels
    // as terms until UAMS-Web/wordpress-importer#1220 lands.
    'import_tags' => filter_var(
        env(
            'WORDPRESS_IMPORT_TAGS',
            true
        ),
        FILTER_VALIDATE_BOOLEAN
    ),

    // ---
    // Migration API source
    // ---

    // Paging for `uamswp-migration-api`, read by the importer when the import runs
    // with `--source=api` instead of against a WXR file.
    //
    // THE ADDRESS AND THE KEY ARE NOT CONFIGURED, deliberately. The importer asks
    // for both when the command runs and stores neither: the estate is 26 separate
    // multisite networks, each issuing its own key from its own admin page, so a
    // configuration able to address them all would hold 26 live credentials on disk
    // for a job that reads one network at a time. Recorded upstream in that
    // package's `docs/DECISIONS.md` (UAMS-Web/wordpress-importer#982).
    //
    // Do not re-add `base_url` or `secret` here. A key read by nothing is
    // indistinguishable from one read by something, and someone setting it would
    // reasonably believe they had configured the importer.
    'migration_api' => [

        // Records per page. Cast because `env()` returns strings, and the client
        // treats a non-numeric value as a configuration error rather than paging
        // at the default and silently ignoring what was set. The API clamps to its
        // own `per_page_max`, advertised on the index endpoint.
        'per_page' => (int) env(
            'UAMSWP_MIGRATION_API_PER_PAGE',
            100
        ),
    ],

    // ---
    // Import reporting
    // ---

    // Measure directory filesize at start and end of import (default: true).
    'measure_filesize' => filter_var(
        env(
            'WORDPRESS_IMPORT_MEASURE_FILESIZE',
            true
        ),
        FILTER_VALIDATE_BOOLEAN
    ),
];
