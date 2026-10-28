#!/usr/bin/env node
//
// local-ci.mjs — run the GitHub Actions CI (.github/workflows/ci.yml) locally
// against any branch, inside an isolated git worktree, without disturbing your
// current checkout. Cross-platform: one script for macOS, Linux, and Windows.
//
// Reproduces the jobs from .github/workflows/ci.yml using your local
// PHP 8.4 (Herd), Composer, Node 22 and Python 3 toolchain — no Docker, no act, no
// GitHub Actions minutes:
//
//     pest   pest --parallel --exclude-group=serial  +  pest --group=serial
//     pint      pint --test  on .php files changed vs the base ref
//     rector    rector --dry-run (gating profile) over app/, in a private TMPDIR
//     mutate    the mutation-TOOLING smoke: one scoped pest --mutate run, asserting
//               a non-zero mutation count and a 100% score (needs a coverage driver)
//     phpstan  composer stan (phpstan analyse app/ and tests/ at max level, --memory-limit=2G)
//     build     npm run build  +  npm run cp:build
//     js        npm run test:js  (vitest)
//     spell     npm run spell  (cspell)
//     no-emoji  the durable-record glyph guard (.claude/, docs/, README.md)
//     content-pointer  refuses a submodule pointer that is not on its repository's main
//     encoding  the vendored cp1252 double-encoding guard, then the decision-log order,
//               mutate-ignore and lone-CR guards and the release-notes generator suite
//     isolation php scripts/check-parallel-safe-tests.php (parallel-safety guard)
//               + .claude/skills/pest-testing/scripts/check-global-test-symbols.php tests
//     validate  composer validate (composer.json <-> composer.lock freshness)
//
// The jobs run as a cost-ordered CHAIN, shortest
// -> longest: isolation -> validate -> no-emoji -> content-pointer -> encoding -> spell -> js -> build -> pint ->
// rector -> mutate -> phpstan -> pest -> browser. Each runs only if the previous passed, so a failure
// skips every more-expensive job after it, saving your wall-clock. This order is
// LOCAL ONLY: ci.yml declares no `needs:` and fans its jobs out in parallel (#373),
// because one machine runs them serially whatever the graph says and stopping early
// saves your own time rather than a metered budget.
// Run one job (e.g. `--jobs pest`) to bypass the chain.
//
// The target branch is checked out into a sibling worktree (default
// ../uams-statamic-ci — the CI slot in the worktrees rule),
// so the branch you are actively working on never moves. The private content/
// and users/ submodules (needed only by pest) are initialized with the Composer
// GitHub PAT already configured in your global Composer auth, exactly as the CI
// workflow does.
//
// USAGE
//     node scripts/local-ci.mjs --branch <branch> [options]
//     ./scripts/local-ci.mjs <branch> [options]        # macOS/Linux (executable)
//
// OPTIONS
//     -b, --branch <branch>     Branch to run CI against. Required.
//     -j, --jobs <list>         Comma-separated subset of
//                               isolation,validate,no-emoji,content-pointer,encoding,spell,js,build,pint,rector,mutate,phpstan,pest,browser,
//                               or `all` (default; `all` is every job, browser included).
//     -w, --worktree-path <p>   Where to place the worktree (default
//                               ../uams-statamic-ci).
//         --base-ref <ref>      Base ref the Pint diff is taken against (default
//                               main; resolved to origin/<ref> when it exists).
//         --fresh               Remove and recreate the worktree from scratch.
//         --skip-install        Reuse the worktree's vendor/ and node_modules/.
//         --skip-submodule      Do not init/checkout the content/, users/ and resources/forms/ submodules (for
//                               the parallel phase of a concurrent multi-branch run;
//                               see the skill doc).
//         --fetch               Best-effort `git fetch origin` of the branch + base
//                               ref before running.
//     -h, --help                Show this help.
//
// TOOLCHAIN
//     PHP, Composer, Node, npm, npx and Python 3 are discovered automatically. On macOS the
//     version-pinned Herd binary (php84) is preferred so the run targets 8.4 even
//     when your default Herd PHP is another version; otherwise tools are resolved on
//     PATH. A missing tool fails fast with an actionable message. Override any tool
//     with an env var holding an absolute path: LOCAL_CI_PHP, LOCAL_CI_COMPOSER,
//     LOCAL_CI_NODE, LOCAL_CI_NPM, LOCAL_CI_NPX, LOCAL_CI_PYTHON (the Python 3 the
//     encoding job runs the release-notes generator suite with). The PHP memory_limit applied to
//     every PHP process the runner starts (and forwarded to the pest parallel workers)
//     defaults to 4G (CI runs unlimited); override with LOCAL_CI_PHP_MEMORY_LIMIT (e.g. `8G` or `-1`).
//
// PARITY WITH REMOTE CI — this script is a hand-maintained mirror of
// .github/workflows/ci.yml. The two must change together; when you edit a job's
// command in one, update the other. Each chained remote job's run step carries a
// `# PARITY:` marker pointing back here, and the `// PARITY:` markers below point
// the other way. A pre-commit hook (.githooks/pre-commit) blocks a commit that
// stages only one of the two files; bypass with `git commit --no-verify`.
//
//     ci.yml job        this script
//     --------------    -------------------------------------------------
//     pest           pest --parallel --exclude-group=serial then a serial
//                       pest --group=serial tail (paratest; each worker gets an
//                       isolated per-TEST_TOKEN cache warmed by the per-process
//                       CreatesApplication stache warm), plus the
//                       .env/key:generate/post-autoload-dump prep and the
//                       content/, users/ and resources/forms/ submodules
//     pint              changed-files diff (diff-filter=ACMR, *.php) against the
//                       remote-tracking base origin/<base> — matching ci.yml's
//                       pull_request path — then pint --test, chunked so a large
//                       diff can't overflow the Windows command line
//     rector            rector --dry-run under the default (gating) profile, over
//                       the app/ paths rector.php pins, with TMPDIR/TMP/TEMP
//                       pointed at a private dir so a warm cache from another
//                       profile cannot make the run under-report (UAMS-Web/uams-statamic-archive#1720)
//     mutate            the mutation-TOOLING smoke (UAMS-Web/uams-statamic-archive#1731): one narrowly-scoped
//                       pest --mutate run over AlertSiteKeyResolver, asserting a
//                       NON-ZERO mutation count and a 100% score. Gates the tool,
//                       not the mutants — UAMS-Web/uams-statamic-archive#1728 was `0 Mutations for 0 Files
//                       created`, which Pest exits 0 on. REQUIRES a coverage
//                       driver (PCOV or Xdebug); see .claude/skills/pcov-setup
//     phpstan          composer stan (phpstan analyse app/ and tests/ at max level,
//                       --memory-limit=2G), plus the .env/key:generate/
//                       post-autoload-dump prep (larastan boots the app); no
//                       content/ submodule or Node
//     build             npm run build + npm run cp:build (and npm ci)
//     js                npm run test:js (vitest; framework-free, needs npm ci)
//     spell             npm run spell (cspell; needs npm ci)
//     browser           playwright install chromium; npm run build + cp:build
//                       (SKIPPED when the `build` job already produced them in
//                       this same worktree this run — UAMS-Web/uams-statamic-archive#1727); pest tests/Browser,
//                       plus the same framework prep as pest. Includes the CP
//                       smokes AND the §504/WCAG axe-core accessibility gate
//                       (UAMS-Web/uams-statamic-archive#1046), which fails on serious/critical violations.
//                       Part of the default `all` set since UAMS-Web/uams-statamic-archive#1727: four CP smokes
//                       sat red on main for eleven days because running this suite
//                       depended on somebody REMEMBERING to ask for it.
//     encoding-guard    the vendored check-double-encoding.mjs invocation
//     composer-validate composer validate --no-check-publish --no-check-all (the
//                       lock-hash freshness gate; needs neither composer install
//                       nor the framework, so it runs on the two files alone)
//     toolchain pins    PHP 8.4, Node 22
//
// This script intentionally does NOT replicate CI-only infra that does not affect
// pass/fail locally: OPcache/JIT ini tuning, actions/cache layers, and the runner's
// clean-checkout guarantees. See .claude/skills/local-ci/SKILL.md for the full map.
//
// The remote `RUN_REMOTE_CI` budget kill-switch (UAMS-Web/uams-statamic-archive#980) does NOT apply here: this
// runner has no concept of the toggle and always runs the requested jobs. That is
// the point — when remote CI is switched off (Actions budget exhausted), this is
// the validation path. So flipping RUN_REMOTE_CI changes only ci.yml's job `if:`
// gates, with no behavioral mirror needed in this script.
//
// A green run here is mergeable because no ruleset on `main` requires a status
// check, NOT because `main` is unprotected: two organization rulesets refuse
// deletion, force-pushes and direct pushes, and require a pull request, which a
// merge is. See docs/runbooks/branch-protection.md and UAMS-Web/uams-statamic-archive#2016. Adding a required
// check while remote CI is off would leave it pending forever and deadlock merges,
// which no amount of local green can clear.
//
// RUNNER HANDOFF (UAMS-Web/uams-statamic-archive#1882) — this script is invoked from the checkout it lives in,
// not from the branch it validates, so a PR that ADDS, REMOVES or CHANGES a chain
// job used to be validated by the BASE branch's job list. Observed on PR UAMS-Web/uams-statamic-archive#1878:
// the `mutate` job it added never ran, and the chain reported green without it — a
// job that never executed cannot fail. After the branch is checked out into the
// worktree, this script compares the worktree's `scripts/local-ci.mjs`, and the
// helper modules it imports (RUNNER_HELPER_MODULES, UAMS-Web/uams-statamic-archive#2881), against its own and,
// when any differs, RE-EXECS the branch's copy with the same arguments,
// forwarding its exit code. The handoff happens BEFORE toolchain discovery and the
// submodule/install/prep steps, so the branch's copy governs the whole run and not
// just the job loop. Recursion is structurally impossible: the child is marked
// pinned via LOCAL_CI_RUNNER_PINNED — an ENVIRONMENT variable rather than a flag,
// because a runner predating this change would reject an unknown option and take
// every older branch down with it. Unlike remote CI, which checks out the PR's own
// `ci.yml` and never had this problem.
//
// Job-name validation is DEFERRED until after that decision, because the executing
// copy cannot know the branch's job list until the branch is on disk. Validating
// eagerly would reject `--jobs <branch-only-job>` with "Unknown job" — refusing to
// address the very job the branch added. So an unrecognized name is carried across
// the handoff and validated by whichever runner ends up defining the chain. The
// residual cost is that a typo is reported after the checkout rather than before it.
//
// REUSED-WORKTREE DRIFT (UAMS-Web/uams-statamic-archive#1889) — the checkout that follows updates TRACKED files
// and removes nothing that has become untracked, so a file the validated commit
// DELETES can survive on disk and be executed by the run establishing its absence.
// Observed validating UAMS-Web/uams-statamic-archive#1848 and UAMS-Web/uams-statamic-archive#1855: two audit tests deleted by UAMS-Web/uams-statamic-archive#1847 ran and
// passed in the reusing run, a two-test discrepancy that reconciled exactly with
// the `--fresh` run. The worktree is therefore verified against the commit before
// any job is set up, and a mismatch REFUSES the run rather than being cleaned
// away — a silent clean fixes one run and hides an accumulating leak.

import { spawnSync } from 'node:child_process'
import { existsSync, copyFileSync, readFileSync, readdirSync, realpathSync, writeFileSync, rmSync, mkdirSync, mkdtempSync } from 'node:fs'
import { join, dirname, basename, isAbsolute, relative } from 'node:path'
import { homedir, tmpdir } from 'node:os'
import { fileURLToPath, pathToFileURL } from 'node:url'
import process from 'node:process'
import { GATE_SUBMODULES, patchedPackages, patchStaleReason, readPatchLockHash } from './gate-slot.mjs'
import { bandFromJunit, bandLine } from './local-ci-bands.mjs'
import { primaryCheckout } from './repo-root.mjs'
import { isExecutable, needsShell, which, winQuote } from './spawn-helpers.mjs'

const isWindows = process.platform === 'win32'
const isMacOS = process.platform === 'darwin'

export const ALL_JOBS = ['pest', 'pint', 'rector', 'mutate', 'build', 'js', 'spell', 'phpstan', 'encoding', 'no-emoji', 'content-pointer', 'isolation', 'validate', 'browser']
const VALID_JOBS = [...ALL_JOBS, 'all']


// PARITY: the cost-ordered chain, shortest -> longest. A job runs only if every
// earlier selected job passed; the moment one fails, the rest are SKIPPED (not
// run), so a failure never pays for the more-expensive jobs after it. `browser`
// runs last, as the most expensive link. Run a single job (e.g. `--jobs pest`) to
// bypass the chain. This ORDER is local only: ci.yml declares no `needs:` and runs
// its jobs in parallel (#373). Module-scoped and exported so the parity test can
// compare this SET against the jobs ci.yml declares (UAMS-Web/uams-statamic-archive#1735).
export const CHAIN = ['isolation', 'validate', 'no-emoji', 'content-pointer', 'encoding', 'spell', 'js', 'build', 'pint', 'rector', 'mutate', 'phpstan', 'pest', 'browser']
// PARITY-END: the chain itself only. What follows is unrelated (UAMS-Web/uams-statamic-archive#2376).

// UAMS-Web/uams-statamic-archive#2880: the jobs that run a script through `node`, so the runner resolves Node when the
// job list includes any of them. `node` used to be resolved only for `encoding`, so
// `--jobs no-emoji` on its own spawned `null` and crashed with `The "file" argument must
// be of type string. Received null`. `local-ci.node-jobs.test.mjs` fails when a job
// body calls `node` without being listed here.
export const NODE_JOBS = ['encoding', 'no-emoji', 'content-pointer']

/**
 * Whether the `browser` job may reuse the `build` job's output instead of running
 * `npm run build` + `npm run cp:build` a second time (UAMS-Web/uams-statamic-archive#1727).
 *
 * ci.yml gets a FRESH RUNNER per job, so its browser job must always build. This
 * runner is the opposite — every job shares one worktree — so once `build` has
 * produced the two bundles with byte-identical commands, repeating them buys
 * nothing.
 *
 * Deliberately keyed on the RESULT of this run, not on the bundles existing on
 * disk. A reused worktree (`--skip-install`, no `--fresh`) can carry bundles from
 * an earlier run at a different commit, and trusting those would let the browser
 * job smoke stale assets — the exact false-green class this gate exists to close.
 * Only a `build` that ran and exited 0 in THIS invocation licenses the skip.
 *
 * @param {Map<string, number|string>} results  Job id -> exit code, or 'skipped'.
 * @returns {boolean}
 */
/**
 * The one-line description a commit status carries (UAMS-Web/uams-statamic-archive#2323).
 *
 * Pure and exported so the wording is testable without a network call — the
 * posting itself needs `gh`, a remote and a resolvable head, none of which a
 * unit test should require to pin what the status SAYS.
 *
 * GitHub truncates a description past 140 characters; the caller slices, and
 * the failing-job list is placed first so a truncation loses the tail rather
 * than the job names.
 *
 * @param  {boolean}  failed
 * @param  {Iterable<[string, number|'skipped']>}  results  The runner's Map, or any iterable of entries.
 * @param  {?string}  phpVersion  The interpreter the jobs ran under, appended last.
 * @return {string}
 */
