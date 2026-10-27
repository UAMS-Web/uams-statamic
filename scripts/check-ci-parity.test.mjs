import { describe, it, expect } from 'vitest'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { parityRegions, touchedLines, touchesRegion, evaluate } from './check-ci-parity.mjs'

// UAMS-Web/uams-statamic-archive#2376 — the CI-parity hook fired on any staged `scripts/local-ci.mjs`, so the
// ~half of that file's changes that are legitimately one-sided all needed
// `--no-verify` — which also skips the unrelated merge-resolution gate in the
// same hook. These assert the hook now gates on the marked REGIONS instead.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

// A hunk header for lines [start, start+count) of the new file. -U0, so context
// never widens the touched set.
const hunk = (start, count = 1) => `@@ -${start} +${start},${count} @@\n`

const MJS = [
    'const x = 1',                                  // 1
    '',                                             // 2
    '    const runners = {',                        // 3
    "        pest: () => {",                        // 4
    '            // PARITY: mirrored by the pest job in ci.yml.',   // 5
    "            step('JOB pest')",                 // 6
    '        },',                                   // 7
    "        local: () => {",                       // 8
    "            step('JOB local')",                // 9  <- unmarked runner
    '        },',                                   // 10
    '    }',                                        // 11
    'function localOnly() { return 2 }',            // 12 <- unmarked, outside any runner
].join('\n')

const YML = [
    'jobs:',                                        // 1
    '  pest:',                                      // 2
    '    steps:',                                   // 3
    '      # PARITY: mirrored by the pest job in scripts/local-ci.mjs.', // 4
    '      - name: Run Pest',                       // 5
    '        run: vendor/bin/pest',                 // 6
    '      - name: Unmarked step',                  // 7
    '        run: echo hi',                         // 8
].join('\n')

describe('parityRegions', () => {
    it('scopes an mjs marker to its enclosing runner body', () => {
        expect(parityRegions(MJS, 'mjs')).toEqual([{ start: 5, end: 7 }])
    })

    it('scopes a yml marker to the step it precedes, not to the rest of the file', () => {
        // 4 marker, 5 the step, 6 its `run:` — and it stops BEFORE line 7, the
        // next step, which is the boundary that makes this scoping rather than
        // "everything after the marker".
        expect(parityRegions(YML, 'yml')).toEqual([{ start: 4, end: 6 }])
    })

    it('gives a marker that delimits nothing no region at all', () => {
        // An un-delimited marker must not silently claim the rest of the file. An
        // earlier draft let ci.yml's two header notes run to EOF and reported 2289
        // covered lines in a 1062-line file — over-firing on every edit.
        const header = '// PARITY WITH REMOTE CI — describes the convention\nconst x = 1\n'
        expect(parityRegions(header, 'mjs')).toEqual([])
    })

    it('finds a marker sitting mid-comment-block, not only one directly above its step', () => {
        // In ci.yml a marker is often followed by further comment lines before
        // the step it annotates. Requiring the very next non-blank line to be the
        // step drops every such marker: an UNDER-fire, where a genuinely mirrored
        // step reads as unmarked. So the region has to reach past the rest of the
        // comment block to the step.
        const midBlock = [
            'jobs:', '  a:', '    steps:',
            '      # PARITY: mirrored by the a job.',
            '      # a second comment line before the step',
            '      - name: Run a',
            '        run: echo a',
        ].join('\n')
        expect(parityRegions(midBlock, 'yml')).toHaveLength(1)
    })

    it('sees regions in the REAL files, so a blind parser cannot pass the rest', () => {
        const mjs = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')
        const yml = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')
        expect(parityRegions(mjs, 'mjs').length).toBeGreaterThan(20)
        expect(parityRegions(yml, 'yml').length).toBeGreaterThan(20)
    })
})

describe('touchedLines', () => {
    it('attributes a pure deletion to the gap it sat in', () => {
        // A deletion has no new-file line of its own, so it would otherwise
        // register as touching nothing — removing a mirrored step would pass.
        // It is the gap between lines 4 and 5, inside a region only when both
        // of them are (UAMS-Web/uams-statamic-archive#2865).
        expect(touchedLines('@@ -5,3 +4,0 @@\n')).toEqual([4.5])
        expect(touchesRegion([4.5], [{ start: 3, end: 5 }])).toBe(true)
        expect(touchesRegion([4.5], [{ start: 1, end: 4 }])).toBe(false)
    })
})

