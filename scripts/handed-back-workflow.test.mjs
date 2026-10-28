import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { cpSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { checkWorkflow, TEMPLATE_PATH, WORKFLOW_PATH } from '../.claude/skills/handed-back-label/scripts/check-handed-back-workflow.mjs'

// #297: the handed-back-label skill ships its own drift check, which compares the
// whole file, header comments included. workflow-copies.test.mjs compares only what
// follows the header (UAMS-Web/uams-statamic-archive#3107), so a copy whose comments
// drifted from the template passed there. Running the skill's check here puts it
// in the js job, which both scripts/local-ci.mjs and ci.yml run.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

describe('the handed-back workflow matches its template byte for byte (#297)', () => {
    let scratch

    beforeAll(() => {
        scratch = mkdtempSync(join(tmpdir(), 'handed-back-workflow-'))
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

    it('reads the template from the skill this repository carries', () => {
        expect(readFileSync(TEMPLATE_PATH, 'utf8')).toContain('handed-back')
    })

    it('finds this repository copy identical to the template', () => {
        const { status, lines } = checkWorkflow({ root: repoRoot })

        expect(lines).toEqual([`${WORKFLOW_PATH} matches the template.`])
        expect(status).toBe(0)
    })

    it('fails a copy whose header comment alone differs', () => {
        const root = scratchRoot('comment')
        const copy = join(root, WORKFLOW_PATH)
        writeFileSync(copy, `# a note only this repository carries\n${readFileSync(copy, 'utf8')}`)

        const { status, lines } = checkWorkflow({ root })

        expect(status).toBe(1)
        expect(lines[0]).toContain('differs from the template')
        expect(lines[0]).toContain('first at line 1.')
    })

    it('fails a copy whose job settings differ', () => {
        const root = scratchRoot('timeout')
        const copy = join(root, WORKFLOW_PATH)
        const text = readFileSync(copy, 'utf8')

        expect(text).toContain('timeout-minutes: 2')
        writeFileSync(copy, text.replace('timeout-minutes: 2', 'timeout-minutes: 3'))

        expect(checkWorkflow({ root }).status).toBe(1)
    })

    it('names the missing actor condition on a copy that runs on edited without it', () => {
        const root = scratchRoot('actor')
        const copy = join(root, WORKFLOW_PATH)
        const text = readFileSync(copy, 'utf8')
        const stripped = text.replace(/^.*github\.event\.sender\.type.*$/gm, '')

        expect(stripped).not.toBe(text)
        writeFileSync(copy, stripped)

        const { status, lines } = checkWorkflow({ root })

        expect(status).toBe(1)
        expect(lines.some((line) => line.startsWith('Unsafe:') && line.includes('github.event.sender.type'))).toBe(true)
    })

    it('fails when the copy is missing', () => {
        const root = scratchRoot('missing')
        rmSync(join(root, WORKFLOW_PATH))

        const { status, lines } = checkWorkflow({ root })

        expect(status).toBe(1)
        expect(lines[0]).toContain('is missing.')
    })
})
