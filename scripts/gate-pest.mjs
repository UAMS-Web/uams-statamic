#!/usr/bin/env node
/**
 * gate-pest.mjs: the gate's `pest` step: both bands, with everything they need around them.
 *
 *     node scripts/gate-pest.mjs [--tree <path>] [--no-reset]
 *
 * Runs against an installed checkout, `--tree` or the working directory. The step owns its whole
 * setup: the reset of the file caches and the Stache, the Stache seed and the route cache, the
 * testing environment those two run under, and the memory limit. A caller adds no environment
 * and runs no step before it.
 *
 * The reset deletes every ignored file under `storage/framework` and `bootstrap/cache`, sessions
 * included, so `--no-reset` is for a tree where that matters, and for a tree already reset, which
 * is how `local-ci.mjs` calls `gatePest` (#295).
 *
 * It exits with the status of the first failing stage, or 2 for a wrong flag or a missing tool.
 */

import { existsSync, rmSync } from 'node:fs'
import { join } from 'node:path'
import process from 'node:process'
import { pathToFileURL } from 'node:url'
import { captureResult, PHP_MEMORY_LIMIT, phpRunner, red, resolveGit, resolvePhp, run, runCli, step, warn } from './gate-process.mjs'
import { GATE_SUBMODULES } from './gate-slot.mjs'

export const KNOWN_CONTENT_LITTER = []

/**
 * Split what the suite just wrote into the content/ submodule from what was
 * already there, holding known debt aside.
 */
export function contentLitter(before, after) {
    const existing = new Set(before.map((entry) => entry.path))
    const fresh = after.filter((entry) => !existing.has(entry.path))
    const isKnown = (entry) => KNOWN_CONTENT_LITTER.some((prefix) => entry.path.startsWith(prefix))

    return {
        introduced: fresh.filter((entry) => !isKnown(entry)),
        known: fresh.filter(isKnown),
        preexisting: after.filter((entry) => existing.has(entry.path)),
    }
}

// UAMS-Web/uams-statamic-archive#1889 — parse `git status --porcelain -z` into one entry per differing path.
//
// `-z` rather than the default: porcelain v1 quotes and C-escapes any path with a
// space, a quote or a non-ASCII byte, so reading the default form correctly means
// reversing that escaping first. The NUL-separated form has no quoting at all, which
// removes the whole class of parse bug from a check whose output is a list of paths.
//
// Each entry is `XY <path>`; a rename or copy adds a SECOND field holding the
// origin path. Consuming that field here is what stops it being read as a further
// entry with two characters sliced off the front of its path.
export function worktreeDriftEntries(porcelain) {
    const fields = porcelain.split('\0').filter((field) => field !== '')
    const entries = []

    for (let i = 0; i < fields.length; i++) {
        const field = fields[i]
        // Shorter than `XY p` is not an entry git wrote; skipping beats slicing a
        // path out of something that is not one.
        if (field.length < 4) {
            continue
        }
        const status = field.slice(0, 2)
        entries.push({
            status,
            path: field.slice(3),
            origin: /[RC]/.test(status) ? (fields[++i] ?? null) : null,
        })
    }

    return entries
}

// UAMS-Web/uams-statamic-archive#1889 — say what a status code means for the run, not what it means to git.
//
// The reader's question is always "why would this change what was validated",
// and `??` is the one that has to be spelled out: a file the commit DELETED
// survives on disk as untracked, so it reads as an addition while being the exact
// residue this check exists to catch.
export function driftLabel(status) {
    if (status === '??') {
        return 'on disk but absent from the validated commit (a surviving deletion, or stray scratch)'
    }
    if (status.includes('D')) {
        return 'tracked by the validated commit but missing from the worktree'
    }
    if (status.includes('M') || status.includes('T')) {
        return 'content differs from the validated commit'
    }
    return 'differs from the validated commit'
}

