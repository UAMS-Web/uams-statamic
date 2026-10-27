import { describe, it, expect } from 'vitest'
import { worktreeFromLog, runHasStalled } from './local-ci-progress.mjs'

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
