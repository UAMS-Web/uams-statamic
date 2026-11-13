#!/usr/bin/env node
/**
 * prepare-dependencies.mjs: the `dependencies` job in `local-ci.json` (#296).
 *
 *     node scripts/prepare-dependencies.mjs [--tree <path>]
 *
 * The shared runner installs nothing, and its slot is reused from run to run, so without this a
 * pull request that moves `composer.lock` is validated against whatever the slot held last and
 * passes against the wrong versions: the stale-tree green `.claude/rules/worktrees.md` names.
 *
 * Where it runs decides what it does:
 *
 *   - In a slot the shared runner created (its `local-ci-slot` marker is in the slot's git
 *     directory), it does what the private runner did before its jobs: initializes the gate
 *     submodules, clearing files a killed run stranded in an uninitialized one; installs from
 *     `composer.lock` with `--no-scripts`; brings patched packages in line with
 *     `patches.lock.json`; clears the reused tree's file caches; prepares `.env`, the app key and
 *     `post-autoload-dump`; and runs `npm ci`.
 *   - Anywhere else (a developer's checkout, a work slot) it installs NOTHING. Installing into
 *     someone's working tree is their decision, so it reports what is out of date and fails,
 *     including patched packages `vendor/` was never recorded as carrying the patches of:
 *     `composer install` reports "Nothing to install" for a package whose patch is new, so a
 *     version check alone passes an unpatched `vendor/`. `--sync-patches` is that decision, made
 *     explicitly: it reinstalls those packages in place and records the lock.
 *
 * The patch step runs here rather than as the runner's `prepare.patches`, because `prepare` runs
 * before any job: it would `composer reinstall` the slot's OLD version of a package against the
 * commit's NEW patch. Installing first and re-syncing second is the order the private runner
 * used. The patch record is read, then dropped, before the install: read first, because it is
 * what the sync compares against (dropped first, every run would reinstall every patched package);
 * dropped before the install, so one that dies after re-extracting a patched package cannot
 * leave a record vouching for an unpatched `vendor/`.
 */

