import { describe, it, expect } from 'vitest'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync, realpathSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { execFileSync, spawnSync } from 'node:child_process'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
    baseProbe,
    contentMirrorProbe,
    contentMirrorReason,
    contentMirrorStateOf,
    refreshOutcome,
    baseState,
    checkVerdictLine,
    driftReasons,
    expectationsFor,
    fetchOriginArgs,
    installStaleReason,
    isOwnToplevel,
    judgeTree,
    normalizePath,
    parseWorktreeList,
    pinnedDependencyGrouping,
    pinnedDependencyNotice,
    provisionSteps,
    releaseCheckoutArgs,
    releaseRefusal,
    releaseRefusalReason,
    submoduleProbe,
    submoduleReleaseRefusalReason,
    submoduleRevision,
    submoduleWorkingTreeState,
    submoduleWorktreeReason,
    resolveSlot,
    slotDirName,
    slotOccupancy,
    treeKind,
    heldSlotRefusalReason,
    releaseRaceAbortReason,
    flagValue,
    bootProbe,
    installFailureReport,
    vendorPackages,
    vendorCheckoutProbe,
    vendorCheckoutReason,
    vendorCheckoutRecovery,
    vendorCheckoutsOf,
} from './worktree-slots.mjs'

// The slot model's one new risk is a tree whose dependencies have drifted from the branch it sits
// on, and that failure is silent — the suite runs, passes, and passes against the wrong versions.
// These assert the properties that make the gate trustworthy: drift is detected rather than
// assumed absent, a release cannot strand uncommitted work, and a slot name only ever resolves to
// a worktree git actually knows about.

const PORCELAIN = `worktree C:/Users/dev/Herd/uams-statamic
HEAD 9252a7374bd29f87a462ed3e8d0518d0a57a013d
branch refs/heads/main

worktree C:/Users/dev/Herd/uams-statamic-a
HEAD 9252a7374bd29f87a462ed3e8d0518d0a57a013d
branch refs/heads/1946-add-worktree-slots

worktree C:/Users/dev/Herd/uams-statamic-b
HEAD 9252a7374bd29f87a462ed3e8d0518d0a57a013d
detached
`

describe('parseWorktreeList', () => {
    it('reads one record per tree, stripping the refs/heads prefix', () => {
        const trees = parseWorktreeList(PORCELAIN)

        expect(trees).toHaveLength(3)
        expect(trees[0].branch).toBe('main')
        expect(trees[1].branch).toBe('1946-add-worktree-slots')
    })

    // A detached tree carries a bare `detached` line and no `branch` line at all. That absence is
    // the free/held signal, so it must survive parsing rather than defaulting to something truthy.
    it('marks a detached tree as detached with no branch', () => {
        const [, , slotB] = parseWorktreeList(PORCELAIN)

        expect(slotB.detached).toBe(true)
        expect(slotB.branch).toBeNull()
    })

    it('tolerates trailing whitespace and blank padding between records', () => {
        expect(parseWorktreeList(`${PORCELAIN}\n\n  \n`)).toHaveLength(3)
    })

    it('returns nothing for empty input rather than a phantom record', () => {
        expect(parseWorktreeList('')).toEqual([])
    })
})

describe('normalizePath', () => {
    // git prints forward slashes on every platform; Node's join yields backslashes on Windows. Any
    // comparison between the two must normalize, or every slot resolves as "not registered".
    it('treats a Windows path and its git-reported form as equal', () => {
        expect(normalizePath('C:\\Users\\dev\\Herd\\uams-statamic-a'))
            .toBe(normalizePath('C:/Users/dev/Herd/uams-statamic-a'))
    })

    it('ignores drive-letter casing and a trailing separator', () => {
        expect(normalizePath('c:/users/dev/herd/uams-statamic-a/'))
            .toBe(normalizePath('C:/Users/dev/Herd/uams-statamic-a'))
    })
})

describe('slotDirName', () => {
    // Derived from the primary's own basename so the convention holds for a clone under any
    // directory name, rather than hard-coding one machine's layout.
    it('derives the slot directory from the primary checkout', () => {
        expect(slotDirName('/home/dev/projects/uams-statamic', 'a')).toBe('uams-statamic-a')
        expect(slotDirName('/home/dev/projects/renamed-clone', 'b')).toBe('renamed-clone-b')
    })
})

describe('resolveSlot', () => {
    const trees = parseWorktreeList(PORCELAIN)
    const root = 'C:/Users/dev/Herd/uams-statamic'

    it('resolves a slot name to its registered worktree', () => {
        expect(resolveSlot(trees, root, 'a').branch).toBe('1946-add-worktree-slots')
        expect(resolveSlot(trees, root, 'b').detached).toBe(true)
    })

    // Acting on an unregistered path would install into, or detach, whatever happens to sit there.
    it('refuses a slot that is not a registered worktree', () => {
        expect(() => resolveSlot(trees, root, 'zz')).toThrow(/No registered worktree/)
    })

    // A name is interpolated into a path, so anything path-like must be rejected before it is.
    it('rejects names that are not plain slot identifiers', () => {
        expect(() => resolveSlot(trees, root, '../../etc')).toThrow(/Invalid slot name/)
        expect(() => resolveSlot(trees, root, '')).toThrow(/Invalid slot name/)
        expect(() => resolveSlot(trees, root, '1946')).toThrow(/Invalid slot name/)
    })
})

describe('resolveSlot — the primary as a target', () => {
    const trees = parseWorktreeList(PORCELAIN)
    const root = 'C:/Users/dev/Herd/uams-statamic'

    // `primary` is the clone itself, NOT a `<basename>-primary` sibling. Before UAMS-Web/uams-statamic-archive#1952 it resolved
    // to the sibling, so the one tree `status` reported drift on was the one `refresh` could not
    // reach — and `--check` could never clear.
    it('resolves `primary` to the repo root itself', () => {
        expect(resolveSlot(trees, root, 'primary').branch).toBe('main')
    })

    // There is no `provision primary`; suggesting it sent the reader off to create a fifth tree.
    it('omits the provision hint when the primary is somehow unregistered', () => {
        expect(() => resolveSlot([], root, 'primary')).toThrow(/No registered worktree/)
        expect(() => resolveSlot([], root, 'primary')).not.toThrow(/provision/)
        expect(() => resolveSlot([], root, 'a')).toThrow(/provision a/)
    })
})

describe('treeKind', () => {
    const root = 'C:/Users/dev/Herd/uams-statamic'

    it('separates the primary, the CI slot, and an ordinary slot', () => {
        expect(treeKind(root, root)).toBe('primary')
        expect(treeKind('C:/Users/dev/Herd/uams-statamic-ci', root)).toBe('ci')
        expect(treeKind('C:/Users/dev/Herd/uams-statamic-a', root)).toBe('slot')
    })

    // Derived from the primary's basename, like slotDirName — a clone under any directory name
    // still has to identify its own CI slot.
    it('identifies the CI slot of a differently-named clone', () => {
        expect(treeKind('/home/dev/renamed-clone-ci', '/home/dev/renamed-clone')).toBe('ci')
        expect(treeKind('/home/dev/renamed-clone-a', '/home/dev/renamed-clone')).toBe('slot')
    })

    // A slot is a SIBLING named `<primary>-<name>`. Anything else git has registered is somebody's
    // temporary tree — `.claude/worktrees/…`, a subagent's isolated tree — and holding those to a
    // slot's bootstrap is the same bug as the CI slot's: they never carry one, `refresh` cannot
    // address them, and one existing would pin `--check` at 1 forever.
    it('treats a worktree that is not a sibling slot as ephemeral', () => {
        expect(treeKind(`${root}/.claude/worktrees/t1948-probe`, root)).toBe('ephemeral')
        expect(treeKind('C:/Users/dev/elsewhere/scratch-tree', root)).toBe('ephemeral')
    })

    // A sibling whose name merely starts the same way is not a slot either.
    it('does not mistake an unrelated sibling directory for a slot', () => {
        expect(treeKind('C:/Users/dev/Herd/unrelated-checkout', root)).toBe('ephemeral')
    })
})

describe('expectationsFor', () => {
    // The CI slot is runner-owned: local-ci.mjs initializes only `content`, `users` and
    // `resources/forms` (UAMS-Web/uams-statamic-archive#2477, UAMS-Web/uams-statamic-archive#2818)
    // and runs its own installs without writing this script's stamp. Judging it as a slot
    // reported four permanent drift lines for a tree in exactly the state it should be in.
    it('holds the CI slot to content, users and forms only, and to no install stamp', () => {
        const ci = expectationsFor('ci')

        expect(ci.checkInstall).toBe(false)
        expect(ci.submodules).toEqual(['content', 'users', 'resources/forms'])

        // The runner seeds its own content mirror before every run (UAMS-Web/uams-statamic-archive#2935)
        expect(ci.checkContentMirror).toBe(false)
    })

    it('holds a slot and the primary to the full bootstrap', () => {
        for (const kind of ['slot', 'primary']) {
            expect(expectationsFor(kind).checkInstall).toBe(true)
            expect(expectationsFor(kind).checkContentMirror).toBe(true)
            expect(expectationsFor(kind).submodules).toHaveLength(5)
            expect(expectationsFor(kind).submodules).toContain('public/assets')
        }
    })

    it('expects nothing of an ephemeral worktree, which is nobody\'s to keep current', () => {
        expect(expectationsFor('ephemeral')).toEqual({ checkInstall: false, checkBase: false, checkContentMirror: false, submodules: [] })
    })
})

