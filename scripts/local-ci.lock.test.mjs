// Coverage for the single-flight guard on the CI worktree (UAMS-Web/uams-statamic-archive#2256).
//
// Two kinds of test here, and the second exists because the first cannot replace
// it. The behavioral tests exercise acquire/refuse/reclaim/release. The ORDERING
// test asserts, by line index, that the lock is taken before every call that
// mutates or reads worktree state — which no behavioral test can see, because a
// misplaced lock fails identically downstream of the removal it failed to guard.
// That is exactly how the same defect survived its own suite in the sibling
// repository's implementation (UAMS-Web/wordpress-importer#936).

import { describe, expect, it } from 'vitest'
import { mkdtempSync, mkdirSync, writeFileSync, existsSync, readFileSync, rmSync } from 'node:fs'
import { join } from 'node:path'
import { tmpdir } from 'node:os'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import {
    LOCK_REFUSED_EXIT,
    acquireWorktreeLock,
    holderDisposition,
    lockRefusalMessage,
    worktreeLockPath,
} from './local-ci.mjs'

const scratch = () => mkdtempSync(join(tmpdir(), 'ci-lock-'))

describe('worktreeLockPath', () => {
    it('places the lock beside the worktree, never inside it', () => {
        // `--fresh` deletes the worktree wholesale; a lock inside it would go too,
        // handing the slot to the next caller mid-run.
        const lock = worktreeLockPath('/tmp/repo-ci')

        expect(lock).toBe('/tmp/repo-ci.lock')
        expect(lock.startsWith('/tmp/repo-ci/')).toBe(false)
    })

    it('does not produce a nested path when the worktree has a trailing separator', () => {
        expect(worktreeLockPath('/tmp/repo-ci/')).toBe('/tmp/repo-ci.lock')
    })
})

describe('holderDisposition', () => {
    const alive = () => true
    const dead = () => false

    it('reports a live holder as held, naming it', () => {
        const d = holderDisposition(JSON.stringify({ pid: 4242, branch: 'x' }), alive)

        expect(d.state).toBe('held')
        expect(d.pid).toBe(4242)
        expect(d.branch).toBe('x')
    })

    it('reports a dead holder as stale, which is the only reclaimable state', () => {
        expect(holderDisposition(JSON.stringify({ pid: 4242 }), dead).state).toBe('stale')
    })

    // Every ambiguous read fails CLOSED. Reclaiming on a bad read is how a guard
    // comes to delete a live run's worktree, so absence of evidence is not treated
    // as evidence of absence.
    it.each([
        ['unreadable', null],
        ['empty', '   '],
        ['unparseable', '{not json'],
        ['pid-less', JSON.stringify({ branch: 'x' })],
        ['non-numeric pid', JSON.stringify({ pid: 'nope' })],
        ['zero pid', JSON.stringify({ pid: 0 })],
        ['negative pid', JSON.stringify({ pid: -1 })],
    ])('reports a %s holder file as held rather than stale', (_label, raw) => {
        // `dead` is passed deliberately: even with a probe that would say "gone",
        // an unreadable holder must not be reclaimed.
        expect(holderDisposition(raw, dead).state).toBe('held')
    })

    it('treats EPERM from the probe as alive', () => {
        // A process owned by another user EXISTS. `pidIsAlive` maps EPERM to true;
        // this pins that the disposition honors it rather than second-guessing.
        expect(holderDisposition(JSON.stringify({ pid: 1 }), (pid) => pid === 1).state).toBe('held')
    })
})

describe('lockRefusalMessage', () => {
    it('names the holder so the refusal is actionable', () => {
        const msg = lockRefusalMessage(
            { state: 'held', pid: 999, branch: 'feat', startedAt: '2026-01-01T00:00:00.000Z', detail: 'pid 999 is still running' },
            '/tmp/repo-ci',
        )

        expect(msg).toContain('/tmp/repo-ci')
        expect(msg).toContain('pid 999')
        expect(msg).toContain('feat')
        expect(msg).toContain('--worktree-path')
    })

    it('omits holder details it does not have rather than printing null', () => {
        const msg = lockRefusalMessage(
            { state: 'held', pid: null, branch: null, startedAt: null, detail: 'its holder file is unparseable' },
            '/tmp/repo-ci',
        )

        expect(msg).not.toContain('null')
        expect(msg).toContain('unparseable')
    })
})

