<?php

declare(strict_types=1);

namespace App\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Cache for scope alias lists, usable before Laravel's cache is bound.
 *
 * Currently used only by EntriesBySchemaType. Intended for any Statamic scope
 * class that implements aliases() and needs to persist alias lists across
 * requests and CLI runs. Such scopes should resolve this singleton and use
 * rememberForever(key, callback), put(key, value), and forget(key) with a
 * scope-specific cache key (e.g., EntriesBySchemaType::CACHE_KEY_ALIASES).
 *
 * When Laravel's cache is bound, delegates to it; otherwise uses a file-based
 * store under storage/framework/cache/data so alias discovery works even when
 * scope discovery runs before CacheServiceProvider.
 */
class ScopeAliasCache
{
    /**
     * Subdirectory under framework cache data for our keys.
     */
    private const string CACHE_DIR = 'entries_by_schema_type';

    /**
     * Get the path to the cache file for a given key.
     *
     * @param  string  $key  Cache key (e.g., CACHE_KEY_ALIASES)
     * @return string Absolute path to the cache file
     */
    private function path(string $key): string
    {
        $base = storage_path('framework/cache/data/'.self::CACHE_DIR);

        return $base.DIRECTORY_SEPARATOR.sha1($key);
    }

    /**
     * Ensure the cache directory exists, without a check-then-create race.
     *
     * This directory is shared by every process on the machine — unlike the
     * file cache path in `config/cache.php`, it carries no `TEST_TOKEN`
     * segment — and it sits on the boot path: the singleton is registered from
     * `bootstrap/app.php`'s `beforeBootstrapping()` hook, and this method is
     * reached whenever alias discovery runs before Laravel's cache binds, since
     * `put()` short-circuits to `Cache::put()` once it has.
     *
     * `File::ensureDirectoryExists()` does not do what its name promises under
     * concurrency: it tests `isDirectory()` and then calls Laravel's UNGUARDED
     * `mkdir()`, so two processes reaching a cold cache together both see the
     * directory absent and the loser takes a `mkdir(): File exists` warning —
     * promoted to an `ErrorException` by the test environment's error handler.
     *
     * Suppressing the warning (`makeDirectory()`'s `$force` argument) and
     * re-checking is the race-safe idiom, and the same one UAMS-Web/uams-statamic-archive#1853 settled on for
     * `TableCellSanitizer` — the loser simply observes the directory the winner
     * has just created.
     *
     * **Hardening by inspection, not a fix for an observed failure.** Written
     * while investigating UAMS-Web/uams-statamic-archive#1970's concurrent-band startup abort, which carries
     * the same `mkdir(): File exists` signature — but that abort's caller was
     * never pinned, and an attempt to attribute it here did not establish the
     * link. Do not cite this as UAMS-Web/uams-statamic-archive#1970's resolution; the race is a defect on its
     * own terms, which is the whole justification.
     */
    private function ensureDirectory(): void
    {
        $dir = storage_path('framework/cache/data/'.self::CACHE_DIR);

        if (File::isDirectory($dir)) {
            return;
        }

        // Nothing follows in this method but the stat-cache drop below, so returning early
        // and falling through end the same way. Negating this condition is a surviving,
        // equivalent mutant: a marker reaches a node's children, never the node itself.
        if (File::makeDirectory(
            $dir,
            0755,
            // The parent, storage/framework/cache/data, is Laravel's own file cache and
            // always exists, so creating parents changes nothing.
            // @pest-mutate-ignore: TrueToFalse
            true,
            true
        )) {
            return;
        }

        // A suppressed failure means either "a sibling already made it" or "it
        // genuinely cannot be made". Only the filesystem can tell those apart,
        // and PHP's stat cache still holds the "absent" answer from the check
        // above, so drop it before re-reading. Nothing in this method reads the
        // filesystem after it, so the call cannot change what the method does: its flag
        // is marked, and removing the call is a surviving, equivalent mutant no marker
        // can reach.
        // @pest-mutate-ignore: TrueToFalse
        clearstatcache(true, $dir);

        // Still absent means a real failure — a read-only or full disk. This is
        // a cache on the boot path, so it does not throw: `put()` will fail to
        // write, `rememberForever()` will recompute from the callback every
        // time, and the application still works. Throwing here would take the
        // whole boot down for a cache directory.
        unset($dir);
    }

    /**
     * Get a value from cache, or compute and store it forever.
     *
     * Uses Laravel Cache when bound, otherwise file-based store.
     *
     * @param  string  $key  Cache key
     * @param  \Closure(): array<int, string>  $callback  Callback that returns the alias list
     * @return array<int, string> Cached or computed alias list
     */
    public function rememberForever(string $key, \Closure $callback): array
    {
        if (app()->bound('cache')) {
            return Cache::rememberForever($key, $callback);
        }

        $path = $this->path($key);

        if (File::exists($path)) {
            $contents = File::get($path);
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                unset($contents);

                /** @var array<int, string> $decoded */
                return $decoded;
            }

            unset($contents, $decoded);
        }

        $value = $callback();
        $this->put($key, $value);
        unset($path);

        return $value;
    }

    /**
     * Store a value in cache.
     *
     * Uses Laravel Cache when bound, otherwise file-based store.
     *
     * @param  string  $key  Cache key
     * @param  array<int, string>  $value  Alias list to store
     */
    public function put(string $key, array $value): void
    {
        if (app()->bound('cache')) {
            Cache::put($key, $value, now()->addYears(10));
            unset($value);

            return;
        }

        $this->ensureDirectory();
        $path = $this->path($key);
        $encoded = json_encode($value);
        if ($encoded === false) {
            $encoded = '[]';
        }

        File::put($path, $encoded);
        unset(
            $path,
            $encoded,
            $value
        );
    }

    /**
     * Remove a value from cache.
     *
     * Uses Laravel Cache when bound, otherwise file-based store.
     *
     * @param  string  $key  Cache key
     */
    public function forget(string $key): void
    {
        if (app()->bound('cache')) {
            Cache::forget($key);

            return;
        }

        $path = $this->path($key);
        if (File::exists($path)) {
            File::delete($path);
        }

        unset($path);
    }
}