/**
 * Delete what a previous run compiled, without booting the app (UAMS-Web/uams-statamic-archive#2832).
 *
 * This has to be a filesystem clear: `artisan statamic:stache:clear` boots the app first, and a
 * Stache built for another `content/` revision crashes that boot. Non-fatal, as a reset hiccup
 * should not fail a run. `git clean` prints each removed path, so a leak stays visible.
 *
 * @param  {string}  git
 * @param  {string}  tree
 * @param  {string}  cwd
 * @return {void}
 */
export function clearFileCaches(git, tree, cwd = tree) {
    run(git, ['-C', tree, 'clean', '-xdf', 'storage/framework', 'bootstrap/cache'], cwd)
}

/**
 * The half of the reset that boots the app, safe once `clearFileCaches` has run.
 *
 * @param  {string}  php
 * @param  {string}  tree
 * @return {void}
 */
export function clearRuntimeCaches(php, tree) {
    const runPhp = phpRunner(php)

    runPhp(['artisan', 'optimize:clear'], tree)
    runPhp(['artisan', 'statamic:stache:clear'], tree)
}

/**
 * @param  {{tree: string, php: string, git: string, repoRoot?: string, reset?: boolean}}  options
 * @return {number}  The step's exit code.
 */
export function gatePest({ tree, php, git, repoRoot = tree, reset = true }) {
    const runPhp = phpRunner(php)

    if (reset) {
        step('JOB pest - resetting file caches and the Stache')
        clearFileCaches(git, tree, repoRoot)
        clearRuntimeCaches(php, tree)
    }

    // PARITY: the pest and pest-serial jobs in ci.yml.
    // UAMS-Web/uams-statamic-archive#1873 — snapshot each gate submodule before the bands so litter THIS
    // run created is distinguishable from a worktree that arrived dirty.
    // Returns null only when there is genuinely no submodule to read, which
    // switches the check off rather than failing a run that legitimately has
    // none.
    //
    // Keyed on the submodule being ON DISK, NOT on `--skip-submodule`. That
    // flag means "content/ is already materialized, don't re-init it" — the
    // multi-worktree recipe in the local-ci skill stages every submodule
    // serially and then fans out WITH the flag — so treating it as "no
    // content/" would switch the gate off for precisely the concurrent runs
    // that do have content and do run the bands. An initialized submodule
    // leaves a `<name>/.git` gitdir file; an uninitialized one leaves
    // nothing, which is the fact worth reading.
    //
    // `users/` is probed the same way since UAMS-Web/uams-statamic-archive#2477 checked it out: the suite
    // writes there through the file user driver, and a tracked seed account a
    // test deletes or rewrites stays that way in this reused worktree —
    // `submodule update --init` on an unchanged gitlink restores nothing —
    // while `git ls-files` keeps listing it, so nothing else would notice.
    // Test-CREATED accounts never show here: the allowlist ignores them, and
    // `--untracked-files=all` lists untracked, not ignored.
    // Mirrored by the "Check the suite left content/, users/ and resources/forms/ clean"
    // step in ci.yml.
    const submoduleStatus = (name) => {
        if (!existsSync(join(tree, name, '.git'))) {
            return null
        }
        // captureResult for the same reason UAMS-Web/uams-statamic-archive#1889 uses it: a `git` that failed
        // printing nothing is otherwise indistinguishable from a clean tree,
        // and a check that silently verifies nothing is the bug it guards.
        const probe = captureResult(
            git,
            ['-C', join(tree, name), 'status', '--porcelain', '-z', '--untracked-files=all'],
            repoRoot,
        )
        if (probe.status !== 0) {
            throw new Error(
                `\`git status\` in ${join(tree, name)} exited ${probe.status}, so the suite's `
                + `effect on the ${name} submodule could not be checked.`,
            )
        }
        return worktreeDriftEntries(probe.stdout)
    }

    const litterBefore = Object.fromEntries(GATE_SUBMODULES.map((name) => [name, submoduleStatus(name)]))

    // Mirrors the pest job's two run steps in ci.yml — the parallel pass
    // (excluding the serial group) then the serial tail. The per-worker
    // isolated cache (config/cache.php) plus the per-process stache warm in
    // tests/CreatesApplication.php remove the need for a separate stache:warm
    // step. Two local-only `-d` additions (CI runs memory unlimited, so there
    // is nothing to mirror): `-d pcov.enabled=0` keeps the run coverage-free,
    // and `-d memory_limit=${PHP_MEMORY_LIMIT}` raises the ceiling. The parallel
    // pass forwards the limit to the paratest workers via `--passthru-php` —
    // they run as their own PHP processes at the system CLI memory_limit
    // otherwise (~2G on Herd), which the growing content set now OOMs mid-run;
    // the single-process serial tail takes it directly.
    // Build the Stache seed once before the parallel suite so each of the
    // ~12 paratest workers copies it into its own per-worker cache instead
    // of running statamic:stache:warm: 12 concurrent warms saturate the
    // disk (~168s each vs 21s alone), the dominant parallel cost (UAMS-Web/uams-statamic-archive#1537).
    // DB_DATABASE=:memory: mirrors phpunit.xml: the warm probes the DB for
    // computed-field indexes, so it needs a connectable database (a fresh
    // worktree has no sqlite file, like a fresh CI checkout).
    // Mirrors the "Build Stache seed (testing env)" step in ci.yml.
    step('JOB pest - build Stache seed (testing env)')
    const seedCode = runPhp(['artisan', 'test:seed-stache'], tree, { APP_ENV: 'testing', DB_DATABASE: ':memory:' })
    // Build the route cache in the testing env before the suite so the URL
    // generator uses the compiled O(1) RouteCollection instead of scanning
    // the full table. mitydigital/sitemapamic registers one sitemap route
    // per site (255 on this multisite), and uncached URL generation scales
    // with route count — ~2x slower on the render-heavy suite (UAMS-Web/uams-statamic-archive#1537).
    // Built as APP_ENV=testing so an env-conditional route (e.g. the
    // local-only dev preview route) is baked exactly as the tests expect;
    // a non-zero here also surfaces a route the prod deploy's route:cache
    // could not serialize. Cleared after the tail so a cached table never
    // leaks into a reused worktree.
    // Built AFTER the Stache seed (UAMS-Web/uams-statamic-archive#3106): routes/web.php registers each
    // site's feed.xml only when `Collection::findByHandle('posts')` resolves
    // as the route file loads. Compiled against the cold Stache the chain
    // leaves behind, that read comes up empty and the table omits all 244
    // feed routes, so two PostsFeedRouteTest cases 404 on a --jobs pest run
    // and pass in the full chain, where earlier jobs have warmed it.
    // Mirrors the "Build route cache (testing env)" + "Clear route
    // cache" steps in the pest job in ci.yml.
    step('JOB pest - build route cache (testing env)')
    const cacheCode = runPhp(['artisan', 'route:cache'], tree, { APP_ENV: 'testing' })
    // Two shared locks would otherwise 503 a front-end request whenever a
    // sibling worker holds them, and phpunit.xml disables both: Statamic's
    // static-cache per-URL lock (via the NullCacher) and the Stache warming
    // lock, a flock over the single, non-tokenized
    // storage/statamic/stache-locks held for the duration of a warm or a
    // Duplicates::find() (UAMS-Web/uams-statamic-archive#1689). The seed step above makes a warm the
    // fallback rather than the norm here, which narrows that window
    // without closing it.
    // NO --tia HERE, DELIBERATELY (UAMS-Web/uams-statamic-archive#1717). Pest 5's test-impact-analysis
    // engine replays cached results instead of executing tests it believes
    // a change cannot reach. It is excluded from BOTH bands below for three
    // independent reasons, any one of which is disqualifying:
    //
    //   1. No coverage driver. Tia builds its graph from coverage, and both
    //      pest calls below pass `-d pcov.enabled=0` (ci.yml pins
    //      `coverage: none`). With no driver it records zero edges, and a
    //      graph with no edges marks nothing affected -- so every run
    //      replays and reports a green suite having executed nothing. Pest
    //      5.0.1 does not warn about this.
    //   2. No persistent cache. Tia's graph lives at tests/.pest/tia/,
    //      which #192 pins via `pest()->tia()->directory()` instead of the
    //      default ~/.pest/tia/<key>/. That default keys on the normalized
    //      remote URL with the folder name in front, and in a linked
    //      worktree `Storage::rawOriginUrl()` does `is_file()` on `.git` (a
    //      FILE there, not a directory) and falls back to a path-derived
    //      key -- so per-worktree isolation used to be an accident of that
    //      implementation rather than a design. It is now by construction,
    //      but the directory is git-ignored and this runner builds a fresh
    //      sibling worktree, so nothing survives between runs regardless.
    //   3. The content/ blind spot. `.gitmodules` sets `ignore = dirty` on
    //      the content submodule, so `git status --porcelain` -- Tia's
    //      exact change detection -- reports nothing when content changes,
    //      and a submodule path is a directory, which Tia's watch-pattern
    //      fallback drops anyway. Reproduced: with one content entry added,
    //      --tia returned `2 passed, 2 replayed` (exit 0) where --no-tia
    //      returned `1 failed` (exit 1).
    //
    // Tia IS available locally, opt-in, behind a guard that refuses to run
    // under conditions 1 and 3: `composer test:tia`
    // (scripts/check-tia-safe.php). The gate deliberately does not use it --
    // an exhaustive run is the whole point.
    // Mirrors the pest job's note in ci.yml.
    // --log-junit feeds the skip-baseline check below; it changes nothing
    // about what runs.
    // Mirrors the "Run Pest (parallel shard...)" step in ci.yml.
    // CI runs the same command four times with `--shard=<i>/4` and merges
    // the four reports in its `pest-report` job before the band checks
    // (#387); this runner runs the band whole, so it has nothing to shard
    // or merge. scripts/local-ci.chain.test.mjs pins that the two commands
    // differ only by --shard.
    step('JOB pest - parallel suite (excluding serial group)')
    const parallelReport = join(tree, 'junit-parallel.xml')
    const serialReport = join(tree, 'junit-serial.xml')
    // The runner reuses its worktree between runs, so a report left by the
    // previous one would let a band that died before writing its own read
    // as verified against stale results — the exact silence this check
    // removes. CI checks out fresh, so it has nothing to mirror here.
    rmSync(parallelReport, { force: true })
    rmSync(serialReport, { force: true })
    const parallelCode = runPhp(['-d', 'pcov.enabled=0', 'vendor/bin/pest', '--parallel', '--exclude-group=serial', `--passthru-php=-d memory_limit=${PHP_MEMORY_LIMIT}`, '--display-all-issues', '--log-junit', parallelReport], tree)
    step('JOB pest - serial tail (group=serial)')
    const serialCode = runPhp(['-d', 'pcov.enabled=0', 'vendor/bin/pest', '--group=serial', '--display-all-issues', '--log-junit', serialReport], tree)

    // UAMS-Web/uams-statamic-archive#1744 — a band that silently skips tests would otherwise report PASS
    // having tested less than it should have. Only meaningful when the band
    // itself ran; a band that failed already fails the job, and its report
    // would be incomplete.
    // Mirrors the "Check skip baseline" steps in ci.yml.
    //
    // The guard lives in the *worktree*, i.e. in the branch under test, so a
    // branch predating UAMS-Web/uams-statamic-archive#1744 does not carry it. The runner validates arbitrary
    // branches (including older ones), so a missing guard is skipped with a
    // notice rather than failing a branch for not having been written yet.
    let baselineCode = 0
    const skipGuard = join(tree, 'scripts', 'check-skip-baseline.php')
    if (!existsSync(skipGuard)) {
        console.log('Skip baseline: guard not present on this branch (pre-#1744) — not checked.')
    } else {
        if (parallelCode === 0) {
            step('JOB pest - skip baseline (parallel band)')
            baselineCode = runPhp(['scripts/check-skip-baseline.php', `--report=${parallelReport}`, '--band=parallel'], tree)
        }
        if (baselineCode === 0 && serialCode === 0) {
            step('JOB pest - skip baseline (serial band)')
            baselineCode = runPhp(['scripts/check-skip-baseline.php', `--report=${serialReport}`, '--band=serial'], tree)
        }
    }

    // UAMS-Web/uams-statamic-archive#1890 — nothing pinned what the parallel band COSTS, so a change that
    // reclaimed it passed in silence: the UAMS-Web/uams-statamic-archive#1348 route-count tax (UAMS-Web/uams-statamic-archive#1521 →
    // UAMS-Web/uams-statamic-archive#1573) and the class that grew to 1085.3s of a 1306s band (UAMS-Web/uams-statamic-archive#1797) were
    // both found by hand, long after the fact. This reads the same reports
    // the skip guard does, so it needs no extra run. It judges the costliest
    // class by its share of the band's summed class time, which a loaded
    // host inflates uniformly and so cannot move (UAMS-Web/uams-statamic-archive#2530).
    // Mirrors the "Check slowest class" steps in ci.yml.
    //
    // Absent-on-older-branches, for the same reason as the skip guard above:
    // this runner validates arbitrary branches, and one predating UAMS-Web/uams-statamic-archive#1890 is
    // passed with a notice rather than failed for not carrying it yet.
    let slowestCode = 0
    const slowestGuard = join(tree, 'scripts', 'check-slowest-class.php')
    if (!existsSync(slowestGuard)) {
        console.log('Slowest class: guard not present on this branch (pre-#1890) — not checked.')
    } else {
        if (parallelCode === 0) {
            step('JOB pest - slowest class (parallel band)')
            slowestCode = runPhp(['scripts/check-slowest-class.php', `--report=${parallelReport}`, '--band=parallel'], tree)
        }
        if (slowestCode === 0 && serialCode === 0) {
            step('JOB pest - slowest class (serial band)')
            slowestCode = runPhp(['scripts/check-slowest-class.php', `--report=${serialReport}`, '--band=serial'], tree)
        }
    }

    // UAMS-Web/uams-statamic-archive#3018 - is the content mirror band lock still held now that the suite
    // has finished? Every test process holds it shared, and a child a test
    // spawns inherits that descriptor and keeps the lock after both bands
    // exit, so every later `test:seed-stache` in this tree is refused with
    // nothing naming a lock or a process. Runs only here, after the serial
    // tail: a live band legitimately holds the lock, so the probe must
    // never run between bands. Unconditional, like the litter check below,
    // because a failed band can leave a child behind as easily as a
    // passing one.
    // Mirrors the "Check the content mirror band lock was released"
    // step in ci.yml.
    //
    // Absent-on-older-branches, as for the guards above.
    let bandLockCode = 0
    const bandLockGuard = join(tree, 'scripts', 'check-band-lock-released.php')
    if (!existsSync(bandLockGuard)) {
        console.log('Band lock: guard not present on this branch (pre-#3018) - not checked.')
    } else {
        step('JOB pest - content mirror band lock released')
        bandLockCode = runPhp(['scripts/check-band-lock-released.php'], tree)
    }

    step('JOB pest - clear route cache')
    runPhp(['artisan', 'route:clear'], tree)

    // UAMS-Web/uams-statamic-archive#1873 — did the suite write into a live gate submodule?
    // Unconditional: a band that FAILED is exactly the case where cleanup
    // did not run, so suppressing the check on failure would hide the
    // litter in the one run most likely to have produced it.
    // Mirrored by the "Check the suite left content/, users/ and resources/forms/ clean"
    // step in ci.yml.
    //
    // The remedy differs per submodule, because the writers do. content/ is
    // written through the Stache, and a store re-root is the fix. users/ is
    // written by the file user driver, and what shows here is not a created
    // account (those are ignored by the allowlist) but damage to a tracked
    // seed account — a test that deleted every user, or rewrote one — which
    // afterEach should never have needed to undo.
    const litterRemedy = {
        content: 'A test persisted a fixture into the live submodule. Cleanup that runs in `afterEach` cannot\n'
            + 'survive a band killed at its bound or a fatal, so the file is stranded and every later run —\n'
            + 'and every `statamic:stache:warm` — reads it as real content (UAMS-Web/uams-statamic-archive#1845).\n'
            + '  Put the test on `Tests\\Support\\CollectionTreeIsolatedTestCase`, which re-roots the\n'
            + '  collection-trees store at a throwaway directory so its writes land in\n'
            + '  storage/framework/testing/ instead of the live submodule.',
        users: 'A test deleted or rewrote a TRACKED account in the live users/ submodule — the seed accounts the\n'
            + 'Control Panel login and slot provisioning depend on (UAMS-Web/uams-statamic-archive#2454). This worktree is reused, and\n'
            + '`submodule update --init` on an unchanged gitlink restores nothing, so the damage persists\n'
            + 'into every later run while `git ls-files` keeps listing the file (UAMS-Web/uams-statamic-archive#2477).\n'
            + '  Delete only what the test created, by the email it chose, and never `User::all()` in bulk;\n'
            + '  or re-root the users store the way `Tests\\Support\\BrowserWriteIsolation` does.',
        'resources/forms': 'A test saved a form into the live forms submodule (UAMS-Web/uams-statamic-archive#2818). Point `statamic.forms.forms` at a\n'
            + 'throwaway directory for the test, the way the tests under tests/Feature/Forms/ do.',
    }
    let litterCode = 0
    for (const name of GATE_SUBMODULES) {
        const before = litterBefore[name]
        if (before === null) {
            console.log(`${name}/ litter: no submodule to check on this run.`)
            continue
        }
        const { introduced, known, preexisting } = contentLitter(before, submoduleStatus(name))

        if (known.length > 0) {
            warn(`${name}/ carries ${known.length} known write${known.length > 1 ? 's' : ''} this gate does not yet fail on (see KNOWN_CONTENT_LITTER):`)
            for (const entry of known) {
                console.error(`  ${entry.status} ${entry.path}`)
            }
        }

        if (preexisting.length > 0) {
            warn(`${name}/ arrived dirty (${preexisting.length} path${preexisting.length > 1 ? 's' : ''}); not attributed to this run:`)
            for (const entry of preexisting) {
                console.error(`  ${entry.status} ${entry.path}`)
            }
        }

        if (introduced.length > 0) {
            litterCode = 1
            console.error(red(`The suite wrote into the ${name}/ submodule (${introduced.length} path${introduced.length > 1 ? 's' : ''}):`))
            for (const entry of introduced) {
                console.error(red(`  ${entry.status} ${entry.path}${entry.origin ? ` (from ${entry.origin})` : ''}`))
                console.error(`       ${driftLabel(entry.status)}`)
            }
            console.error(
                `${litterRemedy[name]}\n`
                + `  Restore the submodule with: git -C ${join(tree, name)} checkout -- .`,
            )
        } else {
            // Say so out loud: a check whose only output is silence cannot be
            // told apart from one that never ran.
            console.log(`${name}/ litter: none — the suite left the submodule as it found it.`)
        }
    }

    return cacheCode !== 0 ? cacheCode : (seedCode !== 0 ? seedCode : (parallelCode !== 0 ? parallelCode : (serialCode !== 0 ? serialCode : (baselineCode !== 0 ? baselineCode : (slowestCode !== 0 ? slowestCode : (bandLockCode !== 0 ? bandLockCode : litterCode))))))
    // PARITY-END
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    runCli({ 'no-reset': { type: 'boolean' } }, ({ tree, 'no-reset': noReset }) => gatePest({
        tree,
        php: resolvePhp(),
        git: resolveGit(),
        reset: noReset !== true,
    }))
}
