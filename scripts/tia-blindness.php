<?php

declare(strict_types=1);

/**
 * The pure decision half of the Tia fingerprint-blindness guard (UAMS-Web/uams-statamic-archive#1996).
 *
 * DECLARATION-ONLY on purpose. {@see check-tia-safe.php} is a CLI that runs its checks
 * at the bottom of the file, so a test cannot `require` it without executing the guard.
 * Splitting the decision out means the part that must not regress is directly testable,
 * while the CLI keeps the I/O.
 *
 * WHAT IS BEING DECIDED. Pest validates a cached impact graph by comparing a structural
 * fingerprint over `composer.lock`, `phpunit.xml` and the JS lock files. Each input is
 * gated on `Fingerprint::isTrackedByGit()`, a Symfony Finder query with
 * `ignoreVCSIgnored(true)`. In some linked-worktree shapes that query returns nothing
 * for plainly tracked files; every input then resolves to `null`, `structuralMatches()`
 * compares null to null and reports a match, and a dependency change invalidates
 * NOTHING — so `--tia` replays results recorded against a different dependency tree.
 * That is the silent green run UAMS-Web/uams-statamic-archive#1717 adopted Tia to avoid.
 *
 * Measured 2026-08-18 on macOS, pest v5.1.1, editing `composer.lock` in each tree:
 *
 *   tree                              composer_lock          structuralMatches()
 *   primary clone                     hash -> new hash       false  (invalidates)
 *   sibling slot `../<name>-a`        hash -> new hash       false  (invalidates)
 *   `<primary>/.claude/worktrees/<n>` NULL -> NULL           TRUE   (replays stale)
 *
 * THE TRIGGER, characterized. Three conditions must hold together:
 *
 *   1. the tree is a linked worktree (its `.git` is a file);
 *   2. an ancestor holds a real `.git` DIRECTORY, and that ancestor's ignore rules
 *      match the worktree's own path; and
 *   3. the project-root string is a realpath, not a symlinked alias for one.
 *
 * Upstream ([`pestphp/pest#1813`]) names only (1) and (2). Condition (3) is not a
 * property of the bug so much as of how you observe it: Symfony's
 * `VcsIgnoredFilterIterator` compares `$file->getRealPath()` — resolved — against a
 * `baseDir` it never resolves, so a root reached through a symlink never prefix-matches
 * and the ignore filter silently disengages. That is worth writing down because it makes
 * the fault look intermittent: an earlier revision of this file recorded "a throwaway
 * fixture did not reproduce" and concluded the mechanism was open, when the fixture had
 * merely been built under `sys_get_temp_dir()` — `/var/folders/…` on macOS, whose
 * realpath is `/private/var/folders/…`. The same tree, addressed both ways in the same
 * instant, fingerprints resolved through the symlink and NULL through the realpath.
 *
 * The sibling slots are safe for reason (2): neither Herd's parked directory nor `$HOME`
 * nor `/Users` holds a `.git` directory, so no ancestor ignore rules apply. `EnterWorktree`
 * trees fail all three at once, since `.claude/worktrees/` is git-ignored by design.
 *
 * The guard nonetheless MEASURES rather than applying those three conditions, for a
 * reason unrelated to confidence in them: measuring means the refusal retires itself the
 * moment upstream lands UAMS-Web/uams-statamic-archive#1813, whereas a rule encoding the conditions would keep refusing
 * in a tree that had been fixed — and a guard that can never go green is one people learn
 * to skip (UAMS-Web/uams-statamic-archive#1952).
 */

/**
 * Fingerprint inputs worth testing, as `structural` key => repo-relative filename.
 *
 * Shared so the guard and its tests cannot drift about what is being checked.
 */
const TIA_FINGERPRINT_CANDIDATES = [
    'composer_lock' => 'composer.lock',
    'phpunit_xml' => 'phpunit.xml',
    'package_lock' => 'package-lock.json',
];

/**
 * Which provable inputs Tia reports as absent — i.e. where invalidation is blind.
 *
 * `$provable` must already be filtered to files that exist AND are git-tracked: a null
 * for a genuinely absent file is correct behavior, not blindness, and treating it as a
 * fault would refuse in any checkout that happens to lack `package-lock.json`.
 *
 * @param  array<string, string>  $provable  key => filename, pre-filtered by the caller
 * @param  array<string, int|string|null>  $structural  `Fingerprint::compute()['structural']`
 * @return list<string> the filenames reported absent despite being present and tracked
 */
function tiaBlindFingerprintInputs(array $provable, array $structural): array
{
    $blind = [];

    foreach ($provable as $key => $file) {
        if (($structural[$key] ?? null) === null) {
            $blind[] = $file;
        }
    }

    return $blind;
}
