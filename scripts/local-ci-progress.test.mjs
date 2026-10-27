import { describe, it, expect } from 'vitest'
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { worktreeFromLog, runHasStalled, parseSummary, summaryVerdict, buildReport } from './local-ci-progress.mjs'
import { CHAIN } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#1745 — three independent defects made the heartbeat's serial-tail reporting
// wrong and let a dead run look live. These cover the two decisions that were
// silently wrong; the third (per-run cache keying) is covered by the fact that
// worktreeFromLog now supplies the key's worktree half from the run itself.

describe('worktreeFromLog', () => {
    it('takes the worktree from the run being reported, not the flag', () => {
        // The monitor was armed with --worktree .../uams-ci-pipeline while the
        // runner worked out of .../uams-ci, so the serial denominator came from a
        // different checkout — an independent second cause of the same wrong total.
        const log = [
            'Repo root: /Users/x/Herd/uams-statamic',
            'Worktree:  /Users/x/Herd/uams-ci',
            'Branch:    some-branch',
            'Jobs:      phpunit',
        ].join('\n')

        expect(worktreeFromLog(log, '/Users/x/Herd/uams-ci-pipeline')).toBe('/Users/x/Herd/uams-ci')
    })

    it('falls back to the flag when the log has no worktree header', () => {
        expect(worktreeFromLog('Branch: b\nJobs: phpunit', '../uams-ci')).toBe('../uams-ci')
    })

    it('does not bleed trailing whitespace into the path', () => {
        expect(worktreeFromLog('Worktree:  /tmp/wt   ', '/fallback')).toBe('/tmp/wt')
    })
})

describe('runHasStalled', () => {
    it('treats a long-silent log as ended, not in flight', () => {
        // A killed run leaves the log frozen; replaying its last sub-phase made a
        // dead run indistinguishable from a live one.
        expect(runHasStalled(901, 900)).toBe(true)
        expect(runHasStalled(900, 900)).toBe(true)
    })

    it('leaves a run that is merely slow alone', () => {
        expect(runHasStalled(120, 900)).toBe(false)
    })

    it('can be disabled with a zero threshold', () => {
        expect(runHasStalled(99999, 0)).toBe(false)
    })
})

// #338: the summary block is read by its own delimiters. The fixtures are built the
// way the runner prints them (`job.padEnd(9)`, colored verdicts, a preamble before the
// `----` rule), and the job list is the runner's own CHAIN, so a job added there is a
// job these cases expect to see.
describe('parseSummary and buildReport', () => {
    const esc = '\x1b'
    const green = (text) => `${esc}[32m${text}${esc}[39m`
    const red = (text) => `${esc}[31m${text}${esc}[39m`
    const yellow = (text) => `${esc}[33m${text}${esc}[39m`

    const row = (job, outcome) => {
        if (outcome === 'skip') {
            return `  ${job.padEnd(9)} ${yellow('SKIP (gated, an earlier job failed)')}`
        }

        return outcome === 'pass' ? `  ${job.padEnd(9)} ${green('PASS')}` : `  ${job.padEnd(9)} ${red('FAIL (exit 1)')}`
    }

    const summaryLog = (outcomes, preamble = ['  Runner:   this branch (scripts/local-ci.mjs)']) => [
        'Worktree:  /tmp/uams-statamic-ci',
        `Jobs:      ${CHAIN.join(', ')}`,
        '==> JOB browser - pest browser smoke',
        '================ CI summary ================',
        ...preamble,
        '--------------------------------------------',
        ...CHAIN.map((job) => row(job, outcomes[job] ?? 'pass')),
        '============================================',
    ].join('\n')

    const report = (text) => {
        const directory = mkdtempSync(join(tmpdir(), 'local-ci-progress-'))
        const log = join(directory, 'local-ci-test.log')
        writeFileSync(log, text)

        try {
            return buildReport(log, { idleInterval: 0, stallAfter: 0, worktree: directory }, {})
        } finally {
            rmSync(directory, { recursive: true, force: true })
        }
    }

    it('lists every job in a finished chain, hyphenated names included', () => {
        const parsed = parseSummary(summaryLog({}).replace(/\x1b\[[0-9;]*m/g, ''))

        expect(parsed.jobs.map(({ job }) => job)).toEqual(CHAIN)
        expect(CHAIN).toHaveLength(14)
        expect(parsed.jobs.map(({ job }) => job)).toContain('no-emoji')
        expect(parsed.jobs.map(({ job }) => job)).toContain('content-pointer')
    })

    it('still reports an all-pass chain as passed, with all 14 jobs', () => {
        const line = report(summaryLog({}))

        expect(line).toContain('COMPLETE (passed)')
        expect(line).toContain(CHAIN.map((job) => `${job}=PASS`).join(' '))
    })

    it('reports a pest failure, past the old window, as a failure', () => {
        const line = report(summaryLog({ pest: 'fail', browser: 'skip' }))

        expect(line).toContain('COMPLETE (FAILED)')
        expect(line).toContain('pest=FAIL')
        expect(line).toContain('browser=SKIP')
    })

    it('reports a failure in the last job of the chain', () => {
        const line = report(summaryLog({ browser: 'fail' }))

        expect(line).toContain('COMPLETE (FAILED)')
        expect(line).toContain('browser=FAIL')
    })

    it('reports a failure in a hyphenated job', () => {
        const line = report(summaryLog({ 'content-pointer': 'fail' }))

        expect(line).toContain('COMPLETE (FAILED)')
        expect(line).toContain('content-pointer=FAIL')
    })

    it('reports gated jobs as SKIP', () => {
        const skipped = Object.fromEntries(CHAIN.slice(CHAIN.indexOf('pint') + 1).map((job) => [job, 'skip']))
        const line = report(summaryLog({ build: 'fail', ...skipped }))

        expect(line).toContain('build=FAIL')
        expect(line).toContain('rector=SKIP')
        expect(line).toContain('browser=SKIP')
    })

    it('keeps all 14 jobs when the preamble carries PHP and band lines', () => {
        const preamble = [
            '  Runner:   this branch (scripts/local-ci.mjs)',
            '  PHP:      /usr/bin/php (8.4.27)',
            '  parallel: 1200 tests in 300.0s under PHP 8.4.27',
            '  serial: 80 tests in 120.0s under PHP 8.4.27',
        ]
        const line = report(summaryLog({ browser: 'fail' }, preamble))

        expect(line).toContain(CHAIN.map((job) => `${job}=${job === 'browser' ? 'FAIL' : 'PASS'}`).join(' '))
        expect(line).not.toContain('PHP=')
    })

    it('does not claim a pass for a summary cut off before its closing rule', () => {
        const text = summaryLog({}).replace(/\n=+$/, '')

        expect(summaryVerdict(parseSummary(text.replace(/\x1b\[[0-9;]*m/g, '')))).toBe('summary incomplete')
    })

    it('reports the in-flight stage for a log with no summary block', () => {
        const log = [
            'Worktree:  /tmp/uams-statamic-ci',
            `Jobs:      ${CHAIN.join(', ')}`,
            '==> JOB content-pointer - submodule pointers vs origin/main',
        ].join('\n')

        expect(parseSummary(log)).toBeNull()

        const line = report(log)

        expect(line).toContain('stage=content-pointer (4 of 14)')
        expect(line).not.toContain('COMPLETE')
    })
})
