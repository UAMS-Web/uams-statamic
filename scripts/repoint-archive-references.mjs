#!/usr/bin/env node
/**
 * Re-point references to this repository's history at `uams-statamic-archive` (#3383).
 *
 * This repository is renamed `UAMS-Web/uams-statamic-archive` and a new repository takes
 * the old name (#2659). GitHub's rename redirect stops when the new repository is created,
 * so from then on a link to `UAMS-Web/uams-statamic` resolves against the new repository,
 * where this one's closed issues, pull requests and commits do not exist. The same script
 * re-pointed `UAMS-Web/wordpress-importer-archive` (its #1427 and #1432).
 *
 * Two modes:
 *
 *   files   [--write]                 tracked files in this checkout
 *   bodies  --repo <owner/name> [--apply]   issue and pull-request bodies and comments
 *
 * Both print what they would change and change nothing without `--write` or `--apply`.
 *
 * ## What is re-pointed
 *
 * - `github.com/UAMS-Web/uams-statamic/{pull,issues,commit,compare}/...`
 * - `github.com/UAMS-Web/uams-statamic/{blob,tree}/<ref>/...` at any ref but `main` or a
 *   `<placeholder>` such as the pull-request template's `<branch>`
 * - `UAMS-Web/uams-statamic#N`, and the bare `uams-statamic#N`
 * - in tracked files only, a bare `#N` that names this repository's history (below)
 *
 * The repository root, `blob/main` and `tree/main` are left alone: they mean the package
 * going forward, and the new repository's first commit carries the same tree. So are the
 * Composer package name and the clone URLs, which name no issue, pull request or commit.
 *
 * ## Which bare `#N` names this repository's history
 *
 * A bare `#N` in a tracked file is rewritten to `UAMS-Web/uams-statamic-archive#N` when all of
 * these hold, and is reported as left otherwise:
 *
 * - `N` is at most the highest issue or pull-request number here, so it can name one;
 * - `N` is not an open issue, which moves to the new repository in #2661 and is rewritten
 *   there to its new number (#2660);
 * - the line names no other repository, where the number may well be that repository's;
 * - it is not the text of a Markdown link (`[#N](...)`), whose URL carries the target;
 * - it is not a CSS color written in digits only (`#333`), which a template carries in a
 *   `color`, `background` or `fill` context, and a stylesheet, Vue or SVG file also in a
 *   variable (`$black: #000`) or after a length (`5px #000`);
 * - it is not part of string data. Outside prose files (Markdown and plain text), a code
 *   line that is not a comment is data when any reference on it opens a string (`'#42'`)
 *   or carries a hyphenated suffix (`#42-duns`), and every such line of a test file is
 *   data, links included: the test asserts on those strings, and only its comments are
 *   rewritten.
 *
 * In a body or comment a bare `#N` already resolves against the repository the body is
 * in, which is the archive after the rename, so the `bodies` mode leaves it alone.
 *
 * The files mode also leaves whole files alone (`SKIPPED` below): the Statamic Peak
 * changelog, whose numbers are that starter kit's, and the vendor patches with the patch
 * descriptions in `composer.json`, which `patches.lock.json` hashes; the closing-keyword
 * check and test fixtures, whose numbers are data; and one published Statamic translation
 * file the Pint job would fail on.
 *
 * Commit messages cannot be edited and are out of reach of both modes.
 */

