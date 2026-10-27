import { describe, it, expect } from 'vitest'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
    ALL_JOBS,
    CHAIN,
    RUNNER_PINNED_ENV,
    RUNNER_PINNED_FLAG,
    driftLabel,
    handoffArgv,
    parseArgs,
    runnerHandoff,
    unknownJobs,
    worktreeDriftEntries,
} from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#1735 — `scripts/local-ci.mjs` is a HAND-maintained mirror of
// `.github/workflows/ci.yml`, and the cost-ordered chain is the part of it that
// silently rots: edit one file's `needs:` graph and the other keeps running the
// old order, so a locally-green branch is gated differently than it would be on
// CI. The `.githooks/pre-commit` hook only catches staging one file without the
// other — it cannot see whether the two agree. These assert that they do.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const workflow = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')

// ci.yml job id -> the `--jobs` key local-ci.mjs addresses that job by. Every job
// in the workflow must appear here or in REMOTE_ONLY below; an unmapped id fails the
// test, which is the point — a job added to CI has to be mirrored, not silently ignored.
const JOB_KEYS = {
    'test-isolation-guard': 'isolation',
    'composer-validate': 'validate',
    'no-emoji-guard': 'no-emoji',
    'content-pointer-guard': 'content-pointer',
    'encoding-guard': 'encoding',
    spell: 'spell',
    js: 'js',
    build: 'build',
    pint: 'pint',
    rector: 'rector',
    mutate: 'mutate',
    phpstan: 'phpstan',
    pest: 'pest',
    browser: 'browser',
}

// ci.yml jobs with deliberately no local counterpart, each outside the chain. Kept as an
// explicit list so a new job still has to be mirrored or named here: an unlisted one fails
// the stranger check below. `submodule-access` proves a GitHub-only secret on manual runs
// (UAMS-Web/uams-statamic-archive#2497). `synced-files` runs the shared synced-files check (UAMS-Web/uams-statamic-archive#3084); the local runner
// gains its own job with #2837. The first case in the parity block below pins that each
// stays outside the chain.
const REMOTE_ONLY = new Set(['submodule-access', 'synced-files'])

/**
 * Read the `jobs:` ids and their `needs:` edges out of ci.yml.
 *
 * Deliberately a small scanner rather than a YAML dependency: the workflow's job
 * headers and `needs:` lines sit at fixed indents, and the repo has no YAML
 * parser in devDependencies. Parsing starts at the top-level `jobs:` key because
 * `on:` also carries 2-space children (`pull_request:`, `push:`), which would
 * otherwise read as jobs. An unrecognized `needs:` form throws rather than being
 * skipped, so the test can never pass by failing to understand the file.
 */
function parseWorkflowJobs(yaml) {
    const lines = yaml.split(/\r?\n/)
    const start = lines.indexOf('jobs:')
    if (start === -1) {
        throw new Error('ci.yml has no top-level `jobs:` key.')
    }

    const jobs = new Map()
    let current = null

    for (const line of lines.slice(start + 1)) {
        // A non-indented, non-comment, non-blank line ends the `jobs:` block.
        if (/^\S/.test(line) && !line.startsWith('#')) {
            break
        }

        const header = line.match(/^ {2}([A-Za-z0-9_-]+):\s*$/)
        if (header) {
            current = header[1]
            jobs.set(current, { needs: null })
            continue
        }

        const needs = line.match(/^ {4}needs:(.*)$/)
        if (needs && current) {
            const value = needs[1].trim()
            // A bare `needs:` is the YAML block-sequence form, whose entries are on
            // the following lines. Throwing keeps the docblock's promise honest —
            // skipping it would leave the job looking like a second chain head.
            if (value === '') {
                throw new Error(`Block-sequence \`needs:\` on job ${current} is not supported by this scanner.`)
            }
            const list = value.startsWith('[')
                ? value.replace(/^\[|\]$/g, '').split(',').map((n) => n.trim()).filter(Boolean)
                : [value]
            if (list.length === 0 || list.some((n) => !/^[A-Za-z0-9_-]+$/.test(n))) {
                throw new Error(`Unparsed \`needs:\` form on job ${current}: ${value}`)
            }
            jobs.get(current).needs = list
        }
    }

    return jobs
}

/**
 * Flatten the `needs:` graph into the sequential order it encodes.
 *
 * The chain is a strict linked list by design (docs/DECISIONS.md, 2026-07-15):
 * exactly one head with no `needs:`, every other job depending on exactly one
 * predecessor, and no job depended on twice. Each of those is asserted here —
 * a fan-out would mean the chain stopped being sequential, which is a decision
 * to make deliberately, not to discover through a passing parity test.
 */
