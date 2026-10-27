import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { COVERAGE_DRIVER_PROBE, MUTATE_NO_DRIVER_MESSAGE, mutateSmokeVerdict } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#3213: with no coverage driver loaded, the mutate job reported the generic
// "did not report" line, so a workstation condition read as a branch defect. The
// job now names the absent driver, and keeps the UAMS-Web/uams-statamic-archive#1728 zero-count message apart.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const runner = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')
const workflow = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')

const HEALTHY = '  8 Mutations for 1 Files created\n\n  Mutations: 8 tested\n  Score:     100.00%\n'
const ZERO = '  0 Mutations for 0 Files created\n'
const PEST_REFUSAL = '  Pest\\Exceptions\\InvalidOption\n\n  Mutation testing requires code coverage to be enabled. You can find more about code coverage in the Pest documentation.\n'

describe('mutateSmokeVerdict()', () => {
    it('refuses with the driver message when the probe finds no driver', () => {
        expect(mutateSmokeVerdict('none\n', null)).toEqual({ ok: false, message: MUTATE_NO_DRIVER_MESSAGE })
    })

    it('names the driver when the probe passed and Pest still refused', () => {
        expect(mutateSmokeVerdict('pcov', PEST_REFUSAL)).toEqual({ ok: false, message: MUTATE_NO_DRIVER_MESSAGE })
    })

    it('points the driver message at the pcov-setup skill', () => {
        expect(MUTATE_NO_DRIVER_MESSAGE).toContain('.claude/skills/pcov-setup/SKILL.md')
        expect(MUTATE_NO_DRIVER_MESSAGE).toContain('no coverage driver')
    })

    it('keeps the #1728 zero-count message distinct from the driver message', () => {
        const zero = mutateSmokeVerdict('pcov', ZERO)

        expect(zero.ok).toBe(false)
        expect(zero.message).toContain('#1728 false green')
        expect(zero.message).not.toBe(MUTATE_NO_DRIVER_MESSAGE)
        expect(zero.message).not.toContain('coverage driver')
    })

    it('passes a run with a driver, a non-zero count and a full score', () => {
        expect(mutateSmokeVerdict('pcov', HEALTHY)).toEqual({ ok: true })
        expect(mutateSmokeVerdict('xdebug', HEALTHY)).toEqual({ ok: true })
    })

    it('still fails a run with a driver whose score is below 100%', () => {
        const verdict = mutateSmokeVerdict('pcov', HEALTHY.replace('100.00%', '87.50%'))

        expect(verdict).toEqual({ ok: false, message: 'mutate: score 87.50%, expected 100.00%.' })
    })

    it('still fails a run with no summary line', () => {
        expect(mutateSmokeVerdict('pcov', 'nothing useful\n').ok).toBe(false)
    })

    it('never passes a driverless run, whatever Pest printed', () => {
        expect(mutateSmokeVerdict('none', HEALTHY).ok).toBe(false)
        expect(mutateSmokeVerdict('none', null).ok).toBe(false)
    })
})

describe('the probe runs before Pest, in both CI files', () => {
    it('probes for a driver ahead of the mutation run in the local runner', () => {
        const probe = runner.indexOf("capturePhp(['-r', COVERAGE_DRIVER_PROBE], worktreePath)")
        const pest = runner.indexOf("'--mutate',")

        expect(probe).toBeGreaterThan(-1)
        expect(probe).toBeLessThan(pest)
    })

    it('runs the same probe in ci.yml ahead of the mutation run', () => {
        const probe = workflow.indexOf(`DRIVER="$(php -r '${COVERAGE_DRIVER_PROBE}')"`)
        const pest = workflow.indexOf('--mutate --path=app/Support/UamsAlert')

        expect(probe).toBeGreaterThan(-1)
        expect(probe).toBeLessThan(pest)
    })
})