import { execFileSync } from 'node:child_process'
import { readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { pathToFileURL } from 'node:url'

const OWNER = 'UAMS-Web'
const OLD = 'uams-statamic'
const NEW = 'uams-statamic-archive'

// Tracked paths the files mode leaves alone. The submodule gitlinks are directories and
// are skipped when they cannot be read as files.
const SKIPPED = [
    // This script and its test, whose references are the rules' own examples
    'scripts/repoint-archive-references.',
    // The Statamic Peak changelog, whose numbers are that starter kit's pull requests
    'CHANGELOG.md',
    // The vendor patches, whose sha256 `patches.lock.json` records, and that lock, whose
    // `_hash` covers each patch description `composer.json` declares
    'patches/',
    'patches.lock.json',
    'composer.json',
    // The closing-keyword check and its test, whose numbers show the directive syntax the
    // check reads, which a qualified reference would no longer be
    'scripts/check-closing-keyword-across-lines.',
    // Recorded test data, whose `#N` are row labels and inputs rather than references
    'tests/Fixtures/',
    // A translation file published from Statamic that carries no `declare(strict_types=1)`,
    // so the Pint job, which checks every changed PHP file, fails on any edit to it
    'lang/vendor/statamic/en/messages.php',
]

// The old name, not followed by anything that would make it a different repository
const NAME = `${OLD}(?![-\\w])`

/**
 * The rewrite rules shared by both modes, each a pattern and its replacement.
 */
export const RULES = [
    {
        cls: 'url',
        re: new RegExp(`(github\\.com/${OWNER}/)${NAME}(/(?:pull|issues|commit|compare)/)`, 'g'),
        to: `$1${NEW}$2`,
    },
    {
        cls: 'url_ref',
        re: new RegExp(`(github\\.com/${OWNER}/)${NAME}(/(?:blob|tree)/)(?!main(?:[/)\\s#]|$)|<)`, 'g'),
        to: `$1${NEW}$2`,
    },
    {
        cls: 'qualified',
        re: new RegExp(`(?<![\\w./-])(${OWNER}/)${NAME}#(?=\\d)`, 'g'),
        to: `$1${NEW}#`,
    },
    {
        cls: 'short',
        re: new RegExp(`(?<![\\w./-])${NAME}#(?=\\d)`, 'g'),
        to: `${NEW}#`,
    },
]

// A bare reference: not part of a word, a numeric character reference (plain, or with its
// `&` escaped as `&amp;`) or a link target
const BARE = /(?<![\w&;-]|\]\()#(\d{2,4})\b/g

// A CSS color written in digits only: the property or attribute that takes it, then `#`
const COLOR = /(?:color|fill|stroke|background|border|outline|shadow)\s*[:=]\s*["']?$/i

// A file in which `#` and digits may be a color in any property, not only the named ones
const STYLE_FILE = /\.(?:s?css|sass|less|vue|svg|html)$/

// In such a file, a color: a property or variable and its `:`, or a length, before the `#`
const STYLE_COLOR = /(?:[$\w-]+\s*[:=]\s*["']?|\d(?:px|em|rem|%)?\s+)$/

// A file of prose, where every bare reference is one. In any other file a `#N` that opens a
// string or follows an interpolation (`'#42'`, `"#10 role=…"`, `${repo}#71`), or runs on
// into a hyphenated id (`#42-duns`), is test input or data, not a reference.
const PROSE_FILE = /\.(?:md|mdc|txt)$/

// A test, whose strings are inputs and expected output rather than prose
const TEST_FILE = /^tests\/|\.test\.[cm]?[jt]s$|_test\.py$|\.test\.py$/

// A comment line in a code file: `//`, a docblock's `*` or `/*`, a shell or YAML `#`, or a
// Laravel config banner's `|`
const COMMENT = /^\s*(?:\/\/|\/?\*|#(?!\d)|\|)/

// A line citing another repository's issue or pull request, whose bare numbers may be
// that repository's. A line that only names another repository, a package or a product
// (`wordpress-importer`, `statamic/cms v6`, the Find-a-Doc API) does not count: this
// repository qualifies every reference to another tracker, so a bare number beside such
// a name is this repository's.
const OTHER_REPO = new RegExp(
    [
        // owner/repo#N, with the owner/repo optionally in backticks
        '(?<![\\w-])`?[\\w.-]+/[\\w.-]+`?#\\d',
        // repo#N, a repository written without its owner
        '(?<![\\w/#&-])[A-Za-z][\\w.]*-[\\w.-]*#\\d',
        // a link to another repository's issue, pull request or commit
        'github\\.com/[\\w.-]+/[\\w.-]+/(?:issues|pull|commit)/',
    ].join('|'),
)

/**
 * Rewrite one text with the shared rules, counting each class's matches.
 *
 * @param {string} text  the text to rewrite
 * @param {Record<string, number>} counts  per-class counts, added to in place
 * @returns {string} the rewritten text
 */
export function rewriteLinks(text, counts = {}) {
    let out = text
    for (const { cls, re, to } of RULES) {
        const hits = out.match(re)
        if (hits) {
            counts[cls] = (counts[cls] ?? 0) + hits.length
            out = out.replace(re, to)
        }
    }

    return out
}

/**
 * Rewrite the bare `#N` references on one line that name this repository's history.
 *
 * @param {string} line  the line to rewrite
 * @param {{max: number, open: Set<number>}} history  the highest number here and the open issues
 * @param {Array<{n: number, ref: string, why: string}>} left  every bare reference left, added to in place
 * @param {{style?: boolean, prose?: boolean, test?: boolean}} [file]  whether the line's
 *        file is one `STYLE_FILE`, `PROSE_FILE` or `TEST_FILE` matches
 * @returns {{line: string, rewritten: number}} the line and how many references it changed
 */
export function rewriteBare(line, history, left = [], { style = false, prose = true, test = false } = {}) {
    const otherRepo = OTHER_REPO.test(
        line
            .replace(new RegExp(`github\\.com/${OWNER}/(?:${NAME}|${NEW})/`, 'g'), '')
            .replace(new RegExp(`(?:${OWNER}/)?${NAME}#`, 'g'), '#')
            .replace(new RegExp(`(?:${OWNER}/)?${NEW}#`, 'g'), '#'),
    )
    // A comment in a code file is prose. Any other line of a test is data, since a test's
    // strings are its inputs and the output it expects. On any other line of a code file, a
    // single reference that reads as data leaves every reference on the line, so a string is
    // never half rewritten.
    const data = !prose
        && !COMMENT.test(line)
        && (test || [...line.matchAll(BARE)].some(({ 0: match, index }) => (
            /["'`}]$/.test(line.slice(0, index)) || line[index + match.length] === '-'
        )))
    let rewritten = 0
    const out = line.replace(BARE, (match, digits, offset) => {
        const n = Number(digits)
        const linkText = line[offset - 1] === '[' && line.startsWith(`${match}](`, offset)
        let why = null
        const before = line.slice(Math.max(0, offset - 40), offset)
        if (COLOR.test(before) || (style && STYLE_COLOR.test(before))) {
            why = 'CSS color'
        } else if (data) {
            why = 'string data'
        } else if (n > history.max) {
            why = 'above this repository\'s numbers'
        } else if (history.open.has(n)) {
            why = 'open issue, moves in #2661'
        } else if (linkText) {
            why = 'link text'
        } else if (otherRepo) {
            why = 'line names another repository'
        }
        if (why) {
            left.push({ n, ref: match, why })

            return match
        }
        rewritten++

        return `${OWNER}/${NEW}${match}`
    })

    return { line: out, rewritten }
}

/**
 * Run `gh` and return its standard output.
 *
 * @param {string[]} args  the arguments to `gh`
 * @param {string} [input]  standard input
 * @returns {string} the output
 */
function gh(args, input) {
    return execFileSync('gh', args, { encoding: 'utf8', input, maxBuffer: 1 << 28 })
}

/**
 * Read every page of a REST list endpoint, walking `page=N` until a short page.
 *
 * @param {string} path  the endpoint, without a query string's page parameters
 * @returns {Array<object>} every item
 */
function pages(path) {
    const sep = path.includes('?') ? '&' : '?'
    const all = []
    for (let page = 1; ; page++) {
        const items = JSON.parse(gh(['api', `${path}${sep}per_page=100&page=${page}`]))
        all.push(...items)
        if (items.length < 100) {
            return all
        }
    }
}

/**
 * The highest issue or pull-request number here, and the open issues.
 *
 * @returns {{max: number, open: Set<number>}} the history bounds
 */
function readHistory() {
    const newest = JSON.parse(gh(['api', `repos/${OWNER}/${OLD}/issues?state=all&per_page=1`]))
    const open = pages(`repos/${OWNER}/${OLD}/issues?state=open`)
        .filter((issue) => !issue.pull_request)
        .map((issue) => issue.number)

    return { max: newest[0].number, open: new Set(open) }
}

/**
 * Rewrite this checkout's tracked files, skipping files the shared sync manages.
 *
 * @param {boolean} write  whether to write the files
 */
function files(write) {
    const root = execFileSync('git', ['rev-parse', '--show-toplevel'], { encoding: 'utf8' }).trim()
    const synced = new Set(JSON.parse(readFileSync(join(root, '.claude/sync-manifest.jsonc'), 'utf8')).files)
    const tracked = execFileSync('git', ['-C', root, 'ls-files', '-z'], { encoding: 'utf8', maxBuffer: 1 << 28 })
        .split('\0')
        .filter((f) => f && !synced.has(f) && !SKIPPED.some((prefix) => f.startsWith(prefix)))
    const history = readHistory()
    const counts = {}
    const left = []
    let changed = 0
    for (const file of tracked) {
        const path = join(root, file)
        let text
        try {
            text = readFileSync(path, 'utf8')
        } catch {
            continue
        }
        if (text.includes('\0')) {
            continue
        }
        const kind = {
            style: STYLE_FILE.test(file),
            prose: PROSE_FILE.test(file),
            test: TEST_FILE.test(file),
        }
        const out = text
            .split('\n')
            .map((line, i) => {
                // A test's strings are data, its links included, so only its comments take the
                // link rules; `rewriteBare` leaves its other lines' bare references as data
                const testData = kind.test && !kind.prose && !COMMENT.test(line)
                const fileLeft = []
                const result = rewriteBare(testData ? line : rewriteLinks(line, counts), history, fileLeft, kind)
                counts.bare = (counts.bare ?? 0) + result.rewritten
                left.push(...fileLeft.map((l) => ({ ...l, at: `${file}:${i + 1}` })))

                return result.line
            })
            .join('\n')
        if (out !== text) {
            changed++
            if (write) {
                writeFileSync(path, out)
            }
        }
    }
    console.log(`history: highest number ${history.max}, ${history.open.size} open issues`)
    console.log(`files scanned: ${tracked.length} (synced files skipped: ${synced.size})`)
    console.log(`files ${write ? 'changed' : 'to change'}: ${changed}`)
    console.log(`rewritten by class: ${JSON.stringify(counts)}`)
    const byWhy = {}
    for (const l of left) {
        byWhy[l.why] = (byWhy[l.why] ?? 0) + 1
    }
    console.log(`bare references left by reason: ${JSON.stringify(byWhy)}`)
    for (const l of left.filter((x) => ['line names another repository', 'CSS color', 'string data'].includes(x.why))) {
        console.log(`  left ${l.ref} at ${l.at} (${l.why})`)
    }
}

/**
 * Rewrite a repository's issue and pull-request bodies and comments.
 *
 * Each body is read again immediately before it is written, and the write is skipped
 * when that read no longer matches what was rewritten, so a concurrent edit is never lost.
 *
 * @param {string} repo  the repository, as owner/name
 * @param {boolean} apply  whether to write the edits
 */
function bodies(repo, apply) {
    const issues = pages(`repos/${repo}/issues?state=all`)
    const comments = pages(`repos/${repo}/issues/comments`)
    const reviewComments = pages(`repos/${repo}/pulls/comments`)
    console.log(`read: ${issues.length} issues and pull requests, ${comments.length} comments, ${reviewComments.length} review comments`)
    const targets = [
        ...issues.map((i) => ({ url: `repos/${repo}/issues/${i.number}`, body: i.body })),
        ...comments.map((c) => ({ url: `repos/${repo}/issues/comments/${c.id}`, body: c.body })),
        ...reviewComments.map((c) => ({ url: `repos/${repo}/pulls/comments/${c.id}`, body: c.body })),
    ]
    const counts = {}
    let edits = 0
    for (const target of targets) {
        if (!target.body) {
            continue
        }
        const out = rewriteLinks(target.body, counts)
        if (out === target.body) {
            continue
        }
        edits++
        console.log(`edit ${target.url}`)
        for (const [i, line] of target.body.split('\n').entries()) {
            const next = out.split('\n')[i]
            if (next !== line) {
                console.log(`  - ${line}\n  + ${next}`)
            }
        }
        if (!apply) {
            continue
        }
        const current = JSON.parse(gh(['api', target.url])).body
        if (current !== target.body) {
            console.log('  skipped: the body changed since it was read')
            continue
        }
        gh(['api', '-X', 'PATCH', target.url, '--input', '-'], JSON.stringify({ body: out }))
    }
    console.log(`${apply ? 'edited' : 'to edit'}: ${edits}; rewritten by class: ${JSON.stringify(counts)}`)
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const [mode, ...rest] = process.argv.slice(2)
    if (mode === 'files') {
        files(rest.includes('--write'))
    } else if (mode === 'bodies' && rest.includes('--repo')) {
        bodies(rest[rest.indexOf('--repo') + 1], rest.includes('--apply'))
    } else {
        console.error('usage: repoint-archive-references.mjs files [--write] | bodies --repo <owner/name> [--apply]')
        process.exit(2)
    }
}
