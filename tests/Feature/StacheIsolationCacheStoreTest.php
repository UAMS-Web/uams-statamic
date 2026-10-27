<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use Tests\Concerns\IsolatesStacheCache;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Stache-isolated tests must not reach the worker's shared Stache cache (UAMS-Web/uams-statamic-archive#1747).
 *
 * `PreventsSavingStacheItemsToDisk` re-roots the store *directories*; before
 * {@see IsolatesStacheCache} it left the *cache store* shared, so
 * an isolated test both erased the seeded index for a real collection handle and
 * wrote its own fixture view of that handle in its place — which the next test in
 * the worker then read as authoritative (a `knowledge_bases` store 1 entry deep
 * instead of 30).
 *
 * Both directions are asserted here rather than through a full parallel band,
 * because the band only exposes the leak when a fixture handle happens to collide
 * with a real one — the reproduction has to be the mechanism, not the coincidence.
 *
 * @command php artisan test --compact tests/Feature/StacheIsolationCacheStoreTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * The shared per-worker cache key holding the `knowledge_bases` entry paths.
 *
 * A real handle is what makes the leak damaging, so the write test uses one —
 * but only ever *reads* this key, never writes it. The erase test needs to plant
 * a value, so it uses {@see STACHE_ISOLATION_PROBE_HANDLE} instead: writing a
 * one-path index over a real handle here would inflict on the rest of the worker
 * precisely the starvation this file exists to prevent.
 */
const STACHE_ISOLATION_SHARED_PATHS_KEY = 'stache::indexes::entries::knowledge_bases::path';

/**
 * A collection handle no other test reads, used where a value must be planted in
 * the shared cache. `Stache::clear()` reaches it exactly like a real handle once
 * the throwaway root declares it, so it exercises the same code path.
 */
const STACHE_ISOLATION_PROBE_HANDLE = 'stache_isolation_probe';

/**
 * The `knowledge_bases` child of the entries aggregate store, narrowed.
 *
 * `Stache::store()` is documented as returning `mixed`, and the child lookup is
 * only on the aggregate, so both hops need narrowing. Doing it once here keeps
 * the assertion above readable.
 */
function knowledgeBaseEntriesStore(): Store
{
    /** @var AggregateStore $entries */
    $entries = Stache::store('entries');

    /** @var Store $store */
    $store = $entries->store('knowledge_bases');

    return $store;
}

it('does not write its throwaway-root index into the shared Stache cache', function (): void {
    // The shared store is the worker's `file` store — the one config/cache.php
    // tokenizes per worker and the per-process seed populates. Read it directly
    // (not via Stache::cacheStore()) so the assertion is about the shared store
    // whatever the Stache is currently pointed at.
    $shared = Cache::store('file');
    $before = $shared->get(STACHE_ISOLATION_SHARED_PATHS_KEY);

    // Recreate a real collection handle inside the throwaway root and give it a
    // single entry, reproducing the collision that makes the leak damaging.
    Collection::make('knowledge_bases')->save();

    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    /** @var StatamicEntry $entry */
    $entry = $entry->collection('knowledge_bases');
    /** @var StatamicEntry $entry */
    $entry = $entry->slug('isolation-probe');
    /** @var StatamicEntry $entry */
    $entry = $entry->data(['title' => 'Isolation probe']);

    // `saveQuietly()` — the store write is what this test is about, and the
    // events are not. `knowledge_bases` carries a domain listener
    // (`ValidateKnowledgeBaseEntry`) that rejects an `article` with no topic
    // parent, and a blueprint-less probe entry resolves to whichever blueprint
    // the throwaway root happens to yield. Saving quietly still runs
    // `BasicStore::save()` — `setPath()` plus the index update, i.e. exactly the
    // writes that used to reach the shared cache — while keeping the assertion
    // independent of how a real handle's blueprints resolve. The base class
    // documents this pairing.
    $entry->saveQuietly();

    // Force the entries index to build from the throwaway root, which is what
    // persists the fixture's view of the collection.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    /** @var EntryQueryBuilder $collectionQuery */
    $collectionQuery = $query->where('collection', 'knowledge_bases');
    $collectionQuery->get();

    // The isolated test sees its own one-entry collection...
    expect(knowledgeBaseEntriesStore()->paths())->toHaveCount(1)
        // ...and the shared store never learned about it.
        ->and($shared->get(STACHE_ISOLATION_SHARED_PATHS_KEY))->toEqual($before);
});

it('does not erase the shared Stache cache when it clears its own', function (): void {
    // `Stache::clear()` runs on entry and exit of every isolated test. It forgets
    // the paths/keys/index entries of every handle Collection::handles() resolves,
    // so declaring the handle in the throwaway root is what puts the shared key in
    // its reach — the same way a fixture handle that shadows a real one does.
    Collection::make(STACHE_ISOLATION_PROBE_HANDLE)->save();

    $key = 'stache::indexes::entries::'.STACHE_ISOLATION_PROBE_HANDLE.'::path';
    $sentinel = ['sentinel-id' => 'content/collections/'.STACHE_ISOLATION_PROBE_HANDLE.'/sentinel.md'];

    $shared = Cache::store('file');
    $shared->forever($key, $sentinel);

    Stache::clear();

    // Read, then clean up before asserting, so a failure still leaves the shared
    // store as this test found it.
    $after = $shared->get($key);
    $shared->forget($key);

    expect($after)->toEqual($sentinel);
});
