// Coverage for where the runner handoff reads its OWN side of the comparison (UAMS-Web/uams-statamic-archive#2856).
//
// The UAMS-Web/uams-statamic-archive#1882 handoff compares the executing runner against the branch's copy. It used
// to read the executing side from disk AFTER the worktree checkout, so a runner
// invoked from inside the worktree it validates compared one file with itself and
// could never hand off. Two kinds of test, for the reason the lock test gives: the
// behavioral ones prove a source captured at load survives the file changing
// under it and drives the decision, and the ordering ones prove `main()` actually
// passes that capture. The ordering ones are the guard on the defect itself: the
// behavioral ones would still pass if `main()` went back to a fresh read.
import { describe, expect, it } from 'vitest'
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { tmpdir } from 'node:os'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import {
    EXECUTING_RUNNER_PATH,
    EXECUTING_RUNNER_SOURCE,
    RUNNER_HELPER_MODULES,
    isInsideTree,
    runnerHandoff,
    runnerProvenanceLine,
} from './local-ci.mjs'

const scriptsDir = dirname(fileURLToPath(import.meta.url))
const runnerPath = join(scriptsDir, 'local-ci.mjs')
const scratch = () => mkdtempSync(join(tmpdir(), 'ci-handoff-'))

/**
 * Copy the runner and the two modules it imports into a scratch `scripts/` folder,
 * then run a probe that imports that copy, optionally rewrites the copy's file the
 * way a checkout does, and reports what the handoff decides.
 *
 * @param {boolean} rewrite  Whether the probe replaces the file after importing it.
 * @returns {{ action: string, reason: string }} The handoff decision the copy made.
 */
function decideAfterLoad(rewrite) {
    const root = scratch()

    try {
        const dir = join(root, 'scripts')
        mkdirSync(dir)
        // The runner and every helper it imports, so the copy loads as the original does
        for (const file of ['local-ci.mjs', ...RUNNER_HELPER_MODULES]) {
            copyFileSync(join(scriptsDir, file), join(dir, file))
        }

        // The rewrite stands in for `git checkout <branch>` landing a different
        // runner on the path this process was loaded from.
        const probe = join(root, 'probe.mjs')
        writeFileSync(probe, [
            "import { readFileSync, appendFileSync } from 'node:fs'",
            "const runner = new URL('./scripts/local-ci.mjs', import.meta.url)",
            "const mod = await import(runner.href)",
            rewrite ? "appendFileSync(runner, '\\nconst BRANCH_ONLY_JOB = 1\\n')" : '',
            'const decision = mod.runnerHandoff({',
            '    pinned: false,',
            '    executingSource: mod.EXECUTING_RUNNER_SOURCE,',
            "    branchSource: readFileSync(runner, 'utf8'),",
            '})',
            'console.log(JSON.stringify(decision))',
        ].join('\n'))

        const result = spawnSync(process.execPath, [probe], { encoding: 'utf8', timeout: 60_000 })
        expect(result.status, result.stderr).toBe(0)

        return JSON.parse(result.stdout.trim().split('\n').pop())
    } finally {
        rmSync(root, { recursive: true, force: true })
    }
}

describe('the executing runner source', () => {
    it('is this file, read at module load', () => {
        expect(EXECUTING_RUNNER_PATH).toBe(runnerPath)
        expect(EXECUTING_RUNNER_SOURCE).toBe(readFileSync(runnerPath, 'utf8'))
    })

    it('decides handoff from the capture after the file it was loaded from is replaced', () => {
        // The UAMS-Web/uams-statamic-archive#2856 configuration: the runner's own path is inside the worktree, so the
        // checkout overwrites it. Reading it again would see the branch's bytes on both
        // sides and report "identical".
        const decision = decideAfterLoad(true)

        expect(decision.action).toBe('handoff')
        expect(decision.reason).toBe('the branch\'s runner differs from this one')
    })

    it('decides identical from the capture when the file it was loaded from is unchanged', () => {
        // The fix must not turn every run into a re-exec.
        const decision = decideAfterLoad(false)

        expect(decision.action).toBe('run')
        expect(decision.reason).toBe('the branch\'s runner is identical to this one')
    })
})

