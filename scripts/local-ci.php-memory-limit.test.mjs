import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { withPhpMemoryLimit } from './local-ci.mjs'

// UAMS-Web/uams-statamic-archive#3212: the mutate-ignore auditor tokenizes every file under app and tests and
// needs more than 128M. It ran as a bare `php` call at the CLI's own memory_limit,
// so a PHP with a low default failed the encoding job with exit 255 for a reason
// unrelated to the branch. Every PHP process the runner starts now goes through
// runPhp()/capturePhp(), which prefix the runner's memory limit.

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const runner = readFileSync(join(repoRoot, 'scripts', 'local-ci.mjs'), 'utf8')

describe('withPhpMemoryLimit()', () => {
    it('prefixes the limit ahead of the script and its arguments', () => {
        expect(withPhpMemoryLimit(['scripts/check-mutate-ignore.php', 'app', 'tests'], '4G')).toEqual([
            '-d',
            'memory_limit=4G',
            'scripts/check-mutate-ignore.php',
            'app',
            'tests',
        ])
    })

    it('passes an unlimited override through unchanged', () => {
        expect(withPhpMemoryLimit(['artisan', 'route:clear'], '-1').slice(0, 2)).toEqual(['-d', 'memory_limit=-1'])
    })
})

describe('every PHP process the runner starts carries the memory limit', () => {
    it('runs the mutate-ignore auditor through runPhp()', () => {
        expect(runner).toContain("runPhp(['scripts/check-mutate-ignore.php', 'app', 'tests'], worktreePath)")
    })

    it('defines runPhp() and capturePhp() on withPhpMemoryLimit()', () => {
        expect(runner).toContain('const runPhp = (args, cwd, extraEnv) => run(php, withPhpMemoryLimit(args), cwd, extraEnv)')
        expect(runner).toContain('const capturePhp = (args, cwd) => capture(php, withPhpMemoryLimit(args), cwd)')
    })

    it('leaves no bare run() or capture() of php outside those two definitions', () => {
        // `\s*` spans the line break in a call written across lines, such as the
        // mutate smoke's `capture(\n php,`.
        const bare = runner.match(/\b(?:run|capture)\(\s*php\s*,/g) ?? []

        expect(bare).toHaveLength(2)
        expect(runner.match(/\b(?:runPhp|capturePhp)\(/g)?.length ?? 0).toBeGreaterThan(5)
    })

    it.each(['gate-browser.mjs', 'gate-pest.mjs', 'gate-pint.mjs', 'gate-rector.mjs'])('starts PHP in %s only through phpRunner()', (name) => {
        const step = readFileSync(join(repoRoot, 'scripts', name), 'utf8')

        expect(step).toContain('const runPhp = phpRunner(php)')
        expect(step.match(/\b(?:run|capture)\(\s*php\s*,/g) ?? []).toEqual([])
    })

    it('defines phpRunner() on withPhpMemoryLimit()', () => {
        const shared = readFileSync(join(repoRoot, 'scripts', 'gate-process.mjs'), 'utf8')

        expect(shared).toContain('return (args, cwd, extraEnv) => run(php, withPhpMemoryLimit(args), cwd, extraEnv)')
    })
})
