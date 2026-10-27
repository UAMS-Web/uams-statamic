#!/usr/bin/env node
/**
 * Run cspell over the repository and refuse a run that checked too few files (UAMS-Web/uams-statamic-archive#2296).
 *
 * `cspell` is invoked with `--no-must-find-files`, which turns "matched no files"
 * from an error into a pass. That flag is wanted: `.githooks/*` is legitimately
 * allowed to be empty. But it applies to every glob in the list, including the
 * one matching ~2,600 files — so if a glob ever stops matching, the job reports
 * success over an empty set and spelling defects reach `main` with nothing red.
 *
 * `spell` is fourth in a twelve-job chain with eight jobs behind it, so the
 * failure is not a broken chain but a silent one.
 *
 * This is a PREVENTIVE fix, not a repair: measured across 85 local-CI logs, zero
 * reported `Files checked: 0` and the lowest count seen was 2,585. Nothing has
 * fired. The hole is real and has not been fallen into.
 *
 * Exit codes:
 *   0  cspell passed and checked a plausible number of files
 *   1  cspell reported issues (its own exit passed through)
 *   2  the run checked too few files -- the check itself is not trustworthy
 *
 * Usage:  node scripts/spell.mjs          (via `npm run spell`)
 */

import { spawnSync } from 'node:child_process'
import { pathToFileURL } from 'node:url'
import { needsShell, which, winQuote } from './spawn-helpers.mjs'

/**
 * The globs cspell scans. Kept here rather than in `package.json` so the list and
 * the floor that guards it live in one file and cannot drift apart.
 */
const GLOBS = [
    '**/*.{php,js,mjs,vue,md,yml,yaml,py}',
    '**/*.antlers.html',
    '.githooks/*',
]

/**
 * The minimum plausible number of files.
 *
 * Measured 2026-09-06: the real run checks 2,630. The floor sits well below that
 * deliberately — it exists to catch a glob that stopped matching, not to track the
 * corpus. A floor tuned tight enough to notice a handful of deleted files would
 * red on every ordinary change, which is the guard-that-cries-wolf failure rather
 * than a fix.
 *
 * It does NOT catch a partial regression: dropping `**\/*.antlers.html` alone
 * leaves the total four figures and sails past this. Detecting that needs a
 * per-glob assertion, which is a different mechanism and is recorded on UAMS-Web/uams-statamic-archive#2296
 * rather than attempted here.
 */
const MINIMUM_FILES = 2000

/** Parse `CSpell: Files checked: N, Issues found: ...` out of cspell's output. */
export function filesChecked(output) {
    const match = /Files checked:\s*(\d+)/.exec(String(output))

    return match ? Number(match[1]) : null
}

/**
 * Whether this run is trustworthy, and why not when it is not.
 *
 * Separated from the spawn so both arms are testable without running cspell:
 * the failing arm is the one that has never occurred, so it cannot be reached
 * by driving the real binary.
 *
 * @return {?string} the refusal message, or null when the run is sound
 */
export function coverageRefusal(count, minimum = MINIMUM_FILES) {
    if (count === null) {
        return 'spell: could not find a "Files checked:" line in cspell output.\n'
            + 'An unparseable result is not a pass — cspell may have changed its summary format.'
    }

    if (count < minimum) {
        return `spell: cspell checked ${count} files, below the floor of ${minimum}.\n`
            + 'A run that matches few or no files reports success having checked nothing, because\n'
            + '`--no-must-find-files` makes an empty glob a pass. Check the glob list in\n'
            + 'scripts/spell.mjs against the tree before trusting this result.'
    }

    return null
}

/* c8 ignore start -- the spawn wrapper; both decision functions above are tested directly */
// `pathToFileURL`, not a hand-built `file://` string: on Windows the real value is
// `file:///C:/...` with three slashes, so a hand-built two-slash comparison never
// matches and the whole block silently does not run — exiting 0 having done
// nothing, which is the defect this file exists to prevent. Observed here before
// it was fixed. Same idiom as scripts/worktree-slots.mjs:1557.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const exe = which('npx') ?? 'npx'
    const args = ['cspell', '--no-progress', '--no-must-find-files', '--dot', ...GLOBS]
    const shell = needsShell(exe)

    const result = spawnSync(
        shell ? winQuote(exe) : exe,
        shell ? args.map(winQuote) : args,
        { encoding: 'utf8', shell }
    )

    const output = `${result.stdout ?? ''}${result.stderr ?? ''}`
    process.stdout.write(output)

    const refusal = coverageRefusal(filesChecked(output))

    if (refusal) {
        process.stderr.write(`\n${refusal}\n`)
        process.exit(2)
    }

    process.exit(result.status ?? 1)
}
/* c8 ignore stop */
