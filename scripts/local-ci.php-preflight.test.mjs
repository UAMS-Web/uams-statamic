import { describe, it, expect, afterEach } from 'vitest'
import { chmodSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { phpVersionOrRefuse } from './local-ci.mjs'
import { which } from './spawn-helpers.mjs'

// UAMS-Web/uams-statamic-archive#2312. Preflight reported a MISSING IMAGICK EXTENSION when the PHP binary could not execute at
// all. The extension was present; the probe that looked for it was killed, and its empty output
// read as absence — the same way the version probe immediately above it had already produced the
// tell-tale `PHP: /path/php84 ()` without anything branching on it.
//
// The costly part is not the false negative. A bare wrong answer leaves a reader puzzled; this one
// handed them a confident, useless task — install imagick, read README_Imagick.md — for a fault
// that had nothing to do with imagick. And because preflight runs before job selection, it aborted
// runs that asked only for node jobs and never needed PHP.
//
// So what these pin is a boundary: PHP must be shown to RUN before anything is claimed about what
// it HAS, and the refusal must not name a remedy the preflight has not established the need for.

const onWindows = process.platform === 'win32'
const made = []

afterEach(() => {
    while (made.length) {
        rmSync(made.pop(), { recursive: true, force: true })
    }
})

/** A real, runnable program that exits non-zero and prints nothing — "runs, cannot answer". */
function silentFailure() {
    const dir = mkdtempSync(join(tmpdir(), 'php-preflight-fail-'))
    made.push(dir)

    const file = join(dir, onWindows ? 'php.cmd' : 'php')
    writeFileSync(file, onWindows ? '@echo off\r\nexit /b 1\r\n' : '#!/bin/sh\nexit 1\n')

    if (! onWindows) {
        chmodSync(file, 0o755)
    }

    return file
}

/** Runs, prints something on stdout, and still exits non-zero — the case the STATUS check alone catches. */
function noisyFailure() {
    const dir = mkdtempSync(join(tmpdir(), 'php-preflight-noisy-'))
    made.push(dir)

    const file = join(dir, onWindows ? 'php.cmd' : 'php')
    writeFileSync(
        file,
        onWindows ? '@echo off\r\necho PHP Startup: broken\r\nexit /b 1\r\n' : '#!/bin/sh\necho "PHP Startup: broken"\nexit 1\n',
    )

    if (! onWindows) {
        chmodSync(file, 0o755)
    }

    return file
}

/**
 * Runs, succeeds, and prints nothing — exit 0 with empty stdout.
 *
 * The only shape the empty-string clause catches ALONE, and the one acceptance criterion 1
 * names outright: "preflight fails when the PHP version probe returns empty". Every other
 * failure fixture here exits non-zero, so without this the status clause covers them all and
 * `|| version === ''` can be deleted green. Found by the gate running the mirror of the
 * mutation recorded in the pull request, which had only been run in one direction.
 */
function silentSuccess() {
    const dir = mkdtempSync(join(tmpdir(), 'php-preflight-quiet-'))
    made.push(dir)

    const file = join(dir, onWindows ? 'php.cmd' : 'php')
    writeFileSync(file, onWindows ? '@echo off\r\nexit /b 0\r\n' : '#!/bin/sh\nexit 0\n')

    if (! onWindows) {
        chmodSync(file, 0o755)
    }

    return file
}

/** A real file the OS refuses to execute, so the spawn itself fails. */
function refusedByOs() {
    const dir = mkdtempSync(join(tmpdir(), 'php-preflight-'))
    made.push(dir)

    const file = join(dir, onWindows ? 'php.phar' : 'php')
    writeFileSync(file, 'not a real interpreter')

    if (! onWindows) {
        chmodSync(file, 0o644)
    }

    return file
}

describe('phpVersionOrRefuse', () => {
    // The happy path through the REAL binary, so it is not asserted against a stub that agrees
    // with the test by construction. Skipped rather than failed where php is not on PATH: this
    // asserts the guard lets a working interpreter through, not that a machine has one.
    it.skipIf(which('php') === null)('returns the version when PHP runs', () => {
        const version = phpVersionOrRefuse('php', process.cwd())

        expect(version).toMatch(/^\d+\.\d+/)
    })

    it('refuses when the interpreter cannot be spawned at all', () => {
        expect(() => phpVersionOrRefuse(join(tmpdir(), 'php-absent-2312', 'php'), process.cwd())).toThrow()
    })

    it('refuses a file the OS will not execute', () => {
        expect(() => phpVersionOrRefuse(refusedByOs(), process.cwd())).toThrow()
    })

    // The exact shape the old `capture()` flattened: a program that RUNS, exits non-zero and
    // prints nothing. Trimmed stdout cannot tell this from a healthy PHP that printed nothing,
    // which is why the status has to be read.
    it('refuses when the probe exits non-zero with no output', () => {
        expect(() => phpVersionOrRefuse(silentFailure(), process.cwd())).toThrow(/did not report a version/)
    })

    // The survivor that the empty-output case cannot kill, found by mutation: drop the status
    // check and every other assertion here still passes. A PHP that prints a startup warning on
    // stdout and exits non-zero yields a NON-empty `version` that is not a version at all, and
    // only the exit status distinguishes it from a healthy answer. Without this the guard would
    // hand `warnVersion()` and the `PHP: … (…)` banner a line of diagnostic noise as the version.
    it('refuses when the probe exits non-zero even though it printed something', () => {
        expect(() => phpVersionOrRefuse(noisyFailure(), process.cwd())).toThrow(/did not report a version/)
    })

    // The mirror survivor: an interpreter that reports SUCCESS and says nothing. The status is
    // 0, so only the empty-string clause refuses it — and a preflight that accepted it would
    // carry `''` forward into `warnVersion()` and print the `PHP: /path ()` banner that was the
    // original defect's own unheeded evidence.
    it('refuses when the probe exits zero but prints nothing', () => {
        expect(() => phpVersionOrRefuse(silentSuccess(), process.cwd())).toThrow(/did not report a version/)
    })

    // THE ONE THAT MATTERS. The old path reached the extension check and blamed imagick for a dead
    // interpreter. Whatever else the message says, it must not send the reader after an extension.
    it('never names imagick or its README, for any refusal', () => {
        const cases = [
            join(tmpdir(), 'php-absent-2312', 'php'),
            refusedByOs(),
            silentFailure(),
            noisyFailure(),
            silentSuccess(),
        ]

        for (const candidate of cases) {
            let message = ''

            try {
                phpVersionOrRefuse(candidate, process.cwd())
            } catch (error) {
                message = error.message
            }

            expect(message, candidate).not.toMatch(/imagick/i)
            expect(message, candidate).not.toMatch(/README_Imagick/i)
        }
    })

    // The refusal has to be actionable on its own terms, or it trades one unhelpful message for
    // another: it names the interpreter it tried, and points at running it directly.
    it('names the interpreter and says nothing further is established', () => {
        const file = refusedByOs()

        let message = ''

        try {
            phpVersionOrRefuse(file, process.cwd())
        } catch (error) {
            message = error.message
        }

        expect(message).toContain(file)
    })
})
