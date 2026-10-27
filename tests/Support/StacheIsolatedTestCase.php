<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Spatie\Blink\Blink as SpatieBlink;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Tests\Concerns\IsolatesStacheCache;
use Tests\TestCase;

/**
 * Base test case that repoints every Stache store at a throwaway directory so a
 * test can persist real collections/entries without reading production content
 * or leaving litter behind. Saves still hydrate the in-memory Stache, so queries
 * (where/like, index-on-demand) exercise the real query layer — only the disk
 * target changes.
 *
 * Blueprints are NOT Stache items: `Blueprint::save()` writes to
 * `resources/blueprints/<namespace>/<handle>.yaml`, which the Stache fake
 * directory does not cover — so a fixture blueprint saved under a real handle
 * (e.g. `collections.people`) would silently clobber the production blueprint on
 * disk. This base therefore also repoints the blueprint repository at a
 * throwaway directory for the duration of each test and restores it afterwards.
 *
 * The Stache *cache* is repointed too, at a per-test in-memory store — see
 * {@see IsolatesStacheCache}. A throwaway directory alone isolates only what is
 * read from disk; the index built from it was still written to (and cleared out
 * of) the worker's shared Stache cache, where the next test read it as the real
 * collection (UAMS-Web/uams-statamic-archive#1747).
 *
 * Use for service/repository tests that must persist real Statamic items. Pair
 * with `saveQuietly()` when a global EntrySaved/EntrySaving listener would
 * otherwise mutate the fixtures out from under the assertion.
 */
abstract class StacheIsolatedTestCase extends TestCase
{
    use IsolatesStacheCache;
    use PreventsSavingStacheItemsToDisk;

    /**
     * Named Blink stores that mirror the Stache, and are therefore stale the
     * moment the stores are re-rooted.
     *
     * Deliberately excludes the three `blueprints.*` stores, which is the line
     * that matters here (UAMS-Web/uams-statamic-archive#1845). This base re-roots the blueprint directory to
     * stop a fixture blueprint saved under a real handle from clobbering the
     * production file — a WRITE protection. Reading real blueprints is intended
     * and depended on: `EntriesByBlueprintDynamicDerivationTest` asserts against
     * the real `clinical_expertise` handles and goes red the moment those memos
     * are dropped. So flush what this base re-roots, and leave what it only
     * write-protects.
     *
     * @var array<int, string>
     */
    private const array STACHE_DERIVED_BLINK_STORES = [
        'entry-uris',
        'structure-uris',
        'structure-entries',
        'collection-mounts',
    ];

    /**
     * Throwaway Stache root. Must end in `/dev-null` for the trait's path math
     * (it splits on that segment to re-root each store under this directory).
     */
    protected string $fakeStacheDirectory;

    /**
     * Throwaway blueprint root, used so fixture blueprints never touch
     * `resources/blueprints`.
     */
    protected string $fakeBlueprintDirectory;

    /**
     * The real blueprint directory, restored in tearDown.
     */
    private string $originalBlueprintDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Give the Stache its own in-memory cache for the duration of this test.
        // Re-rooting the store directories below isolates what is READ FROM DISK;
        // this isolates what is INDEXED INTO THE CACHE, which is otherwise the
        // worker's shared file store and the seeded real-`content/` index with it
        // (UAMS-Web/uams-statamic-archive#1747). Must come before preventSavingStacheItemsToDisk() and the
        // Stache::clear() below, both of which index against the shared store
        // otherwise. See the trait for the two directions this closes.
        $this->isolateStacheCache();

        // Per-run isolation: the throwaway roots below are wiped by
        // File::deleteDirectory in BOTH setUp and tearDown, so a run that shares one
        // with another run erases that run's fixtures mid-test. {@see ThrowawayRoot}
        // resolves the segment that separates them. Every segment ends in the
        // process id — `<worker index>-<pid>` under paratest, `seq-<pid>`
        // serially — which covers all three collisions: ~12 workers racing one
        // `framework/testing/{stache,blueprints}` tree (UAMS-Web/uams-statamic-archive#1136), two concurrent
        // serial runs, which the old fixed `seq` put on one path (UAMS-Web/uams-statamic-archive#1957), and two
        // concurrent parallel bands, which a bare worker index put on one path
        // because that index repeats across runs (UAMS-Web/uams-statamic-archive#1960).
        //
        // Deliberately NOT the same shape as the per-worker file cache path in
        // config/cache.php, which still keys on the bare token: that path is read
        // in production, and `SeedTestStache` needs it to parent every
        // `data/<token>` tree.
        //
        // Called inline in each storage_path() rather than lifted to a local first:
        // the parallel-safety guard (scripts/check-parallel-safe-tests.php, Rule B)
        // reads the call's own tokens to confirm a framework/testing path is
        // per-run, and a hoisted variable hides it.
        $this->fakeStacheDirectory = storage_path(
            'framework/testing/stache/'.ThrowawayRoot::segment().'/dev-null'
        );

