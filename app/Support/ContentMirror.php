<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Statamic\Facades\YAML;
use Statamic\Yaml\ParseException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

/**
 * Builds and maintains the disposable copy of `content/` that the test env
 * reads through (UAMS-Web/uams-statamic-archive#1916).
 *
 * Tests persist real Statamic items — entries, terms, globals, collection
 * configs, asset containers — and dual-write asset metadata through Eloquent.
 * Pointed at `content/`, every one of those writes lands in the live git
 * submodule; a run that passes cleans up after itself, but a band killed at its
 * bound strands whatever was in flight, and a stray file is boot-visible input
 * the next `statamic:stache:warm` indexes as real content (UAMS-Web/uams-statamic-archive#1845). Reading
 * through a copy makes the guarantee structural rather than per-test.
 *
 * **Two entry points, deliberately not one.** The difference is what happens
 * when the mirror no longer matches `content/`:
 *
 *  - {@see rebuildIfStale()} — for `test:seed-stache`, which runs *between*
 *    bands under its own single-flight lock. A drifted mirror there is last
 *    band's litter, and rebuilding is the point.
 *  - {@see ensureExists()} — for a test process booting. It builds only when the
 *    mirror is **absent**, never when it has drifted: by then the drift is other
 *    workers' live fixtures, and rebuilding would delete files a concurrent
 *    worker is reading. Its job is only to keep a bare `php artisan test` (no
 *    seed step) working.
 *
 * Both take a lock, so concurrent boots cannot half-build the tree beneath each
 * other. Callers return an error string rather than throwing: the console
 * command renders it, and the test bootstrap has no console to render to.
 *
 * Both also take an explicit source and mirror, defaulting to the real pair.
 * The two production callers always want the default; the arguments exist so a
 * test can exercise the staleness rules on a tree of its own, since asserting
 * them against the shared mirror would mean deleting and re-copying the tree
 * every other test in the process is reading.
 */
final class ContentMirror
{
    /**
     * Rebuild the mirror unless it already matches its source exactly.
     *
     * @param  string|null  $source  Defaults to the `content/` submodule
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     * @return string|null An operator-facing error message, or `null` on success
     */
    public static function rebuildIfStale(Filesystem $files, ?string $source = null, ?string $mirror = null): ?string
    {
        return self::withLock(
            $files,
            $source,
            $mirror,
            static fn (string $from, string $to): ?string => self::isCurrent($files, $from, $to)
                ? null
                : self::rebuild($files, $from, $to)
        );
    }

    /**
     * Build the mirror only if it is missing, leaving a drifted one alone.
     *
     * @param  string|null  $source  Defaults to the `content/` submodule
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     * @return string|null An operator-facing error message, or `null` on success
     */
    public static function ensureExists(Filesystem $files, ?string $source = null, ?string $mirror = null): ?string
    {
        return self::withLock(
            $files,
            $source,
            $mirror,
            // Present *and stamped*. The stamp is written only after a complete
            // copy, so it answers "was this mirror finished?" where a file count
            // answers only "is something here" — an interrupted build leaves a
            // partial tree that passes the latter and starves the process, the
            // same shape as an incomplete snapshot copy (UAMS-Web/uams-statamic-archive#1740). It is also O(1),
            // which matters: ~12 workers boot at once behind this lock, and a
            // 3,233-file walk each would serialize seconds onto every band.
            static fn (string $from, string $to): ?string => $files->isDirectory($to) && $files->exists($to.'.stamp')
                ? null
                : self::rebuild($files, $from, $to)
        );
    }