// The seam that decides the exit code. Both halves were already tested in isolation, and an
// adversarial review showed that proves nothing: deleting the `expected:` wiring between them left
// the whole suite green while `status --check` went back to reporting four permanent drift lines,
// because `driftReasons` falls back to the STRICT reading when `expected` is omitted. These cross
// the seam, so that mutation now reds.
describe('judgeTree', () => {
    const root = 'C:/Users/dev/Herd/uams-statamic'
    const probe = {
        lockHash: 'abc123',
        stampHash: null,
        composerHash: 'def456',
        stampComposerHash: null,
        submodules: [
            { path: 'content', recorded: 'aaa111', actual: 'aaa111' },
            { path: 'users', recorded: 'bbb222', actual: 'bbb222' },
            { path: 'public/assets', recorded: 'ccc333', actual: null },
            { path: 'app/private/assets', recorded: 'ddd444', actual: null },
        ],
    }

    it('clears the CI slot in its prescribed state', () => {
        expect(judgeTree(`${root}-ci`, root, probe)).toEqual({ kind: 'ci', drift: [], notices: [] })
    })

    it('faults the identical state in a real slot', () => {
        const { kind, drift } = judgeTree(`${root}-a`, root, probe)

        expect(kind).toBe('slot')
        expect(drift).toHaveLength(4)
        expect(drift).toContain('public/assets: not initialized')
    })

    it('clears an ephemeral worktree that carries no bootstrap at all', () => {
        const bare = { lockHash: 'abc123', stampHash: null, composerHash: 'def456', stampComposerHash: null, submodules: [] }

        expect(judgeTree(`${root}/.claude/worktrees/probe`, root, bare)).toEqual({ kind: 'ephemeral', drift: [], notices: [] })
    })

    // Narrowing what counts must never silence what remains: the runner does need `content`.
    it('still faults a CI slot with no content/', () => {
        const { drift } = judgeTree(`${root}-ci`, root, {
            ...probe,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: null }],
        })

        expect(drift).toEqual(['content: not initialized'])
    })

    // The UAMS-Web/uams-statamic-archive#2023 seam, crossed rather than tested in halves. The importer slot presented exactly
    // this: every install stamp current, both asset submodules at their recorded revisions, and
    // 1,696 tracked files deleted underneath. `status --check` read 0.
    it('faults a slot whose submodules are at the right revision and gutted', () => {
        const { kind, drift } = judgeTree(`${root}-a`, root, {
            lockHash: 'abc123',
            stampHash: 'abc123',
            composerHash: 'def456',
            stampComposerHash: 'def456',
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: { tracked: 0, untracked: 0 } },
                { path: 'users', recorded: 'bbb222', actual: 'bbb222', worktree: { tracked: 3, untracked: 0 } },
                { path: 'public/assets', recorded: 'ccc333', actual: 'ccc333', worktree: { tracked: 1211, untracked: 1 } },
                { path: 'app/private/assets', recorded: 'ddd444', actual: 'ddd444', worktree: { tracked: 485, untracked: 0 } },
            ],
        })

        expect(kind).toBe('slot')
        expect(drift).toEqual([
            'users: 3 uncommitted change(s)',
            'public/assets: 1211 uncommitted change(s), 1 untracked path(s)',
            'app/private/assets: 485 uncommitted change(s)',
        ])
    })

    // The exemption is the expectation set doing the work, not a weakened check: the CI slot does
    // not carry these submodules at all, so their state is none of this gate's business there.
    it('exempts a CI slot from working-tree state in submodules it never initializes', () => {
        const { drift } = judgeTree(`${root}-ci`, root, {
            lockHash: 'abc123',
            stampHash: null,
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: { tracked: 0, untracked: 0 } },
                { path: 'public/assets', recorded: 'ccc333', actual: 'ccc333', worktree: { tracked: 1211, untracked: 0 } },
            ],
        })

        expect(drift).toEqual([])
    })

    // …but `content` IS the CI slot's, and the runner reads it. A gutted one there is real drift.
    it('still faults a gutted content/ on the CI slot', () => {
        const { drift } = judgeTree(`${root}-ci`, root, {
            lockHash: 'abc123',
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: { tracked: 82, untracked: 0 } }],
        })

        expect(drift).toEqual(['content: 82 uncommitted change(s)'])
    })
})

describe('submoduleWorkingTreeState', () => {
    it('counts a clean tree as nothing at all', () => {
        expect(submoduleWorkingTreeState('')).toEqual({ tracked: 0, untracked: 0 })
        expect(submoduleWorkingTreeState('  \n \n')).toEqual({ tracked: 0, untracked: 0 })
    })

    // The live state of the importer slot's `users` when this was written: three tracked files
    // staged as deletions, the directory empty but for `.git`, and the revision still matching.
    it('counts staged deletions as tracked changes', () => {
        expect(submoduleWorkingTreeState('D  .gitignore\nD  seed-one@example.test.yaml\nD  seed-two@example.test.yaml\n'))
            .toEqual({ tracked: 3, untracked: 0 })
    })

    it('separates untracked paths from tracked changes', () => {
        expect(submoduleWorkingTreeState(' M a.yaml\n?? images/default/2026-08-w3c-full.png\nD  b.yaml\n'))
            .toEqual({ tracked: 2, untracked: 1 })
    })

    // `??` must be matched at the START of the entry, not anywhere in it — a tracked file whose
    // name merely contains the sequence is still a tracked change.
    it('does not mistake a tracked path containing ?? for an untracked entry', () => {
        expect(submoduleWorkingTreeState(' M what??.yaml\n')).toEqual({ tracked: 1, untracked: 0 })
    })
})

// The wiring between the predicate and the probe, which is where the walk-up actually gets stopped.
// `isOwnToplevel` was already covered when a mutation replacing this guard with a constant `true`
// left all 80 tests green — nothing exercised the decision, only the predicate behind it.
describe('submoduleProbe', () => {
    it('reads revision and working tree from an initialized submodule', () => {
        expect(submoduleProbe('content', '/repo/content', 'aaa111', '/repo/content', 'aaa111', ' M a.yaml\n?? b.yaml\n'))
            .toEqual({
                path: 'content',
                recorded: 'aaa111',
                actual: 'aaa111',
                worktree: { tracked: 1, untracked: 1 },
                unreadable: false,
            })
    })

    // The mutation this exists for. `git -C <empty dir> status --porcelain` answers for the
    // SUPERPROJECT, so the porcelain below is the app tree's dirt, not the submodule's. Trusting it
    // would refuse a release over files that are not there — and on the CI slot, whose three
    // uninitialized submodules are the prescribed state, it would refuse every release forever.
    it('discards a status the enclosing superproject answered for', () => {
        const probe = submoduleProbe('users', '/repo/users', 'bbb222', '/repo', '163b300df', ' M app/Foo.php\n M app/Bar.php\n')

        expect(probe.actual).toBeNull()
        expect(probe.worktree).toBeNull()
        // Absent, not broken — an uninitialized directory has no working tree to fail at reading.
        expect(probe.unreadable).toBe(false)
    })

    // Fail CLOSED. `capture()` returns '' for a clean tree and null for a command that failed, so
    // the two are distinguishable — and collapsing them with `porcelain || ''` would report a
    // submodule whose index is corrupt as pristine, in the one guard whose whole job is catching
    // silently destroyed content. Same asymmetry as `installStaleReason` reading a missing stamp
    // as stale rather than fresh.
    it('marks an initialized submodule whose status could not be read as unreadable, not clean', () => {
        const probe = submoduleProbe('content', '/repo/content', 'aaa111', '/repo/content', 'aaa111', null)

        expect(probe.unreadable).toBe(true)
        expect(probe.worktree).toBeNull()
    })

    // Initialized but unreadable is NOT uninitialized: the directory is its own toplevel, so the
    // status answer is genuinely its own and must be kept even though the revision is unknown.
    it('keeps the working tree when the revision alone could not be read', () => {
        const probe = submoduleProbe('content', '/repo/content', 'aaa111', '/repo/content', null, 'D  a.yaml\n')

        expect(probe.actual).toBeNull()
        expect(probe.worktree).toEqual({ tracked: 1, untracked: 0 })
    })

    it('reads a clean initialized submodule as clean rather than never-probed', () => {
        expect(submoduleProbe('content', '/repo/content', 'aaa111', '/repo/content', 'aaa111', '').worktree)
            .toEqual({ tracked: 0, untracked: 0 })
    })
})

describe('submoduleWorktreeReason', () => {
    it('is silent for a clean tree, and for one that was never probed', () => {
        expect(submoduleWorktreeReason({ tracked: 0, untracked: 0 })).toBeNull()
        expect(submoduleWorktreeReason(null)).toBeNull()
    })

    it('reports each count only when it is non-zero', () => {
        expect(submoduleWorktreeReason({ tracked: 4, untracked: 0 })).toBe('4 uncommitted change(s)')
        expect(submoduleWorktreeReason({ tracked: 0, untracked: 2 })).toBe('2 untracked path(s)')
        expect(submoduleWorktreeReason({ tracked: 4, untracked: 2 })).toBe('4 uncommitted change(s), 2 untracked path(s)')
    })
})

describe('isOwnToplevel', () => {
    it('accepts a directory that is its own git toplevel', () => {
        expect(isOwnToplevel('/repo/content', '/repo/content')).toBe(true)
    })

    // The walk-up. Every `git -C <uninitialized submodule dir>` probe answers for the enclosing
    // superproject instead of failing, and for `status --porcelain` that means reporting the
    // SUPERPROJECT's dirty files as the submodule's — which would make `release` refuse over files
    // that are not there.
    it('rejects a superproject answer from an uninitialized submodule directory', () => {
        expect(isOwnToplevel('/repo/users', '/repo')).toBe(false)
    })

    it('normalizes separators and casing before comparing', () => {
        expect(isOwnToplevel('C:\\repo\\content', 'C:/repo/content')).toBe(true)
    })

    it('is false when git could not answer at all', () => {
        expect(isOwnToplevel('/repo/content', null)).toBe(false)
    })
})

