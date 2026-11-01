<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Cache\RetryingFileStore;
use App\Support\ContentMirror;
use App\Support\ContentMirrorBandLock;
use App\Support\ContentRoot;
use App\Support\StacheSeedStamp;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Statamic\Sites\Sites;
use Tests\Support\CachedSites;
use Tests\Support\ContentSubmodule;
use Tests\Support\ParallelParent;
use Tests\Support\ThrowawayRoot;
use Tests\Support\WorkerCacheDirectory;
use Throwable;

/**
 * Boots the application for each test.
 *
 * Laravel's `TestCase` recreates `$this->app` per test method, so the framework
 * boot (`require bootstrap/app.php` + `Kernel::bootstrap()`) is paid per method.
 * That boot was measured at ~0.8s/method for this app (UAMS-Web/uams-statamic-archive#1121). Reusing a single
 * booted app across methods was evaluated and **not** adopted:
 *
 *   - It cannot bring the importer integration files under the per-file
 *     diagnostic cap. Their cost is dominated by real-import execution — the
 *     `statamic:import:wordpress` command runs at ~30-40s per case, tens of
 *     seconds over the ~0.8s boot it would save — so removing the boot floor
 *     leaves those files far over the cap regardless (the per-import cost is a
 *     wordpress-importer test optimization, out of scope for the host app).
 *   - Per-method app recreation is Laravel's isolation guarantee. The stateful
 *     import tests mutate extensive global state (Stache, content, config), so
 *     sharing one app across them would leak state between methods.
 *
 * The cap is therefore re-baselined with the boot floor characterized (~0.8s)
 * rather than reused away. The genuinely expensive once-per-process work — the
 * `statamic:stache:warm` — *is* gated to one run per process; that gate is
 * guarded against regression by TestBootstrapCostTest.
 *
 * Under `--parallel`, even one warm per worker is costly: all ~12 workers warm
 * concurrently and saturate the disk, ballooning a 21s warm to ~168s each — the
 * dominant parallel-suite cost (UAMS-Web/uams-statamic-archive#1537). So when the shared snapshot built by
 * `test:seed-stache` is present, each worker seeds its own per-worker cache from
 * it ({@see seedStacheFromSnapshot()}) — a cheap copy — instead of warming; the
 * warm is the fallback when no snapshot exists.
 *
 * The other once-per-process lever is the per-boot re-hydration of the 255-site
 * `resources/sites.yaml` (~280ms of the remaining boot). {@see CachedSites},
 * rebound in the `booting()` callback below, hydrates it once per process and
 * reuses the resolved collection across boots; that gate is guarded via
 * {@see sitesHydrationCount()} (UAMS-Web/uams-statamic-archive#1582).
 */
trait CreatesApplication
{
    /**
     * Whether `statamic:stache:warm` has already been run inside this PHP
     * process. Laravel's `TestCase` recreates `$this->app` per test method,
     * but the underlying Statamic stache cache (Laravel cache store, file
     * driver in tests) persists across app instances within one process.
     * Warming once populates that cache; every subsequent app boot in this
     * process can lazy-load from it without re-discovering content.
     */
    private static bool $stacheWarmed = false;

    /**
     * Number of times `statamic:stache:warm` has run in this PHP process.
     * Stays at 0 when the process seeded from the snapshot instead; otherwise 1
     * once warmed. A value above 1 means the once-per-process gate regressed to
     * a per-method warm. Read by the bootstrap-cost timing guard.
     */
    private static int $stacheWarmCount = 0;

    /**
     * Number of times this process seeded its Stache from the shared snapshot
     * ({@see seedStacheFromSnapshot()}) instead of warming. Stays at 1 once
     * seeded; a value above 1 means the once-per-process gate regressed to a
     * per-method seed. Read by the bootstrap-cost timing guard.
     */
    private static int $stacheSeedCount = 0;

    /**
     * Whether a usable snapshot was present when this process made its
     * once-per-process seed-or-warm decision. Recorded at that moment (not
     * re-read later) so the bootstrap-cost guard can assert "seeded, not warmed"
     * without depending on the snapshot dir's live state — a sibling test may
     * delete it, and its emptiness semantics must match {@see seedStacheFromSnapshot()}.
     */
    private static bool $stacheSnapshotPresentAtBoot = false;

