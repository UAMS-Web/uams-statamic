<?php

declare(strict_types=1);

use Illuminate\Support\Str;

// Per-worker cache isolation for parallel testing (paratest). Each worker process
// is given its own TEST_TOKEN within a band, so appending it to the file cache path
// hands every worker its own cache directory; a concurrent cache clear in one worker then
// cannot corrupt another worker's reads (Statamic's Stache key index lives in this
// store, and a shared directory deadlocks the parallel suite with
// "FilesystemIterator ... cannot find the path"). TEST_TOKEN is unset in
// production and in serial runs, so the path falls back to the shared default
// there — a no-op outside a parallel test run.
//
// The segment ends in the worker's process id as well (UAMS-Web/uams-statamic-archive#1985). TEST_TOKEN is the
// worker INDEX, 1..N, identical across runs, so two concurrent bands in one
// checkout both resolved `data/1`, and one band's cache reset deleted the other's
// Stache index mid-test. A pid cannot be shared by two live processes, which is
// the same rule tests/Support/ThrowawayRoot.php applies to fixture roots (UAMS-Web/uams-statamic-archive#1960).
// The seed snapshot is unaffected: workers copy it from `framework/stache-seed`,
// and `test:seed-stache` still clears everything under the shared `data/`.
$fileCachePath = storage_path('framework/cache/data'.(($token = env('TEST_TOKEN')) ? '/'.$token.'-'.getmypid() : ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION', null),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => $fileCachePath,
            'lock_path' => $fileCachePath,
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'static_cache' => [
            'driver' => 'file',
            'path' => storage_path('statamic/static-urls-cache'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes (Laravel 13+)
    |--------------------------------------------------------------------------
    |
    | When false, cache unserialization is restricted to prevent deserialization
    | gadget chain attacks if APP_KEY is leaked. Set to an array of class
    | names if your application intentionally stores PHP objects in cache.
    |
    */

    'serializable_classes' => false,

];
