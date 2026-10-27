<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\Keys;
use Statamic\Stache\Stores\Store;

/**
 * Relocate every durable write a `tests/Browser` smoke makes into git-ignored
 * throwaway storage, so an interrupted run cannot strand source files (UAMS-Web/uams-statamic-archive#1920).
 *
 * ## What was stranded, and why an `afterEach` could not fix it
 *
 * The CP fieldtype smokes create a throwaway collection, blueprint and super
 * user in `beforeEach` and delete them in `afterEach`. That `afterEach` does not
 * run when the process dies first — a band killed at its bound, a fatal, a
 * `Ctrl-C` — and UAMS-Web/uams-statamic-archive#1727 moved `browser` into local-CI's default `all` set, where
 * it is both the last and the longest job and therefore the one an interrupt is
 * most likely to land on. The three artifacts land in three different places:
 *
 *   - `resources/blueprints/collections/<handle>/` — tracked source. An untracked
 *     leftover here is fatal to the NEXT run: UAMS-Web/uams-statamic-archive#1889's drift gate refuses to
 *     validate a worktree whose paths differ from the commit under test, so one
 *     interrupted run blocks every branch queued behind it.
 *   - `content/collections/<handle>.yaml` — the live content submodule, which
 *     UAMS-Web/uams-statamic-archive#1873's litter gate fails the build on. This half was never covered at all.
 *   - `users/<email>.yaml` — its own submodule, checked out by both gates since
 *     UAMS-Web/uams-statamic-archive#2477 (the UAMS-Web/uams-statamic-archive#1873 litter probe reads it too), and a dirty submodule in a
 *     developer's checkout.
 *
 * No teardown mechanism closes this, because the failure is the teardown not
 * running. `register_shutdown_function` survives a fatal but not a `SIGKILL`, and
 * self-healing on the NEXT run is too late — the drift gate runs before the suite
 * does, so the litter has already refused the run. The only fix that holds is to
 * never write into a tracked tree in the first place.
 *
 * ## Why relocation rather than an ignore rule
 *
 * `.gitignore` carried `/resources/blueprints/collections/*_smoke/` as an explicit
 * stopgap, naming this ticket and asking to be deleted when it landed. An ignore
 * rule over a tracked source tree is the wrong shape twice over: it can mask a
 * genuine blueprint someone adds, and it hides rather than prevents the write —
 * a green run today still leaves an empty `resources/blueprints/collections/
 * fieldtype_smoke/` behind, which reads as clean only because git cannot see an
 * empty directory. Relocation leaves nothing to see.
 *
 * The idiom is already this suite's own: `CspPublicFormConditionsTest` and
 * `ReplicatorPublicFormTest` point `statamic.forms.forms` at a throwaway under
 * `storage/framework/testing/` for exactly this reason. Forms are therefore
 * already covered and are deliberately left alone here.
 *
 * ## Seeded copies, not empty directories
 *
 * Blueprints and collections are seeded with a copy of the real thing, because
 * the CP pages these smokes render read production blueprints and resolve real
 * collections — an empty root would put a materially different application under
 * test, and the front-end browser tests would 404 outright. Users are not seeded:
 * every smoke creates the account it logs in as, so an empty store is the honest
 * one. Copying 21 collection configs and the blueprint tree costs milliseconds
 * against a CP boot and login that cost tens of seconds.
 *
 * {@see StacheIsolatedTestCase} is the feature-suite sibling. It cannot be reused
 * here: it re-roots EVERY store at an EMPTY directory, which is right for a test
 * that authors all its own fixtures and wrong for one that renders the real
 * Control Panel.
 */
final class BrowserWriteIsolation
{
    /**
     * Stache store holding collection configs (`content/collections/*.yaml`).
     *
     * The `entries` store shares that directory but is deliberately NOT re-rooted:
     * the smokes create no entries, and real entries must keep resolving for the
     * front-end browser tests.
     */
    private const string COLLECTIONS_STORE = 'collections';

    /**
     * Stache store holding user accounts (`users/`).
     */
    private const string USERS_STORE = 'users';

    /**
     * Throwaway root for this process, or an empty string while not isolated.
     */
    private static string $root = '';

    /**
     * The real blueprint directory, restored by {@see end()}.
     */
    private static ?string $originalBlueprintDirectory = null;

    /**
     * Store key => real directory, restored by {@see end()}.
     *
     * @var array<string, string>
     */
    private static array $originalStoreDirectories = [];