describe('submoduleReleaseRefusalReason', () => {
    it('permits a release when every submodule working tree is clean', () => {
        expect(submoduleReleaseRefusalReason([
            { path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: { tracked: 0, untracked: 0 } },
        ])).toBeNull()
        expect(submoduleReleaseRefusalReason([])).toBeNull()
        expect(submoduleReleaseRefusalReason(undefined)).toBeNull()
    })

    // The whole point: `commandRelease` reads the superproject with `--ignore-submodules=all`, so
    // without this a slot holding 1,696 deleted files detaches cleanly and reads `free` to the
    // next session.
    it('refuses over destroyed submodule contents, naming each submodule and its count', () => {
        const reason = submoduleReleaseRefusalReason([
            { path: 'content', worktree: { tracked: 0, untracked: 0 } },
            { path: 'public/assets', worktree: { tracked: 1211, untracked: 0 } },
            { path: 'app/private/assets', worktree: { tracked: 485, untracked: 0 } },
        ])

        expect(reason).toMatch(/^2 submodule working tree\(s\) carry uncommitted changes:/)
        expect(reason).toContain('public/assets: 1211 uncommitted change(s)')
        expect(reason).toContain('app/private/assets: 485 uncommitted change(s)')
        expect(reason).not.toContain('content')
    })

    // `--ignore-submodules=all` stays on the superproject call precisely so a routinely-drifting
    // `content/` POINTER does not block every release. Guarding contents must not undo that.
    it('permits a release whose only submodule difference is a pointer mismatch', () => {
        expect(submoduleReleaseRefusalReason([
            { path: 'content', recorded: 'aaa111', actual: 'ccc333', worktree: { tracked: 0, untracked: 0 } },
        ])).toBeNull()
    })

    // Untracked residue is the workflow these slots exist to host — a completed import leaves
    // artifacts behind. Refusing over it would red the prescribed resting state, which is the
    // UAMS-Web/uams-statamic-archive#1952 failure and how a guard gets skipped.
    it('permits a release over untracked residue alone, which the slots produce by design', () => {
        expect(submoduleReleaseRefusalReason([
            { path: 'public/assets', worktree: { tracked: 0, untracked: 1 } },
        ])).toBeNull()
    })

    // The boundary, and not a hypothetical one: the primary's `users` submodule carries exactly one
    // modified file whenever a CP preference is saved. One change is a change. A `> 1` threshold
    // survived mutation until this existed.
    it('refuses over a single tracked change, not just a bulk deletion', () => {
        expect(submoduleReleaseRefusalReason([
            { path: 'users', worktree: { tracked: 1, untracked: 0 } },
        ])).toContain('users: 1 uncommitted change(s)')
    })

    // Not evidence of destruction, but not evidence of health either — and this is the last gate
    // before the tree is detached.
    it('refuses over a probe that failed rather than assuming the tree is clean', () => {
        const reason = submoduleReleaseRefusalReason([
            { path: 'content', worktree: null, unreadable: true },
        ])

        expect(reason).toContain('content: working tree could not be read')
        expect(reason).not.toContain('undefined')
    })

    // The generic "commit or move the work first" advice is wrong here: these are deletions or
    // app-written files, not authored work, so the fix is to restore to the gitlink.
    it('names the recovery command, which is cheap and not what the generic advice suggests', () => {
        expect(submoduleReleaseRefusalReason([{ path: 'users', worktree: { tracked: 3, untracked: 0 } }]))
            .toContain('git -C <submodule> restore --staged --worktree .')
    })

    // A submodule that refused for tracked reasons shows BOTH counts, so the reader sees the whole
    // picture — but an untracked-ONLY submodule is not listed at all, since it did not refuse.
    it('shows both counts for a refusing submodule and omits one that did not refuse', () => {
        const reason = submoduleReleaseRefusalReason([
            { path: 'content', worktree: { tracked: 4, untracked: 2 } },
            { path: 'public/assets', worktree: { tracked: 0, untracked: 9 } },
        ])

        expect(reason).toContain('content: 4 uncommitted change(s), 2 untracked path(s)')
        expect(reason).not.toContain('public/assets')
    })

    // An uninitialized submodule was never probed. Reading its null `worktree` as dirty would stop
    // the CI slot ever releasing; reading it as a crash would be worse.
    it('ignores a submodule that was never probed', () => {
        expect(submoduleReleaseRefusalReason([
            { path: 'users', recorded: 'bbb222', actual: null, worktree: null },
            { path: 'public/assets', recorded: 'ccc333', actual: null },
        ])).toBeNull()
    })
})

// The seam `commandRelease` actually calls, asserted because testing the two halves above proved
// nothing. Verified by mutation while writing this: with both halves fully covered, deleting the
// submodule arm from `commandRelease` left the whole suite green (75/75) while `release` went on to
// detach a slot whose `users` submodule had all three tracked files staged as deletions. Same shape
// as the UAMS-Web/uams-statamic-archive#1952 `judgeTree` seam, and the same fix — assert the composition, not just its parts.
describe('releaseRefusal', () => {
    const gutted = [{ path: 'public/assets', worktree: { tracked: 1211, untracked: 0 } }]

    it('permits a release when the tree and every submodule are clean', () => {
        expect(releaseRefusal('', [{ path: 'content', worktree: { tracked: 0, untracked: 0 } }])).toBeNull()
    })

    // The mutation. If the submodule arm is ever dropped, this is what reds.
    it('refuses over a gutted submodule even though the superproject is spotless', () => {
        const reason = releaseRefusal('', gutted)

        expect(reason).not.toBeNull()
        expect(reason).toContain('public/assets: 1211 uncommitted change(s)')
    })

    it('refuses over an uncommitted superproject even though every submodule is clean', () => {
        expect(releaseRefusal(' M scripts/worktree-slots.mjs\n', [])).toMatch(/1 uncommitted change/)
    })

    // Both dirty: the superproject reason wins, because uncommitted work in the tree about to be
    // detached is the more specific hazard and printing both would bury it.
    it('leads with the superproject when both are dirty', () => {
        const reason = releaseRefusal(' M app/Foo.php\n', gutted)

        expect(reason).toMatch(/^the tree has 1 uncommitted change/)
        expect(reason).not.toContain('public/assets')
    })

    it('tolerates a caller that passes no submodule state at all', () => {
        expect(releaseRefusal('', undefined)).toBeNull()
    })
})

describe('slotOccupancy', () => {
    // A slot at rest is detached, never on `main` — git allows a branch in one worktree only, so
    // two slots could not both rest on it.
    it('calls a detached slot free and a branched slot held', () => {
        expect(slotOccupancy({ branch: null, detached: true })).toBe('free')
        expect(slotOccupancy({ branch: '1946-add-worktree-slots' })).toBe('held')
    })
})

describe('installStaleReason', () => {
    it('is silent when the stamp matches the lockfile', () => {
        expect(installStaleReason('abc123', 'abc123')).toBeNull()
    })

    it('reports a moved lockfile with both hashes', () => {
        expect(installStaleReason('bbbbbbbbbbbbbb', 'aaaaaaaaaaaaaa')).toMatch(/lockfile changed/)
    })

    // A missing stamp is unknown, not fresh. Reinstalling costs minutes; a false "fresh" is the
    // silent-wrong-versions failure the script exists to prevent, so the asymmetry is deliberate.
    it('treats a missing stamp as stale rather than current', () => {
        expect(installStaleReason('abc123', null)).toBe('no record of a previous install')
    })

    // Nothing declared, nothing to verify — a repo with no lockfile is not perpetually stale.
    it('is silent when there is no lockfile at all', () => {
        expect(installStaleReason(null, null)).toBeNull()
    })
})

