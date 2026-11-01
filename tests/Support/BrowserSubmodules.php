<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses a browser run in a checkout without the submodules the served
 * application boots from (#378).
 *
 * Collection and asset-container configs live in `content/`, the Control Panel
 * login user is a tracked file in `users/`, and the form configurations are in
 * `resources/forms/`. Without them a smoke does not fail on the missing
 * submodule: it fails on whatever the application reaches first, so a run on a
 * checkout that lacked all three reported four unrelated-looking Control Panel
 * failures, each naming a different collection or container, and a login
 * throttle on top. This names the cause before any of that happens.
 *
 * **A failure, not a skip.** The `pest` job's content guards skip when
 * `content/` is absent (see `ContentSubmodule`), because most of that suite does
 * not need it. Every Control Panel smoke logs in first, so a browser run without
 * these submodules tests nothing, and a skip would report that as clean.
 *
 * **It does not check them out.** The checkout needs credentials for private
 * repositories, which a test process has no business holding; failing with the
 * command is the job.
 */
final class BrowserSubmodules
{
    /**
     * Checkout-relative mount points of the submodules a browser run needs.
     *
     * @var list<string> The same three the CI `pest` and `browser` jobs check out.
     */
    public const array REQUIRED = ['content', 'users', 'resources/forms'];

    /**
     * Armed once per process. The check is a handful of file checks, but
     * the requirement is that it fires before the first browser test, not that
     * it re-runs for each of them.
     */
    private static bool $checked = false;

    /**
     * Throw unless every required submodule is initialized.
     *
     * @param  string|null  $root  Checkout root to probe; defaults to this one
     *
     * @throws RuntimeException When one or more required submodules are absent,
     *                          naming each and the command that checks it out
     */
    public static function assertPresent(?string $root = null): void
    {
        // An explicit root means a test drives its own fixture, so it must run
        // every time rather than once per process.
        $driven = $root !== null;

        // Skip the repeat check for the real checkout once it has passed
        if (! $driven && self::$checked) {
            return;
        }

        // Collect every absent submodule, so one message names all of them
        $missing = self::missing($root ?? dirname(__DIR__, 2));

        // Fail with the full list and the command that fixes it
        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'The browser suite needs the %s submodule%s, which %s not initialized in this checkout. '
                .'Every Control Panel smoke boots the application from them and would fail on whatever '
                .'it reaches first (a missing collection, asset container or login user) rather than on this. '
                .'Run: git submodule update --init %s',
                implode(', ', $missing),
                count($missing) === 1 ? '' : 's',
                count($missing) === 1 ? 'is' : 'are',
                implode(' ', $missing),
            ));
        }

        // Remember the pass for the real checkout only
        if (! $driven) {
            self::$checked = true;
        }
    }

    /**
     * The required submodules that are not initialized under a root.
     *
     * `git submodule update --init` leaves a `.git` entry in the mount point, and
     * every route to a working checkout runs it, so that entry is the test rather
     * than whether the directory holds anything. A mount point can hold files with
     * no submodule behind it: `composer install` run first has `statamic:install`
     * scaffold `.gitkeep` files in `content/`, which then makes the init refuse the
     * non-empty directory (#383), and a killed run can strand app output there.
     *
     * @param  string  $root  Checkout root to probe
     * @return list<string> The checkout-relative paths that are absent
     */
    public static function missing(string $root): array
    {
        // Keep the paths whose mount point has no `.git` entry
        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $path): bool => ! self::isInitialized($root.'/'.$path),
        ));
    }

    /**
     * Whether a mount point carries a submodule's `.git` entry.
     *
     * A file in a submodule checkout, and a directory where git has not absorbed
     * the submodule's repository into the superproject.
     *
     * @param  string  $directory  The mount point to inspect
     * @return bool Whether a submodule is initialized there
     */
    private static function isInitialized(string $directory): bool
    {
        return file_exists($directory.'/.git');
    }
}
