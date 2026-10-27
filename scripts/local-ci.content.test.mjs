import { describe, it, expect } from 'vitest'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, rmSync, writeFileSync, mkdirSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { contentLitter, worktreeDriftEntries, KNOWN_CONTENT_LITTER } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#1873 — tests used to persist fixtures straight into the live content/ submodule
// and remove them in an `afterEach` that only runs if the PHP process survives to
// it. A band killed at its bound stranded the file; the submodule then read dirty
// for every later run on that checkout, and `statamic:stache:warm` indexed the
// stray as real content (the UAMS-Web/uams-statamic-archive#1845 mechanism). The write is fixed on the test side
// by Tests\Support\ContentWriteIsolatedTestCase; these cover the gate that catches
// the next instance of it.

/** Build parsed entries the way the runner does, from `-z` porcelain. */
const entries = (...records) => worktreeDriftEntries(records.map((r) => `${r}\0`).join(''))

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

// Every case below exercises the helper. None of them notices if the WIRING is
// deleted — and neither does anything else in the repo: `local-ci.chain.test.mjs`
// reads only the workflow's job headers and `needs:` edges, and the
// `.githooks/pre-commit` parity gate only checks that both mirrored file NAMES are
// staged, which a commit removing the gate from both would satisfy. So a green
// suite would survive the gate being dropped, which is the same false green the
// gate itself exists to remove.
describe('the gate is wired into both mirrored CI files', () => {
    it('runs in the local runner`s phpunit job', () => {
        const runner = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')

        expect(runner).toContain('contentLitter(')
        // The snapshot half is what makes the verdict attributable; a check that
        // only reads the AFTER state reds whichever PR follows the guilty one. Since
        // UAMS-Web/uams-statamic-archive#2477 the snapshot covers every gate submodule (`content`, `users` and, since
        // UAMS-Web/uams-statamic-archive#2818, `resources/forms`),
        // keyed by the same list the init step reads.
        expect(runner).toContain('const litterBefore = Object.fromEntries(GATE_SUBMODULES.map((name) => [name, submoduleStatus(name)]))')
        expect(runner).toContain('for (const name of GATE_SUBMODULES) {')
    })

    it('runs in the ci.yml phpunit job', () => {
        const workflow = readFileSync(join(repoRoot, '.github', 'workflows', 'ci.yml'), 'utf8')

        expect(workflow).toContain('Check the suite left content/, users/ and resources/forms/ clean')
        // `if: always()` is load-bearing, not style: a band that FAILED is exactly
        // when cleanup did not run, so a conditional step would hide the litter in
        // the run most likely to have produced it.
        const step = workflow.slice(workflow.indexOf('Check the suite left content/, users/ and resources/forms/ clean'))
        expect(step.slice(0, 200)).toContain('if: always()')
        // Every gate submodule is probed by the one loop (UAMS-Web/uams-statamic-archive#2477, UAMS-Web/uams-statamic-archive#2818).
        expect(step.slice(0, 400)).toContain('for sub in content users resources/forms; do')
    })
})

describe('contentLitter', () => {
    it('passes when the suite left the submodule as it found it', () => {
        const verdict = contentLitter([], [])

        expect(verdict.introduced).toEqual([])
        expect(verdict.preexisting).toEqual([])
    })

    it('fails and names the path when the suite dirtied the submodule', () => {
        // Verbatim from the reproduction on main: the ENTRY file stranded first,
        // before any tree file — which is why the fix covers entry writes and not
        // just the collection trees the ticket described.
        const after = entries('?? collections/post_categories/default/zz-pc-test-parent.md')
        const verdict = contentLitter([], after)

        expect(verdict.introduced.map((e) => e.path)).toEqual([
            'collections/post_categories/default/zz-pc-test-parent.md',
        ])
    })

    it('catches a modified or deleted committed file, not just untracked litter', () => {
        // The more serious defect: untracked litter is additive, but these rewrite
        // or remove checked-out content. Both were observed — a run deleted
        // `trees/collections/default/recognition_lists.yaml` outright.
        const after = entries(
            ' M collections/people/default/some-person.md',
            ' D trees/collections/default/recognition_lists.yaml',
        )
        const verdict = contentLitter([], after)

        expect(verdict.introduced.map((e) => e.status)).toEqual([' M', ' D'])
        expect(verdict.introduced.map((e) => e.path)).toEqual([
            'collections/people/default/some-person.md',
            'trees/collections/default/recognition_lists.yaml',
        ])
    })

    it('does not blame this run for a worktree that arrived dirty', () => {
        // The runner REUSES its worktree. A bare "is it dirty now" check reds
        // whichever PR happens to run after the guilty one — the "caught three runs
        // later" confusion the gate exists to end.
        const stale = entries('?? collections/a/stale.md')
        const verdict = contentLitter(stale, stale)

        expect(verdict.introduced).toEqual([])
        expect(verdict.preexisting.map((e) => e.path)).toEqual(['collections/a/stale.md'])
    })

    it("separates this run's litter from what was already there", () => {
        const before = entries('?? collections/a/stale.md')
        const after = entries('?? collections/a/stale.md', '?? collections/b/fresh.md')
        const verdict = contentLitter(before, after)

        expect(verdict.introduced.map((e) => e.path)).toEqual(['collections/b/fresh.md'])
        expect(verdict.preexisting.map((e) => e.path)).toEqual(['collections/a/stale.md'])
    })

    it('fails on the asset-mirror write that used to be held aside', () => {
        // app/Models/Asset.php rewrites content/models/assets/{id}.yml through
        // base_path() on model save, outside the Stache — which is why no store
        // re-root reached it and why it was exempted rather than fixed. UAMS-Web/uams-statamic-archive#1919 closed
        // it at the seam instead (Asset::$skipYamlSync is armed for every test by
        // Tests\TestCase::setUp()), so the exemption came out and this is now an
        // ordinary failure. If it ever comes back, this test is what says so.
        const after = entries(' M models/assets/1.yml')
        const verdict = contentLitter([], after)

        expect(verdict.introduced.map((e) => e.path)).toEqual(['models/assets/1.yml'])
        expect(verdict.known).toEqual([])
    })

    it('fails on every path of a multi-write run, with nothing exempt', () => {
        // The pairing that used to prove the allowlist was not a blanket amnesty.
        // With the list empty it proves something stronger: both paths fail, and the
        // asset write has no privileged status left.
        const after = entries(' M models/assets/1.yml', '?? trees/collections/default/post_categories.yaml')
        const verdict = contentLitter([], after)

        expect(verdict.introduced.map((e) => e.path)).toEqual([
            'models/assets/1.yml',
            'trees/collections/default/post_categories.yaml',
        ])
        expect(verdict.known).toEqual([])
    })

    it('carries no held-aside debt at all', () => {
        // A prefix list that grows silently stops being debt and becomes a blind spot,
        // so the list is pinned rather than merely kept short. Empty is the current
        // truth; adding an entry has to change this line too, which puts the exemption
        // in front of a reviewer instead of inside a gate nobody reads.
        expect(KNOWN_CONTENT_LITTER).toEqual([])
    })

    it('reports a path that was cleaned up as neither introduced nor preexisting', () => {
        // A run that removes what it found is not littering. Only the AFTER state
        // can fail the job, which keeps the gate about what is on disk at the end.
        const before = entries('?? collections/a/stale.md')
        const verdict = contentLitter(before, [])

        expect(verdict.introduced).toEqual([])
        expect(verdict.preexisting).toEqual([])
    })
})

