import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { cpSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { checkCopies } from '../.claude/skills/synced-files/scripts/check-workflow-copies.mjs'

// UAMS-Web/uams-statamic-archive#3107: the workflows the shared skills ship as templates are copied into
// .github/workflows/ once, through a reviewed pull request, and nothing keeps the
// copy and the template together afterwards. The synced-files skill's
// check-workflow-copies.mjs compares them outside the header; this runs it in the
// js job, so a template change the copies never received fails here rather than
// being found by hand.
//
// Every copy this repository carries is REQUIRED, so deleting one fails too: the
// check skips a missing copy by default, which would read as a pass.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

const CARRIED = [
    'assign-author.yml',
    'check-attribution-footer.yml',
    'check-closing-keyword-across-lines.yml',
    'clear-handed-back.yml',
]

describe('workflow copies match their shared templates (#3107)', () => {
    let scratch

    beforeAll(() => {
        scratch = mkdtempSync(join(tmpdir(), 'workflow-copies-'))
    })

    afterAll(() => {
        rmSync(scratch, { recursive: true, force: true })
    })

    /** A scratch root holding a copy of this repository's .github/workflows/. */
    const scratchRoot = (name) => {
        const root = join(scratch, name)
        cpSync(join(repoRoot, '.github', 'workflows'), join(root, '.github', 'workflows'), { recursive: true })
        return root
    }

    it('finds every carried copy matching its template outside the header', () => {
        const { status, lines } = checkCopies({ root: repoRoot, required: CARRIED })

        expect(lines).toHaveLength(CARRIED.length)
        expect(lines.every((line) => line.endsWith('matches the template outside its header.'))).toBe(true)
        expect(status).toBe(0)
    })

    it('fails a copy whose body differs from its template', () => {
        const root = scratchRoot('body')
        const copy = join(root, '.github', 'workflows', 'assign-author.yml')
        writeFileSync(copy, `${readFileSync(copy, 'utf8')}# appended\n`)

        const { status, lines } = checkCopies({ root, required: CARRIED })

        expect(status).toBe(1)
        expect(lines.find((line) => line.includes('assign-author.yml'))).toContain('differs from the template')
    })

    it('accepts a copy whose header alone differs', () => {
        const root = scratchRoot('header')
        const copy = join(root, '.github', 'workflows', 'check-attribution-footer.yml')
        const text = readFileSync(copy, 'utf8')
        const onIndex = text.indexOf('\non:')

        expect(onIndex).toBeGreaterThan(0)
        writeFileSync(copy, `${text.slice(0, onIndex)}\n# a note only this repository carries\n${text.slice(onIndex)}`)

        expect(checkCopies({ root, required: CARRIED }).status).toBe(0)
    })

    it('fails when a carried copy is deleted', () => {
        const root = scratchRoot('missing')
        rmSync(join(root, '.github', 'workflows', 'check-closing-keyword-across-lines.yml'))

        expect(checkCopies({ root, required: CARRIED }).status).toBe(1)
    })
})
