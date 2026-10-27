// Coverage for tying a chain's band figures to its interpreter (UAMS-Web/uams-statamic-archive#3235).
//
// The header's `PHP:` line moved between line 23 and line 24 across three logs of one
// tree, so the reader is tested on both shapes and must return the interpreter rather
// than whatever line follows it. The band totals are summed over testcases, so both a
// single-file report and a merged one with nested suites are covered.

import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { bandFromJunit, bandLine, interpreterFromLog } from './local-ci-bands.mjs'
import { commitStatusDescription, pestBandLines } from './local-ci.mjs'

/**
 * A runner header, with the fetch section one line longer or shorter.
 *
 * @param {boolean} branchExistsLocally The shorter form, as when the fetch skips a line.
 * @returns {string}
 */
function header(branchExistsLocally) {
    return [
        '==> Fetching origin',
        ...(branchExistsLocally ? [] : ['From github.com:UAMS-Web/uams-statamic', ' * [new branch] 3235-band-interpreter -> origin/3235-band-interpreter']),
        '==> Resolving local toolchain',
        'PHP:       /Users/kirk/Library/Application Support/Herd/bin/php84 (8.4.27)',
        'Composer:  /Users/kirk/Library/Application Support/Herd/bin/composer',
        'Node:      /usr/local/bin/node (v22.22.3)',
    ].join('\n')
}

describe('interpreterFromLog', () => {
    it('reads the interpreter whichever line of the header it sits on', () => {
        const long = header(false)
        const short = header(true)

        // The two shapes put the PHP line at different positions, which is the case
        // a position-based read gets wrong.
        expect(long.split('\n').findIndex((line) => line.startsWith('PHP:')))
            .not.toBe(short.split('\n').findIndex((line) => line.startsWith('PHP:')))

        for (const log of [long, short]) {
            expect(interpreterFromLog(log)).toEqual({
                path: '/Users/kirk/Library/Application Support/Herd/bin/php84',
                version: '8.4.27',
            })
        }
    })

    it('returns the interpreter, not the line that follows it', () => {
        const interpreter = interpreterFromLog(header(true))

        expect(interpreter?.path).not.toContain('composer')
        expect(interpreter?.version).not.toContain('Composer')
    })

    it('keeps a parenthesis inside the path out of the version', () => {
        const log = 'PHP:       C:\\Program Files (x86)\\php84\\php.exe (8.4.25)\r\nComposer:  composer\r\n'

        expect(interpreterFromLog(log)).toEqual({ path: 'C:\\Program Files (x86)\\php84\\php.exe', version: '8.4.25' })
    })

    it('ignores a line that only mentions PHP further along', () => {
        const log = 'Warning: PHP: 8.3.0 (old)\nPHP:       /opt/php84 (8.4.27)\n'

        expect(interpreterFromLog(log)?.version).toBe('8.4.27')
    })

    it('returns null for a log with no header line', () => {
        expect(interpreterFromLog('Tests:    10 passed (19 assertions)\n')).toBeNull()
        expect(interpreterFromLog('')).toBeNull()
    })
})