    /**
     * Whether a snapshot was present but refused because it indexes a different
     * content root (or carries no stamp saying which).
     *
     * Recorded rather than left silent: the fallback is a correct-but-slow warm,
     * and UAMS-Web/uams-statamic-archive#1740's lesson is that a silent fallback trades one invisible
     * degradation for another. Read by {@see StacheSnapshotRootStampTest}.
     */
    private static bool $stacheSnapshotRejectedForRoot = false;

    /**
     * How many times {@see seedStacheFromSnapshot()} retries the snapshot copy
     * before giving up and warming. The copy fails under concurrent load rather
     * than for a persistent reason (UAMS-Web/uams-statamic-archive#1740), so a retry usually succeeds; the cap
     * bounds a worker's startup when the failure is not transient.
     */
    private const SNAPSHOT_COPY_ATTEMPTS = 3;

    /**
     * How many snapshot-copy attempts came back incomplete in this process.
     *
     * The fallback to a warm keeps a starved worker from running (UAMS-Web/uams-statamic-archive#1740), but a
     * silent fallback would just trade one invisible degradation for another: the
     * band would be correct and slow, with nothing saying the copy is failing. So
     * the failures are counted and asserted on rather than swallowed.
     *
     * 0 — copied cleanly first attempt (the expected state).
     * >0 with a seed — a retry recovered it; the copy is flaky under load.
     * == SNAPSHOT_COPY_ATTEMPTS — every attempt failed and the process warmed.
     */
    private static int $stacheSnapshotCopyFailures = 0;

    /**
     * Whether this process has registered the exit cleanup for its own search
     * index directory ({@see isolateSearchIndexes()}). Laravel boots a fresh app
     * per test, so without the flag every test would add another shutdown function.
     */
    private static bool $searchIndexCleanupRegistered = false;

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        // Reuse the hydrated 255-site multisite config across per-test app boots.
        // Statamic hydrates `resources/sites.yaml` (parse + per-site Antlers
        // resolve) the first time `Site::all()` is resolved — which happens in
        // `Stache\ServiceProvider::boot()` on every fresh per-test app, ~280ms
        // each (UAMS-Web/uams-statamic-archive#1582). Rebinding `Sites::class` to the caching {@see CachedSites}
        // in a `booting()` callback — which fires after Statamic's
        // `AppServiceProvider::register()` binds `Sites::class` and before the
        // Stache boot resolves it — hydrates once per process and injects the
        // cached collection thereafter. The sites are read-only global config,
        // identical for every test, so the reuse leaks no state (see CachedSites).
        $app->booting(function (Application $app): void {
            $app->singleton(Sites::class, fn (): CachedSites => new CachedSites);
        });

        $app->make(Kernel::class)->bootstrap();

        $this->isolateSearchIndexes($app);

        // Swap the `file` cache store for one that retries a transient Windows
        // "Permission denied" write (UAMS-Web/uams-statamic-archive#1381) — the same per-worker cache path,
        // only the write is made resilient. Registered before the stache warm
        // below, which writes the Stache key index through this store, so the
        // very first (warming) write is already covered. Harmless on Linux CI,
        // where the underlying error never fires. Mirrors the framework's
        // `createFileDriver` exactly (permission, `serializable_classes`, and
        // lock directory) so the store behaves identically apart from the retry.
        $cache = $app->make(CacheManager::class);
        $cache->extend('file', function (Application $app, array $config) use ($cache) {
            /** @var array{path: string, permission?: int|null, lock_path?: string|null} $config */
            /** @var array<int, class-string>|bool|null $serializableClasses */
            $serializableClasses = $app['config']['cache.serializable_classes'] ?? null;

            $store = (new RetryingFileStore(
                $app->make(Filesystem::class),
                $config['path'],
                $config['permission'] ?? null,
                $serializableClasses,
            ))->setLockDirectory($config['lock_path'] ?? null);

            return $cache->repository($store, $config);
        });
        $cache->forgetDriver('file');