describe('driftReasons', () => {
    it('reports nothing for a current slot', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: 'abc123',
            patchHash: 'p111',
            stampPatchHash: 'p111',
            submodules: [{ path: 'content', recorded: 'aaa111', actual: 'aaa111' }],
        })).toEqual([])
    })

    // UAMS-Web/uams-statamic-archive#2020 — the false green this check exists for. A patch is declared in composer.json's
    // `extra.patches`, so adding one moves NEITHER lockfile, and `composer install` on a tree whose
    // vendor/ already matches the lock installs nothing and therefore patches nothing. Both hashes
    // below agree; only the patch set moved, and the tree is genuinely missing a patch.
    it('reports a changed patch set while both lock files agree', () => {
        const reasons = driftReasons({
            lockHash: 'abc123',
            stampHash: 'abc123',
            composerHash: 'def456',
            stampComposerHash: 'def456',
            patchHash: 'p222222222222',
            stampPatchHash: 'p111111111111',
            submodules: [],
        })

        expect(reasons).toEqual(['patches: patch set changed (p11111111111 -> p22222222222)'])
    })

    // A tree that predates this check cannot prove its patches are applied, so it must read stale.
    // The opposite default would certify exactly the trees most likely to be wrong.
    it('reports an unstamped patch set as stale', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: 'abc123',
            patchHash: 'p111',
            stampPatchHash: null,
            submodules: [],
        })).toEqual(['patches: no record of previously applied patches'])
    })

    // Nothing declared, nothing to verify — a repo with no patches must not report permanent drift.
    it('reports nothing when the tree declares no patches', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: 'abc123',
            patchHash: null,
            stampPatchHash: null,
            submodules: [],
        })).toEqual([])
    })

    // The CI slot is runner-owned: `local-ci.mjs` runs its own UAMS-Web/uams-statamic-archive#1743 patch check there, and this
    // script does not install into it at all. Reporting patch drift would make `status --check`
    // permanently red for a tree nobody here bootstraps — the UAMS-Web/uams-statamic-archive#1952 failure, re-introduced.
    it('exempts a tree this script does not bootstrap from the patch check', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: null,
            patchHash: 'p111',
            stampPatchHash: null,
            submodules: [],
            expected: expectationsFor('ci'),
        })).toEqual([])
    })

    // The uninitialized case is the one that produces a wall of null-method errors across
    // unrelated suites and reads as a real regression, so it must be named explicitly.
    it('names an uninitialized submodule', () => {
        const reasons = driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: null }],
        })

        expect(reasons).toEqual(['content: not initialized'])
    })

    it('reports a submodule sitting at the wrong revision', () => {
        const [reason] = driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111bbb', actual: 'ccc222ddd' }],
        })

        expect(reason).toMatch(/^content: expected aaa111bbb, found ccc222ddd$/)
    })

    // A submodule the branch does not record is not drift — it is simply absent from this tree.
    it('ignores a submodule with no recorded gitlink', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'public/assets', recorded: null, actual: null }],
        })).toEqual([])
    })

    // The exact condition that made `status --check` permanently red: the CI slot carries no stamp
    // and two uninitialized asset submodules, every one of them the state the rule prescribes
    // (`content` and `users` are the two the runner initializes; UAMS-Web/uams-statamic-archive#2477 added the second).
    it('reports nothing for a CI slot in its prescribed state', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: null,
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: 'aaa111' },
                { path: 'users', recorded: 'bbb222', actual: 'bbb222' },
                { path: 'public/assets', recorded: 'ccc333', actual: null },
                { path: 'app/private/assets', recorded: 'ddd444', actual: null },
            ],
            expected: expectationsFor('ci'),
        })).toEqual([])
    })

    // Narrowing what counts must not silence the check: the runner does need `content`.
    it('still reports a missing content/ on the CI slot', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: null,
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: null },
                { path: 'users', recorded: 'bbb222', actual: 'bbb222' },
            ],
            expected: expectationsFor('ci'),
        })).toEqual(['content: not initialized'])
    })

    // And `users` since UAMS-Web/uams-statamic-archive#2477: a CI slot that stopped checking it out is drift, not the exemption.
    it('reports a missing users/ on the CI slot', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: null,
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: 'aaa111' },
                { path: 'users', recorded: 'bbb222', actual: null },
            ],
            expected: expectationsFor('ci'),
        })).toEqual(['users: not initialized'])
    })

    // Same input as the prescribed-state case above, judged as a slot: three reasons (the missing
    // stamp and the two asset submodules). This is the pair that shows the exemption is the
    // expectation set doing the work, not a weakened check.
    it('judges the same tree strictly when it is a slot', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: null,
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: 'aaa111' },
                { path: 'users', recorded: 'bbb222', actual: 'bbb222' },
                { path: 'public/assets', recorded: 'ccc333', actual: null },
                { path: 'app/private/assets', recorded: 'ddd444', actual: null },
            ],
            expected: expectationsFor('slot'),
        })).toHaveLength(3)
    })

    it('accumulates every reason rather than stopping at the first', () => {
        expect(driftReasons({
            lockHash: 'bbb',
            stampHash: 'aaa',
            submodules: [
                { path: 'content', recorded: 'aaa111', actual: null },
                { path: 'users', recorded: 'bbb222', actual: 'ccc333' },
            ],
        })).toHaveLength(3)
    })

    // UAMS-Web/uams-statamic-archive#2023 — the exact state that reported zero drift. Both submodules sat at precisely the
    // revisions their gitlinks recorded, so the revision check had nothing to say, while every
    // tracked file underneath had been deleted. `actual === recorded` is the whole trap.
    it('reports a dirty working tree even when the revision matches the gitlink', () => {
        expect(driftReasons({
            lockHash: 'abc123',
            stampHash: 'abc123',
            submodules: [
                { path: 'public/assets', recorded: 'ccc333', actual: 'ccc333', worktree: { tracked: 1211, untracked: 0 } },
                { path: 'app/private/assets', recorded: 'ddd444', actual: 'ddd444', worktree: { tracked: 485, untracked: 0 } },
            ],
        })).toEqual([
            'public/assets: 1211 uncommitted change(s)',
            'app/private/assets: 485 uncommitted change(s)',
        ])
    })

    // Two independent facts, so two reasons. Folding them into one line would let the revision
    // mismatch hide destroyed contents, or the reverse — and either half can be the whole story.
    it('keeps a revision mismatch and a dirty working tree individually readable', () => {
        const reasons = driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111bbb', actual: 'ccc222ddd', worktree: { tracked: 7, untracked: 0 } }],
        })

        expect(reasons).toEqual([
            'content: expected aaa111bbb, found ccc222ddd',
            'content: 7 uncommitted change(s)',
        ])
    })

    // Untracked residue is reported so it is visible, but it is deliberately NOT what refuses a
    // release — see `submoduleReleaseRefusalReason`.
    it('names untracked paths distinctly from tracked changes', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [
                { path: 'public/assets', recorded: 'ccc333', actual: 'ccc333', worktree: { tracked: 0, untracked: 1 } },
                { path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: { tracked: 4, untracked: 2 } },
            ],
        })).toEqual([
            'public/assets: 1 untracked path(s)',
            'content: 4 uncommitted change(s), 2 untracked path(s)',
        ])
    })

    it('says nothing about a submodule whose working tree is clean', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: { tracked: 0, untracked: 0 } }],
        })).toEqual([])
    })

    // An uninitialized submodule has no working tree to probe, so `submoduleStateOf` skips the
    // status call entirely and leaves `worktree` null. If that null ever became a zero-count
    // object, nothing here would change — but if the probe were run anyway it would report the
    // SUPERPROJECT's dirty files, and this asserts the one reason that is true.
    it('reports only absence for an uninitialized submodule, never a working-tree count', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: null, worktree: null }],
        })).toEqual(['content: not initialized'])
    })

    // "Could not tell" is drift, not health. A corrupt index makes `git status` exit non-zero, and
    // reading that as clean would fail open in the one guard that exists to be closed.
    it('reports a submodule whose working tree could not be read', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: 'aaa111', worktree: null, unreadable: true }],
        })).toEqual(['content: working tree could not be read'])
    })

    // The revision can still be perfectly readable when the status is not, so the two must not
    // collapse into one another.
    it('keeps an unreadable working tree separate from a revision mismatch', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111bbb', actual: 'ccc222ddd', worktree: null, unreadable: true }],
        })).toEqual([
            'content: expected aaa111bbb, found ccc222ddd',
            'content: working tree could not be read',
        ])
    })

    // An absent submodule is already fully described by "not initialized"; adding a second line
    // about an unread working tree would be noise about a directory that has none.
    it('does not also call an uninitialized submodule unreadable', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: null, worktree: null, unreadable: false }],
        })).toEqual(['content: not initialized'])
    })

    // A tree from before this check existed carries no `worktree` key at all. It must read clean
    // rather than throwing — the opposite of the install-stamp asymmetry, because here a missing
    // value means "not probed", not "unverified".
    it('tolerates a probe that predates the working-tree check', () => {
        expect(driftReasons({
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: 'aaa111' }],
        })).toEqual([])
    })
})

describe('submoduleRevision', () => {
    it('accepts a revision when the directory is its own toplevel', () => {
        expect(submoduleRevision('/repo/content', '/repo/content', 'aaa111')).toBe('aaa111')
    })

    // The bug this exists for: `git -C <empty submodule dir> rev-parse HEAD` does not fail, it
    // walks up to the enclosing superproject and answers for that. Observed against the local-CI
    // worktree, where three uninitialized submodules all reported the superproject's own HEAD —
    // which read as a revision mismatch while the real state was "absent".
    it('rejects a superproject answer from an uninitialized submodule directory', () => {
        expect(submoduleRevision('/repo/users', '/repo', '163b300df')).toBeNull()
    })

    it('normalizes separators and casing before comparing', () => {
        expect(submoduleRevision('C:\\repo\\content', 'C:/repo/content', 'aaa111')).toBe('aaa111')
    })

    it('is null when git could not answer at all', () => {
        expect(submoduleRevision('/repo/content', null, null)).toBeNull()
    })
})

describe('checkVerdictLine', () => {
    // `--check` used to carry its verdict only in an exit code, and the ergonomic
    // invocation throws that away: `… --check 2>&1 | tail -6; echo $?` reports
    // `tail`'s status. Five sessions across four repositories hit that on this one
    // command inside ten minutes, two of them publishing a drift-free verdict that
    // was wrong (UAMS-Web/uams-statamic-archive#2282). The line exists so a pipe cannot be the last thing to speak.
    it('names every drifted tree, so a piped run still shows the verdict', () => {
        expect(checkVerdictLine(['uams-statamic', 'uams-statamic-a'])).toBe(
            'worktree-slots: DRIFT — 2 tree(s): uams-statamic, uams-statamic-a'
        )
        expect(checkVerdictLine(['uams-statamic-ci'])).toBe(
            'worktree-slots: DRIFT — 1 tree(s): uams-statamic-ci'
        )
    })

    // The other arm. A verdict line that only ever says DRIFT would pass a test
    // written against the drifted machine this was built on and tell a reader
    // nothing on the day their trees are clean.
    it('reports clean when nothing drifted', () => {
        expect(checkVerdictLine([])).toBe('worktree-slots: CLEAN — no drift in any tree')
    })
})

