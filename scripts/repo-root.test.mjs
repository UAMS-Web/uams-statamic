import { describe, it, expect } from 'vitest'
import { dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { COMMON_DIR_ARGS, primaryCheckout, primaryCheckoutFrom } from './repo-root.mjs'

// The whole point of this module is that "the repo root" has two answers in a repository with
// linked worktrees, and only one of them anchors a SIBLING path correctly. `--show-toplevel`
// answers for the worktree you are standing in, so a slot derived from it nests inside that tree
// instead of sitting beside the primary. These pin the derivation, the guard that keeps a submodule
// from producing a path inside `.git`, and the fact that both trees agree on the answer.

describe('primaryCheckoutFrom', () => {
    // Measured on this repo: `--git-common-dir` reports the PRIMARY's `.git` from a linked
    // worktree, which is exactly the property `--show-toplevel` lacks.
    it('takes the parent of the primary .git, whichever worktree asked', () => {
        expect(primaryCheckoutFrom('C:/Users/dev/Herd/uams-statamic/.git'))
            .toBe('C:/Users/dev/Herd/uams-statamic')
    })

    it('normalizes backslashes and a trailing separator before deriving', () => {
        expect(primaryCheckoutFrom('C:\\Users\\dev\\Herd\\uams-statamic\\.git\\'))
            .toBe('C:/Users/dev/Herd/uams-statamic')
    })

    // Run from inside the content submodule, git answers with the submodule's gitdir. Its parent
    // is a path INSIDE .git, so accepting it would place a sibling worktree in the git directory —
    // silently, because `git worktree add` succeeds there. Refusing is the whole guard.
    it('refuses a submodule gitdir rather than returning a path inside .git', () => {
        const submoduleGitDir = 'C:/Users/dev/Herd/uams-statamic/.git/worktrees/uams-statamic-a/modules/content'

        expect(() => primaryCheckoutFrom(submoduleGitDir)).toThrow(/not a '\.git' directory/)
    })

    it('refuses empty output rather than deriving from nothing', () => {
        expect(() => primaryCheckoutFrom('')).toThrow(/Not inside a git repository/)
        expect(() => primaryCheckoutFrom(null)).toThrow(/Not inside a git repository/)
    })

    // A failure that names only "not inside a git repository" hides the cause it was handed. Every
    // git-side failure otherwise reads identically, whether the directory is outside a repo, the
    // index is broken, or git could not start at all.
    it('carries git\'s own message into the failure rather than discarding it', () => {
        expect(() => primaryCheckoutFrom(null, 'fatal: detected dubious ownership'))
            .toThrow(/dubious ownership/)
    })
})

describe('primaryCheckout', () => {
    // An integration check against the real repository: whatever tree the suite runs in — the
    // primary or either slot — the answer must be the same primary checkout, and it must contain
    // this very file. That is the property the default worktree path depends on.
    // `fileURLToPath`, never `new URL(...).pathname` — the latter leaves the path percent-encoded,
    // so a checkout under a directory with a space (or any non-ASCII character) would hand git a
    // literal `%20` and this test would red on the path rather than on the behavior.
    it('agrees on one answer from the process cwd and from this file\'s directory', () => {
        const fromCwd = primaryCheckout(process.cwd())
        const fromHere = primaryCheckout(dirname(fileURLToPath(import.meta.url)))

        expect(fromCwd).toBe(fromHere)
        expect(fromCwd).toMatch(/[^/\\]$/)
    })

    it('asks git for the common dir, not the toplevel', () => {
        expect(COMMON_DIR_ARGS).toContain('--git-common-dir')
        expect(COMMON_DIR_ARGS).not.toContain('--show-toplevel')
    })

    it('reports a directory outside any repository rather than guessing', () => {
        expect(() => primaryCheckout('/')).toThrow()
    })
})
