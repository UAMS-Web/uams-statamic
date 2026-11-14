#!/usr/bin/env node
/**
 * ci-local.mjs: the shared local CI runner, with this repository's flag translations (#296).
 *
 *     node scripts/ci-local.mjs [shared runner options]      (npm run ci:local -- ...)
 *
 * The shared runner (`.claude/skills/local-ci/local-ci.mjs`, configured by `local-ci.json`) does
 * the work: with `--branch`, in its slot `<primary>-ci`; without it, in place. This only adds the
 * jobs a `--jobs` subset depends on and translates the private runner's flags, refusing the ones
 * with no counterpart rather than dropping them: `--fresh` dropped silently reads as though it
 * rebuilt something.
 */

import { spawnSync } from 'node:child_process'
import { dirname, join, resolve } from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

/** The job that installs, or reports drift, before anything else runs. */
export const DEPENDENCIES_JOB = 'dependencies'

/**
 * The job that makes the shared runner resolve PHP. The runner resolves it, checks it against
 * `config.platform.php`, prints it in its header and hands it to every job as `LOCAL_CI_PHP` only
 * when a selected job's command starts with `php` or `vendor/bin/`; without this job a `--jobs pest`
 * run would do none of that, and the gates would fall back to whatever `php` is first on PATH.
 */
export const PHP_JOB = 'php'

/**
 * Jobs a selected job reads the output of, so a subset naming the second gains the first.
 *
 * @type {Record<string, string>}
 */
export const JOB_INPUTS = {
    // `browser` runs with --skip-build, reusing the bundles the `build` job made from this commit
    browser: 'build',
}

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
    '--fetch': 'the shared runner fetches only the branch on a --branch run, not origin/main, so run `git fetch origin main` first when the pint and content-pointer base must be current',
}

/**
 * A `--jobs` list with the jobs its members depend on added: `php` and `dependencies` always, and
 * each named job's input (see {@see JOB_INPUTS}). The shared runner runs a subset in
 * `local-ci.json` order, so where an addition lands in the list does not matter.
 *
 * The shared runner ignores `always` once `--jobs` names a subset, so without this a
 * `--branch --jobs pest` run in a reused slot would install nothing and pass against whatever
 * `vendor/` the slot held last, and `--jobs browser` would test the bundles an earlier commit
 * built.
 *
 * @param  {string}  list  The comma-separated value given to `--jobs`.
 * @return {string}
 */
export function withDependencies(list) {
    const names = list.split(',').map((name) => name.trim()).filter(Boolean)
    const added = [PHP_JOB, DEPENDENCIES_JOB, ...names.map((name) => JOB_INPUTS[name]).filter(Boolean)]

    return [...new Set([...added.filter((name) => ! names.includes(name)), ...names])].join(',')
}

/**
 * The shared runner's arguments for this invocation's.
 *
 * `--jobs all` becomes `--all` and `-b` becomes `--branch`: the private runner took both, and
 * the shared runner would refuse them. Any other `--jobs` list gains the jobs it depends on.
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