describe('releaseRefusalReason', () => {
    it('permits a release from a clean tree', () => {
        expect(releaseRefusalReason('')).toBeNull()
        expect(releaseRefusalReason('   \n  \n')).toBeNull()
    })

    // Releasing detaches the tree. Uncommitted work would be stranded where the next claim will
    // not look for it, and nothing would report that it had happened.
    it('refuses a dirty tree and names the offending files', () => {
        const reason = releaseRefusalReason(' M scripts/worktree-slots.mjs\n?? notes.txt\n')

        expect(reason).toMatch(/2 uncommitted change/)
        expect(reason).toContain('scripts/worktree-slots.mjs')
        expect(reason).toContain('notes.txt')
    })

    // A tree left in a bad state can carry hundreds of entries; the refusal has to stay readable.
    it('truncates a long change list but reports the true count', () => {
        const reason = releaseRefusalReason(Array.from({ length: 30 }, (_, i) => ` M file-${i}.php`).join('\n'))

        expect(reason).toMatch(/30 uncommitted change/)
        expect(reason).toMatch(/… and 25 more/)
    })
})

describe('provisionSteps', () => {
    const root = 'C:/Users/dev/Herd/uams-statamic'
    const path = 'C:/Users/dev/Herd/uams-statamic-importer'

    const indexOfCommand = (steps, command, ...args) =>
        steps.findIndex((step) => step.command === command && args.every((arg) => step.args.includes(arg)))

    // The bug this guards (UAMS-Web/uams-statamic-archive#1999): `composer install` runs `post-autoload-dump` →
    // `artisan statamic:install`, which scaffolds `content/*/.gitkeep`. Git will not clone a
    // submodule into a non-empty directory, so installing first left every NEW slot without
    // `content` — while the other three submodules cloned, so the tree looked provisioned and
    // failed later as a wall of null-method errors.
    it('initializes submodules before composer install', () => {
        const steps = provisionSteps(root, path)

        const submodules = indexOfCommand(steps, 'git', 'submodule', 'update')
        const composer = indexOfCommand(steps, 'composer', 'install')

        expect(submodules).toBeGreaterThan(-1)
        expect(composer).toBeGreaterThan(-1)
        expect(submodules).toBeLessThan(composer)
    })

    // Submodules cannot be populated before the tree they live in exists.
    it('creates the worktree before initializing its submodules', () => {
        const steps = provisionSteps(root, path)

        expect(indexOfCommand(steps, 'git', 'worktree', 'add'))
            .toBeLessThan(indexOfCommand(steps, 'git', 'submodule', 'update'))
    })

    // Every submodule the app declares, not just `content` — `users` carries the login accounts
    // and the two asset modules carry the media the served slot renders.
    it('initializes all five declared submodules in one call', () => {
        const steps = provisionSteps(root, path)
        const submodules = steps[indexOfCommand(steps, 'git', 'submodule', 'update')]

        expect(submodules.args).toEqual(
            expect.arrayContaining(['content', 'users', 'resources/forms', 'public/assets', 'app/private/assets'])
        )
    })

    // Each step names the tree it must run in; a step that inherited the caller's cwd would
    // install into whichever tree the session happened to be sitting in.
    it('runs each step in an explicit working directory', () => {
        for (const step of provisionSteps(root, path)) {
            expect([root, path]).toContain(step.cwd)
        }
    })
})

// UAMS-Web/uams-statamic-archive#2148. The five checks that preceded this one all judge a tree's ENVIRONMENT — installs, patches,
// submodules — and none looked at the commit the tree sits on, so a slot released a week ago read
// identically to one released a minute ago. These assert the two halves that decide it: which trees
// are in scope at all, and what a base reading means once taken.
describe('base drift', () => {
    it('reports how far a tree at rest is behind, and names the verb that fixes it', () => {
        expect(baseState(baseProbe(null, '12')).reason)
            .toBe('base: 12 behind origin/main - run `release`')
    })

    it('says nothing about a tree already at its base', () => {
        expect(baseState(baseProbe(null, '0'))).toEqual({ reason: null, notice: null })
    })

    it('judges the primary by its branch rather than exempting it', () => {
        expect(baseState(baseProbe('main', '5')).reason)
            .toBe('base: 5 behind origin/main - run `release`')
    })

    // The load-bearing negative. A slot carrying a ticket branch is EXPECTED to fall behind, and
    // bringing it current is `sync-pr-branch`'s business. Reporting it here would flag every branch
    // in the repo the moment `main` moved, which is how a guard becomes noise people learn to skip.
    it('does not report a slot on a branch as drifted for being behind', () => {
        const probe = baseProbe('2148-report-base-drift', '40')

        expect(probe.tracked).toBe(false)
        expect(baseState(probe)).toEqual({ reason: null, notice: null })
    })

    // Reported so it is not silent, NOT faulted so it cannot red `--check` on a checkout that is
    // whose remote-tracking ref is merely stale. Faulting a failed measurement is the UAMS-Web/uams-statamic-archive#1952 shape; hiding it is UAMS-Web/uams-statamic-archive#2148's.
    it('reports an unmeasurable base as a notice rather than as drift', () => {
        const { reason, notice } = baseState(baseProbe(null, null))

        expect(reason).toBeNull()
        expect(notice).toBe('base: position against origin/main could not be measured')
    })

    // The notice must not name a cause. A missing ref and a failed git call are the same null here,
    // and `wordpress-importer#880` is the sibling shipping "no origin/main ref" where it exists.
    it('does not state why the base could not be measured', () => {
        const { notice } = baseState(baseProbe(null, 'fatal: bad revision'))

        expect(notice).not.toMatch(/no .*ref|missing|absent/i)
    })

    it('reads an absent probe as clean, not as unverified', () => {
        expect(baseState(null)).toEqual({ reason: null, notice: null })
        expect(baseState(undefined)).toEqual({ reason: null, notice: null })
    })

    // The wiring is what a unit test of either half cannot catch — with the probe dropped from the
    // call site every tree reads clean and the suite stays green. Same seam as UAMS-Web/uams-statamic-archive#1952.
    it('surfaces base drift through judgeTree, so dropping the wiring is caught', () => {
        const root = normalizePath('C:/Users/dev/Herd/uams-statamic')
        const { drift } = judgeTree(`${root}-a`, root, {
            lockHash: 'abc123',
            stampHash: 'abc123',
            composerHash: 'def456',
            stampComposerHash: 'def456',
            submodules: [],
            base: baseProbe(null, '12'),
        })

        expect(drift).toContain('base: 12 behind origin/main - run `release`')
    })

    // The notice reaches the caller the same way, and stays out of `drift` so `--check` ignores it.
    it('surfaces an unmeasurable base as a notice through judgeTree, not as drift', () => {
        const root = normalizePath('C:/Users/dev/Herd/uams-statamic')
        const { drift, notices } = judgeTree(`${root}-a`, root, {
            lockHash: 'abc123',
            stampHash: 'abc123',
            composerHash: 'def456',
            stampComposerHash: 'def456',
            submodules: [],
            base: baseProbe(null, null),
        })

        expect(drift).toEqual([])
        expect(notices).toEqual(['base: position against origin/main could not be measured'])
    })

    it('leaves the runner-owned CI slot base alone', () => {
        const root = normalizePath('C:/Users/dev/Herd/uams-statamic')
        const { drift, notices } = judgeTree(`${root}-ci`, root, {
            lockHash: null,
            stampHash: null,
            submodules: [{ path: 'content', recorded: 'aaa111', actual: 'aaa111' }],
            base: baseProbe(null, '99'),
        })

        expect(drift).toEqual([])
        expect(notices).toEqual([])
    })
})

// The drift line names a command, and a command that the same script REFUSES for that tree is
// worse than naming none — the reader runs it, is told no, and has no next step. UAMS-Web/uams-statamic-archive#2148 shipped
// `release` unconditionally; `release primary` is refused by design, because releasing detaches
// and the primary must keep `main` checked out. These assert the remedy is executable for the kind
// it is addressed to, which is the property the original tests missed: they checked that drift was
// REPORTED, never that what it prescribed could be run.
describe('base drift names a remedy that fits the tree kind', () => {
    it('tells a slot at rest to release, which re-detaches it at origin/main', () => {
        expect(baseState(baseProbe(null, '3', 'slot')).reason)
            .toBe('base: 3 behind origin/main - run `release`')
    })

    it('does NOT tell the primary to release, because the script refuses that', () => {
        const { reason } = baseState(baseProbe('main', '3', 'primary'))

        expect(reason).not.toContain('run `release`')
        expect(reason).toBe('base: 3 behind origin/main - run `git pull --ff-only origin main`')
    })

    // Guards the coupling rather than the string: whatever the primary's remedy becomes, it must
    // never be the verb `release`, which `commandRelease` rejects for this kind.
    it('never prescribes release for the primary, whatever the distance', () => {
        for (const behind of ['1', '7', '204']) {
            expect(baseState(baseProbe('main', behind, 'primary')).reason).not.toMatch(/`release`/)
        }
    })
})

describe('pinnedDependencyNotice', () => {
    it('says nothing when the lockfile named no pinned version', () => {
        expect(pinnedDependencyNotice({})).toBeNull()
    })

    it('reports an unset clone variable rather than going quiet', () => {
        // The check silently skipping is the failure this exists to prevent, so an unconfigured
        // machine has to say so rather than print a clean table.
        expect(pinnedDependencyNotice({ version: 'v0.16.0' })).toBe(
            'aether-wordpress-importer pinned v0.16.0 - upstream distance unmeasured (UAMS_WORDPRESS_IMPORTER_CLONE unset)',
        )
    })

    it('distinguishes an unreadable clone from an unset one', () => {
        expect(pinnedDependencyNotice({ version: 'v0.16.0', clone: '/nope' })).toBe(
            'aether-wordpress-importer pinned v0.16.0 - upstream distance unreadable in /nope',
        )
    })

    it('reports the distance when the clone answers', () => {
        expect(pinnedDependencyNotice({ version: 'v0.16.0', clone: '/c', ahead: 139 })).toBe(
            'aether-wordpress-importer pinned v0.16.0 - upstream main +139',
        )
    })

    it('reads a pin level with upstream as level, not as +0', () => {
        expect(pinnedDependencyNotice({ version: 'v0.17.0', clone: '/c', ahead: 0 })).toBe(
            'aether-wordpress-importer pinned v0.17.0 - upstream main level',
        )
    })

    it('qualifies the count when the clone has not fetched recently', () => {
        // An unqualified number from an un-fetched clone is the same class of claim this notice
        // exists to make impossible.
        expect(pinnedDependencyNotice({ version: 'v0.16.0', clone: '/c', ahead: 139, staleDays: 6 })).toBe(
            'aether-wordpress-importer pinned v0.16.0 - upstream main +139, clone fetched 6d ago',
        )
    })

    it('does not qualify a clone fetched within the day', () => {
        expect(pinnedDependencyNotice({ version: 'v0.16.0', clone: '/c', ahead: 2, staleDays: 0 })).toBe(
            'aether-wordpress-importer pinned v0.16.0 - upstream main +2',
        )
    })
})