describe('acquireWorktreeLock', () => {
    it('acquires when nothing holds the tree, and releases', () => {
        const dir = scratch()
        const wt = join(dir, 'repo-ci')

        const lock = acquireWorktreeLock(wt, 'branch-a')

        expect(lock.ok).toBe(true)
        expect(existsSync(worktreeLockPath(wt))).toBe(true)

        const holder = JSON.parse(readFileSync(join(worktreeLockPath(wt), 'holder.json'), 'utf8'))
        expect(holder.pid).toBe(process.pid)
        expect(holder.branch).toBe('branch-a')

        lock.release()
        expect(existsSync(worktreeLockPath(wt))).toBe(false)

        rmSync(dir, { recursive: true, force: true })
    })

    // The positive arm above and this negative arm are both required: a guard that
    // can never fire is indistinguishable from one that works, and a guard that
    // always fires is indistinguishable from one that is correct.
    it('refuses while a live holder has it, with the distinct exit code available', () => {
        const dir = scratch()
        const wt = join(dir, 'repo-ci')

        const first = acquireWorktreeLock(wt, 'branch-a')
        expect(first.ok).toBe(true)

        const second = acquireWorktreeLock(wt, 'branch-b')

        expect(second.ok).toBe(false)
        expect(second.disposition.state).toBe('held')
        expect(second.disposition.pid).toBe(process.pid)
        expect(LOCK_REFUSED_EXIT).toBe(4)

        first.release()
        rmSync(dir, { recursive: true, force: true })
    })

    it('reclaims a stale lock rather than deadlocking the next run', () => {
        const dir = scratch()
        const wt = join(dir, 'repo-ci')
        const lockPath = worktreeLockPath(wt)

        // A killed run leaves the directory and a holder file behind. pid 2^22 is
        // above any Linux/macOS pid_max, so it is reliably not running.
        mkdirSync(lockPath)
        writeFileSync(join(lockPath, 'holder.json'), JSON.stringify({ pid: 4194304, branch: 'dead' }))

        const lock = acquireWorktreeLock(wt, 'branch-b')

        expect(lock.ok).toBe(true)
        expect(JSON.parse(readFileSync(join(lockPath, 'holder.json'), 'utf8')).branch).toBe('branch-b')

        lock.release()
        rmSync(dir, { recursive: true, force: true })
    })

    it('does not reclaim a lock whose holder file it cannot parse', () => {
        const dir = scratch()
        const wt = join(dir, 'repo-ci')
        const lockPath = worktreeLockPath(wt)

        mkdirSync(lockPath)
        writeFileSync(join(lockPath, 'holder.json'), '{ truncated')

        const lock = acquireWorktreeLock(wt, 'branch-b')

        expect(lock.ok).toBe(false)
        expect(lock.disposition.state).toBe('held')

        rmSync(dir, { recursive: true, force: true })
    })

    // The exposure is the shared TREE, not concurrency itself. Two runs pointed at
    // different worktrees must both proceed, or the guard has over-reached.
    it('lets two runs with different worktree paths both proceed', () => {
        const dir = scratch()
        const a = acquireWorktreeLock(join(dir, 'repo-ci-a'), 'branch-a')
        const b = acquireWorktreeLock(join(dir, 'repo-ci-b'), 'branch-b')

        expect([a.ok, b.ok]).toEqual([true, true])

        a.release()
        b.release()
        rmSync(dir, { recursive: true, force: true })
    })

    it('has an idempotent release', () => {
        const dir = scratch()
        const wt = join(dir, 'repo-ci')
        const lock = acquireWorktreeLock(wt, 'branch-a')

        lock.release()
        expect(() => lock.release()).not.toThrow()

        rmSync(dir, { recursive: true, force: true })
    })
})

