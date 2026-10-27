<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ContentRoot;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection as EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\CollectionEntriesStore;
use Statamic\Stache\Stores\EntriesStore;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;

/**
 * Base test case for a test that persists a **collection tree** while still
 * reading the shared, real content for everything else (UAMS-Web/uams-statamic-archive#1873).
 *
 * **What this base is for changed in UAMS-Web/uams-statamic-archive#1916, and it is worth being precise about.**
 * It was written because the collection-trees store pointed at the live `content/`
 * submodule, so a test that built a structure wrote
 * `content/trees/collections/<site>/<handle>.yaml` into tracked source and removed
 * it in an `afterEach` that only runs when the PHP process survives to it. A band
 * killed at its bound stranded the file, the submodule read dirty for every later
 * run, and the stray was boot-visible input: `statamic:stache:warm` indexes it and
 * memoizes a collection-structure entry against the real collection, the state
 * that produced UAMS-Web/uams-statamic-archive#1845.
 *
 * UAMS-Web/uams-statamic-archive#1916 made that half structural — under test the store roots at a disposable
 * mirror of `content/`, so no tree write can reach the submodule whether or not a
 * test uses this base. What remains is the other half, which this base is still
 * the only thing providing: the mirror is **shared by every paratest worker**, so
 * a test that authors a tree and asserts against it needs a root of its own, or a
 * sibling worker's fixture is in its results. That is why this base survives
 * rather than being retired with the ticket that motivated it.
 *
 * The write is not always authored by the test. `Entry::delete()` on an entry in a
 * *structured* collection removes it from the tree and saves the tree, so any test
 * that creates and tears down such an entry writes a tree too.
 *
 * ## The invariant this rests on
 *
 * A re-rooted store has exactly two safe states: **directory and cached view both
 * real** (no isolation), or **both throwaway** (isolated). Every mixed state is
 * destructive, and not merely misdirected:
 *
 * ```php
 * // Statamic\Data\ExistsAsFile::writeFile()
 * $path = $path ?? $this->buildPath();     // recomputed LIVE from the store directory
 * $initial = $this->initialPath();          // where the item was READ from
 * if ($initial && $path !== $initial) {
 *     File::delete($this->initialPath());   // the committed file is REMOVED
 * }
 * ```
 *
 * So a tree loaded from the real directory and then saved under a re-rooted store
 * is *moved*, not copied — `File::delete()` takes the committed YAML with it. This
 * is not hypothetical: an earlier iteration of this ticket, which re-rooted the
 * directory while leaving the shared path index real, deleted
 * `content/trees/collections/default/recognition_lists.yaml` and
 * `content/trees/collections/science_reports/post_categories.yaml` during a run.
 *
 * `Entry::path()` has an `initialPath ?? buildPath()` fallback that looks like it
 * prevents this. It does not: `writeFile()` calls `buildPath()` directly and never
 * consults `path()`. `CollectionTree`, `Term`, `GlobalSet`, `Variables`,
 * `AssetContainer` and `Nav` have no such fallback at all.
 *
 * {@see setUp()} therefore moves the directory **and** drops the cached view, so
 * nothing this test resolves can carry an `initialPath` inside the shared root.
 *
 * ## What that costs, and why it is affordable here
 *
 * With the path index dropped, the store sees an empty directory, so real
 * committed trees are invisible for the duration of the test. That is exactly why
 * this base is scoped to *collection trees* rather than to content generally: the
 * tests on it **author the tree they assert against**. `CollectionStructure::in()`
 * makes a fresh tree when the store has none (`! $tree && $this->existsIn($site)`),
 * so an absent tree is a blank one rather than a null, and the implicit save from
 * `Entry::delete()` lands in the throwaway directory.
 *
 * Everything else — collections, entries, taxonomies, terms, globals — is
 * untouched, so the real content these tests exist to exercise still resolves.
 *
 * The sibling {@see StacheIsolatedTestCase} isolates every store *and* the Stache
 * cache; use it when a test needs no real content at all. This base is the narrow
 * case: real content, isolated trees.
 */
abstract class CollectionTreeIsolatedTestCase extends TestCase
{
    /**
     * Stache store holding collection structure trees.
     */
    private const string TREE_STORE = 'collection-trees';

    /**
     * Throwaway root standing in for `content/trees/collections/`.
     */
    protected string $throwawayTreeDirectory;

