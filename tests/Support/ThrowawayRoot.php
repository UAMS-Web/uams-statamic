<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The path segment that keeps one test process's throwaway fixture roots off
 * every other concurrent process's (UAMS-Web/uams-statamic-archive#1136, UAMS-Web/uams-statamic-archive#1957).
 *
 * Several test-support bases repoint a Statamic store, the blueprint repository
 * or a browser write target at a directory under `storage/framework/testing/`,
 * and each one calls `File::deleteDirectory()` on that root in **both** `setUp`
 * and `tearDown`. The cleanup is what makes a shared root harmful rather than
 * merely untidy: two runs on one path do not interleave, they wipe each other's
 * fixtures mid-test.
 *
 * Three things share a filesystem here, and the segment separates all of them
 * with one rule: **every segment ends in the process id**, which no two
 * concurrently-alive processes can share.
 *
 * 1. **Paratest workers within one run** (UAMS-Web/uams-statamic-archive#1136). Each worker is a process with
 *    its own pid, and carries the worker index as the segment's prefix.
 * 2. **Two serial runs in one worktree** (UAMS-Web/uams-statamic-archive#1957). `TEST_TOKEN` is set only by
 *    paratest, so every *serial* run used to fall back to the literal `seq` —
 *    one fixed path for all of them. The slot model isolates two sessions in
 *    different worktrees, but not a fan-out of agents in one checkout, a soak
 *    harness looping bands while someone starts a run by hand, or a backgrounded
 *    run that outlived its wrapper.
 * 3. **Two concurrent parallel bands** (UAMS-Web/uams-statamic-archive#1960). `TEST_TOKEN` is the worker
 *    *index* — a small integer, 1..N, identical across independent runs — so
 *    keying on it alone made worker 3 of one band and worker 3 of another
 *    resolve the same root, exactly as two serial runs used to. A band
 *    saturates the machine, so nobody starts a second on purpose; it happens
 *    anyway in the shapes `.claude/rules/long-running-commands.md` documents —
 *    a soak parent that survived a kill, a band detached by a tool timeout
 *    while the next one starts.
 *
 * The worker index stays legible in the directory name (`3-48812` rather than
 * a bare pid) because a path is a debugging aid as much as an isolation
 * mechanism, and losing the index would make a failing worker harder to place.
 *
 * **Why the pid rather than paratest's `UNIQUE_TEST_TOKEN`.** That variable
 * exists for exactly this purpose and is set whenever `TEST_TOKEN` is — both
 * come from `Options::fillEnvWithTokens()` behind the same `noTestTokens`
 * flag, so it was a real alternative rather than an unavailable one. It is
 * `uniqid($index.'_')`, which is microsecond-time-based: two bands starting
 * within the same microsecond would collide. A pid cannot collide among
 * processes that are alive at the same time, which is precisely the hazard,
 * and it keeps both branches derived from one identity instead of two.
 *
 * The failure all of this prevents is the expensive kind — not a crash but
 * scattered reds that read as real regressions.
 * `.claude/rules/long-running-commands.md` records six of them, every one green
 * on a quiet machine, and an hour spent bisecting an innocent diff.
 *
 * **Accepted trade-off: litter.** Every run now leaves an empty per-process
 * directory behind — `<root>/seq-<pid>` serially, `<root>/<index>-<pid>` per
 * worker — not only a killed one, because the classes below delete the leaf
 * they write into rather than the segment directory above it. That is one
 * directory per process rather than a small fixed set, and `git status` is
 * blind to an empty directory so nothing surfaces it. It is cosmetic — nothing
 * scans this tree, and git ignores it wholesale — and it is cleared by deleting
 * the per-process directories under `storage/framework/testing` while no run is
 * in flight. Deliberately NOT swept at boot: a sweep that removed another
 * process's directory would reinstate the very race this class closes, and "is
 * that pid still alive" is not a question worth answering here.
 *
 * **Call this inline, inside the `storage_path()` call.**
 * `scripts/check-parallel-safe-tests.php` (Rule B) reads the *call's own* tokens
 * to decide whether a `framework/testing` path is per-worker, and recognizes
 * this class by name. Hoisting the result to a local first hides it from the
 * guard and reds the `isolation` CI job.
 */
final class ThrowawayRoot
{
    /**
     * The memoized segment, so every call within one process resolves to the
     * same directory — `setUp` creates it and `tearDown` must delete that same
     * one. Only the degenerate `getmypid() === false` branch actually needs the
     * memo, but keeping it unconditional makes the stability property hold
     * without depending on which branch ran.
     */
    private static ?string $segment = null;

    /**
     * The segment for the current process: `<worker index>-<pid>` under
     * paratest, or `seq-<pid>` when `TEST_TOKEN` is unset.
     *
     * Read with `getenv()` rather than `env()` — it is a per-process variable,
     * which keeps it clear of the config-cache concern larastan flags.
     */
    public static function segment(): string
    {
        return self::$segment ??= self::segmentFor(getenv('TEST_TOKEN'), getmypid());
    }

    /**
     * The pure derivation behind {@see segment()}, taking both identities as
     * arguments so a test can exercise the property directly: two identities
     * yield two segments, one identity yields a stable one.
     *
     * Both branches end in the process identity, because that is what makes two
     * concurrent runs safe. The token only decides the prefix.
     *
     * @param  string|false  $token  A `getenv('TEST_TOKEN')` result — `false` when unset.
     * @param  int|false  $pid  A `getmypid()` result — `false` when unavailable.
     */
    public static function segmentFor(string|false $token, int|false $pid): string
    {
        $prefix = is_string($token) && $token !== '' ? $token : 'seq';

        return $prefix.'-'.self::processIdentity($pid);
    }

    /**
     * The per-process half of a segment.
     *
     * `getmypid()` is documented as returning `false` on failure. It does not do
     * so on any platform this suite runs on, but a random suffix is the only
     * honest answer if it ever did — a shared constant there would silently
     * reinstate the collision this class exists to prevent. {@see segment()}
     * memoizes the result, so the randomness is drawn once per process.
     *
     * @param  int|false  $pid  A `getmypid()` result.
     */
    private static function processIdentity(int|false $pid): string
    {
        return $pid === false ? 'unknown-'.bin2hex(random_bytes(4)) : (string) $pid;
    }
}
