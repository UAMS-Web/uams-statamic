import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { cpSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { checkCopies } from '../.claude/skills/synced-files/scripts/check-workflow-copies.mjs'

// UAMS-Web/uams-statamic-archive#3107: the workflows the shared skills ship as templates are copied into
// .github/workflows/ once, through a reviewed pull request, and nothing keeps the
// copy and the template together afterwards. The synced-files skill's
// check-workflow-copies.mjs compares them; this runs it in the js job, so a
// template change the copies never received fails here rather than being found
// by hand.
//
// Every copy this repository carries is REQUIRED, so deleting one fails too: the
// check skips a missing copy by default, which would read as a pass.
//
// Only the checker's exit status is asserted. Its wording, and which part of a copy
// it compares (the header included or not), belong to the checker and its own tests
// in UAMS-Web/uams-claude-skills, and they change when the sync delivers a new
// checker (#544). Asserting them here broke the sync that delivered such a change.

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

    it('finds every carried copy matching its template', () => {
        expect(checkCopies({ root: repoRoot, required: CARRIED }).status).toBe(0)
    })

    it('fails a copy whose body differs from its template', () => {
        const root = scratchRoot('body')
        const copy = join(root, '.github', 'workflows', 'assign-author.yml')
        writeFileSync(copy, `${readFileSync(copy, 'utf8')}# appended\n`)

        expect(checkCopies({ root, required: CARRIED }).status).toBe(1)
    })

    it('fails when a carried copy is deleted', () => {
        const root = scratchRoot('missing')
        rmSync(join(root, '.github', 'workflows', 'check-closing-keyword-across-lines.yml'))

        expect(checkCopies({ root, required: CARRIED }).status).toBe(1)
    })
})
