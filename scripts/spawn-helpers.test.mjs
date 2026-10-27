import { describe, it, expect, afterEach } from 'vitest'
import { chmodSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { basename, delimiter, dirname, join } from 'node:path'
import { isExecutable, needsShell, which, winQuote } from './spawn-helpers.mjs'

// These three helpers exist because Node refuses to spawn .cmd/.bat directly (CVE-2024-27980), and
// on Windows this repo's toolchain straddles that line — composer.bat, php.bat and npm.cmd are
// shims, git.exe is not. Getting it wrong produces a bare `spawnSync composer ENOENT` that reads as
// a missing install rather than a spawn-mode bug, which is how it first cost time here.
//
// So what is asserted is the CHAIN, not three isolated functions: resolving the absolute path is
// the only thing that makes the extension visible, the extension is the only signal needsShell has,
// and the quoting repairs what shell:true costs. Any link asserted alone would still pass with the
// bug present.

const onWindows = process.platform === 'win32'

const temporaryDirectories = []
const originalPath = process.env.PATH
const originalPathExt = process.env.PATHEXT

/** A throwaway PATH entry holding one file, cleaned up after the test. */
function directoryHolding(filename) {
    const dir = mkdtempSync(join(tmpdir(), 'spawn-helpers-'))

    temporaryDirectories.push(dir)
    writeFileSync(join(dir, filename), '')

    return dir
}

/** The name a command takes on disk here — bare off Windows, and a shim on it. */
const shimName = onWindows ? 'sample.cmd' : 'sample'

afterEach(() => {
    process.env.PATH = originalPath

    if (originalPathExt === undefined) {
        delete process.env.PATHEXT
    } else {
        process.env.PATHEXT = originalPathExt
    }

    while (temporaryDirectories.length > 0) {
        rmSync(temporaryDirectories.pop(), { recursive: true, force: true })
    }
})

describe('needsShell', () => {
    it.runIf(onWindows)('routes Windows shims through a shell', () => {
        expect(needsShell('C:\\Users\\dev\\.config\\herd\\bin\\composer.bat')).toBe(true)
        expect(needsShell('C:\\Program Files\\nodejs\\npm.cmd')).toBe(true)
        expect(needsShell('C:\\Users\\dev\\.config\\herd\\bin\\PHP.BAT')).toBe(true)
    })

    it.runIf(onWindows)('spawns real executables directly', () => {
        expect(needsShell('C:\\Program Files\\Git\\cmd\\git.exe')).toBe(false)
        expect(needsShell('C:\\Program Files\\nodejs\\node.exe')).toBe(false)
    })

    // No shim concept off Windows, so nothing should ever take the shell path there.
    it.runIf(!onWindows)('never uses a shell off Windows', () => {
        expect(needsShell('/usr/local/bin/composer')).toBe(false)
        expect(needsShell('/usr/bin/git')).toBe(false)
    })
})

describe('which', () => {
    it('answers null rather than a bare name when nothing on PATH matches', () => {
        process.env.PATH = directoryHolding(shimName)

        expect(which('no-such-command')).toBe(null)
    })

    // Asserted on the directory rather than the whole path: on Windows the resolved extension
    // carries PATHEXT's case (`.CMD`), not the filesystem's, so pinning the string would pin an
    // incidental. Which directory won is the property.
    it('searches PATH in order, so the entry that would win in a shell wins here', () => {
        const first = directoryHolding(shimName)
        const second = directoryHolding(shimName)

        process.env.PATH = [first, second].join(delimiter)

        expect(dirname(which('sample'))).toBe(first)
    })

    it.runIf(!onWindows)('resolves the exact file name off Windows', () => {
        const dir = directoryHolding('sample')

        process.env.PATH = dir

        expect(which('sample')).toBe(join(dir, 'sample'))
    })

    // The link that carries the whole thing: a bare command name has no extension, so needsShell
    // reads it as a real executable and the shim is spawned directly. Resolution is what closes
    // that gap, which is why these two assertions belong in one test rather than apart.
    it.runIf(onWindows)('resolves through PATHEXT, which is what makes a shim visible at all', () => {
        const dir = directoryHolding('sample.cmd')

        process.env.PATH = dir
        process.env.PATHEXT = '.COM;.EXE;.BAT;.CMD'

        expect(dirname(which('sample'))).toBe(dir)
        expect(basename(which('sample'))).toMatch(/^sample\.cmd$/i)
        expect(needsShell('sample')).toBe(false)
        expect(needsShell(which('sample'))).toBe(true)
    })
})

describe('winQuote', () => {
    it('leaves an argument with nothing to escape untouched', () => {
        expect(winQuote('--no-interaction')).toBe('--no-interaction')
        expect(winQuote('C:\\Users\\dev\\bin\\git.exe')).toBe('C:\\Users\\dev\\bin\\git.exe')
    })

    it('quotes an argument containing whitespace', () => {
        expect(winQuote('C:\\Program Files\\nodejs\\npm.cmd')).toBe('"C:\\Program Files\\nodejs\\npm.cmd"')
    })

    it('escapes an embedded quote', () => {
        expect(winQuote('say "hi"')).toBe('"say \\"hi\\""')
    })

    // A trailing backslash would otherwise escape the closing quote and swallow it, running the
    // rest of the command line into this argument.
    it('doubles the backslashes that sit against the closing quote', () => {
        expect(winQuote('C:\\Program Files\\node\\')).toBe('"C:\\Program Files\\node\\\\"')
    })

    // The empty argument is the one with no character to notice. Unquoted it disappears from the
    // command line entirely, shifting every argument after it by one position. This branch was
    // missing from the worktree-slots copy until the two were merged here — exactly the silent
    // drift between duplicates that motivated the extraction.
    it('emits an explicit empty pair rather than nothing', () => {
        expect(winQuote('')).toBe('""')
    })
})

describe('isExecutable', () => {
    const isWindows = process.platform === 'win32'
    const made = []

    afterEach(() => {
        while (made.length) {
            rmSync(made.pop(), { recursive: true, force: true })
        }
    })

    function write(nameWithExt, mode) {
        const dir = mkdtempSync(join(tmpdir(), 'is-exec-'))
        made.push(dir)
        const file = join(dir, nameWithExt)
        writeFileSync(file, '#!/usr/bin/env node')
        if (mode !== undefined && ! isWindows) {
            chmodSync(file, mode)
        }

        return file
    }

    it('refuses a path that does not exist', () => {
        expect(isExecutable(join(tmpdir(), 'is-exec-absent', 'nope.exe'))).toBe(false)
    })

    it('refuses a directory', () => {
        const dir = mkdtempSync(join(tmpdir(), 'is-exec-dir-'))
        made.push(dir)
        expect(isExecutable(dir)).toBe(false)
    })

    // THE NEGATIVE CONTROL for UAMS-Web/uams-statamic-archive#2335. Before the guard, `resolveTool` accepted any override that
    // merely EXISTED, so this file was returned and failed later inside `spawnSync` — as `EACCES`
    // on POSIX, `EFTYPE` for a `.phar` on Windows. Both point at the tool rather than at the
    // variable that chose it. If this assertion ever passes `true`, the guard is gone.
    it('refuses a file this OS cannot run', () => {
        // POSIX: real bytes, execute bit cleared. Windows: an extension absent from PATHEXT.
        const file = isWindows ? write('tool.phar') : write('tool', 0o644)

        expect(isExecutable(file)).toBe(false)
    })

    // The other side of the same coin, and the reason the test is not "does it end in .phar":
    // a suffix check would refuse this on POSIX, where it runs.
    it('accepts a file this OS can run', () => {
        const file = isWindows ? write('tool.cmd') : write('tool.phar', 0o755)

        expect(isExecutable(file)).toBe(true)
    })
})
