<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses a browser run whose built assets are older than their sources (UAMS-Web/uams-statamic-archive#2252).
 *
 * A `tests/Browser` fixture resolves its script tag through
 * `public/build/manifest.json`, so the suite tests whatever JS was last built
 * rather than the tree it is checked out at. Nothing compares the two, and the
 * failure surfaces as whatever the old JS does differently — an undefined Alpine
 * component, a missing binding, a state that never changes. That is
 * indistinguishable from the code being wrong, and it read as a regression in a
 * just-merged fix on two machines in one evening:
 *
 *  - `josh-home`, manifest 3 weeks stale: four `PublicFormControlsTest` failures
 *    naming the exact fieldtype UAMS-Web/uams-statamic-archive#2065 had fixed, so it read as "that regressed".
 *  - `josh-office`, manifest a month stale: `SignatureConditionalRebuildTest`
 *    failing an assertion in a test merged the same day, so it read as
 *    "UAMS-Web/uams-statamic-archive#2063 shipped broken".
 *
 * **Local runs only, by design.** `scripts/local-ci.mjs`'s chain runs `build` at
 * job 6 and `browser` at job 12, so a gated run cannot reach this state — six
 * merged-state gates were measured doing exactly that. The exposure is a bare
 * `vendor/bin/pest tests/Browser`, which is how a browser test is normally
 * written. Putting the check in the chain would arm it against a state the chain
 * cannot produce, so it could never be observed failing there.
 *
 * **It does not build.** A suite that silently repairs its own inputs cannot
 * report on the drift it exists to surface; failing with the command is the job.
 */
final class BrowserAssetFreshness
{
    /**
     * Armed once per process. The check is cheap but not free — it walks
     * `resources/js` — and the requirement is that it fires before any browser
     * test executes, not that it re-runs for each of them.
     */
    private static bool $checked = false;

    /**
     * Throw unless the built manifest is at least as new as its sources.
     *
     * @param  string|null  $sourceDir  Defaults to `resources/js`
     * @param  string|null  $manifest  Defaults to `public/build/manifest.json`
     *
     * @throws RuntimeException When the manifest is missing or older than a source
     */
    public static function assertFresh(?string $sourceDir = null, ?string $manifest = null): void
    {
        // Explicit paths mean a test drives its own fixture rather than the real
        // tree, so the guard can be watched failing without backdating this
        // checkout's assets.
        $driven = $sourceDir !== null || $manifest !== null;

        if (! $driven && self::$checked) {
            return;
        }

        if (! $driven) {
            self::$checked = true;
        }

        $sourceDir ??= base_path('resources/js');
        $manifest ??= public_path('build/manifest.json');

        if (! is_file($manifest)) {
            throw new RuntimeException(
                "Browser assets have never been built: {$manifest} does not exist.\n"
                .'Run: npm run build'
            );
        }

        $manifestTime = (int) filemtime($manifest);
        $newest = self::newestSourceTime($sourceDir);

        if ($newest === null || $newest <= $manifestTime) {
            return;
        }

        throw new RuntimeException(sprintf(
            "Browser assets are stale, so this run would test JS that is not in this tree.\n"
            ."  newest source  %s  (%s)\n"
            ."  built manifest %s  (%s)\n"
            .'Run: npm run build',
            date('Y-m-d H:i:s', $newest),
            $sourceDir,
            date('Y-m-d H:i:s', $manifestTime),
            $manifest
        ));
    }

    /**
     * The newest mtime of any file under the given directory, or null when the
     * directory is absent or empty.
     *
     * Absent rather than throwing: a checkout with no `resources/js` has nothing
     * to be stale against, and inventing a failure there would make the guard fire
     * on a state that is not the one it exists for.
     */
    private static function newestSourceTime(string $directory): ?int
    {
        if (! is_dir($directory)) {
            return null;
        }

        $newest = null;

        /** @var \SplFileInfo $file */
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            ) as $file
        ) {
            if (! $file->isFile()) {
                continue;
            }

            $time = (int) $file->getMTime();

            if ($newest === null || $time > $newest) {
                $newest = $time;
            }
        }

        return $newest;
    }
}
