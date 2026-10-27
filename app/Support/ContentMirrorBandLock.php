<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Tells `test:seed-stache` that a test process is reading the content mirror (UAMS-Web/uams-statamic-archive#2762).
 *
 * `test:seed-stache` deletes and re-copies the shared, un-tokenized mirror at
 * `storage/framework/testing/content-mirror`. Run while a band is reading it, the
 * band's workers read files mid-rewrite, and a torn globals file then surfaces as a
 * YAML `ParseException` in suites that have nothing to do with it. The seed's own
 * single-flight lock refuses a second *seed*; it cannot see a band.
 *
 * So every test process holds a **shared** lock on a sibling lock file for its
 * whole lifetime ({@see hold()}), and the seed takes an **exclusive** lock on the
 * same file without blocking ({@see tryExclusive()}). Any live test process makes
 * the exclusive lock fail, and the seed refuses. A test process that boots while a
 * seed holds the exclusive lock waits for it, so it never reads a half-built mirror.
 *
 * `flock()` locks are dropped by the operating system when the holding process
 * exits, however it exits, so a killed band leaves nothing to clear by hand. The
 * locks are per checkout, because the mirror is: a band in one worktree never
 * blocks a seed in another.
 */
final class ContentMirrorBandLock
{
    /**
     * The handle this process holds its shared lock on, or `null` when it holds none.
     *
     * @var resource|null
     */
    private static $held;

    /**
     * The band lock file for a mirror.
     *
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     */
    public static function path(?string $mirror = null): string
    {
        return ($mirror ?? ContentRoot::path(Config::string('app.env'))).'.band.lock';
    }

    /**
     * Hold a shared lock for the rest of this process, waiting out a seed in progress.
     *
     * Idempotent: a process boots the application once per test, and only the first
     * call opens the lock.
     *
     * Throws rather than continuing unlocked (UAMS-Web/uams-statamic-archive#2773): a process holding no shared
     * lock is invisible to the seed, which would then rewrite the mirror beneath it.
     *
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     *
     * @throws RuntimeException When the lock file cannot be opened or locked
     */
    public static function hold(?string $mirror = null): void
    {
        if (self::$held !== null) {
            return;
        }

        $path = self::path($mirror);

        // Open the lock file, failing exactly as the seed's exclusive path does
        $handle = self::open($path);

        // Wait out any seed in progress, and refuse to run with no lock taken
        if (! flock($handle, LOCK_SH)) {
            fclose($handle);

            throw new RuntimeException(sprintf('Could not take the shared content mirror band lock at %s.', $path));
        }

        self::$held = $handle;
    }

    /**
     * Give up this process's shared lock, for a test that runs `test:seed-stache` itself.
     */
    public static function release(): void
    {
        if (self::$held === null) {
            return;
        }

        flock(self::$held, LOCK_UN);
        fclose(self::$held);

        self::$held = null;
    }

    /**
     * Take the exclusive lock without waiting, or report that a test process holds the shared one.
     *
     * @param  string|null  $mirror  Defaults to {@see ContentRoot::path()}
     * @return resource|null The locked handle, to pass to {@see releaseExclusive()}, or `null` when a band is live
     *
     * @throws RuntimeException When the lock file cannot be opened
     */
    public static function tryExclusive(?string $mirror = null)
    {
        $handle = self::open(self::path($mirror));

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * Release an exclusive lock taken by {@see tryExclusive()}.
     *
     * @param  resource  $handle  The handle {@see tryExclusive()} returned
     */
    public static function releaseExclusive($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Open the band lock file, creating it and its directory when absent.
     *
     * Shared by {@see hold()} and {@see tryExclusive()} so the two cannot disagree
     * about what an open failure means (UAMS-Web/uams-statamic-archive#2773).
     *
     * @param  string  $path  The band lock file, from {@see path()}
     * @return resource The open handle, not yet locked
     *
     * @throws RuntimeException When the file cannot be opened, for example a lock
     *                          file the current user cannot write
     */
    private static function open(string $path)
    {
        // Create the mirror's parent directory so the lock file can sit beside it
        if (! is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0777, true);
        }

        // Capture the open's warning as the failure's reason, so the failure reads
        // the same whether or not the framework's error handler is installed
        $reason = null;

        set_error_handler(function (int $level, string $message) use (&$reason): bool {
            $reason = $message;

            return true;
        });

        // `c` creates without truncating, so every process locks one inode
        try {
            $handle = fopen($path, 'c');
        } finally {
            restore_error_handler();
        }

        // A lock file that cannot be opened is a fault, not a live band, so it must not read as one
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                'Could not open the content mirror band lock at %s: %s',
                $path,
                $reason ?? 'unknown error'
            ));
        }

        return $handle;
    }
}
