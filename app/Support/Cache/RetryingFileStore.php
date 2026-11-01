<?php

declare(strict_types=1);

namespace App\Support\Cache;

use ErrorException;
use Statamic\Extensions\FileStore;

/**
 * Statamic's {@see FileStore} with a retry on a transient Windows "Permission
 * denied" write.
 *
 * Extends Statamic's file store (not Laravel's) so it keeps Statamic's `path()`
 * override — the one that maps `stache::keys/*` to the readable
 * `storage/framework/testing/cache/data/<TEST_TOKEN>-<pid>/stache/keys/*` path where the Stache
 * key index lives. On paratest under Windows, `file_put_contents` on that file
 * intermittently throws `ErrorException: ... Permission denied` — the OS / Windows
 * Defender briefly holds a handle on the file being rewritten. It is a
 * filesystem-timing hiccup, not a real failure: a retry a few milliseconds later
 * succeeds. Left unhandled it aborts a random, diff-unrelated test in the parallel
 * suite (UAMS-Web/uams-statamic-archive#1381).
 *
 * A bounded retry with linear backoff absorbs the hiccup. `FileStore::forever()`
 * delegates to `put()`, so overriding the single `put()` method covers both the
 * timed and the permanent writes the Stache key index uses. Wired in for the test
 * environment only by `Tests\CreatesApplication::createApplication()`, which
 * mirrors Statamic's own `file`-driver registration; production runs on Linux,
 * where the underlying error does not occur.
 */
final class RetryingFileStore extends FileStore
{
    /**
     * Maximum write attempts before giving up and rethrowing.
     */
    private const int MAX_ATTEMPTS = 5;

    /**
     * Base backoff between attempts, in microseconds (scaled linearly by attempt
     * number, so waits grow 20ms, 40ms, 60ms, 80ms across the retries).
     */
    private const int BACKOFF_MICROSECONDS = 20_000;

    /**
     * Store an item in the cache, retrying a transient Windows "Permission
     * denied" write. Any other error propagates immediately.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    #[\Override]
    public function put($key, $value, $seconds): bool
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return parent::put($key, $value, $seconds);
            } catch (ErrorException $e) {
                if ($attempt >= self::MAX_ATTEMPTS || ! str_contains($e->getMessage(), 'Permission denied')) {
                    throw $e;
                }

                // Timing-only backoff. RemoveFunctionCall on usleep() is equivalent
                // and cannot be annotated: that mutator removes the call node, and the
                // ignore marker reaches the node's children rather than the call
                // itself (same trap as RedirectRuleMatcher::ini_set).
                usleep(self::BACKOFF_MICROSECONDS * $attempt);
            }
        }
    }
}