describe('the gate decision', () => {
    const both = { ciSource: YML, scriptSource: MJS }

    it('does NOT fire when the mjs change touches no marked region', () => {
        // Criterion 1, and the case UAMS-Web/uams-statamic-archive#2376 exists for: line 12 is `localOnly()`.
        expect(evaluate({ ...both, scriptDiff: hunk(12) }).fire).toBe(false)
    })

    it('fires when only one side changes inside a marked region', () => {
        // Criterion 2. Line 6 is inside the marked pest runner.
        expect(evaluate({ ...both, scriptDiff: hunk(6) }).fire).toBe(true)
    })

    it('does NOT fire when both sides change inside a marked region', () => {
        expect(evaluate({ ...both, scriptDiff: hunk(6), ciDiff: hunk(6) }).fire).toBe(false)
    })

    it('fires on a commit touching a marked AND an unmarked region together', () => {
        // Criterion 3 — the mixed case. Line 6 marked, line 12 not; the marked
        // half still has no counterpart, so this must not be waved through.
        const diff = hunk(6) + hunk(12)
        expect(evaluate({ ...both, scriptDiff: diff }).fire).toBe(true)
    })

    it('fires when a whole marked region is DELETED', () => {
        // The new-file side alone cannot see this: the staged source no longer
        // contains the region, so there is nothing left to intersect and the gate
        // passed. Removing a mirrored runner outright is exactly the drift it
        // exists to catch. Found by adversarial review, not by a test.
        const gone = ['const x = 1', '', '    const runners = {', '    }'].join('\n')
        const r = evaluate({
            scriptDiff: '@@ -4,4 +3,0 @@\n',
            scriptSource: gone,
            scriptSourceOld: MJS,
            ciSource: YML,
        })
        expect(r.fire).toBe(true)
    })

    it('fires when a yml marked region changes with no mjs counterpart', () => {
        expect(evaluate({ ...both, ciDiff: hunk(6) }).fire).toBe(true)
    })
})

describe('the two commits that had to bypass it (criterion 5)', () => {
    // Read at each commit's OWN file state: the markers have moved since.
    const at = (sha, file) => execFileSync('git', ['show', `${sha}:${file}`], { cwd: repoRoot, encoding: 'utf8' })
    const diffAt = (sha, file) => execFileSync(
        'git', ['diff', '--unified=0', `${sha}^1`, sha, '--', file], { cwd: repoRoot, encoding: 'utf8' },
    )

    it.each([
        ['#2350', 'dc036a092'],
        ['#2312', 'b792b083b'],
    ])('%s would no longer need --no-verify', (_label, sha) => {
        const source = at(sha, 'scripts/local-ci.mjs')
        const diff = diffAt(sha, 'scripts/local-ci.mjs')
        expect(diff).not.toBe('')                       // the diff really is non-empty
        expect(touchesRegion(touchedLines(diff), parityRegions(source, 'mjs'))).toBe(false)
    })
})

// A real `git diff -U0` between two texts, so the hunk headers are the ones the hook reads.
const diffOf = (before, after) => {
    const dir = mkdtempSync(join(tmpdir(), 'parity-'))
    try {
        writeFileSync(join(dir, 'a.yml'), before)
        writeFileSync(join(dir, 'b.yml'), after)
        try {
            execFileSync('git', ['diff', '--no-index', '--unified=0', 'a.yml', 'b.yml'], { cwd: dir, encoding: 'utf8' })
            return ''
        } catch (error) {
            // `git diff --no-index` exits 1 when the files differ.
            return error.stdout
        }
    } finally {
        rmSync(dir, { recursive: true, force: true })
    }
}