    /**
     * Whether the mirror records a different `content/` than the one on disk now.
     *
     * Read-only and lock-free by design. This **reports** drift; it never
     * restores, which is what separates it from the approach `docs/DECISIONS.md`
     * rejects under UAMS-Web/uams-statamic-archive#1916 ("Rebuild the mirror on drift at process boot").
     * Restoring mid-band deletes files a concurrent worker is reading; comparing
     * two fingerprints deletes nothing, so the safety property that rejection
     * protects is untouched.
     *
     * Compares the **source** side only. The mirror side legitimately drifts all
     * band long — that is where workers write their fixtures — so including it
     * would report every ordinary run as stale. `content/` is the side nothing
     * should be writing to, which is what makes a difference there meaningful.
     *
     * **Blind spots, stated because a guard whose limits are unknown invites
     * more trust than it earns:**
     *
     *  - An edit preserving every file's size *and* mtime to the second is
     *    invisible, since the fingerprint is a stat rather than a content hash.
     *    No test write does this; a scripted restore of an identical tree could.
     *  - A missing or unparseable stamp reports *no drift* rather than drift.
     *    That case belongs to {@see ensureExists()}, and a rebuild in flight has
     *    deliberately removed the stamp — failing on it would red a band for a
     *    build that is working correctly.
     *  - An uninitialized `content/` is not reached: callers gate on
     *    {@see ContentSubmodule::isInitialized()} first, because an
     *    absent submodule is a documented skip rather than a stale mirror.
     *
     * @param  string|null  $source  Defaults to the `content/` submodule
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     * @return string|null An operator-facing message naming the fix, or `null` when current
     */
    public static function sourceDrift(Filesystem $files, ?string $source = null, ?string $mirror = null): ?string
    {
        $mirror ??= ContentRoot::path(Config::string('app.env'));
        $source ??= base_path('content');

        $stampFile = $mirror.'.stamp';

        if (! $files->isDirectory($mirror) || ! $files->exists($stampFile)) {
            return null;
        }

        $stored = json_decode($files->get($stampFile), true);

        if (! \is_array($stored) || ! \is_string($stored['source'] ?? null)) {
            return null;
        }

        if ($stored['source'] === self::fingerprint($files, $source)) {
            return null;
        }

        return sprintf(
            'The content mirror at %s was built from a different %s than the one on disk now, so '
            ."every test reading content is reading a tree that no longer exists.\n"
            .'Rebuild it with: php artisan test:seed-stache',
            $mirror,
            $source
        );
    }

