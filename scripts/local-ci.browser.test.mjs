import { describe, it, expect } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { canReuseBuildOutput } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#1727 — `browser` joined the default `all` set, and its `npm run build` +
// `npm run cp:build` became conditional so a full run does not pay for them twice
// in one shared worktree. These cover the predicate that decides that, and the
// wiring that consumes it.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

describe('browser job build reuse (#1727)', () => {
    it('reuses the output when `build` ran and passed in this invocation', () => {
        expect(canReuseBuildOutput(new Map([['build', 0]]))).toBe(true)
    })

    it('builds when `build` was not selected at all', () => {
        // The `--jobs browser`-only case the ticket calls out by name: nothing has
        // produced the bundles, so skipping would smoke whatever the worktree
        // happened to be carrying.
        expect(canReuseBuildOutput(new Map())).toBe(false)
        expect(canReuseBuildOutput(new Map([['phpunit', 0]]))).toBe(false)
    })

    it('builds when `build` ran but failed', () => {
        // Defense in depth: the chain already skips every job after a failure, so
        // `browser` should not run at all here. If that gating is ever loosened,
        // this must not silently treat a failed build as reusable output.
        expect(canReuseBuildOutput(new Map([['build', 1]]))).toBe(false)
    })

    it('builds when `build` was skipped by the chain', () => {
        // The runner stores the STRING 'skipped' for a chain-gated job. Loose
        // equality would be safe here (`'skipped' == 0` is false), but a
        // TRUTHINESS check would not: `Boolean('skipped')` is true, so rewriting
        // the predicate as `if (results.get('build'))` would treat a skipped build
        // as reusable output. That is the mutation this case exists to catch.
        expect(canReuseBuildOutput(new Map([['build', 'skipped']]))).toBe(false)
    })

    it('is actually wired into the browser job', () => {
        // The predicate is pure and fully covered above, and every one of those
        // assertions stays green if the runner stops calling it and rebuilds
        // unconditionally — or worse, skips unconditionally.
        //
        // Pin the CALL SITE and the BRANCH, not the bare identifier. A
        // `toContain('canReuseBuildOutput(results)')` is satisfied by the
        // function's own declaration — `export function canReuseBuildOutput(results)`
        // contains that substring — so it stays green with the call site deleted,
        // which is the false green this whole file exists to prevent.
        const runner = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')

        expect(runner).toContain('const reusePriorBuild = canReuseBuildOutput(results)')
        expect(runner).toContain('if (reusePriorBuild) {')
    })
})