describe('the line after a marked yml step (#2865)', () => {
    // The same job as YML, as a file ends: a trailing newline after the last step.
    const LAST = [
        'jobs:',                                        // 1
        '  pest:',                                      // 2
        '    steps:',                                   // 3
        '      # PARITY: mirrored by the pest job in scripts/local-ci.mjs.', // 4
        '      - name: Run Pest',                       // 5
        '        run: vendor/bin/pest',                 // 6
        '',                                             // 7: the empty line after the final newline
    ].join('\n')

    const yml = (before, after) => evaluate({
        ciDiff: diffOf(before, after), ciSource: after, ciSourceOld: before, scriptSource: MJS,
    })

    it('ends a yml region on its step\'s last line, not on the blank line after it', () => {
        expect(parityRegions(LAST, 'yml')).toEqual([{ start: 4, end: 6 }])

        const separated = ['jobs:', '  pest:', '    steps:',
            '      # PARITY: mirrored', '      - name: A', '        run: a', '', '',
            '      - name: B', '        run: b'].join('\n')
        expect(parityRegions(separated, 'yml')).toEqual([{ start: 4, end: 6 }])
    })

    it('lets a job be added after the last marked step', () => {
        const after = LAST + '\n  extra:\n    steps:\n      - run: echo extra\n'
        expect(yml(LAST, after).fire).toBe(false)
    })

    it('lets a step be added right after a marked step', () => {
        const before = YML + '\n'
        const after = before.replace('        run: vendor/bin/pest\n', '        run: vendor/bin/pest\n\n      - name: New step\n        run: echo new\n')
        expect(after).not.toBe(before)
        expect(yml(before, after).fire).toBe(false)
    })

    it('still refuses an edit inside a marked step, or a line added to one', () => {
        // Each is a change to what the step runs, with no counterpart in the runner.
        expect(yml(LAST, LAST.replace('vendor/bin/pest', 'vendor/bin/pest --parallel')).fire).toBe(true)
        expect(yml(LAST, LAST.replace('        run: vendor/bin/pest\n', '        run: vendor/bin/pest\n        env:\n          X: 1\n')).fire).toBe(true)
        expect(yml(LAST, LAST.replace('      - name: Run Pest\n', '      - name: Run Pest\n        if: always()\n')).fire).toBe(true)
    })
})

describe('a marker in the middle of a comment block (#3238)', () => {
    // 4 the marker, 5 and 6 more comment, 7 the step it annotates, 8 its run:,
    // 9 the next step. The region has to reach the step, not stop at line 6.
    const MID = [
        'jobs:', '  pest:', '    steps:',
        '      # PARITY: mirrored by the pest job in scripts/local-ci.mjs.', // 4
        '      #',                                                            // 5
        '      # Why this step is shaped the way it is.',                     // 6
        '      - name: Run Pest',                                             // 7
        '        run: vendor/bin/pest',                                       // 8
        '      - name: Unmarked step',                                        // 9
        '        run: echo hi',                                               // 10
        '',
    ].join('\n')

    const yml = (before, after) => evaluate({
        ciDiff: diffOf(before, after), ciSource: after, ciSourceOld: before, scriptSource: MJS,
    })

    it('covers the annotated step and stops before the next one', () => {
        expect(parityRegions(MID, 'yml')).toEqual([{ start: 4, end: 8 }])
    })

    it('refuses an edit to the annotated step with no runner counterpart', () => {
        expect(yml(MID, MID.replace('run: vendor/bin/pest', 'run: vendor/bin/pest --parallel')).fire).toBe(true)
    })

    it('still lets the next, unmarked step change', () => {
        expect(yml(MID, MID.replace('run: echo hi', 'run: echo bye')).fire).toBe(false)
    })

    it('ends a region on its step, not in the comment above the next step', () => {
        // Before UAMS-Web/uams-statamic-archive#3238 a marker directly above its step also took in the next
        // step's leading comment, so editing that comment read as editing this step.
        const direct = [
            'jobs:', '  pest:', '    steps:',
            '      # PARITY: mirrored by the pest job in scripts/local-ci.mjs.', // 4
            '      - name: Run Pest',                                             // 5
            '        run: vendor/bin/pest',                                       // 6
            '',
            '      # Why the next step exists.',                                  // 8
            '      - name: Unmarked step',                                        // 9
            '        run: echo hi',                                               // 10
            '',
        ].join('\n')

        expect(parityRegions(direct, 'yml')).toEqual([{ start: 4, end: 6 }])
        expect(yml(direct, direct.replace('Why the next step exists.', 'Why it exists.')).fire).toBe(false)
    })

    it('covers the run: body of the real mutate step, not only the comment above it', () => {
        // Measured on main at 32ad877cb: this marker's region was lines 675 to 689,
        // the comment block alone, and an edit to the step's run: body was refused.
        const real = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')
        const lines = real.split('\n')
        const marker = lines.findIndex((l) => l.includes('# PARITY: mirrored by the mutate job in scripts/local-ci.mjs')) + 1
        const run = lines.findIndex((l) => l.includes('--mutate --path=app/Support/UamsAlert')) + 1

        expect(marker).toBeGreaterThan(0)
        expect(run).toBeGreaterThan(marker)

        const region = parityRegions(real, 'yml').find((r) => r.start === marker)
        expect(region).toBeDefined()
        expect(region.end).toBeGreaterThanOrEqual(run)
        expect(touchesRegion([run], [region])).toBe(true)
    })
})