    /**
     * The cached `uri` index of each structured collection as the committed trees
     * built it, keyed by Stache cache key, for {@see tearDown()} to put back.
     *
     * @var array<string, mixed>
     */
    private array $committedUriIndexes = [];

    protected function setUp(): void
    {
        parent::setUp();

        // With the watcher on, `Store::handleFileChanges()` re-scans and re-indexes
        // behind this base's back, which would put the store straight back into the
        // mixed state the class docblock calls destructive. The watcher is off under
        // `APP_ENV=testing` (`statamic.stache.watcher` is `auto` → `app()->isLocal()`);
        // say so out loud rather than assume it.
        if (Stache::isWatcherEnabled()) {
            static::fail(
                'The Stache watcher is enabled, so the collection-trees store would be re-scanned '
                .'while re-rooted. Run this suite with APP_ENV=testing (phpunit.xml), or set '
                .'STATAMIC_STACHE_WATCHER=false.'
            );
        }

        // Per-process, matching the throwaway roots in StacheIsolatedTestCase:
        // all ~12 paratest workers would otherwise share one directory, and the
        // cleanup below would delete a sibling's fixtures mid-test (the UAMS-Web/uams-statamic-archive#1136
        // race) — as would a second serial run in this worktree (UAMS-Web/uams-statamic-archive#1957), or a
        // second concurrent band, whose worker indexes repeat across runs
        // (UAMS-Web/uams-statamic-archive#1960). {@see ThrowawayRoot} ends every segment in the pid, closing
        // all three. Not the shape of config/cache.php's path, which keeps the
        // bare token because production reads it.
        //
        // The helper is called inside the `storage_path()` call
        // rather than lifted to a local first, because
        // `scripts/check-parallel-safe-tests.php` (rule B) reads the *call's own
        // tokens* to decide whether a `framework/testing` path is per-run.
        // Assigning to `$segment` on a previous line hides it from the guard, and
        // the guard reds the `isolation` CI job.
        $this->throwawayTreeDirectory = storage_path(
            'framework/testing/collection-trees/'.ThrowawayRoot::segment()
        );

        // Start clean even when a prior run died before its tearDown, so a stale
        // fixture tree can never be read back as this test's own.
        File::deleteDirectory($this->throwawayTreeDirectory);

        $store = $this->treeStore();

        // Assert the store is where this base thinks it is before moving it. If it
        // ever stops resolving inside the shared root, re-rooting it would isolate
        // writes that threatened nothing while the real ones carried on — and a
        // silent no-op is the one outcome worse than a loud failure here.
        //
        // The shared root itself moved in UAMS-Web/uams-statamic-archive#1916: under test it is the disposable
        // `content/` mirror rather than the submodule. That is why this compares
        // against `ContentRoot` instead of `base_path('content')` — the *reason*
        // for the check is unchanged, but hard-coding the submodule made it fire
        // on every test using this base the moment the mirror landed.
        $directory = str_replace('\\', '/', (string) $store->directory());
        $sharedRoot = str_replace('\\', '/', ContentRoot::path(Config::string('app.env')));

        if (! str_starts_with($directory, rtrim($sharedRoot, '/').'/')) {
            static::fail(
                "The {$this->treeStoreKey()} store is rooted at \"{$directory}\", which is not inside "
                ."the shared content root \"{$sharedRoot}\" — this base would isolate nothing."
            );
        }

        $this->warmStructuredUriIndexes();

        $store->directory($this->throwawayTreeDirectory);

        // Drop the cached path index so the store's view matches its new directory.
        // This is the load-bearing half: leaving the real index in place is the mixed
        // state, where `getItem()` returns a tree whose `initialPath` is the committed
        // file and the next save deletes it. Erase-only, so it is self-healing —
        // `Store::paths()` treats an absent value as a miss and re-traverses.
        $store->clearCachedPaths();

        // `CollectionStructure::in()` is `Blink::once("collection-structure-tree-…")`,
        // so a tree resolved during the boot in `parent::setUp()` is memoized WITH its
        // real `initialPath` and would be handed back — and saved — after the re-root.
        // Dropping the memo is what makes the clear above actually reach the caller.
        Blink::flush();

        // Assert the postcondition rather than trusting the two calls above.
        //
        // This lives here, not in a test, because a test cannot reach the state that
        // makes it matter: the danger only exists when the shared path index was WARM
        // when `setUp()` ran, and by the time any test body executes, `setUp()` has
        // already been and gone. Verified the hard way — deleting both calls above and
        // re-running `CollectionTreeIsolationTest` left it GREEN, because that file run
        // alone never warmed the index. A guard that cannot fail is not a guard, so the
        // check belongs at the point the precondition is live.
        //
        // Cheap: one cache read per test, against a key the clear just forgot.
        $cached = Stache::cacheStore()->get("stache::indexes::{$this->treeStoreKey()}::path");

        if (! empty($cached)) {
            static::fail(
                'The collection-trees path index survived isolation, so a tree resolved here would '
                .'still carry its committed `initialPath` — and `ExistsAsFile::writeFile()` deletes '
                .'the initial path when it differs from the recomputed one, which removes the real '
                .'file from content/. Restore the `clearCachedPaths()` call in setUp().'
            );
        }
    }

