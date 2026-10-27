<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\ParallelRunner;
use Tests\CreatesApplication;

/**
 * Whether the current process is paratest's *parent* rather than a test worker
 * (UAMS-Web/uams-statamic-archive#1970).
 *
 * The parent runs no tests. Laravel's {@see ParallelRunner}
 * nonetheless builds a full application per process slot, twice per band, so it
 * can invoke `ParallelTesting::callSetUpProcessCallbacks()` and the matching
 * teardown:
 *
 * ```php
 * Collection::range(1, $processes)->each(function ($token) use ($callback) {
 *     tap($this->createApplication(), function ($app) use ($callback, $token) {
 *         ParallelTesting::resolveTokenUsing(fn () => $token);
 *         $callback($app);
 *     })->flush();
 * });
 * ```
 *
 * Those applications go through {@see CreatesApplication}, which seeds
 * this process's Stache cache — and that is the problem, because **the parent
 * has no `TEST_TOKEN`**. Note the ordering above: `resolveTokenUsing()` is
 * called *after* the application is built, and it sets Laravel's own token
 * rather than the `TEST_TOKEN` environment variable that `config/cache.php`
 * reads. paratest sets that variable only in worker subprocesses. So the
 * parent's application resolves `cache.stores.file.path` to the **shared,
 * non-tokenized** `storage/framework/cache/data`, and seeding it there wipes and
 * rebuilds a directory that per-worker isolation was designed to keep nobody
 * sharing.
 *
 * One band alone survives that — it is merely pointless work on a directory no
 * test reads, though it does wipe the cache directory out from under any
 * concurrent *serial* run, which has no token either and so uses that same path.
 * Two concurrent bands do not survive it: both parents run
 * `resetCacheDirectory()`, which is `deleteDirectory()` followed by
 * `ensureDirectoryExists()`, and Laravel's `Filesystem` checks then acts on both
 * lines. Observed here, traced to `CreatesApplication::resetCacheDirectory()`:
 * `FilesystemIterator::__construct(...): The system cannot find the path
 * specified` — `deleteDirectory()` passed `isDirectory()` and then iterated a
 * directory the other parent had already removed.
 *
 * **This is one of several layered races, not the whole of UAMS-Web/uams-statamic-archive#1970.** Closing it
 * does not make two concurrent bands pass; it removes the outermost abort and
 * exposes the next. Do not read a still-failing concurrent band as this fix not
 * working — check which frame the trace names. The others found so far are
 * Laravel's own `Illuminate\Testing\Concerns\TestViews`, which creates and
 * deletes `views/test_<worker index>` in the same parent and is not
 * race-tolerant either, and `config/cache.php` keying worker cache directories
 * on the bare `TEST_TOKEN`, so two bands' worker 1 shared `cache/data/1` until
 * UAMS-Web/uams-statamic-archive#1985 added the worker's pid to that path. Each
 * one masks the ones beneath it, which is why this symptom resisted attribution
 * for so long: a fix looks ineffective because the band still fails, for a
 * different reason, at a later point.
 *
 * **Detection.** `TEST_TOKEN` is set in every worker and never in the parent, so
 * an absent token rules workers out. It is also absent in a *serial* run, which
 * genuinely does need seeding — so the second half of the test is whether this
 * invocation is a parallel one at all, read from the command line. A worker's
 * argv carries neither `--parallel` nor the paths the parent was given, but it
 * does carry a token, so the first condition already excludes it.
 */
final class ParallelParent
{
    /**
     * Whether this process is the parallel parent.
     */
    public static function isCurrentProcess(): bool
    {
        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        return self::isParentFor(getenv('TEST_TOKEN'), $argv);
    }

    /**
     * The pure form, so the matrix can be covered without spawning a band.
     *
     * @param  string|false  $token  A `getenv('TEST_TOKEN')` result — `false` when unset.
     * @param  list<string>  $argv  The process command line.
     */
    public static function isParentFor(string|false $token, array $argv): bool
    {
        if (is_string($token) && $token !== '') {
            return false;
        }

        return in_array('--parallel', $argv, true);
    }
}
