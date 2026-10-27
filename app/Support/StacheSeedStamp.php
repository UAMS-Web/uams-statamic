<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Records which content root the shared Stache snapshot was warmed against.
 *
 * **This exists because the mismatch it detects deletes committed files.** A
 * snapshot holds absolute paths, and a store whose directory disagrees with its
 * cached view is the destructive mixed state UAMS-Web/uams-statamic-archive#1873 documents:
 * `ExistsAsFile::writeFile()` recomputes `buildPath()` from the directory and
 * `File::delete()`s the `initialPath` it no longer matches, so the committed file
 * is *moved* rather than the write misdirected.
 *
 * Since UAMS-Web/uams-statamic-archive#1916 roots content at a disposable mirror under test, an ordinary branch
 * switch reaches that state — build a snapshot on a branch without the mirror and
 * it indexes `content/`; check out one with it and the stores read the mirror.
 * That cost `content/trees/collections/default/pages.yaml` (166 lines) twice
 * before this stamp existed, the second time via nothing more exotic than running
 * `main`'s suite and checking the branch back out.
 *
 * Nothing about a snapshot reveals which root it indexes, so it says so here, in
 * a file beside the snapshot directory rather than inside it — every worker
 * copies that directory wholesale into its own cache, and a stray entry there
 * would ride along as a cache key.
 */
final class StacheSeedStamp
{
    /**
     * Absolute path to the stamp file.
     */
    public static function path(): string
    {
        return storage_path('framework/stache-seed.root');
    }

    /**
     * Record the root a freshly built snapshot indexes.
     *
     * @param  string  $root  The content root the warm ran against
     */
    public static function write(Filesystem $files, string $root): void
    {
        $files->put(self::path(), $root);
    }

    /**
     * Drop the stamp, so a build that dies partway leaves none vouching for a
     * snapshot nothing indexed.
     */
    public static function clear(Filesystem $files): void
    {
        // Guarded: `Filesystem::delete()` reaches `@unlink()`, and the test env's
        // error handler promotes the suppressed warning anyway.
        if ($files->exists(self::path())) {
            $files->delete(self::path());
        }
    }

    /**
     * Whether the snapshot on disk indexes the root given.
     *
     * A **missing** stamp is a mismatch, not a pass: it means the snapshot
     * predates this check, and nothing about it says which root it holds.
     * Rejecting costs one warm; trusting costs committed content.
     *
     * @param  string  $root  The content root this process's stores read
     */
    public static function matches(Filesystem $files, string $root): bool
    {
        if (! $files->exists(self::path())) {
            return false;
        }

        return trim($files->get(self::path())) === $root;
    }
}
