#!/usr/bin/env node
/**
 * gate-rector.mjs: the gate's `rector` step: the gating profile, as a dry run.
 *
 *     node scripts/gate-rector.mjs [--tree <path>]
 *
 * Runs against an installed checkout, `--tree` or the working directory, and exits with Rector's
 * own status, or 2 for a wrong flag or a missing tool. `local-ci.mjs` calls `gateRector` (#295).
 */

import { mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import process from 'node:process'
import { pathToFileURL } from 'node:url'
import { phpRunner, resolvePhp, runCli, step } from './gate-process.mjs'

/**
 * @param  {{tree: string, php: string}}  options
 * @return {number}  The step's exit code.
 */
export function gateRector({ tree, php }) {
    const runPhp = phpRunner(php)

    // PARITY: the rector job's "Run Rector (gating profile, dry-run)" step in ci.yml.
    //
    // Runs the DEFAULT profile — the gating one — so this asserts the
    // same zero baseline the remote job does; `rector-sweep.php` is deliberately
    // not run here (its extra rules are judgment calls, UAMS-Web/uams-statamic-archive#1720).
    //
    // The private temp dir is load-bearing, not hygiene. Rector's file cache
    // (sys_get_temp_dir()/rector_cached_files) does NOT key on the active rule
    // set, so a warm cache written by a different profile makes a later run
    // silently UNDER-report — measured while building UAMS-Web/uams-statamic-archive#1720: 124 files cold
    // versus 92 warm. Under-reporting is a false GREEN, so a gate reusing a warm
    // cache can pass on a backlog it never re-analyzed. That risk is sharper
    // here than on CI: a runner starts clean, but this script reuses a
    // long-lived worktree on a developer machine where `composer refactor`,
    // another worktree's run, or a previous sweep may have warmed the shared
    // directory already. A fresh dir starts empty, so no `--clear-cache` (which
    // would race a concurrent Rector process) is needed. PHP reads TMPDIR on
    // macOS/Linux but TMP/TEMP on Windows, so all three are set — matching
    // tests/Feature/RectorProfileSplitTest.php.
    step('JOB rector - rector --dry-run (gating profile)')
    const rectorTmp = mkdtempSync(join(tmpdir(), 'local-ci-rector-'))
    try {
        return runPhp(['vendor/bin/rector', '--dry-run', '--no-progress-bar'], tree, {
            TMPDIR: rectorTmp,
            TMP: rectorTmp,
            TEMP: rectorTmp,
        })
    } finally {
        // Best-effort cleanup; a leftover temp dir is harmless.
        rmSync(rectorTmp, { recursive: true, force: true })
    }
    // PARITY-END
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    runCli({}, ({ tree }) => gateRector({ tree, php: resolvePhp() }))
}
