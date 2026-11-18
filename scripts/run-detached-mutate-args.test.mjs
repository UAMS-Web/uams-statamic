// Coverage for the Pest argument list `run-detached-mutate.mjs` starts (#1033).
//
// The runner used to pass `--covered-only`, which mutates only the lines the tests
// already execute, so a class whose tests miss most of it could still report 100%.
// The first test is the one that matters: it fails if the flag comes back.

import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { mutatePestArgs } from './run-detached-mutate-args.mjs'

const base = {
    testFile: 'tests/Feature/Fieldtypes/HiddenFieldtypeTest.php',
    pathArg: 'app/Fieldtypes',
    className: 'App\\Fieldtypes\\Hidden',
}

describe('mutatePestArgs', () => {
    it('does not pass --covered-only, so every line of the class is mutated', () => {
        const args = mutatePestArgs(base)

        expect(args).not.toContain('--covered-only')
        expect(args.some((arg) => arg.includes('covered-only'))).toBe(false)
    })

    it('runs pest on the test file, scoped by --path and --class, uncached and compact', () => {
        expect(mutatePestArgs(base)).toEqual([
            path.join('vendor', 'bin', 'pest'),
            'tests/Feature/Fieldtypes/HiddenFieldtypeTest.php',
            '--mutate',
            '--path=app/Fieldtypes',
            '--class=App\\Fieldtypes\\Hidden',
            '--no-cache',
            '--compact',
        ])
    })

    it('adds --parallel alone for a bare --parallel flag', () => {
        expect(mutatePestArgs({ ...base, parallel: 'true' }).slice(-1)).toEqual(['--parallel'])
        expect(mutatePestArgs({ ...base, parallel: '' }).slice(-1)).toEqual(['--parallel'])
    })

    it('adds --parallel and --processes for --parallel=N', () => {
        expect(mutatePestArgs({ ...base, parallel: '6' }).slice(-2)).toEqual(['--parallel', '--processes=6'])
    })

    it('adds neither when --parallel is absent', () => {
        const args = mutatePestArgs(base)

        expect(args).not.toContain('--parallel')
        expect(args.some((arg) => arg.startsWith('--processes'))).toBe(false)
    })
})
