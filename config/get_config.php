<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed config key patterns
    |--------------------------------------------------------------------------
    |
    | Keys passed to {{ get_config key="..." }} must match at least one pattern
    | (Laravel Str::is). Example: crosswalk.* allows any key under config/crosswalk.php.
    |
    */

    'allowed_key_patterns' => [
        'crosswalk.*',
    ],

];
