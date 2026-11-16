#!/usr/bin/env node
/**
 * ci-job.mjs: the `local-ci.json` jobs whose `ci.yml` counterpart runs more than one command.
 *
 *     node scripts/ci-job.mjs <isolation|validate|content-pointer|encoding|build> [--tree <path>]
 *
 * The shared runner gives a job one command, run by `/bin/sh` or `cmd.exe`, and only the first
 * word of it under the PHP the configuration names (#296). These jobs run several, so each is a
 * list of steps here, run in order under that PHP (`$LOCAL_CI_PHP`, which the runner sets) and
 * stopping at the first failure, as `ci.yml` stops at a failed step. Exits with the failing
 * step's status, 0 when all pass, or 2 for an unknown job or a missing tool.
 */

import process from 'node:process'
import { capture, isMainModule, phpRunner, resolveGit, resolveNpm, resolvePhp, run, runCli, step } from './gate-process.mjs'
import { which } from './spawn-helpers.mjs'

/**
 * The base `content-pointer` compares submodule pointers with: `origin/main` when the tree has
 * it, else `main`, as `ci.yml` compares with the pull request's base branch.
 *
 * @param  {(ref: string) => boolean}  hasRef  Whether a commit-ish resolves in the tree.
 * @return {string}
 */
export function pointerBase(hasRef) {
    return hasRef('origin/main^{commit}') ? 'origin/main' : 'main'
}

/**
 * The git configuration that lets a submodule clone authenticate with Composer's GitHub token,
 * passed through the environment so the token never reaches an argument list.
 *
 * @param  {string}  token  The token, or an empty string when none is configured.
 * @return {Record<string, string>|undefined}
 */
export function tokenEnv(token) {
    return token
        ? {
            GIT_CONFIG_COUNT: '1',
            GIT_CONFIG_KEY_0: `url.https://x-access-token:${token}@github.com/.insteadOf`,
            GIT_CONFIG_VALUE_0: 'https://github.com/',
        }
        : undefined
}

/**
 * Each job's steps: a label and the command, as `['php', ...args]` for PHP or `[tool, ...args]`.
 *
 * @type {Record<string, Array<[string, string[]]>>}
 */
export const JOBS = {
    isolation: [
        ['parallel-safety test guard', ['php', 'scripts/check-parallel-safe-tests.php']],
        ['global test symbols', ['php', '.claude/skills/pest-testing/scripts/check-global-test-symbols.php', 'tests']],
    ],
    validate: [
        ['composer validate', ['composer', 'validate', '--no-check-publish', '--no-check-all']],
        ['PSR-4 class check', ['composer', 'dump-autoload', '--optimize', '--dev', '--strict-psr', '--dry-run', '--no-scripts']],
        ['one-class-per-file check', ['php', 'scripts/check-one-class-per-file.php']],
    ],
    encoding: [
        ['cp1252 double-encoding guard', ['node', 'vendor/joshdaugherty/ipa-unicode-inventory/scripts/check-double-encoding.mjs']],
        ['decision-log order guard', ['node', 'scripts/check-decision-log-order.mjs']],
        ['mutate-ignore auditor', ['php', 'scripts/check-mutate-ignore.php', 'app', 'tests']],
        ['mutate-ignore breadth check', ['php', '.claude/skills/pest-testing/scripts/check-mutate-ignore-breadth.php', '--baseline=tests/mutate-ignore-breadth.baseline.json', 'app', 'tests']],
        ['lone-CR guard', ['node', 'scripts/check-lone-cr.mjs']],
        ['release-notes generator suite', ['python3', '.claude/skills/writing-release-notes/gen_release_notes.test.py']],
    ],
    build: [
        ['npm run build', ['npm', 'run', 'build']],
        ['npm run cp:build', ['npm', 'run', 'cp:build']],
    ],
}

/**
 * Resolve a step's tool to an executable path, or throw naming it.
 *
 * `python3` falls back to `python`, which is Python 3 on a Windows install; `$LOCAL_CI_PYTHON`
 * overrides both.
 *
 * @param  {string}  tool
 * @return {string}
 */
function resolveStepTool(tool) {
    if (tool === 'node') {
        return process.execPath
    }

    if (tool === 'npm') {
        return resolveNpm()
    }

    const candidates = tool === 'python3'
        ? [process.env.LOCAL_CI_PYTHON, which('python3'), which('python')]
        : [which(tool)]
    const found = candidates.find(Boolean)

    if (! found) {
        throw new Error(`Required tool '${tool}' was not found on PATH.`)
    }

    return found
}

/**
 * Run one job's steps in order, stopping at the first that fails.
 *
 * @param  {string}  name
 * @param  {string}  tree
 * @return {number}  The failing step's status, or 0.
 */
export function runJob(name, tree) {
    if (name === 'content-pointer') {
        const git = resolveGit()
        const base = pointerBase((ref) => capture(git, ['-C', tree, 'rev-parse', '--verify', '--quiet', ref], tree, { ignoreStderr: true }) !== '')
        const token = capture(resolveStepTool('composer'), ['config', '--global', '--auth', 'github-oauth.github.com'], tree, { ignoreStderr: true })
        step(`JOB content-pointer - submodule pointers vs ${base}`)

        return run(process.execPath, ['scripts/check-content-pointer.mjs', '--base', base], tree, tokenEnv(token))
    }

    const steps = JOBS[name]

    if (! steps) {
        throw new Error(`Unknown job '${name}'. Known: ${[...Object.keys(JOBS), 'content-pointer'].join(', ')}.`)
    }

    const runPhp = steps.some(([, [tool]]) => tool === 'php') ? phpRunner(resolvePhp()) : null

    for (const [label, [tool, ...args]] of steps) {
        step(`JOB ${name} - ${label}`)
        const code = tool === 'php' ? runPhp(args, tree) : run(resolveStepTool(tool), args, tree)

        if (code !== 0) {
            return code
        }
    }

    return 0
}

if (isMainModule(import.meta.url)) {
    const [job, ...rest] = process.argv.slice(2)
    process.argv.splice(2, process.argv.length, ...rest)
    runCli({}, ({ tree }) => runJob(job ?? '', tree))
}