export function commitStatusDescription(failed, results, phpVersion = null) {
    // The runner holds its results in a Map, which has no `filter`, so spread whatever
    // iterable of [job, code] entries arrives. Called with the Map, the array-only form
    // threw from the summary and turned every `--commit-status` run into exit 1.
    const entries = [...results]
    const ran = entries.filter(([, code]) => code !== 'skipped')
    const gated = entries.length - ran.length
    // UAMS-Web/uams-statamic-archive#3235: the status outlives the log, so it names the interpreter the jobs ran under.
    const under = phpVersion ? ` under PHP ${phpVersion}` : ''

    if (!failed) {
        return `${ran.length} of ${entries.length} jobs passed${under}`
    }

    const failing = ran.filter(([, code]) => code !== 0).map(([job]) => job).join(', ')

    return `${failing} failed` + (gated ? `; ${gated} chain-gated` : '') + under
}

// UAMS-Web/uams-statamic-archive#3235: the summary's band lines, read from the JUnit reports the pest job writes.
// Only when pest ran in this chain: the job deletes both reports before it starts, so a
// report present after a run that did not reach pest is the previous run's and is not
// read. A band whose report is missing (it died before writing) is named as such rather
// than left out, so its absence is visible in the block a verdict quotes.
export function pestBandLines(worktreePath, results, phpVersion) {
    const code = results instanceof Map ? results.get('pest') : undefined

    if (code === undefined || code === 'skipped') {
        return []
    }

    return ['parallel', 'serial'].map((band) => {
        const report = join(worktreePath, `junit-${band}.xml`)

        return existsSync(report)
            ? bandLine(band, bandFromJunit(readFileSync(report, 'utf8')), phpVersion)
            : `${band}: no report written under PHP ${phpVersion}`
    })
}

export function canReuseBuildOutput(results) {
    return results.get('build') === 0
}

// CI's setup-php leaves PHP's memory_limit at its -1 (unlimited) default, so the
// remote suite has no ceiling. A local CLI does: Herd's PHP caps it (2G here), which
// the full framework-booting suite blows past mid-run as the content set grows (a
// fatal OOM, not a test failure). Raise the limit for every PHP process the runner
// starts, and forward it to the paratest workers, to keep parity with CI. Override with
// LOCAL_CI_PHP_MEMORY_LIMIT (any PHP ini shorthand, e.g. `8G` or `-1` for unlimited).
const PHP_MEMORY_LIMIT = process.env.LOCAL_CI_PHP_MEMORY_LIMIT || '4G'

// Prefix a PHP argument list with the runner's memory limit. Every PHP process the
// runner starts goes through this (UAMS-Web/uams-statamic-archive#3212), not only the test runners: a PHP whose
// own memory_limit is low (128M with no php.ini) fatals the mutate-ignore auditor
// on `token_get_all()` with exit 255, which fails the encoding job for a reason
// that has nothing to do with the branch. CI runs every step unlimited, so a limit
// on each process is the parity, and no job is better served by the CLI default.
export function withPhpMemoryLimit(args, limit = PHP_MEMORY_LIMIT) {
    return ['-d', `memory_limit=${limit}`, ...args]
}

// --- mutation-tooling smoke (UAMS-Web/uams-statamic-archive#1731) ----------------------------------------
//
// WHY THIS CLASS: the smoke needs a subject that is small, pure, fully covered,
// and — most importantly — has a MUTABLE SURFACE that will not drain away. If a
// later refactor leaves the subject with nothing to mutate, the job reports
// `0 Mutations` and the assertions below turn it red rather than letting it pass
// as a silent no-op. `AlertSiteKeyResolver` qualifies: no I/O, no framework boot,
// 8 mutants across ternaries, a foreach, boolean operators and an early return,
// and a dedicated unit test at 100%. It is also the class UAMS-Web/uams-statamic-archive#1730 used to verify
// the fix for UAMS-Web/uams-statamic-archive#1728, so these numbers are directly comparable to that ticket's.
//
// If you must change the subject, pick one with the same properties and re-record
// the expected numbers here and in tests/README.md. Do NOT relax the assertions.
const MUTATE_SMOKE_TEST = 'tests/Unit/Support/UamsAlert/AlertSiteKeyResolverTest.php'
const MUTATE_SMOKE_PATH = 'app/Support/UamsAlert'
const MUTATE_SMOKE_CLASS = 'App\\Support\\UamsAlert\\AlertSiteKeyResolver'

// The coverage-driver probe the smoke runs before Pest (UAMS-Web/uams-statamic-archive#3213). It mirrors
// `activeCoverageDriver()` in scripts/check-tia-safe.php and the inline probe in
// ci.yml's mutate step: PCOV counts only when enabled, Xdebug only when its mode
// includes coverage. It prints `pcov`, `xdebug` or `none`.
export const COVERAGE_DRIVER_PROBE = 'echo extension_loaded("pcov") && filter_var(ini_get("pcov.enabled"), FILTER_VALIDATE_BOOLEAN) ? "pcov" : (extension_loaded("xdebug") && str_contains((string) ini_get("xdebug.mode"), "coverage") ? "xdebug" : "none");'

// What Pest prints when it refuses `--mutate` for want of a driver. Read as a
// fallback for a PHP the probe passed and Pest still refused.
const PEST_NO_COVERAGE = /Mutation testing requires code coverage to be enabled/i

export const MUTATE_NO_DRIVER_MESSAGE = 'mutate: no coverage driver is active in this PHP (PCOV, or Xdebug with xdebug.mode including coverage), '
    + 'so the mutation run cannot start. This is a workstation condition, not a branch defect, and it is not a pass. '
    + 'See .claude/skills/pcov-setup/SKILL.md.'

// The smoke's verdict, kept pure so both failure branches are testable without a
// PHP (UAMS-Web/uams-statamic-archive#3213). `driver` is the probe's output; `plain` is Pest's ANSI-stripped
// output, or null when the probe refused before Pest ran. An absent driver and a
// zero mutation count get different messages: the first is the machine, the
// second (UAMS-Web/uams-statamic-archive#1728) is the repository, and they call for opposite responses.
export function mutateSmokeVerdict(driver, plain) {
    if (driver.trim() === 'none' || (plain !== null && PEST_NO_COVERAGE.test(plain))) {
        return { ok: false, message: MUTATE_NO_DRIVER_MESSAGE }
    }

    if (plain === null) {
        return { ok: false, message: 'mutate: the mutation run did not report.' }
    }

    // Read the mutation count out of the plugin's own summary line.
    const created = plain.match(/(\d+)\s+Mutations?\s+for\s+(\d+)\s+Files?\s+created/i)
    if (!created) {
        return { ok: false, message: 'mutate: no "N Mutations for M Files created" line, so the mutation run did not report.' }
    }

    // A zero count is the UAMS-Web/uams-statamic-archive#1728 signature: the tooling ran and found nothing
    // to mutate. That is a setup failure, never a pass.
    if (Number(created[1]) === 0 || Number(created[2]) === 0) {
        return {
            ok: false,
            message: `mutate: "${created[0]}": the tooling found nothing to mutate. `
                + 'This is the UAMS-Web/uams-statamic-archive#1728 false green, not a pass. See .claude/skills/pcov-setup/SKILL.md.',
        }
    }

    // The smoke's subject is fully covered, so anything below 100% is a
    // regression in the tooling or in that class's tests.
    const score = plain.match(/Score:\s*([\d.]+)%/i)
    if (!score || Number(score[1]) < 100) {
        return { ok: false, message: `mutate: score ${score ? score[1] + '%' : 'not reported'}, expected 100.00%.` }
    }

    return { ok: true }
}

// --- console helpers -------------------------------------------------------

const useColor = process.stdout.isTTY && !process.env.NO_COLOR
const paint = (code, text) => (useColor ? `\x1b[${code}m${text}\x1b[0m` : text)
const cyan = (t) => paint('36', t)
const green = (t) => paint('32', t)
const red = (t) => paint('31', t)
const yellow = (t) => paint('33', t)

function step(message) {
    console.log('')
    console.log(cyan(`==> ${message}`))
}

function warn(message) {
    console.error(yellow(`WARNING: ${message}`))
}

// --- process execution -----------------------------------------------------

// `winQuote`, `needsShell` and `which` live in ./spawn-helpers.mjs — shared with
// worktree-slots.mjs, because the CVE-2024-27980 shim rule they encode is one a
// second copy would drift on silently (UAMS-Web/uams-statamic-archive#1948).