describe('pinnedDependencyGrouping', () => {
    const tree = (name, version) => ({ name, pinned: { version } })

    it('reports one shared pin when every tree agrees', () => {
        const { shared, divergent } = pinnedDependencyGrouping([tree('a', 'v0.16.0'), tree('b', 'v0.16.0')])

        expect(shared).toBe('v0.16.0')
        expect(divergent).toEqual([])
    })

    it('names only the trees that differ from the majority', () => {
        const { shared, divergent } = pinnedDependencyGrouping([
            tree('a', 'v0.16.0'),
            tree('b', 'v0.16.0'),
            tree('c', 'v0.17.0'),
        ])

        expect(shared).toBe('v0.16.0')
        expect(divergent).toEqual([{ name: 'c', version: 'v0.17.0' }])
    })

    it('ignores trees whose lockfile could not be read rather than counting them as divergent', () => {
        const { shared, divergent } = pinnedDependencyGrouping([tree('a', 'v0.16.0'), tree('ci', null)])

        expect(shared).toBe('v0.16.0')
        expect(divergent).toEqual([])
    })

    it('reports nothing when no tree yielded a version', () => {
        expect(pinnedDependencyGrouping([tree('a', null)])).toEqual({ shared: null, divergent: [] })
    })
})

// UAMS-Web/uams-statamic-archive#2273 — `release` refused on work that would be LOST and not on a tree that was merely TAKEN.
describe('heldSlotRefusalReason', () => {
    // AC3. A slot at rest is detached, so the ordinary release of a finished slot must stay a
    // no-op — a guard that broke this would make the normal path require a flag.
    it.each([[null], [''], [undefined]])('permits a release when the slot is detached (%p)', (branch) => {
        expect(heldSlotRefusalReason(branch, null)).toBeNull()
    })

    // AC1. This is the whole defect: clean-on-a-branch is the normal state for most of a ticket,
    // and it was releasable by anyone.
    it('refuses a slot that is on a branch, naming it', () => {
        const reason = heldSlotRefusalReason('2273-refuse-releasing-a-held-slot', null)

        expect(reason).toContain('2273-refuse-releasing-a-held-slot')
        expect(reason).toContain('another session')
    })

    it('permits the release when the caller names that exact branch', () => {
        expect(heldSlotRefusalReason('2273-x', '2273-x')).toBeNull()
    })

    // Naming the WRONG branch must not pass. Without this, `--branch` degrades into a bare
    // "yes I am sure" flag and stops carrying any information about which tree is being taken.
    it('refuses when the caller names a different branch, and says which is which', () => {
        const reason = heldSlotRefusalReason('actually-this', 'thought-it-was-that')

        expect(reason).toContain('actually-this')
        expect(reason).toContain('thought-it-was-that')
    })

    it('is not satisfied by a substring or a prefix of the branch name', () => {
        expect(heldSlotRefusalReason('2273-refuse-releasing', '2273')).not.toBeNull()
        expect(heldSlotRefusalReason('2273', '2273-refuse-releasing')).not.toBeNull()
    })
})

describe('releaseRaceAbortReason', () => {
    it('permits the detach when the tree is unchanged', () => {
        expect(releaseRaceAbortReason('', '')).toBeNull()
        expect(releaseRaceAbortReason(' M a.php', ' M a.php')).toBeNull()
    })

    it('aborts when work appeared after the tree was judged', () => {
        expect(releaseRaceAbortReason('', ' M a.php')).toContain('changed')
    })

    it('aborts when work disappeared after the tree was judged', () => {
        // Directionless on purpose. The guard's job is to notice that the state it approved is no
        // longer the state it is about to act on, not to decide which direction is dangerous.
        expect(releaseRaceAbortReason(' M a.php', '')).toContain('changed')
    })

    it('ignores only surrounding whitespace, not a real difference', () => {
        expect(releaseRaceAbortReason(' M a.php\n', ' M a.php')).toBeNull()
        expect(releaseRaceAbortReason(' M a.php', ' M b.php')).not.toBeNull()
    })
})

describe('flagValue', () => {
    it('reads a spaced pair', () => {
        expect(flagValue(['release', 'a', '--branch', 'x'], '--branch')).toBe('x')
    })

    it('reads an inline pair', () => {
        expect(flagValue(['release', 'a', '--branch=x'], '--branch')).toBe('x')
    })

    it('returns null when the flag is absent', () => {
        expect(flagValue(['release', 'a'], '--branch')).toBeNull()
    })

    // A dangling `--branch` must not swallow the next flag as its value. Reading `--json` as a
    // branch name would turn a deliberate release into a refusal naming a branch nobody typed.
    it('does not consume a following flag as the value', () => {
        expect(flagValue(['release', 'a', '--branch', '--json'], '--branch')).toBeNull()
        expect(flagValue(['release', 'a', '--branch'], '--branch')).toBeNull()
    })

    it('treats an empty inline value as absent', () => {
        expect(flagValue(['--branch='], '--branch')).toBeNull()
    })
})

describe('installFailureReport (#2692)', () => {
    const packages = ['laravel/framework', 'statamic/cms', 'symfony/console']

    it('names a tree that cannot boot, the error, the removed packages, and the remedy', () => {
        const report = installFailureReport('uams-statamic-a', {
            before: packages,
            after: ['laravel/framework'],
            probe: { ran: true, ok: false, detail: 'Failed opening required vendor/symfony/console/Application.php' },
        })

        expect(report).toMatch(/uams-statamic-a CANNOT BOOT/)
        expect(report).toMatch(/Failed opening required vendor\/symfony\/console/)
        expect(report).toMatch(/missing 2 packages .*statamic\/cms, symfony\/console/)
        expect(report).toMatch(/Re-run `composer install --no-interaction` in uams-statamic-a/)
    })

    it('reports an intact tree as unchanged, with no remedy to run', () => {
        const report = installFailureReport('uams-statamic-a', {
            before: packages,
            after: packages,
            probe: { ran: true, ok: true, detail: '' },
        })

        expect(report).toMatch(/is unchanged and still boots/)
        expect(report).not.toMatch(/Re-run/)
        expect(report).not.toMatch(/CANNOT BOOT/)
    })

    it('does not call a tree that still boots unchanged when packages went missing', () => {
        const report = installFailureReport('uams-statamic-a', {
            before: packages,
            after: ['laravel/framework', 'statamic/cms'],
            probe: { ran: true, ok: true, detail: '' },
        })

        expect(report).toMatch(/still boots, but it is not the tree the install started from/)
        expect(report).toMatch(/missing 1 package that was there before the refresh began: symfony\/console/)
        expect(report).not.toMatch(/unchanged/)
    })

    it('never reads a probe that could not run as a healthy tree', () => {
        const report = installFailureReport('uams-statamic-a', {
            before: packages,
            after: packages,
            probe: { ran: false, ok: false, detail: 'spawnSync php ENOENT' },
        })

        expect(report).toMatch(/boot probe could not run \(spawnSync php ENOENT\)/)
        expect(report).toMatch(/unknown/)
        expect(report).not.toMatch(/unchanged/)
        expect(report).not.toMatch(/and still boots/)
    })
})

describe('vendorPackages (#2692)', () => {
    it('lists vendor/package directories, skipping bin and composer', () => {
        const tree = mkdtempSync(join(tmpdir(), 'wts-vendor-'))

        try {
            for (const dir of ['vendor/laravel/framework', 'vendor/bin', 'vendor/composer', 'vendor/statamic/cms']) {
                mkdirSync(join(tree, dir), { recursive: true })
            }
            writeFileSync(join(tree, 'vendor/autoload.php'), '<?php')

            expect(vendorPackages(tree)).toEqual(['laravel/framework', 'statamic/cms'])
        } finally {
            rmSync(tree, { recursive: true, force: true })
        }
    })

    it('returns nothing for a tree with no vendor directory', () => {
        const tree = mkdtempSync(join(tmpdir(), 'wts-vendor-'))

        try {
            expect(vendorPackages(tree)).toEqual([])
        } finally {
            rmSync(tree, { recursive: true, force: true })
        }
    })
})

describe('bootProbe (#2692)', () => {
    it('reports a PHP that cannot be started as not having run', () => {
        const tree = mkdtempSync(join(tmpdir(), 'wts-probe-'))

        try {
            const probe = bootProbe(tree, 'definitely-not-a-php-binary-2692')

            expect(probe.ran).toBe(false)
            expect(probe.ok).toBe(false)
        } finally {
            rmSync(tree, { recursive: true, force: true })
        }
    })

    it('reports a tree whose artisan cannot load its autoloader as unable to boot', () => {
        const tree = mkdtempSync(join(tmpdir(), 'wts-probe-'))

        try {
            writeFileSync(join(tree, 'artisan'), "<?php\nrequire __DIR__.'/vendor/autoload.php';\n")

            const probe = bootProbe(tree)

            expect(probe.ran).toBe(true)
            expect(probe.ok).toBe(false)
            expect(probe.detail).toMatch(/vendor\/autoload\.php/)
        } finally {
            rmSync(tree, { recursive: true, force: true })
        }
    })

    it('reports a tree whose artisan runs as booting', () => {
        const tree = mkdtempSync(join(tmpdir(), 'wts-probe-'))

        try {
            writeFileSync(join(tree, 'artisan'), "<?php\necho 'Laravel Framework 12';\n")

            expect(bootProbe(tree)).toEqual({ ran: true, ok: true, detail: '' })
        } finally {
            rmSync(tree, { recursive: true, force: true })
        }
    })
})

