<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ContentRoot;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Spatie\Blink\Blink as SpatieBlink;
use Statamic\Facades\Blink;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\BasicStore;
use Statamic\Stache\Stores\Keys;
use Tests\TestCase;

/**
 * Base test case for a test that saves **entries or terms** while still reading the
 * real content for everything (UAMS-Web/uams-statamic#556, decided on #538).
 *
 * Under test every Stache store reads the disposable content mirror
 * (`storage/framework/testing/content-mirror`, {@see ContentRoot}), and every
 * paratest worker reads the same one. A test on the plain {@see TestCase} that saves
 * an entry or a term writes it there, where a sibling worker's cold Stache warm or a
 * listing that enumerates the collection reads it as committed content. This base
 * gives each process a copy of the mirror's `collections/` and `taxonomies/` and
 * points the `entries` and `terms` stores at the copy, so a save lands where only
 * this process reads.
 *
 * ## The invariant it keeps
 *
 * Moving a store's directory is destructive unless nothing the test resolves still
 * carries a path in the old one, which {@see CollectionTreeIsolatedTestCase} explains
 * at length: `ExistsAsFile::writeFile()` deletes an item's `initialPath` when it
 * differs from the path recomputed from the store directory. Two caches carry those
 * paths where a save reads them: the path and key indexes, and the cached items
 * themselves, which serialize their `initialPath`. So {@see setUp()} rewrites every
 * entry and term path index and key index into the copy, and forgets every cached
 * entry and term, which then load from the copy's files when read. It asserts that
 * no path index still names the mirror before the test body runs. The duplicates
 * index (`stache::duplicates`) keeps mirror paths too, and is left alone: it only
 * reports duplicates and is never a save path.
 *
 * Nothing is moved back in `tearDown()`. The application, and with it the Stache,
 * its store directories and the overlay, is rebuilt for every test, so a restore
 * would only open a window in which a teardown callback saves against the shared
 * mirror with indexes that still hold copy paths.
 *
 * ## Why the Stache cache is overlaid rather than replaced
 *
 * Those rewrites, and every index row a save writes, go through
 * {@see OverlayCacheStore}: reads fall through to the worker's warm Stache cache and
 * writes stay in memory for one test. {@see StacheIsolatedTestCase} replaces the cache
 * outright, which costs nothing there because its stores are empty; here the stores
 * hold all of `content/`, and rebuilding their indexes per test would cost a cold
 * warm. Indexes other than the paths hold content values, not paths, and the copy is
 * byte-identical to the mirror when a test starts, so they stay correct.
 *
 * ## The copy is kept current, not rebuilt
 *
 * {@see setUp()} brings the copy back in line with the mirror before each test:
 * files the last test added are removed, and files it changed or removed are copied
 * again, judged by size and modification time. The first test in a process copies
 * everything. The copy is removed when the process exits; a killed process leaves it.
 *
 * ## What it does not isolate
 *
 * Only the `entries` and `terms` stores move. An entry saved in a structured
 * collection also saves that collection's tree, into the shared mirror; trees are
 * {@see CollectionTreeIsolatedTestCase}'s, and a test that needs both extends
 * {@see EntriesTermsAndTreesIsolatedTestCase}. Globals, navigation, asset containers and form submissions
 * stay on the shared mirror too, and listeners an `Entry::save()` fires can still
 * write elsewhere, which is why the proof saves quietly. Their cache writes go
 * through the overlay and are discarded, so one of them saved under this base
 * changes its mirror file while this worker's cache keeps the earlier row: save
 * them on a base that isolates them, not this one.
 */
abstract class EntriesAndTermsIsolatedTestCase extends TestCase
{
    /**
     * The Stache stores this base moves, keyed by the mirror directory each reads.
     *
     * @var array<string, string>
     */
    private const array ISOLATED_STORES = [
        'entries' => 'collections',
        'terms' => 'taxonomies',
    ];

    /**
     * The cache store name the overlay is registered under.
     */
    private const string OVERLAY_STORE = 'stache_entries_terms_overlay';

    /**
     * Named Blink stores that memoize entries, and so hold items resolved from the
     * shared mirror before the stores moved. The same list
     * {@see StacheIsolatedTestCase} flushes.
     *
     * @var array<int, string>
     */
    private const array ENTRY_DERIVED_BLINK_STORES = [
        'entry-uris',
        'structure-uris',
        'structure-entries',
        'collection-mounts',
    ];

    /**
     * Whether this process has registered the removal of its copy.
     */
    private static bool $copyCleanupRegistered = false;

    /**
     * This process's copy of the mirror, standing in for it for entries and terms.
     */
    protected string $isolatedContentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // A watcher re-scans a store behind this base's back, which would put it back
        // into the mixed state the class docblock calls destructive
        if (Stache::isWatcherEnabled()) {
            static::fail(
                'The Stache watcher is enabled, so the entries and terms stores would be re-scanned '
                .'while re-rooted. Run this suite with APP_ENV=testing (phpunit.xml), or set '
                .'STATAMIC_STACHE_WATCHER=false.'
            );
        }

