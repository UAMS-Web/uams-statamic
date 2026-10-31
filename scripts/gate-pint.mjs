#!/usr/bin/env node
/**
 * gate-pint.mjs: the gate's `pint` step: Pint over the PHP files the branch changed.
 *
 *     node scripts/gate-pint.mjs [--tree <path>] [--base <ref>]
 *
 * Runs against an installed checkout, `--tree` or the working directory, and exits with Pint's
 * own status: 0 when no changed file needs formatting or none changed. A wrong flag or a missing
 * tool exits 2. `--base` names the branch to diff against, `main` by default. `local-ci.mjs`
 * calls `gatePint` (#295).
 */

import process from 'node:process'
import { pathToFileURL } from 'node:url'
import { capture, phpRunner, resolveGit, resolvePhp, runCli, step } from './gate-process.mjs'

// Split a list of file-path args into batches whose joined length stays under
// `maxLen`, so a spawned command line never exceeds the OS limit (Windows
// CreateProcess caps the whole command at ~32 KB). A single path longer than
// `maxLen` still goes out alone — there is nothing else to do, and no real repo
// path is that long. Order is preserved and the concatenation of the chunks
// equals the input, so running a per-file tool (Pint) chunk-by-chunk yields the
// same result as one invocation over the whole list.
export function chunkByArgLength(files, maxLen) {
    const chunks = []
    let current = []
    let length = 0
    for (const file of files) {
        const cost = file.length + 1 // + a separator/quoting byte of slack
        if (current.length > 0 && length + cost > maxLen) {
            chunks.push(current)
            current = []
            length = 0
        }
        current.push(file)
        length += cost
    }
    if (current.length > 0) {
        chunks.push(current)
    }
    return chunks
}

/**
 * @param  {{tree: string, php: string, git: string, repoRoot?: string, baseRef?: string}}  options
 * @return {number}  The step's exit code.
 */
export function gatePint({ tree, php, git, repoRoot = tree, baseRef = 'main' }) {
    const runPhp = phpRunner(php)

    // PARITY: the pint job's "Determine changed PHP files" + "Run Pint on changed
    // files" steps in ci.yml.
    //
    // Diff against the REMOTE-TRACKING base (origin/<base>), matching ci.yml's
    // pull_request path (base="origin/${{ github.base_ref }}"). `--fetch`
    // updates origin/<base> but never moves the local <base> branch ref, so
    // after a run of `gh pr merge`s a local main that lags origin would
    // otherwise balloon `<base>...HEAD` with already-merged commits and report a
    // false FAIL (UAMS-Web/uams-statamic-archive#1079). Fall back to the local ref when the remote-tracking
    // ref doesn't resolve (a purely local base).
    //
    // A BARE `pint --test` IS NOT A SUBSTITUTE FOR THIS, AND ITS `0 dirty` IS
    // NOT EVIDENCE THE TREE IS CLEAN (UAMS-Web/uams-statamic-archive#2341). Pint's own finder does not
    // descend into dot-directories; the git pathspec below does. Measured with
    // both arms at one moment, with two tracked files dirty on disk:
    //
    //   vendor/bin/pint --test                  "result":"passed"  exit 0
    //   vendor/bin/pint --test <those paths>    "result":"fail"    exit 1
    //
    // The explicit arm failing at the same instant is what makes the bare run's
    // `passed` readable as blindness rather than cleanliness. So this job sees
    // `.cursor/*.php` and a whole-tree sweep does not — which is why a sweep, if
    // one is ever wanted, must enumerate with `git ls-files -- '*.php'` rather
    // than by letting Pint find its own files.
    const remoteBase = `origin/${baseRef}`
    const diffBase = capture(git, ['-C', tree, 'rev-parse', '--verify', '--quiet', `${remoteBase}^{commit}`], repoRoot, { ignoreStderr: true })
        ? remoteBase
        : baseRef
    step(`JOB pint - diffing .php files vs ${diffBase}`)
    const changed = capture(git, ['-C', tree, 'diff', '--name-only', '--diff-filter=ACMR', `${diffBase}...HEAD`, '--', '*.php'], repoRoot)
        .split('\n')
        .map((f) => f.trim())
        .filter(Boolean)
    if (changed.length === 0) {
        console.log('No PHP files changed vs base; skipping Pint (CI does the same).')
        return 0
    }
    console.log(`Changed PHP files: ${changed.length}`)
    // Chunk the path list so a large diff never overflows the Windows command
    // line (UAMS-Web/uams-statamic-archive#1079). ci.yml passes the whole list at once (its Ubuntu runner
    // allows a ~2 MB command line; a 300+ file diff exceeds Windows' ~32 KB
    // cap). Pint checks each file independently, so per-chunk codes aggregate.
    let pintCode = 0
    for (const chunk of chunkByArgLength(changed, 8000)) {
        const code = runPhp(['vendor/bin/pint', '--test', ...chunk], tree)
        if (code !== 0) {
            pintCode = code
        }
    }
    return pintCode
    // PARITY-END
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    runCli({ base: { type: 'string' } }, ({ tree, base }) => gatePint({ tree, php: resolvePhp(), git: resolveGit(), baseRef: base }))
}
