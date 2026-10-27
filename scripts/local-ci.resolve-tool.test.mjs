import { describe, it, expect, afterEach } from 'vitest'
import { chmodSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { delimiter, dirname, join } from 'node:path'
import { resolveTool } from './local-ci.mjs'
import { isExecutable } from './spawn-helpers.mjs'

// UAMS-Web/uams-statamic-archive#2350. `isExecutable()` is well covered in spawn-helpers.test.mjs; what was not covered is the
// WIRING — that a false from the predicate becomes a throw AT RESOLUTION, before any job runs,
// naming the resolved path and the variable that chose it. Delete the throw and the guard reverts
// to pre-#2335 behavior: an override that merely exists is accepted and fails much later inside
// spawnSync as a bare EACCES or EFTYPE, which reads as "the tool is broken" rather than "this
// variable is wrong". The predicate being thoroughly tested is what made the gap easy to miss.
//
// Real files throughout, no mocking: the point is the OS's own answer, and a stubbed predicate
// would assert the test's opinion of what runs rather than the platform's.

const onWindows = process.platform === 'win32'
const made = []
const originalPath = process.env.PATH

afterEach(() => {
    process.env.PATH = originalPath

    while (made.length) {
        rmSync(made.pop(), { recursive: true, force: true })
    }
})

/** A real file the OS will refuse to execute: no execute bit on POSIX, an unlisted extension on Windows. */
function refusedFile() {
    const dir = mkdtempSync(join(tmpdir(), 'resolve-tool-'))
    made.push(dir)

    const file = join(dir, onWindows ? 'tool.phar' : 'tool')
    writeFileSync(file, '#!/usr/bin/env node')

    if (! onWindows) {
        chmodSync(file, 0o644)
    }

    return file
}

/** A real file the OS will run. */
function runnableFile() {
    const dir = mkdtempSync(join(tmpdir(), 'resolve-tool-ok-'))
    made.push(dir)

    const file = join(dir, onWindows ? 'tool.cmd' : 'tool')
    writeFileSync(file, onWindows ? '@echo off' : '#!/usr/bin/env node')

    if (! onWindows) {
        chmodSync(file, 0o755)
    }

    return file
}

const tool = (overrides) => ({
    name: 'php',
    onPath: 'definitely-not-a-real-command-2350',
    hint: 'Install it.',
    ...overrides,
})

describe('resolveTool refuses a candidate it cannot run, at resolution', () => {
    // The load-bearing one. Not "does isExecutable say false" — that is covered next door — but
    // that a false STOPS resolution rather than being returned for a job to trip over later.
    it('throws instead of returning a candidate this OS cannot execute', () => {
        const file = refusedFile()

        expect(() => resolveTool(tool({ candidates: [file], envVar: 'LOCAL_CI_PHP' }))).toThrow()
    })

    // The guard's whole value is the message: a refusal that fires but names the wrong thing is
    // barely better than the downstream failure it replaced.
    it('names the resolved path and the variable that chose it, for an override at index 0', () => {
        const file = refusedFile()

        let message = ''

        try {
            resolveTool(tool({ candidates: [file], envVar: 'LOCAL_CI_PHP' }))
        } catch (error) {
            message = error.message
        }

        expect(message).toContain(file)
        expect(message).toContain('$LOCAL_CI_PHP')
        expect(message).toContain('cannot execute')
        expect(message).toContain('Install it.')
    })

    // A non-env candidate has no variable to name, so the wording degrades deliberately rather
    // than printing a misleading `$undefined`. Pinned because it is a judgment call: such a
    // candidate aborts the run instead of falling through to the next one or to which().
    it('uses generic wording for a candidate above index 0, naming no variable', () => {
        const runnable = runnableFile()
        const file = refusedFile()

        let message = ''

        try {
            // Index 0 is absent, so the first candidate actually examined is at index 1.
            resolveTool(tool({ candidates: [join(runnable, 'absent'), file], envVar: 'LOCAL_CI_PHP' }))
        } catch (error) {
            message = error.message
        }

        expect(message).toContain(file)
        expect(message).toContain('this candidate path')
        expect(message).not.toContain('$LOCAL_CI_PHP')
    })

    // Aborting rather than falling through is the deliberate half of the same call: a later
    // runnable candidate does NOT rescue an earlier one the OS refuses.
    it('aborts rather than falling through to a runnable candidate behind it', () => {
        const bad = refusedFile()
        const good = runnableFile()

        expect(() => resolveTool(tool({ candidates: [bad, good], envVar: 'LOCAL_CI_PHP' }))).toThrow()
    })
})

describe('resolveTool leaves the working paths alone', () => {
    it('returns a candidate this OS can execute', () => {
        const file = runnableFile()

        expect(resolveTool(tool({ candidates: [file], envVar: 'LOCAL_CI_PHP' }))).toBe(file)
    })

    it('skips a candidate that does not exist and takes the next one', () => {
        const file = runnableFile()

        expect(resolveTool(tool({ candidates: [join(tmpdir(), 'resolve-tool-absent', 'nope'), file] }))).toBe(file)
    })

    // The asymmetry, pinned because it is real rather than incidental: `which()` decides on
    // `isFile()` alone, so a PATH hit is returned WITHOUT the can-it-run check an override
    // gets. A file the OS cannot run is the only fixture that tells the two paths apart — if the
    // check ever moved to cover which(), this goes red and the change is visible.
    //
    // POSIX ONLY, and not for convenience: on Windows the two functions consult the SAME PATHEXT
    // list, so a file which() can find is by construction one isExecutable() accepts and the
    // asymmetry does not exist there. A `.phar` fixture would simply not be found, and the test
    // would pass on the wrong branch — reporting agreement where there is nothing to compare.
    it.skipIf(onWindows)('does not apply the can-it-run check to a which()-resolved tool', () => {
        const file = refusedFile()
        const dir = dirname(file)

        process.env.PATH = `${dir}${delimiter}${originalPath}`

        expect(isExecutable(file)).toBe(false)
        expect(resolveTool(tool({ candidates: [], onPath: 'tool' }))).toBe(file)
    })
})