describe('browser fixtures cannot strand worktree drift (#1727 x #1889)', () => {
    // Promoting `browser` into the default set changes the blast radius of a
    // leftover fixture. tests/Browser writes static fixtures under public/ in
    // beforeEach and deletes them in afterEach — but that afterEach does not run
    // if the process is killed first, and the browser job is both the longest and
    // the last, so it is the likeliest to be cut short at a bound. An untracked
    // leftover then makes the UAMS-Web/uams-statamic-archive#1889 drift gate REFUSE the next run on that reused
    // worktree, turning one interrupted run into a blocked queue.
    const fixtureCalls = () => {
        const dir = join(repoRoot, 'tests', 'Browser')
        return readdirSync(dir)
            .filter((name) => name.endsWith('.php'))
            .flatMap((name) => [...readFileSync(join(dir, name), 'utf8')
                .matchAll(/public_path\('([^']+)'\)/g)].map((m) => m[1]))
            .filter((path) => path.startsWith('__'))
    }

    it('names every fixture so the ignore rule matches it', () => {
        // The rule is a glob, so a fixture named outside the pattern is silently
        // unprotected. Assert the shape rather than a hand-maintained list.
        const calls = fixtureCalls()

        expect(calls.length).toBeGreaterThan(0)
        for (const path of calls) {
            expect(path).toMatch(/^__.*_fixture\.html$/)
        }
    })

    it('ignores that pattern', () => {
        const ignore = readFileSync(join(repoRoot, '.gitignore'), 'utf8')

        expect(ignore).toContain('/public/__*_fixture.html')
    })

    it('wires the write isolation at the directory level, where no smoke can forget it', () => {
        // The CP fieldtype smokes also write a BLUEPRINT, a COLLECTION and a CP
        // USER — into resources/blueprints/, the content/ submodule and the users/
        // submodule respectively, all tracked trees — and strand them on the same
        // interrupted-run path. UAMS-Web/uams-statamic-archive#1920 relocated those writes into
        // storage/framework/testing rather than ignoring them.
        //
        // `begin()` lives in tests/Pest.php's ->in('Browser') registration on
        // purpose: a per-file beforeEach is something a NEW smoke can omit, and the
        // omission is invisible until an interrupted run wedges the next validation.
        // This pins the wiring, since nothing else fails when it goes missing —
        // a green suite cleans up after itself either way.
        const pest = readFileSync(join(repoRoot, 'tests', 'Pest.php'), 'utf8')

        expect(pest).toContain('BrowserWriteIsolation::begin()')
        expect(pest).toContain("->in('Browser')")

        // `end()` is deliberately NOT here. Pest chains a uses()-level hook AHEAD of
        // the file-level one in BOTH directions, so a uses()->afterEach() teardown
        // releases isolation BEFORE each smoke's own cleanup runs — aiming those
        // fixture deletes at tracked source. It belongs in Tests\TestCase::tearDown(),
        // which Pest calls from the `finally` after the whole afterEach chain.
        // Tests\Browser\BrowserWriteIsolationOrderingTest pins that behaviorally;
        // this pins the structure that makes it true, because the behavioral guard
        // only runs in the `browser` job.
        expect(pest).not.toContain('BrowserWriteIsolation::end()')

        const testCase = readFileSync(join(repoRoot, 'tests', 'TestCase.php'), 'utf8')

        expect(testCase).toContain('BrowserWriteIsolation::end()')
        expect(testCase).toMatch(/function tearDown\(\): void\s*\{\s*BrowserWriteIsolation::end\(\);/)
    })

    it('no longer ignores a path inside the tracked blueprint tree', () => {
        // The rule this replaces was carried as an explicit stopgap naming UAMS-Web/uams-statamic-archive#1920.
        // It has to stay gone: a glob over resources/blueprints/ can mask a genuine
        // blueprint someone adds, and it hid the write rather than preventing it —
        // even a fully green run left an empty collections/<handle>_smoke/ behind,
        // which reads as clean only because git cannot see an empty directory.
        const ignore = readFileSync(join(repoRoot, '.gitignore'), 'utf8')

        expect(ignore).not.toContain('/resources/blueprints/')
    })

    it('keeps the throwaway root somewhere git already ignores', () => {
        // The whole fix rests on this: an interrupted run leaves its residue under
        // storage/framework/testing, so the UAMS-Web/uams-statamic-archive#1889 drift gate never sees it. Laravel
        // ships the `*` rule there, which is why UAMS-Web/uams-statamic-archive#1920 adds no ignore rule of its
        // own — but "already ignored" is exactly the kind of inherited assumption
        // that breaks silently, so assert it.
        const ignore = readFileSync(join(repoRoot, 'storage', 'framework', 'testing', '.gitignore'), 'utf8')

        expect(ignore.split(/\r?\n/).map((line) => line.trim())).toContain('*')

        const isolation = readFileSync(join(repoRoot, 'tests', 'Support', 'BrowserWriteIsolation.php'), 'utf8')

        expect(isolation).toContain("storage_path('framework/testing/browser-writes/")
    })
})

describe('ci.yml keeps building for itself (#1727)', () => {
    it('does not adopt the local reuse, because each CI job is a fresh runner', () => {
        // The mirrored files legitimately DIVERGE here, which is worth pinning so a
        // future parity sweep does not "fix" it: on Actions the browser job shares
        // no filesystem with the build job, so both of its build steps must stay
        // unconditional. Locally they are the duplicate this ticket removed.
        const workflow = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')

        expect(workflow).toContain('- name: Build front-end assets')
        expect(workflow).toContain('- name: Build CP assets')
    })

    it('no longer gates the browser job behind a PR label', () => {
        // The regression this pins is the whole point of UAMS-Web/uams-statamic-archive#1727: a suite that runs
        // only when a human remembers to ask went red on `main` for eleven days.
        // Re-adding a label gate here reinstates exactly that bet on memory.
        //
        // Asserted on the MECHANISM rather than on the string `browser-tests`,
        // which survives in prose here and in the skills as a topic label — and
        // which a re-added gate could trivially avoid by quoting it differently or
        // choosing another label name.
        const workflow = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')

        expect(workflow).not.toContain('github.event.pull_request.labels')
    })
})
