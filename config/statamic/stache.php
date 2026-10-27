<?php

declare(strict_types=1);

use App\Stache\EntriesStore;
use App\Support\ContentRoot;

/*
 * Root every content-backed Stache store at a disposable mirror of `content/`
 * while testing (UAMS-Web/uams-statamic-archive#1916).
 *
 * Tests persist real Statamic items — entries, terms, globals, collection
 * configs, asset containers — and every one of those saves writes a file under
 * the store's directory. Pointed at `content/`, that is the live git submodule:
 * a passing run cleans up after itself, but a band killed at its bound strands
 * whatever was in flight, and a stray file is boot-visible input the next
 * `statamic:stache:warm` indexes as real content (UAMS-Web/uams-statamic-archive#1845). Re-rooting here makes
 * the guarantee structural rather than per-test — no store points at the
 * submodule in the test env, so no test can dirty it, including tests nobody
 * has written yet.
 *
 * **The directory and the Stache cache must move together.** A store whose
 * directory is the mirror while its cached index still resolves items read from
 * `content/` is the destructive mixed state UAMS-Web/uams-statamic-archive#1873 documents:
 * `ExistsAsFile::writeFile()` recomputes `buildPath()` against the new
 * directory, sees it differs from the `initialPath` the item was read from, and
 * `File::delete()`s the committed file. Verified here rather than reasoned
 * about — re-rooting the directories alone, against a snapshot warmed from
 * `content/`, deleted `content/trees/collections/default/pages.yaml` (166 lines)
 * on a run whose tests all passed, because saving an entry in a *structured*
 * collection implicitly saves its tree. `test:seed-stache` therefore warms
 * through this same config, so the snapshot it builds indexes mirror paths and
 * both halves agree.
 *
 * The root itself is resolved by `App\Support\ContentRoot`, shared with
 * `config/asset_metadata.php` — the asset-metadata dual-write reaches
 * `content/` outside the Stache, and moving one surface without the other is
 * the same destructive mixed state at a larger scale. See that class for why it
 * reads `env()` directly and why config caching fails safe.
 */
$contentRoot = ContentRoot::path(env('APP_ENV'));

return [

    /*
    |--------------------------------------------------------------------------
    | File Watcher
    |--------------------------------------------------------------------------
    |
    | File changes will be noticed and data will be updated accordingly.
    | This can be disabled to reduce overhead, but you will need to
    | either update the cache manually or use the Control Panel.
    |
    */

    'watcher' => env('STATAMIC_STACHE_WATCHER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Here you may configure which Cache Store the Stache uses.
    |
    */

    'cache_store' => null,

    /*
    |--------------------------------------------------------------------------
    | Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure the stores that are used inside the Stache.
    |
    | https://statamic.dev/stache#stores
    |
    */

    // Only the keys that differ from Statamic's defaults are listed: the vendor
    // config is merged recursively, so naming a store here overrides the keys
    // given and inherits the rest (`entries` has carried only `class` for as
    // long as this file has existed, and still resolves the vendor `directory`).
    //
    // `assets`, `users`, `form-submissions` and `revisions` are deliberately
    // absent — none is rooted in `content/`, so none is this ticket's concern.
    'stores' => [
        'taxonomies' => [
            'directory' => $contentRoot.'/taxonomies',
        ],
        'terms' => [
            'directory' => $contentRoot.'/taxonomies',
        ],
        'collections' => [
            'directory' => $contentRoot.'/collections',
        ],
        'entries' => [
            'class' => EntriesStore::class,
            'directory' => $contentRoot.'/collections',
        ],
        'navigation' => [
            'directory' => $contentRoot.'/navigation',
        ],
        'collection-trees' => [
            'directory' => $contentRoot.'/trees/collections',
        ],
        'nav-trees' => [
            'directory' => $contentRoot.'/trees/navigation',
        ],
        'globals' => [
            'directory' => $contentRoot.'/globals',
        ],
        'global-variables' => [
            'directory' => $contentRoot.'/globals',
        ],
        'asset-containers' => [
            'directory' => $contentRoot.'/assets',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexes
    |--------------------------------------------------------------------------
    |
    | Here you may define any additional indexes that will be inherited
    | by each store in the Stache. You may also define indexes on a
    | per-store level by adding an "indexes" key to its config.
    |
    */

    'indexes' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Locking
    |--------------------------------------------------------------------------
    |
    | In order to prevent concurrent requests from updating the Stache at the
    | same time and wasting resources, it will be locked so that subsequent
    | requests will have to wait until the first one has been completed.
    |
    | https://statamic.dev/stache#locks
    |
    | The lock is a Symfony `FlockStore` over `storage/statamic/stache-locks`
    | (`Statamic\Stache\ServiceProvider::createFileLockStore()`) — one directory,
    | NOT tokenized per process the way the Laravel cache is in config/cache.php.
    | Under `pest --parallel` that makes it the one piece of Stache state every
    | paratest worker shares. `Stache::warm()` and `Duplicates::find()` both take
    | the flock with a blocking `acquire(true)` and hold it for their duration —
    | a warm runs ~168s per worker under parallel contention (UAMS-Web/uams-statamic-archive#1537) — while
    | `Statamic\Http\Middleware\StacheLock` has every OTHER worker's front-end
    | request wait `timeout` seconds for the same flock and then return 503. That
    | is the UAMS-Web/uams-statamic-archive#1689 `Error404PageTest` flake and the `GET /` 503 that pinned
    | `ExampleTest` to the serial tail.
    |
    | So the suite turns it off (phpunit.xml sets STATAMIC_STACHE_LOCK_ENABLED
    | to false), which both short-circuits the middleware and swaps the lock
    | factory to a `NullLockStore`. Each worker has its own cache store, so the
    | warm the lock would deduplicate is one the other workers do not want.
    | It defaults to on, so production is unchanged — and that default is pinned
    | by a test, since phpunit.xml would otherwise mask a regression in it.
    |
    | The `(bool)` cast is load-bearing, not decoration: a phpunit.xml `<env>` of
    | "false" reaches `env()` as an EMPTY STRING, which is truthy nowhere but is
    | not `false` either — and because the variable IS set, `env()` returns it
    | rather than the default. Same reason `config/maps.php` casts.
    |
    */

    'lock' => [
        'enabled' => (bool) env('STATAMIC_STACHE_LOCK_ENABLED', true),
        'timeout' => 30,
    ],

];
