import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { spawnSync } from 'node:child_process'
import { chmodSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { GATE_STEP_FILES, evaluate, parityRegions } from './check-ci-parity.mjs'

// #295: each gate step runs standalone. These run the real scripts as commands, with
// $LOCAL_CI_PHP, $LOCAL_CI_NPM and $LOCAL_CI_NPX pointed at a stub that records its
// arguments and environment and exits as told. So what is under test is what a caller
// sees: the exit status, the order of the stages, and the setup the step owns.

const scripts = dirname(fileURLToPath(import.meta.url))
const isWindows = process.platform === 'win32'

let root
let tree
let log
let env

/** Write a tool stub that runs `stub.mjs`, in the form this platform can execute. */
function writeStub(name) {
    const stub = join(root, isWindows ? `${name}.cmd` : name)

    writeFileSync(stub, isWindows
        ? `@node "%~dp0stub.mjs" ${name} %*\r\n@exit /b %errorlevel%\r\n`
        : `#!/bin/sh\nexec node "$(dirname "$0")/stub.mjs" ${name} "$@"\n`)
    chmodSync(stub, 0o755)

    return stub
}

const git = (...args) => spawnSync('git', ['-C', tree, ...args], { encoding: 'utf8' })

/** The commands the stub saw, oldest first. */
const calls = () => (existsSync(log) ? readFileSync(log, 'utf8').trim().split('\n').filter(Boolean).map((line) => JSON.parse(line)) : [])

/** The first call whose arguments contain `fragment`, and its position. */
const callOf = (fragment) => {
    const index = calls().findIndex((call) => call.args.join(' ').includes(fragment))

    return { index, call: calls()[index] }
}

/** Run a step script as a command. `failOn` makes the stub exit 3 for a matching call. */
function runStep(script, args = [], failOn = null) {
    return spawnSync(process.execPath, [join(scripts, script), '--tree', tree, ...args], {
        encoding: 'utf8',
        env: { ...env, STUB_FAIL_ON: failOn ?? '' },
    })
}

beforeEach(() => {
    root = mkdtempSync(join(tmpdir(), 'gate-steps-'))
    tree = join(root, 'tree')
    log = join(root, 'calls.jsonl')
    mkdirSync(tree)

    writeFileSync(join(root, 'stub.mjs'), [
        "import { appendFileSync } from 'node:fs'",
        'const [tool, ...args] = process.argv.slice(2)',
        'appendFileSync(process.env.STUB_LOG, JSON.stringify({ tool, args, APP_ENV: process.env.APP_ENV ?? null, DB_DATABASE: process.env.DB_DATABASE ?? null, TMP: process.env.TMP ?? null }) + "\\n")',
        'const failOn = process.env.STUB_FAIL_ON',
        'process.exit(failOn && args.join(" ").includes(failOn) ? 3 : 0)',
    ].join('\n'))

    env = {
        ...process.env,
        LOCAL_CI_PHP: writeStub('php'),
        LOCAL_CI_NPM: writeStub('npm'),
        LOCAL_CI_NPX: writeStub('npx'),
        LOCAL_CI_PHP_MEMORY_LIMIT: '5G',
        STUB_LOG: log,
    }
    delete env.APP_ENV
    delete env.DB_DATABASE

    // A real repository, because pint diffs against a base and pest's reset cleans.
    git('init', '-q', '-b', 'main')
    git('config', 'user.email', 'gate@example.test')
    git('config', 'user.name', 'Gate')
    writeFileSync(join(tree, 'README.md'), 'x\n')
    git('add', '.')
    git('commit', '-q', '-m', 'base')
})

afterEach(() => {
    rmSync(root, { recursive: true, force: true })
})

describe('gate-pint.mjs', () => {
    beforeEach(() => {
        git('checkout', '-q', '-b', 'topic')
        writeFileSync(join(tree, 'a.php'), '<?php\n')
        git('add', '.')
        git('commit', '-q', '-m', 'topic')
    })

    it('exits 0 and checks the changed file when Pint passes', () => {
        expect(runStep('gate-pint.mjs').status).toBe(0)
        expect(callOf('vendor/bin/pint').call.args.slice(-3)).toEqual(['vendor/bin/pint', '--test', 'a.php'])
    })

    it('exits with the status Pint returned', () => {
        expect(runStep('gate-pint.mjs', [], 'vendor/bin/pint').status).toBe(3)
    })

    it('exits 0 without starting PHP when the branch changed no PHP file', () => {
        const result = runStep('gate-pint.mjs', ['--base', 'topic'])

        expect(result.status).toBe(0)
        expect(result.stdout).toContain('No PHP files changed vs base')
        expect(calls()).toEqual([])
    })
})

describe('gate-rector.mjs', () => {
    it('exits 0 and runs the dry run in a private temp directory', () => {
        expect(runStep('gate-rector.mjs').status).toBe(0)

        const { call } = callOf('vendor/bin/rector')

        expect(call.args).toContain('--dry-run')
        expect(call.TMP).toContain('local-ci-rector-')
        expect(existsSync(call.TMP)).toBe(false)
    })

    it('exits with the status Rector returned', () => {
        expect(runStep('gate-rector.mjs', [], 'vendor/bin/rector').status).toBe(3)
    })
})

describe('gate-browser.mjs', () => {
    it('exits 0, building both bundles before the suite', () => {
        expect(runStep('gate-browser.mjs').status).toBe(0)
        expect(calls().map((call) => `${call.tool} ${call.args.slice(-2).join(' ')}`)).toEqual([
            'npx install chromium',
            'npm run build',
            'npm run cp:build',
            'php vendor/bin/pest tests/Browser',
        ])
    })

    it('skips both builds on --skip-build', () => {
        expect(runStep('gate-browser.mjs', ['--skip-build']).status).toBe(0)
        expect(calls().filter((call) => call.tool === 'npm')).toEqual([])
    })

    it.each([
        ['the site build', 'run build'],
        ['the CP build', 'run cp:build'],
        ['the suite', 'tests/Browser'],
    ])('exits with the status of %s when it fails', (_label, failOn) => {
        expect(runStep('gate-browser.mjs', [], failOn).status).toBe(3)
    })
})

describe('gate-pest.mjs', () => {
    it('exits 0 with its own setup in order: reset, seed, route cache, bands, clear', () => {
        expect(runStep('gate-pest.mjs').status).toBe(0)

        const order = ['optimize:clear', 'statamic:stache:clear', 'test:seed-stache', 'route:cache', '--parallel', '--group=serial', 'route:clear']
            .map((fragment) => callOf(fragment).index)

        expect(order).not.toContain(-1)
        expect(order).toEqual([...order].sort((a, b) => a - b))
    })

    it('sets the testing environment and the memory limit itself', () => {
        runStep('gate-pest.mjs')

        const seed = callOf('test:seed-stache').call
        const cache = callOf('route:cache').call

        expect([seed.APP_ENV, seed.DB_DATABASE]).toEqual(['testing', ':memory:'])
        expect(cache.APP_ENV).toBe('testing')
        expect(seed.args.slice(0, 2)).toEqual(['-d', 'memory_limit=5G'])

        const parallel = callOf('--parallel').call.args

        expect(parallel).toContain('--passthru-php=-d memory_limit=5G')
        expect(parallel).toContain('pcov.enabled=0')
    })

    it('leaves the caches alone on --no-reset', () => {
        mkdirSync(join(tree, 'storage', 'framework'), { recursive: true })
        writeFileSync(join(tree, 'storage', 'framework', 'keep.txt'), 'x')

        expect(runStep('gate-pest.mjs', ['--no-reset']).status).toBe(0)
        expect(callOf('optimize:clear').index).toBe(-1)
        expect(existsSync(join(tree, 'storage', 'framework', 'keep.txt'))).toBe(true)
    })

    it('deletes ignored and untracked files under storage/framework in the reset', () => {
        mkdirSync(join(tree, 'storage', 'framework'), { recursive: true })
        writeFileSync(join(tree, 'storage', 'framework', 'stale.txt'), 'x')

        expect(runStep('gate-pest.mjs').status).toBe(0)
        expect(existsSync(join(tree, 'storage', 'framework', 'stale.txt'))).toBe(false)
    })

    it.each([
        ['the seed', 'test:seed-stache'],
        ['the route cache', 'route:cache'],
        ['the parallel band', '--parallel'],
        ['the serial band', '--group=serial'],
    ])('exits with the status of %s when it fails', (_label, failOn) => {
        expect(runStep('gate-pest.mjs', [], failOn).status).toBe(3)
    })

    it('still runs the serial band and clears the route cache after the parallel band fails', () => {
        runStep('gate-pest.mjs', [], '--parallel')

        expect(callOf('--group=serial').index).toBeGreaterThan(-1)
        expect(callOf('route:clear').index).toBeGreaterThan(-1)
    })

    it('fails on a tracked file the suite changed in a gate submodule', () => {
        const content = join(tree, 'content')
        const sub = (...args) => spawnSync('git', ['-C', content, ...args], { encoding: 'utf8' })

        mkdirSync(content)
        sub('init', '-q')
        sub('config', 'user.email', 'gate@example.test')
        sub('config', 'user.name', 'Gate')
        writeFileSync(join(content, 'a.yaml'), 'a: 1\n')
        sub('add', '.')
        sub('commit', '-q', '-m', 'seed')

        // The stub stands in for a band that writes into the submodule.
        writeFileSync(join(root, 'stub.mjs'), [
            "import { appendFileSync, writeFileSync } from 'node:fs'",
            "if (process.argv.includes('--group=serial')) { writeFileSync(process.env.STUB_LITTER, 'a: 2\\n') }",
        ].join('\n'))
        env.STUB_LITTER = join(content, 'a.yaml')

        const result = runStep('gate-pest.mjs', ['--no-reset'])

        expect(result.status).toBe(1)
        expect(result.stderr).toContain('The suite wrote into the content/ submodule')
    })
})

describe('every step script as a command', () => {
    it.each(['gate-browser.mjs', 'gate-pest.mjs', 'gate-pint.mjs', 'gate-rector.mjs'])('%s refuses an unknown flag with exit 2 and starts nothing', (script) => {
        const result = runStep(script, ['--bogus'])

        expect(result.status).toBe(2)
        expect(result.stderr).toContain('--bogus')
        expect(calls()).toEqual([])
    })

    it('exits 2 and names the variable when a tool override cannot be executed', () => {
        // The control for this case is every test above, which accepts a stub that can run.
        const text = join(root, 'not-a-tool.txt')

        writeFileSync(text, 'x')
        chmodSync(text, 0o644)
        env.LOCAL_CI_PHP = text

        const result = runStep('gate-rector.mjs')

        expect(result.status).toBe(2)
        expect(result.stderr).toContain('LOCAL_CI_PHP')
    })
})

describe('the gate step scripts stay under the CI parity gate', () => {
    const ciRegion = {
        ciDiff: '@@ -2,1 +2,1 @@\n',
        ciSource: '# x\n      # PARITY: y\n      - name: Run\n        run: z\n',
        ciSourceOld: '# x\n      # PARITY: y\n      - name: Run\n        run: z\n',
    }

    it.each(GATE_STEP_FILES)('marks one closed region in %s that spans the step', (file) => {
        const source = readFileSync(join(scripts, '..', file), 'utf8')
        const regions = parityRegions(source, 'mjs')

        expect(regions).toHaveLength(1)
        expect(regions[0].end - regions[0].start).toBeGreaterThan(3)
    })

    it('fires on a marked step edit with no ci.yml change, and not on a change outside the marks', () => {
        const source = ['x', '// PARITY: a', 'y', '// PARITY-END', 'z'].join('\n')
        const step = (line) => ({ diff: `@@ -${line},1 +${line},1 @@\n`, source, sourceOld: source })

        expect(evaluate({ gateSteps: [step(3)] }).fire).toBe(true)
        expect(evaluate({ gateSteps: [step(5)] }).fire).toBe(false)
        expect(evaluate({ ...ciRegion, gateSteps: [step(3)] }).fire).toBe(false)
    })
})
