import { describe, it, expect } from 'vitest'
import { chunkByArgLength } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#1079 — the Pint job chunks its changed-file list so a large diff never
// overflows the Windows command line (~32 KB). These assert the two invariants
// that make chunk-by-chunk `pint --test` equivalent to one invocation: every
// chunk stays under the cap, and the chunks concatenate back to the input in
// order (no file dropped, duplicated, or reordered).

const joinedCost = (files) => files.reduce((n, f) => n + f.length + 1, 0)

describe('chunkByArgLength', () => {
    it('returns no chunks for an empty list', () => {
        expect(chunkByArgLength([], 8000)).toEqual([])
    })

    it('keeps a small list in a single chunk', () => {
        const files = ['app/A.php', 'app/B.php', 'app/C.php']
        expect(chunkByArgLength(files, 8000)).toEqual([files])
    })

    it('splits a large list so every chunk stays under maxLen', () => {
        // 500 realistic-length paths blow past any small cap.
        const files = Array.from({ length: 500 }, (_, i) => `app/Services/ComputedFields/Provider${i}/SomeLongishClassName${i}.php`)
        const maxLen = 8000
        const chunks = chunkByArgLength(files, maxLen)

        expect(chunks.length).toBeGreaterThan(1)
        for (const chunk of chunks) {
            expect(chunk.length).toBeGreaterThan(0)
            expect(joinedCost(chunk)).toBeLessThanOrEqual(maxLen)
        }
    })

    it('preserves order and completeness (concatenation equals the input)', () => {
        const files = Array.from({ length: 250 }, (_, i) => `tests/Feature/Path/Number${i}Test.php`)
        const flattened = chunkByArgLength(files, 500).flat()
        expect(flattened).toEqual(files)
    })

    it('emits an over-long single path alone rather than dropping it', () => {
        const longPath = 'app/' + 'x'.repeat(200) + '.php'
        const files = ['app/A.php', longPath, 'app/B.php']
        const chunks = chunkByArgLength(files, 50)

        expect(chunks.flat()).toEqual(files)
        // The over-long path can't fit under the cap, so it occupies its own chunk.
        expect(chunks.some((chunk) => chunk.length === 1 && chunk[0] === longPath)).toBe(true)
    })
})
