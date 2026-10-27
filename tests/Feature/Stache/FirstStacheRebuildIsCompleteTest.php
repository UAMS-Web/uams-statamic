<?php

declare(strict_types=1);

use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\ChildStore;
use Statamic\Stache\Stores\Store;
use Tests\Support\StacheCacheOnlyIsolatedTestCase;

/**
 * The first Stache rebuild against an empty cache must return the whole
 * collection, not one entry (UAMS-Web/uams-statamic-archive#2037).
 *
 * `Statamic\Stache\Traverser` is reached through a real-time facade
 * (`use Facades\Statamic\Stache\Traverser`), and Laravel caches a facade's
 * resolved object in `Facade::$resolvedInstance` — so every store in the request
 * shares ONE traverser. `filter()` writes the callback onto that shared object
 * and `traverse()` reads it back inside its own `foreach`, and nothing keeps the
 * two apart when a traversal starts another one.
 *
 * On a cold cache the first file's `getItemFilter()` resolves the collection,
 * which builds the `collections` store, which calls `Traverser::filter()` on the
 * same object mid-loop. The outer loop resumes holding
 * `CollectionsStore::getItemFilter`, which accepts only `.yaml` — so every
 * remaining `.md` entry is rejected. Measured on `entries::pages`: 82 files in,
 * one kept, the other 81 filtered by the wrong store. The one-item result is
 * truthy, so `Store::paths()` caches it as authoritative and it never self-heals.
 *
 * The cold cache is reached with {@see StacheCacheOnlyIsolatedTestCase} and NOT
 * with `Cache::flush()`. A bare flush empties the worker's shared store and takes
 * the seeded index with it — the UAMS-Web/uams-statamic-archive#1994 defect, which this test would otherwise
 * reintroduce while claiming to fix its consequence. That base case swaps only
 * `statamic.stache.cache_store`, leaving the content directories pointed at the
 * real corpus, so the store reads 82 real files while its cache starts empty.
 * Nothing outside this test is touched, so no later test inherits a cold index.
 *
 * The two reads are the whole assertion: the first rebuild is the one that
 * starves, and the second is always correct, so they disagree only when the
 * defect is present. No hard-coded 82 — the corpus may grow.
 *
 * The sibling taxonomy call site was checked under UAMS-Web/uams-statamic-archive#2242 and CANNOT starve, so
 * this test is the whole regression surface rather than one of two. Instrumenting
 * `Traverser` with a re-entrancy depth counter records no nested `filter()` call
 * during a `TaxonomyTermsStore` traversal: its `getItemFilter()` is path string
 * work and an extension check, and the `Taxonomy::findByHandle()` that would nest
 * is commented out in the vendored source, so nothing exists to replace the
 * filter mid-loop. Measured with the patch reverted, four taxonomies read cold in
 * separate processes (`fields_of_study`, `knowledge_base_tags`,
 * `specialties_acgme`, `credentials`; counts are terms across localizations) each
 * rebuilt completely, while this test failed `1 of 82` in the same tree and the
 * same vendor state.
 *
 * The taxonomy store sits on the OTHER side of this defect. The same
 * instrumentation catches `TaxonomyTermsStore::paths()` calling `filter()` from
 * inside a running entries traversal, so it is one of the nested calls that
 * corrupts an outer loop rather than a loop that can be corrupted. The patch
 * covers that direction too, and this test already fails when it is removed.
 *
 * Measure ONE store per process. An earlier probe read a taxonomy and `pages`
 * in a single test and the entries control stopped firing — 82 of 82, with the
 * defect present — because resolving a taxonomy first warms the collections
 * lookup whose nested traversal is the entire mechanism. A probe that silences
 * its own control reports a starvation-free corpus in exactly the conditions
 * that starve.
 *
 * @command php artisan test --compact tests/Feature/Stache/FirstStacheRebuildIsCompleteTest.php
 */
uses(StacheCacheOnlyIsolatedTestCase::class);

it('rebuilds the whole collection on the first read against an empty cache', function (): void {
    $store = coldRebuildPagesStore();

    // ---
    // Force the `collections` store cold BEFORE the measured read (UAMS-Web/uams-statamic-archive#2261).
    //
    // The defect needs the collections store to be built INSIDE the entries
    // traversal, because that is what replaces the shared traverser's filter
    // mid-loop. Whether that happens was an accident of the worktree until this
    // line existed: an empty `storage/framework/stache-seed` makes the first
    // Stache access build everything eagerly, so collections is already resolved
    // by the time this test reads, and the nested call never occurs.
    //
    // Measured with a re-entrancy counter on `Statamic\Stache\Traverser`, same
    // commit, fix removed in both trees:
    //
    //   provisioned slot   entries(0) -> collections(1) -> entries(0)   FAILED 1 of 82
    //   fresh worktree     collections(0) -> entries(0) -> entries(0)   passed, 82 of 82
    //
    // Same three traversals, different order, opposite verdict. With this line the
    // fresh tree produces the slot's order and fails identically, so the test now
    // detects the defect wherever it runs rather than where the environment allows.
    // ---
    coldRebuildCollectionsStore()->clearCachedPaths();

    // The Stache cache is empty, so this read IS the cold rebuild.
    $firstRebuild = coldRebuildPagesCount();

    // The second rebuild is always correct, which is what makes it the reference.
    $store->clearCachedPaths();
    $secondRebuild = coldRebuildPagesCount();

    expect($secondRebuild)->toBeGreaterThan(
        1,
        'Baseline broken: the reference rebuild should hold the real `pages` corpus, so a starved first rebuild is distinguishable from a genuinely small collection.'
    );

    expect($firstRebuild)->toBe(
        $secondRebuild,
        "The first rebuild returned {$firstRebuild} of {$secondRebuild} entries. A nested traversal replaced the traverser's filter mid-loop (#2037)."
    );
});

/**
 * How many `pages` entries the Stache resolves right now.
 *
 * `Entry::query()` is typed as the `QueryBuilder` *contract*, which declares no
 * `where()`, so the concrete builder is named for the analyser rather than
 * chained through — the same shape as `contentMirrorIndexedEntryCount()`.
 */
function coldRebuildPagesCount(): int
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'pages');

    $count = $query->count();

    if (! \is_int($count)) {
        throw new RuntimeException('the entry query returned a non-integer count');
    }

    return $count;
}

/**
 * The `collections` store, named for the analyser.
 *
 * Same shape as {@see coldRebuildPagesStore()}: the facade hands back `mixed`, so
 * the type is narrowed by control flow rather than asserted, and a store that
 * stopped being a Stache store surfaces here rather than as a silent failure.
 */
function coldRebuildCollectionsStore(): Store
{
    $store = Stache::store('collections');

    if (! $store instanceof Store) {
        throw new RuntimeException('the `collections` store is not a Stache store');
    }

    return $store;
}

/**
 * The `pages` child store, named for the analyser.
 */
function coldRebuildPagesStore(): ChildStore
{
    $store = Stache::store('entries::pages');

    // Narrowed by control flow rather than asserted: the facade hands back
    // `mixed`, and a store that stopped being a child store should surface here
    // rather than as a silent failure inside the test.
    if (! $store instanceof ChildStore) {
        throw new RuntimeException('the `entries::pages` store is not a child store');
    }

    return $store;
}