        $parent = \dirname($this->fakeStacheDirectory);
        if (! is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        // Start clean even if a prior run crashed before its tearDown ran, so a
        // stale fixture can never bleed into this test's Stache index.
        $this->clearThrowawayDirectory($this->fakeStacheDirectory);

        $this->preventSavingStacheItemsToDisk();

        // Isolate blueprint saves: capture the real directory, repoint at a
        // throwaway one, and start it clean. Restored in tearDown so a later
        // (non-isolated) test still sees production blueprints.
        $this->originalBlueprintDirectory = Blueprint::directory();
        $this->fakeBlueprintDirectory = storage_path('framework/testing/blueprints/'.ThrowawayRoot::segment());
        $this->clearThrowawayDirectory($this->fakeBlueprintDirectory);
        Blueprint::setDirectory($this->fakeBlueprintDirectory);

        // The store directories are now isolated, but the Stache index is cached
        // via the application cache (file driver under phpunit.xml), which
        // survives across both tests and separate processes. Forget it so each
        // test rebuilds its index from the empty fake directory only.
        Stache::clear();

        // Drop every value Statamic memoized while the REAL content directory was
        // still in place (UAMS-Web/uams-statamic-archive#1851). Re-rooting the stores changes only what they
        // READ FROM DISK; it cannot reach what has already been computed into
        // Blink — 900+ keys by this point, from the boot in `parent::setUp()`.
        //
        // The sibling {@see SchemaFixtureStacheTestCase} has carried this flush
        // (and the same `Stache::clear()`-then-flush ordering) since UAMS-Web/uams-statamic-archive#1625; this
        // base simply never got it. Its comment documents the memo classes and why
        // a populated `content/` is the trigger — read it there rather than here.
        //
        // What that leaves worth stating is the symptom this base produced:
        // `Collection::handles()` is `Blink::once('collection-handles', …)`, so it
        // kept returning the 19 handles of the real `content/` submodule while
        // `findByHandle()` — which reads the re-rooted store directly — returned
        // null for every one. `QueriesEntryStatus` enumerates handles, resolves
        // each, and calls `handle()` on the null it gets back, so every
        // status-filtered entry query died there. It stayed hidden for two tickets
        // because `Collection::save()` calls `Blink::forget('collection-handles')`
        // — any suite that builds a fixture collection silently repairs the memo
        // before querying, which is why the failure landed on one file that
        // creates none (`MarketingRedirectControllerTest`, 22/22) rather than on
        // the dozens of other isolated suites.
        //
        // MUST come after `Stache::clear()`: the clear repopulates the memo with
        // the pre-isolation handles, so flushing first leaves it present and stale
        // by the time the test body runs. Measured, not assumed.
        //
        // Safe because Blink is per-application — a fresh instance per test
        // (verified: the object id differs between two tests in one file, and a
        // planted key does not survive), so an isolated test cannot leave its
        // empty view behind for a later non-isolated one.
        //
        Blink::flush();

        // `Blink::flush()` above reaches ONE store (UAMS-Web/uams-statamic-archive#1845). `Statamic\Support\Blink`
        // is a map of Spatie stores keyed by name and its `__call` forwards to
        // `store()`, i.e. `default`; the named stores are untouched. Measured at
        // this exact point, with the default store already at 0: `structure-entries`
        // 728, `entry-uris` 153, `structure-uris` 120, `collection-mounts` 1 — all
        // built against the real `content/` this base spent the lines above
        // re-rooting away from.
        foreach (self::STACHE_DERIVED_BLINK_STORES as $store) {
            $blinkStore = Blink::store($store);

            // Narrowed by control flow rather than annotation: the facade types
            // `store()` as mixed. A skip here cannot pass silently — the guard in
            // {@see StacheIsolatedStructureResolutionTest} requires each of these
            // stores to be BOTH present in the wrapper's map and empty, and only
            // this flush produces that pair.
            if ($blinkStore instanceof SpatieBlink) {
                $blinkStore->flush();
            }
        }
    }

    protected function tearDown(): void
    {
        $this->deleteFakeStacheDirectory();

        // Restore the real blueprint directory and remove any fixture blueprints.
        Blueprint::setDirectory($this->originalBlueprintDirectory);
        $this->clearThrowawayDirectory($this->fakeBlueprintDirectory);

        // Leave no cached index behind for the next (possibly non-isolated) test.
        Stache::clear();

        parent::tearDown();
    }

    /**
     * Delete a throwaway directory, retrying briefly while it survives its own
     * delete.
     *
     * `setUp()` clears the Stache root with this before each test. Teardown uses
     * the upstream `deleteFakeStacheDirectory()`, which guards its recreate but
     * deletes once (statamic/cms#15538), so a delete Windows defers can leave
     * the previous test's files in the root; the retry here removes them before
     * the next test indexes it (UAMS-Web/uams-statamic-archive#1696, UAMS-Web/uams-statamic-archive#2424).
     *
     * Deliberately silent when the directory outlasts every attempt: the delete
     * removes the *contents* first and only the final `rmdir` is deferred, so a
     * surviving entry is an empty one and isolation still holds. Raising here
     * would trade the flake this fixes for a louder one.
     *
     * @param  string  $directory  Absolute path to empty and remove.
     * @param  int  $attempts  How many times to try before giving up.
     */
    protected function clearThrowawayDirectory(string $directory, int $attempts = 5): void
    {
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) {
                usleep(10_000 * $attempt);
            }

            File::deleteDirectory($directory);

            clearstatcache(true, $directory);

            if (! is_dir($directory)) {
                return;
            }
        }
    }
}
