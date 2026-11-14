#!/usr/bin/env node
/**
 * gate-browser.mjs: the gate's `browser` step: Playwright, both asset builds, `tests/Browser`.
 *
 *     node scripts/gate-browser.mjs [--tree <path>] [--skip-build]
 *
 * Runs against an installed checkout, `--tree` or the working directory. It exits with the first
 * failing build's status or the suite's, or 2 for a wrong flag or a missing tool. `--skip-build`
 * keeps the bundles already in the tree, which is right only when they were built from it.
 * `local-ci.json` runs it as the `browser` job (#296).
 */

import process from 'node:process'
import { isMainModule, phpRunner, resolveNpm, resolveNpx, resolvePhp, run, runCli, step } from './gate-process.mjs'

/**
 * @param  {{tree: string, npm: string, npx: string, php: string, reuseBuild?: boolean}}  options
 * @return {number}  The step's exit code.
 */
export function gateBrowser({ tree, npm, npx, php, reuseBuild = false }) {
    const runPhp = phpRunner(php)

    // PARITY: the browser job in ci.yml.
    // `--with-deps` (used in ci.yml on the Linux runner) is omitted here: it
    // only installs Linux system libraries and is a no-op on macOS and Windows.
    step('JOB browser - installing Playwright Chromium')
    run(npx, ['playwright', 'install', 'chromium'], tree)

    // The suite needs BOTH bundles on disk: the front-end theme smokes (KB
    // rating active-state, the uams_2020 runtime-JS smoke) load the compiled
    // `public/build`, and the CP smokes load `public/vendor/app`.
    //
    // ci.yml gets a FRESH RUNNER per job, so its browser job must always build
    // them itself. This runner is the opposite: every job shares ONE worktree,
    // so in a full run the `build` job has already produced exactly these two
    // bundles with byte-identical commands, and repeating them is duplicated
    // cost for nothing (UAMS-Web/uams-statamic-archive#1727).
    //
    // So the caller decides, through `--skip-build`: `local-ci.json`'s browser job
    // passes it, which is sound only because `build` ran AND exited 0 earlier in
    // the same invocation. `scripts/ci-local.mjs` adds `build` to any `--jobs`
    // subset naming `browser`, and a `build` that FAILED cannot reach here at all:
    // the chain stops the run before every later job, browser included. Run by
    // hand without the flag, this builds, which keeps a standalone run honest.
    let bf = 0
    let bc = 0

    if (reuseBuild) {
        step("JOB browser - reusing this run's `build` output (npm run build + cp:build already done in this worktree)")
    } else {
        step('JOB browser - npm run build')
        bf = run(npm, ['run', 'build'], tree)
        step('JOB browser - npm run cp:build')
        bc = run(npm, ['run', 'cp:build'], tree)
    }

    // Mirrors the browser job's "Run CP browser smokes" step in ci.yml, less its
    // `--shard` and `--log-junit`: ci.yml splits the suite into four Linux shards and
    // merges their reports in `browser-report` (#521); here it runs whole, in one process.
    // tests/Browser holds the CP smokes and the axe-core accessibility gate (UAMS-Web/uams-statamic-archive#1046).
    step('JOB browser - pest tests/Browser')
    const bt = runPhp(['vendor/bin/pest', 'tests/Browser'], tree)
    return bf !== 0 ? bf : (bc !== 0 ? bc : bt)
    // PARITY-END
}

if (isMainModule(import.meta.url)) {
    runCli({ 'skip-build': { type: 'boolean' } }, ({ tree, 'skip-build': skipBuild }) => gateBrowser({
        tree,
        npm: resolveNpm(),
        npx: resolveNpx(),
        php: resolvePhp(),
        reuseBuild: skipBuild === true,
    }))
}
