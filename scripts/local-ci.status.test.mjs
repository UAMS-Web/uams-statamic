import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { commitStatusDescription } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#2323 — a pull request handed back red by this gate was indistinguishable, on
// every surface GitHub displays, from one that had passed: `draft=false`,
// `mergeable=true`, no labels, and `combined_state=pending` across the skipped
// remote workflows. Two readers concluded UAMS-Web/uams-statamic-archive#2168 was ready while it was red.
//
// These cover the wording of the status and the wiring that guards it. The POST
// itself is not covered here: it needs `gh`, a remote and a resolvable head, and
// a unit test that required those would be testing the network rather than the
// decision.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const source = readFileSync(join(repoRoot, 'scripts/local-ci.mjs'), 'utf8')

describe('commit status description (#2323)', () => {
    it('names the passing count when the chain is green', () => {
        const results = [['spell', 0], ['js', 0], ['phpunit', 0]]

        expect(commitStatusDescription(false, results)).toBe('3 of 3 jobs passed')
    })

    it('names the failing job rather than a count, because the count is what a reader already has', () => {
        const results = [['spell', 0], ['larastan', 1]]

        expect(commitStatusDescription(true, results)).toBe('larastan failed')
    })

    it('reports chain-gated jobs separately from failing ones', () => {
        // The distinction the summary makes and a bare "3 failed" would lose: one
        // job failed and the rest never ran.
        const results = [['spell', 0], ['js', 1], ['phpunit', 'skipped'], ['browser', 'skipped']]

        expect(commitStatusDescription(true, results)).toBe('js failed; 2 chain-gated')
    })

    it('lists every failing job when more than one ran red', () => {
        const results = [['pint', 1], ['larastan', 1], ['phpunit', 'skipped']]

        expect(commitStatusDescription(true, results)).toBe('pint, larastan failed; 1 chain-gated')
    })

    it('puts the failing jobs first, so GitHub truncating at 140 loses the tail', () => {
        const results = Array.from({ length: 40 }, (_, i) => [`job${i}`, 1])
        const description = commitStatusDescription(true, results)

        expect(description.slice(0, 4)).toBe('job0')
        expect(description.length).toBeGreaterThan(140)
    })
})

describe('commit status wiring (#2323)', () => {
    it('is opt-in, so a developer running the chain locally does not write to GitHub', () => {
        expect(source).toContain("case '--commit-status':")
        expect(source).toContain('opts.commitStatus = true')
        // Guarded at the call site rather than inside the poster, so the default
        // path does not reach `gh` at all.
        expect(source).toContain('if (opts.commitStatus) {')
    })

    it('annotates the head of the branch, not the merged state it validated', () => {
        // The merge commit exists only in the CI worktree; a status on it would
        // attach to a SHA nobody can see.
        expect(source).toContain('refs/remotes/origin/${branch}')
    })

    it('never changes the run exit code', () => {
        // The chain's verdict is the chain's. An annotation that could red a green
        // run would invert the defect this exists to fix.
        const poster = source.slice(source.indexOf('function postCommitStatus'))
        const body = poster.slice(0, poster.indexOf('\n}\n'))

        expect(body).not.toContain('process.exit')
        expect(body).toContain('warn(')
    })

    it('posts after the verdict is decided, not before', () => {
        expect(source.indexOf('const failed = ')).toBeLessThan(source.indexOf('postCommitStatus(repoRoot'))
    })
})
