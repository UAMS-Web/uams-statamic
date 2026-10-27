<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blink;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Keys;
use Statamic\Stache\Stores\Store;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Tests\Concerns\IsolatesStacheCache;
use Tests\TestCase;

/**
 * Base case for the hermetic schema-JSON-LD person tests (UAMS-Web/uams-statamic-archive#1313).
 *
 * Repoints every Stache store at a throwaway per-worker directory (via
 * {@see PreventsSavingStacheItemsToDisk}) and seeds it with the committed
 * fixture content under {@see self::fixtureContentDirectory()} — the copied
 * `schema_objects` template machinery plus the synthetic Star-Trek people,
 * taxonomies, and global. The real production `content/` submodule is never
 * read, so these tests run with it absent.
 *
 * Unlike {@see StacheIsolatedTestCase} this deliberately does NOT repoint the
 * blueprint directory: the first-party `person` / `schema_object` / taxonomy
 * blueprints in `resources/blueprints/` are load-bearing for the render and are
 * committed (present without `content/`), so we keep reading the real ones.
 *
 * The fixture graph is copied into the throwaway Stache root, so any save a
 * render might trigger lands in disposable storage, never on the committed
 * fixtures or the real `content/`.
 *
 * The Stache *cache* is repointed too, at a per-test in-memory store — see
 * {@see IsolatesStacheCache}. Without it the fixture index, and the wholesale
 * `Stache::cacheStore()->flush()` below, both landed on the worker's shared
 * Stache cache and took the seeded real-`content/` index with them (UAMS-Web/uams-statamic-archive#1747).
 */
abstract class SchemaFixtureStacheTestCase extends TestCase
{
    use IsolatesStacheCache;
    use PreventsSavingStacheItemsToDisk;

    /**
     * Throwaway Stache root. Must end in `/dev-null` for the trait's path math
     * (it splits on that segment to re-root each store under this directory).
     */
    protected string $fakeStacheDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Give the Stache its own in-memory cache for the duration of this test,
        // so the fixture graph indexed below is never written into — and never
        // erases — the worker's shared Stache cache (UAMS-Web/uams-statamic-archive#1747). Must come before
        // preventSavingStacheItemsToDisk() and the flush/clear/warm sequence, all
        // of which index against the shared store otherwise.
        $this->isolateStacheCache();

        // Per-run isolation: {@see ThrowawayRoot} resolves the segment that keeps
        // the destructive cleanup below off another run's fixtures — the paratest
        // worker's TEST_TOKEN, without which all ~12 workers race one Stache
        // directory (UAMS-Web/uams-statamic-archive#1136), or a process-unique `seq-<pid>` in a serial run, where
        // the fixed `seq` used to put two concurrent runs on one path (UAMS-Web/uams-statamic-archive#1957).
        //
        // Called DIRECTLY inside `storage_path()` rather than through an
        // intermediate variable, so the helper's name sits inside the call where
        // the parallel-safety guard (scripts/check-parallel-safe-tests.php) can see
        // it and confirm the path is per-run. Keep it inline — hoisting it back to
        // a variable hides it from the guard and re-trips Rule B.
        $this->fakeStacheDirectory = storage_path(
            'framework/testing/schema-stache/'.ThrowawayRoot::segment().'/dev-null'
        );

