#!/usr/bin/env node
/**
 * Name the tests whose assertion count differs between two band reports (#335).
 *
 * A band's assertion total can move between two runs of one tree while its test count
 * stays put, and the total alone says nothing about where. Each `<testcase>` in a report
 * carries its own count, so comparing the reports test by test names the source.
 *
 * REFUSES WHAT IT CANNOT COMPARE. An unreadable report and two identical reports would
 * otherwise both print nothing, so a report with no testcases, with a testcase it cannot
 * read, or with one test listed twice exits 2 and is never reported as a match. Exit 1
 * means only that two readable reports differ.
 *
 * Usage:  node scripts/diff-band-assertions.mjs <first junit.xml> <second junit.xml>
 * Output: one line per differing test, `<first> -> <second>  <class> :: <name>`, then a
 *         summary with both totals.
 * Exit:   0 = no test differs; 1 = at least one does; 2 = the arguments or a report are
 *         unusable.
 */
import { existsSync, readFileSync } from 'node:fs'
import { pathToFileURL } from 'node:url'

/**
 * Each test's assertion count, keyed by class and test name.
 *
 * @param {string} xml The report PHPUnit wrote with `--log-junit`.
 * @returns {Map<string, number>}
 */
export function assertionsByTest(xml) {
    const counts = new Map()

    for (const match of String(xml).matchAll(/<testcase\b([^>]*)>/g)) {
        const name = /\bname="([^"]*)"/.exec(match[1])?.[1]
        const owner = /\bclass="([^"]*)"/.exec(match[1])?.[1]
        const assertions = /\bassertions="(\d+)"/.exec(match[1])?.[1]

        if (name === undefined || owner === undefined || assertions === undefined) {
            throw new Error(`testcase without a name, class or assertions attribute: ${match[0].slice(0, 120)}`)
        }

        const key = `${owner} :: ${name}`

        if (counts.has(key)) {
            throw new Error(`the report lists one test twice: ${key}`)
        }

        counts.set(key, Number(assertions))
    }

    return counts
}

/**
 * The tests that differ between two reports. A test present in only one has a null count
 * on the other side, so a changed test set is reported rather than skipped.
 *
 * @param {Map<string, number>} first
 * @param {Map<string, number>} second
 * @returns {Array<{test: string, first: ?number, second: ?number}>} Sorted by test.
 */
export function assertionDifferences(first, second) {
    const differences = []

    for (const test of new Set([...first.keys(), ...second.keys()])) {
        const left = first.get(test) ?? null
        const right = second.get(test) ?? null

        if (left !== right) {
            differences.push({ test, first: left, second: right })
        }
    }

    return differences.sort((a, b) => (a.test < b.test ? -1 : a.test > b.test ? 1 : 0))
}

/**
 * The total of one report's counts.
 *
 * @param {Map<string, number>} counts
 * @returns {number}
 */
function total(counts) {
    return [...counts.values()].reduce((sum, count) => sum + count, 0)
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const paths = process.argv.slice(2)

    if (paths.length !== 2 || !paths.every((path) => existsSync(path))) {
        console.error('Usage: node scripts/diff-band-assertions.mjs <first junit.xml> <second junit.xml>')
        process.exit(2)
    }

    let first
    let second

    try {
        ;[first, second] = paths.map((path) => assertionsByTest(readFileSync(path, 'utf8')))
    } catch (error) {
        console.error(`A report could not be read: ${error.message}`)
        process.exit(2)
    }

    if (first.size === 0 || second.size === 0) {
        console.error('A report holds no testcases, so nothing was compared.')
        process.exit(2)
    }

    const differences = assertionDifferences(first, second)

    for (const difference of differences) {
        console.log(`${difference.first ?? 'absent'} -> ${difference.second ?? 'absent'}  ${difference.test}`)
    }

    const tests = new Set([...first.keys(), ...second.keys()]).size

    console.log(`${differences.length} of ${tests} tests differ; totals ${total(first)} and ${total(second)}`)
    process.exit(differences.length === 0 ? 0 : 1)
}
