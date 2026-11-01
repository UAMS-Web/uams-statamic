<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ContentMirror;
use App\Support\ContentMirrorBandLock;
use App\Support\ContentRoot;
use App\Support\StacheSeedStamp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Builds the shared Stache seed the parallel test suite bootstraps from (UAMS-Web/uams-statamic-archive#1581).
 *
 * The parallel suite (paratest) gives every worker its own per-worker file cache
 * (`config/cache.php`, the UAMS-Web/uams-statamic-archive#1136 isolation), so without this each of the ~12
 * workers force-runs `statamic:stache:warm` at startup — and because all warm
 * concurrently they saturate the disk, ballooning a 21s warm to ~168s each (the
 * dominant parallel cost, UAMS-Web/uams-statamic-archive#1537). This command runs the warm exactly once, then
 * snapshots the resulting cache into `storage/framework/stache-seed`.
 * Each worker then copies that snapshot into its own per-worker cache dir instead
 * of re-warming (see `Tests\CreatesApplication::seedStacheFromSnapshot()`) — a
 * cheap file copy per worker, isolation preserved (each worker seeds only its
 * own dir).
 *
 * Run once, in the test env, before the parallel suite:
 * `DB_DATABASE=:memory: APP_ENV=testing php artisan test:seed-stache` (wired into
 * the phpunit job in `ci.yml` and `scripts/local-ci.mjs`, mirroring the
 * `route:cache` pre-step). The `:memory:` mirrors `phpunit.xml`: the warm builds
 * computed-field indexes that probe the database (e.g. `Schema::hasTable`), so it
 * needs a connectable DB — the tests use in-memory SQLite, and a fresh checkout
 * has no sqlite file.
 *
 * **The wipe below is also what heals a hard-killed band (UAMS-Web/uams-statamic-archive#1876).** A killed
 * worker can leave a torn write in its own `data/<TEST_TOKEN>-<pid>` cache, and a torn
 * Stache index reads as a starved collection rather than as a miss. Nothing has to
 * detect that, because the tokenized path is a *subdirectory* of the token-less
 * one: this command runs with `TEST_TOKEN` unset, so `cache.stores.file.path`
 * resolves to `storage/framework/testing/cache/data` and the delete takes every
 * `data/<token>` tree with it. `CreatesApplication::seedStacheFromSnapshot()` then
 * resets each worker's dir again before copying and verifies the file count, so a
 * partial cache cannot survive a band start twice over. Keep the paths nested if
 * either is ever moved — siblings would break this silently.
 *
 * The snapshot persists so workers can copy it, and is NOT re-validated against
 * `content/` on consume — so a stale snapshot (content changed since it was
 * built) would seed pre-change content. CI and `scripts/local-ci.mjs` rebuild it
 * on every run (a fresh checkout / throwaway worktree), so they are always
 * current; the only exposure is running `pest --parallel` directly in a
 * persistent checkout after editing `content/` — rebuild with `test:seed-stache`
 * first, or delete `storage/framework/stache-seed` to fall back to a warm.
 *
 * **Single-flight, enforced by an exclusive lock (UAMS-Web/uams-statamic-archive#1978).** Both directories are
 * shared and both are deleted and rebuilt, so two concurrent runs conflict
 * destructively rather than racing on a `mkdir`. Reproduced here: one run's
 * delete tore the tree the other was mid-copy into, and the loser died inside
 * `Filesystem::deleteDirectory()` with a raw `FilesystemIterator` trace naming
 * neither this command nor the snapshot. The second invocation now refuses up
 * front, before touching either directory. The lock is an `flock()` on a handle
 * held for the build, so the OS drops it if the process is killed — there is no
 * stale lock to clear by hand. It is per-checkout (`storage_path()`), so a band
 * here and the `local-ci` runner in its own worktree never contend.
 *
 * **That lock refuses a second seed, not a running band, so a second lock does (UAMS-Web/uams-statamic-archive#2762).**
 * The wipe above is deliberately un-tokenized, so it takes every live worker's
 * `data/<TEST_TOKEN>-<pid>` cache with it, and the mirror rebuild deletes files a
 * band is reading. That is the UAMS-Web/uams-statamic-archive#1876 healing working as designed, between bands. Run
 * alongside one, it tore a globals file mid-read, which surfaced as a YAML
 * `ParseException` in unrelated form suites. Every test process now holds a shared
 * {@see ContentMirrorBandLock}, and this command refuses when it cannot take that
 * lock exclusively. It holds the exclusive lock for the whole build, so a test
 * process that starts mid-seed waits for it instead of reading a half-built mirror.
 * Seed *between* bands, never alongside one — see the parity checklist in
 * `.claude/rules/measurement-parity.md`.
 */
#[Description('Warm the Stache once and snapshot it so parallel test workers seed from it instead of each re-warming (UAMS-Web/uams-statamic-archive#1581)')]
#[Signature('test:seed-stache')]
class SeedTestStache extends Command
{
    public function handle(Filesystem $files): int
    {
        // Guard: this writes throwaway test caches and must never run against a
        // real environment. It is only meaningful in the test env.
        if (! $this->getLaravel()->environment('testing')) {
            $this->components->error('test:seed-stache is a test-harness command; run it with APP_ENV=testing.');

            return self::FAILURE;
        }

        // Single-flight (UAMS-Web/uams-statamic-archive#1978). The lock file is a sibling of the snapshot dir
        // rather than inside it, since the build deletes that dir wholesale.
        // `c` creates without truncating, so a concurrent opener sees the same
        // inode to lock against.
        $lock = fopen(storage_path('framework/stache-seed.lock'), 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock);
            }

            $this->components->error(
                'Another test:seed-stache is already building the snapshot in this checkout. '
                .'The Stache seed is shared, so a second build would corrupt the first; '
                .'wait for it to finish, then re-run.'
            );

            return self::FAILURE;
        }

        try {
            // No band may be reading the mirror while it is deleted and re-copied (UAMS-Web/uams-statamic-archive#2762)
            $band = ContentMirrorBandLock::tryExclusive();

            if ($band === null) {
                $this->components->error(sprintf(
                    'A test process is reading the content mirror at %s in this checkout. '
                    .'Rebuilding it now would delete files that process is reading, which surfaces as '
                    .'YAML parse errors in unrelated suites. Wait for the running tests to finish, then re-run test:seed-stache.',
                    ContentRoot::path(Config::string('app.env'))
                ));

                return self::FAILURE;
            }

            try {
                return $this->buildSeed($files);
            } finally {
                ContentMirrorBandLock::releaseExclusive($band);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Bring the disposable mirror of `content/` that the test env reads through
     * up to date (UAMS-Web/uams-statamic-archive#1916).
     *
     * Must run **before** the warm below, not after it. The mirror and the
     * snapshot are two halves of one state: `config/statamic/stache.php` points
     * every content-backed store at the mirror while `APP_ENV=testing`, so a
     * warm against a stale or absent mirror indexes the wrong paths, and a store
     * whose cached index disagrees with its directory is the mixed state that
     * deletes committed files rather than merely misdirecting writes.
     *
     * `rebuildIfStale()` rather than an unconditional rebuild: the mirror is
     * fingerprinted on both sides, so last band's litter and a `content/` that
     * moved both force a rebuild, while a genuinely current mirror is reused for
     * the cost of two stat walks (~1s against ~3.7s to delete and re-copy 3,233
     * files). Between-band correctness is unchanged; what it buys is a
     * `test:seed-stache` that is cheap to re-run.
     *
     * @return int|null A failure exit code, or `null` when the mirror is ready.
     */
    private function seedContentMirror(Filesystem $files): ?int
    {
        $failure = ContentMirror::rebuildIfStale($files);

        if ($failure !== null) {
            $this->components->error($failure);

            return self::FAILURE;
        }

        return null;
    }

    /**
     * Wipe both directories, warm the Stache once, and snapshot it.
     *
     * Called only with the single-flight lock held; see the class docblock.
     */
    private function buildSeed(Filesystem $files): int
    {
        // The default `data/` file cache the warm writes to (TEST_TOKEN is unset in
        // this single process) and the snapshot dir workers copy from. Both are
        // cleared first so a stale entry can never ride into the snapshot.
        $cacheDir = Config::string('cache.stores.file.path');
        $seedDir = storage_path('framework/stache-seed');

        // Wipe the cache dir, then restore its tracked `.gitignore` verbatim, so a
        // seed build clears stale entries without dirtying the working tree.
        $gitignore = $cacheDir.DIRECTORY_SEPARATOR.'.gitignore';
        $gitignoreContents = $files->exists($gitignore) ? $files->get($gitignore) : null;
        $files->deleteDirectory($cacheDir);
        $files->ensureDirectoryExists($cacheDir);
        if ($gitignoreContents !== null) {
            $files->put($gitignore, $gitignoreContents);
        }

        $files->deleteDirectory($seedDir);

        // Drop the root stamp along with the snapshot it vouches for, so a build
        // that dies partway cannot leave one asserting a root nothing indexed.
        StacheSeedStamp::clear($files);

        if (($mirrorFailure = $this->seedContentMirror($files)) !== null) {
            return $mirrorFailure;
        }

        $this->call('statamic:stache:warm');

        // Snapshot the warmed cache. Each worker copies this into its own
        // per-worker dir at bootstrap rather than re-running the warm.
        //
        // Verify the copy rather than assume it (UAMS-Web/uams-statamic-archive#1740): `copyDirectory()` aborts
        // at the first failed `copy()` and leaves the partial tree behind. A
        // truncated snapshot here is worse than one truncated per-worker, because
        // every worker inherits it and each one then looks correctly seeded — so
        // fail the command instead of writing a snapshot the whole band trusts.
        //
        // Check every file the copy was asked for against the destination, rather
        // than comparing the two directories' file counts after the fact (UAMS-Web/uams-statamic-archive#1978).
        // The cache dir is shared and un-tokenized, so *any* other app boot in this
        // checkout writes into it — a bare `php artisan inspire` alongside a seed
        // build adds `entries_by_schema_type/<hash>` — and a live recount then read
        // that as a short copy and deleted a complete snapshot. Capturing the source
        // list up front is also the stricter test: it names which files are missing
        // instead of trusting two totals to agree.
        //
        // `allFiles()` ignores dotfiles, so the `.gitignore` restored above is
        // excluded from the expected set and its absence in the seed is not a miss.
        $expected = array_map(
            static fn (SplFileInfo $file): string => $file->getRelativePathname(),
            $files->allFiles($cacheDir)
        );

        // An empty warm is the maximally truncated case, and a verification built
        // on comparing totals waved it through: nothing to copy means nothing
        // missing. `stacheSnapshotIsUsable()` then reads the empty snapshot as no
        // snapshot and every worker falls back to a ~168s warm — the whole cost
        // UAMS-Web/uams-statamic-archive#1581 removed, silently, off a command that reported success.
        if ($expected === []) {
            $files->deleteDirectory($seedDir);

            $this->components->error(
                'The Stache warm produced no cache files, so there is nothing to snapshot. '
                .'Check that the content submodule is initialized, then re-run test:seed-stache.'
            );

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($seedDir);
        $copied = $files->copyDirectory($cacheDir, $seedDir);

        $missing = array_values(array_filter(
            $expected,
            static fn (string $relative): bool => ! is_file($seedDir.DIRECTORY_SEPARATOR.$relative)
        ));

        if (! $copied || $missing !== []) {
            $files->deleteDirectory($seedDir);

            $reason = $missing === []
                ? 'copyDirectory() reported failure'
                : sprintf('%d of %d files missing, e.g. %s', count($missing), count($expected), $missing[0]);

            $this->components->error(sprintf(
                'Stache seed copy was incomplete (%s); refusing to write a partial snapshot. '
                .'Re-run test:seed-stache.',
                $reason
            ));

            return self::FAILURE;
        }

        // Record the content root this snapshot was warmed against, so a process
        // whose stores point somewhere else refuses to seed from it — see
        // {@see StacheSeedStamp} for why that mismatch destroys data.
        StacheSeedStamp::write($files, ContentRoot::path(Config::string('app.env')));

        $this->components->info(sprintf('Stache seed written to %s.', $seedDir));

        return self::SUCCESS;
    }
}