    /**
     * Redirect blueprint, collection and user writes into throwaway storage.
     */
    public static function begin(): void
    {
        // Self-heal a teardown that never ran. `Tests\TestCase::tearDown()` fires
        // from the `finally` around Pest's afterEach chain, so an afterEach that
        // throws still releases isolation — but PHPUnit skips `tearDown()` entirely
        // when `setUp()` itself throws, and this is armed INSIDE that setUp. A smoke
        // whose `beforeEach` fails after this point (a CP user that will not seed, a
        // blueprint that will not save) therefore leaves isolation installed. The
        // next `begin()` would then capture the THROWAWAY directories as the
        // originals and "restore" the rest of the run to a deleted tree. Ending
        // first makes that unreachable rather than merely unlikely.
        if (self::$root !== '') {
            self::end();
        }

        // With the watcher on, `Store::handleFileChanges()` re-scans and re-indexes
        // behind this class's back, putting a re-rooted store back into the mixed
        // state {@see CollectionTreeIsolatedTestCase} documents as destructive. The
        // watcher is off under `APP_ENV=testing` (`statamic.stache.watcher` is
        // `auto` -> `app()->isLocal()`); say so out loud rather than assume it.
        if (Stache::isWatcherEnabled()) {
            throw new \RuntimeException(
                'The Stache watcher is enabled, so a re-rooted store would be re-scanned mid-test. '
                .'Run the browser suite with APP_ENV=testing (phpunit.xml), or set STATAMIC_STACHE_WATCHER=false.'
            );
        }

        // Per-process, matching the throwaway roots in StacheIsolatedTestCase.
        // `tests/Browser` runs serially today, so TEST_TOKEN is unset and
        // {@see ThrowawayRoot} yields `seq-<pid>` — which is the segment that
        // matters here: two browser runs in one worktree would otherwise share
        // this root and the cleanup below would wipe the other's fixtures
        // mid-test (UAMS-Web/uams-statamic-archive#1957). The token branch costs nothing and covers both the
        // UAMS-Web/uams-statamic-archive#1136 within-band race and the UAMS-Web/uams-statamic-archive#1960 across-band one if the suite is
        // ever parallelized, since every segment ends in the pid either way.
        self::$root = storage_path('framework/testing/browser-writes/'.ThrowawayRoot::segment());

        // Start clean even when a prior run died before its teardown, so a stale
        // fixture can never be read back as this test's own.
        File::deleteDirectory(self::$root);

        self::isolateBlueprints();
        self::isolateStore(self::COLLECTIONS_STORE, 'collections', seedFrom: base_path('content/collections'));
        self::isolateStore(self::USERS_STORE, 'users', seedFrom: null);

        // Drop every value Statamic memoized while the real directories were still
        // in place. Re-rooting a store changes only what it READS FROM DISK; it
        // cannot reach what has already been computed into Blink during the boot in
        // `setUp()` — `collection-handles` above all, which `Collection::handles()`
        // memoizes and which would otherwise describe a directory this class has
        // just moved away from.
        Blink::flush();
    }

    /**
     * Restore the real directories and delete everything this process wrote.
     *
     * Safe to call when {@see begin()} did not run — a test that failed during
     * setup must not fail again in teardown.
     */
    public static function end(): void
    {
        if (self::$root === '') {
            return;
        }

        if (self::$originalBlueprintDirectory !== null) {
            Blueprint::setDirectory(self::$originalBlueprintDirectory);
            self::$originalBlueprintDirectory = null;
        }

        foreach (self::$originalStoreDirectories as $key => $directory) {
            $store = self::store($key);

            // Symmetrical with `isolateStore()`, and in the same order: forget what
            // the store derived from the throwaway BEFORE pointing it back, or every
            // real file collides with a key naming a directory about to be deleted.
            self::forgetStoreIndexes($store);

            $store->directory($directory);
        }

        self::$originalStoreDirectories = [];

        File::deleteDirectory(self::$root);
        self::$root = '';

        // The memos built while isolated describe the throwaway roots, exactly as
        // the ones dropped in `begin()` described the real ones.
        Blink::flush();
    }

    /**
     * Point `Blueprint::save()` at a throwaway seeded with the real blueprints.
     */
    private static function isolateBlueprints(): void
    {
        $original = Blueprint::directory();

        // A directory that is not the tracked source tree means this class would
        // isolate nothing while real writes carried on — a silent no-op is the one
        // outcome worse than a loud failure here.
        self::assertInsideRepository($original, 'the blueprint repository');

        self::$originalBlueprintDirectory = $original;

        $throwaway = self::$root.'/blueprints';
        File::ensureDirectoryExists($throwaway);
        File::copyDirectory($original, $throwaway);

        Blueprint::setDirectory($throwaway);
    }

