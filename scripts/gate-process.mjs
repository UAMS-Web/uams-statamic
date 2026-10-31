/**
 * gate-process.mjs: what the standalone gate step scripts and the local runner share.
 *
 * The `pest`, `pint`, `rector` and `browser` steps live in scripts of their own
 * (`gate-pest.mjs`, `gate-pint.mjs`, `gate-rector.mjs`, `gate-browser.mjs`), each runnable
 * alone and each called by `local-ci.mjs` (#295). Both callers need the same console output,
 * the same spawn rules, the same tool discovery and the same PHP memory limit, so a single copy
 * of those lives here.
 */

import { spawnSync } from 'node:child_process'
import { existsSync, realpathSync } from 'node:fs'
import { homedir } from 'node:os'
import { join, resolve } from 'node:path'
import process from 'node:process'
import { pathToFileURL } from 'node:url'
import { parseArgs } from 'node:util'
import { isExecutable, needsShell, which, winQuote } from './spawn-helpers.mjs'

// --- console helpers -------------------------------------------------------

const useColor = process.stdout.isTTY && !process.env.NO_COLOR
const paint = (code, text) => (useColor ? `\x1b[${code}m${text}\x1b[0m` : text)
export const cyan = (t) => paint('36', t)
export const green = (t) => paint('32', t)
export const red = (t) => paint('31', t)
export const yellow = (t) => paint('33', t)

export function step(message) {
    console.log('')
    console.log(cyan(`==> ${message}`))
}

export function warn(message) {
    console.error(yellow(`WARNING: ${message}`))
}

// --- process execution -----------------------------------------------------

// `winQuote`, `needsShell` and `which` live in ./spawn-helpers.mjs — shared with
// worktree-slots.mjs, because the CVE-2024-27980 shim rule they encode is one a
// second copy would drift on silently (UAMS-Web/uams-statamic-archive#1948).

// Run a command, streaming its output to the console. Returns the exit code.
export function run(exe, args, cwd, extraEnv) {
    const shell = needsShell(exe)
    const res = spawnSync(
        shell ? winQuote(exe) : exe,
        shell ? args.map(winQuote) : args,
        { cwd, stdio: 'inherit', shell, ...(extraEnv ? { env: { ...process.env, ...extraEnv } } : {}) },
    )
    if (res.error) {
        throw res.error
    }
    return res.status ?? 1
}

// Run a command and capture its trimmed stdout (exit status is not asserted, so
// a probe like the optional token read can fail softly). `ignoreStderr` discards
// stderr — mirrors the PowerShell `2>$null` on that same token read.
export function capture(exe, args, cwd, { ignoreStderr = false } = {}) {
    return captureResult(exe, args, cwd, { ignoreStderr }).stdout.trim()
}

