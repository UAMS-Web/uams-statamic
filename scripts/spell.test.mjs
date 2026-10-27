// Coverage for the spell-run coverage guard (UAMS-Web/uams-statamic-archive#2296).
//
// `cspell` runs with `--no-must-find-files`, so a glob that stops matching makes
// the job report success over an empty set. `spell` is fourth in a twelve-job
// chain with eight behind it, so that failure is silent rather than loud.
//
// The refusal arms cannot be reached by driving the real binary: the defect has
// never fired here (85 local-CI logs, zero zero-file runs, lowest 2,585). So the
// decision is tested directly and the spawn is left to the end-to-end run.

import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { coverageRefusal, filesChecked } from './spell.mjs'

const HERE = dirname(fileURLToPath(import.meta.url))

describe('filesChecked', () => {
    it('reads the count out of a real cspell summary', () => {
        expect(filesChecked('CSpell: Files checked: 2631, Issues found: 0 in 0 files.')).toBe(2631)
        expect(filesChecked('CSpell: Files checked: 0, Issues found: 0 in 0 files.')).toBe(0)
    })

    it('returns null when no summary line is present', () => {
        // Distinguished from zero on purpose. A missing line means cspell changed
        // its output or never ran; zero means it ran and matched nothing. Both are
        // refusals below, and conflating them would hide which one happened.
        expect(filesChecked('')).toBeNull()
        expect(filesChecked('some unrelated output')).toBeNull()
    })
})

describe('coverageRefusal', () => {
    it('passes a run that checked a plausible number of files', () => {
        expect(coverageRefusal(2631)).toBeNull()
        expect(coverageRefusal(2000)).toBeNull()
    })

    it('refuses a zero-file run, which is the defect', () => {
        const refusal = coverageRefusal(0)

        expect(refusal).toContain('checked 0 files')
        expect(refusal).toContain('--no-must-find-files')
    })

    it('refuses an implausibly low count, not only exactly zero', () => {
        // `Files checked: 3` is the same defect wearing a less obvious number, and
        // a zero-only guard sails past it.
        expect(coverageRefusal(3)).toContain('checked 3 files')
        expect(coverageRefusal(1999)).toContain('below the floor')
    })

    it('refuses an unparseable result rather than treating it as a pass', () => {
        // cspell changing its summary format would otherwise read as success.
        expect(coverageRefusal(null)).toContain('could not find')
    })

    it('names the floor it applied, so a red says what to compare against', () => {
        expect(coverageRefusal(10, 500)).toContain('floor of 500')
    })
})

describe('the wiring', () => {
    it('is what `npm run spell` invokes, so both gates go through the guard', () => {
        // `ci.yml` and `local-ci.mjs` both call `npm run spell` rather than cspell
        // directly, so this one line is the seam. If it is ever pointed back at
        // cspell, the guard is bypassed everywhere at once with nothing to notice.
        const pkg = JSON.parse(readFileSync(resolve(HERE, '..', 'package.json'), 'utf8'))

        expect(pkg.scripts.spell).toBe('node scripts/spell.mjs')
    })

    it('keeps `--no-must-find-files`, which the .githooks glob needs', () => {
        // The flag is not the defect and removing it is not the fix: `.githooks/*`
        // is legitimately allowed to match nothing, and without the flag cspell
        // would error on it. The guard replaces what the flag suppresses rather
        // than removing the suppression.
        const source = readFileSync(join(HERE, 'spell.mjs'), 'utf8')

        expect(source).toContain('--no-must-find-files')
        expect(source).toContain('.githooks/*')
    })
})
