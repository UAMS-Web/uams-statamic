import { afterEach, describe, expect, it } from 'vitest'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import {
    PATCH_STAMP_FILE,
    branchSyncAction,
    describePatches,
    installWithPatchStamp,
} from './local-ci.mjs'
import { patchStaleReason, readPatchLockHash } from './gate-slot.mjs'

// UAMS-Web/uams-statamic-archive#1743 — on a REUSED worktree `composer install` reports "Nothing to install,
// update or remove", so no package-install event fires and a newly added patch is
// never applied. The band then validates the previous patch set and reports green.
// These cover the decision that catches it: does the worktree's last-applied patch
// hash still match the one this branch declares?

const HASH_A = '2cf1f01682c04effddbf0abf4b95ed079d266d0d95b7f03bdb962d6320f9c797'
const HASH_B = 'abe8a777e18c8ef6e4c29fc3080738d720bb7f47862369860c17c45c627b0ba1'

describe('patchStaleReason', () => {
    it('is current when the stamp matches the lock', () => {
        expect(patchStaleReason(HASH_A, HASH_A)).toBeNull()
    })

    it('re-patches when the branch changed the patch set — the #1730 miss', () => {
        // The worktree last applied HASH_B; this branch declares HASH_A.
        const reason = patchStaleReason(HASH_A, HASH_B)
        expect(reason).toBeTruthy()
        expect(reason).toContain(HASH_A.slice(0, 12))
    })

    it('re-patches when nothing records what was applied', () => {
        // A worktree predating the stamp is UNKNOWN, not fresh. Treating unknown as
        // current is precisely the false green this exists to prevent.
        expect(patchStaleReason(HASH_A, null)).toBe('no record of previously applied patches')
        expect(patchStaleReason(HASH_A, '')).toBe('no record of previously applied patches')
    })

    it('does nothing when the project declares no patches', () => {
        // Nothing declared means nothing to verify — not a reason to re-patch.
        expect(patchStaleReason(null, null)).toBeNull()
        expect(patchStaleReason(null, HASH_A)).toBeNull()
    })
})

describe('readPatchLockHash', () => {
    it('reads the _hash from a real lock shape', () => {
        const raw = JSON.stringify({ _hash: HASH_A, patches: { 'statamic/cms': [] } })
        expect(readPatchLockHash(raw)).toBe(HASH_A)
    })

    it('returns null for missing, malformed, or hash-less input', () => {
        // Non-fatal by design: an unreadable lock must not fail the whole run.
        expect(readPatchLockHash(null)).toBeNull()
        expect(readPatchLockHash('')).toBeNull()
        expect(readPatchLockHash('{ not json')).toBeNull()
        expect(readPatchLockHash(JSON.stringify({ patches: {} }))).toBeNull()
        expect(readPatchLockHash(JSON.stringify({ _hash: 42 }))).toBeNull()
    })
})

describe('describePatches', () => {
    it('names every patch as "package: description", sorted by package', () => {
        const raw = JSON.stringify({
            _hash: HASH_A,
            patches: {
                'statamic/cms': [
                    { package: 'statamic/cms', description: 'Add blueprint-aware collection create labels', url: 'patches/a.patch' },
                    { package: 'statamic/cms', description: 'Align CP updates badge', url: 'patches/b.patch' },
                ],
                'pestphp/pest-plugin-mutate': [
                    { package: 'pestphp/pest-plugin-mutate', description: 'Read the php-code-coverage 14 format', url: 'patches/c.patch' },
                ],
            },
        })

        expect(describePatches(raw)).toEqual([
            'pestphp/pest-plugin-mutate: Read the php-code-coverage 14 format',
            'statamic/cms: Add blueprint-aware collection create labels',
            'statamic/cms: Align CP updates badge',
        ])
    })

    it('falls back to the patch url when a description is absent', () => {
        const raw = JSON.stringify({ patches: { 'a/b': [{ package: 'a/b', url: 'patches/x.patch' }] } })
        expect(describePatches(raw)).toEqual(['a/b: patches/x.patch'])
    })

    it('returns an empty list for missing or malformed input', () => {
        expect(describePatches(null)).toEqual([])
        expect(describePatches('{ not json')).toEqual([])
        expect(describePatches(JSON.stringify({}))).toEqual([])
    })
})

// ---------------------------------------------------------------------------

describe('branchSyncAction (#1746)', () => {
    const LOCAL = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    const REMOTE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'

    it('is current when local and origin are the same commit', () => {
        expect(branchSyncAction(LOCAL, LOCAL, LOCAL).action).toBe('current')
    })

    it('fast-forwards when local is strictly behind origin — the #1710 false red', () => {
        // merge-base === local means local is an ancestor of origin: lossless.
        const result = branchSyncAction(LOCAL, REMOTE, LOCAL)
        expect(result.action).toBe('fast-forward')
        expect(result.reason).toContain(REMOTE.slice(0, 9))
    })

    it('refuses to guess when both sides have unique commits', () => {
        // Neither is an ancestor of the other; fast-forwarding would discard work
        // and validating either side silently is the failure being fixed.
        expect(branchSyncAction(LOCAL, REMOTE, 'cccccccccccccccccccccccccccccccccccccccc').action).toBe('diverged')
    })

    it('flags validating never-pushed work when local is ahead', () => {
        // Legitimate locally, but what runs is not what the PR shows — so it is
        // reported rather than silently accepted.
        expect(branchSyncAction(LOCAL, REMOTE, REMOTE).action).toBe('ahead')
    })

    it('treats a branch with no origin counterpart as local-only', () => {
        expect(branchSyncAction(LOCAL, null, null).action).toBe('local-only')
    })
})

