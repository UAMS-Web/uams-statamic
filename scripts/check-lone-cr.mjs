#!/usr/bin/env node
/**
 * Reject a lone `0x0D` byte in tracked text (UAMS-Web/uams-statamic-archive#2495).
 *
 * A carriage return that is NOT part of a CRLF pair is invisible in every editor,
 * survives every guard this repository already runs, and rewrites itself the first
 * time a newline-translating reader touches the file.
 *
 *   [`.gitattributes`] sets `* text=auto eol=lf`, which normalizes CRLF on checkin
 *   and checkout. A LONE CR is not a line ending to git, so it passes through
 *   untouched. Pint does not see it either: measured on the merged instance below,
 *   `pint --test` passes and `pint` leaves the byte in place. So neither the
 *   `eol=lf` policy nor the `pint` job reaches this class.
 *
 * THE INSTANCE THAT PROMPTED IT. `tests/Feature/Users/RegistrantRecordsAreGitIgnoredTest.php`
 * carried a raw `0x0D` inside a single-quoted string literal — `str_replace('<CR>', '', …)`
 * — and the first edit that read the file through a newline-translating text mode
 * rewrote it to `0x0A` and broke the parser. Python's default `open(p).read()` does
 * exactly that, as does any editor with universal newlines. The damage lands with no
 * diff hunk that reads as a content change.
 *
 * BINARY FILES ARE SKIPPED BY GIT'S OWN RULE, NOT BY AN EXTENSION LIST. Git calls a
 * file binary when a NUL byte appears in the first 8000; this applies that rule rather
 * than inventing a second one. Measured over 3,495 tracked files when this was written:
 * 36 carried a lone CR, 35 of them binary (18 `.jpg`, 12 `.woff2`, 3 `.png`, 2 `.xlsx`)
 * and every one holding a NUL, and exactly 1 was text — the instance above. The
 * partition was clean with nothing on the boundary, which is why no exclusion list is
 * carried here: a list would need maintaining and this does not.
 *
 * WHY NOT `grep -lP '\r'`, which the ticket sketched "in spirit". Git Bash grep refuses
 * `-P` outright, exiting 2 with a locale complaint rather than a result — and it refuses
 * identically on a fixture that CONTAINS the byte and one that does not. Both controls
 * return 2, so it cannot discriminate, and through a pipe it prints nothing and reads
 * exactly like a clean tree. A first pass here reported the tree clean for that reason.
 * The instrument has to be one that runs on both platforms, which is why this is Node
 * beside the two guards the `encoding` job already runs.
 *
 * Usage:  node scripts/check-lone-cr.mjs [path ...]     (default: every tracked file)
 * Exit:   0 = no lone CR in tracked text, 1 = findings, 2 = the scan could not run.
 */
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

/** Git's own binary heuristic: a NUL byte within the first 8000. */
const BINARY_SNIFF_BYTES = 8000

/**
 * Whether git would treat these bytes as binary.
 *
 * @param  {Buffer}  bytes  File contents.
 * @return {boolean}
 */
export function isBinary(bytes) {
    return bytes.subarray(0, BINARY_SNIFF_BYTES).includes(0)
}

/**
 * Every lone carriage return in a buffer, as one-based line and column.
 *
 * A CR followed by LF is a CRLF pair and is not reported — `eol=lf` owns that class,
 * and conflating the two would make this fire on every file a Windows editor saved.
 *
 * @param  {Buffer}  bytes  File contents.
 * @return {Array<{line: number, column: number}>}
 */
export function loneCarriageReturns(bytes) {
    const findings = []
    let line = 1
    let column = 1

    for (let i = 0; i < bytes.length; i++) {
        if (bytes[i] === 0x0a) {
            line++
            column = 1
            continue
        }

        if (bytes[i] === 0x0d && bytes[i + 1] !== 0x0a) {
            findings.push({ line, column })
        }

        column++
    }

    return findings
}

/**
 * Tracked paths, or the paths named on the command line.
 *
 * @param  {Array<string>}  argv  Arguments after the script name.
 * @return {Array<string>}
 */
function targets(argv) {
    if (argv.length > 0) {
        return argv
    }

    const listed = execFileSync('git', ['ls-files', '-z'], { maxBuffer: 1 << 28 })

    return listed.toString('utf8').split('\0').filter(Boolean)
}

let paths

try {
    paths = targets(process.argv.slice(2))
} catch (error) {
    process.stderr.write(`check-lone-cr: could not list tracked files — ${error.message}\n`)
    process.exit(2)
}

if (paths.length === 0) {
    // An empty target set is indistinguishable from a clean one, so it is a failure.
    process.stderr.write('check-lone-cr: no files to scan, which is a failure rather than a pass.\n')
    process.exit(2)
}

const findings = []
let scanned = 0
let skippedBinary = 0

for (const path of paths) {
    let bytes

    try {
        bytes = readFileSync(path)
    } catch {
        // A tracked path that is not readable here — a submodule gitlink, or a file
        // removed in the working tree — is not this check's business.
        continue
    }

    if (isBinary(bytes)) {
        skippedBinary++
        continue
    }

    scanned++

    for (const at of loneCarriageReturns(bytes)) {
        findings.push({ path, ...at })
    }
}

// The denominator is printed BEFORE any finding, so a crash mid-scan leaves a visibly
// incomplete report rather than one that reads as clean.
process.stdout.write(`check-lone-cr: scanned ${scanned} text file(s), skipped ${skippedBinary} binary.\n`)

if (findings.length === 0) {
    process.stdout.write('check-lone-cr: no lone carriage return found.\n')
    process.exit(0)
}

process.stdout.write(`\nFound ${findings.length} lone carriage return(s):\n\n`)

for (const finding of findings) {
    process.stdout.write(`  ${finding.path}, line ${finding.line}, column ${finding.column}\n`)
}

process.stdout.write(
    '\nA lone CR is invisible, survives `eol=lf` and Pint, and is rewritten to LF by the\n'
    + 'first reader that translates newlines — silently, with no hunk that reads as a\n'
    + 'content change. Inside a string literal that changes what the code does.\n'
    + 'Write it as the two-character escape \\r rather than as a raw byte.\n'
)

process.exit(1)
