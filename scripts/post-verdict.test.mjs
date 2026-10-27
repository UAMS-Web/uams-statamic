// Coverage for the gate's verdict command (UAMS-Web/uams-statamic-archive#2845).
//
// The verdict comment and the `handed-back` label used to be two calls, and the label
// was the one missed. These pin that a hand-back applies the label in the same run as
// the comment, that a green verdict never touches the labels, and that the run fails
// unless the label read back agrees with what was asked. The CLI case spawns the real
// script with a stub `gh` on PATH, because the gate consumes its exit code.

import { describe, expect, it } from 'vitest'
import { chmodSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { delimiter, dirname, join, resolve } from 'node:path'
import { tmpdir } from 'node:os'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { DEFAULT_REPO, HANDED_BACK, parseArgs, postVerdict } from './post-verdict.mjs'

const SCRIPT = join(resolve(dirname(fileURLToPath(import.meta.url)), '..'), 'scripts', 'post-verdict.mjs')
const URL = 'https://github.com/UAMS-Web/uams-statamic/pull/42#issuecomment-1'

/**
 * A verdict body in a scratch directory, removed by the returned cleanup.
 *
 * @param {string} text The body.
 * @returns {{file: string, cleanup: () => void}}
 */
function bodyFile(text = '**Local CI: red.** `pest` failed.') {
    const dir = mkdtempSync(join(tmpdir(), 'post-verdict-'))
    const file = join(dir, 'verdict.md')
    writeFileSync(file, text)

    return { file, cleanup: () => rmSync(dir, { recursive: true, force: true }) }
}

/**
 * A fake `run` that records each call and answers like the GitHub REST API.
 *
 * @param {{labelsAfter?: Array<string>, labelFails?: boolean, readBackFails?: boolean}} options How the fake answers.
 */
function fakeGh({ labelsAfter = [], labelFails = false, readBackFails = false } = {}) {
    const calls = []
    const run = (command, args) => {
        calls.push([command, ...args])
        const path = args.find((arg) => arg.startsWith('repos/'))

        if (path.endsWith('/comments')) {
            return `${URL}\n`
        }
        if (path.endsWith('/labels')) {
            if (labelFails) {
                throw new Error('HTTP 403')
            }

            return '[]'
        }
        if (readBackFails) {
            throw new Error('HTTP 502')
        }

        return JSON.stringify(labelsAfter)
    }

    return { run, calls }
}

describe('parseArgs', () => {
    it('reads the pull request, the body file and the hand-back flag', () => {
        expect(parseArgs(['42', 'v.md', '--hand-back'])).toEqual({ pr: 42, bodyFile: 'v.md', handBack: true, repo: DEFAULT_REPO })
        expect(parseArgs(['42', 'v.md', '--repo', 'UAMS-Web/wordpress-importer']))
            .toEqual({ pr: 42, bodyFile: 'v.md', handBack: false, repo: 'UAMS-Web/wordpress-importer' })
    })

    it('accepts --repo=<owner/name>', () => {
        expect(parseArgs(['42', 'v.md', '--repo=UAMS-Web/wordpress-importer']).repo).toBe('UAMS-Web/wordpress-importer')
    })

    it.each([
        [['v.md'], 'Usage:'],
        [['0', 'v.md'], 'Not a pull request number'],
        [['#42', 'v.md'], 'Not a pull request number'],
        [['42', 'v.md', '--handed-back'], 'Unknown option: --handed-back'],
        [['42', 'v.md', '--repo', 'not a repo'], 'Not an owner/name repository'],
        [['42', 'v.md', '--repo'], 'Not an owner/name repository'],
        [['42', 'v.md', '--repo', '../..'], 'Not an owner/name repository'],
        [['42', '-'], 'not standard input'],
    ])('refuses %j', (argv, reason) => {
        // The reason is asserted, so a refusal for the wrong cause does not pass.
        expect(() => parseArgs(argv)).toThrow(reason)
    })
})

describe('postVerdict', () => {
    it('posts the comment and applies the label in the same run on a hand-back', () => {
        const { file, cleanup } = bodyFile()
        const gh = fakeGh({ labelsAfter: ['development', HANDED_BACK] })

        try {
            const result = postVerdict({ pr: 42, bodyFile: file, handBack: true, repo: DEFAULT_REPO }, gh.run)

            expect(result).toEqual({ comment: URL, handedBack: true, labels: ['development', HANDED_BACK], warning: null })
            expect(gh.calls).toEqual([
                ['gh', 'api', '-X', 'POST', 'repos/UAMS-Web/uams-statamic/issues/42/comments', '-F', `body=@${file}`, '--jq', '.html_url'],
                ['gh', 'api', '-X', 'POST', 'repos/UAMS-Web/uams-statamic/issues/42/labels', '-f', `labels[]=${HANDED_BACK}`],
                ['gh', 'api', 'repos/UAMS-Web/uams-statamic/issues/42', '--jq', '[.labels[].name]'],
            ])
        } finally {
            cleanup()
        }
    })

    it('sends every call to the repository it is given, not the default', () => {
        // Every other case uses DEFAULT_REPO, so a path built from the default
        // instead of the caller's repository would pass them all.
        const { file, cleanup } = bodyFile()
        const gh = fakeGh({ labelsAfter: [HANDED_BACK] })

        try {
            postVerdict({ pr: 7, bodyFile: file, handBack: true, repo: 'UAMS-Web/wordpress-importer' }, gh.run)

            expect(gh.calls.map((call) => call.find((arg) => arg.startsWith('repos/')))).toEqual([
                'repos/UAMS-Web/wordpress-importer/issues/7/comments',
                'repos/UAMS-Web/wordpress-importer/issues/7/labels',
                'repos/UAMS-Web/wordpress-importer/issues/7',
            ])
        } finally {
            cleanup()
        }
    })

    it('never touches the labels on a green verdict', () => {
        // The negative case the ticket asks for: a green is not a hand-back.
        const { file, cleanup } = bodyFile('**Local CI: green.**')
        const gh = fakeGh({ labelsAfter: ['development'] })

        try {
            const result = postVerdict({ pr: 42, bodyFile: file, handBack: false, repo: DEFAULT_REPO }, gh.run)

            expect(result.handedBack).toBe(false)
            expect(result.warning).toBeNull()
            expect(gh.calls.some((call) => call.some((arg) => arg.endsWith('/labels')))).toBe(false)
        } finally {
            cleanup()
        }
    })

    it('fails, naming the posted comment, when the label call fails', () => {
        const { file, cleanup } = bodyFile()

        try {
            expect(() => postVerdict({ pr: 42, bodyFile: file, handBack: true, repo: DEFAULT_REPO }, fakeGh({ labelFails: true }).run))
                .toThrow(`The verdict comment was posted (${URL}), but applying ${HANDED_BACK} failed`)
        } finally {
            cleanup()
        }
    })

    it('fails when the label is not there on read-back, even though the call succeeded', () => {
        const { file, cleanup } = bodyFile()

        try {
            expect(() => postVerdict({ pr: 42, bodyFile: file, handBack: true, repo: DEFAULT_REPO }, fakeGh({ labelsAfter: ['development'] }).run))
                .toThrow(`${HANDED_BACK} is not on #42 when read back`)
        } finally {
            cleanup()
        }
    })

    it('warns, without touching the labels, when a green finds handed-back still on', () => {
        // A push that clear-handed-back.yml did not run for leaves the label behind, and the
        // pull request then drops out of queue depth until someone notices.
        const { file, cleanup } = bodyFile('**Local CI: green.**')
        const gh = fakeGh({ labelsAfter: [HANDED_BACK] })

        try {
            const result = postVerdict({ pr: 42, bodyFile: file, handBack: false, repo: DEFAULT_REPO }, gh.run)

            expect(result.handedBack).toBe(true)
            expect(result.warning).toContain('still carries handed-back after a green verdict')
            expect(gh.calls.some((call) => call.some((arg) => arg.endsWith('/labels')))).toBe(false)
        } finally {
            cleanup()
        }
    })

    it('names the posted comment when the read-back fails, so the command is not re-run', () => {
        const { file, cleanup } = bodyFile()

        try {
            expect(() => postVerdict({ pr: 42, bodyFile: file, handBack: true, repo: DEFAULT_REPO }, fakeGh({ readBackFails: true }).run))
                .toThrow(`The verdict comment was posted (${URL}) and ${HANDED_BACK} was requested, but reading #42's labels back failed`)
            expect(() => postVerdict({ pr: 42, bodyFile: file, handBack: false, repo: DEFAULT_REPO }, fakeGh({ readBackFails: true }).run))
                .toThrow(`The verdict comment was posted (${URL}), but reading #42's labels back failed`)
        } finally {
            cleanup()
        }
    })

    it('refuses a missing body file before posting anything', () => {
        const gh = fakeGh()

        expect(() => postVerdict({ pr: 42, bodyFile: join(tmpdir(), 'no-such-verdict.md'), handBack: true, repo: DEFAULT_REPO }, gh.run))
            .toThrow('missing or empty')
        expect(gh.calls).toEqual([])
    })

    it('refuses an empty body before posting anything', () => {
        const { file, cleanup } = bodyFile('   \n')
        const gh = fakeGh()

        try {
            expect(() => postVerdict({ pr: 42, bodyFile: file, handBack: true, repo: DEFAULT_REPO }, gh.run)).toThrow('missing or empty')
            expect(gh.calls).toEqual([])
        } finally {
            cleanup()
        }
    })
})

describe('the script', () => {
    /**
     * Run the real script with a stub `gh` on PATH that logs its arguments and answers by path.
     *
     * @param {Array<string>} args The script's arguments.
     * @param {string} labels The JSON label list the read-back returns.
     * @returns {{result: object, log: string}}
     */
    function runScript(args, labels) {
        const bin = mkdtempSync(join(tmpdir(), 'post-verdict-bin-'))
        const log = join(bin, 'calls.log')

        try {
            writeFileSync(join(bin, 'gh'), [
                '#!/bin/sh',
                `echo "$*" >> '${log}'`,
                'case "$*" in',
                `  *"/comments"*) echo '${URL}' ;;`,
                "  *\"/labels\"*) echo '[]' ;;",
                `  *) echo '${labels}' ;;`,
                'esac',
            ].join('\n') + '\n')
            chmodSync(join(bin, 'gh'), 0o755)
            writeFileSync(log, '')

            const result = spawnSync(process.execPath, [SCRIPT, ...args], {
                encoding: 'utf8',
                env: { ...process.env, PATH: `${bin}${delimiter}${process.env.PATH}` },
            })

            return { result, log: readFileSync(log, 'utf8') }
        } finally {
            rmSync(bin, { recursive: true, force: true })
        }
    }

    it.skipIf(process.platform === 'win32')('posts and labels a hand-back, exiting 0 with the read-back', () => {
        const { file, cleanup } = bodyFile()

        try {
            const { result, log } = runScript(['42', file, '--hand-back'], `["${HANDED_BACK}"]`)

            expect(result.status, result.stderr).toBe(0)
            expect(JSON.parse(result.stdout)).toEqual({ comment: URL, handedBack: true, labels: [HANDED_BACK], warning: null })
            expect(log).toContain(`issues/42/labels -f labels[]=${HANDED_BACK}`)
        } finally {
            cleanup()
        }
    })

    it.skipIf(process.platform === 'win32')('passes --repo through to every call', () => {
        const { file, cleanup } = bodyFile()

        try {
            const { result, log } = runScript(['7', file, '--hand-back', '--repo', 'UAMS-Web/wordpress-importer'], `["${HANDED_BACK}"]`)

            expect(result.status, result.stderr).toBe(0)

            const paths = log.trim().split('\n').map((line) => line.split(' ').find((arg) => arg.startsWith('repos/')))

            expect(paths).toEqual([
                'repos/UAMS-Web/wordpress-importer/issues/7/comments',
                'repos/UAMS-Web/wordpress-importer/issues/7/labels',
                'repos/UAMS-Web/wordpress-importer/issues/7',
            ])
        } finally {
            cleanup()
        }
    })

    it.skipIf(process.platform === 'win32')('exits 1 when the read-back lacks the label', () => {
        const { file, cleanup } = bodyFile()

        try {
            const { result } = runScript(['42', file, '--hand-back'], '["development"]')

            expect(result.status).toBe(1)
            expect(result.stdout).toBe('')
            expect(result.stderr).toContain('is not on #42 when read back')
        } finally {
            cleanup()
        }
    })

    it('refuses malformed arguments', () => {
        const result = spawnSync(process.execPath, [SCRIPT, 'not-a-number', 'x.md'], { encoding: 'utf8' })

        expect(result.status).toBe(1)
        expect(result.stderr).toContain('Not a pull request number')
    })
})