        $parent = \dirname($this->fakeStacheDirectory);
        if (! is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        // Start clean even if a prior run crashed before its tearDown, so a stale
        // fixture can never bleed into this test's Stache index. The upstream
        // trait recreates the directory only when the delete removed it, so a
        // directory that outlives its own delete on Windows no longer raises
        // `mkdir(): File exists` here (UAMS-Web/uams-statamic-archive#1696, statamic/cms#15538).
        $this->deleteFakeStacheDirectory();

        // Re-root every Stache store under the throwaway directory.
        $this->preventSavingStacheItemsToDisk();

        // Seed the throwaway content root with the committed fixture graph.
        //
        // The destination is derived from the re-rooted `entries` store rather
        // than written out, because Statamic rebuilds each store's directory as
        // `<fakeStacheDirectory>/<that store's path relative to the app base>`.
        // That tail was `content/…` while the stores were rooted in the submodule
        // and is `storage/framework/testing/content-mirror/…` since UAMS-Web/uams-statamic-archive#1916, so a
        // hard-coded `/content` left every store pointed at an empty directory —
        // and a JSON-LD test with no content renders no script tag, which reads as
        // a schema regression rather than as a fixture that never loaded.
        // `entries` lives at `<root>/collections`, so its parent is the root.
        //
        // Narrowed by control flow rather than annotation: the facade types
        // `store()` as mixed, and a store that failed to resolve would otherwise
        // derive a destination from an empty string.
        $entriesStore = Stache::store('entries');

        if (! $entriesStore instanceof Store) {
            static::fail('The Stache `entries` store did not resolve, so the fixture destination cannot be derived.');
        }

        $throwawayContentRoot = \dirname((string) $entriesStore->directory());

        // Assert the derivation landed inside the throwaway tree before copying
        // into it. Were upstream ever to stop re-rooting, this would otherwise
        // copy fixture content straight into whatever the stores really point at.
        if (! str_starts_with(
            str_replace('\\', '/', $throwawayContentRoot),
            rtrim(str_replace('\\', '/', $this->fakeStacheDirectory), '/').'/'
        )) {
            static::fail(sprintf(
                'The entries store re-rooted to "%s", which is not inside the throwaway directory '
                .'"%s" — the fixture graph would be copied over real content.',
                $throwawayContentRoot,
                $this->fakeStacheDirectory
            ));
        }

        File::copyDirectory(
            self::fixtureContentDirectory(),
            $throwawayContentRoot
        );

        // Rebuild the Stache index from the freshly-copied fixture content on disk.
        //
        // Nuke the persisted Stache cache (paths, keys, and every index) first, so no
        // index survives to let a fixture collection or entry collide with its real
        // namesake on warm. Since {@see isolateStacheCache()} above, that store is
        // this test's own in-memory one rather than the worker's shared file cache —
        // the flush is scoped to this test and the seeded real-`content/` index it
        // used to destroy is left intact (UAMS-Web/uams-statamic-archive#1747). `Stache::clear()` then forgets the
        // boot-time in-memory index and dispatches the cleared event.
        Stache::cacheStore()->flush();

        Stache::clear();

        // Drop every per-process Blink memo BEFORE re-warming. The application boot
        // (parent::setUp) warms Blink against the pre-seed Stache — the real
        // `content/`, or empty when it is absent. Two memo classes bite:
        //
        //  - The `collection-handles` list + repository cache behind
        //    `Collection::handles()`/`findByHandle()`, and the `entry-to-collection-map`
        //    behind `Entry::find()`/`where('id', …)`, plus the once-per-process
        //    multisite site hydration (UAMS-Web/uams-statamic-archive#1610). Left stale, the entries warm iterates
        //    the *real* collection handles — none of which resolve against the
        //    re-rooted collections store — so `CollectionEntriesStore::collection()`
        //    returns null (`sites() on null`). This surfaces whenever `content/` is
        //    present (the CI full suite), never in the content-absent guarantee run.
        //
        //  - The Stache stores cache their path lists in Blink too, so the flush must
        //    precede the warm below: flushing AFTER warming would discard the freshly
        //    indexed fixture paths. Warming after the flush repopulates every memo
        //    from the re-rooted fixture Stache.
        Blink::flush();

        // Warm config/tree stores before their dependent aggregate stores, resetting
        // each store's in-memory keys index just before it warms.
        //
        // `Keys::clear()` (called by `Stache::clear()`) only forgets the *persisted*
        // keys — it leaves the store object's in-memory `$this->keys` array populated
        // with the boot warm's real-`content/` ids. Because the fixture graph
        // deliberately reuses the real `schema_object` ids (so the asserted canonical
        // `@id`s stay stable), warming a fixture entry then calls `Keys::add()` with a
        // key already mapped to the real path — a different path — which throws
        // `DuplicateKeyException`; `Store::paths()` silently rejects it, so the
        // structured `schema_objects` entries never index and the render composes an
        // empty graph. `setKeys([])` after the cache flush truly empties the in-memory
        // index so the fixture ids add cleanly. Aggregate stores (`entries`, `terms`,
        // …) hold one child store per collection/taxonomy, each with its own keys
        // index, so we recurse — and only after the parent config store has warmed, so
        // the children resolve. A content-absent run has no real ids to collide with,
        // making this a no-op there.
        $warmStore = function (string $storeKey): void {
            if (! Stache::stores()->has($storeKey)) {
                return;
            }

            $store = Stache::store($storeKey);

            if (! $store instanceof Store) {
                return;
            }

            $this->prepareStoreForWarmRecursive($store);
            $store->warm();
        };

        foreach (self::WARM_ORDER as $storeKey) {
            $warmStore($storeKey);
        }

        $warmStore('entries');
    }

    /**
     * Reset a store's in-memory path list and keys index — recursing into an
     * aggregate store's per-collection/per-taxonomy child stores — immediately
     * before it warms.
     *
     * `clearCachedPaths()` nulls the store's in-memory `$this->paths` (and forgets
     * its persisted paths cache) so `warm()` re-traverses the re-rooted fixture
     * directory. Without it a paths list cached empty earlier in the bootstrap
     * survives, and the warm reads that empty list instead of the fixtures — the
     * store then indexes nothing. This surfaces under `vendor/bin/pest` (how CI runs
     * the suite) even when `php artisan test` happens to leave the paths null.
     *
     * `setKeys([])` empties the in-memory keys index (which `Keys::clear()` leaves
     * populated) so a fixture id reusing a real `content/` id adds without a
     * `DuplicateKeyException` when `content/` is present (the CI full suite).
     */
    private function prepareStoreForWarmRecursive(Store $store): void
    {
        $store->clearCachedPaths();

        if ($store instanceof AggregateStore) {
            /** @var Collection<int|string, mixed> $children */
            $children = $store->discoverStores();
            $children->each(function (mixed $child): void {
                if ($child instanceof Store) {
                    $this->prepareStoreForWarmRecursive($child);
                }
            });

            return;
        }

        $keys = $store->keys();

        if ($keys instanceof Keys) {
            $keys->setKeys([]);
        }
    }

    /**
     * Stache stores to warm (in order) before entries, so collection/taxonomy configs
     * and structure trees are indexed before any entry references them.
     *
     * @var list<string>
     */
    private const WARM_ORDER = [
        'taxonomies',
        'terms',
        'globals',
        'global-variables',
        'collections',
        'collection-trees',
        'navigation',
        'nav-trees',
        'asset-containers',
    ];

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fakeStacheDirectory);

        // Leave no cached index behind for the next (possibly non-isolated) test.
        Stache::clear();

        parent::tearDown();
    }

    /**
     * Absolute path to the committed fixture `content/` tree.
     */
    protected static function fixtureContentDirectory(): string
    {
        return \dirname(__DIR__).'/Fixtures/SchemaJsonLd/content';
    }
}