function linearize(jobs) {
    const heads = [...jobs].filter(([, job]) => job.needs === null).map(([id]) => id)
    expect(heads).toHaveLength(1)

    const nextOf = new Map()
    for (const [id, job] of jobs) {
        if (job.needs === null) {
            continue
        }
        expect(job.needs).toHaveLength(1)
        const parent = job.needs[0]
        expect(jobs.has(parent)).toBe(true)
        expect(nextOf.has(parent)).toBe(false)
        nextOf.set(parent, id)
    }

    const order = []
    for (let id = heads[0]; id !== undefined; id = nextOf.get(id)) {
        order.push(id)
    }
    expect(order).toHaveLength(jobs.size)

    return order
}

describe('ci.yml <-> local-ci.mjs chain parity', () => {
    const allJobs = parseWorkflowJobs(workflow)
    const jobs = new Map([...allJobs].filter(([id]) => !REMOTE_ONLY.has(id)))

    it('keeps every remote-only job in ci.yml and outside the chain', () => {
        // Each listed job must exist, so a rename cannot leave a stale exemption, and must
        // neither need a job nor be needed by one. A remote-only job joined to the chain
        // would gate or be gated by jobs local-ci runs while local-ci never runs it.
        for (const id of REMOTE_ONLY) {
            expect(allJobs.has(id)).toBe(true)
            expect(allJobs.get(id).needs).toBeNull()
        }
        for (const [, job] of allJobs) {
            for (const parent of job.needs ?? []) {
                expect(REMOTE_ONLY.has(parent)).toBe(false)
            }
        }
    })

    it('finds every job the mirror knows about, and no stranger', () => {
        expect([...jobs.keys()].sort()).toEqual(Object.keys(JOB_KEYS).sort())
    })

    it('runs local-ci jobs in the exact order ci.yml `needs:` encodes', () => {
        expect(linearize(jobs).map((id) => JOB_KEYS[id])).toEqual(CHAIN)
    })

    it('addresses every chained job by a valid `--jobs` key', () => {
        // Since UAMS-Web/uams-statamic-archive#1727 there is no opt-in job: the chain and the `all` expansion
        // hold exactly the same set, so a default run cannot silently omit a job.
        expect([...CHAIN].sort()).toEqual([...ALL_JOBS].sort())
    })

    it('keeps `browser` in the default `all` set, running last (#1727)', () => {
        // The regression this pins: four CP smokes sat red on `main` for eleven
        // days because tests/Browser ran only when a human remembered to ask for
        // it. Dropping `browser` back out of ALL_JOBS restores that blind spot,
        // and every other assertion in this file would still pass — the set-
        // equality check above compares CHAIN against whatever ALL_JOBS happens
        // to be, so removing it from BOTH stays green. This names it explicitly.
        expect(ALL_JOBS).toContain('browser')
        expect(CHAIN.at(-1)).toBe('browser')
    })

    it('implements a runner for every job it chains', () => {
        // Name-level parity is not enough: a job present in both files' chains but
        // missing its runner passes every other assertion here and then dies at
        // dispatch on `runners[job]()` being undefined. Asserted against the source
        // text because `runners` is built inside `main()` and cannot be imported.
        const script = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')
        // The key may be quoted: a hyphenated job name is not a bare identifier, so
        // `no-emoji` is written `'no-emoji': () => {`. Matching only bare keys would
        // silently drop it from `implemented` and report a missing runner that exists.
        const implemented = [...script.matchAll(/^ {8}'?([a-z][a-z-]*)'?: \(\) => \{$/gm)].map((m) => m[1])
        expect([...CHAIN].sort()).toEqual([...implemented].sort())
    })

    it('gates composer.lock freshness before any job that installs anything', () => {
        // The saving that justifies the extra link: `encoding` is the first job to
        // run `composer install`, and everything after it is costlier still.
        expect(CHAIN.indexOf('validate')).toBeGreaterThan(-1)
        expect(CHAIN.indexOf('validate')).toBeLessThan(CHAIN.indexOf('encoding'))
    })

    it('hands the run to the branch when its runner defines the chain differently', () => {
        // The UAMS-Web/uams-statamic-archive#1882 case, in miniature: `main`'s runner has no `mutate` job and
        // the branch's does. Comparing SOURCE is what notices — nothing else in
        // the run can, because the executing copy is the only one parsed.
        const base = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')
        const branch = base.replace("'rector', 'mutate'", "'rector', 'mutate', 'boom'")
        expect(branch).not.toBe(base)

        expect(runnerHandoff({ pinned: false, executingSource: base, branchSource: branch }).action).toBe('handoff')
    })

    it('runs in place when the branch runner is identical, absent, or already pinned', () => {
        const base = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')

        // Identical — the overwhelmingly common case, and the one that must cost
        // nothing: no re-exec means no second checkout and no second install.
        expect(runnerHandoff({ pinned: false, executingSource: base, branchSource: base }).action).toBe('run')

        // A branch that carries no runner at all has nothing to hand off TO.
        expect(runnerHandoff({ pinned: false, executingSource: base, branchSource: null }).action).toBe('run')

        // Line endings alone are not a difference in what the chain DOES, so a
        // checkout under a different core.autocrlf must not trigger a handoff.
        expect(runnerHandoff({
            pinned: false,
            executingSource: 'const CHAIN = []\nmain()\n',
            branchSource: 'const CHAIN = []\r\nmain()\r\n',
        }).action).toBe('run')
    })

    it('cannot recurse: a pinned run never hands off again, however different the sources', () => {
        // Recursion is the failure mode a self-re-exec invites, so the guard is
        // proved rather than asserted: feed the decision two deliberately DIFFERENT
        // sources — the one input that would otherwise hand off forever — with the
        // pin set, from either channel.
        expect(runnerHandoff({ pinned: true, executingSource: 'a', branchSource: 'b' }).action).toBe('run')
        expect(parseArgs(['--branch', 'b', RUNNER_PINNED_FLAG]).runnerPinned).toBe(true)
    })

    it('keeps the pin out of the forwarded argv, so a runner predating it still parses', () => {
        // The pin travels in RUNNER_PINNED_ENV precisely because the branch's copy
        // may be OLDER than this file. A pre-#1882 `parseArgs` has no case for the
        // flag, so its `default:` arm throws `Unknown option` — which would make
        // every branch cut before this change un-validatable the moment it merged.
        const argv = handoffArgv(['--branch', 'some-branch', '--jobs', 'all'], '/tmp/wt')
        expect(argv).not.toContain(RUNNER_PINNED_FLAG)

        // And the env channel is what the child actually reads — a name an older
        // parser cannot see at all.
        expect(RUNNER_PINNED_ENV).toBe('LOCAL_CI_RUNNER_PINNED')
        expect(readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8'))
            .toContain(`[RUNNER_PINNED_ENV]: '1'`)
    })

    it('forwards only flags the base branch\'s runner already parses', (context) => {
        // Asserted against the real base-branch source, not a hand-written stand-in.
        // A CI checkout fetches only the commit under test and creates no
        // `origin/main`, so there the base cannot be read at all (#375). That case
        // is a skip that names the missing ref, never a pass: with no base source
        // the loop below would have nothing to compare against.
        const base = readAtRef(repoRoot, 'origin/main', 'scripts/local-ci.mjs')
        if (base.unreachable !== null) {
            context.skip(base.unreachable)
        }

        const flags = handoffArgv(['--branch', 'some-branch', '--jobs', 'all'], '/tmp/wt')
            .filter((arg) => arg.startsWith('-'))
        expect(flags.length).toBeGreaterThan(0)
        for (const arg of flags) {
            // Every flag the handoff forwards must be one the BASE branch's parser
            // already knows, or that older runner dies before it validates anything.
            expect(base.source).toContain(`'${arg}'`)
        }
    })

    it('rejects an unknown job name even when `all` is also requested', () => {
        // `--jobs all,typo` expands to ALL_JOBS and drops `typo` on the way, so a
        // guard reading the EXPANSION returns clean and the chain reports every job
        // passing while the operator believes `typo` ran. The caller's own list is
        // what has to be checked.
        expect(unknownJobs(parseArgs(['--branch', 'b', '--jobs', 'all,typo']).jobs)).toEqual(['typo'])
        expect(unknownJobs(parseArgs(['--branch', 'b', '--jobs', 'all,browser']).jobs)).toEqual([])
    })

    it('forwards the caller\'s arguments but drops the ones this process already spent', () => {
        const argv = handoffArgv(
            ['--branch', 'b', '--fresh', '--fetch', '--jobs', 'pest', '-w', '/old/path', '--skip-install'],
            '/new/path',
        )

        // --fresh already rebuilt the worktree and --fetch already reconciled the
        // ref; repeating either would discard this process's work or silently
        // validate a newer commit than the one it announced.
        expect(argv).not.toContain('--fresh')
        expect(argv).not.toContain('--fetch')

        // Everything else the caller asked for survives, and exactly one worktree
        // path reaches the child — the resolved one.
        const opts = parseArgs(argv)
        expect(opts.branch).toBe('b')
        expect(opts.jobs).toEqual(['pest'])
        expect(opts.skipInstall).toBe(true)
        expect(opts.worktreePath).toBe('/new/path')
        expect(argv.filter((a) => a === '-w' || a === '--worktree-path')).toHaveLength(1)
    })

    it('carries an unrecognized job name past parsing so the branch can define it', () => {
        // A job that exists only on the branch must survive `--jobs` parsing here,
        // or the handoff can never reach it — the runner would reject the name
        // before the branch it belongs to is even on disk. parseArgs therefore
        // accepts it, and unknownJobs() is what main() consults afterwards.
        expect(parseArgs(['--branch', 'b', '--jobs', 'boom']).jobs).toEqual(['boom'])
        expect(unknownJobs(['boom'])).toEqual(['boom'])

        // Everything this runner does implement still reads as known, `browser`
        // and `all` included — they are valid `--jobs` values, just not in ALL_JOBS.
        expect(unknownJobs([...ALL_JOBS, 'browser', 'all'])).toEqual([])
    })

    it('sees a file the validated commit deleted still sitting in the worktree', () => {
        // The UAMS-Web/uams-statamic-archive#1889 case exactly: UAMS-Web/uams-statamic-archive#1847 deleted these two audit tests, they were
        // absent from both validated commits, and the REUSING run executed and
        // passed them anyway. A surviving deletion is UNTRACKED — `??`, which
        // reads like an addition — so it is the status that must not be waved
        // through as harmless scratch.
        const entries = worktreeDriftEntries(
            '?? tests/Feature/FormidableNotificationAuditTest.php\0'
            + '?? tests/Feature/GravityFormsNotificationAuditTest.php\0',
        )

        expect(entries.map((e) => e.path)).toEqual([
            'tests/Feature/FormidableNotificationAuditTest.php',
            'tests/Feature/GravityFormsNotificationAuditTest.php',
        ])
        expect(driftLabel(entries[0].status)).toContain('surviving deletion')
    })

    it('survives the leading status column that a worktree-only change starts with', () => {
        // ` D path` — index unmodified, worktree changed — is what git writes for a
        // deleted-on-disk tracked file, and it is the FIRST record, so its leading
        // space is the first byte of the whole stream. Trimming that stream (as the
        // shared `capture()` helper does) shifts the field by one: the status reads
        // `D ` and the path loses its first character, so the refusal names a file
        // that does not exist. A short enough record vanishes under the `length < 4`
        // guard entirely, and the drifted run is allowed through — a false green in
        // the guard itself. The drift probe therefore reads RAW stdout.
        const raw = ' D app/Support/Gone.php\0?? scratch.txt\0'
        expect(worktreeDriftEntries(raw)[0]).toEqual({ status: ' D', path: 'app/Support/Gone.php', origin: null })

        // The shape the bug produced, pinned so a reintroduced trim is visible.
        expect(worktreeDriftEntries(raw.trim())[0].path).toBe('pp/Support/Gone.php')
        expect(worktreeDriftEntries(' M a\0'.trim())).toEqual([])
        expect(worktreeDriftEntries(' M a\0')).toHaveLength(1)
    })

    it('reads a clean worktree as no drift, and never invents an entry', () => {
        // The overwhelmingly common case. An empty status must parse to zero
        // entries — anything else turns every reused run into a false refusal.
        expect(worktreeDriftEntries('')).toEqual([])
        expect(worktreeDriftEntries('\0')).toEqual([])
    })

    it('parses the statuses a drifted worktree actually produces', () => {
        // A deleted app/ source file (larastan would otherwise analyze the stale
        // copy), a modified one, and a rename — whose ORIGIN path arrives as a
        // second NUL-terminated field and must be consumed, not read as a further
        // entry with two characters shorn off the front of its path.
        const entries = worktreeDriftEntries(
            ' D app/Support/Gone.php\0'
            + ' M app/Support/Edited.php\0'
            + 'R  app/Support/New.php\0app/Support/Old.php\0'
            + '?? scratch note.txt\0',
        )

        expect(entries).toHaveLength(4)
        expect(entries[2]).toEqual({ status: 'R ', path: 'app/Support/New.php', origin: 'app/Support/Old.php' })
        // The path with a space survives intact — the whole reason for `-z`, since
        // porcelain v1 would have delivered it quoted and C-escaped.
        expect(entries[3].path).toBe('scratch note.txt')
        expect(driftLabel(' D')).toContain('missing from the worktree')
        expect(driftLabel(' M')).toContain('content differs')
    })

    it('runs composer validate with the application-scoped flags in both files', () => {
        // The flags scope the check to an application rather than a published
        // package. They do not change pass/fail — what they must stay is IDENTICAL
        // across the two files, or the mirror lies about what the gate checks.
        const flags = /composer validate --no-check-publish --no-check-all/
        expect(workflow).toMatch(flags)

        const script = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')
        expect(script).toMatch(/'validate', '--no-check-publish', '--no-check-all'/)
    })
})

// UAMS-Web/uams-statamic-archive#2989: the header comment's `--jobs` usage text is the list a person copies
// from when assembling a job selection. It once omitted `phpstan`, and because
// that name is valid the runner accepted the narrower selection without a word.
// So the documented list must hold exactly the jobs the runner knows, compared
// as sets (two lists of equal length can still disagree), in the chain's order.
describe('--jobs usage text', () => {
    // Read the comma-separated names on the line after the `--jobs` option
    function documentedJobs() {
        const lines = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8').split('\n')
        const option = lines.findIndex((line) => line.includes('-j, --jobs <list>') && line.startsWith('//'))
        expect(option).toBeGreaterThan(-1)

        return lines[option + 1]
            .replace(/^\/\/\s*/, '')
            .replace(/,\s*$/, '')
            .split(',')
            .map((name) => name.trim())
    }

    it('names every job in ALL_JOBS and nothing else', () => {
        expect(new Set(documentedJobs())).toEqual(new Set(ALL_JOBS))
    })

    it('lists the jobs in the chain order', () => {
        expect(documentedJobs()).toEqual(CHAIN)
    })

    it('is accepted whole by --jobs', () => {
        const opts = parseArgs(['--branch', 'x', '--jobs', documentedJobs().join(',')])

        expect(opts.jobs).toEqual(documentedJobs())
        expect(unknownJobs(opts.jobs)).toEqual([])
    })
})

/**
 * Read a file as it stands at a git ref, or say why the ref cannot be read.
 *
 * A ref that does not resolve is reported, not thrown, so a caller can skip with
 * a stated reason: a CI checkout has the commit under test and nothing else. A
 * ref that resolves but lacks the file throws, because that is a real finding
 * about the ref and must fail rather than skip.
 *
 * @param {string} cwd Any directory inside the repository to read.
 * @param {string} ref The ref to read the file at, such as `origin/main`.
 * @param {string} file The file's path relative to the repository root.
 * @returns {{source: string|null, unreachable: string|null}} The file's text, or
 *          the reason the ref could not be read.
 */
function readAtRef(cwd, ref, file) {
    // Check whether the ref names a commit in this checkout at all
    try {
        execFileSync('git', ['-C', cwd, 'rev-parse', '--verify', '--quiet', `${ref}^{commit}`], {
            stdio: ['ignore', 'pipe', 'pipe'],
        })
    } catch {
        return {
            source: null,
            unreachable: `${ref} does not resolve in this checkout, so ${file} cannot be read at it`,
        }
    }

    // Read the file at the ref; a missing file throws out of here on purpose
    return {
        source: execFileSync('git', ['-C', cwd, 'show', `${ref}:${file}`], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }),
        unreachable: null,
    }
}

describe('reading a file at a ref a checkout may not have (#375)', () => {
    // A throwaway repository with one commit and no remote: the shape of a CI
    // checkout, which has the commit under test and no `origin/main`.
    const withRepo = (body) => {
        const dir = mkdtempSync(join(tmpdir(), 'read-at-ref-'))
        try {
            const git = (...args) => execFileSync('git', ['-C', dir, ...args], { stdio: ['ignore', 'pipe', 'pipe'] })
            git('init', '--quiet')
            writeFileSync(join(dir, 'present.txt'), 'present\n')
            git('add', 'present.txt')
            git('-c', 'user.name=test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'one')
            body(dir)
        } finally {
            rmSync(dir, { recursive: true, force: true })
        }
    }

    it('reports a ref that does not resolve, naming it, instead of reading nothing', () => {
        withRepo((dir) => {
            expect(readAtRef(dir, 'origin/main', 'present.txt')).toEqual({
                source: null,
                unreachable: 'origin/main does not resolve in this checkout, so present.txt cannot be read at it',
            })
        })
    })

    it('reads the file when the ref resolves', () => {
        withRepo((dir) => {
            expect(readAtRef(dir, 'HEAD', 'present.txt')).toEqual({ source: 'present\n', unreachable: null })
        })
    })

    it('throws when the ref resolves but the file is not there, rather than skipping', () => {
        withRepo((dir) => {
            expect(() => readAtRef(dir, 'HEAD', 'absent.txt')).toThrow()
        })
    })
})