        // Per process, so no two workers or runs share a copy. Called inside the
        // storage_path() call because scripts/check-parallel-safe-tests.php (rule B)
        // reads the call's own tokens to confirm a framework/testing path is per run.
        $this->isolatedContentRoot = storage_path(
            'framework/testing/entries-terms/'.ThrowawayRoot::segment()
        );

        $sharedRoot = $this->normalize(ContentRoot::path(Config::string('app.env')));

        $this->syncCopy($sharedRoot);
        $this->registerCopyCleanup();
        $this->installOverlayCache();

        foreach (self::ISOLATED_STORES as $storeKey => $subdirectory) {
            $this->isolateStore($storeKey, $sharedRoot.'/'.$subdirectory, $this->normalize($this->isolatedContentRoot).'/'.$subdirectory);
        }

        $this->flushEntryMemos();
    }

    /**
     * Bring this process's copy of `collections/` and `taxonomies/` in line with
     * the shared mirror.
     *
     * Removes what the copy has and the mirror does not, then copies every file
     * whose size or modification time differs, keeping the mirror's modification
     * time so the next comparison can skip it.
     *
     * @param  string  $sharedRoot  The shared mirror, normalized to forward slashes.
     */
    private function syncCopy(string $sharedRoot): void
    {
        $copyRoot = $this->normalize($this->isolatedContentRoot);

        foreach (self::ISOLATED_STORES as $subdirectory) {
            $source = $sharedRoot.'/'.$subdirectory;
            $target = $copyRoot.'/'.$subdirectory;

            // Fail rather than isolate against nothing, which would hide every real entry
            if (! is_dir($source)) {
                static::fail("The shared content mirror has no {$subdirectory}/ directory at \"{$source}\" to copy.");
            }

            $sourceFiles = $this->fileStamps($source);
            $targetFiles = is_dir($target) ? $this->fileStamps($target) : [];

            // Remove what the last test added
            foreach (array_diff_key($targetFiles, $sourceFiles) as $relative => $stamp) {
                File::delete($target.'/'.$relative);
            }

            // Restore what the last test changed or removed
            foreach ($sourceFiles as $relative => $stamp) {
                if (($targetFiles[$relative] ?? null) === $stamp) {
                    continue;
                }

                $destination = $target.'/'.$relative;
                File::ensureDirectoryExists(\dirname($destination));
                // A file a sibling worker removed mid-scan must fail here, not leave a stale copy
                if (! File::copy($source.'/'.$relative, $destination)) {
                    static::fail("Could not copy \"{$source}/{$relative}\" into this process's copy at \"{$destination}\".");
                }

                touch($destination, (int) explode(':', $stamp)[1]);
            }
        }
    }

    /**
     * Each file under a directory, as its relative path mapped to `size:mtime`.
     *
     * @param  string  $directory  Absolute, normalized to forward slashes.
     * @return array<string, string>
     */
    private function fileStamps(string $directory): array
    {
        $stamps = [];

        // Key on the forward-slashed relative path so both trees compare alike
        foreach (File::allFiles($directory, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $stamps[$relative] = $file->getSize().':'.$file->getMTime();
        }

        return $stamps;
    }

    /**
     * Remove this process's copy when the process exits, once per process.
     */
    private function registerCopyCleanup(): void
    {
        if (self::$copyCleanupRegistered) {
            return;
        }

        self::$copyCleanupRegistered = true;
        $directory = $this->isolatedContentRoot;

        register_shutdown_function(static function () use ($directory): void {
            (new Filesystem)->deleteDirectory($directory);
        });
    }

    /**
     * Point the Stache at an {@see OverlayCacheStore} over the cache it uses now.
     */
    private function installOverlayCache(): void
    {
        $current = Config::get('statamic.stache.cache_store');
        $fallback = Cache::store(\is_string($current) ? $current : null)->getStore();

        Cache::extend('stache_overlay', fn (): Repository => Cache::repository(new OverlayCacheStore($fallback)));
        Config::set('cache.stores.'.self::OVERLAY_STORE, ['driver' => 'stache_overlay']);
        Cache::forgetDriver(self::OVERLAY_STORE);
        Config::set('statamic.stache.cache_store', self::OVERLAY_STORE);
    }

    /**
     * Move one aggregate store into the copy, with every cached path and item that
     * named the shared mirror rewritten or forgotten first.
     *
     * @param  string  $storeKey  The aggregate store, `entries` or `terms`.
     * @param  string  $sharedDirectory  Where it reads now, normalized.
     * @param  string  $copyDirectory  Where it reads after this, normalized.
     */
    private function isolateStore(string $storeKey, string $sharedDirectory, string $copyDirectory): void
    {
        $store = $this->aggregateStore($storeKey);
        $directory = rtrim($this->normalize((string) $store->directory()), '/');

        // Refuse to move a store that is not where this base thinks it is, which
        // would isolate writes that threatened nothing while the real ones carried on
        if ($directory !== $sharedDirectory) {
            static::fail("The {$storeKey} store is rooted at \"{$directory}\", not \"{$sharedDirectory}\", so this base would isolate nothing.");
        }

        foreach ($this->childStores($store) as $child) {
            $this->rewriteChildStore($child, $sharedDirectory, $copyDirectory);
        }

        // The key index is the aggregate's, one for all its child stores, and records
        // each key's path too; a stale one reads a rebuilt path as a duplicate (#329)
        $keys = mustBe(Keys::class, $store->keys(), "the {$storeKey} key index");
        $keys->load();
        $rewritten = [];

        foreach (mustBeArray($keys->all(), "the {$storeKey} key index") as $key => $path) {
            $rewritten[$key] = $this->rewritePath(mustBeString($path, "the {$storeKey} key index path for {$key}"), $store->key(), $sharedDirectory, $copyDirectory);
        }

        $keys->setKeys($rewritten);
        $keys->cache();

        $store->directory($copyDirectory);
        $store->resetMemoizedState();

        // Assert the postcondition rather than trusting the rewrite: a path left in
        // the shared mirror is the one a later save would delete from it
        foreach ($this->childStores($store) as $child) {
            foreach ($this->cachedPaths($child) as $key => $path) {
                if (! str_starts_with($this->normalize($path), $copyDirectory.'/')) {
                    static::fail("The {$child->key()} path index still names \"{$path}\" for {$key} after isolation, outside \"{$copyDirectory}\".");
                }
            }
        }
    }

    /**
     * Rewrite one child store's cached path index into the copy, and forget each
     * cached item so it loads from the copy's file when read.
     *
     * @param  BasicStore  $child  An entries or terms child store.
     * @param  string  $sharedDirectory  The aggregate's shared directory, normalized.
     * @param  string  $copyDirectory  The aggregate's copy directory, normalized.
     */
    private function rewriteChildStore(BasicStore $child, string $sharedDirectory, string $copyDirectory): void
    {
        $paths = $this->cachedPaths($child);

        // Forget every cached item, whose serialized initialPath names the shared mirror
        foreach (array_keys($paths) as $key) {
            $child->forgetItem((string) $key);
        }

        if ($paths !== []) {
            Stache::cacheStore()->forever("stache::indexes::{$child->key()}::path", array_map(
                fn (string $path): string => $this->rewritePath($path, $child->key(), $sharedDirectory, $copyDirectory),
                $paths,
            ));
        }

        $child->resetMemoizedState();
    }

    /**
     * One cached path, moved from the shared directory into the copy.
     *
     * @param  string  $path  The cached path.
     * @param  string  $storeKey  The store that cached it, for the failure message.
     * @param  string  $sharedDirectory  The shared directory, normalized.
     * @param  string  $copyDirectory  The copy directory, normalized.
     */
    private function rewritePath(string $path, string $storeKey, string $sharedDirectory, string $copyDirectory): string
    {
        $normalized = $this->normalize($path);

        // Fail on a path in neither tree, which this base cannot account for
        if (! str_starts_with($normalized, $sharedDirectory.'/')) {
            static::fail("The {$storeKey} store caches \"{$path}\", outside \"{$sharedDirectory}\", which this base cannot rewrite.");
        }

        return $copyDirectory.substr($normalized, \strlen($sharedDirectory));
    }

    /**
     * A child store's cached path index, read straight from the Stache cache.
     *
     * @param  BasicStore  $child  An entries or terms child store.
     * @return array<string, string>
     */
    private function cachedPaths(BasicStore $child): array
    {
        $paths = Stache::cacheStore()->get("stache::indexes::{$child->key()}::path");
        $typed = [];

        // An absent index reads as empty; a present one must map keys to paths
        foreach (\is_array($paths) ? $paths : [] as $key => $path) {
            $typed[(string) $key] = mustBeString($path, "the {$child->key()} cached path for {$key}");
        }

        return $typed;
    }

    /**
     * The child stores an aggregate has discovered, typed.
     *
     * @param  AggregateStore  $store  The entries or terms store.
     * @return list<BasicStore>
     */
    private function childStores(AggregateStore $store): array
    {
        $children = [];

        // Each child is a basic store; anything else is a Statamic change to look at
        foreach (mustBe(Collection::class, $store->discoverStores(), "the {$store->key()} child stores") as $child) {
            $children[] = mustBe(BasicStore::class, $child, "a {$store->key()} child store");
        }

        return $children;
    }

    /**
     * Drop every memo of an entry resolved before the stores moved.
     */
    private function flushEntryMemos(): void
    {
        Blink::flush();

        // The facade's flush reaches only the default store, so flush the named ones
        foreach (self::ENTRY_DERIVED_BLINK_STORES as $name) {
            $blinkStore = Blink::store($name);

            if ($blinkStore instanceof SpatieBlink) {
                $blinkStore->flush();
            }
        }
    }

    /**
     * One of the two aggregate stores this base moves.
     *
     * @param  string  $storeKey  `entries` or `terms`.
     */
    private function aggregateStore(string $storeKey): AggregateStore
    {
        return mustBe(AggregateStore::class, Stache::store($storeKey), "the {$storeKey} store");
    }

    /**
     * A path with forward slashes and no trailing separator.
     *
     * @param  string  $path  Absolute.
     */
    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
