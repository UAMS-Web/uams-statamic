<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | License Key
    |--------------------------------------------------------------------------
    |
    | The license key for the corresponding domain from your Statamic account.
    | Without a key entered, your app will be considered to be in Trial Mode.
    |
    | https://statamic.dev/licensing#trial-mode
    |
    */

    'license_key' => env('STATAMIC_LICENSE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Enable Multi-site
    |--------------------------------------------------------------------------
    |
    | Whether Statamic's multi-site functionality should be enabled. It is
    | assumed Statamic Pro is also enabled. To get started, you can run
    | the `php please multisite` command to update your content file
    | structure, after which you can manage your sites in the CP.
    |
    | https://statamic.dev/multi-site
    |
    */

    'multisite' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Addons Paths
    |--------------------------------------------------------------------------
    |
    | When generating addons via `php please make:addon`, this path will be
    | used by default. You can still specify custom repository paths in
    | your composer.json, but this is the path used by the generator.
    |
    */

    'addons_path' => base_path('addons'),

    /*
    |--------------------------------------------------------------------------
    | Blueprints Path
    |--------------------------------------------------------------------------
    |
    | Where your blueprint YAML files are stored.
    |
    */

    'blueprints_path' => resource_path('blueprints'),

    /*
    |--------------------------------------------------------------------------
    | Fieldsets Path
    |--------------------------------------------------------------------------
    |
    | Where your fieldset YAML files are stored.
    |
    */

    'fieldsets_path' => resource_path('fieldsets'),

    /*
    |--------------------------------------------------------------------------
    | Send the Powered-By Header
    |--------------------------------------------------------------------------
    |
    | Websites like builtwith.com use the X-Powered-By header to determine
    | what technologies are used on a particular site. Disabled here to avoid
    | a small information-disclosure surface — knowing the stack hands
    | attackers a starting point for version-specific exploits without giving
    | UAMS anything in return. Flip back to true if a tool we own actually
    | needs the header for monitoring.
    |
    */

    'send_powered_by_header' => false,

    /*
    |--------------------------------------------------------------------------
    | Date Format
    |--------------------------------------------------------------------------
    |
    | Whenever a Carbon date is cast to a string on front-end routes, it will
    | use this format. On CP routes, the format defined in cp.php is used.
    | You can customize this format using PHP's date string constants.
    | Setting this value to null will use Carbon's default format.
    |
    | https://www.php.net/manual/en/function.date.php
    |
    */

    'date_format' => 'F jS, Y',

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Statamic will use this timezone when displaying dates on the front-end.
    | You can use any timezone supported by PHP. When set to null it will
    | fall back to the timezone defined in your `app.php` config file.
    |
    | Prefer keeping `app.timezone` as UTC and setting this to your audience's
    | zone (see Statamic 6 timezones documentation).
    |
    | https://www.php.net/manual/en/timezones.php
    |
    */

    'display_timezone' => env('STATAMIC_DISPLAY_TIMEZONE', 'America/Chicago'),

    /*
    |--------------------------------------------------------------------------
    | Localize Dates in Modifiers
    |--------------------------------------------------------------------------
    |
    | When using date-related modifiers, Carbon instances will be in UTC.
    | Enabling this setting will ensure that dates get localized into
    | the timezone defined in `display_timezone`. Otherwise you'll
    | need to manually localize dates in all of your templates.
    |
    */

    'localize_dates_in_modifiers' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Character Set
    |--------------------------------------------------------------------------
    |
    | Statamic will use this character set when performing specific string
    | encoding and decoding operations; This does not apply everywhere.
    |
    */

    'charset' => 'UTF-8',

    /*
    |--------------------------------------------------------------------------
    | Track Last Update
    |--------------------------------------------------------------------------
    |
    | Statamic will automatically set an `updated_at` timestamp (along with
    | `updated_by`, where applicable) when specific content is updated.
    | In some situations, you may wish disable this functionality.
    |
    */

    'track_last_update' => true,

    /*
    |--------------------------------------------------------------------------
    | Enable Cache Tags
    |--------------------------------------------------------------------------
    |
    | Sometimes you'll want to be able to disable the {{ cache }} tags in
    | Antlers, so here is where you can do that. Otherwise, it will be
    | enabled all the time.
    |
    */

    'cache_tags_enabled' => env('STATAMIC_CACHE_TAGS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Intensive Operations
    |--------------------------------------------------------------------------
    |
    | System resource limits Statamic raises for image generation, console
    | processes (`php please ...`), view cascade rendering, and Antlers regex
    | matching (pcre.backtrack_limit). Defaults are unlimited (`-1` / `0`) so
    | local dev and the WordPress importer don't hit ceilings while debugging.
    |
    | Production should set explicit caps via env so a runaway import or
    | image conversion can't exhaust a shared host:
    |
    |   STATAMIC_PHP_MEMORY_LIMIT=512M      (or 1G — match hosting plan)
    |   STATAMIC_PHP_MAX_EXECUTION_TIME=300 (5 min — long enough for big
    |                                         images, shorter than indefinite)
    |   STATAMIC_PCRE_BACKTRACK_LIMIT=10000000  (10M — sane regex ceiling)
    |
    | When a legitimate intensive job needs more, move it to a queue worker
    | (`QUEUE_CONNECTION=database|redis|sqs`, see UAMS-Web/uams-statamic-archive#610) where you can raise
    | limits per-worker without affecting web request limits.
    |
    */

    'php_memory_limit' => env('STATAMIC_PHP_MEMORY_LIMIT', '-1'),
    'php_max_execution_time' => env('STATAMIC_PHP_MAX_EXECUTION_TIME', '0'),
    'ajax_timeout' => env('STATAMIC_AJAX_TIMEOUT', '600000'),
    'pcre_backtrack_limit' => env('STATAMIC_PCRE_BACKTRACK_LIMIT', '-1'),

    /*
    |--------------------------------------------------------------------------
    | Debugbar Integration
    |--------------------------------------------------------------------------
    |
    | Statamic integrates with Laravel Debugbar to bring more detail to your
    | debugging experience. Here you may adjust various default options.
    |
    */

    'debugbar' => [
        'pretty_print_variables' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | ASCII
    |--------------------------------------------------------------------------
    |
    | During various string manipulations (e.g. slugification), Statamic will
    | need to make ASCII character conversions. Here you may define whether
    | or not extra characters get converted. e.g. "%" becomes "percent".
    |
    */

    'ascii_replace_extra_symbols' => false,

    /*
    |--------------------------------------------------------------------------
    | Update References on Change
    |--------------------------------------------------------------------------
    |
    | With this enabled, Statamic will attempt to update references to assets
    | and terms when moving, renaming, replacing, deleting, etc. This will
    | be queued, but it can disabled as needed for performance reasons.
    |
    */

    'update_references' => true,

    /*
    |--------------------------------------------------------------------------
    | Always Augment to Query
    |--------------------------------------------------------------------------
    |
    | By default, Statamic will augment relationship fields with max_items: 1
    | to the result of a query, for example an Entry instance. Setting this
    | to true will augment to the query builder instead of the result.
    |
    */

    'always_augment_to_query' => false,

    /*
    |--------------------------------------------------------------------------
    | Row ID handle
    |--------------------------------------------------------------------------
    |
    | Rows in Grid, Replicator, and Bard fields will be given a unique ID using
    | the "id" field. You may need your own field named "id", in which case
    | you may customize the handle of the field that Statamic will use.
    |
    */

    'row_id_handle' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Fake SQL Queries
    |--------------------------------------------------------------------------
    |
    | Enable while using the flat-file Stache driver to show fake "SQL" query
    | approximations in your database debugging tools — including Debugbar,
    | Laravel Telescope, and Ray with the ray()->showQueries() helper.
    |
    */

    'fake_sql_queries' => config('app.debug'),

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Define the default layout that will be used by views.
    |
    */

    'layout' => env('STATAMIC_LAYOUT', 'layout'),

    /*
    |--------------------------------------------------------------------------
    | View Config Allowlist
    |--------------------------------------------------------------------------
    |
    | The config keys exposed to Antlers templates through the `{{ config:* }}`
    | variable. `@default` expands to Statamic's built-in allowlist; the
    | additional keys make app config available to the front end.
    |
    | `maps.viewer` lets the `partials/map` viewer read its tile provider,
    | attribution, max zoom, and default zoom from config/maps.php.
    |
    */

    'view_config_allowlist' => [
        '@default',
        'maps.viewer',
    ],

];