    /**
     * Explain a YAML parse failure in the mirror, telling a torn copy from a real content defect (UAMS-Web/uams-statamic-archive#2762).
     *
     * A torn mirror file surfaces as a Statamic `ParseException` several frames into the
     * Stache, in whatever suite happened to boot it, and the message names only the YAML
     * file. That reads as a defect in the branch under test. This names the cause instead.
     * It compares the failing mirror file with its counterpart in `content/`:
     *
     *  - **The counterpart parses, or there is none**: the mirror is torn. It was rewritten
     *    while this process read it, or a band was killed mid-write. The message names the
     *    mirror and the command that rebuilds it.
     *  - **The counterpart fails too**: the defect is real and lives in `content/`. The
     *    message says so, so a broken commit is never reported as a tear.
     *
     * Read-only. It rebuilds nothing, for the reason {@see ensureExists()} gives.
     *
     * @param  Throwable  $failure  The exception to explain, searched with its previous chain
     * @param  string|null  $source  Defaults to the `content/` submodule
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     * @return string|null The explanation, or `null` when the failure is not a parse error in the mirror
     */
    public static function parseFailureDiagnosis(Throwable $failure, ?string $source = null, ?string $mirror = null): ?string
    {
        $parse = $failure;

        while ($parse instanceof Throwable && ! $parse instanceof ParseException) {
            $parse = $parse->getPrevious();
        }

        if (! $parse instanceof ParseException) {
            return null;
        }

        $mirror ??= ContentRoot::path(Config::string('app.env'));
        $source ??= base_path('content');

        $mirrorRoot = realpath($mirror);
        $sourceRoot = realpath($source);

        // Outside the test env the two are one directory, and there is no mirror to blame
        if ($mirrorRoot === false || $sourceRoot === false || $mirrorRoot === $sourceRoot) {
            return null;
        }

        $file = realpath($parse->getFile());

        if ($file === false || ! str_starts_with($file, $mirrorRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $relative = substr($file, \strlen($mirrorRoot) + 1);
        $counterpart = $sourceRoot.DIRECTORY_SEPARATOR.$relative;

        if (is_file($counterpart) && ! self::parses($counterpart)) {
            return sprintf(
                '%s is invalid YAML in content/ itself, not only in the test mirror (%s). '
                .'This is a content defect: fix the file in the content submodule.',
                $counterpart,
                $parse->getMessage()
            );
        }

        return sprintf(
            'The test content mirror at %s is torn: %s does not parse, while %s. '
            .'The mirror was most likely rewritten while this process was reading it '
            .'(test:seed-stache run alongside running tests, or a test run killed mid-write). '
            .'Rebuild it with no tests running: php artisan test:seed-stache',
            $mirrorRoot,
            $relative,
            is_file($counterpart)
                ? sprintf('the same file in %s does', $sourceRoot)
                : sprintf('no such file exists in %s, so it was written into the mirror by a test', $sourceRoot)
        );
    }

    /**
     * Whether a content file parses as Statamic YAML, front matter included.
     */
    private static function parses(string $file): bool
    {
        try {
            YAML::file($file)->parse();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve and validate both paths, then run `$build` under an exclusive lock.
     *
     * The lock is blocking rather than `LOCK_NB`: a second caller wants the
     * mirror the first is building, so waiting for it is the correct outcome —
     * unlike `test:seed-stache`'s own lock, where a second *build* is the thing
     * being refused.
     *
     * @param  callable(string, string): (string|null)  $build  Receives (source, mirror)
     * @return string|null An operator-facing error message, or `null` on success
     */
    private static function withLock(Filesystem $files, ?string $source, ?string $mirror, callable $build): ?string
    {
        $mirror ??= ContentRoot::path(Config::string('app.env'));
        $source ??= base_path('content');

        // The mirror resolving to its own source means the testing branch in
        // `App\Support\ContentRoot` did not take, and the rebuild below would be
        // about to wipe `content/` itself. Refuse rather than run — this is the
        // one failure here that destroys tracked data instead of a copy of it.
        if (realpath($mirror) !== false && realpath($mirror) === realpath($source)) {
            return sprintf(
                'The content mirror and its source are the same directory (%s), so a rebuild would '
                .'wipe the source. Run with APP_ENV=testing.',
                $source
            );
        }

        if (! $files->isDirectory($source)) {
            return sprintf(
                'There is no content to mirror at %s. If that is the content/ submodule, '
                .'run: git submodule update --init content',
                $source
            );
        }

        $files->ensureDirectoryExists(\dirname($mirror));

        // `c` creates without truncating, so concurrent openers lock one inode.
        $lock = fopen($mirror.'.lock', 'c');

        if ($lock === false) {
            return sprintf('Could not open the content mirror lock at %s.lock.', $mirror);
        }

        try {
            flock($lock, LOCK_EX);

            return $build($source, $mirror);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Whether the mirror is a faithful copy of `content/` right now.
     *
     * Both sides are fingerprinted, and both have to match. The source side
     * catches a `content/` submodule that moved; the mirror side catches litter
     * a previous band wrote *into* the mirror — which is the whole reason the
     * mirror exists, and would otherwise be reused as though it were content.
     *
     * A stat fingerprint (path + size + mtime) rather than a content hash: it
     * costs ~0.5s per side against ~3.7s for the delete-and-copy it replaces,
     * where hashing 9 MB would cost more than the copy. The residual blind spot
     * is an overwrite that preserves both size and mtime to the second, which no
     * test write does.
     */
    private static function isCurrent(Filesystem $files, string $source, string $mirror): bool
    {
        if (! $files->isDirectory($mirror)) {
            return false;
        }

        $stampFile = $mirror.'.stamp';

        if (! $files->exists($stampFile)) {
            return false;
        }

        $stored = json_decode($files->get($stampFile), true);

        return \is_array($stored)
            && ($stored['source'] ?? null) === self::fingerprint($files, $source)
            && ($stored['mirror'] ?? null) === self::fingerprint($files, $mirror);
    }

    /**
     * Replace the mirror wholesale, verify the copy, and stamp the result.
     *
     * The stamp is removed *before* the copy, so a build that dies partway
     * leaves no stamp vouching for a half-written tree — the next caller sees a
     * missing stamp and rebuilds.
     *
     * **The copy is verified rather than assumed (UAMS-Web/uams-statamic-archive#1740, UAMS-Web/uams-statamic-archive#1978).**
     * `copyDirectory()` reports a failure two ways depending on the error
     * handler, and a `copy()` that returns `true` without writing reports none at
     * all — which is how a worker came to run a whole band against a two-file
     * Stache. Here the consequence is worse than a cold cache: this tree *is* the
     * content, so a short copy is a checkout that silently lost entries, and
     * every test that reads one then fails pointing anywhere but at the copy. The
     * file lists the stamp needs answer the question anyway, so checking costs
     * one extra walk on a ~4s operation.
     *
     * @return string|null An operator-facing error message, or `null` on success
     */
    private static function rebuild(Filesystem $files, string $source, string $mirror): ?string
    {
        $stampFile = $mirror.'.stamp';

        // Guarded rather than unconditional: `Filesystem::delete()` reaches
        // `@unlink()`, and the test env's error handler promotes the suppressed
        // warning anyway — a first build would report a warning for a stamp that
        // was never supposed to exist yet.
        if ($files->exists($stampFile)) {
            $files->delete($stampFile);
        }

        // Listed before the wipe, because the wipe is what makes a mistake here
        // expensive. An empty source is a present-but-uninitialized submodule
        // mount point, which would otherwise yield an empty mirror carrying a
        // stamp that vouches for it.
        $expected = self::relativeFiles($files, $source);

        if ($expected === []) {
            return sprintf(
                'There is nothing to mirror at %s — the directory is empty. If that is the content/ '
                .'submodule, run: git submodule update --init content',
                $source
            );
        }

        $files->deleteDirectory($mirror);
        $files->ensureDirectoryExists($mirror);

        // `copyDirectory()` walks the tree itself, so the submodule's own `.git`
        // file comes along; it is inert here and costs one entry.
        $copied = $files->copyDirectory($source, $mirror);
        $actual = self::relativeFiles($files, $mirror);
        $missing = array_diff_key($expected, $actual);

        if (! $copied || $missing !== []) {
            $files->deleteDirectory($mirror);

            $reason = $missing === []
                ? 'copyDirectory() reported failure'
                : sprintf('%d of %d files missing, e.g. %s', \count($missing), \count($expected), array_key_first($missing));

            return sprintf(
                'Copying %s into the mirror at %s was incomplete (%s), so the stores would read a '
                .'partial tree. The partial copy has been removed; re-run test:seed-stache.',
                $source,
                $mirror,
                $reason
            );
        }

        $files->put($stampFile, (string) json_encode([
            'source' => self::digest($expected),
            'mirror' => self::digest($actual),
        ]));

        return null;
    }

    /**
     * A stable digest of every file in a tree, hidden files included.
     */
    private static function fingerprint(Filesystem $files, string $directory): string
    {
        return self::digest(self::relativeFiles($files, $directory));
    }

    /**
     * Every file in a tree, keyed by forward-slashed relative path.
     *
     * Hidden files count: `copyDirectory()` copies them (it sets
     * `ignoreDotFiles(false)`), so excluding them would make a `.gitignore`
     * change invisible to the staleness check. Keyed rather than listed so the
     * completeness check in {@see rebuild()} is one `array_diff_key()`.
     *
     * @return array<string, string> Relative path => `size:mtime`
     */
    private static function relativeFiles(Filesystem $files, string $directory): array
    {
        $entries = [];

        foreach ($files->allFiles($directory, true) as $file) {
            /** @var SplFileInfo $file */
            $entries[str_replace('\\', '/', $file->getRelativePathname())] = $file->getSize().':'.$file->getMTime();
        }

        ksort($entries);

        return $entries;
    }

    /**
     * Collapse a file list into one comparable string.
     *
     * @param  array<string, string>  $entries  Relative path => `size:mtime`
     */
    private static function digest(array $entries): string
    {
        $lines = [];

        foreach ($entries as $path => $stat) {
            $lines[] = $path.':'.$stat;
        }

        return sha1(implode("\n", $lines));
    }
}
