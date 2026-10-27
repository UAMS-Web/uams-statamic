import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { PHAR_PATH, TOOLS_DIR, fetchPlan, installedReference, pintSourceFromLock, readLock } from './fetch-pint.mjs'

// This script exists so a checkout with no dev dependencies can still run the formatter (UAMS-Web/uams-statamic-archive#1880).
// Its load-bearing property is that the pinned reference comes from `composer.lock` rather than
// from a constant in the script: a hardcoded SHA is a second source of truth for "which Pint this
// project uses", and the two drift silently. These pin that, the dist-only failure mode that
// `phpstan/phpstan` already exhibits, and the single-commit fetch — `clone --depth 1` would take
// the default branch tip instead, which is a different Pint from the locked one.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

describe('pintSourceFromLock', () => {
    it('reads the git url and pinned reference', () => {
        expect(pintSourceFromLock({
            'packages-dev': [{ name: 'laravel/pint', version: 'v1.30.3', source: { url: 'https://github.com/laravel/pint.git', reference: 'abc123' } }],
        })).toEqual({ url: 'https://github.com/laravel/pint.git', reference: 'abc123' })
    })

    it('finds Pint in either array, so moving it between require and require-dev cannot break the fetch', () => {
        expect(pintSourceFromLock({
            packages: [{ name: 'laravel/pint', source: { url: 'u', reference: 'r' } }],
        })).toEqual({ url: 'u', reference: 'r' })
    })

    // The `phpstan/phpstan` shape, and the whole reason this repo cannot just `--prefer-source`
    // everything: a dist-only package has `"source": null` and no git path to take.
    it('refuses a dist-only package rather than fetching nothing', () => {
        expect(() => pintSourceFromLock({
            'packages-dev': [{ name: 'laravel/pint', version: 'v1.30.3', source: null }],
        })).toThrow(/no git source/)
    })

    it('says so when Pint is absent entirely', () => {
        expect(() => pintSourceFromLock({ packages: [], 'packages-dev': [] })).toThrow(/not in composer.lock/)
    })
})

describe('fetchPlan', () => {
    const url = 'https://example.test/pint.git'
    const plan = fetchPlan('/repo', { url, reference: 'deadbeef' })

    it('fetches ONE commit by reference, never a branch tip', () => {
        const fetch = plan.find((step) => step.args.includes('fetch'))

        expect(fetch.args).toContain('--depth')
        expect(fetch.args).toContain('deadbeef')
        // A `clone` would resolve the default branch instead — measured 2026-08-18, that gave
        // 1.30.5 against a locked 1.30.3.
        expect(plan.some((step) => step.args.includes('clone'))).toBe(false)
    })

    // Asserting the flags alone left "where does this write" untested, so the git-ignored
    // destination could be changed to a staged path with every test still green.
    it('writes only inside the git-ignored tools directory, and fetches the lock\'s url', () => {
        expect(plan[0].args).toContain(join('/repo', TOOLS_DIR))
        expect(plan.find((step) => step.args.includes('fetch')).args).toContain(url)

        for (const step of plan) {
            const target = step.args.find((arg) => arg.startsWith('/repo'))

            if (target) {
                expect(target.startsWith(join('/repo', '.tools'))).toBe(true)
            }
        }
    })

    it('checks out the fetched commit rather than a named branch', () => {
        expect(plan.at(-1).args).toContain('FETCH_HEAD')
    })

    // `remote add origin` fails with "remote origin already exists" on any rerun, so one
    // interrupted fetch — the documented, expected cloud failure — wedged the script permanently.
    // Fetching the URL directly makes every step safely repeatable.
    it('adds no named remote, so an interrupted fetch can simply be rerun', () => {
        expect(plan.some((step) => step.args.includes('remote'))).toBe(false)
    })
})

describe('installedReference', () => {
    // The staleness check. Testing for the phar FILE alone cannot tell a current install from one
    // pinned to a superseded Pint, which would format against a version the project does not use.
    it('is null when the directory is not a git repository', () => {
        expect(installedReference('/definitely/not/a/repo/anywhere')).toBeNull()
    })
})

describe('against this repository', () => {
    // Not a mock: if the real lock ever stops carrying a git source for Pint, the mechanism is dead
    // and this should be what says so.
    it('resolves a real git source for Pint from the committed composer.lock', () => {
        const { url, reference } = pintSourceFromLock(readLock(repoRoot))

        expect(url).toMatch(/laravel\/pint/)
        expect(reference).toMatch(/^[0-9a-f]{40}$/)
    })

    it('keeps the fetched tree git-ignored, so nothing it writes can be staged', () => {
        const ignored = readFileSync(join(repoRoot, '.gitignore'), 'utf8')

        expect(ignored).toMatch(/^\/?\.tools\/?$/m)
        expect(TOOLS_DIR).toMatch(/^\.tools[\\/]/)
        expect(PHAR_PATH).toMatch(/builds[\\/]pint$/)
    })

    // Git-ignoring the tree is not enough: `npm run spell` passes `--dot`, which reaches
    // git-ignored dot-paths anyway. Without this exclusion, fetching Pint reds the spell gate with
    // ~120 findings from Pint's own source — so the runbook would tell a cloud session to run a
    // command that breaks a different mandated check. Same class as the `vendor/**` entry, which is
    // what kept the identical tree out of scope at its old location.
    it('keeps the fetched tree out of the spell gate, which --dot would otherwise reach', () => {
        const cspell = readFileSync(join(repoRoot, 'cspell.json'), 'utf8')
            .split('\n')
            .filter((line) => !line.trim().startsWith('//'))
            .join('\n')

        expect(JSON.parse(cspell).ignorePaths).toContain('.tools/**')
    })
})