        // Cold-stache test processes resolve `null` for content the project
        // assumes is present — asset containers, taxonomy terms, entries,
        // global sites. Populate the Stache once per process so every app boot
        // in this process can lazy-load from it. Prefer seeding from the shared
        // snapshot (`test:seed-stache`) — a cheap copy into this worker's own
        // per-worker cache dir — and fall back to a full warm when no snapshot is
        // present (a subset run, or a developer who skipped the seed step). See
        // {@see seedStacheFromSnapshot()} for why the snapshot is copied rather
        // than shared (the UAMS-Web/uams-statamic-archive#728 cross-process invisibility is a path mismatch).
        //
        // Skipped entirely in paratest's parent process (UAMS-Web/uams-statamic-archive#1970). It runs no
        // tests, so it needs no Stache — but it has no `TEST_TOKEN` either, so
        // seeding there targets the *shared* non-tokenized cache directory rather
        // than a per-worker one. Harmless for a lone band and fatal for two:
        // both parents race on `resetCacheDirectory()`'s check-then-act pair and
        // one aborts before a test runs. See {@see ParallelParent}.
        if (! self::$stacheWarmed && ! ParallelParent::isCurrentProcess()) {
            $this->ensureContentMirror($app);

            self::$stacheSnapshotPresentAtBoot = $this->stacheSnapshotIsUsable($app);
            if (self::$stacheSnapshotPresentAtBoot && $this->seedStacheFromSnapshot($app)) {
                self::$stacheSeedCount++;
            } else {
                $app->make(Kernel::class)->call('statamic:stache:warm');
                self::$stacheWarmCount++;
            }
            self::$stacheWarmed = true;

            $this->removeRunUniqueCacheAtExit($app);
        }