// The same spawn, returning the RAW stdout alongside the exit status.
//
// Two callers need what `capture()` discards. A NUL-delimited record stream must
// not be trimmed: `git status --porcelain -z` writes ` M path` for a worktree-only
// modification, and trimming eats the leading status column — the path then loses
// its first character, and a short enough record is dropped entirely, which reads
// as a clean worktree. And a probe whose *absence of output* means "nothing wrong"
// has to distinguish that from a probe that failed: without the status, a git that
// exited non-zero printing nothing is byte-identical to a clean answer.
export function captureResult(exe, args, cwd, { ignoreStderr = false } = {}) {
    const shell = needsShell(exe)
    const res = spawnSync(
        shell ? winQuote(exe) : exe,
        shell ? args.map(winQuote) : args,
        { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', ignoreStderr ? 'ignore' : 'inherit'], shell },
    )
    if (res.error) {
        throw res.error
    }
    return { stdout: res.stdout ?? '', status: res.status ?? 1 }
}

// --- PHP memory limit ------------------------------------------------------

// CI's setup-php leaves PHP's memory_limit at its -1 (unlimited) default, so the
// remote suite has no ceiling. A local CLI does: Herd's PHP caps it (2G here), which
// the full framework-booting suite blows past mid-run as the content set grows (a
// fatal OOM, not a test failure). Raise the limit for every PHP process the runner
// starts, and forward it to the paratest workers, to keep parity with CI. Override with
// LOCAL_CI_PHP_MEMORY_LIMIT (any PHP ini shorthand, e.g. `8G` or `-1` for unlimited).
export const PHP_MEMORY_LIMIT = process.env.LOCAL_CI_PHP_MEMORY_LIMIT || '4G'

// Prefix a PHP argv with the runner's memory limit (UAMS-Web/uams-statamic-archive#3212).
export function withPhpMemoryLimit(args, limit = PHP_MEMORY_LIMIT) {
    return ['-d', `memory_limit=${limit}`, ...args]
}

// --- tool discovery --------------------------------------------------------

// Resolve a toolchain binary to an absolute path so a missing or misconfigured
// tool fails here with an actionable message — never as a path-not-found error
// deep inside a job. Order: explicit override path(s), then a version-pinned Herd
// binary where one exists, then the command on PATH.
export function resolveTool({ name, candidates = [], onPath, hint, envVar }) {
    for (const [index, candidate] of candidates.entries()) {
        if (! candidate || ! existsSync(candidate)) {
            continue
        }

        // Existence is not the question — `spawnSync` needs something the OS will RUN. An
        // override pointing at a file that exists but cannot be executed used to be accepted
        // here and fail much later as a bare `EACCES` (POSIX) or `EFTYPE` (a `.phar` on
        // Windows), which reads as "the tool is broken" rather than "this variable is wrong".
        // Refuse it where the choice was made, and name the thing that made it (UAMS-Web/uams-statamic-archive#2335).
        if (! isExecutable(candidate)) {
            const chose = index === 0 && envVar ? `$${envVar}` : 'this candidate path'

            throw new Error(
                `Required tool '${name}' resolved to '${candidate}', which this OS cannot execute `
                + `(${chose}). ${hint}`
            )
        }

        return candidate
    }
    const found = which(onPath)
    if (found) {
        return found
    }
    throw new Error(`Required tool '${name}' was not found. ${hint}`)
}

// Herd's own bin folder, which macOS keeps off PATH for a non-login shell.
export function herdMacBin() {
    return process.platform === 'darwin' ? join(homedir(), 'Library', 'Application Support', 'Herd', 'bin') : null
}

export function resolveGit() {
    return resolveTool({
        name: 'git',
        candidates: [process.env.LOCAL_CI_GIT],
        onPath: 'git',
        hint: 'Install git or set $LOCAL_CI_GIT to its path.',
        envVar: 'LOCAL_CI_GIT',
    })
}

export function resolvePhp() {
    const herd = herdMacBin()

    return resolveTool({
        name: 'PHP 8.4',
        candidates: [process.env.LOCAL_CI_PHP, herd && join(herd, 'php84')],
        onPath: 'php',
        hint: 'Install PHP 8.4 — via Laravel Herd (https://herd.laravel.com) — or set $LOCAL_CI_PHP to its path.',
        envVar: 'LOCAL_CI_PHP',
    })
}

export function resolveNpm() {
    return resolveTool({
        name: 'npm',
        candidates: [process.env.LOCAL_CI_NPM],
        onPath: 'npm',
        hint: 'Install Node 22 (which bundles npm) or set $LOCAL_CI_NPM to its path.',
        envVar: 'LOCAL_CI_NPM',
    })
}

export function resolveNpx() {
    return resolveTool({
        name: 'npx',
        candidates: [process.env.LOCAL_CI_NPX],
        onPath: 'npx',
        hint: 'Install Node 22 (which bundles npx) or set $LOCAL_CI_NPX to its path.',
        envVar: 'LOCAL_CI_NPX',
    })
}

// How a step starts PHP: always with the memory limit (UAMS-Web/uams-statamic-archive#3212).
export function phpRunner(php) {
    return (args, cwd, extraEnv) => run(php, withPhpMemoryLimit(args), cwd, extraEnv)
}

// --- command line ----------------------------------------------------------

/**
 * Whether the module at `moduleUrl` is the script node was started with.
 *
 * Node resolves links in a module's URL and leaves `argv[1]` as typed, so the two are compared
 * after resolving both. Compared as they stand, a script reached through a symbolic link or a
 * junction would run nothing and exit 0, which reads as a pass.
 *
 * @param  {string}  moduleUrl  The caller's `import.meta.url`.
 * @return {boolean}
 */
export function isMainModule(moduleUrl) {
    const entry = process.argv[1]

    if (! entry) {
        return false
    }

    try {
        return moduleUrl === pathToFileURL(realpathSync(resolve(entry))).href
    } catch {
        return false
    }
}

/**
 * Run a step script as a command: parse its flags, run it, and exit with its code.
 *
 * Flags are strict, so a mistyped one refuses rather than running the step with a default the
 * caller did not mean. A refusal or a missing tool exits 2, which no step returns for its own
 * reasons unless a tool it ran did, and prints one line rather than a stack trace.
 *
 * @param  {Record<string, {type: string}>}  options  The script's flags, beside the common `--tree`.
 * @param  {(values: Record<string, *>) => number}  body  Runs the step and returns its exit code.
 * @return {void}
 */
export function runCli(options, body) {
    try {
        const { values } = parseArgs({
            args: process.argv.slice(2),
            options: { tree: { type: 'string' }, ...options },
            strict: true,
        })

        // Absolute, as every step passes the tree both as a spawn cwd and to `git -C`, which would
        // apply a relative path twice.
        process.exitCode = body({ ...values, tree: resolve(values.tree ?? process.cwd()) })
    } catch (error) {
        console.error(red(error.message))
        process.exitCode = 2
    }
}
