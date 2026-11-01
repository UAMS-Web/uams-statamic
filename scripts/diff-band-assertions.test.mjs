// Coverage for naming the tests behind a moved assertion total (#335).
//
// The CLI cases run the script itself, because its exit status is the contract: a report
// it could not read must never come back looking like two reports that match.

import { spawnSync } from 'node:child_process'
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { assertionDifferences, assertionsByTest } from './diff-band-assertions.mjs'

const script = fileURLToPath(new URL('./diff-band-assertions.mjs', import.meta.url))

/**
 * A report holding the given testcases, in PHPUnit's attribute order.
 *
 * @param {Array<[string, string, number]>} cases Class, name and assertion count.
 * @returns {string}
 */
function report(cases) {
    const lines = cases.map(([owner, name, assertions]) => `<testcase name="${name}" class="${owner}" classname="x" file="x.php::${name}" line="0" assertions="${assertions}" time="1.0"/>`)

    return `<?xml version="1.0"?>\n<testsuites>\n<testsuite name="" tests="${cases.length}">\n${lines.join('\n')}\n</testsuite>\n</testsuites>\n`
}

describe('assertionsByTest', () => {
    it('keys a count by class and name, on self-closing and failing testcases', () => {
        const xml = `${report([['Tests\\A', 'it passes', 3]])}<testcase name="it fails" class="Tests\\A" assertions="2" time="1.0">\n<failure>nope</failure>\n</testcase>`

        expect([...assertionsByTest(xml)]).toEqual([
            ['Tests\\A :: it passes', 3],
            ['Tests\\A :: it fails', 2],
        ])
    })

    it('tells two classes apart when they share a test name', () => {
        const counts = assertionsByTest(report([['Tests\\A', 'it runs', 1], ['Tests\\B', 'it runs', 4]]))

        expect(counts.size).toBe(2)
    })

    it('throws on a testcase it cannot read rather than counting it as zero', () => {
        expect(() => assertionsByTest('<testcase name="it runs" class="Tests\\A"/>')).toThrow('assertions')
    })
})

describe('assertionDifferences', () => {
    it('returns only the tests whose count moved', () => {
        const first = new Map([['b', 40], ['a', 2]])
        const second = new Map([['b', 43], ['a', 2]])

        expect(assertionDifferences(first, second)).toEqual([{ test: 'b', first: 40, second: 43 }])
    })

    it('reports a test present on one side only', () => {
        const differences = assertionDifferences(new Map([['a', 1]]), new Map([['b', 1]]))

        expect(differences).toEqual([
            { test: 'a', first: 1, second: null },
            { test: 'b', first: null, second: 1 },
        ])
    })
})

describe('the command', () => {
    let directory

    /**
     * Run the script over two reports written to disk.
     *
     * @param {string} first
     * @param {string} second
     * @returns {import('node:child_process').SpawnSyncReturns<string>}
     */
    function run(first, second) {
        writeFileSync(join(directory, 'a.xml'), first)
        writeFileSync(join(directory, 'b.xml'), second)

        return spawnSync(process.execPath, [script, join(directory, 'a.xml'), join(directory, 'b.xml')], { encoding: 'utf8' })
    }

    beforeEach(() => {
        directory = mkdtempSync(join(tmpdir(), 'diff-band-assertions-'))
    })

    afterEach(() => {
        rmSync(directory, { recursive: true, force: true })
    })

    it('exits 0 when no test differs', () => {
        const xml = report([['Tests\\A', 'it runs', 3]])
        const result = run(xml, xml)

        expect(result.status).toBe(0)
        expect(result.stdout).toContain('0 of 1 tests differ; totals 3 and 3')
    })

    it('exits 1 and names the test when one differs', () => {
        const result = run(report([['Tests\\A', 'it retries', 40]]), report([['Tests\\A', 'it retries', 43]]))

        expect(result.status).toBe(1)
        expect(result.stdout).toContain('40 -> 43  Tests\\A :: it retries')
        expect(result.stdout).toContain('totals 40 and 43')
    })

    it('exits 2 on a report with no testcases rather than calling the two a match', () => {
        const result = run('<testsuites/>', '<testsuites/>')

        expect(result.status).toBe(2)
        expect(result.stderr).toContain('no testcases')
    })

    it('exits 2 when a report is missing', () => {
        const result = spawnSync(process.execPath, [script, join(directory, 'gone.xml')], { encoding: 'utf8' })

        expect(result.status).toBe(2)
    })
})