// The cases above feed the verdict hand-built entries. These run the REAL
// `git status --porcelain -z` against a real repository, so the gate is proven
// against git's actual output rather than against this file's idea of it — a
// fixture that drifts from git would otherwise pass while the gate failed for real.
describe('the gate against a real git repository', () => {
    /** A throwaway repo with one committed file, standing in for a clean content/. */
    function makeRepo() {
        const dir = mkdtempSync(join(tmpdir(), 'local-ci-content-'))
        const git = (...args) => execFileSync('git', ['-C', dir, ...args], { encoding: 'utf8' })

        git('init', '--quiet')
        git('config', 'user.email', 'test@example.com')
        git('config', 'user.name', 'Test')
        mkdirSync(join(dir, 'collections', 'post_categories', 'default'), { recursive: true })
        writeFileSync(join(dir, 'collections', 'post_categories', 'default', 'real.md'), 'title: Real\n')
        git('add', '.')
        git('commit', '--quiet', '-m', 'content')

        const status = () =>
            worktreeDriftEntries(git('status', '--porcelain', '-z', '--untracked-files=all'))

        return { dir, status, cleanup: () => rmSync(dir, { recursive: true, force: true }) }
    }

    it('passes on a clean checkout', () => {
        const repo = makeRepo()
        try {
            const before = repo.status()
            expect(before).toEqual([])
            expect(contentLitter(before, repo.status()).introduced).toEqual([])
        } finally {
            repo.cleanup()
        }
    })

    it('fails on a deliberately dirtied checkout, naming the stranded file', () => {
        const repo = makeRepo()
        try {
            const before = repo.status()

            // Exactly what a band killed mid-test leaves behind.
            writeFileSync(
                join(repo.dir, 'collections', 'post_categories', 'default', 'zz-pc-test-parent.md'),
                'title: Test Parent Category\n',
            )

            const verdict = contentLitter(before, repo.status())

            expect(verdict.introduced.map((e) => e.path)).toEqual([
                'collections/post_categories/default/zz-pc-test-parent.md',
            ])
        } finally {
            repo.cleanup()
        }
    })

    it('fails when the run modified committed content', () => {
        const repo = makeRepo()
        try {
            const before = repo.status()
            writeFileSync(
                join(repo.dir, 'collections', 'post_categories', 'default', 'real.md'),
                'title: Rewritten\n',
            )

            const verdict = contentLitter(before, repo.status())

            expect(verdict.introduced.map((e) => e.path)).toEqual([
                'collections/post_categories/default/real.md',
            ])
        } finally {
            repo.cleanup()
        }
    })

    it('names a path containing a space, which is why the runner reads `-z`', () => {
        // Default porcelain would hand this back C-quoted; the gate's whole output
        // is a list of paths, so a mangled one sends the reader hunting a file that
        // does not exist.
        const repo = makeRepo()
        try {
            const before = repo.status()
            writeFileSync(join(repo.dir, 'collections', 'post_categories', 'default', 'with space.md'), 'x\n')

            const verdict = contentLitter(before, repo.status())

            expect(verdict.introduced.map((e) => e.path)).toEqual([
                'collections/post_categories/default/with space.md',
            ])
        } finally {
            repo.cleanup()
        }
    })
})
