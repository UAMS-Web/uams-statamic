#!/usr/bin/env node
/**
 * Tie a chain's band figures to the interpreter that produced them (UAMS-Web/uams-statamic-archive#3235).
 *
 * The runner prints the PHP it resolved in its header and leaves the band figures in
 * Pest's own output, so nothing that outlives the log says which interpreter a figure
 * came from. When Herd's PHP moved from 8.4.25 to 8.4.27 on one machine, the next chain
 * reported 10 more parallel assertions for the same tree, and the move could not be
 * told apart from a code change. The runner now prints each band beside the version in
 * its summary block and in the commit status it posts, which is what a verdict quotes.
 *
 * READ BY PREFIX, NEVER BY POSITION. The header's `PHP:` line sat at line 24 in two of
 * three logs and at line 23 in the third, because the fetch section is a line shorter
 * when the branch already exists locally. `interpreterFromLog()` matches the line's own
 * prefix, so a shorter header still yields the interpreter rather than the line after it.
 *
 * COUNTED FROM THE TESTCASES. The figures are summed over every `<testcase>` in the
 * band's JUnit report rather than read from a suite's attributes, because a merged
 * parallel report and a single-file report nest their suites differently, and a total
 * read from the wrong level counts one class as the band.
 *
 * Usage:  node scripts/local-ci-bands.mjs <ci.log>
 * Output: the interpreter the chain resolved, as `PHP <version> (<path>)`.
 * Exit:   0 = found; 1 = the log has no `PHP:` line; 2 = the arguments are wrong.
 */
import { existsSync, readFileSync } from 'node:fs'
import { pathToFileURL } from 'node:url'

/**
 * The interpreter a chain's header names, read from the line that starts `PHP:`.
 *
 * @param {string} log The runner's whole output.
 * @returns {?{path: string, version: string}} null when no header line is present.
 */
export function interpreterFromLog(log) {
    const match = /^PHP:[ \t]+(.+?)[ \t]+\(([^()\r\n]*)\)[ \t]*\r?$/m.exec(String(log))

    return match ? { path: match[1], version: match[2] } : null
}

/**
 * The totals of one band, summed over the testcases of its JUnit report.
 *
 * @param {string} xml The report PHPUnit wrote with `--log-junit`.
 * @returns {{tests: number, skipped: number, failed: number, assertions: number}}
 */
export function bandFromJunit(xml) {
    const totals = { tests: 0, skipped: 0, failed: 0, assertions: 0 }

    for (const match of String(xml).matchAll(/<testcase\b([^>]*?)(\/>|>([\s\S]*?)<\/testcase>)/g)) {
        const assertions = /\bassertions="(\d+)"/.exec(match[1])
        const body = match[3] ?? ''

        totals.tests++
        totals.assertions += assertions ? Number(assertions[1]) : 0

        if (/<(failure|error)\b/.test(body)) {
            totals.failed++
        } else if (/<skipped\b/.test(body)) {
            totals.skipped++
        }
    }

    return totals
}

/**
 * One band as the summary prints it, with the interpreter that produced it.
 *
 * @param {string} name The band, such as `parallel`.
 * @param {{tests: number, skipped: number, failed: number, assertions: number}} band
 * @param {string} phpVersion The version the runner resolved.
 * @returns {string}
 */
export function bandLine(name, band, phpVersion) {
    const passed = band.tests - band.skipped - band.failed
    const parts = [`${passed.toLocaleString('en-US')} passed`]

    if (band.skipped) {
        parts.push(`${band.skipped.toLocaleString('en-US')} skipped`)
    }
    if (band.failed) {
        parts.push(`${band.failed.toLocaleString('en-US')} failed`)
    }

    return `${name}: ${parts.join(', ')} (${band.assertions.toLocaleString('en-US')} assertions) under PHP ${phpVersion}`
}

/* c8 ignore start -- the command-line wrapper; the functions above are tested directly */
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const [file] = process.argv.slice(2)

    if (!file || !existsSync(file)) {
        process.stderr.write('Usage: node scripts/local-ci-bands.mjs <ci.log>\n')
        process.exit(2)
    }

    const interpreter = interpreterFromLog(readFileSync(file, 'utf8'))

    if (!interpreter) {
        process.stderr.write(`local-ci-bands: no "PHP:" line in ${file}\n`)
        process.exit(1)
    }

    process.stdout.write(`PHP ${interpreter.version} (${interpreter.path})\n`)
}
/* c8 ignore stop */