    /**
     * Re-root one Stache store at a throwaway directory, optionally seeded with
     * the top-level YAML files of the real one.
     *
     * @param  string  $key  Stache store key.
     * @param  string  $segment  Directory name for this store under the throwaway root.
     * @param  string|null  $seedFrom  Real directory whose `*.yaml` files to copy, or null for an empty store.
     */
    private static function isolateStore(string $key, string $segment, ?string $seedFrom): void
    {
        $store = self::store($key);
        $original = (string) $store->directory();

        self::assertInsideRepository($original, "the {$key} store");

        self::$originalStoreDirectories[$key] = $original;

        // Forget everything derived from the directory being left, BEFORE moving
        // the store. Order is load-bearing in both directions — see {@see
        // forgetStoreIndexes()} for the two ways a surviving index corrupts the
        // re-root, one of which returns an EMPTY store rather than a wrong one.
        self::forgetStoreIndexes($store);

        $throwaway = self::$root.'/'.$segment;
        File::ensureDirectoryExists($throwaway);

        // Only the top-level configs, never the sibling directories: `collections`
        // and `entries` share one directory, and copying the entry trees would both
        // cost real time and give the entries store two homes.
        if ($seedFrom !== null && File::isDirectory($seedFrom)) {
            foreach (File::files($seedFrom) as $file) {
                if ($file->getExtension() === 'yaml') {
                    File::copy($file->getPathname(), $throwaway.'/'.$file->getFilename());
                }
            }
        }

        $store->directory($throwaway);
    }

    /**
     * Drop every index a Stache store derived from its current directory, so the
     * next traversal rebuilds from wherever the store is pointed next.
     *
     * Called before each move rather than after, because both indexes describe the
     * directory being LEFT and each corrupts the destination in its own way:
     *
     *  1. The PATH index is the mixed state {@see CollectionTreeIsolatedTestCase}
     *     documents as destructive — `getItem()` returns an item whose
     *     `initialPath` is the file in the old directory, and
     *     `ExistsAsFile::writeFile()` DELETES the initial path when it differs
     *     from the recomputed one, so the next save removes the old file rather
     *     than writing beside it.
     *  2. The KEY index is the one a SEEDED throwaway exposes, and it fails
     *     silently in the opposite direction. `Store::paths()` calls
     *     `$this->keys()->add($key, $path)` for every file it traverses and
     *     REJECTS any that raises `DuplicateKeyException` — so with the previous
     *     directory's keys still loaded, all 21 seeded collection configs collide
     *     with themselves and `paths()` returns EMPTY. Measured exactly that:
     *     `Collection::handles()` came back `[]` while the store's own directory
     *     held all 21 files.
     *
     * `Store::clear()` alone does not close the second one. `Keys::clear()` only
     * forgets the CACHE key — it never touches the `$keys` array on the in-memory
     * `Keys` object, which `Store::keys()` memoizes — so the stale map survives a
     * clear and goes on rejecting. `setKeys([])` is the public way to empty it.
     *
     * Scoped to one store, so the expensive `entries` store — which is not
     * re-rooted, and holds the real content these smokes render — never rebuilds.
     */
    private static function forgetStoreIndexes(Store $store): void
    {
        $store->clear();

        $keys = $store->keys();

        // Narrowed by control flow rather than annotation: `Store::keys()` carries
        // no return type upstream. A skip here would be the silent half of the very
        // failure this method exists to prevent, so it fails loudly instead.
        if (! $keys instanceof Keys) {
            throw new \RuntimeException(
                sprintf('The %s store\'s key index is a %s, so it cannot be emptied.', $store->key(), get_debug_type($keys))
            );
        }

        $keys->setKeys([]);
    }

    /**
     * Fail loudly when a directory this class is about to move is not inside the
     * repository, which would mean it is isolating something that was never at risk.
     */
    private static function assertInsideRepository(string $directory, string $description): void
    {
        $normalized = str_replace('\\', '/', $directory);
        $root = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        if (! str_starts_with($normalized, $root)) {
            throw new \RuntimeException(
                sprintf('%s is rooted at "%s", which is outside "%s" — this class would isolate nothing.', $description, $normalized, $root)
            );
        }
    }

    /**
     * Resolve a Stache store by key.
     */
    private static function store(string $key): Store
    {
        $store = Stache::stores()->get($key);

        if (! $store instanceof Store) {
            throw new \RuntimeException(sprintf('The Stache "%s" store is not registered, so there is nothing to isolate.', $key));
        }

        return $store;
    }
}
