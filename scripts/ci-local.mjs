#!/usr/bin/env node
/**
 * ci-local.mjs: the shared local CI runner, with this repository's flag translations (#296).
 *
 *     node scripts/ci-local.mjs [shared runner options]      (npm run ci:local -- ...)
 *
 * The shared runner (`.claude/skills/local-ci/local-ci.mjs`, configured by `local-ci.json`) does
 * the work, in its default slot `<primary>-ci`. This only adds the `dependencies` job to a
 * `--jobs` subset and translates the private runner's flags, refusing the ones with no
 * counterpart rather than dropping them: `--fresh` dropped silently reads as though it rebuilt
 * something.
 */

import { spawnSync } from 'node:child_process'
import { dirname, join, resolve } from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

/** The job that installs, or reports drift, before anything else runs. */
export const DEPENDENCIES_JOB = 'dependencies'

/**
 * The private runner's flags the shared runner has no counterpart for, and what to do instead.
 *
 * @type {Record<string, string>}
 */
export const RETIRED_FLAGS = {
    '--fresh': 'the shared runner reuses its slot; to rebuild it, remove it with `git worktree remove --force <slot>` and the next run recreates it',
    '--skip-install': 'the `dependencies` job installs only in the runner\'s slot, and every run includes it',
    '--skip-submodule': 'the `dependencies` job initializes the gate submodules in the runner\'s slot on every run',
    '--no-reset': 'the `pest` job always resets the reused tree\'s caches before the suite',
    '--fetch': 'the shared runner fetches the branch from origin on every --branch run',
}

/**
 * A `--jobs` list with the `dependencies` job first, added when it is missing.
 *
 * The shared runner ignores `always` once `--jobs` names a subset, so without this a
 * `--branch --jobs pest` run in a reused slot would install nothing and pass against whatever
 * `vendor/` the slot held last.
 *
 * @param  {string}  list  The comma-separated value given to `--jobs`.
 * @return {string}
 */
export function withDependencies(list) {
    const names = list.split(',').map((name) => name.trim()).filter(Boolean)

    return names.includes(DEPENDENCIES_JOB) ? names.join(',') : [DEPENDENCIES_JOB, ...names].join(',')
}

/**
 * The shared runner's arguments for this invocation's.
 *
 * `--jobs all` becomes `--all` and `-b` becomes `--branch`: the private runner took both, and
 * the shared runner would refuse them. Any other `--jobs` list gains the `dependencies` job.
 *
 * @param  {string[]}  args
 * @return {string[]}
 */
export function runnerArgs(args) {
    for (const flag of Object.keys(RETIRED_FLAGS)) {
        if (args.includes(flag)) {
            throw new Error(`${flag} is not a flag of the shared runner: ${RETIRED_FLAGS[flag]}.`)
        }
    }

    const out = []

    for (let i = 0; i < args.length; i++) {
        const isJobs = args[i] === '--jobs' || args[i] === '-j'

        if (isJobs && args[i + 1] === 'all') {
            out.push('--all')
            i++
        } else if (isJobs && args[i + 1] !== undefined) {
            out.push('--jobs', withDependencies(args[i + 1]))
            i++
        } else if (args[i] === '-b') {
            out.push('--branch')
        } else {
            out.push(args[i])
        }
    }

    return out
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    let args

    try {
        args = runnerArgs(process.argv.slice(2))
    } catch (error) {
        console.error(error.message)
        process.exit(2)
    }

    const runner = join(dirname(fileURLToPath(import.meta.url)), '..', '.claude', 'skills', 'local-ci', 'local-ci.mjs')
    const result = spawnSync(process.execPath, [runner, ...args], { stdio: 'inherit' })

    // A runner killed by a signal has no status; report it the way a shell would, never as a pass
    process.exitCode = result.status ?? (result.signal ? 128 + ({ SIGHUP: 1, SIGINT: 2, SIGTERM: 15 }[result.signal] ?? 0) : 1)
}
