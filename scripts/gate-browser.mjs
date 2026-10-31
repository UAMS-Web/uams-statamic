#!/usr/bin/env node
/**
 * gate-browser.mjs: the gate's `browser` step: Playwright, both asset builds, `tests/Browser`.
 *
 *     node scripts/gate-browser.mjs [--tree <path>] [--skip-build]
 *
 * Runs against an installed checkout, `--tree` or the working directory. It exits with the first
 * failing build's status or the suite's, or 2 for a wrong flag or a missing tool. `--skip-build`
 * keeps the bundles already in the tree, which is right only when they were built from it.
 * `local-ci.mjs` calls `gateBrowser` (#295).
 */

import process from 'node:process'
import { pathToFileURL } from 'node:url'
import { phpRunner, resolveNpm, resolveNpx, resolvePhp, run, runCli, step } from './gate-process.mjs'

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
    // So the caller decides, through `reuseBuild`: `canReuseBuildOutput()` in the
    // runner, or `--skip-build` by hand. Skip only when `build` actually ran AND
    // exited 0 in THIS invocation.
    // `results` holds only jobs already executed this run, so a `--jobs
    // browser`-only run (or any selection omitting `build`) reads undefined
    // and still builds — which is what keeps a standalone browser run honest.
    // A `build` that FAILED cannot reach here at all: the chain marks the run
    // broken and skips every later job, browser included.
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

    // Mirrors the browser job's "Run CP browser smokes" step in ci.yml.
    // tests/Browser holds the CP smokes and the axe-core accessibility gate (UAMS-Web/uams-statamic-archive#1046).
    step('JOB browser - pest tests/Browser')
    const bt = runPhp(['vendor/bin/pest', 'tests/Browser'], tree)
    return bf !== 0 ? bf : (bc !== 0 ? bc : bt)
    // PARITY-END
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    runCli({ 'skip-build': { type: 'boolean' } }, ({ tree, 'skip-build': skipBuild }) => gateBrowser({
        tree,
        npm: resolveNpm(),
        npx: resolveNpx(),
        php: resolvePhp(),
        reuseBuild: skipBuild === true,
    }))
}
