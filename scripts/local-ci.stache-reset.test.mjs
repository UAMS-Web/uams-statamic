import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { contentMoveNote } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#2832 — on a reused worktree, a Stache cached against the previous content/
// revision crashes the first app boot once the new revision adds a collection
// (`Call to a member function sites() on null` in CollectionEntriesStore). The
// runner clears the file caches between the submodule step and the first boot,
// and names the move when a first boot fails anyway. These pin both.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const runner = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')

/** The offset of a fragment that must occur exactly once in the runner. */
const offsetOf = (fragment) => {
    const first = runner.indexOf(fragment)

    expect(first, `missing from scripts/local-ci.mjs: ${fragment}`).toBeGreaterThanOrEqual(0)
    expect(runner.indexOf(fragment, first + 1), `occurs more than once: ${fragment}`).toBe(-1)

    return first
}

const SUBMODULE_STEP = "step('Initializing content/, users/ and resources/forms/ submodules')"
const EARLY_CLEAR_STEP = "step('Clearing reused-worktree file caches before the first app boot')"
const EARLY_CLEAR_RUN = 'clearFileCaches(git, worktreePath, repoRoot)'
const KEY_GENERATE = "runPhp(['artisan', 'key:generate'], worktreePath)"
const POST_AUTOLOAD_DUMP = "run(composer, ['run-script', 'post-autoload-dump', '--no-interaction'], worktreePath)"
const ARTISAN_RESET = 'clearRuntimeCaches(php, worktreePath)'

describe('the reused-worktree cache clear runs before the first app boot', () => {
    it('sits after the submodule step and before both boots in the install sequence', () => {
        const submodule = offsetOf(SUBMODULE_STEP)
        const clearStep = offsetOf(EARLY_CLEAR_STEP)
        const clearRun = offsetOf(EARLY_CLEAR_RUN)

        expect(clearStep).toBeGreaterThan(submodule)
        expect(clearRun).toBeGreaterThan(clearStep)
        expect(clearRun).toBeLessThan(offsetOf(KEY_GENERATE))
        expect(clearRun).toBeLessThan(offsetOf(POST_AUTOLOAD_DUMP))
    })

    it('is a filesystem clear, with nothing booting the app between the submodule step and it', () => {
        // `artisan statamic:stache:clear` boots the app first and crashes on the
        // same stale index, so the window must hold no `php` invocation at all.
        const window = runner.slice(offsetOf(SUBMODULE_STEP), offsetOf(EARLY_CLEAR_RUN))

        expect(window).not.toMatch(/runPhp\(\['artisan'/)
        expect(window).not.toContain("'run-script'")
    })

    it('leaves the artisan half of the reset after the install, where booting is safe', () => {
        expect(offsetOf(ARTISAN_RESET)).toBeGreaterThan(offsetOf(POST_AUTOLOAD_DUMP))
    })

    it('names a content move in both first-boot failure labels', () => {
        expect(runner).toContain('contentMove ? `key:generate (${contentMove})` : \'key:generate\'')
        expect(runner).toContain('contentMove ? `post-autoload-dump (${contentMove})` : \'post-autoload-dump\'')
        expect(runner).toContain('contentMove = contentMoveNote(contentBefore, contentRevision())')
    })
})

describe('contentMoveNote', () => {
    const before = '51a49885570d24328da3044955553f1339d85101'
    const after = '942f64f2729b13f3ea46ee88305e7d1ff7547d3d'

    it('names both short revisions when content/ moved in this run', () => {
        expect(contentMoveNote(before, after)).toBe('the first app boot after content/ moved 51a498855 -> 942f64f27 in this run')
    })

    it('says nothing when the revision did not change', () => {
        expect(contentMoveNote(before, before)).toBeNull()
    })

    it('says nothing when either revision is unknown', () => {
        expect(contentMoveNote(null, after)).toBeNull()
        expect(contentMoveNote(before, null)).toBeNull()
        expect(contentMoveNote('', after)).toBeNull()
    })
})
