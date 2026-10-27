// Coverage for handing off when only the runner's helper modules differ (UAMS-Web/uams-statamic-archive#2881).
//
// The UAMS-Web/uams-statamic-archive#1882 handoff compared `scripts/local-ci.mjs` alone against the branch's copy, but
// the runner imports `scripts/repo-root.mjs` and `scripts/spawn-helpers.mjs`, and since
// UAMS-Web/uams-statamic-archive#1950 and UAMS-Web/uams-statamic-archive#1953 tool resolution and primary-checkout detection live there. A branch
// changing only a helper was run with the helpers this process loaded at start.
import { describe, expect, it } from 'vitest'
import { appendFileSync, copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { tmpdir } from 'node:os'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { EXECUTING_HELPER_SOURCES, RUNNER_HELPER_MODULES, runnerHandoff } from './local-ci.mjs'

const scriptsDir = dirname(fileURLToPath(import.meta.url))

/**
 * The local modules a source loads statically: `import ... from './x'`, the bare
 * `import './x'`, and the re-exports `export ... from './x'` and `export * from './x'`.
 * Each starts a line, as every static import and re-export in `scripts/` does.
 *
 * @param {string} source  Module source.
 * @returns {string[]} The `./x.mjs` specifiers it loads, as `x.mjs`.
 */
function localImportsIn(source) {
    return [...source.matchAll(/^(?:import|export)\s(?:[^'"]*?\sfrom\s*)?['"]\.\/([^'"]+)['"]/gm)].map((match) => match[1])
}

/**
 * The local modules a file loads statically, by name relative to `scripts/`.
 *
 * @param {string} name  A file in `scripts/`.
 * @returns {string[]} The `./x.mjs` specifiers it loads, as `x.mjs`.
 */
function localImports(name) {
    return localImportsIn(readFileSync(join(scriptsDir, name), 'utf8'))
}

/**
 * Every local module the runner reaches through its imports, directly or not.
 *
 * @returns {string[]} The names, sorted.
 */
function runnerImportClosure() {
    const seen = new Set()
    const queue = localImports('local-ci.mjs')

    while (queue.length > 0) {
        const name = queue.shift()

        if (!seen.has(name)) {
            seen.add(name)
            queue.push(...localImports(name))
        }
    }

    return [...seen].sort()
}

/**
 * Copy the runner and its helpers into a scratch `scripts/` folder.
 *
 * @param {string} root  The scratch root.
 * @returns {string} The scratch `scripts/` folder.
 */
function copyRunner(root) {
    const dir = join(root, 'scripts')
    mkdirSync(dir, { recursive: true })

    for (const name of ['local-ci.mjs', ...RUNNER_HELPER_MODULES]) {
        copyFileSync(join(scriptsDir, name), join(dir, name))
    }

    return dir
}

describe('the helper module list', () => {
    it('names every local module the runner imports, directly or through a helper', () => {
        // The guard against a new helper reopening UAMS-Web/uams-statamic-archive#2881: a helper missing from the list
        // would be loaded from this checkout whatever the branch carries.
        expect([...RUNNER_HELPER_MODULES].sort()).toEqual(runnerImportClosure())
    })

    it('sees a transitive import, so the guard above can fail', () => {
        // repo-root.mjs imports spawn-helpers.mjs; a scan reading only local-ci.mjs's own
        // imports would still pass here, so the closure is checked to reach through it.
        expect(localImports('repo-root.mjs')).toContain('spawn-helpers.mjs')
        expect(localImports('local-ci.mjs').length).toBeGreaterThan(0)
    })

    it('sees every static form a new helper could arrive through', () => {
        // A form the scan cannot see is a helper the guard above cannot catch: a barrel
        // re-export or a side-effect import loads a module as surely as a named import.
        const source = [
            "import { a } from './named.mjs'",
            "import b from './default.mjs'",
            "import * as c from './namespace.mjs'",
            'import {',
            '    d,',
            "} from './multiline.mjs'",
            "import './side-effect.mjs'",
            "export { e } from './re-export.mjs'",
            "export * from './star.mjs'",
            "export * as f from './star-as.mjs'",
        ].join('\n')

        expect(localImportsIn(source)).toEqual([
            'named.mjs', 'default.mjs', 'namespace.mjs', 'multiline.mjs', 'side-effect.mjs',
            're-export.mjs', 'star.mjs', 'star-as.mjs',
        ])
    })

    it('does not count a package import, a string, or an export that loads nothing', () => {
        const source = [
            "import { join } from 'node:path'",
            "export const name = './not-a-module.mjs'",
            "export function from() { return './nor-this.mjs' }",
            "const text = \"import x from './quoted.mjs'\"",
        ].join('\n')

        expect(localImportsIn(source)).toEqual([])
    })

    it('captures each helper\'s source at load', () => {
        for (const name of RUNNER_HELPER_MODULES) {
            expect(EXECUTING_HELPER_SOURCES[name]).toBe(readFileSync(join(scriptsDir, name), 'utf8'))
        }
    })
})

describe('runnerHandoff with helper modules', () => {
    const helpers = { 'repo-root.mjs': 'root', 'spawn-helpers.mjs': 'spawn' }
    const decide = (branchHelpers) => runnerHandoff({
        pinned: false,
        executingSource: 'runner',
        branchSource: 'runner',
        executingHelpers: helpers,
        branchHelpers,
    })

    it('runs in place when the runner and every helper match', () => {
        const decision = decide({ ...helpers })

        expect(decision.action).toBe('run')
        expect(decision.reason).toBe('the branch\'s runner and its helper modules are identical to this one\'s')
    })

    it('hands off when only a helper differs, and names it', () => {
        const decision = decide({ ...helpers, 'spawn-helpers.mjs': 'changed' })

        expect(decision.action).toBe('handoff')
        expect(decision.reason).toBe('the branch\'s runner is identical to this one, but its scripts/spawn-helpers.mjs differs')
    })

    it('names both when both helpers differ', () => {
        expect(decide({ 'repo-root.mjs': 'x', 'spawn-helpers.mjs': 'y' }).reason)
            .toBe('the branch\'s runner is identical to this one, but its scripts/repo-root.mjs and scripts/spawn-helpers.mjs differ')
    })

    it('hands off when the branch lacks a helper', () => {
        expect(decide({ 'repo-root.mjs': 'root', 'spawn-helpers.mjs': null }).action).toBe('handoff')
    })

    it('ignores a line-ending difference, as it does for the runner', () => {
        const crlf = { 'repo-root.mjs': 'a\nb', 'spawn-helpers.mjs': 'spawn' }

        expect(runnerHandoff({
            pinned: false, executingSource: 'runner', branchSource: 'runner',
            executingHelpers: crlf, branchHelpers: { ...crlf, 'repo-root.mjs': 'a\r\nb' },
        }).action).toBe('run')
    })
})

describe('a branch that changes only a helper', () => {
    it('hands off, and the handed-off runner loads the branch\'s helper', () => {
        // Two copies of scripts/: this checkout's and a branch whose spawn-helpers.mjs alone
        // differs, printing a marker when it is imported.
        const root = mkdtempSync(join(tmpdir(), 'ci-helpers-'))

        try {
            const base = copyRunner(join(root, 'base'))
            const branch = copyRunner(join(root, 'branch'))
            appendFileSync(join(branch, 'spawn-helpers.mjs'), "\nconsole.log('BRANCH-HELPER-LOADED')\n")

            // The decision, made by the base runner against the branch's files, the way
            // main() makes it after the checkout.
            const probe = join(root, 'probe.mjs')
            writeFileSync(probe, [
                "import { readFileSync } from 'node:fs'",
                "import { join } from 'node:path'",
                `const base = ${JSON.stringify(base)}`,
                `const branch = ${JSON.stringify(branch)}`,
                "const mod = await import(new URL('file://' + join(base, 'local-ci.mjs')).href)",
                'console.log(JSON.stringify(mod.runnerHandoff({',
                '    pinned: false,',
                '    executingSource: mod.EXECUTING_RUNNER_SOURCE,',
                "    branchSource: readFileSync(join(branch, 'local-ci.mjs'), 'utf8'),",
                '    executingHelpers: mod.EXECUTING_HELPER_SOURCES,',
                "    branchHelpers: Object.fromEntries(mod.RUNNER_HELPER_MODULES.map((n) => [n, readFileSync(join(branch, n), 'utf8')])),",
                '})))',
            ].join('\n'))

            const decided = spawnSync(process.execPath, [probe], { encoding: 'utf8', timeout: 60_000 })
            expect(decided.status, decided.stderr).toBe(0)
            const decision = JSON.parse(decided.stdout.trim().split('\n').pop())

            expect(decision.action).toBe('handoff')
            expect(decision.reason).toContain('scripts/spawn-helpers.mjs differs')

            // The handoff starts the branch's runner; `--help` exits before any git work,
            // after its imports have run. Only the branch's copy prints the marker.
            const handedOff = spawnSync(process.execPath, [join(branch, 'local-ci.mjs'), '--help'], { encoding: 'utf8', timeout: 60_000 })
            const inPlace = spawnSync(process.execPath, [join(base, 'local-ci.mjs'), '--help'], { encoding: 'utf8', timeout: 60_000 })

            expect(handedOff.status, handedOff.stderr).toBe(0)
            expect(handedOff.stdout).toContain('BRANCH-HELPER-LOADED')
            expect(inPlace.status, inPlace.stderr).toBe(0)
            expect(inPlace.stdout).not.toContain('BRANCH-HELPER-LOADED')
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })
})

describe('main() passes the helpers to the handoff', () => {
    it('compares the captured helper sources against the branch\'s files', () => {
        const lines = readFileSync(join(scriptsDir, 'local-ci.mjs'), 'utf8').split('\n')
        const call = lines.findIndex((line) => line.includes('const handoff = runnerHandoff({'))
        const args = lines.slice(call, call + 14).join('\n')

        expect(call).toBeGreaterThan(-1)
        expect(args).toContain('executingHelpers: EXECUTING_HELPER_SOURCES,')
        expect(args).toContain("branchHelpers: Object.fromEntries(RUNNER_HELPER_MODULES.map(")
        expect(args).toContain("join(worktreePath, 'scripts', name)")
    })
})
