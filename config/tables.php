<?php

declare(strict_types=1);

// Per-worker isolation for the table-cell sanitizer's HTMLPurifier definition
// cache (UAMS-Web/uams-statamic-archive#1853). Each paratest worker is given its own TEST_TOKEN within a band, so appending
// it to the serializer path hands every worker its own cache directory. Without
// it ~12 workers share one `storage/app/htmlpurifier` tree: they race to create
// it (the loser takes a `mkdir(): File exists` warning, promoted to
// ErrorException by the test error handler) and then race on the `.ser` files
// inside it, which HTMLPurifier writes with a non-atomic `file_put_contents()`
// and reads back through `unserialize()` behind nothing but a `file_exists()`
// check. TEST_TOKEN is unset in production and in serial runs, so the path falls
// back to the shared default there — a no-op outside a parallel test run, which
// keeps one warm definition cache in production. Mirrors the file cache's
// per-worker path in config/cache.php, including its process-id suffix (UAMS-Web/uams-statamic-archive#1985):
// the bare token is the worker index, shared by the same-numbered worker of a
// second concurrent band, and these writes are not atomic.
$purifierCachePath = storage_path('app/htmlpurifier'.(($token = env('TEST_TOKEN')) ? '/'.$token.'-'.getmypid() : ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Table Cell Sanitizer
    |--------------------------------------------------------------------------
    |
    | Settings for App\Services\Tables\TableCellSanitizer, which renders every
    | table cell through HTMLPurifier against a small inline allowlist.
    |
    */

    'cell_sanitizer' => [

        // Where HTMLPurifier serializes its compiled HTML/URI definitions.
        // Kept under storage because vendor/ is read-only in production.
        'cache_path' => $purifierCachePath,

    ],

];