// --- the ordering test ------------------------------------------------------
//
// This is the one a behavioral test cannot replace. The natural place to take a
// lock is beside the destructive git calls it protects — but `--fresh` runs
// `worktree remove --force` well above them, and the already-checked-out refusal
// READS `git worktree list`, state a concurrent `--fresh` mutates. A lock placed
// after that read guards the writes and leaves the read they depend on unguarded.
// Both placements fail identically downstream, so only line order distinguishes
// them.
describe('lock ordering in the runner (static)', () => {
    const source = readFileSync(new URL('./local-ci.mjs', import.meta.url), 'utf8').split('\n')

    const linesMatching = (needle) => source
        .map((line, i) => (line.includes(needle) ? i : -1))
        .filter((i) => i !== -1)

    const acquireCallLines = source
        .map((line, i) => (line.includes('acquireWorktreeLock(worktreePath') && !line.includes('export function') ? i : -1))
        .filter((i) => i !== -1)

    it('takes the lock at exactly one place', () => {
        // More than one acquire would mean two entry points, and this whole test
        // would only be pinning whichever came first.
        expect(acquireCallLines).toHaveLength(1)
    })

    it.each([
        ['worktree remove --force, which --fresh runs', "'worktree', 'remove', '--force'"],
        ['the git worktree list read the checkout refusal depends on', "'worktree', 'list', '--porcelain'"],
        ['git branch -f', "'branch', '-f'"],
        ['git worktree add', "'worktree', 'add', worktreePath"],
    ])('acquires the lock before %s', (_label, needle) => {
        const targets = linesMatching(needle)

        // Assert the needle still matches something. A static test whose pattern
        // has drifted out of the source passes vacuously, and a vacuous pass here
        // is indistinguishable from correct ordering.
        expect(targets.length).toBeGreaterThan(0)

        for (const target of targets) {
            expect(acquireCallLines[0]).toBeLessThan(target)
        }
    })
})

// --- the pinned handoff -----------------------------------------------------
//
// When a branch ships a different runner, the parent hands off to the branch's
// copy with LOCAL_CI_RUNNER_PINNED=1 and waits. That child resolves the SAME
// worktree path, so a lock taken unconditionally would make it refuse against
// its own parent — turning the guard into a deadlock on exactly the PRs that
// change the runner, which is every PR that could have introduced it.
//
// Spawned rather than unit-tested because the exemption lives in `main()`, and
// the thing worth pinning is the end-to-end exit code a lane would actually see.
describe('a pinned handoff child does not refuse against its own parent', () => {
    const spawnRunner = (env) => {
        const worktree = join(mkdtempSync(join(tmpdir(), 'ci-pinned-')), 'repo-ci')
        const lockPath = worktreeLockPath(worktree)

        // A holder that is genuinely alive: this very test process.
        mkdirSync(lockPath, { recursive: true })
        writeFileSync(join(lockPath, 'holder.json'), JSON.stringify({ pid: process.pid, branch: 'parent-run' }))

        const result = spawnSync(
            process.execPath,
            [fileURLToPath(new URL('./local-ci.mjs', import.meta.url)),
                '--branch', 'no-such-branch-for-this-test', '--worktree-path', worktree, '--jobs', 'spell'],
            { encoding: 'utf8', timeout: 60_000, env: { ...process.env, ...env } },
        )

        rmSync(lockPath, { recursive: true, force: true })

        return { status: result.status, output: `${result.stdout ?? ''}${result.stderr ?? ''}` }
    }

    // The control. Without it, a pinned run that proceeds proves nothing — it is
    // equally consistent with a guard that never fires at all.
    it('refuses when NOT pinned, against a live holder', () => {
        const { status, output } = spawnRunner({ LOCAL_CI_RUNNER_PINNED: '' })

        expect(status).toBe(LOCK_REFUSED_EXIT)
        expect(output).toContain('REFUSED')
    })

    it('proceeds when pinned, against the same live holder', () => {
        const { status, output } = spawnRunner({ LOCAL_CI_RUNNER_PINNED: '1' })

        expect(output).not.toContain('REFUSED')
        expect(status).not.toBe(LOCK_REFUSED_EXIT)
    })
})
