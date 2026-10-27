// Coverage for the lone-CR guard (UAMS-Web/uams-statamic-archive#2495).
//
// The guard rejects a raw 0x0D that is not part of a CRLF pair. It went into the
// `encoding` job of both CI definitions, which chain-gates eight downstream jobs,
// so a guard that silently stopped firing would take the whole class with it.
//
// These spawn the real script rather than importing its helpers, because what the
// gate consumes is the EXIT CODE and nothing else.
//
// THE NEGATIVE CASES ARE THE POINT, and there are two distinct ones. A guard that
// fired on CRLF would red every file a Windows editor ever saved — `eol=lf` owns
// that class and this must not — and a guard that fired on binary would red 35
// tracked images and fonts. Both are asserted, because either would make the check
// something people route around rather than fix.
//
// Fixtures written as explicit byte arrays, never as string literals: a literal
// carrying a raw CR is the exact defect under test, and it would be rewritten by
// the first tool that read this file in a newline-translating mode.

import { describe, expect, it } from 'vitest'
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs'
import { join, dirname, resolve } from 'node:path'
import { tmpdir } from 'node:os'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

const CHECKER = resolve(dirname(fileURLToPath(import.meta.url)), 'check-lone-cr.mjs')

const CR = 0x0d
const LF = 0x0a

/**
 * Run the guard against one fixture file, named explicitly so the scan does not
 * walk the repository.
 */
function check(bytes) {
    const scratch = mkdtempSync(join(tmpdir(), 'lone-cr-'))
    const file = join(scratch, 'fixture.txt')

    try {
        writeFileSync(file, Buffer.from(bytes))

        const result = spawnSync(process.execPath, [CHECKER, file], { encoding: 'utf8' })

        return { status: result.status, output: `${result.stdout}${result.stderr}` }
    } finally {
        rmSync(scratch, { recursive: true, force: true })
    }
}

/** ASCII bytes for a string, so no fixture depends on a literal's byte content. */
function ascii(text) {
    return [...text].map((character) => character.charCodeAt(0))
}

describe('check-lone-cr', () => {
    it('fails on a lone carriage return and names the line and column', () => {
        // The shape of the real instance: a raw CR inside a single-quoted PHP literal,
        // which is how it survived review — invisible, and inert to every other guard.
        const { status, output } = check([
            ...ascii("<?php"), LF,
            ...ascii("$x = str_replace('"), CR, ...ascii("', '', $y);"), LF,
        ])

        expect(status).toBe(1)
        expect(output).toContain('line 2')
        expect(output).toContain('Found 1 lone carriage return')
    })

    it('passes on the same content with the byte written as an escape', () => {
        // The remedy the guard names, asserted so the message it prints is actionable
        // rather than merely correct.
        const { status } = check([
            ...ascii("<?php"), LF,
            ...ascii("$x = str_replace(" + String.fromCharCode(34) + String.fromCharCode(92) + "r" + String.fromCharCode(34) + ", '', $y);"), LF,
        ])

        expect(status).toBe(0)
    })

    it('does NOT fire on CRLF, which `eol=lf` owns', () => {
        // Conflating the two would red every file saved by a Windows editor, which is
        // how a guard stops being fixed and starts being bypassed.
        const { status, output } = check([...ascii('first'), CR, LF, ...ascii('second'), CR, LF])

        expect(status).toBe(0)
        expect(output).toContain('no lone carriage return')
    })

    it('does NOT fire on a binary file that happens to contain a lone CR', () => {
        // Git calls a file binary when a NUL appears in the first 8000 bytes, and this
        // applies that rule rather than inventing a second one. Measured over the tree
        // when the guard was written: 36 files carried a lone CR and 35 were binary.
        const { status, output } = check([0x89, 0x50, 0x00, 0x4e, CR, 0x47, 0x00])

        expect(status).toBe(0)
        expect(output).toContain('skipped 1 binary')
    })

    it('reports a scan that could not run as a failure rather than a pass', () => {
        // An empty target set produces no findings, which is byte-identical to a clean
        // tree. That is the exact failure this guard exists to close, so it must not be
        // reachable through the guard itself.
        const scratch = mkdtempSync(join(tmpdir(), 'lone-cr-empty-'))

        try {
            const result = spawnSync(process.execPath, [CHECKER], { cwd: scratch, encoding: 'utf8' })

            expect(result.status).toBe(2)
        } finally {
            rmSync(scratch, { recursive: true, force: true })
        }
    })
})
