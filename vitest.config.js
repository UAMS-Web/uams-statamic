import { configDefaults, defineConfig } from 'vitest/config';

// Standalone Vitest config for the plain-JS unit suite (e.g. the coordinate
// parser). It deliberately does NOT extend vite.config.js / vite-cp.config.js —
// those import `@statamic/cms/vite-plugin`, which needs the Composer vendor
// tree, whereas the unit tests are framework-free and run on `npm ci` alone.
export default defineConfig({
    test: {
        // `.claude/**` needs naming because a dot-directory is invisible to a bare `**` glob —
        // the reason the wave generator shipped untested (UAMS-Web/uams-statamic-archive#1750).
        include: ['resources/js/**/*.test.js', 'scripts/**/*.test.mjs', '.claude/**/*.test.mjs'],
        // ...but `.claude/worktrees/` holds full checkouts of other branches, each carrying its
        // own copy of every test file. Without this the primary checkout collects them all —
        // 33 stale duplicates today — and runs other branches' tests against this one's source.
        // CI never sees it (the directory is git-ignored), which is what makes it easy to ship.
        exclude: [...configDefaults.exclude, '.claude/worktrees/**'],
        environment: 'node',
        // 16 suites fork real `git` and Node processes, so a test's duration depends on
        // the machine as much as on the code. On the 5 second default the js job failed
        // twice on code that had not changed (UAMS-Web/uams-statamic-archive#3100): a test at 5690ms and a beforeAll
        // hook past its 10 second default, both in check-content-pointer.test.mjs.
        // Measured on macOS, 12 cores, at 1123 passing tests: the slowest test ran
        // 1.34s on a quiet machine and 4.42s with four full runs at once (load average
        // 26). 30 seconds sits above both that and the 5690ms failure with room to
        // spare, and a test that genuinely hangs still fails the job inside 30 seconds
        // rather than stalling it. Hooks get twice that, because the one that failed
        // was already past 10 seconds and its true duration was never recorded.
        // A test that fails on a real error fails at once whatever these say: the bound
        // is reached only by a test that has not finished.
        testTimeout: 30_000,
        hookTimeout: 60_000,
    },
});
