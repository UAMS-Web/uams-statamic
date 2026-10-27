<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Which cache directories a paratest worker may delete when it exits (UAMS-Web/uams-statamic-archive#1985).
 *
 * `config/cache.php` and `config/tables.php` give each worker a directory ending in
 * `<TEST_TOKEN>-<pid>`, which no other live process can share. A serial run has no
 * token and uses the shared default, which holds the seeded Stache and other
 * processes' state. So a directory is the worker's to delete only when it ends in
 * this process's pid; anything else is left alone.
 */
final class WorkerCacheDirectory
{
    /**
     * Whether a cache directory belongs to the process with the given pid alone.
     *
     * @param  string  $directory  A resolved cache directory.
     * @param  int|false  $pid  A `getmypid()` result, `false` when unavailable.
     */
    public static function isOwnedBy(string $directory, int|false $pid): bool
    {
        if ($pid === false) {
            return false;
        }

        return str_ends_with(rtrim($directory, '/\\'), '-'.$pid);
    }
}