// UAMS-Web/uams-statamic-archive#3050 -- the stamp records which patch set was last applied, and was read as "that
// set is applied now". An install that re-extracts a patched package and then fails
// left the stamp behind, so the next run skipped the re-patch over an unpatched
// vendor/. These drive the whole install sequence against a scratch vendor/ with a
// fake `composer install` that records each call and can fail.
describe('installWithPatchStamp (#3050)', () => {
    const LOCK = JSON.stringify({ _hash: HASH_A, patches: { 'statamic/cms': [{ description: 'a patch' }] } })
    const dirs = []

    afterEach(() => {
        for (const dir of dirs.splice(0)) {
            rmSync(dir, { recursive: true, force: true })
        }
    })

    /** A scratch vendor/ holding a patched statamic/cms and, optionally, a stamp. */
    function vendorWith(stamp) {
        const root = mkdtempSync(join(tmpdir(), 'local-ci-stamp-'))
        dirs.push(root)
        const vendorDir = join(root, 'vendor')
        mkdirSync(join(vendorDir, 'statamic', 'cms'), { recursive: true })
        writeFileSync(join(vendorDir, 'statamic', 'cms', 'PATCHED'), '1')
        if (stamp !== null) {
            writeFileSync(join(vendorDir, PATCH_STAMP_FILE), `${stamp}\n`)
        }
        return vendorDir
    }

    /** A fake install: records its label, and throws when `fail(label)` says so. */
    function fakeInstall(calls, fail = () => false) {
        return (label) => {
            calls.push(label)
            if (fail(label)) {
                throw new Error(`${label} failed (exit 1)`)
            }
        }
    }

    const stampOf = (vendorDir) => {
        const path = join(vendorDir, PATCH_STAMP_FILE)
        return existsSync(path) ? readFileSync(path, 'utf8').trim() : null
    }

    it('skips the re-patch on a warm worktree whose stamp matches and whose install succeeds', () => {
        const vendorDir = vendorWith(HASH_A)
        const calls = []
        const announced = []

        const result = installWithPatchStamp({ vendorDir, patchLockRaw: LOCK, install: fakeInstall(calls), announce: (m) => announced.push(m) })

        expect(result.staleReason).toBeNull()
        expect(calls).toEqual(['composer install'])
        expect(announced).toEqual([])
        expect(existsSync(join(vendorDir, 'statamic', 'cms', 'PATCHED'))).toBe(true)
        expect(stampOf(vendorDir)).toBe(HASH_A)
    })

    it('leaves no stamp when the install fails, so the next run re-patches and names the missing record', () => {
        const vendorDir = vendorWith(HASH_A)

        // The failing install re-extracts the patched package (its patch marker is
        // gone) and then dies before patching it, as on 2026-10-13.
        const reExtractThenFail = (label) => {
            rmSync(join(vendorDir, 'statamic', 'cms', 'PATCHED'), { force: true })
            throw new Error(`${label} failed (exit 1)`)
        }
        expect(() => installWithPatchStamp({ vendorDir, patchLockRaw: LOCK, install: reExtractThenFail })).toThrow('composer install failed')
        expect(stampOf(vendorDir)).toBeNull()

        // The next run, on a branch declaring the SAME patch set, re-patches rather
        // than trusting a record the failure invalidated.
        const calls = []
        const announced = []
        const result = installWithPatchStamp({ vendorDir, patchLockRaw: LOCK, install: fakeInstall(calls), announce: (m) => announced.push(m) })

        expect(result.staleReason).toBe('no record of previously applied patches')
        expect(announced).toEqual(['Re-applying patches (no record of previously applied patches)'])
        expect(calls).toEqual(['composer install', 'composer install (re-applying patches)'])
        expect(existsSync(join(vendorDir, 'statamic', 'cms'))).toBe(false)
        expect(stampOf(vendorDir)).toBe(HASH_A)
    })

    it('leaves no stamp when the re-patch install fails', () => {
        const vendorDir = vendorWith(HASH_B)
        const calls = []

        expect(() => installWithPatchStamp({
            vendorDir,
            patchLockRaw: LOCK,
            install: fakeInstall(calls, (label) => label === 'composer install (re-applying patches)'),
        })).toThrow('re-applying patches')

        expect(calls).toEqual(['composer install', 'composer install (re-applying patches)'])
        expect(stampOf(vendorDir)).toBeNull()
    })

    it('stamps a cold install, which has no vendor/ until the install creates it', () => {
        const root = mkdtempSync(join(tmpdir(), 'local-ci-stamp-'))
        dirs.push(root)
        const vendorDir = join(root, 'vendor')
        const calls = []

        const result = installWithPatchStamp({
            vendorDir,
            patchLockRaw: LOCK,
            install: (label) => {
                calls.push(label)
                mkdirSync(vendorDir, { recursive: true })
            },
        })

        expect(result.staleReason).toBe('no record of previously applied patches')
        expect(calls).toEqual(['composer install', 'composer install (re-applying patches)'])
        expect(stampOf(vendorDir)).toBe(HASH_A)
    })

    it('writes no stamp when no patches are declared', () => {
        const vendorDir = vendorWith(null)
        const calls = []

        const result = installWithPatchStamp({ vendorDir, patchLockRaw: null, install: fakeInstall(calls) })

        expect(result).toEqual({ lockHash: null, staleReason: null })
        expect(calls).toEqual(['composer install'])
        expect(stampOf(vendorDir)).toBeNull()
    })
})