describe('vendor source checkouts that block composer install (#2691)', () => {
    const git = (cwd, ...args) => execFileSync('git', ['-c', 'user.name=t', '-c', 'user.email=t@example.test', '-c', 'init.defaultBranch=main', ...args], { cwd, stdio: 'pipe' })

    /**
     * A tree with three vendor packages: a dist install, a source checkout on a branch its remote
     * carries, and a source checkout on a branch no remote carries.
     */
    function fixtureTree() {
        // The real path: a Windows runner's temp directory is an 8.3 short name, and git reports the long one.
        const root = realpathSync.native(mkdtempSync(join(tmpdir(), 'wts-vendor-git-')))
        const upstream = join(root, 'upstream.git')
        const seed = join(root, 'seed')
        const tree = join(root, 'tree')

        mkdirSync(join(tree, 'vendor/acme/dist'), { recursive: true })
        writeFileSync(join(tree, 'vendor/acme/dist/composer.json'), '{}')

        git(root, 'init', '--bare', '-q', upstream)
        git(root, 'init', '-q', seed)
        writeFileSync(join(seed, 'README.md'), 'seed')
        git(seed, 'add', '.')
        git(seed, 'commit', '-q', '-m', 'seed')
        git(seed, 'push', '-q', upstream, 'main')

        mkdirSync(join(tree, 'vendor/acme'), { recursive: true })
        git(root, 'clone', '-q', upstream, join(tree, 'vendor/acme/on-remote'))
        git(root, 'clone', '-q', upstream, join(tree, 'vendor/acme/local-only'))
        git(join(tree, 'vendor/acme/local-only'), 'checkout', '-q', '-b', 'importer-1234')

        return { root, tree }
    }

    it('reports only the checkout on a branch no remote carries', () => {
        const { root, tree } = fixtureTree()

        try {
            const checkouts = vendorCheckoutsOf(tree)
            const byPath = Object.fromEntries(checkouts.map((checkout) => [checkout.path, checkout]))

            // The dist install has no .git and is not probed at all
            expect(Object.keys(byPath).sort()).toEqual(['vendor/acme/local-only', 'vendor/acme/on-remote'])
            expect(byPath['vendor/acme/on-remote']).toMatchObject({ branch: 'main', blocking: false, unreadable: false })
            expect(byPath['vendor/acme/local-only']).toMatchObject({ branch: 'importer-1234', blocking: true, unreadable: false })
            expect(checkouts.map(vendorCheckoutReason).filter(Boolean)).toEqual([
                "vendor/acme/local-only: source checkout on branch 'importer-1234', which no remote carries - `composer install` will refuse it",
            ])
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })

    it('reports a detached checkout, the --prefer-source resting state, as clean', () => {
        const { root, tree } = fixtureTree()

        try {
            git(join(tree, 'vendor/acme/local-only'), 'checkout', '-q', '--detach')

            expect(vendorCheckoutsOf(tree).filter(vendorCheckoutReason)).toEqual([])
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })

    it('stops blocking once the branch reaches a remote', () => {
        const { root, tree } = fixtureTree()

        try {
            const checkout = join(tree, 'vendor/acme/local-only')
            git(checkout, 'push', '-q', 'origin', 'importer-1234')

            expect(vendorCheckoutsOf(tree).filter(vendorCheckoutReason)).toEqual([])
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })

    it('treats a git state that could not be read as drift, not health', () => {
        const probe = vendorCheckoutProbe('vendor/acme/x', '/t/vendor/acme/x', '/t/vendor/acme/x', 'main', null)

        expect(probe.unreadable).toBe(true)
        expect(vendorCheckoutReason(probe)).toMatch(/could not be read/)
    })

    it('does not mistake a branch sharing a remote branch prefix for the same branch', () => {
        const probe = vendorCheckoutProbe('vendor/acme/x', '/t/x', '/t/x', 'fix', 'refs/remotes/origin/fix-2\nrefs/remotes/origin/HEAD')

        expect(probe.blocking).toBe(true)
    })

    it('matches a slashed branch name on any remote', () => {
        const probe = vendorCheckoutProbe('vendor/acme/x', '/t/x', '/t/x', 'feature/a', 'refs/remotes/fork/feature/a')

        expect(probe.blocking).toBe(false)
    })

    it('reports the blocking checkout even when both install stamps are current', () => {
        const reasons = driftReasons({
            lockHash: 'a'.repeat(64),
            stampHash: 'a'.repeat(64),
            composerHash: 'b'.repeat(64),
            stampComposerHash: 'b'.repeat(64),
            patchHash: 'c'.repeat(64),
            stampPatchHash: 'c'.repeat(64),
            vendorCheckouts: [{ path: 'vendor/uams-web/aether-wordpress-importer', branch: 'importer-1234', blocking: true, unreadable: false }],
        })

        expect(reasons).toEqual([
            "vendor/uams-web/aether-wordpress-importer: source checkout on branch 'importer-1234', which no remote carries - `composer install` will refuse it",
        ])
    })

    it('names the directory and every recovery step when refresh refuses', () => {
        const text = vendorCheckoutRecovery('uams-statamic', [
            { path: 'vendor/uams-web/aether-wordpress-importer', branch: 'importer-1234', blocking: true, unreadable: false },
        ])

        expect(text).toMatch(/refusing to refresh uams-statamic before `composer install`/)
        expect(text).toMatch(/vendor\/uams-web\/aether-wordpress-importer: source checkout on branch 'importer-1234'/)
        expect(text).toMatch(/move each directory above out of vendor\//)
        expect(text).toMatch(/re-run `node scripts\/worktree-slots\.mjs refresh <tree>`/)
        expect(text).toMatch(/test ! -e <dir>\/\.git/)
    })
})

// UAMS-Web/uams-statamic-archive#2935: `refresh` moved `content/` and left the test-environment copy every content test reads
// built from the old revision, and `status --check` called the slot current.
describe('content mirror drift', () => {
    const drifted = { drifted: true, unreadable: false }
    const current = { drifted: false, unreadable: false }

    it('reads the probe\'s two answers, and anything else as unreadable', () => {
        expect(contentMirrorProbe('current')).toEqual(current)
        expect(contentMirrorProbe('drifted')).toEqual(drifted)

        // A probe that could not run, or printed something else, is never read as current
        expect(contentMirrorProbe(null)).toEqual({ drifted: false, unreadable: true })
        expect(contentMirrorProbe('PHP Fatal error: ...')).toEqual({ drifted: false, unreadable: true })
    })

    it('names the command that rebuilds a drifted mirror', () => {
        expect(contentMirrorReason(drifted)).toBe(
            'content mirror: built from a different content/, rebuild it with `DB_DATABASE=:memory: APP_ENV=testing php artisan test:seed-stache`',
        )
        expect(contentMirrorReason({ drifted: false, unreadable: true })).toBe('content mirror: could not be read')
        expect(contentMirrorReason(current)).toBeNull()

        // Not probed: no autoloader, or no initialized content/, each its own drift line already
        expect(contentMirrorReason(null)).toBeNull()
    })

    it('counts a drifted or unreadable mirror as drift on a slot, and not on the CI slot', () => {
        const base = { lockHash: 'a', stampHash: 'a', composerHash: 'b', stampComposerHash: 'b' }

        expect(driftReasons({ ...base, contentMirror: drifted, expected: expectationsFor('slot') }))
            .toEqual([contentMirrorReason(drifted)])
        expect(driftReasons({ ...base, contentMirror: { drifted: false, unreadable: true }, expected: expectationsFor('primary') }))
            .toEqual(['content mirror: could not be read'])
        expect(driftReasons({ ...base, contentMirror: current, expected: expectationsFor('slot') })).toEqual([])
        expect(driftReasons({ ...base, contentMirror: drifted, expected: expectationsFor('ci') })).toEqual([])
    })

    it('wires the mirror into the verdict judgeTree gives a slot', () => {
        const repoRoot = '/Herd/uams-statamic'
        const { drift } = judgeTree('/Herd/uams-statamic-a', repoRoot, {
            lockHash: 'a', stampHash: 'a', composerHash: 'b', stampComposerHash: 'b', submodules: [], contentMirror: drifted,
        })

        expect(drift).toContain(contentMirrorReason(drifted))
    })

    // The probe runs the app's own ContentMirror::sourceDrift() through PHP, so this needs PHP and
    // this checkout's vendor/. The local gate has both; GitHub's `js` job installs no PHP, and
    // there the case is skipped by name rather than passing on a probe that never ran.
    const autoload = resolve(dirname(fileURLToPath(import.meta.url)), '..', 'vendor', 'autoload.php')
    const php = spawnSync('php', ['-v'], { encoding: 'utf8' }).status === 0 && existsSync(autoload)

    /** Build a mirror of `tree/content` with the app's own rebuild, as test:seed-stache does. */
    const buildMirror = (tree) => {
        const quote = (value) => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`
        const code = `require ${quote(autoload)}; echo App\\Support\\ContentMirror::rebuildIfStale(new Illuminate\\Filesystem\\Filesystem, ${quote(join(tree, 'content'))}, ${quote(join(tree, 'storage', 'framework', 'testing', 'content-mirror'))}) ?? 'built';`
        const result = spawnSync('php', ['-r', code], { encoding: 'utf8' })

        expect(result.stdout).toBe('built')
    }

    it.skipIf(!php)('reports a mirror as drifted after content/ changes, and current otherwise, through PHP', () => {
        const tree = mkdtempSync(join(tmpdir(), 'content-mirror-probe-'))

        try {
            mkdirSync(join(tree, 'content', 'collections'), { recursive: true })
            writeFileSync(join(tree, 'content', 'collections', 'pages.yaml'), 'title: Pages\n')

            // Absent: nothing built yet, which the next test process builds itself
            expect(contentMirrorStateOf(tree, autoload)).toEqual(current)

            buildMirror(tree)
            expect(contentMirrorStateOf(tree, autoload)).toEqual(current)

            // A file added to content/ after the build, as a moved submodule does
            writeFileSync(join(tree, 'content', 'collections', 'posts.yaml'), 'title: Posts\n')
            expect(contentMirrorStateOf(tree, autoload)).toEqual(drifted)

            // Rebuilt, current again
            buildMirror(tree)
            expect(contentMirrorStateOf(tree, autoload)).toEqual(current)

            // No autoloader: not probed
            expect(contentMirrorStateOf(tree, join(tree, 'missing-autoload.php'))).toBeNull()
        } finally {
            rmSync(tree, { recursive: true, force: true })
        }
    })
})

// UAMS-Web/uams-statamic-archive#2938's gate finding: refresh joined the mirror's reason into the base's, and the `else if`
// that printed an unmeasurable base's notice was skipped whenever the mirror had drifted.
describe('refreshOutcome', () => {
    const unreadable = baseState({ tracked: true, behind: null, ref: 'origin/main', unreadable: true })
    const behind = baseState({ tracked: true, behind: 3, ref: 'origin/main', unreadable: false })
    const current = baseState({ tracked: true, behind: 0, ref: 'origin/main', unreadable: false })
    const mirror = contentMirrorReason({ drifted: true, unreadable: false })

    it('starts from baseState answers that carry a reason or a notice, never both', () => {
        expect(unreadable.reason).toBeNull()
        expect(unreadable.notice).toContain('could not be measured')
        expect(behind.reason).toContain('3 behind')
        expect(behind.notice).toBeNull()
    })

    it('keeps an unmeasurable base\'s notice whether or not the mirror drifted', () => {
        // Mirror current: the notice stands alone, as on main
        expect(refreshOutcome(unreadable, null)).toEqual({ reason: null, notice: unreadable.notice })

        // Mirror drifted: the notice rides on the reason instead of being dropped
        const outcome = refreshOutcome(unreadable, mirror)
        expect(outcome.reason).toContain(mirror)
        expect(outcome.reason).toContain('could not be measured')
        expect(outcome.notice).toBeNull()
    })

    it('joins a base reason and a mirror reason', () => {
        expect(refreshOutcome(behind, mirror)).toEqual({ reason: `${behind.reason}; ${mirror}`, notice: null })
        expect(refreshOutcome(behind, null)).toEqual({ reason: behind.reason, notice: null })
    })

    it('reports nothing for a current base and a current mirror', () => {
        expect(refreshOutcome(current, null)).toEqual({ reason: null, notice: null })
        expect(refreshOutcome({ reason: null, notice: null }, mirror)).toEqual({ reason: mirror, notice: null })
    })
})

// UAMS-Web/uams-statamic-archive#3288. With `submodule.recurse=true` in a developer's global config, a bare `checkout` that
// releases a slot across a commit adding a submodule aborts partway, leaving HEAD unmoved and part
// of the target commit in the working tree.
describe('releasing a slot when submodule.recurse is set globally (#3288)', () => {
    const git = (cwd, ...args) => execFileSync('git', ['-c', 'user.name=t', '-c', 'user.email=t@example.test', '-c', 'init.defaultBranch=main', '-c', 'protocol.file.allow=always', ...args], { cwd, stdio: 'pipe' })

    it('turns submodule recursion off for the checkout', () => {
        const args = releaseCheckoutArgs('C:/Users/dev/Herd/uams-statamic-a')

        expect(args.slice(args.indexOf('-c'), args.indexOf('-c') + 2)).toEqual(['-c', 'submodule.recurse=false'])
        expect(args.indexOf('-c')).toBeLessThan(args.indexOf('checkout'))
        expect(args.slice(args.indexOf('checkout'))).toEqual(['checkout', '--detach', 'origin/main'])
    })

    // The primary initializes a submodule the slot's older commit does not have. `.git/config` is
    // shared, so the submodule reads as active in the slot, and a recursing checkout looks for a
    // gitdir under the slot's own worktree directory that does not exist.
    it('releases across a commit that adds a submodule, leaving a clean tree at the target', () => {
        const root = mkdtempSync(join(tmpdir(), 'wts-recurse-'))

        try {
            const sub = join(root, 'sub')
            const primary = join(root, 'primary')
            const slot = join(root, 'slot')

            git(root, 'init', '-q', sub)
            writeFileSync(join(sub, 'file.txt'), 'sub')
            git(sub, 'add', '.')
            git(sub, 'commit', '-q', '-m', 'sub')

            git(root, 'init', '-q', primary)
            writeFileSync(join(primary, 'file.txt'), 'primary')
            git(primary, 'add', '.')
            git(primary, 'commit', '-q', '-m', 'before the submodule')
            const before = git(primary, 'rev-parse', 'HEAD').toString().trim()
            git(primary, 'submodule', 'add', '-q', '../sub', 'sub')
            git(primary, 'commit', '-q', '-m', 'add the submodule')
            const target = git(primary, 'rev-parse', 'HEAD').toString().trim()
            git(primary, 'update-ref', 'refs/remotes/origin/main', target)
            git(primary, 'worktree', 'add', '-q', '--detach', slot, before)

            const result = spawnSync('git', releaseCheckoutArgs(slot), {
                cwd: slot,
                encoding: 'utf8',
                env: { ...process.env, GIT_CONFIG_COUNT: '1', GIT_CONFIG_KEY_0: 'submodule.recurse', GIT_CONFIG_VALUE_0: 'true' },
            })

            expect(result.stderr).not.toMatch(/fatal/)
            expect(result.status).toBe(0)
            expect(git(slot, 'rev-parse', 'HEAD').toString().trim()).toBe(target)
            expect(git(slot, 'status', '--porcelain').toString()).toBe('')
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })
})

// #289. With `submodule.recurse=true` in a developer's global config, a bare `fetch` fetches every
// initialized submodule as well, over the network, though the script never needs those objects.
describe('fetching origin when submodule.recurse is set globally (#289)', () => {
    const git = (cwd, ...args) => execFileSync('git', ['-c', 'user.name=t', '-c', 'user.email=t@example.test', '-c', 'init.defaultBranch=main', '-c', 'protocol.file.allow=always', ...args], { cwd, stdio: 'pipe' })

    it('turns submodule recursion off for the fetch', () => {
        const args = fetchOriginArgs('C:/Users/dev/Herd/uams-statamic-a', '--quiet')

        expect(args).toEqual(['-C', 'C:/Users/dev/Herd/uams-statamic-a', 'fetch', '--no-recurse-submodules', 'origin', '--quiet'])
    })

    it('provisions with the same fetch', () => {
        const fetch = provisionSteps('C:/Users/dev/Herd/uams-statamic', 'C:/Users/dev/Herd/uams-statamic-a')
            .find((step) => step.args.includes('fetch'))

        expect(fetch.args).toEqual(fetchOriginArgs('C:/Users/dev/Herd/uams-statamic'))
    })

    // `refresh` and `release` call `run()` directly, so their arguments cannot be read from outside.
    // Every fetch in the script goes through fetchOriginArgs(), which this pins: a fetch written
    // inline again adds a second `'fetch'` literal.
    it('fetches nowhere else in the script', () => {
        const source = readFileSync(join(dirname(fileURLToPath(import.meta.url)), 'worktree-slots.mjs'), 'utf8')

        expect(source.match(/'fetch'/g)).toHaveLength(1)
        expect(source.match(/fetchOriginArgs\(/g).length).toBeGreaterThanOrEqual(4)
    })

    // The submodule's remote is removed after it is initialized, so a fetch that recursed into it
    // would fail. The control runs the same fetch without the flag and must fail.
    it('fetches a tree whose submodule remote is gone, where a recursing fetch fails', () => {
        const root = mkdtempSync(join(tmpdir(), 'wts-fetch-'))
        const env = { ...process.env, GIT_CONFIG_COUNT: '1', GIT_CONFIG_KEY_0: 'submodule.recurse', GIT_CONFIG_VALUE_0: 'true' }

        try {
            const sub = join(root, 'sub')
            const origin = join(root, 'origin')
            const clone = join(root, 'clone')

            git(root, 'init', '-q', sub)
            writeFileSync(join(sub, 'file.txt'), 'sub')
            git(sub, 'add', '.')
            git(sub, 'commit', '-q', '-m', 'sub')

            git(root, 'init', '-q', origin)
            git(origin, 'submodule', 'add', '-q', '../sub', 'sub')
            git(origin, 'commit', '-q', '-m', 'add the submodule')

            git(root, 'clone', '-q', '--recurse-submodules', origin, clone)
            rmSync(sub, { recursive: true, force: true })

            const recursing = spawnSync('git', ['-C', clone, 'fetch', 'origin'], { cwd: clone, encoding: 'utf8', env })
            const result = spawnSync('git', fetchOriginArgs(clone), { cwd: clone, encoding: 'utf8', env })

            expect(recursing.status).not.toBe(0)
            expect(result.stderr).not.toMatch(/fatal|error/i)
            expect(result.status).toBe(0)
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })
})