        return $app;
    }

    /**
     * Delete this worker's own file cache directory when the process exits.
     *
     * Since UAMS-Web/uams-statamic-archive#1985 a worker's cache path ends in its process id, so it is never
     * reused: without this, every band would leave one copy of the warm Stache
     * (4,242 files and 21 MB per worker, measured on Windows) until the next
     * `test:seed-stache` cleared `data/`. The pid makes the directory this
     * process's alone, so removing it at exit cannot touch a concurrent band.
     *
     * The HTMLPurifier definition cache (`tables.cell_sanitizer.cache_path`) is
     * keyed the same way and removed with it; nothing else would ever clear it.
     *
     * Only directories {@see WorkerCacheDirectory::isOwnedBy()} this process are
     * removed, so a serial run, which uses the shared default, never deletes it.
     * A killed worker skips shutdown functions and leaves its directories behind.
     */
    private function removeRunUniqueCacheAtExit(Application $app): void
    {
        /** @var list<string> $directories */
        $directories = array_values(array_filter(
            [
                $app['config']['cache.stores.file.path'],
                $app['config']['tables.cell_sanitizer.cache_path'],
            ],
            static fn (mixed $directory): bool => is_string($directory) && WorkerCacheDirectory::isOwnedBy($directory, getmypid()),
        ));

        if ($directories === []) {
            return;
        }

        register_shutdown_function(static function () use ($directories): void {
            $files = new Filesystem;

            foreach ($directories as $directory) {
                $files->deleteDirectory($directory);
            }
        });
    }

    /**
     * Point the `local` search driver at a directory only this process uses (UAMS-Web/uams-statamic-archive#2536).
     *
     * Statamic's `UpdateItemIndexes` subscriber runs on every entry, term, user and
     * asset save, inline because the test env sets `QUEUE_CONNECTION=sync`. It
     * reads a Comb index file, merges the saved item in and writes the file back, or
     * rebuilds the whole index when the file is missing. The driver's default path,
     * `storage/statamic/search`, is shared by every paratest worker, every
     * concurrent `--mutate` child and every serial run in the checkout. It is also
     * the index the checkout's own `.test` site serves. A concurrent read-modify-write
     * on one file loses updates, and a `--mutate --parallel` run was measured leaving
     * `cp.json` as invalid JSON.
     *
     * The segment ends in the process id ({@see ThrowawayRoot}), so no two live
     * processes share a directory, serial runs included. Each process starts with no
     * index and builds the ones its saves touch, as a fresh checkout already did.
     * The directory is removed when the process exits; a killed process leaves it.
     */
    private function isolateSearchIndexes(Application $app): void
    {
        $directory = storage_path('framework/testing/search/'.ThrowawayRoot::segment());

        $app['config']->set('statamic.search.drivers.local.path', $directory);

        if (self::$searchIndexCleanupRegistered) {
            return;
        }

        self::$searchIndexCleanupRegistered = true;

        register_shutdown_function(static function () use ($directory): void {
            (new Filesystem)->deleteDirectory($directory);
        });
    }

    /**
     * Make sure the disposable `content/` mirror the test env reads through
     * exists before anything indexes it (UAMS-Web/uams-statamic-archive#1916).
     *
     * `test:seed-stache` builds it, and both CI and `scripts/local-ci.mjs` run
     * that first — so in a normal band this finds the mirror present and does
     * nothing but take a lock. It is here for the path that skips the seed: a
     * developer running one test file, or a fresh checkout. Without it the
     * fallback warm below would index an *absent* mirror and hand the process a
     * Stache with no content in it, which reads as a wall of unrelated failures.
     *
     * Only ever *creates*, never rebuilds a drifted mirror — see
     * {@see ContentMirror} for why rebuilding here would delete
     * fixtures a concurrent worker is reading.
     *
     * A failure is thrown rather than tolerated: continuing would run the whole
     * process against empty content, and every one of those failures would point
     * anywhere but here.
     *
     * **Except when `content/` itself is absent, which is a supported state.**
     * A checkout without the submodule runs the suite with content-dependent
     * tests skipping through {@see ContentSubmodule::reportMissing()} (UAMS-Web/uams-statamic-archive#856,
     * UAMS-Web/uams-statamic-archive#1738) — and that path needs the process to boot at all. There is nothing
     * to mirror and nothing to repair, so this returns rather than converting a
     * documented skip into a fatal for every test in the run. The discriminator
     * reads the real submodule (not the mirror), so a *present* `content/` with a
     * broken mirror still fails loudly, which is what it should do.
     *
     * **It also tells `test:seed-stache` this process is reading the mirror (UAMS-Web/uams-statamic-archive#2762).**
     * The shared band lock is taken first and held for the rest of the process, so a
     * seed run alongside this process refuses instead of deleting files it is reading,
     * and a process that boots mid-seed waits for the seed rather than indexing a
     * half-built mirror. See {@see ContentMirrorBandLock}.
     */
    private function ensureContentMirror(Application $app): void
    {
        if (! ContentSubmodule::isInitialized()) {
            return;
        }

        ContentMirrorBandLock::hold();

        $failure = ContentMirror::ensureExists($app->make(Filesystem::class));

        if ($failure !== null) {
            throw new RuntimeException($failure);
        }
    }

    /**
     * Whether the shared Stache snapshot built by `test:seed-stache` exists,
     * holds cache files, and indexes the content root this process reads.
     *
     * A present-but-empty dir — an interrupted build, or a warm that wrote
     * nothing — is treated as no snapshot, so the process falls back to a full
     * warm. `allFiles()` ignores dotfiles, so a snapshot carrying only a
     * `.gitignore` reads as empty here.
     *
     * **The root check is the one that prevents data loss, not just a cold
     * cache.** A snapshot indexes absolute paths, and a store whose directory
     * disagrees with its cached view is the destructive mixed state UAMS-Web/uams-statamic-archive#1873
     * documents: `ExistsAsFile::writeFile()` recomputes `buildPath()` and
     * deletes the `initialPath` it no longer matches. Since UAMS-Web/uams-statamic-archive#1916 an ordinary
     * branch switch reaches that state — a snapshot built where the stores were
     * rooted in `content/`, then a boot with them re-rooted at the mirror — and
     * it took `content/trees/collections/default/pages.yaml` twice before this
     * check existed. Rejecting the snapshot costs one warm; trusting it costs
     * committed content.
     *
     * A missing stamp is treated as a mismatch. It means the snapshot predates
     * this check, and nothing about it says which root it indexes.
     */
    private function stacheSnapshotIsUsable(Application $app): bool
    {
        $files = $app->make(Filesystem::class);
        $seedDirectory = $app->storagePath('framework/stache-seed');

        if (! $files->isDirectory($seedDirectory) || $files->allFiles($seedDirectory) === []) {
            return false;
        }

        $matches = StacheSeedStamp::matches($files, ContentRoot::path(Config::string('app.env')));

        self::$stacheSnapshotRejectedForRoot = ! $matches;

        return $matches;
    }

    /**
     * Seeds this process's per-worker file cache from the shared Stache snapshot
     * built by `test:seed-stache`. Called only when {@see stacheSnapshotIsUsable()}
     * confirmed a snapshot exists.
     *
     * The pre-run warm writes the default `storage/framework/testing/cache/data`,
     * but each paratest worker reads a per-worker `data/<TEST_TOKEN>-<pid>` dir it never
     * populated — the path mismatch behind the UAMS-Web/uams-statamic-archive#728 "the pre-run warm doesn't
     * help the parallel job" note. Copying the snapshot into the worker's own
     * dir hands it a fully warm index for the price of a file copy instead of a
     * ~168s contended re-warm (UAMS-Web/uams-statamic-archive#1581). Isolation is preserved: each worker copies
     * a read-only snapshot into its own dir, so the UAMS-Web/uams-statamic-archive#1136 per-worker separation
     * is untouched.
     *
     * **The copy is verified, not assumed (UAMS-Web/uams-statamic-archive#1740).** `copyDirectory()` aborts at
     * the *first* failed `copy()`, returns `false`, and leaves the partial tree it
     * had written. With ~12 workers each copying thousands of files at once, that
     * happened constantly and silently: a starved worker ran the whole band against
     * a near-empty Stache, and content-dependent tests skipped (or failed) for
     * reasons unrelated to the code under test. Measured on one band, no worker
     * received the complete 4,586-file snapshot and one received two files.
     *
     * A short retry keeps the common case a cheap copy — degrading straight to a
     * warm would hand back the ~168s-per-worker cost UAMS-Web/uams-statamic-archive#1581 removed. Each attempt
     * restarts from an empty destination, because a partial tree from the previous
     * attempt would otherwise satisfy the count check with stale files.
     *
     * @return bool Whether the worker's cache now holds the complete snapshot.
     *              `false` means the caller must warm instead.
     */
    private function seedStacheFromSnapshot(Application $app): bool
    {
        $files = $app->make(Filesystem::class);
        /** @var string $cacheDirectory */
        $cacheDirectory = $app['config']['cache.stores.file.path'];
        $seedDirectory = $app->storagePath('framework/stache-seed');

        // `allFiles()` ignores dotfiles on both sides, so the `.gitignore` the
        // snapshot carries is excluded from the source and destination counts
        // alike — consistent, not an off-by-one. An unreadable source makes the
        // traversal throw; that is a snapshot we cannot verify, so warm.
        try {
            $expected = count($files->allFiles($seedDirectory));
        } catch (Throwable) {
            return false;
        }

        for ($attempt = 1; $attempt <= self::SNAPSHOT_COPY_ATTEMPTS; $attempt++) {
            $this->resetCacheDirectory($files, $cacheDirectory);

            try {
                // A failed `copy()` reports two different ways depending on the
                // error handler: `copyDirectory()` returns false, or the warning
                // it raises is promoted to an ErrorException (which is what the
                // test env's handler does). Both mean the same thing here, and
                // neither should take the worker down — the whole point is to
                // fall back to a warm rather than run starved.
                $copied = $files->copyDirectory($seedDirectory, $cacheDirectory)
                    && count($files->allFiles($cacheDirectory)) === $expected;
            } catch (Throwable) {
                $copied = false;
            }

            if ($copied) {
                return true;
            }

            self::$stacheSnapshotCopyFailures++;
        }

        // Leave no partial cache behind: a warm lazy-loads what it needs, but a
        // truncated index that is *present* would be read as authoritative.
        $this->resetCacheDirectory($files, $cacheDirectory);

        return false;
    }

    /**
     * Empty the per-worker cache directory, preserving its tracked `.gitignore`.
     *
     * `deleteDirectory()` takes the `.gitignore` with it. That file is committed,
     * so dropping it dirties the working tree of any persistent checkout the suite
     * runs in — and `git status` noise is exactly what hides a real stray file.
     * `SeedTestStache` preserves it the same way for the same reason.
     */
    private function resetCacheDirectory(Filesystem $files, string $cacheDirectory): void
    {
        $gitignore = $cacheDirectory.DIRECTORY_SEPARATOR.'.gitignore';
        $contents = $files->exists($gitignore) ? $files->get($gitignore) : null;

        $files->deleteDirectory($cacheDirectory);
        $files->ensureDirectoryExists($cacheDirectory);

        if ($contents !== null) {
            $files->put($gitignore, $contents);
        }
    }

    /**
     * The number of `statamic:stache:warm` runs paid in this PHP process.
     *
     * Read these accessors through the **using class** — `Tests\TestCase::…` —
     * never as `Tests\CreatesApplication::…`. Static properties declared in a
     * trait are per-using-class, so calling them on the trait returns the trait's
     * own copies, which nothing ever increments: every counter reads 0 and
     * `stacheSnapshotWasPresentAtBoot()` reads `false`, which looks exactly like
     * a process that neither seeded nor warmed. That misread a UAMS-Web/uams-statamic-archive#1740 diagnostic.
     *
     * @return int Warm count; 1 once the process has warmed, 0 if it seeded.
     */
    public static function stacheWarmCount(): int
    {
        return self::$stacheWarmCount;
    }

    /**
     * The number of snapshot seeds paid in this PHP process.
     *
     * @return int Seed count; 1 once the process has seeded, 0 if it warmed.
     */
    public static function stacheSeedCount(): int
    {
        return self::$stacheSeedCount;
    }

    /**
     * Whether a usable snapshot was present when this process decided to seed or
     * warm. Frozen at that decision, so the bootstrap-cost guard can assert the
     * seed-not-warm win regardless of later snapshot-dir mutations.
     *
     * This records the **source** being usable, not that the copy succeeded — see
     * {@see stacheSnapshotCopyFailures()} for the outcome.
     */
    public static function stacheSnapshotWasPresentAtBoot(): bool
    {
        return self::$stacheSnapshotPresentAtBoot;
    }

    /**
     * Whether this process refused a snapshot because it indexes a different
     * content root, or carries no stamp naming one (UAMS-Web/uams-statamic-archive#1916).
     *
     * `false` is the expected state. `true` means the process paid a full warm
     * instead — correct, and slow, and worth being able to see rather than
     * inferring from a boot that took ~21s longer than it should have.
     */
    public static function stacheSnapshotWasRejectedForRoot(): bool
    {
        return self::$stacheSnapshotRejectedForRoot;
    }

    /**
     * The number of incomplete snapshot-copy attempts in this process (UAMS-Web/uams-statamic-archive#1740).
     *
     * Read via `Tests\TestCase::…`, like the counters above. Zero is the expected
     * state; a non-zero value means {@see seedStacheFromSnapshot()} caught a
     * partial copy that would previously have starved this worker's Stache.
     *
     * @return int Failed attempts; 0 when the snapshot copied cleanly first try.
     */
    public static function stacheSnapshotCopyFailures(): int
    {
        return self::$stacheSnapshotCopyFailures;
    }

    /**
     * How many copy attempts {@see seedStacheFromSnapshot()} makes before warming.
     *
     * Exposed so the bootstrap-cost guard can assert the counter against the cap
     * rather than restating an invariant it already checks.
     */
    public static function stacheSnapshotCopyAttempts(): int
    {
        return self::SNAPSHOT_COPY_ATTEMPTS;
    }

    /**
     * The number of `resources/sites.yaml` hydrations paid in this PHP process.
     *
     * Delegates to {@see CachedSites::hydrationCount()}. Stays at 1 once the
     * process has hydrated the multisite config; a value above 1 means the
     * per-process sites cache regressed to a per-method hydration. Read by the
     * bootstrap-cost timing guard.
     *
     * @return int Hydration count; 1 once the process has hydrated, 0 before.
     */
    public static function sitesHydrationCount(): int
    {
        return CachedSites::hydrationCount();
    }
}