// Run a command, streaming its output to the console. Returns the exit code.
function run(exe, args, cwd, extraEnv) {
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
function capture(exe, args, cwd, { ignoreStderr = false } = {}) {
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
function captureResult(exe, args, cwd, { ignoreStderr = false } = {}) {
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


/** Where a worktree records the patch-set hash it last applied, under vendor/. */
export const PATCH_STAMP_FILE = '.local-ci-patches-hash'

// UAMS-Web/uams-statamic-archive#3050 -- the stamp records which patch set was last applied, and it is read as
// "that patch set is applied now". The two come apart when a later install
// re-extracts a patched package (a version change in the lock) and then dies before
// patching it: the stamp survives, still matches, and the next run in the worktree
// skips the re-patch over a vendor/ that lost those patches. Observed on 2026-10-13,
// where it cost nine guard-test failures read as a defect in the branch under test.
//
// So the stamp fails closed. It is read, then removed BEFORE any install runs, and
// written back only after every install here has exited 0. A failed install (which
// `install` signals by throwing) therefore leaves no stamp, and the next run sees
// "no record" and re-patches. A successful warm install whose stamp matched writes
// the same stamp back and skips the re-patch, so the ordinary path costs nothing.
//
// `install(label)` runs one `composer install` and throws on a non-zero exit.
// `announce(message)` reports the re-patch and its reason.
//
// Returns { lockHash, staleReason }: staleReason is null when no re-patch ran.
export function installWithPatchStamp({ vendorDir, patchLockRaw, install, announce = () => {} }) {
    const stampPath = join(vendorDir, PATCH_STAMP_FILE)
    const lockHash = readPatchLockHash(patchLockRaw)
    const stampHash = existsSync(stampPath) ? readFileSync(stampPath, 'utf8').trim() : null
    rmSync(stampPath, { force: true })

    install('composer install')

    // On a REUSED worktree the install above reports "Nothing to install", so a
    // patch the branch newly added is never applied (UAMS-Web/uams-statamic-archive#1743). Compare the branch's
    // patch-set hash against what this worktree last applied, and re-patch when
    // they differ or nothing records it.
    const staleReason = patchStaleReason(lockHash, stampHash)
    if (staleReason) {
        // NOT `composer patches-repatch`: it runs its inner install and then
        // `return 0` unconditionally (cweagans/composer-patches 2.0.0,
        // RepatchCommand.php:56-62), so a patch that fails to apply still exits
        // 0. Removing the patched packages and re-running `composer install` gets
        // the same effect through a path whose failure IS observable: a patch
        // that will not apply fails the install with a non-zero exit.
        announce(`Re-applying patches (${staleReason})`)
        for (const pkg of patchedPackages(patchLockRaw)) {
            rmSync(join(vendorDir, ...pkg.split('/')), { recursive: true, force: true })
        }
        install('composer install (re-applying patches)')
    }

    if (lockHash) {
        // Reached only when every install above exited 0, so the patches really
        // did apply: the stamp certifies a verified state, not an assumption.
        writeFileSync(stampPath, `${lockHash}\n`)
    }

    return { lockHash, staleReason }
}

// UAMS-Web/uams-statamic-archive#1746 — decide how to reconcile the local validation branch with origin.
//
// `git worktree add <path> <branch>` and `git checkout <branch>` both resolve the
// LOCAL ref. `--fetch` updates `origin/<branch>` but never fast-forwards the local
// one, and `--fresh` recreates the worktree from that same stale local ref — so
// neither addresses validating an outdated commit. UAMS-Web/uams-statamic-archive#1710 was a false red from
// exactly this: the gate validated a commit the PR had already moved past.
//
// Returns the action plus a human-readable reason:
//   local-only   — no origin counterpart; nothing to sync against.
//   current      — already the remote commit.
//   fast-forward — local is strictly behind; moving it is lossless.
//   ahead        — local has commits origin lacks. Proceeds (validating work
//                  that was never pushed is a legitimate local use) but says so
//                  loudly: what runs is NOT what a reviewer sees on the PR.
//   diverged     — both sides have unique commits (a rebase + force-push, the
//                  flow sync-pr-branch mandates). The caller decides: with
//                  --fetch and the branch checked out nowhere, reset to origin;
//                  otherwise refuse rather than pick a side silently.
export function branchSyncAction(localSha, remoteSha, mergeBaseSha) {
    if (!remoteSha) {
        return { action: 'local-only', reason: 'no origin counterpart; validating the local ref' }
    }
    if (localSha === remoteSha) {
        return { action: 'current', reason: 'already at the remote commit' }
    }
    if (mergeBaseSha === localSha) {
        return { action: 'fast-forward', reason: `local is behind origin; syncing to ${remoteSha.slice(0, 9)}` }
    }
    if (mergeBaseSha === remoteSha) {
        return { action: 'ahead', reason: 'local has commits origin does not; validating work that was never pushed' }
    }
    return { action: 'diverged', reason: 'local and origin have both moved; refusing to guess which to validate' }
}

// UAMS-Web/uams-statamic-archive#1882 — how the re-exec tells the branch's runner it is already pinned. Its only
// job is to make recursion impossible: a pinned run skips the comparison entirely,
// so the handoff can happen at most once no matter what the two copies contain.
//
// The ENV VAR is what the handoff actually sends, and the reason is version skew.
// A runner that predates UAMS-Web/uams-statamic-archive#1882 has no `--runner-pinned` case in its `parseArgs`, so
// the flag would hit its `default:` arm and throw `Unknown option: --runner-pinned`
// — meaning every branch cut before this change became un-validatable the moment it
// merged. That is the opposite of what this file promises twice elsewhere, where the
// skip-baseline and slowest-class guards are skipped with a notice on a branch that
// predates them. An environment variable is invisible to an older parser, so the old
// runner simply runs (it has no handoff logic to suppress), while a current one
// reads it and does not hand off again.
//
// The flag remains accepted on the command line as the manual escape hatch: passing
// it to the executing runner forces an in-place run when you do not want a handoff.
export const RUNNER_PINNED_FLAG = '--runner-pinned'
export const RUNNER_PINNED_ENV = 'LOCAL_CI_RUNNER_PINNED'

// UAMS-Web/uams-statamic-archive#2856: the executing runner's own source, read ONCE, at module load.
//
// The handoff below compares this against the branch's copy, and the comparison is
// only meaningful against the bytes that are actually running. Node loaded them
// before `main()` checked anything out, so reading the file again after the
// checkout answers a different question. When the runner is invoked from inside
// the worktree it validates, the post-checkout read and the branch's copy are the
// same file, byte-identical by construction, and the handoff could never fire: a
// runner other than the branch's then defined the chain for a branch that changes
// it, which is the UAMS-Web/uams-statamic-archive#1878 false green UAMS-Web/uams-statamic-archive#1882 exists to close. Measured validating
// UAMS-Web/uams-statamic-archive#2842, whose 60-line runner change printed "identical" and ran the base copy's
// step labels.
//
// A few milliseconds separate Node reading this file from this line reading it
// again. A checkout in that window would make the two differ, but the worktree lock
// is what serializes checkouts and it is taken later, in `main()`, so only a
// hand-driven checkout could land there. Node offers no way to read back an ES
// module's loaded source, so the window is accepted rather than closed.
export const EXECUTING_RUNNER_PATH = fileURLToPath(import.meta.url)
export const EXECUTING_RUNNER_SOURCE = readFileSync(EXECUTING_RUNNER_PATH, 'utf8')

// UAMS-Web/uams-statamic-archive#2881: the local modules this runner imports, directly or through each other, named
// relative to its own folder. They are part of what defines a run as much as this file
// is: since UAMS-Web/uams-statamic-archive#1950 and UAMS-Web/uams-statamic-archive#1953 moved tool resolution and primary-checkout detection into
// them, a branch can change how a run behaves without touching this file. Comparing
// only this file then ran the branch with the helpers this process loaded at start.
// `scripts/local-ci.handoff-helpers.test.mjs` fails when this file, or a helper, gains
// a static local import or re-export the list does not name (`import ... from './x'`,
// `import './x'`, `export ... from './x'`), so a new helper cannot reopen the gap that
// way. A dynamic `import('./x')` is not scanned; none of these files uses one.
export const RUNNER_HELPER_MODULES = Object.freeze(['gate-slot.mjs', 'local-ci-bands.mjs', 'repo-root.mjs', 'spawn-helpers.mjs'])

// Captured at module load beside the runner's own source, for the same reason (UAMS-Web/uams-statamic-archive#2856):
// a checkout can replace these files when the runner sits inside the worktree it
// validates, and the comparison has to be against the bytes Node actually loaded.
export const EXECUTING_HELPER_SOURCES = Object.freeze(Object.fromEntries(
    RUNNER_HELPER_MODULES.map((name) => [name, readFileSync(join(dirname(EXECUTING_RUNNER_PATH), name), 'utf8')]),
))

// UAMS-Web/uams-statamic-archive#2856: whether `file` sits inside the directory `tree`, compared on resolved
// paths and anchored on the separator, so a sibling such as `<repo>-ci-2` is not
// read as inside `<repo>-ci`. A path that cannot be resolved (it does not exist)
// is compared as given. On macOS the comparison ignores case, because the default
// file system does and `realpathSync` keeps the casing it was given; Windows'
// `path.relative` already does. Only the provenance note reads this, never the
// handoff decision.
export function isInsideTree(file, tree) {
    const resolve = (path) => {
        try {
            return realpathSync(path)
        } catch {
            return path
        }
    }
    const fold = (path) => (process.platform === 'darwin' ? path.toLowerCase() : path)
    const fromTree = relative(fold(resolve(tree)), fold(resolve(file)))

    return fromTree !== '' && !fromTree.startsWith('..') && !isAbsolute(fromTree)
}

// UAMS-Web/uams-statamic-archive#2856: the `Runner:` line a summary prints, naming which copy defined the chain.
//
// When the executing runner sits inside the worktree it validates, its file on
// disk was replaced by the branch's copy at checkout, so "identical" could once
// have meant "one file compared with itself". It no longer can, because the
// comparison uses the source captured at module load, and the line says which
// configuration produced it so a reader does not have to take that on trust. A run
// that made no comparison (pinned by its parent, or a branch with no runner) gets
// no such note, because there was nothing compared to describe.
export function runnerProvenanceLine({ executingRunner, worktreePath, reason, compared = true }) {
    if (!compared || !isInsideTree(executingRunner, worktreePath)) {
        return `${executingRunner}: ${reason}`
    }

    return `${executingRunner}: ${reason} (this runner runs from inside the worktree it validates, `
        + 'so it was compared as loaded before the checkout, not as the file now on disk)'
}

// UAMS-Web/uams-statamic-archive#1882 — decide whether to hand the run over to the branch's own copy of this
// script.
//
// The comparison is on SOURCE TEXT rather than mtime or git status: the question
// is "does the branch define the jobs differently", and only the bytes answer it.
// Line endings are normalized first, because a checkout under a different
// `core.autocrlf` would otherwise differ from the executing copy while defining
// exactly the same chain — a re-exec that changes nothing but costs a second
// checkout.
//
// Returns the action plus a human-readable reason, which the summary prints so a
// green can never be quoted without naming which runner produced it:
//   run    — this checkout's runner defines the jobs (identical, absent, or pinned).
//   handoff — the branch's runner or one of its helper modules differs, so the
//            branch's copy must define the jobs instead.
// `compared` says whether the two sources were actually compared (UAMS-Web/uams-statamic-archive#2856), which the
// provenance line needs to know.
//
// `executingHelpers` and `branchHelpers` map each name in RUNNER_HELPER_MODULES to its
// source, the branch's side null where the branch has no such file (UAMS-Web/uams-statamic-archive#2881). A helper
// the branch lacks counts as differing: the branch's runner cannot import it, so
// handing off is what surfaces that, where running in place would hide it.
export function runnerHandoff({ pinned, executingSource, branchSource, executingHelpers = {}, branchHelpers = {} }) {
    const normalize = (source) => source.replace(/\r\n/g, '\n')

    if (pinned) {
        return { action: 'run', compared: false, reason: 'pinned by the parent run to the branch\'s own runner' }
    }
    if (branchSource === null) {
        return { action: 'run', compared: false, reason: 'the branch carries no scripts/local-ci.mjs; using this checkout\'s runner' }
    }
    if (normalize(branchSource) !== normalize(executingSource)) {
        return { action: 'handoff', compared: true, reason: 'the branch\'s runner differs from this one' }
    }

    const differing = Object.keys(executingHelpers).filter((name) => {
        const branch = branchHelpers[name]

        return typeof branch !== 'string' || normalize(branch) !== normalize(executingHelpers[name])
    })
    if (differing.length > 0) {
        const named = differing.map((name) => `scripts/${name}`).join(' and ')

        return {
            action: 'handoff',
            compared: true,
            reason: `the branch's runner is identical to this one, but its ${named} ${differing.length === 1 ? 'differs' : 'differ'}`,
        }
    }
    return {
        action: 'run',
        compared: true,
        reason: Object.keys(executingHelpers).length > 0
            ? 'the branch\'s runner and its helper modules are identical to this one\'s'
            : 'the branch\'s runner is identical to this one',
    }
}

// UAMS-Web/uams-statamic-archive#1882 — build the argv for the re-exec of the branch's runner.
//
// Three edits to the caller's arguments, each load-bearing:
//   - `--fresh` is DROPPED. This process already removed and rebuilt the worktree;
//     letting the child do it again would throw away that rebuild and pay a second
//     full `composer install` for an identical result.
//   - `--fetch` is DROPPED. This process already fetched and reconciled the local
//     ref, and the commit it announced is the one that must be validated. A second
//     fetch could quietly move the branch to a newer origin commit, so the run
//     would report a result for a commit the parent never named.
//   - `--worktree-path` is PINNED to the resolved path, so the child cannot
//     recompute a different default from its own cwd.
// Any existing `-w`/`--worktree-path` pair is stripped first so the forwarded argv
// carries exactly one, rather than relying on last-wins parsing.
//
// Note what is NOT added: the pin. It travels in RUNNER_PINNED_ENV instead, because
// a runner predating UAMS-Web/uams-statamic-archive#1882 would reject the flag outright — see that constant.
export function handoffArgv(argv, worktreePath) {
    const forwarded = []
    for (let i = 0; i < argv.length; i++) {
        const arg = argv[i]
        if (arg === '--fresh' || arg === '--fetch' || arg === RUNNER_PINNED_FLAG) {
            continue
        }
        if (arg === '-w' || arg === '--worktree-path') {
            i++
            continue
        }
        forwarded.push(arg)
    }
    return [...forwarded, '--worktree-path', worktreePath]
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

// UAMS-Web/uams-statamic-archive#1873 — split what the suite just wrote into the content/ submodule from what
// was already sitting there.
//
// Tests persist fixtures through the Stache, and a test whose process does not
// survive to its cleanup — a band killed at its bound, or one that fatals —
// strands the file. The submodule then reads dirty for every later run on that
// checkout, and the stray is boot-visible input: `statamic:stache:warm` indexes
// it and memoizes a collection-structure entry against the real collection,
// which is the state that produced UAMS-Web/uams-statamic-archive#1845.
//
// A before/after diff rather than a bare "is it dirty now", because the runner
// REUSES its worktree. A bare check blames whichever run happens to notice a
// file some earlier run stranded — the "caught three runs later" confusion this
// exists to end. Diffing makes the accusation specific: this run's litter fails
// the job, and a worktree that arrived dirty is reported separately so a stale
// file cannot red an innocent PR forever.
//
// Takes the parsed entries from `worktreeDriftEntries()` rather than raw
// porcelain, so the `-z` parsing lives in exactly one place.
// Paths the suite is KNOWN to still write, carried as named debt rather than
// silence — currently NONE, and the empty list is the point.
//
// It held `models/assets/` while `app/Models/Asset.php` still rewrote
// `content/models/assets/{id}.yml` from a test: the write goes through
// `base_path()` on model save, outside the Stache entirely, so no store re-root
// reached it. UAMS-Web/uams-statamic-archive#1919 closed that at the seam instead — `Asset::$skipYamlSync` is
// armed for every test by `Tests\TestCase::setUp()`, so a test that never heard
// of the flag now writes nothing — and the entry came out with it.
//
// Deliberately a prefix list and deliberately tiny. The reason to keep the empty
// list rather than delete the mechanism is that debt has to be ADDED on purpose:
// `local-ci.content.test.mjs` pins this to `[]`, so a future hold-aside cannot be
// slipped in without a reviewer seeing the test change too.
// --- single-flight on the CI worktree (UAMS-Web/uams-statamic-archive#2256) -------------------------------

// The runner owns a FIXED worktree (`../<repo>-ci`), so two invocations that do
// not pass `--worktree-path` share one tree and corrupt each other. The failure
// is not the wasted CPU: two runs in one tree produced failures that MOVED
// between them on an identical commit, which is the standard sufficient test for
// a flake. The absence of this guard manufactures evidence for a false
// diagnosis, and that near-miss is what filed the ticket.
//
// UAMS-Web/uams-statamic-archive#1987 made `test:seed-stache` single-flight for the same class of reason and
// is the shape followed here: refuse up front, name the holder, exit distinctly.
// What does NOT transfer is its primitive. That command uses `flock()`, whose
// lock the kernel drops when the holder dies — which is precisely why it needs
// no stale-holder handling at all. Node has no `flock` without adding a
// dependency, so staleness is resolved explicitly below instead.
export const LOCK_REFUSED_EXIT = 4

/**
 * Where a worktree's lock lives: a SIBLING of the tree, never a file inside it.
 *
 * `--fresh` runs `git worktree remove --force` on the tree, which would delete
 * an inside-the-tree lock along with it and hand the slot to the next caller
 * while the first is still running.
 */
export function worktreeLockPath(worktreePath) {
    return `${worktreePath.replace(/[\\/]+$/, '')}.lock`
}

/**
 * Decide what an existing lock means, from the holder file's raw contents and a
 * liveness predicate. Pure, so both arms are testable without spawning anything.
 *
 * Fails CLOSED in every ambiguous case: an absent, empty, unparseable or
 * pid-less holder file reports `held`, never `stale`. A lock that cannot be read
 * is not evidence that nobody holds it, and reclaiming on a bad read is how a
 * guard comes to delete a live run's worktree.
 *
 * @param {string|null} raw  Holder file contents, or null when unreadable.
 * @param {(pid: number) => boolean} isAlive  Liveness probe.
 */
export function holderDisposition(raw, isAlive) {
    if (typeof raw !== 'string' || raw.trim() === '') {
        return { state: 'held', pid: null, branch: null, detail: 'its holder file is missing or empty' }
    }

    let parsed
    try {
        parsed = JSON.parse(raw)
    } catch {
        return { state: 'held', pid: null, branch: null, detail: 'its holder file is unparseable' }
    }

    const pid = Number(parsed?.pid)
    const branch = typeof parsed?.branch === 'string' ? parsed.branch : null
    const startedAt = typeof parsed?.startedAt === 'string' ? parsed.startedAt : null

    if (!Number.isInteger(pid) || pid <= 0) {
        return { state: 'held', pid: null, branch, startedAt, detail: 'its holder file names no usable pid' }
    }

    if (isAlive(pid)) {
        return { state: 'held', pid, branch, startedAt, detail: `pid ${pid} is still running` }
    }

    return { state: 'stale', pid, branch, startedAt, detail: `pid ${pid} is gone` }
}

/**
 * The refusal a second run sees. Names the holder, because "already running" with
 * nothing to look at is the message people work around rather than act on.
 */
export function lockRefusalMessage(disposition, worktreePath) {
    const who = [
        disposition.pid === null ? null : `pid ${disposition.pid}`,
        disposition.branch === null ? null : `branch ${disposition.branch}`,
        disposition.startedAt === null ? null : `started ${disposition.startedAt}`,
    ].filter(Boolean).join(', ')

    return [
        `REFUSED: another local-CI run holds ${worktreePath}.`,
        who === '' ? null : `  Holder: ${who}.`,
        `  Reason: ${disposition.detail}.`,
        '  Two runs in one worktree corrupt each other, and the failures they produce',
        '  move between runs on an identical commit — which reads exactly like a flake.',
        `  Wait for it to finish, or give this run its own --worktree-path.`,
    ].filter(Boolean).join('\n')
}

/**
 * `process.kill(pid, 0)` as a liveness probe. EPERM means the process EXISTS and
 * belongs to another user, so it is alive — treating it as dead is how a lock
 * gets reclaimed out from under a run started by someone else.
 */
function pidIsAlive(pid) {
    try {
        process.kill(pid, 0)

        return true
    } catch (err) {
        return err.code === 'EPERM'
    }
}

/**
 * Take the single-flight lock on a worktree, or refuse.
 *
 * `mkdirSync` is the primitive because it is ATOMIC: it either creates the
 * directory or throws EEXIST, with no window between the two. An
 * `existsSync`-then-write pair has exactly that window, and two runs starting
 * together both pass it.
 *
 * Returns a RESULT rather than exiting, so both arms are reachable from a test:
 * the process exit lives at the single call site instead of in here.
 *
 * @returns {{ok: true, release: () => void} | {ok: false, disposition: object}}
 */
export function acquireWorktreeLock(worktreePath, branch) {
    const lockPath = worktreeLockPath(worktreePath)
    const holderFile = join(lockPath, 'holder.json')

    // At most two passes: one reclaim of a provably dead holder, then a final
    // attempt. A loop here would spin against a live competitor.
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            mkdirSync(lockPath)
        } catch (err) {
            if (err.code !== 'EEXIST') {
                throw err
            }

            let raw = null
            try {
                raw = readFileSync(holderFile, 'utf8')
            } catch {
                raw = null
            }

            const disposition = holderDisposition(raw, pidIsAlive)

            if (disposition.state === 'held' || attempt > 0) {
                return { ok: false, disposition }
            }

            // Announced, never silent: a reclaim that leaves no trace is
            // indistinguishable from never having been locked.
            warn(`Reclaiming a stale lock on ${worktreePath} — ${disposition.detail}.`)
            rmSync(lockPath, { recursive: true, force: true })

            continue
        }

        writeFileSync(holderFile, `${JSON.stringify({
            pid: process.pid,
            branch,
            startedAt: new Date().toISOString(),
        }, null, 2)}\n`)

        let released = false

        return {
            ok: true,
            release: () => {
                if (released) {
                    return
                }
                released = true
                rmSync(lockPath, { recursive: true, force: true })
            },
        }
    }

    /* c8 ignore next */
    throw new Error('unreachable: lock acquisition fell through')
}

export const KNOWN_CONTENT_LITTER = []

/**
 * The clause a first-boot failure carries when `content/` changed revision during
 * this run (UAMS-Web/uams-statamic-archive#2832), or null when it did not or either revision is unknown.
 *
 * A Stache cached against the old revision crashes the first boot that reads it
 * once the new revision adds a collection (`Call to a member function sites() on
 * null`), and nothing in that error names the submodule move. Naming it here is
 * what tells this case apart from an ordinary install failure.
 *
 * @param {string|null} before The `content/` revision before the submodule step.
 * @param {string|null} after The `content/` revision after it.
 * @returns {string|null}
 */
export function contentMoveNote(before, after) {
    if (!before || !after || before === after) {
        return null
    }

    return `the first app boot after content/ moved ${before.slice(0, 9)} -> ${after.slice(0, 9)} in this run`
}

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


// UAMS-Web/uams-statamic-archive#1743 — flatten patches.lock.json into `package: description` lines so the run
// output NAMES what the branch DECLARES. Absence then shows up as a missing line
// instead of being invisible, which is what let the UAMS-Web/uams-statamic-archive#1730 miss go unnoticed.
// This reports the declaration, not proof of application — the reinstall below is
// what establishes that.
export function describePatches(raw) {
    if (!raw) {
        return []
    }
    try {
        const parsed = JSON.parse(raw)
        const groups = parsed?.patches ?? {}
        return Object.keys(groups)
            .sort()
            .flatMap((pkg) => (Array.isArray(groups[pkg]) ? groups[pkg] : [])
                .map((p) => `${pkg}: ${p.description ?? p.url ?? 'unnamed patch'}`))
    } catch {
        return []
    }
}

// Abort setup steps hard — there is no point running jobs against a half-built
// worktree.
function assertExit(code, what) {
    if (code !== 0) {
        throw new Error(`${what} failed (exit ${code}).`)
    }
}

// --- toolchain discovery ---------------------------------------------------

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

/**
 * Ask the resolved PHP for its version, and refuse to continue if it could not answer.
 *
 * `capture()` returns trimmed stdout and discards the exit status, so a PHP that was KILLED
 * and one that printed nothing are the same empty string. Preflight then carried that empty
 * value forward: it printed `PHP: /path/to/php84 ()` — its own evidence that discovery had
 * failed — and went on to probe `extension_loaded("imagick")` the same way, whose identically
 * empty answer was read as the extension being absent (UAMS-Web/uams-statamic-archive#2312).
 *
 * The cost is not the false negative but the REMEDY it prescribes. A bare wrong answer leaves
 * a reader puzzled; this one told them to install imagick and read README_Imagick.md, which
 * does nothing whatever for a PHP that cannot execute. Preflight also runs before job
 * selection, so it aborted node-only runs that never needed PHP at all.
 *
 * So this fails HERE, naming the interpreter, and the extension check is never reached unless
 * PHP has been shown to run. `captureResult()` is what makes that possible, and it exists in
 * this file for exactly this reason — see its comment on a probe whose absence of output is
 * indistinguishable from a probe that failed.
 *
 * @param  {string}  php        resolved path to the PHP binary
 * @param  {string}  repoRoot   cwd for the probe
 * @return {string}  the reported version, guaranteed non-empty
 */
export function phpVersionOrRefuse(php, repoRoot) {
    let probe

    try {
        probe = captureResult(php, ['-r', 'echo PHP_VERSION;'], repoRoot)
    } catch (error) {
        // spawn itself failed — no such file, not executable, EAGAIN.
        throw new Error(
            `Could not run PHP at '${php}': ${error.message}. `
            + 'Fix the interpreter before anything else — no other preflight result is meaningful '
            + 'until it runs, and none is reported.',
        )
    }

    const version = probe.stdout.trim()

    if (probe.status !== 0 || version === '') {
        throw new Error(
            `PHP at '${php}' did not report a version (exit ${probe.status}). It is not executing, `
            + 'so nothing further has been established about it — including which extensions it has. '
            + 'Run it directly to see the underlying error; `php -n -v` bypasses the ini if an '
            + 'extension is at fault.',
        )
    }

    return version
}

/**
 * Pick the first candidate that answers as a Python 3, or null when none does.
 *
 * Being on PATH is not the question, for the same reason `phpVersionOrRefuse()` gives for PHP:
 * a name that resolves is not an interpreter that runs. `python` can be a Python 2, and on
 * Windows `python3` can be the Microsoft Store alias, an installer stub rather than an
 * interpreter (not measured on a Windows machine here). So each candidate is asked for its
 * major version and only a `3` is accepted (UAMS-Web/uams-statamic-archive#2850).
 *
 * @param  {Array<string|null|undefined>}  candidates  paths in preference order; empty entries are skipped
 * @param  {(candidate: string) => string}  majorOf    the major version it reports, or '' when it cannot answer
 * @return {string|null}
 */
export function pickPython3(candidates, majorOf) {
    for (const candidate of candidates) {
        if (candidate && majorOf(candidate) === '3') {
            return candidate
        }
    }

    return null
}

// Warn — never fail — when a resolved tool drifts from CI's pinned version. A
// local minor/major bump usually still passes; a hard stop here would be more
// obstructive than useful. Absence is the only hard error (see resolveTool).
function warnVersion(name, actual, expectedPrefix) {
    if (actual && !actual.startsWith(expectedPrefix)) {
        warn(`${name} is ${actual} locally; CI pins ${expectedPrefix}.x. Results may differ from remote CI.`)
    }
}

// The npm floor `.npmrc`'s `engine-strict` enforces against `engines.npm`. Below it npm
// drops `min-release-age` with no warning and no non-zero exit, so an install silently
// loses the cooldown. Note the cooldown is resolution-time — `npm ci` reifies a lockfile
// and never applies it — so the floor's real protection is on whoever writes the lock.
// PARITY: mirrored by the "Pin npm to the min-release-age floor" step in ci.yml
const NPM_FLOOR = '11.10.0'
// PARITY-END: the FLOOR VALUE only. `assertNpmFloor()` below is local-only
// enforcement -- ci.yml pins npm and has no counterpart to it, so a region
// that swallowed the function would fire on changes CI cannot drift from.

// Unlike warnVersion this is a HARD stop, for two reasons: `engine-strict=true` in
// `.npmrc` means `npm ci` would fail anyway (with an opaque EBADENGINE dump), and an
// npm below the floor is the one drift that changes what gets installed rather than
// just how. Failing here names the fix instead.
function assertNpmFloor(npm, cwd) {
    const actual = capture(npm, ['--version'], cwd).trim()
    // An unreadable version is treated as below the floor rather than waved through:
    // the whole point is that a silently-wrong npm must not reach `npm ci`.
    const parts = /^(\d+)\.(\d+)\.(\d+)/.exec(actual)
    const asNumbers = (version) => version.split('.').map(Number)
    const [floorMajor, floorMinor, floorPatch] = asNumbers(NPM_FLOOR)
    const [major, minor, patch] = parts ? parts.slice(1).map(Number) : [-1, -1, -1]

    const satisfied = major > floorMajor
        || (major === floorMajor && minor > floorMinor)
        || (major === floorMajor && minor === floorMinor && patch >= floorPatch)

    if (!satisfied) {
        throw new Error(
            `npm reports ${parts ? actual : `an unreadable version (${JSON.stringify(actual)})`}; `
                + `this repo requires >= ${NPM_FLOOR} so that .npmrc's min-release-age cooldown `
                + 'is honored (Node 22 bundles npm 10.9.x, which ignores it silently). Upgrade '
                + 'with `npm install -g npm@^11.10.0`, or point $LOCAL_CI_NPM at a newer npm.',
        )
    }

    console.log(`npm:       ${npm} (${actual}, floor ${NPM_FLOOR})`)
}

// --- argument parsing ------------------------------------------------------

function usage() {
    console.log(`Run CI locally against a branch in an isolated git worktree.

Usage:
  node scripts/local-ci.mjs --branch <branch> [options]
  ./scripts/local-ci.mjs <branch> [options]

Options:
  -b, --branch <branch>     Branch to run CI against (required).
  -j, --jobs <list>         Subset of ${VALID_JOBS.join(',')} (default: all).
  -w, --worktree-path <p>   Worktree location (default ../uams-statamic-ci).
      --base-ref <ref>      Base ref for the Pint diff (default main; resolved to
                            origin/<ref> when that remote-tracking ref exists).
      --fresh               Remove and recreate the worktree from scratch.
      --skip-install        Reuse existing vendor/ and node_modules/.
      --skip-submodule      Do not init/checkout the content/, users/ and resources/forms/ submodules.
      --fetch               git fetch origin for the branch + base ref first.
      --no-reset            Skip the reused-worktree runtime-state reset.
      --commit-status       Post the verdict as a commit status on the branch's
                            head SHA (UAMS-Web/uams-statamic-archive#2323). Off by default: a developer running
                            this on their own branch should not write to GitHub.
      --runner-pinned       Internal (UAMS-Web/uams-statamic-archive#1882): set by the re-exec onto the branch's
                            own runner. Suppresses a further handoff, so the
                            comparison can fire at most once.
  -h, --help                Show this help.`)
}

export function parseArgs(argv) {
    const opts = {
        branch: null,
        jobs: ['all'],
        worktreePath: null,
        baseRef: 'main',
        fresh: false,
        skipInstall: false,
        skipSubmodule: false,
        fetch: false,
        noReset: false,
        runnerPinned: false,
    }
    const needsValue = (flag, value) => {
        if (value === undefined) {
            throw new Error(`Option ${flag} requires a value.`)
        }
        return value
    }
    for (let i = 0; i < argv.length; i++) {
        const arg = argv[i]
        switch (arg) {
            case '-h':
            case '--help':
                usage()
                process.exit(0)
                break
            case '-b':
            case '--branch':
                opts.branch = needsValue(arg, argv[++i])
                break
            case '-j':
            case '--jobs':
                opts.jobs = needsValue(arg, argv[++i]).split(',').map((j) => j.trim()).filter(Boolean)
                break
            case '-w':
            case '--worktree-path':
                opts.worktreePath = needsValue(arg, argv[++i])
                break
            case '--base-ref':
                opts.baseRef = needsValue(arg, argv[++i])
                break
            case '--fresh':
                opts.fresh = true
                break
            case '--skip-install':
                opts.skipInstall = true
                break
            case '--skip-submodule':
                opts.skipSubmodule = true
                break
            case '--fetch':
                opts.fetch = true
                break
            case '--no-reset':
                opts.noReset = true
                break
            case '--commit-status':
                opts.commitStatus = true
                break
            case RUNNER_PINNED_FLAG:
                // Accepted, but never sent by the handoff — see RUNNER_PINNED_ENV.
                // This arm is the manual escape hatch for forcing an in-place run.
                opts.runnerPinned = true
                break
            default:
                if (arg.startsWith('-')) {
                    throw new Error(`Unknown option: ${arg}`)
                }
                if (opts.branch === null) {
                    opts.branch = arg
                } else {
                    throw new Error(`Unexpected argument: ${arg}`)
                }
        }
    }
    if (!opts.branch) {
        throw new Error('A branch is required. Pass it with --branch <branch> (see --help).')
    }
    return opts
}

// UAMS-Web/uams-statamic-archive#1882 — the requested jobs this runner does not implement.
//
// Called from main() AFTER the runner handoff rather than from parseArgs, because
// the branch's job list is unknowable until the branch is checked out. Rejecting
// here would reject `--jobs <a-job-only-the-branch-has>` outright, which is the
// same class of failure the handoff exists to close.
export function unknownJobs(jobs) {
    return jobs.filter((job) => !VALID_JOBS.includes(job))
}

// --- main ------------------------------------------------------------------

function main() {
    const opts = parseArgs(process.argv.slice(2))

    // git is the one tool needed before anything else (to find the repo root).
    const git = resolveTool({
        name: 'git',
        candidates: [process.env.LOCAL_CI_GIT],
        onPath: 'git',
        hint: 'Install git or set $LOCAL_CI_GIT to its path.',
        envVar: 'LOCAL_CI_GIT',
    })

    // The PRIMARY checkout, not the worktree this was invoked from (UAMS-Web/uams-statamic-archive#1953). The
    // default below is a SIBLING of the repo root, so resolving `--show-toplevel`
    // here put the CI worktree inside `.claude/worktrees/` whenever the runner was
    // driven from a linked worktree — which the local-ci skill tells the pipeline
    // session to do. Every other use of repoRoot is a git command on the repository
    // (identical from any worktree) or a cwd for a cwd-independent tool, so the
    // primary is the right anchor for all of them.
    const repoRoot = primaryCheckout(process.cwd(), git)

    // Derived from the primary's own directory name, matching `slotDirName` in worktree-slots.mjs
    // instead of hard-coding one clone's name. Identical here (`uams-statamic-ci`), so the default
    // does not move; but a clone under any other directory name used to get a CI worktree that
    // worktree-slots could not recognize as the CI slot, and so held it to a full slot's bootstrap
    // forever — the two scripts have to agree on this name for either to be right.
    const worktreePath = opts.worktreePath || join(dirname(repoRoot), `${basename(repoRoot)}-ci`)

    // Single-flight (UAMS-Web/uams-statamic-archive#2256). Taken at the FIRST point the slot path is known, and
    // that position is deliberate rather than convenient. The obvious placement is
    // beside the destructive git calls further down — but `--fresh` runs
    // `worktree remove --force` before them, and the already-checked-out refusal
    // below is computed from `git worktree list`, state a concurrent `--fresh`
    // mutates. A lock taken after that read guards the writes and leaves the read
    // they depend on unguarded: the same defect, one level quieter. Held for the
    // whole run and released on exit and on a signal.
    //
    // `local-ci.lock.test.mjs` pins this ordering STATICALLY, by line index. A
    // behavioral test cannot: both runs fail identically downstream of the
    // removal, which is how this survived its own suite in the sibling repo.
    //
    // A PINNED run is the second half of ONE run, not a second run. When the
    // branch ships a different runner, the parent hands off to the branch's copy
    // with LOCAL_CI_RUNNER_PINNED=1 and waits; that child resolves the same
    // worktree path, so acquiring here would make it refuse against its own
    // parent and exit 4. The parent holds the lock across the handoff and
    // releases it when the whole run ends.
    const runnerPinned = opts.runnerPinned || process.env[RUNNER_PINNED_ENV] === '1'
    const worktreeLock = runnerPinned
        ? { ok: true, release: () => {} }
        : acquireWorktreeLock(worktreePath, opts.branch)

    if (!worktreeLock.ok) {
        console.error(lockRefusalMessage(worktreeLock.disposition, worktreePath))
        process.exit(LOCK_REFUSED_EXIT)
    }

    process.on('exit', worktreeLock.release)
    for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
        process.on(signal, () => {
            worktreeLock.release()
            process.exit(130)
        })
    }

    // Expand the `all` shorthand into the concrete job list — no numeral here, so
    // it cannot go stale the way "six" already had by the time this chain reached
    // nine links. Every job is in ALL_JOBS now that `browser` joined it (UAMS-Web/uams-statamic-archive#1727), so
    // this is a straight substitution with nothing held out.
    let jobs = opts.jobs
    if (jobs.includes('all')) {
        jobs = [...ALL_JOBS]
    }

    console.log(`Repo:      ${repoRoot}`)
    console.log(`Worktree:  ${worktreePath}`)
    console.log(`Branch:    ${opts.branch}`)
    console.log(`Base ref:  ${opts.baseRef}`)
    console.log(`Jobs:      ${jobs.join(', ')}`)

    // --- optional fetch -----------------------------------------------------

    if (opts.fetch) {
        step(`Fetching ${opts.branch} and ${opts.baseRef} from origin`)
        if (run(git, ['-C', repoRoot, 'fetch', 'origin', opts.branch, opts.baseRef], repoRoot) !== 0) {
            warn('Fetch failed (offline or out-of-budget?); continuing with local refs.')
        }
    }

    // --- worktree -----------------------------------------------------------

    let worktreeExists = existsSync(join(worktreePath, '.git'))

    if (opts.fresh && worktreeExists) {
        step('Removing existing worktree (--fresh)')
        assertExit(run(git, ['-C', repoRoot, 'worktree', 'remove', '--force', worktreePath], repoRoot), 'git worktree remove')
        worktreeExists = false
    }

    // UAMS-Web/uams-statamic-archive#1746 — reconcile the LOCAL branch with origin BEFORE checkout. Both paths
    // below resolve the local ref, and neither --fetch nor --fresh moves it, so
    // without this the run can validate a commit the PR has already moved past
    // (UAMS-Web/uams-statamic-archive#1710's false red). Done before checkout so `git branch -f` is legal — the
    // ref must not be checked out anywhere while it is rewritten.
    const localSha = capture(git, ['-C', repoRoot, 'rev-parse', '--verify', `refs/heads/${opts.branch}`], repoRoot, { ignoreStderr: true })
    const remoteSha = capture(git, ['-C', repoRoot, 'rev-parse', '--verify', `refs/remotes/origin/${opts.branch}`], repoRoot, { ignoreStderr: true })
    const mergeBase = localSha && remoteSha
        ? capture(git, ['-C', repoRoot, 'merge-base', localSha, remoteSha], repoRoot, { ignoreStderr: true })
        : null
    const sync = branchSyncAction(localSha || null, remoteSha || null, mergeBase || null)

    step(`Branch sync: ${sync.action} — ${sync.reason}`)

    if (sync.action === 'diverged') {
        // An author who rebases onto main and force-pushes — the flow the
        // sync-pr-branch rule *mandates* before the gating run — leaves the local
        // ref diverged from origin. That local ref is usually one THIS runner wrote
        // on a previous validation, so there is nothing of anyone's to lose, and a
        // hard refusal would stop the autonomous pipeline on its single most common
        // re-validation trigger.
        //
        // Reset to origin only when both hold: --fetch was passed (the caller is
        // explicitly asking to validate what is on the remote), and the branch is
        // not checked out in any worktree (nobody is sitting on it with work in
        // progress). Otherwise refuse — but say what actually happened.
        const checkedOutSomewhere = capture(git, ['-C', repoRoot, 'worktree', 'list', '--porcelain'], repoRoot, { ignoreStderr: true })
            .split('\n')
            .some((line) => line.trim() === `branch refs/heads/${opts.branch}`)

        if (opts.fetch && !checkedOutSomewhere) {
            warn(
                `${opts.branch} diverged from origin/${opts.branch} (force-push or rebase). `
                + `--fetch was passed and the branch is not checked out anywhere, so it is being reset to `
                + `origin (${remoteSha.slice(0, 9)}). The previous local commit was ${localSha.slice(0, 9)}.`,
            )
            assertExit(run(git, ['-C', repoRoot, 'branch', '-f', opts.branch, remoteSha], repoRoot), 'git branch -f')
        } else {
            throw new Error(
                `Refusing to validate ${opts.branch}: the local ref and origin/${opts.branch} have diverged.\n`
                + `  local  ${localSha}\n  origin ${remoteSha}\n`
                + (checkedOutSomewhere
                    ? `The branch is checked out in a worktree, so it may carry work in progress; `
                        + `reconcile it there rather than having this runner rewrite it.\n`
                    : `Re-run with --fetch to validate what is on the remote (this rewrites the local ref), `
                        + `or reconcile them by hand.\n`)
                + 'Validating either side silently would report a result for a commit nobody reviewed.',
            )
        }
    }

    if (sync.action === 'fast-forward') {
        // Lossless: local is strictly an ancestor of origin. Rewriting the ref here
        // (rather than resetting after checkout) keeps a reused worktree and a
        // fresh one on exactly the same path.
        if (worktreeExists) {
            assertExit(run(git, ['-C', worktreePath, 'checkout', '--detach', remoteSha], repoRoot), 'git checkout --detach')
        }
        assertExit(run(git, ['-C', repoRoot, 'branch', '-f', opts.branch, remoteSha], repoRoot), 'git branch -f')
    }

    if (sync.action === 'ahead') {
        warn(`${opts.branch} has commits origin/${opts.branch} does not — validating work that was never pushed, which is not what the PR shows.`)
    }

    if (worktreeExists) {
        step(`Reusing worktree, checking out ${opts.branch}`)
        assertExit(run(git, ['-C', worktreePath, 'checkout', opts.branch], repoRoot), 'git checkout')
    } else {
        step(`Creating worktree for ${opts.branch}`)
        assertExit(run(git, ['-C', repoRoot, 'worktree', 'add', worktreePath, opts.branch], repoRoot), 'git worktree add')
    }

    // UAMS-Web/uams-statamic-archive#1746 — state the commit actually under test, so it can be compared against
    // the PR head without inspecting the worktree. UAMS-Web/uams-statamic-archive#1710 cost a full run precisely
    // because the validated commit was never printed.
    const validatedSha = capture(git, ['-C', worktreePath, 'rev-parse', 'HEAD'], repoRoot, { ignoreStderr: true })
    const validatedSubject = capture(git, ['-C', worktreePath, 'log', '-1', '--pretty=%s'], repoRoot, { ignoreStderr: true })
    console.log(`Validating: ${validatedSha} ${validatedSubject}`)

    // --- runner handoff (UAMS-Web/uams-statamic-archive#1882) ---------------------------------------------
    //
    // The branch is on disk now, so its own runner can be compared against this
    // one. Done HERE — after checkout, before the submodule/install/prep steps —
    // so a branch that changes any of those governs them too, not merely the job
    // loop. A branch whose runner matches pays one file read and continues.
    //
    // This side of the comparison is the source captured at module load, never a
    // read made now: the checkout above may have replaced this very file (UAMS-Web/uams-statamic-archive#2856).
    const executingRunner = EXECUTING_RUNNER_PATH
    const branchRunner = join(worktreePath, 'scripts', 'local-ci.mjs')
    const handoff = runnerHandoff({
        pinned: runnerPinned,
        executingSource: EXECUTING_RUNNER_SOURCE,
        branchSource: existsSync(branchRunner) ? readFileSync(branchRunner, 'utf8') : null,
        // UAMS-Web/uams-statamic-archive#2881: the helpers too, captured at load on this side like the runner itself.
        executingHelpers: EXECUTING_HELPER_SOURCES,
        branchHelpers: Object.fromEntries(RUNNER_HELPER_MODULES.map((name) => {
            const branchHelper = join(worktreePath, 'scripts', name)

            return [name, existsSync(branchHelper) ? readFileSync(branchHelper, 'utf8') : null]
        })),
    })

    if (handoff.action === 'handoff') {
        step(`Runner handoff: ${handoff.reason}`)
        console.log(`  this checkout: ${executingRunner}`)
        console.log(`  branch's copy: ${branchRunner}`)
        console.log('Re-running under the branch\'s own runner so its job list is the one that executes.')
        const child = spawnSync(
            process.execPath,
            [branchRunner, ...handoffArgv(process.argv.slice(2), worktreePath)],
            // The pin rides in the ENVIRONMENT, never in argv: a runner predating
            // UAMS-Web/uams-statamic-archive#1882 would reject an unknown flag and take every older branch with
            // it. An older runner simply ignores this variable — it has no handoff
            // logic to suppress — while a current one reads it and does not hand
            // off again.
            { cwd: repoRoot, stdio: 'inherit', env: { ...process.env, [RUNNER_PINNED_ENV]: '1' } },
        )
        if (child.error) {
            throw child.error
        }
        // A signalled child reports a null status; treat that as a failure rather
        // than letting `?? 1` read a clean exit into an unrelated code.
        process.exit(child.status ?? 1)
    }

    // Named in the summary so a green is self-describing: which copy of this
    // script defined the job list is part of what a run establishes.
    const runnerProvenance = runnerProvenanceLine({
        executingRunner,
        worktreePath,
        reason: handoff.reason,
        compared: handoff.compared,
    })
    console.log(`Runner:     ${runnerProvenance}`)

    // This runner is the one defining the chain, so it is now the authority on
    // which job names are real. A name that survived the handoff and still is not
    // implemented would otherwise be dropped in silence by the CHAIN loop below,
    // and the summary would report a pass for a job nobody ran.
    //
    // Checked against what the CALLER asked for (`opts.jobs`), not the expansion:
    // `--jobs all,typo` expands to ALL_JOBS and loses `typo` on the way, so
    // validating the expansion would report every job passing while the operator
    // believed `typo` had run. That is the very silence this guard exists for.
    const unknown = unknownJobs(opts.jobs)
    if (unknown.length > 0) {
        throw new Error(
            `Unknown job${unknown.length > 1 ? 's' : ''} ${unknown.map((j) => `'${j}'`).join(', ')}. `
            + `Valid jobs for this runner: ${VALID_JOBS.join(', ')}.`,
        )
    }

    // --- reused-worktree drift (UAMS-Web/uams-statamic-archive#1889) --------------------------------------
    //
    // `git checkout` updates TRACKED files, and nothing removes a path that has
    // become untracked — so a file the validated commit DELETES can survive on
    // disk and be executed by the run that is supposed to establish its absence.
    // Observed validating UAMS-Web/uams-statamic-archive#1848 and UAMS-Web/uams-statamic-archive#1855 back to back: two audit tests deleted by
    // UAMS-Web/uams-statamic-archive#1847 were absent from both validated commits, and the REUSING run executed
    // and passed both (458 serial), reconciling exactly with the --fresh run (456).
    //
    // DETECTION, not a silent clean. A clean would have fixed that run while
    // leaving nobody aware a prior one had left files behind, and the leak would go
    // on accumulating — the same reasoning the reset step below gives for having
    // `git clean` print each path it removes.
    //
    // Only on a REUSED worktree: `git worktree add` produces a tree that matches
    // the commit by construction, so --fresh has nothing to check and is left
    // exactly as it was. Run here — after the handoff, before the submodule and
    // install steps — so a drifted worktree is refused before it costs anything,
    // and so a branch that changes this check is the one whose version runs.
    //
    // `--ignore-submodules=all` is load-bearing rather than tidiness, though not
    // for the reason this comment used to give (a shared submodule HEAD leaking a
    // concurrent session's checkout in — false on the pinned git, measured under
    // UAMS-Web/uams-statamic-archive#1959; see .claude/rules/worktrees.md). A reused worktree's content/ sits at
    // the PREVIOUS run's revision until the submodule step re-checks it out at the
    // recorded pointer a few lines below, so a pointer difference here is both
    // expected and self-healing — not drift this check could act on. The same flag,
    // for the same shape of reason, is in .githooks/pre-commit.
    if (worktreeExists) {
        step('Verifying the reused worktree matches the validated commit')
        // captureResult, not capture: the RAW stdout (a trim eats the leading
        // status column of the first record) and the exit STATUS (a git that
        // failed printing nothing is otherwise indistinguishable from a clean
        // worktree — the check would silently verify nothing).
        const probe = captureResult(
            git,
            ['-C', worktreePath, 'status', '--porcelain', '-z', '--untracked-files=all', '--ignore-submodules=all'],
            repoRoot,
        )

        if (probe.status !== 0) {
            throw new Error(
                `Refusing to validate ${opts.branch}: \`git status\` in ${worktreePath} exited ${probe.status}, `
                + 'so the worktree could not be checked against the commit under test.\n'
                + 'An unverifiable worktree is not a verified one — rebuild it with --fresh, or fix the repository state.',
            )
        }

        const drift = worktreeDriftEntries(probe.stdout)

        if (drift.length > 0) {
            console.error(red(`The worktree at ${worktreePath} differs from ${validatedSha.slice(0, 9)}:`))
            for (const entry of drift) {
                console.error(red(`  ${entry.status} ${entry.path}${entry.origin ? ` (from ${entry.origin})` : ''}`))
                console.error(`       ${driftLabel(entry.status)}`)
            }
            throw new Error(
                `Refusing to validate ${opts.branch}: ${drift.length} path${drift.length > 1 ? 's' : ''} in the worktree `
                + 'do not match the commit under test, so the run would exercise code the commit does not contain.\n'
                + '  Rebuild the worktree with --fresh, or remove/restore the paths above by hand.\n'
                + 'Reporting a result for a tree that is not the commit is the false green this check exists to remove.',
            )
        }

        // Say so out loud. A check whose only output is silence cannot be told
        // apart from one that never ran, which is the shape of the bug it guards.
        console.log(`Worktree clean: 0 paths differ from ${validatedSha.slice(0, 9)}.`)
    }

    // --- toolchain discovery ------------------------------------------------
    //
    // Resolve every binary the selected jobs need before any of them runs, so a
    // missing tool fails with an actionable message rather than as a path-not-found
    // error deep inside a job. Only the tools actually used are required (a
    // Pint-only run never forces a Node install). macOS Herd keeps version-pinned
    // binaries (php84) next to the `php` symlink, so we target 8.4 even when the
    // contributor's default Herd PHP is another version; elsewhere we resolve on
    // PATH.
    //
    // Deliberately AFTER the handoff (UAMS-Web/uams-statamic-archive#1882), not before it. Which tools a run
    // needs is a function of the job list, and until the handoff resolves, the job
    // list is the EXECUTING copy's — not the branch's. Resolving first let the base
    // branch's requirements gate a branch that had changed them: `assertNpmFloor`
    // is a hard stop, so a branch removing the npm-dependent jobs (or raising the
    // floor it fails) died on the base branch's floor before its own runner was
    // ever consulted. Everything above this point uses `git` alone, which is
    // resolved at the top of main(); the first tool need is the submodule step
    // below. The cost is that a missing tool is now reported after the checkout
    // rather than before it — seconds on a reused worktree.

    step('Resolving local toolchain')

    const herdMacBin = isMacOS ? join(homedir(), 'Library', 'Application Support', 'Herd', 'bin') : null

    const php = resolveTool({
        name: 'PHP 8.4',
        candidates: [process.env.LOCAL_CI_PHP, herdMacBin && join(herdMacBin, 'php84')],
        onPath: 'php',
        hint: 'Install PHP 8.4 — via Laravel Herd (https://herd.laravel.com) — or set $LOCAL_CI_PHP to its path.',
        envVar: 'LOCAL_CI_PHP',
    })
    // Every PHP process carries the runner's memory limit (UAMS-Web/uams-statamic-archive#3212); see withPhpMemoryLimit().
    const runPhp = (args, cwd, extraEnv) => run(php, withPhpMemoryLimit(args), cwd, extraEnv)
    const capturePhp = (args, cwd) => capture(php, withPhpMemoryLimit(args), cwd)
    const composer = resolveTool({
        name: 'Composer',
        candidates: [process.env.LOCAL_CI_COMPOSER, herdMacBin && join(herdMacBin, 'composer')],
        onPath: 'composer',
        hint: 'Install Composer (bundled with Laravel Herd) or set $LOCAL_CI_COMPOSER to its path.',
        envVar: 'LOCAL_CI_COMPOSER',
    })

    // Refuses when PHP cannot answer, so the imagick check below is only ever reached by an
    // interpreter that has been shown to run (UAMS-Web/uams-statamic-archive#2312).
    const phpVersion = phpVersionOrRefuse(php, repoRoot)
    warnVersion('PHP', phpVersion, '8.4')
    console.log(`PHP:       ${php} (${phpVersion})`)
    console.log(`Composer:  ${composer}`)

    // Node tooling is per-job: `node` for the jobs in NODE_JOBS, `npm` for build/
    // browser (and the npm ci install step), `npx` for the browser job's
    // Playwright install. Resolve lazily so unrelated runs don't require Node.
    let node = null
    let npm = null
    let npx = null
    let python = null

    if (jobs.some((job) => NODE_JOBS.includes(job))) {
        node = resolveTool({
            name: 'Node 22',
            candidates: [process.env.LOCAL_CI_NODE],
            onPath: 'node',
            hint: 'Install Node 22 (https://nodejs.org or a version manager) or set $LOCAL_CI_NODE to its path.',
            envVar: 'LOCAL_CI_NODE',
        })
        const nodeVersion = capture(node, ['--version'], repoRoot).replace(/^v/, '')
        warnVersion('Node', nodeVersion, '22')
        console.log(`Node:      ${node} (v${nodeVersion})`)
    }
    if (jobs.includes('encoding')) {
        // The encoding job also runs the release-notes generator's own suite (UAMS-Web/uams-statamic-archive#2850). An
        // override is taken alone, so a wrong $LOCAL_CI_PYTHON refuses rather than quietly
        // falling through to whatever is on PATH.
        const pythonCandidates = process.env.LOCAL_CI_PYTHON
            ? [process.env.LOCAL_CI_PYTHON]
            : [which('python3'), which('python')]
        python = pickPython3(pythonCandidates, (candidate) => {
            try {
                const probe = captureResult(candidate, ['-c', 'import sys; print(sys.version_info[0])'], repoRoot, { ignoreStderr: true })

                return probe.status === 0 ? probe.stdout.trim() : ''
            } catch {
                return ''
            }
        })
        if (! python) {
            throw new Error(
                "Required tool 'Python 3' was not found"
                + (process.env.LOCAL_CI_PYTHON ? ` ($LOCAL_CI_PYTHON='${process.env.LOCAL_CI_PYTHON}' does not answer as Python 3)` : '')
                + '. Install Python 3 (https://www.python.org) or set $LOCAL_CI_PYTHON to its path.',
            )
        }
        console.log(`Python:    ${python}`)
    }
    if (jobs.includes('build') || jobs.includes('browser') || jobs.includes('js') || jobs.includes('spell')) {
        npm = resolveTool({
            name: 'npm',
            candidates: [process.env.LOCAL_CI_NPM],
            onPath: 'npm',
            hint: 'Install Node 22 (which bundles npm) or set $LOCAL_CI_NPM to its path.',
            envVar: 'LOCAL_CI_NPM',
        })
        assertNpmFloor(npm, repoRoot)
    }
    if (jobs.includes('browser')) {
        npx = resolveTool({
            name: 'npx',
            candidates: [process.env.LOCAL_CI_NPX],
            onPath: 'npx',
            hint: 'Install Node 22 (which bundles npx) or set $LOCAL_CI_NPX to its path.',
            envVar: 'LOCAL_CI_NPX',
        })
        console.log(`npx:       ${npx}`)
    }

    const needsContent = jobs.includes('pest')
    const needsNode = jobs.includes('build') || jobs.includes('browser') || jobs.includes('js') || jobs.includes('spell')
    // The framework prep (env + app key + post-autoload-dump) that CI runs in the
    // pest job is also required by the browser job (post-autoload-dump runs
    // statamic:install, which publishes the Statamic core CP assets the served
    // Control Panel loads) and by the phpstan job (larastan boots the Laravel app
    // to resolve container/facade types — see vendor/larastan/larastan/bootstrap.php).
    // The content/, users/ and resources/forms/ submodules stay pest-only.
    const needsFramework = jobs.includes('pest') || jobs.includes('browser') || jobs.includes('phpstan')

    // --- content, users and forms submodules (pest only) ----------------
    //
    // users/ rides along with content/ (UAMS-Web/uams-statamic-archive#2477): since UAMS-Web/uams-statamic-archive#2454 it holds only the two
    // seed accounts and the .gitignore allowlist that keeps registrant records
    // out of git, so checking it out costs a 2 KB pack and is what lets
    // tests/Feature/Users/RegistrantRecordsAreGitIgnoredTest.php run in this gate
    // rather than skip. A reverted allowlist then reds the pest job here instead
    // of passing it. The two asset submodules stay un-initialized: they are
    // binary-heavy and nothing in the suite needs them.
    //
    // --skip-submodule lets a caller that has already materialized content/ (e.g.
    // a concurrent multi-worktree run that staged every submodule sequentially
    // first) skip this step. On SOME git versions submodules share a single
    // .git/modules/<name> gitdir across all worktrees, and two worktrees checking
    // content/ out at once can corrupt that shared state — stage submodules
    // serially, then run the heavy jobs in parallel with --skip-submodule. Not the
    // pinned git, which gives each worktree its own gitdir (measured under UAMS-Web/uams-statamic-archive#1959;
    // see .claude/rules/worktrees.md), so this is a version-safe habit rather than
    // a local necessity. The `--ignore-submodules=all` comment above covers why
    // that measurement does not retire this flag.

    // The content/ revision either side of the submodule step, so a first-boot
    // failure can name a move that happened in this run (UAMS-Web/uams-statamic-archive#2832). Read only from an
    // INITIALIZED submodule: in a plain directory `rev-parse` walks up and answers
    // for the superproject instead.
    const contentRevision = () => (existsSync(join(worktreePath, 'content', '.git'))
        ? capture(git, ['-C', join(worktreePath, 'content'), 'rev-parse', 'HEAD'], repoRoot, { ignoreStderr: true }) || null
        : null)
    let contentMove = null

    if (needsContent && !opts.skipSubmodule) {
        step('Initializing content/, users/ and resources/forms/ submodules')
        const contentBefore = contentRevision()

        // UAMS-Web/uams-statamic-archive#1999's non-empty-directory trap, on the runner's own reused worktree. An
        // UNINITIALIZED submodule path is a plain directory, and the app writes into
        // it anyway: the file user driver saves test-created accounts under `users/`
        // whether or not the submodule is checked out, and a band killed before its
        // `afterEach` strands them there. `git submodule update --init` then refuses
        // the clone — "destination path already exists and is not an empty
        // directory", twice, then aborts — and every later run on this machine dies
        // at this step with a message naming neither the cause nor the remedy. The
        // UAMS-Web/uams-statamic-archive#1889 drift probe cannot see it either: `--ignore-submodules=all` hides
        // paths inside a gitlink. So clear the directory here, naming what went. It
        // is safe by construction on this tree only: the CI worktree is runner-owned,
        // nothing is authored in it, and a submodule path with no `.git` can hold
        // only app output. (An initialized one carries `<path>/.git` — a gitdir file
        // — and is left alone; `update --init` is a no-op or a re-checkout there.)
        for (const name of GATE_SUBMODULES) {
            const path = join(worktreePath, name)
            if (!existsSync(path) || existsSync(join(path, '.git'))) {
                continue
            }
            const stranded = readdirSync(path)
            if (stranded.length === 0) {
                continue
            }
            warn(`${name}/ is not initialized but holds ${stranded.length} path${stranded.length > 1 ? 's' : ''} (app output stranded by an earlier run); clearing it so the clone can land:`)
            for (const entry of stranded) {
                console.error(`  ${name}/${entry}`)
            }
            rmSync(path, { recursive: true, force: true })
        }

        // Mirror ci.yml: clone the private content, users and forms repos over HTTPS
        // using the Composer GitHub PAT via git's insteadOf rewrite. The token
        // lives in your global Composer auth and is read at runtime — never
        // hardcoded. One argv, so the token and credential-helper branches cannot
        // drift apart on WHICH submodules they initialize.
        const token = capture(composer, ['config', '--global', '--auth', 'github-oauth.github.com'], repoRoot, { ignoreStderr: true })
        const submoduleInit = ['submodule', 'update', '--init', '--depth', '1', ...GATE_SUBMODULES]
        if (token) {
            const insteadOf = `url.https://x-access-token:${token}@github.com/.insteadOf=https://github.com/`
            assertExit(
                run(git, ['-C', worktreePath, '-c', insteadOf, ...submoduleInit], repoRoot),
                'gate submodule init',
            )
        } else {
            warn('No Composer github-oauth token found; trying submodule init via your git credential helper.')
            assertExit(
                run(git, ['-C', worktreePath, ...submoduleInit], repoRoot),
                'gate submodule init',
            )
        }

        contentMove = contentMoveNote(contentBefore, contentRevision())
    }

    // --- install ------------------------------------------------------------

    if (!opts.skipInstall) {
        // PARITY: mirrors the `imagick` entry added to every setup-php `extensions:`
        // list in ci.yml (UAMS-Web/uams-statamic-archive#1830). `ext-imagick` is a declared platform requirement in
        // composer.json — the app forces the Imagick manipulation driver at boot, and
        // SvgRasterizer needs it for the brand-logo SVG→PNG fallback (GD cannot
        // rasterize SVG). CI provisions the extension; a developer machine installs it
        // by hand, so the useful mirror here is naming the omission before Composer's
        // platform error, which does not know about README_Imagick.md.
        if (!capturePhp(['-r', 'echo extension_loaded("imagick") ? "1" : "";'], repoRoot)) {
            throw new Error(
                'This PHP has no `imagick` extension, which composer.json declares as '
                + '`ext-imagick`, so the `composer install` below fails its platform check. '
                + 'Install it first — see README_Imagick.md.',
            )
        }
        // PARITY-END: the extension probe only. What follows is this script's own
        // install sequence, which ci.yml does not mirror.

        // UAMS-Web/uams-statamic-archive#1743 and UAMS-Web/uams-statamic-archive#3050 -- install, re-applying the patch set when this worktree's
        // record of what it last applied does not match the branch. The record is
        // removed before the install and rewritten only once every install has exited
        // 0, so a failed install leaves none behind and the next run re-patches.
        const patchLockPath = join(worktreePath, 'patches.lock.json')
        const patchLockRaw = existsSync(patchLockPath) ? readFileSync(patchLockPath, 'utf8') : null
        step('composer install (--no-scripts)')
        installWithPatchStamp({
            vendorDir: join(worktreePath, 'vendor'),
            patchLockRaw,
            install: (label) => assertExit(
                run(composer, ['install', '--no-scripts', '--no-interaction', '--no-progress', '--prefer-dist'], worktreePath),
                label,
            ),
            announce: step,
        })

        // Name what the branch DECLARES, so a missing patch is visible rather than
        // silent. This reads patches.lock.json only: the install exit codes above
        // are the evidence that the patches applied, so the line claims no more.
        const declared = describePatches(patchLockRaw)
        if (declared.length > 0) {
            console.log(`Patches declared (${declared.length}):`)
            for (const line of declared) {
                console.log(`  - ${line}`)
            }
        } else {
            console.log('Patches: none declared in patches.lock.json')
        }
    }

    // Clear a REUSED worktree's file caches before anything boots the app (UAMS-Web/uams-statamic-archive#2832).
    // The Stache lives in the file cache under storage/framework, built against
    // whatever content/ revision the last run had. When the submodule step above
    // moved content/ to a revision that adds a collection, the first boot reads
    // that stale index and dies in `CollectionEntriesStore` with `Call to a member
    // function sites() on null` -- reproduced on demand by building the Stache at
    // one revision and booting at the next. This has to be a FILESYSTEM clear:
    // `artisan statamic:stache:clear` boots the app first, and crashes the same way.
    // bootstrap/cache goes too, since its compiled package and config caches are
    // equally from the previous run; `package:discover` rebuilds them below.
    // Local-only: ci.yml always starts from a fresh checkout, so it has no cache to
    // be stale and needs no counterpart. The artisan half of the reset still runs
    // after the install, where booting is safe again.
    if (needsFramework && worktreeExists && !opts.noReset) {
        step('Clearing reused-worktree file caches before the first app boot')
        run(git, ['-C', worktreePath, 'clean', '-xdf', 'storage/framework', 'bootstrap/cache'], repoRoot)
    } else if (contentMove && opts.noReset) {
        warn(`--no-reset keeps this worktree's Stache cache, and content/ moved in this run (${contentMove}); the first boot may read an index built for the old revision.`)
    }

    // The pest and browser jobs boot the framework, so the env/key/post-autoload-dump
    // preparation (which CI runs in the pest job and, identically, the browser job)
    // is gated on either being requested.
    if (needsFramework && !opts.skipInstall) {
        step('Preparing .env + app key + Statamic install')
        const envPath = join(worktreePath, '.env')
        // Preserve an existing .env. CI starts from a clean checkout with none, so
        // this still copies there; reusing a real dev worktree, it keeps the
        // developer's local config instead of overwriting it.
        if (!existsSync(envPath)) {
            copyFileSync(join(worktreePath, '.env.example'), envPath)
        }
        // Generate an app key only when a real one is not already set. A generated
        // Laravel key is `base64:...`; matching that (rather than any non-empty
        // value) means an empty/placeholder APP_KEY="" from .env.example is still
        // regenerated, while a real existing key is preserved.
        if (!/^APP_KEY="?base64:/m.test(readFileSync(envPath, 'utf8'))) {
            assertExit(runPhp(['artisan', 'key:generate'], worktreePath), contentMove ? `key:generate (${contentMove})` : 'key:generate')
        }
        assertExit(
            run(composer, ['run-script', 'post-autoload-dump', '--no-interaction'], worktreePath),
            contentMove ? `post-autoload-dump (${contentMove})` : 'post-autoload-dump',
        )
    }

    if (needsNode && !opts.skipInstall) {
        step('npm ci')
        assertExit(run(npm, ['ci'], worktreePath), 'npm ci')
    }

    // Reset stale runtime state on a REUSED worktree so a residue-tainted run
    // cannot silently test the wrong thing — stale compiled views, a cached config,
    // or a half-written cache file left by a crashed prior run. A --fresh worktree
    // is already clean, so this runs only when reusing one; --no-reset opts out.
    // Local-only by design: real CI is always a fresh checkout, so there is no
    // ci.yml counterpart (noted there too). This is determinism hygiene, NOT a
    // flake fix — the intra-run parallel isolation bug (UAMS-Web/uams-statamic-archive#1136) is fixed in the
    // tests, not here. `git clean` prints each removed path, so an accumulating
    // leak stays visible instead of being silently scrubbed. Non-fatal: a reset
    // hiccup should not fail the run, so these are not assertExit-gated.
    if (needsFramework && worktreeExists && !opts.noReset) {
        step('Resetting reused-worktree runtime state (caches, Stache)')
        // storage/framework was already cleaned before the first boot (UAMS-Web/uams-statamic-archive#2832);
        // these two boot the app, which is safe once that stale cache is gone.
        runPhp(['artisan', 'optimize:clear'], worktreePath)
        runPhp(['artisan', 'statamic:stache:clear'], worktreePath)
    }

    // --- run jobs -----------------------------------------------------------

    const results = new Map()

    // Each runner reproduces one ci.yml job's final run step(s) and returns its exit
    // code (the parity-critical surface mirrored from ci.yml). Ordering and gating are
    // the CHAIN loop's job below, not the runner's.
    const runners = {
        isolation: () => {
            // PARITY: the test-isolation-guard job's "Run test isolation guard" step.
            // Dependency-free PHP scan of tests/ (UAMS-Web/uams-statamic-archive#1149) — only the resolved `php`, no
            // composer install or framework. First (fastest) link in the chain.
            step('JOB isolation - parallel-safety test guard')
            const guard = runPhp(['scripts/check-parallel-safe-tests.php'], worktreePath)
            if (guard !== 0) {
                return guard
            }
            // PARITY: the same job's "Run global test symbol check" step: the shared
            // pest-testing script that replaced the guard's Rules C and D (UAMS-Web/uams-statamic-archive#3112).
            return runPhp(['.claude/skills/pest-testing/scripts/check-global-test-symbols.php', 'tests'], worktreePath)
        },
        validate: () => {
            // PARITY: the composer-validate job's "Run composer validate" step in
            // ci.yml. Catches a `composer.json` edit that left composer.lock's
            // content hash stale (UAMS-Web/uams-statamic-archive#1726, UAMS-Web/uams-statamic-archive#1728) — harmless to resolution, but it
            // warns on every `composer install` on every machine until fixed.
            // `--no-check-publish --no-check-all` scopes it to an application
            // rather than a published package. The flags do not change pass/fail
            // (bare `composer validate` also exits 0 clean, 2 stale — warnings
            // only fail under `--strict`); they keep the output to the signal,
            // dropping advice against this repo's deliberate exact-version pins
            // and its absent `license`. `--strict` is deliberately not used: it
            // reds a clean checkout even with both flags.
            // Reads composer.json + composer.lock only — no install, no vendor/,
            // no network or private-package auth (verified with the network off).
            step('JOB validate - composer validate')
            return run(composer, ['validate', '--no-check-publish', '--no-check-all'], worktreePath)
        },
        'no-emoji': () => {
            // PARITY: the no-emoji-guard job's "Run no-emoji guard" step in ci.yml.
            // A pure file scan over the paths .claude/rules/no-emoji-in-durable-records.md
            // names, so it runs ahead of encoding, which is the first job that installs
            // anything. Node-only, no PHP, no content, no vendor/ (UAMS-Web/uams-statamic-archive#2234).
            step('JOB no-emoji - durable-record glyph guard')
            return run(node, ['scripts/check-no-emoji.mjs'], worktreePath)
        },
        'content-pointer': () => {
            // PARITY: the content-pointer-guard job's "Run content pointer guard" step in
            // ci.yml. Refuses a submodule gitlink that names a commit not on its
            // repository's `main` (UAMS-Web/uams-statamic-archive#2965), for every submodule .gitmodules declares
            // (UAMS-Web/uams-statamic-archive#3115). An unchanged pointer passes on two `ls-tree` reads, so the
            // ordinary branch pays nothing; a moved one costs a fresh bare clone of its
            // repository, about two seconds for content. Exit 1 is "not on main", 3 is
            // "not present at all", 2 is "could not run", and all three fail the job.
            //
            // The base is the remote-tracking ref, resolved the same way as the pint job's,
            // so a local `main` that lags origin cannot make an old pointer look moved.
            // The clone authenticates the way the pest job's submodule init does: the
            // Composer GitHub PAT as an insteadOf rewrite, passed through git's
            // GIT_CONFIG_* environment so it never lands on a command line, or the git
            // credential helper when there is no token.
            const remoteBase = `origin/${opts.baseRef}`
            const base = capture(git, ['-C', worktreePath, 'rev-parse', '--verify', '--quiet', `${remoteBase}^{commit}`], repoRoot, { ignoreStderr: true })
                ? remoteBase
                : opts.baseRef
            const token = capture(composer, ['config', '--global', '--auth', 'github-oauth.github.com'], repoRoot, { ignoreStderr: true })
            const auth = token
                ? {
                    GIT_CONFIG_COUNT: '1',
                    GIT_CONFIG_KEY_0: `url.https://x-access-token:${token}@github.com/.insteadOf`,
                    GIT_CONFIG_VALUE_0: 'https://github.com/',
                }
                : undefined
            step(`JOB content-pointer - submodule pointers vs ${base}`)
            return run(node, ['scripts/check-content-pointer.mjs', '--base', base], worktreePath, auth)
        },
        encoding: () => {
            // PARITY: the encoding-guard job's "Run encoding guard" step in ci.yml.
            step('JOB encoding - cp1252 double-encoding guard')
            const guard = run(node, ['vendor/joshdaugherty/ipa-unicode-inventory/scripts/check-double-encoding.mjs'], worktreePath)

            // `run()` is spawnSync, so this stays synchronous and the job keeps
            // returning a number rather than a promise.
            if (guard !== 0) {
                return guard
            }

            // PARITY: the encoding-guard job's "Run decision-log order guard" step in
            // ci.yml. Lives here rather than in a thirteenth job (UAMS-Web/uams-statamic-archive#2236): it reads one
            // tracked file, needs no install, and belongs beside the other
            // file-content guard.
            step('JOB encoding - decision-log order guard')
            const order = run(node, ['scripts/check-decision-log-order.mjs'], worktreePath)

            if (order !== 0) {
                return order
            }

            // PARITY: the encoding-guard job's "Run mutate-ignore auditor" step in ci.yml.
            // Reports `@pest-mutate-ignore` annotations that suppress nothing (UAMS-Web/uams-statamic-archive#2316). An
            // inert one is invisible by construction — the mutant keeps surviving, which
            // reads as a wrong reason rather than as an annotation that never applied.
            //
            // Here rather than in a fourteenth job, extending UAMS-Web/uams-statamic-archive#2236's reasoning rather than
            // contradicting it: that note declined a job for a guard needing no install,
            // while this one DOES read `vendor/pestphp/pest-plugin-mutate` for the mutator
            // names it validates against. This job has already installed, so folding it in
            // costs nothing; a separate job would repeat the whole install preamble for a
            // sub-second script. Third of thirteen in a cost-ordered chain, so it still
            // fails ahead of spell, js, build, pint, rector, mutate, phpstan and pest.
            //
            // Returned as-is so ANY non-zero fails: the auditor exits 2 when it cannot run
            // at all, and passing that would restore the silent false-clean it exists to
            // close. Roots are named explicitly to match ci.yml rather than relying on the
            // script's default, so the two cannot drift apart unnoticed.
            step('JOB encoding - mutate-ignore auditor')
            const mutateIgnore = runPhp(['scripts/check-mutate-ignore.php', 'app', 'tests'], worktreePath)

            if (mutateIgnore !== 0) {
                return mutateIgnore
            }

            // PARITY: the encoding-guard job's "Run mutate-ignore breadth check" step in
            // ci.yml. Counts how many mutants each `@pest-mutate-ignore` annotation removes
            // (UAMS-Web/uams-statamic-archive#3160), which the auditor above cannot see, and fails on one that removes more
            // than one mutant of a named mutator or none at all, unless the committed baseline
            // lists it, and on a baseline entry that no longer occurs. Returned as-is so the
            // exit 2 it gives when it cannot run fails the job as well.
            step('JOB encoding - mutate-ignore breadth check')
            const mutateIgnoreBreadth = runPhp([
                'scripts/check-mutate-ignore-breadth.php',
                '--baseline=scripts/check-mutate-ignore-breadth.baseline.json',
                'app',
                'tests',
            ], worktreePath)

            if (mutateIgnoreBreadth !== 0) {
                return mutateIgnoreBreadth
            }

            // PARITY: the encoding-guard job's "Run lone-CR guard" step in ci.yml. Rejects a
            // raw 0x0D that is not part of a CRLF pair (UAMS-Web/uams-statamic-archive#2495). `eol=lf` normalizes CRLF and
            // does not see a lone CR; Pint passes it through untouched; so this class reaches
            // `main` past both. It belongs in THIS job rather than a fourteenth by the same
            // UAMS-Web/uams-statamic-archive#2236 reasoning as its two siblings above: it reads tracked files, needs no
            // install, and is a file-content guard.
            //
            // Returned as-is so ANY non-zero fails, including the exit 2 it uses when the
            // scan could not run at all — an empty target set is indistinguishable from a
            // clean tree, which is the failure the guard itself is about.
            step('JOB encoding - lone-CR guard')
            const loneCr = run(node, ['scripts/check-lone-cr.mjs'], worktreePath)

            if (loneCr !== 0) {
                return loneCr
            }

            // PARITY: the encoding-guard job's "Run release-notes generator suite" step in
            // ci.yml. The shared `writing-release-notes` skill ships its own unittest suite
            // beside the generator, and nothing ran it, so release-notes coverage rested on
            // repo-local Pest guards written against an interface the shared script no longer
            // has (UAMS-Web/uams-statamic-archive#2850). Here rather than in a fourteenth job by UAMS-Web/uams-statamic-archive#2236's reasoning: offline,
            // no install, no content, and about a second.
            //
            // Returned as-is so ANY non-zero fails: unittest exits 1 on a failure or an error.
            step('JOB encoding - release-notes generator suite')
            return run(python, ['.claude/skills/writing-release-notes/gen_release_notes.test.py'], worktreePath)
        },
        spell: () => {
            // PARITY: the spell job's "Run cspell" step in ci.yml. Blocking (UAMS-Web/uams-statamic-archive#1541): a
            // spelling hit fails, skipping every more-expensive job after it in the
            // chain. Node-only (the cspell CLI), no PHP or content.
            step('JOB spell - npm run spell')
            return run(npm, ['run', 'spell'], worktreePath)
        },
        js: () => {
            // PARITY: the js job's "Run JS unit tests" step in ci.yml.
            step('JOB js - vitest run')
            return run(npm, ['run', 'test:js'], worktreePath)
        },
        build: () => {
            // PARITY: the build job's "Build site assets" + "Build CP assets" steps.
            step('JOB build - npm run build')
            const b1 = run(npm, ['run', 'build'], worktreePath)
            step('JOB build - npm run cp:build')
            const b2 = run(npm, ['run', 'cp:build'], worktreePath)
            return b1 !== 0 ? b1 : b2
        },
        pint: () => {
            // PARITY: the pint job's "Determine changed PHP files" + "Run Pint on
            // changed files" steps in ci.yml.
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
            const remoteBase = `origin/${opts.baseRef}`
            const diffBase = capture(git, ['-C', worktreePath, 'rev-parse', '--verify', '--quiet', `${remoteBase}^{commit}`], repoRoot, { ignoreStderr: true })
                ? remoteBase
                : opts.baseRef
            step(`JOB pint - diffing .php files vs ${diffBase}`)
            const changed = capture(git, ['-C', worktreePath, 'diff', '--name-only', '--diff-filter=ACMR', `${diffBase}...HEAD`, '--', '*.php'], repoRoot)
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
                const code = runPhp(['vendor/bin/pint', '--test', ...chunk], worktreePath)
                if (code !== 0) {
                    pintCode = code
                }
            }
            return pintCode
        },
        rector: () => {
            // PARITY: the rector job's "Run Rector (gating profile, dry-run)" step in
            // ci.yml. Runs the DEFAULT profile — the gating one — so this asserts the
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
                return runPhp(['vendor/bin/rector', '--dry-run', '--no-progress-bar'], worktreePath, {
                    TMPDIR: rectorTmp,
                    TMP: rectorTmp,
                    TEMP: rectorTmp,
                })
            } finally {
                // Best-effort cleanup; a leftover temp dir is harmless.
                rmSync(rectorTmp, { recursive: true, force: true })
            }
        },
        mutate: () => {
            // PARITY: the mutate job's "Run the mutation-tooling smoke" step in ci.yml.
            //
            // This gates the mutation TOOLING, not the mutants. UAMS-Web/uams-statamic-archive#1728 was not a mutant
            // escaping a weak test — it was `pest-plugin-mutate` silently reporting
            // `0 Mutations for 0 Files created` after a `php-code-coverage` bump, which
            // Pest exits 0 on. A gate that only checked the exit code would have passed
            // straight through the exact failure it exists to catch, so the mutation
            // COUNT and the SCORE are both asserted below.
            //
            // `--covered-only` and the narrow `--path`/`--class` scoping keep this at a
            // few seconds. A diff-scoped mutation run over the actually-changed classes
            // is the theoretically right answer and stays unbuilt: it needs a reliable
            // changed-file-to-class mapping and costs minutes rather than seconds (UAMS-Web/uams-statamic-archive#1731).
            // Real mutation runs remain author-side, per the adversarial-review rule.
            step('JOB mutate - mutation-tooling smoke')

            // Refuse before Pest when no coverage driver is active (UAMS-Web/uams-statamic-archive#3213). Without
            // one Pest stops with its own InvalidOption, which the summary parse below
            // would report as a run that did not report: a workstation condition read
            // as a branch defect. The probe costs milliseconds and names the cause.
            const driver = capturePhp(['-r', COVERAGE_DRIVER_PROBE], worktreePath)
            if (driver.trim() === 'none') {
                console.error(red(mutateSmokeVerdict(driver, null).message))
                return 1
            }

            const output = capturePhp(
                [
                    'vendor/bin/pest',
                    MUTATE_SMOKE_TEST,
                    '--mutate',
                    `--path=${MUTATE_SMOKE_PATH}`,
                    `--class=${MUTATE_SMOKE_CLASS}`,
                    '--covered-only',
                ],
                worktreePath,
            )

            // Echo the runner's own output; capture() pipes stdout away from the console.
            console.log(output)

            // Strip ANSI before parsing: these lines are color-wrapped, and an
            // escape-anchored match reads as a clean miss rather than a parse failure.
            const verdict = mutateSmokeVerdict(driver, output.replace(/\x1b\[[0-9;]*m/g, ''))
            if (!verdict.ok) {
                console.error(red(verdict.message))
                return 1
            }

            return 0
        },
        phpstan: () => {
            // PARITY: the phpstan job's "Run Larastan" step in ci.yml. `composer stan`
            // runs `phpstan analyse --no-progress --memory-limit=2G` (pinned in
            // composer.json so local and CI match). It boots the app (relies on the
            // framework prep above) and reads app/ only, so no submodule is needed.
            step('JOB phpstan - composer stan')
            return run(composer, ['stan'], worktreePath)
        },
        pest: () => {
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
            // PARITY: mirrored by the "Check the suite left content/, users/ and resources/forms/ clean"
            // step in ci.yml.
            const submoduleStatus = (name) => {
                if (!existsSync(join(worktreePath, name, '.git'))) {
                    return null
                }
                // captureResult for the same reason UAMS-Web/uams-statamic-archive#1889 uses it: a `git` that failed
                // printing nothing is otherwise indistinguishable from a clean tree,
                // and a check that silently verifies nothing is the bug it guards.
                const probe = captureResult(
                    git,
                    ['-C', join(worktreePath, name), 'status', '--porcelain', '-z', '--untracked-files=all'],
                    repoRoot,
                )
                if (probe.status !== 0) {
                    throw new Error(
                        `\`git status\` in ${join(worktreePath, name)} exited ${probe.status}, so the suite's `
                        + `effect on the ${name} submodule could not be checked.`,
                    )
                }
                return worktreeDriftEntries(probe.stdout)
            }

            const litterBefore = Object.fromEntries(GATE_SUBMODULES.map((name) => [name, submoduleStatus(name)]))

            // PARITY: the pest job's two run steps in ci.yml — the parallel pass
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
            // PARITY: mirrors the "Build Stache seed (testing env)" step in ci.yml.
            step('JOB pest - build Stache seed (testing env)')
            const seedCode = runPhp(['artisan', 'test:seed-stache'], worktreePath, { APP_ENV: 'testing', DB_DATABASE: ':memory:' })
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
            // PARITY: mirrors the "Build route cache (testing env)" + "Clear route
            // cache" steps in the pest job in ci.yml.
            step('JOB pest - build route cache (testing env)')
            const cacheCode = runPhp(['artisan', 'route:cache'], worktreePath, { APP_ENV: 'testing' })
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
            // PARITY: mirrors the pest job's note in ci.yml.
            // --log-junit feeds the skip-baseline check below; it changes nothing
            // about what runs.
            // PARITY: mirrors the "Run Pest (parallel...)" step in ci.yml.
            step('JOB pest - parallel suite (excluding serial group)')
            const parallelReport = join(worktreePath, 'junit-parallel.xml')
            const serialReport = join(worktreePath, 'junit-serial.xml')
            // The runner reuses its worktree between runs, so a report left by the
            // previous one would let a band that died before writing its own read
            // as verified against stale results — the exact silence this check
            // removes. CI checks out fresh, so it has nothing to mirror here.
            rmSync(parallelReport, { force: true })
            rmSync(serialReport, { force: true })
            const parallelCode = runPhp(['-d', 'pcov.enabled=0', 'vendor/bin/pest', '--parallel', '--exclude-group=serial', `--passthru-php=-d memory_limit=${PHP_MEMORY_LIMIT}`, '--display-all-issues', '--log-junit', parallelReport], worktreePath)
            step('JOB pest - serial tail (group=serial)')
            const serialCode = runPhp(['-d', 'pcov.enabled=0', 'vendor/bin/pest', '--group=serial', '--display-all-issues', '--log-junit', serialReport], worktreePath)

            // UAMS-Web/uams-statamic-archive#1744 — a band that silently skips tests would otherwise report PASS
            // having tested less than it should have. Only meaningful when the band
            // itself ran; a band that failed already fails the job, and its report
            // would be incomplete.
            // PARITY: mirrors the "Check skip baseline" steps in ci.yml.
            //
            // The guard lives in the *worktree*, i.e. in the branch under test, so a
            // branch predating UAMS-Web/uams-statamic-archive#1744 does not carry it. The runner validates arbitrary
            // branches (including older ones), so a missing guard is skipped with a
            // notice rather than failing a branch for not having been written yet.
            let baselineCode = 0
            const skipGuard = join(worktreePath, 'scripts', 'check-skip-baseline.php')
            if (!existsSync(skipGuard)) {
                console.log('Skip baseline: guard not present on this branch (pre-#1744) — not checked.')
            } else {
                if (parallelCode === 0) {
                    step('JOB pest - skip baseline (parallel band)')
                    baselineCode = runPhp(['scripts/check-skip-baseline.php', `--report=${parallelReport}`, '--band=parallel'], worktreePath)
                }
                if (baselineCode === 0 && serialCode === 0) {
                    step('JOB pest - skip baseline (serial band)')
                    baselineCode = runPhp(['scripts/check-skip-baseline.php', `--report=${serialReport}`, '--band=serial'], worktreePath)
                }
            }

            // UAMS-Web/uams-statamic-archive#1890 — nothing pinned what the parallel band COSTS, so a change that
            // reclaimed it passed in silence: the UAMS-Web/uams-statamic-archive#1348 route-count tax (UAMS-Web/uams-statamic-archive#1521 →
            // UAMS-Web/uams-statamic-archive#1573) and the class that grew to 1085.3s of a 1306s band (UAMS-Web/uams-statamic-archive#1797) were
            // both found by hand, long after the fact. This reads the same reports
            // the skip guard does, so it needs no extra run. It judges the costliest
            // class by its share of the band's summed class time, which a loaded
            // host inflates uniformly and so cannot move (UAMS-Web/uams-statamic-archive#2530).
            // PARITY: mirrors the "Check slowest class" steps in ci.yml.
            //
            // Absent-on-older-branches, for the same reason as the skip guard above:
            // this runner validates arbitrary branches, and one predating UAMS-Web/uams-statamic-archive#1890 is
            // passed with a notice rather than failed for not carrying it yet.
            let slowestCode = 0
            const slowestGuard = join(worktreePath, 'scripts', 'check-slowest-class.php')
            if (!existsSync(slowestGuard)) {
                console.log('Slowest class: guard not present on this branch (pre-#1890) — not checked.')
            } else {
                if (parallelCode === 0) {
                    step('JOB pest - slowest class (parallel band)')
                    slowestCode = runPhp(['scripts/check-slowest-class.php', `--report=${parallelReport}`, '--band=parallel'], worktreePath)
                }
                if (slowestCode === 0 && serialCode === 0) {
                    step('JOB pest - slowest class (serial band)')
                    slowestCode = runPhp(['scripts/check-slowest-class.php', `--report=${serialReport}`, '--band=serial'], worktreePath)
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
            // PARITY: mirrors the "Check the content mirror band lock was released"
            // step in ci.yml.
            //
            // Absent-on-older-branches, as for the guards above.
            let bandLockCode = 0
            const bandLockGuard = join(worktreePath, 'scripts', 'check-band-lock-released.php')
            if (!existsSync(bandLockGuard)) {
                console.log('Band lock: guard not present on this branch (pre-#3018) - not checked.')
            } else {
                step('JOB pest - content mirror band lock released')
                bandLockCode = runPhp(['scripts/check-band-lock-released.php'], worktreePath)
            }

            step('JOB pest - clear route cache')
            runPhp(['artisan', 'route:clear'], worktreePath)

            // UAMS-Web/uams-statamic-archive#1873 — did the suite write into a live gate submodule?
            // Unconditional: a band that FAILED is exactly the case where cleanup
            // did not run, so suppressing the check on failure would hide the
            // litter in the one run most likely to have produced it.
            // PARITY: mirrored by the "Check the suite left content/, users/ and resources/forms/ clean"
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
                        + `  Restore the submodule with: git -C ${join(worktreePath, name)} checkout -- .`,
                    )
                } else {
                    // Say so out loud: a check whose only output is silence cannot be
                    // told apart from one that never ran.
                    console.log(`${name}/ litter: none — the suite left the submodule as it found it.`)
                }
            }

            return cacheCode !== 0 ? cacheCode : (seedCode !== 0 ? seedCode : (parallelCode !== 0 ? parallelCode : (serialCode !== 0 ? serialCode : (baselineCode !== 0 ? baselineCode : (slowestCode !== 0 ? slowestCode : (bandLockCode !== 0 ? bandLockCode : litterCode))))))
        },
        browser: () => {
            // `--with-deps` (used in ci.yml on the Linux runner) is omitted here: it
            // only installs Linux system libraries and is a no-op on macOS and Windows.
            step('JOB browser - installing Playwright Chromium')
            run(npx, ['playwright', 'install', 'chromium'], worktreePath)

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
            // Skip only when `build` actually ran AND exited 0 in THIS invocation.
            // `results` holds only jobs already executed this run, so a `--jobs
            // browser`-only run (or any selection omitting `build`) reads undefined
            // and still builds — which is what keeps a standalone browser run honest.
            // A `build` that FAILED cannot reach here at all: the chain marks the run
            // broken and skips every later job, browser included.
            const reusePriorBuild = canReuseBuildOutput(results)
            let bf = 0
            let bc = 0

            if (reusePriorBuild) {
                step("JOB browser - reusing this run's `build` output (npm run build + cp:build already done in this worktree)")
            } else {
                step('JOB browser - npm run build')
                bf = run(npm, ['run', 'build'], worktreePath)
                step('JOB browser - npm run cp:build')
                bc = run(npm, ['run', 'cp:build'], worktreePath)
            }

            // PARITY: the browser job's "Run CP browser smokes" step in ci.yml.
            // tests/Browser holds the CP smokes and the axe-core accessibility gate (UAMS-Web/uams-statamic-archive#1046).
            step('JOB browser - pest tests/Browser')
            const bt = runPhp(['vendor/bin/pest', 'tests/Browser'], worktreePath)
            return bf !== 0 ? bf : (bc !== 0 ? bc : bt)
        },
    }

    // The chain order itself is the module-scoped CHAIN above (exported so the
    // parity test can hold its SET against the jobs ci.yml declares; since #373
    // ci.yml encodes no order of its own).
    let brokenBy = null
    for (const job of CHAIN) {
        if (!jobs.includes(job)) {
            continue
        }
        if (brokenBy) {
            step(`JOB ${job} - SKIPPED (chain gated: ${brokenBy} failed earlier)`)
            results.set(job, 'skipped')
            continue
        }
        const code = runners[job]()
        results.set(job, code)
        if (typeof code === 'number' && code !== 0) {
            brokenBy = job
        }
    }

    // --- summary ------------------------------------------------------------

    console.log('')

