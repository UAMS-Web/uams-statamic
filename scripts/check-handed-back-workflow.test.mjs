// Coverage for the handed-back workflow drift check as the gate runs it (#297).
//
// The check is synced from the handed-back-label skill, and the encoding job of both
// CI definitions consumes its exit code and nothing else, so these spawn the real
// script. The wiring cases fail if either definition stops running it, because a
// drift check nothing runs reports nothing.

import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import { spawnSync } from 'node:child_process'
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const CHECK = '.claude/skills/handed-back-label/scripts/check-handed-back-workflow.mjs'
const TEMPLATE = join(repoRoot, '.claude/skills/handed-back-label/templates/clear-handed-back.yml')
const COPY = '.github/workflows/clear-handed-back.yml'

describe('the handed-back workflow drift check (#297)', () => {
    let scratch

    beforeAll(() => {
        scratch = mkdtempSync(join(tmpdir(), 'handed-back-drift-'))
    })

    afterAll(() => {
        rmSync(scratch, { recursive: true, force: true })
    })

    /** A scratch root holding a byte-identical copy of the template. */
    const rootWithCopy = (name) => {
        const root = join(scratch, name)
        mkdirSync(join(root, '.github', 'workflows'), { recursive: true })
        copyFileSync(TEMPLATE, join(root, COPY))

        return root
    }

    /** Run the real script against a root. */
    const check = (root) => spawnSync(process.execPath, [join(repoRoot, CHECK), '--root', root], { encoding: 'utf8' })

    it('passes on this repository, whose copy matches the template', () => {
        expect(check(repoRoot).status).toBe(0)
    })

    it('passes on a byte-identical copy in a scratch root', () => {
        expect(check(rootWithCopy('identical')).status).toBe(0)
    })

    it('fails on a copy that differs from the template', () => {
        const root = rootWithCopy('differs')
        const copy = join(root, COPY)
        const source = readFileSync(copy, 'utf8')
        const changed = source.replace('timeout-minutes: 2', 'timeout-minutes: 3')

        expect(changed).not.toBe(source)
        writeFileSync(copy, changed)

        const result = check(root)

        expect(result.status).toBe(1)
        expect(result.stderr).toContain('differs from the template')
    })

    it('fails when the copy is missing', () => {
        const root = rootWithCopy('missing')
        rmSync(join(root, COPY))

        const result = check(root)

        expect(result.status).toBe(1)
        expect(result.stderr).toContain('is missing')
    })

    it.each([
        ['.github/workflows/ci.yml'],
        ['scripts/local-ci.mjs'],
    ])('is run by the encoding job in %s', (definition) => {
        expect(readFileSync(join(repoRoot, definition), 'utf8')).toContain(CHECK)
    })
})
