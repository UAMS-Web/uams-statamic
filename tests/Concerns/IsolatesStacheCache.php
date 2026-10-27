<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Gives a Stache-isolated test its own in-memory Stache cache, so nothing it
 * indexes can reach the worker's shared Stache cache (UAMS-Web/uams-statamic-archive#1747).
 *
 * `Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk` re-roots every
 * store's **directory** at a throwaway per-worker tree. It does not re-root the
 * **cache store** those stores index into — that stays the worker's default
 * `file` store (`config/cache.php`, tokenized per paratest worker), the same one
 * the per-process Stache seed populated with the real `content/` index. So an
 * isolated test reads and writes the real index while pointing at fixture
 * directories, in both directions:
 *
 *  - **It erases the seeded index.** `Stache::clear()` — called on entry and exit
 *    of both isolated base cases — forgets `stache::indexes::entries::<handle>::*`,
 *    `stache::keys/*`, and the timestamps for every handle `Collection::handles()`
 *    resolves at that moment. Those are the *shared* keys, so every later test in
 *    the worker re-traverses `content/` to rebuild what the seed had already
 *    built. A single band was measured making 70+ such forgets.
 *  - **It writes its fixture index in their place.** Any entry query inside the
 *    isolated test builds the index for a real collection handle from the
 *    throwaway directory (`Index::update()` → `Store::paths()` → `cachePaths()`)
 *    and persists it to the shared store. Teardown only clears what
 *    `Collection::handles()` still resolves — by then the fixture handles, not the
 *    real ones — so a handle the test touched but did not recreate is left
 *    holding the fixture's view of the collection.
 *
 * An empty fixture tree writes an empty list, which is falsy in
 * `Store::paths()` and therefore self-heals on the next read. One fixture entry
 * does not: the next test reads a one-entry `knowledge_bases` (or `people`)
 * store as authoritative and its content-dependent assertions skip or fail
 * against a collection that is really 30 entries deep — the UAMS-Web/uams-statamic-archive#1747 starvation,
 * and the same shape UAMS-Web/uams-statamic-archive#1740 measured (`paths=1`, `uri_index=1`, `blueprint`
 * `{article: 1}`).
 *
 * Pointing the Stache at an `array` store closes both directions at once: the
 * isolated test's *Stache* can neither read nor write the shared cache, and the
 * store dies with the test's application instance, so no restore is needed.
 *
 * Scoped to `Stache::cacheStore()` traffic. A test that calls `Cache::flush()`
 * itself still empties the worker's default store, and this file used to argue
 * that was acceptable because an erase self-heals: `Store::paths()` treats an
 * absent value as a cache miss and re-traverses, so it should cost wall-clock and
 * never a starved collection.
 *
 * That reasoning was wrong, and UAMS-Web/uams-statamic-archive#1994 is what it cost. The FIRST rebuild after
 * such a flush does not come back complete — measured on `entries::pages`, a
 * directory holding 82 files rebuilt as **1**, which was then cached as
 * authoritative. A one-entry list is truthy, so unlike an empty one it never
 * heals on the next read, and every later test in the process ran against a
 * Stache holding roughly one entry per collection while the band stayed green.
 * (The incomplete rebuild itself is UAMS-Web/uams-statamic-archive#2037; this file only governs who can trigger
 * it.)
 *
 * The fix is {@see flushIsolatedCache()}, NOT redirecting `cache.default`.
 * Redirecting the default store was tried and reverted: it hands every isolated
 * suite a cache that dies with the test, which breaks any suite deliberately
 * carrying a value ACROSS tests through the default store.
 * `SchemaJsonLdFragmentCacheRegimeTest` is exactly that — its whole property is
 * that a fragment written by one test survives physically and becomes unreachable
 * by bust ROTATION rather than deletion, with the fragment, the bust counter and
 * the carrier deliberately sharing one store so all three share a fate. A
 * per-test store deletes them instead, which is the one mechanism that suite
 * exists to distinguish from.
 *
 * Fifteen test files call `Cache::flush()`. Only `ProviderApiTest` was both a
 * flusher and Stache-isolated, which is why the starvation surfaced where it did.
 */
trait IsolatesStacheCache
{
    /**
     * Name of the throwaway cache store the Stache is pointed at.
     */
    private const string ISOLATED_CACHE_STORE = 'stache_isolated';

    /**
     * Point `Stache::cacheStore()` at a per-test in-memory store.
     *
     * Call after `parent::setUp()` — the application boot's once-per-process
     * seed/warm must still populate the real shared store — and before
     * `preventSavingStacheItemsToDisk()`, so every Stache read and write made
     * while the directories are re-rooted is served by the throwaway store.
     *
     * `Cache::store()` resolves its configuration on first use, so registering
     * the store here (rather than in `config/cache.php`) keeps it invisible to
     * every non-isolated test and to production.
     *
     * `serialize` is on. Laravel's `ArrayStore` defaults it off and then hands
     * back the *same object instance* it was given — but the Stache caches item
     * objects (`stache::items::*`), and the `file` store this replaces returns a
     * fresh clone on every read. Without serialization an entry and the "stored
     * original" a save compares it against are one object, so every dirty-state
     * check reads clean: `SyncEntryPublishedStatus` stops seeing a status
     * transition, and five of its tests fail. Serializing restores the file
     * store's value semantics, which is the whole contract being stood in for.
     */
    protected function isolateStacheCache(): void
    {
        config([
            'cache.stores.'.self::ISOLATED_CACHE_STORE => ['driver' => 'array', 'serialize' => true],
            'statamic.stache.cache_store' => self::ISOLATED_CACHE_STORE,
        ]);
    }

    /**
     * Empty ONLY the throwaway Stache cache store.
     *
     * The safe stand-in for `Cache::flush()` inside a Stache-isolated test. A bare
     * flush empties the worker's shared store, taking the seeded Stache index with
     * it, and what rebuilds in its place is starved rather than absent (UAMS-Web/uams-statamic-archive#1994).
     * This reaches exactly the store this trait installed and nothing else.
     */
    protected function flushIsolatedCache(): void
    {
        Cache::store(self::ISOLATED_CACHE_STORE)->flush();
    }
}