// --- commit status (UAMS-Web/uams-statamic-archive#2323) ------------------------------------------------
//
// WHY THIS EXISTS. A pull request handed back red by this gate was, on every
// surface GitHub displays, indistinguishable from one that had passed:
// `draft=false`, `mergeable=true`, `mergeable_state=clean`, no labels, and
// `combined_state=pending` across twelve skipped remote workflows. The verdict
// lived only in an issue comment. Two readers concluded UAMS-Web/uams-statamic-archive#2168 was ready to
// merge while it was red, and the fix waited hours on a hand-back nobody could
// see.
//
// `pending` is the half that actively misleads rather than merely omitting: it
// reads as a run still in flight whose result has not arrived, so a reader
// waits for something that is never coming. A real status on the head replaces
// that reading rather than adding to it.
//
// ANNOTATION, NOT MUTATION. The gate writes a status; it does not change the
// pull request's state. Flipping a red PR to draft was considered on UAMS-Web/uams-statamic-archive#2323 and
// deliberately excluded — that widens the gate's remit and is not needed to
// make a verdict visible.
//
// OFF BY DEFAULT. `--commit-status` is opt-in because this same runner is what
// a developer invokes on their own branch, and a local check should not write
// to GitHub. The gate wrapper passes it; a person running the chain by hand
// does not.
//
// NEVER FAILS THE RUN. The chain's verdict is the chain's, not GitHub's. A
// status that cannot be posted — no `gh`, no network, no permission, a branch
// with no remote — warns and returns. Letting it change the exit code would
// make an annotation able to red a green run, which is the inverse of the
// defect this exists to fix.
function postCommitStatus(repoRoot, branch, failed, results, phpVersion) {
    const gh = which('gh')

    if (!gh) {
        warn('commit status skipped: `gh` was not found on PATH.')
        return
    }

    // The status belongs on the PULL REQUEST'S HEAD, not on the merged state
    // this run validated — that merge commit exists only in the CI worktree and
    // is not a commit anyone can see.
    const sha = capture(git, ['-C', repoRoot, 'rev-parse', '--verify', `refs/remotes/origin/${branch}`], repoRoot, { ignoreStderr: true })

    if (!sha) {
        warn(`commit status skipped: origin/${branch} does not resolve, so there is no head to annotate.`)
        return
    }

    const description = commitStatusDescription(failed, results, phpVersion)

    const res = spawnSync(gh, [
        'api', '-X', 'POST', `repos/{owner}/{repo}/statuses/${sha}`,
        '-f', `state=${failed ? 'failure' : 'success'}`,
        '-f', 'context=local-ci',
        // GitHub truncates a description past 140 characters.
        '-f', `description=${description.slice(0, 140)}`,
    ], { cwd: repoRoot, encoding: 'utf8' })

    if (res.status !== 0) {
        warn(`commit status not posted (gh exit ${res.status ?? 'unknown'}): ${(res.stderr || '').trim().split('\n')[0]}`)
        return
    }

    console.log(`Commit status: ${failed ? 'failure' : 'success'} on ${sha.slice(0, 9)} (context local-ci)`)
}

    console.log('================ CI summary ================')
    // UAMS-Web/uams-statamic-archive#1882 — a summary that does not say WHICH runner defined these jobs can be
    // quoted as "the chain passed" when the chain was the base branch's.
    console.log(`  Runner:   ${runnerProvenance}`)
    // UAMS-Web/uams-statamic-archive#3235: each band beside the interpreter that ran it, so a figure quoted from this
    // block can be told apart from a toolchain move without the header that named PHP.
    console.log(`  PHP:      ${php} (${phpVersion})`)
    const bands = pestBandLines(worktreePath, results, phpVersion)
    for (const line of bands) {
        console.log(`  ${line}`)
    }
    console.log('--------------------------------------------')
    let failed = false
    for (const [job, code] of results) {
        if (code === 'skipped') {
            console.log(`  ${job.padEnd(9)} ${yellow('SKIP (gated — an earlier job failed)')}`)
        } else if (code === 0) {
            console.log(`  ${job.padEnd(9)} ${green('PASS')}`)
        } else {
            console.log(`  ${job.padEnd(9)} ${red(`FAIL (exit ${code})`)}`)
            failed = true
        }
    }
    console.log('============================================')

    if (opts.commitStatus) {
        postCommitStatus(repoRoot, opts.branch, failed, results, phpVersion)
    }

    if (failed) {
        process.exit(1)
    }
    console.log(green('All selected jobs passed.'))
    process.exit(0)
}

// Only run the CI when invoked directly (`node scripts/local-ci.mjs`), not when
// this module is imported — e.g. by the unit test that exercises the pure
// helpers above. process.argv[1] is the entry script; comparing its file URL to
// import.meta.url distinguishes the two cross-platform.
if (import.meta.url === pathToFileURL(process.argv[1]).href) {
    try {
        main()
    } catch (err) {
        console.error(red(`\nlocal-ci: ${err.message}`))
        process.exit(1)
    }
}
