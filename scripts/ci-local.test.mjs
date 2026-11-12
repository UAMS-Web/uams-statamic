import { describe, expect, it } from 'vitest'
import { DEPENDENCIES_JOB, RETIRED_FLAGS, runnerArgs, withDependencies } from './ci-local.mjs'
import { pointerBase, tokenEnv } from './ci-job.mjs'
import { composerDrift, npmDrift, strandedEntries } from './prepare-dependencies.mjs'
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

// #296: the wrappers around the shared local CI runner. Each case below names the run the
// wrapper would otherwise get wrong: a subset that skips the install, a retired flag dropped
// silently, a drift report that reads a stale tree as current.

describe('ci-local.mjs argument translation', () => {
    it('puts the dependencies job first in a --jobs subset that lacks it', () => {
        // Without it a reused slot runs `pest` against whatever vendor/ it held last
        expect(withDependencies('pest')).toBe('dependencies,pest')
        expect(withDependencies(' pint , rector ')).toBe('dependencies,pint,rector')
    })

    it('leaves a --jobs subset that already names the dependencies job as it is', () => {
        expect(withDependencies('pest,dependencies')).toBe('pest,dependencies')
        expect(DEPENDENCIES_JOB).toBe('dependencies')
    })

    it('translates the private runner\'s spellings of all and the branch flag', () => {
        expect(runnerArgs(['-b', 'feature', '--jobs', 'all'])).toEqual(['--branch', 'feature', '--all'])
        expect(runnerArgs(['-j', 'all'])).toEqual(['--all'])
        expect(runnerArgs(['--branch', 'feature', '-j', 'pest'])).toEqual(['--branch', 'feature', '--jobs', 'dependencies,pest'])
    })

    it('passes every other argument through unchanged', () => {
        expect(runnerArgs(['--dry-run', '--comment', '--branch', 'x'])).toEqual(['--dry-run', '--comment', '--branch', 'x'])
        expect(runnerArgs([])).toEqual([])
    })

    it('refuses each retired flag, naming what to do instead', () => {
        expect(Object.keys(RETIRED_FLAGS).length).toBeGreaterThan(0)

        for (const [flag, remedy] of Object.entries(RETIRED_FLAGS)) {
            expect(() => runnerArgs(['--branch', 'x', flag])).toThrow(remedy)
        }
    })
})

describe('ci-job.mjs helpers', () => {
    it('compares submodule pointers with origin/main when the tree has it, else main', () => {
        expect(pointerBase((ref) => ref === 'origin/main^{commit}')).toBe('origin/main')
        expect(pointerBase(() => false)).toBe('main')
    })

    it('carries a token in the environment only, and nothing without one', () => {
        expect(tokenEnv('')).toBeUndefined()
        expect(tokenEnv('abc')).toEqual({
            GIT_CONFIG_COUNT: '1',
            GIT_CONFIG_KEY_0: 'url.https://x-access-token:abc@github.com/.insteadOf',
            GIT_CONFIG_VALUE_0: 'https://github.com/',
        })
    })
})

describe('prepare-dependencies.mjs drift detection', () => {
    const lockPkg = (name, version, reference) => ({ name, version, dist: { reference } })

    it('reads a vendor/ that matches composer.lock as no drift', () => {
        const lock = { packages: [lockPkg('a/a', '1.0.0', 'r1')], 'packages-dev': [lockPkg('b/b', '2.0.0', 'r2')] }
        const installed = { packages: [lockPkg('a/a', '1.0.0', 'r1'), lockPkg('b/b', '2.0.0', 'r2')] }

        expect(composerDrift(lock, installed)).toEqual([])
    })

    it('reports a missing package, a different version and a different reference', () => {
        // The reference case is a dev branch moved under the same version string, which a
        // version comparison alone reads as current
        const lock = { packages: [lockPkg('a/a', '1.0.0', 'r1'), lockPkg('b/b', '2.0.0', 'r2'), lockPkg('c/c', 'dev-main', 'new')] }
        const installed = { packages: [lockPkg('b/b', '1.9.0', 'r2'), lockPkg('c/c', 'dev-main', 'old')] }

        expect(composerDrift(lock, installed)).toEqual([
            'a/a: 1.0.0 (not installed)',
            'b/b: 2.0.0 (installed 1.9.0)',
            'c/c: dev-main (installed dev-main)',
        ])
    })

    it('reports every locked package when installed.json is absent', () => {
        expect(composerDrift({ packages: [lockPkg('a/a', '1.0.0', 'r1')] }, null)).toEqual(['a/a: 1.0.0 (not installed)'])
    })

    it('reads node_modules/ against package-lock.json, skipping the root, links and absent optional packages', () => {
        const lock = {
            packages: {
                '': { version: '0.0.0' },
                'node_modules/a': { version: '1.0.0' },
                'node_modules/b': { version: '2.0.0' },
                'node_modules/c': { version: '3.0.0' },
                'node_modules/linked': { link: true },
                'node_modules/@rollup/rollup-darwin-arm64': { version: '4.0.0', optional: true },
            },
        }
        const installed = { packages: { 'node_modules/a': { version: '1.0.0' }, 'node_modules/b': { version: '1.0.0' } } }

        expect(npmDrift(lock, installed)).toEqual([
            'node_modules/b: 2.0.0 (installed 1.0.0)',
            'node_modules/c: 3.0.0 (not installed)',
        ])
    })

    it('reports an optional package npm installed at the wrong version', () => {
        const lock = { packages: { 'node_modules/opt': { version: '2.0.0', optional: true } } }
        const installed = { packages: { 'node_modules/opt': { version: '1.0.0' } } }

        expect(npmDrift(lock, installed)).toEqual(['node_modules/opt: 2.0.0 (installed 1.0.0)'])
    })

    it('finds files stranded in an uninitialized submodule directory, and none in an initialized or absent one', () => {
        const dir = mkdtempSync(join(tmpdir(), 'stranded-'))

        try {
            mkdirSync(join(dir, 'content', 'collections'), { recursive: true })
            writeFileSync(join(dir, 'content', 'left.txt'), 'x')
            expect(strandedEntries(join(dir, 'content')).sort()).toEqual(['collections', 'left.txt'])

            writeFileSync(join(dir, 'content', '.git'), 'gitdir: elsewhere\n')
            expect(strandedEntries(join(dir, 'content'))).toEqual([])

            expect(strandedEntries(join(dir, 'absent'))).toEqual([])
        } finally {
            rmSync(dir, { recursive: true, force: true })
        }
    })
})
