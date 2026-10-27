<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
            // 'visibility' => 'public', // https://statamic.dev/assets#visibility
        ],

        'assets' => [
            'driver' => 'local',
            'root' => public_path('assets'),
            'url' => '/assets',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'images' => [
            'driver' => 'local',
            'root' => public_path('assets/images'),
            'url' => '/assets/images',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'files' => [
            'driver' => 'local',
            'root' => public_path('assets/files'),
            'url' => '/assets/files',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'portraits' => [
            'driver' => 'local',
            'root' => public_path('assets/portraits'),
            'url' => '/assets/portraits',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'portraits_wide' => [
            'driver' => 'local',
            'root' => public_path('assets/portraits_wide'),
            'url' => '/assets/portraits_wide',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Logos (restricted to administrators only)
        'logos' => [
            'driver' => 'local',
            'root' => public_path('assets/logos'),
            'url' => '/assets/logos',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Logos (unrestricted, available to editors)
        'logos_unrestricted' => [
            'driver' => 'local',
            'root' => public_path('assets/logos_unrestricted'),
            'url' => '/assets/logos_unrestricted',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'table_spreadsheets' => [
            'driver' => 'local',
            'root' => base_path('app/private/assets/table_spreadsheets'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'form_entry_uploads' => [
            'driver' => 'local',
            'root' => base_path('app/private/assets/form_entry_uploads'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'audio' => [
            'driver' => 'local',
            'root' => public_path('assets/audio'),
            'url' => '/assets/audio',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'news_releases' => [
            'driver' => 'local',
            'root' => public_path('assets/news_releases'),
            'url' => '/assets/news_releases',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // WebVTT caption sidecars for self-hosted (Box.com) videos. Public and
        // same-origin so a `<track kind="captions">` always loads; `.vtt` must be
        // served as `text/vtt` for the browser to parse it (see UAMS-Web/uams-statamic-archive#1085).
        'captions' => [
            'driver' => 'local',
            'root' => public_path('assets/captions'),
            'url' => '/assets/captions',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