    /**
     * Build the `uri` index of every structured collection while the committed trees
     * are still visible, so this base is never the process that first builds it (UAMS-Web/uams-statamic-archive#3195).
     *
     * The index is persisted to the Stache cache that every test in the run shares,
     * and a fresh seed leaves it unbuilt. Built after the re-root, it records URIs from
     * trees made in entry-index order: `CollectionStructure::validateTree()` fills an
     * absent tree with the collection's entries, and the first becomes the root at `/`.
     * Every later test then reads those URIs from the cache, whichever base it runs on.
     * Loading an index that is already cached is one cache read.
     */
    private function warmStructuredUriIndexes(): void
    {
        // Check the trees are still the committed ones, since an index built after the
        // re-root is the defect this exists to prevent
        $treeDirectory = str_replace('\\', '/', (string) $this->treeStore()->directory());

        if (str_starts_with($treeDirectory, str_replace('\\', '/', $this->throwawayTreeDirectory))) {
            static::fail('The uri indexes must be loaded before the collection-trees store is re-rooted.');
        }

        /** @var EntriesStore $entries */
        $entries = Stache::store('entries');

        /** @var \Illuminate\Support\Collection<int, EntryCollection> $collections */
        $collections = Collection::all();

        // Load each structured collection's index, which builds and caches it when absent
        foreach ($collections as $collection) {
            if (! $collection->hasStructure()) {
                continue;
            }

            $handle = $collection->handle();
            $handle = \is_string($handle) ? $handle : '';

            /** @var CollectionEntriesStore $store */
            $store = $entries->store($handle);
            $index = $store->index('uri');

            // Fail rather than re-root with the index unbuilt, which is the state that
            // writes index-order URIs into the shared cache
            if (! $index instanceof Index || ! $index->isCached()) {
                static::fail(
                    "The {$handle} uri index is not in the Stache cache after loading, so "
                    .'the first read after the re-root would build it from trees this base hides and '
                    .'cache the result for the whole run.'
                );
            }

            // Keep the committed URIs, since a save after the re-root rewrites them
            $cacheKey = mustBeString($index->cacheKey(), "the {$handle} uri index cache key");
            $this->committedUriIndexes[$cacheKey] = Stache::cacheStore()->get($cacheKey);
        }
    }

    /**
     * Put back the `uri` index of every structured collection as the committed trees
     * built it (UAMS-Web/uams-statamic-archive#3227).
     *
     * A save inside this base writes the URI computed from the hidden tree through
     * `Index::updateItem()` into the Stache cache, which every later test in the
     * process reads: an existing `pages` entry saved here is cached at `/`. Restoring
     * the values {@see warmStructuredUriIndexes()} kept also drops the row of an entry
     * the test created, since the committed index never had one.
     */
    private function restoreCommittedUriIndexes(): void
    {
        // Write each kept index back over whatever the test left in the cache
        foreach ($this->committedUriIndexes as $cacheKey => $items) {
            Stache::cacheStore()->forever($cacheKey, $items);
        }

        $this->committedUriIndexes = [];
    }

    protected function tearDown(): void
    {
        // Leave no throwaway-derived index behind for the next test in this process.
        $this->treeStore()->clearCachedPaths();

        // Leave the committed URIs for the next test, whatever this one saved
        $this->restoreCommittedUriIndexes();

        File::deleteDirectory($this->throwawayTreeDirectory);

        parent::tearDown();
    }

    /**
     * The collection-trees store key, named once so the guard message and the
     * lookup cannot drift apart.
     */
    private function treeStoreKey(): string
    {
        return self::TREE_STORE;
    }

    /**
     * The collection-trees store.
     */
    private function treeStore(): Store
    {
        /** @var Store $store */
        $store = Stache::stores()->get(self::TREE_STORE);

        return $store;
    }
}