describe('bandFromJunit', () => {
    it('counts a single-file report', () => {
        const xml = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Tests\\Unit\\ScottyTest" tests="3" assertions="7" errors="0" failures="0" skipped="1">
    <testcase name="it beams" assertions="4" time="0.01"/>
    <testcase name="it beams twice" assertions="3" time="0.01"></testcase>
    <testcase name="it waits" assertions="0" time="0.01"><skipped/></testcase>
  </testsuite>
</testsuites>`

        expect(bandFromJunit(xml)).toEqual({ tests: 3, skipped: 1, failed: 0, assertions: 7 })
    })

    it('counts every testcase of a merged report, not one suite of it', () => {
        // A suite's own attributes would give 2 here; the band is all four testcases.
        const xml = `<testsuites>
  <testsuite name="" tests="4" assertions="9">
    <testsuite name="Tests\\Feature\\SpockTest" tests="2" assertions="5">
      <testcase name="a" assertions="2"/>
      <testcase name="b" assertions="3"/>
    </testsuite>
    <testsuite name="Tests\\Feature\\McCoyTest" tests="2" assertions="4">
      <testcase name="c" assertions="4"/>
      <testcase name="d" assertions="0"><failure type="x">Failed asserting</failure></testcase>
    </testsuite>
  </testsuite>
</testsuites>`

        expect(bandFromJunit(xml)).toEqual({ tests: 4, skipped: 0, failed: 1, assertions: 9 })
    })

    it('counts an error as a failure, and nothing for an empty report', () => {
        expect(bandFromJunit('<testcase name="e" assertions="1"><error type="Error">boom</error></testcase>'))
            .toEqual({ tests: 1, skipped: 0, failed: 1, assertions: 1 })
        expect(bandFromJunit('')).toEqual({ tests: 0, skipped: 0, failed: 0, assertions: 0 })
    })
})

describe('bandLine', () => {
    it('prints a band beside the interpreter that produced it', () => {
        expect(bandLine('parallel', { tests: 12910, skipped: 1, failed: 0, assertions: 36638 }, '8.4.27'))
            .toBe('parallel: 12,909 passed, 1 skipped (36,638 assertions) under PHP 8.4.27')
    })

    it('names failures and leaves out a zero skip count', () => {
        expect(bandLine('serial', { tests: 166, skipped: 0, failed: 2, assertions: 540 }, '8.4.25'))
            .toBe('serial: 164 passed, 2 failed (540 assertions) under PHP 8.4.25')
    })
})

describe('pestBandLines', () => {
    let tree

    beforeEach(() => {
        tree = mkdtempSync(join(tmpdir(), 'local-ci-bands-'))
    })

    afterEach(() => {
        rmSync(tree, { recursive: true, force: true })
    })

    it('reads both reports when pest ran', () => {
        writeFileSync(join(tree, 'junit-parallel.xml'), '<testcase assertions="2"/><testcase assertions="3"/>')
        writeFileSync(join(tree, 'junit-serial.xml'), '<testcase assertions="1"/>')

        expect(pestBandLines(tree, new Map([['pest', 0]]), '8.4.27')).toEqual([
            'parallel: 2 passed (5 assertions) under PHP 8.4.27',
            'serial: 1 passed (1 assertions) under PHP 8.4.27',
        ])
    })

    it('names a band whose report is missing rather than leaving it out', () => {
        writeFileSync(join(tree, 'junit-parallel.xml'), '<testcase assertions="2"/>')

        expect(pestBandLines(tree, new Map([['pest', 1]]), '8.4.27')).toEqual([
            'parallel: 1 passed (2 assertions) under PHP 8.4.27',
            'serial: no report written under PHP 8.4.27',
        ])
    })

    it('reads no report left by an earlier run when pest did not run in this one', () => {
        writeFileSync(join(tree, 'junit-parallel.xml'), '<testcase assertions="2"/>')

        expect(pestBandLines(tree, new Map([['pint', 0]]), '8.4.27')).toEqual([])
        expect(pestBandLines(tree, new Map([['pest', 'skipped']]), '8.4.27')).toEqual([])
    })
})

describe('commitStatusDescription with the runner\'s own results', () => {
    it('accepts the Map the runner holds, and names the interpreter', () => {
        // The summary passes a Map; an array-only builder threw here.
        const results = new Map([['spell', 0], ['js', 'skipped'], ['pest', 1]])

        expect(commitStatusDescription(true, results, '8.4.27')).toBe('pest failed; 1 chain-gated under PHP 8.4.27')
        expect(commitStatusDescription(false, new Map([['spell', 0]]), '8.4.27')).toBe('1 of 1 jobs passed under PHP 8.4.27')
    })
})