import { copyFileSync, existsSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import process from 'node:process'

import { dropPatchRecord, installedPackages, PATCH_LOCK_RECORD, patchReinstallPackages, SLOT_MARKER, syncPatchedVendor } from '../.claude/skills/local-ci/local-ci.mjs'
import { capture, captureResult, isMainModule, phpRunner, resolveGit, resolveNpm, resolvePhp, run, runCli, step, warn } from './gate-process.mjs'
import { clearFileCaches } from './gate-pest.mjs'
import { GATE_SUBMODULES } from './gate-slot.mjs'
import { which } from './spawn-helpers.mjs'

/**
 * Read a JSON file, or null when it is absent or not JSON.
 *
 * @param  {string}  path
 * @return {*}
 */
function readJson(path) {
    try {
        return JSON.parse(readFileSync(path, 'utf8'))
    } catch {
        return null
    }
}

/**
 * Read a text file, or null when it is absent.
 *
 * @param  {string}  path
 * @return {string|null}
 */
function readText(path) {
    return existsSync(path) ? readFileSync(path, 'utf8') : null
}

/**
 * The packages `composer.lock` pins that `vendor/` does not hold at that version and reference.
 * An absent or unreadable `installed.json` reports every locked package.
 *
 * @param  {*}  lock       The parsed `composer.lock`.
 * @param  {*}  installed  The parsed `vendor/composer/installed.json`.
 * @return {string[]}  One `name: wanted (installed)` line per mismatch.
 */
export function composerDrift(lock, installed) {
    const pinned = [...(lock?.packages ?? []), ...(lock?.['packages-dev'] ?? [])]
    const have = new Map((installed?.packages ?? []).map((pkg) => [pkg.name, pkg]))
    const drift = []

    for (const pkg of pinned) {
        const got = have.get(pkg.name)
        const wantRef = pkg.dist?.reference ?? pkg.source?.reference ?? null
        const gotRef = got?.dist?.reference ?? got?.source?.reference ?? null

        if (! got) {
            drift.push(`${pkg.name}: ${pkg.version} (not installed)`)
        } else if (got.version !== pkg.version || wantRef !== gotRef) {
            drift.push(`${pkg.name}: ${pkg.version} (installed ${got.version})`)
        }
    }

    return drift
}

/**
 * The packages `package-lock.json` pins that `node_modules/` does not hold at that version, read
 * from npm's own record of the tree (`node_modules/.package-lock.json`).
 *
 * @param  {*}  lock       The parsed `package-lock.json`.
 * @param  {*}  installed  The parsed `node_modules/.package-lock.json`.
 * @return {string[]}  One `path: wanted (installed)` line per mismatch.
 */
export function npmDrift(lock, installed) {
    const have = installed?.packages ?? {}
    const drift = []

    for (const [path, pkg] of Object.entries(lock?.packages ?? {})) {
        // The root entry, a workspace link, and an optional package npm skipped for this platform
        if (path === '' || pkg.link || (pkg.optional && ! have[path])) {
            continue
        }

        const got = have[path]

        if (! got) {
            drift.push(`${path}: ${pkg.version} (not installed)`)
        } else if (got.version !== pkg.version) {
            drift.push(`${path}: ${pkg.version} (installed ${got.version})`)
        }
    }

    return drift
}

/**
 * Whether `tree` is a worktree the shared runner created as its slot.
 *
 * @param  {string}  tree
 * @param  {string}  git
 * @return {boolean}
 */
export function isRunnerSlot(tree, git) {
    const result = captureResult(git, ['-C', tree, 'rev-parse', '--absolute-git-dir'], tree, { ignoreStderr: true })

    return result.status === 0 && existsSync(join(result.stdout.trim(), SLOT_MARKER))
}

/**
 * The entries an uninitialized submodule directory holds, which `git submodule update --init`
 * refuses to clone over. An initialized one (it carries `.git`) or an absent one holds none.
 *
 * @param  {string}  path
 * @return {string[]}
 */
export function strandedEntries(path) {
    if (! existsSync(path) || existsSync(join(path, '.git'))) {
        return []
    }

    return readdirSync(path)
}

/**
 * Initialize the gate submodules, shallow, authenticating with Composer's GitHub token when one
 * is set and with the user's credential helper otherwise.
 *
 * Files a killed run stranded in an uninitialized submodule directory are cleared first, naming
 * each: that directory can hold only app output in a runner-owned tree, and the clone refuses a
 * non-empty destination.
 *
 * @param  {string}  tree
 * @param  {string}  git
 * @param  {string}  composer
 * @return {number}
 */
function initSubmodules(tree, git, composer) {
    step('Initializing the gate submodules: ' + GATE_SUBMODULES.join(', '))

    for (const name of GATE_SUBMODULES) {
        const stranded = strandedEntries(join(tree, name))

        if (stranded.length > 0) {
            warn(`${name}/ is not initialized but holds ${stranded.length} path(s) an earlier run left; clearing it so the clone can land:`)
            stranded.forEach((entry) => console.error(`  ${name}/${entry}`))
            rmSync(join(tree, name), { recursive: true, force: true })
        }
    }

    const token = capture(composer, ['config', '--global', '--auth', 'github-oauth.github.com'], tree, { ignoreStderr: true })
    const auth = token ? ['-c', `url.https://x-access-token:${token}@github.com/.insteadOf=https://github.com/`] : []

    if (! token) {
        warn('No Composer github-oauth token found; initializing the submodules through your git credential helper.')
    }

    return run(git, ['-C', tree, ...auth, 'submodule', 'update', '--init', '--depth', '1', ...GATE_SUBMODULES], tree)
}

/**
 * Install into the runner's slot. Returns the exit status of the first step that fails.
 *
 * @param  {string}  tree
 * @return {number}
 */
function installSlot(tree) {
    const git = resolveGit()
    const php = resolvePhp()
    const runPhp = phpRunner(php)
    const composer = which('composer')
    const npm = resolveNpm()

    if (! composer) {
        throw new Error('composer is not on PATH.')
    }

    console.log('Runner slot: installing from composer.lock and package-lock.json.')

    // What vendor/ was last brought in line with, read before the install step drops it
    const recordedLock = readText(join(tree, PATCH_LOCK_RECORD))

    const steps = [
        () => initSubmodules(tree, git, composer),
        () => {
            // composer.json declares ext-imagick, so name the omission before Composer's platform error
            const probe = captureResult(php, ['-r', 'echo extension_loaded("imagick") ? "1" : "";'], tree)

            if (probe.status !== 0) {
                console.error(`${php} did not run (exit ${probe.status}), so the imagick check could not be made. Check that this PHP starts: \`${php} --version\`.`)

                return 1
            }
            if (probe.stdout.trim() === '1') {
                return 0
            }
            console.error('This PHP has no `imagick` extension, which composer.json declares as `ext-imagick`. Install it first: see README_Imagick.md.')

            return 1
        },
        () => {
            // Only a completed patch sync below may write the record again
            dropPatchRecord(tree)
            step('composer install (--no-scripts)')

            return run(composer, ['install', '--no-scripts', '--no-interaction', '--no-progress', '--prefer-dist'], tree)
        },
        () => syncPatches(tree, composer, recordedLock),
        () => {
            step('Clearing reused-worktree file caches before the first app boot')
            clearFileCaches(git, tree)

            return 0
        },
        () => {
            step('Preparing .env, the app key and the Statamic install')

            if (! existsSync(join(tree, '.env'))) {
                copyFileSync(join(tree, '.env.example'), join(tree, '.env'))
            }

            if (/^APP_KEY="?base64:/m.test(readFileSync(join(tree, '.env'), 'utf8'))) {
                return 0
            }

            return runPhp(['artisan', 'key:generate'], tree)
        },
        () => run(composer, ['run-script', 'post-autoload-dump', '--no-interaction'], tree),
        () => {
            step('npm ci')

            return run(npm, ['ci', '--no-audit', '--no-fund'], tree)
        },
    ]

    for (const next of steps) {
        const code = next()

        if (code !== 0) {
            return code
        }
    }

    return 0
}

/**
 * Reinstall the packages whose patches differ from the ones `vendor/` was last brought in line
 * with, then record the lock.
 *
 * @param  {string}       tree
 * @param  {string}       composer
 * @param  {string|null}  recordedLock  The record as it stood before this run touched it.
 * @return {number}
 */
function syncPatches(tree, composer, recordedLock) {
    const installedJson = readText(join(tree, 'vendor', 'composer', 'installed.json'))

    if (installedJson === null) {
        console.error('vendor/composer/installed.json is missing after composer install.')

        return 1
    }

    try {
        const packages = syncPatchedVendor({
            currentLock: readText(join(tree, 'patches.lock.json')),
            recordedLock,
            installed: installedPackages(installedJson),
            reinstall: (names) => run(composer, ['reinstall', ...names, '--no-interaction', '--no-progress'], tree),
            writeRecord: (text) => writeFileSync(join(tree, PATCH_LOCK_RECORD), text),
            removeRecord: () => rmSync(join(tree, PATCH_LOCK_RECORD), { force: true }),
        })
        console.log(packages.length > 0 ? `Patches: reinstalled ${packages.join(' ')}.` : 'Patches: vendor/ matches patches.lock.json.')

        return 0
    } catch (error) {
        console.error(error.message)

        return 1
    }
}

/**
 * The patched packages whose patches `vendor/` is not recorded as carrying: every one, when there
 * is no record, since `vendor/composer/installed.json` says nothing about patches.
 *
 * @param  {string}  tree
 * @return {string[]}
 */
export function patchDrift(tree) {
    const installedJson = readText(join(tree, 'vendor', 'composer', 'installed.json'))

    if (installedJson === null) {
        return []
    }

    return patchReinstallPackages(readText(join(tree, 'patches.lock.json')), readText(join(tree, PATCH_LOCK_RECORD)), installedPackages(installedJson))
}

/**
 * Report drift outside the runner's slot, installing nothing.
 *
 * @param  {string}  tree
 * @return {number}
 */
export function reportDrift(tree) {
    const composer = composerDrift(readJson(join(tree, 'composer.lock')), readJson(join(tree, 'vendor', 'composer', 'installed.json')))
    const npm = npmDrift(readJson(join(tree, 'package-lock.json')), readJson(join(tree, 'node_modules', '.package-lock.json')))
    const patched = patchDrift(tree)

    if (patched.length > 0) {
        console.error(`Not a runner slot, so nothing was installed, and vendor/ is not recorded as carrying the patches patches.lock.json declares for: ${patched.join(', ')}.`)
        console.error("Run node scripts/prepare-dependencies.mjs --sync-patches here to reinstall them and record it, or validate in the runner's slot with node scripts/ci-local.mjs --branch <branch>.")
    }

    if (composer.length === 0 && npm.length === 0) {
        if (patched.length > 0) {
            return 1
        }

        console.log('vendor/ and node_modules/ match the locks. Not a runner slot, so nothing was installed.')

        return 0
    }

    console.error('Not a runner slot, so nothing was installed, and this tree does not match its locks:')
    composer.forEach((line) => console.error(`  composer ${line}`))
    npm.forEach((line) => console.error(`  npm ${line}`))
    console.error(`Run ${composer.length > 0 ? '`composer install`' : ''}${composer.length > 0 && npm.length > 0 ? ' and ' : ''}${npm.length > 0 ? '`npm ci`' : ''} here, or validate in the runner's slot with node scripts/ci-local.mjs --branch <branch>.`)

    return 1
}

if (isMainModule(import.meta.url)) {
    runCli({ 'sync-patches': { type: 'boolean' } }, ({ tree, 'sync-patches': sync }) => {
        if (sync) {
            const composer = which('composer')

            if (! composer) {
                throw new Error('composer is not on PATH.')
            }

            return syncPatches(tree, composer, readText(join(tree, PATCH_LOCK_RECORD)))
        }

        return isRunnerSlot(tree, resolveGit()) ? installSlot(tree) : reportDrift(tree)
    })
}