describe('main() compares against the captured source', () => {
    const source = readFileSync(runnerPath, 'utf8')
    const lines = source.split('\n')
    const indexOf = (needle) => lines.findIndex((line) => line.includes(needle))

    it('passes EXECUTING_RUNNER_SOURCE to runnerHandoff, never a fresh read', () => {
        const call = indexOf('const handoff = runnerHandoff({')
        expect(call).toBeGreaterThan(-1)

        const args = lines.slice(call, call + 6).join('\n')
        expect(args).toContain('executingSource: EXECUTING_RUNNER_SOURCE,')
        expect(args).not.toMatch(/executingSource:\s*readFileSync/)
    })

    it('tells the provenance line whether the handoff actually compared', () => {
        // A pinned child compared nothing, so its line must not describe a comparison.
        const call = indexOf('const runnerProvenance = runnerProvenanceLine({')
        expect(call).toBeGreaterThan(-1)

        expect(lines.slice(call, call + 6).join('\n')).toContain('compared: handoff.compared,')
    })

    it('reads its own file only at module load, before main() and its checkout', () => {
        // A read of the runner's own path anywhere inside main() would be a read after
        // the checkout, which is the defect. Only the one module-level capture may exist.
        const capture = indexOf('export const EXECUTING_RUNNER_SOURCE = readFileSync(EXECUTING_RUNNER_PATH')
        const mainStart = indexOf('function main() {')
        const checkout = indexOf('step(`Reusing worktree, checking out')

        expect(capture).toBeGreaterThan(-1)
        expect(capture).toBeLessThan(mainStart)
        expect(mainStart).toBeLessThan(checkout)

        const ownReads = lines
            .map((line, i) => [line, i])
            .filter(([line]) => /readFileSync\(\s*(EXECUTING_RUNNER_PATH|executingRunner|fileURLToPath\(import\.meta\.url\)|new URL\(import\.meta\.url|import\.meta\.url|process\.argv\[1\])/.test(line))
            .map(([, i]) => i)

        expect(ownReads).toEqual([capture])
    })
})

describe('isInsideTree', () => {
    it('places a file inside its tree, and a file in a sibling tree outside it', () => {
        const root = scratch()

        try {
            const tree = join(root, 'repo-ci')
            const sibling = join(root, 'repo-ci-2')
            mkdirSync(join(tree, 'scripts'), { recursive: true })
            mkdirSync(join(sibling, 'scripts'), { recursive: true })
            writeFileSync(join(tree, 'scripts', 'local-ci.mjs'), '')
            writeFileSync(join(sibling, 'scripts', 'local-ci.mjs'), '')

            expect(isInsideTree(join(tree, 'scripts', 'local-ci.mjs'), tree)).toBe(true)
            // The sibling shares the tree's name as a string prefix, which a bare
            // `startsWith` would read as inside.
            expect(isInsideTree(join(sibling, 'scripts', 'local-ci.mjs'), tree)).toBe(false)
            // The tree itself is not a file inside it.
            expect(isInsideTree(tree, tree)).toBe(false)

            // macOS's default file system ignores case, so a differently cased route
            // to the same tree is still inside it.
            if (process.platform === 'darwin') {
                expect(isInsideTree(join(tree, 'scripts', 'local-ci.mjs'), tree.toUpperCase())).toBe(true)
            }
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })

    it('compares resolved paths, so a symlinked route to the tree still counts as inside', () => {
        const root = scratch()

        try {
            const tree = join(root, 'repo-ci')
            mkdirSync(join(tree, 'scripts'), { recursive: true })
            writeFileSync(join(tree, 'scripts', 'local-ci.mjs'), '')
            const link = join(root, 'link-to-ci')
            // A junction on Windows, which needs neither admin rights nor Developer Mode.
            symlinkSync(tree, link, process.platform === 'win32' ? 'junction' : 'dir')

            expect(isInsideTree(join(link, 'scripts', 'local-ci.mjs'), tree)).toBe(true)
        } finally {
            rmSync(root, { recursive: true, force: true })
        }
    })
})

describe('runnerHandoff reports whether it compared', () => {
    it('compares only when neither pinned nor missing a branch runner', () => {
        // The provenance note describes a comparison, so it must know whether one ran.
        expect(runnerHandoff({ pinned: true, executingSource: 'a', branchSource: 'b' }).compared).toBe(false)
        expect(runnerHandoff({ pinned: false, executingSource: 'a', branchSource: null }).compared).toBe(false)
        expect(runnerHandoff({ pinned: false, executingSource: 'a', branchSource: 'a' }).compared).toBe(true)
        expect(runnerHandoff({ pinned: false, executingSource: 'a', branchSource: 'b' }).compared).toBe(true)
    })
})

describe('runnerProvenanceLine', () => {
    const reason = 'the branch\'s runner is identical to this one'

    it('names the runner and the reason when it runs from another checkout', () => {
        expect(runnerProvenanceLine({ executingRunner: runnerPath, worktreePath: join(tmpdir(), 'elsewhere-ci'), reason }))
            .toBe(`${runnerPath}: ${reason}`)
    })

    it('says so when the runner runs from inside the worktree it validates', () => {
        // The line a reader checks to rule out a one-file comparison has to say which
        // configuration produced the verdict, not leave it to be inferred.
        const line = runnerProvenanceLine({ executingRunner: runnerPath, worktreePath: dirname(scriptsDir), reason })

        expect(line).toContain(reason)
        expect(line).toContain('from inside the worktree it validates')
        expect(line).toContain('compared as loaded before the checkout')
    })

    it('adds no comparison note to a run that made no comparison', () => {
        // A pinned child, or a branch with no runner, compared nothing, so a note about
        // how the comparison was made would describe something that did not happen.
        const pinned = 'pinned by the parent run to the branch\'s own runner'

        expect(runnerProvenanceLine({ executingRunner: runnerPath, worktreePath: dirname(scriptsDir), reason: pinned, compared: false }))
            .toBe(`${runnerPath}: ${pinned}`)
    })
})
