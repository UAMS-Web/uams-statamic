// cspell:ignore gpgsign
import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { execFileSync, spawnSync } from 'node:child_process'
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { tmpdir } from 'node:os'
import { fileURLToPath, pathToFileURL } from 'node:url'
import {
    ABSENT,
    CANNOT_RUN,
    NOT_ON_BRANCH,
    PASS,
    checkContentPointer,
    declaredSubmodules,
    githubRepoFromUrl,
    gitlinkFromLsTree,
    parseArgs,
    pullRequestLine,
} from './check-content-pointer.mjs'

// UAMS-Web/uams-statamic-archive#2965 — the four outcomes the ticket names, each against real git repositories
// built in a temp directory: a content repository with a `main` and a branch, and a
// superproject whose `content` gitlink is set to whichever commit a case needs.
//
// The unchanged case points .gitmodules at a path that does not exist, so a pass
// there is proof the check never cloned. The failed-clone case uses the same path
// with a MOVED pointer, which is what makes that proof readable: the same URL is
// fatal the moment the check does reach for it.

const script = join(dirname(fileURLToPath(import.meta.url)), 'check-content-pointer.mjs')

const identity = ['-c', 'user.name=t', '-c', 'user.email=t@example.test', '-c', 'commit.gpgsign=false']

function sh(cwd, ...args) {
    return execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim()
}

function commit(cwd, file, message) {
    writeFileSync(join(cwd, file), `${message}\n`)
    sh(cwd, 'add', file)
    sh(cwd, ...identity, 'commit', '-q', '-m', message)

    return sh(cwd, 'rev-parse', 'HEAD')
}

let root
let contentUrl
let onMain
let olderOnMain
let onBranch
const missingUrl = () => pathToFileURL(join(root, 'no-such-repo')).href
const absentSha = 'f'.repeat(40)

function gitmodules(dir, url) {
    writeFileSync(join(dir, '.gitmodules'), `[submodule "content"]\n\tpath = content\n\turl = ${url}\n`)
    sh(dir, 'add', '.gitmodules')
}

/**
 * A superproject whose `main` points content at `basePointer` (or carries no
 * gitlink, when it is null) and whose HEAD, a branch off it, points content at
 * `headPointer`. `headUrl` rewrites .gitmodules in the same commit as the bump.
 */
function superproject(name, { basePointer, headPointer, url = contentUrl, headUrl = url }) {
    const dir = join(root, name)
    sh(root, 'init', '-q', '-b', 'main', dir)
    gitmodules(dir, url)
    if (basePointer !== null) {
        sh(dir, 'update-index', '--add', '--cacheinfo', `160000,${basePointer},content`)
    }
    sh(dir, ...identity, 'commit', '-q', '-m', 'base')
    sh(dir, 'checkout', '-q', '-b', 'feature')
    sh(dir, 'update-index', '--add', '--cacheinfo', `160000,${headPointer},content`)
    if (headUrl !== url) {
        gitmodules(dir, headUrl)
    }
    sh(dir, ...identity, 'commit', '-q', '--allow-empty', '-m', 'bump')

    return dir
}

/**
 * Run `fn` with git rewriting a GitHub URL to the fixture, the way ci.yml's
 * insteadOf rewrite reaches the real repository. Lets the pull-request lookup be
 * exercised against a URL that names a GitHub repository.
 */
function asGitHub(fn) {
    const keys = ['GIT_CONFIG_COUNT', 'GIT_CONFIG_KEY_0', 'GIT_CONFIG_VALUE_0']
    const saved = keys.map((key) => process.env[key])
    process.env.GIT_CONFIG_COUNT = '1'
    process.env.GIT_CONFIG_KEY_0 = `url.${contentUrl}.insteadOf`
    process.env.GIT_CONFIG_VALUE_0 = githubUrl

    try {
        return fn()
    } finally {
        keys.forEach((key, i) => (saved[i] === undefined ? delete process.env[key] : (process.env[key] = saved[i])))
    }
}

const githubUrl = 'https://github.com/test-org/test-content.git'

function check(dir, findPulls = () => ({ pulls: [] })) {
    return checkContentPointer({ cwd: dir, base: 'main', contentBranch: 'main', findPulls })
}

beforeAll(() => {
    root = mkdtempSync(join(tmpdir(), 'content-pointer-test-'))
    const content = join(root, 'content-remote')
    sh(root, 'init', '-q', '-b', 'main', content)
    olderOnMain = commit(content, 'a.txt', 'first')
    onMain = commit(content, 'a.txt', 'second')
    sh(content, 'checkout', '-q', '-b', '2965-only-here')
    onBranch = commit(content, 'b.txt', 'branch only')
    sh(content, 'checkout', '-q', 'main')
    contentUrl = pathToFileURL(content).href
})

afterAll(() => {
    rmSync(root, { recursive: true, force: true })
})

describe('check-content-pointer', () => {
    it('passes an unchanged pointer without cloning content', () => {
        const dir = superproject('unchanged', { basePointer: onBranch, headPointer: onBranch, url: missingUrl() })
        const result = check(dir)

        expect(result.code).toBe(PASS)
        expect(result.lines.join('\n')).toContain('No fetch needed')
    })

    it('reports a failed clone as could-not-run, never as an absent commit', () => {
        // The control for the case above: the same unreachable URL, with the pointer
        // moved, has to fail. It fails as CANNOT_RUN, because a clone that did not
        // happen says nothing about whether the commit exists.
        const dir = superproject('clone-fails', { basePointer: olderOnMain, headPointer: onMain, url: missingUrl() })
        const result = check(dir)

        expect(result.code).toBe(CANNOT_RUN)
        expect(result.lines[0]).toContain('Could not clone')
    })

    it('passes a moved pointer that is on content main, at its tip or behind it', () => {
        expect(check(superproject('tip', { basePointer: olderOnMain, headPointer: onMain })).code).toBe(PASS)
        expect(check(superproject('behind', { basePointer: onMain, headPointer: olderOnMain })).code).toBe(PASS)
    })

    it('refuses a pointer that is only on a content branch, naming the pointer, the branch and the pull request', () => {
        const asked = []
        const dir = superproject('branch-only', { basePointer: onMain, headPointer: onBranch })
        const result = check(dir, (repo, sha) => {
            asked.push([repo, sha])

            return { pulls: [] }
        })
        const text = result.lines.join('\n')

        expect(result.code).toBe(NOT_ON_BRANCH)
        expect(text).toContain(onBranch)
        expect(text).toContain('2965-only-here')
        expect(text).toContain(`It was ${onMain} on main.`)
        // A file:// URL is not a GitHub repository, so no lookup is attempted.
        expect(asked).toEqual([])
        expect(text).toContain('not a GitHub repository')
    })

    it('refuses a pointer naming a commit content does not have, and says so differently', () => {
        const dir = superproject('absent', { basePointer: onMain, headPointer: absentSha })
        const result = check(dir)

        expect(result.code).toBe(ABSENT)
        expect(result.code).not.toBe(NOT_ON_BRANCH)
        expect(result.lines[0]).toContain(`${absentSha} names a commit that is not in`)
    })

    it('asks GitHub for the pull request carrying the pointer, on both refusals', () => {
        const asked = []
        const findPulls = (repo, sha) => {
            asked.push([repo, sha])

            return { pulls: [{ number: 71, state: 'open', url: 'https://github.com/test-org/test-content/pull/71' }] }
        }

        const branchOnly = asGitHub(() => check(superproject('gh-branch', { basePointer: onMain, headPointer: onBranch, url: githubUrl }), findPulls))
        expect(branchOnly.code).toBe(NOT_ON_BRANCH)
        expect(branchOnly.lines.join('\n')).toContain('Land first: test-org/test-content#71')
        expect(branchOnly.lines.join('\n')).toContain('Merge the `content` change to `main` first')

        // Already squash-merged: the remedy is a new pointer, so "merge it first" is dropped.
        const squashed = asGitHub(() => check(
            superproject('gh-squashed', { basePointer: onMain, headPointer: onBranch, url: githubUrl }),
            () => ({ pulls: [{ number: 65, state: 'closed', url: 'u', merged: true, merge_commit_sha: onMain }] }),
        ))
        expect(squashed.code).toBe(NOT_ON_BRANCH)
        expect(squashed.lines.join('\n')).toContain(`landed as ${onMain}`)
        expect(squashed.lines.join('\n')).not.toContain('Merge the `content` change')

        const absent = asGitHub(() => check(superproject('gh-absent', { basePointer: onMain, headPointer: absentSha, url: githubUrl }), findPulls))
        expect(absent.code).toBe(ABSENT)
        expect(absent.lines.join('\n')).toContain('test-org/test-content#71')

        expect(asked).toEqual([['test-org/test-content', onBranch], ['test-org/test-content', absentSha]])
    })

    it('checks the repository the base names, refusing a bump that also rewrites the URL', () => {
        // A fork whose main carries the branch-only commit: pointed at it, the bump would pass.
        const fork = join(root, 'fork')
        sh(root, 'clone', '-q', contentUrl, fork)
        sh(fork, 'merge', '-q', '--ff-only', 'origin/2965-only-here')
        const dir = superproject('url-swap', { basePointer: onMain, headPointer: onBranch, headUrl: pathToFileURL(fork).href })
        const result = check(dir)

        expect(result.code).toBe(CANNOT_RUN)
        expect(result.lines[0]).toContain('also changes its URL')
    })

    it('treats a base with no content gitlink as a moved pointer', () => {
        const result = check(superproject('no-base-gitlink', { basePointer: null, headPointer: onMain }))

        expect(result.code).toBe(PASS)
        expect(result.lines[0]).toContain('It was absent on main.')
    })

    it('cannot run when the base ref does not resolve', () => {
        const dir = superproject('no-base', { basePointer: onMain, headPointer: onBranch })
        const result = checkContentPointer({ cwd: dir, base: 'origin/main', contentBranch: 'main' })

        expect(result.code).toBe(CANNOT_RUN)
        expect(result.lines[0]).toContain('`origin/main` does not resolve')
    })

    it('cannot run when the content clone has no branch to check against', () => {
        const dir = superproject('no-branch', { basePointer: olderOnMain, headPointer: onMain })
        const result = checkContentPointer({ cwd: dir, base: 'main', contentBranch: 'trunk' })

        expect(result.code).toBe(CANNOT_RUN)
        expect(result.lines[0]).toContain('no `trunk` branch')
    })

    it('exits with the code it decided, from the command line', () => {
        const dir = superproject('cli', { basePointer: onMain, headPointer: onBranch })
        const run = spawnSync(process.execPath, [script, '--base', 'main'], { cwd: dir, encoding: 'utf8' })

        expect(run.status).toBe(NOT_ON_BRANCH)
        expect(run.stderr).toContain('2965-only-here')

        const bad = spawnSync(process.execPath, [script, '--base'], { cwd: dir, encoding: 'utf8' })
        expect(bad.status).toBe(CANNOT_RUN)

        const absent = spawnSync(process.execPath, [script, '--base', 'main'], { cwd: superproject('cli-absent', { basePointer: onMain, headPointer: absentSha }), encoding: 'utf8' })
        expect(absent.status).toBe(ABSENT)

        const pass = spawnSync(process.execPath, [script, '--base', 'main'], { cwd: superproject('cli-pass', { basePointer: olderOnMain, headPointer: onMain }), encoding: 'utf8' })
        expect(pass.status).toBe(PASS)
        expect(pass.stdout).toContain(`The \`content\` pointer ${onMain} is on its repository's \`main\``)
    })

    it('exits could-not-run, never 1, when the check itself throws', () => {
        // Exit 1 means "not on main", so an unexpected error must not land there.
        const dir = superproject('cli-throws', { basePointer: olderOnMain, headPointer: onMain })
        const run = spawnSync(process.execPath, [script, '--base', 'main'], {
            cwd: dir,
            encoding: 'utf8',
            // os.tmpdir() reads TMPDIR on POSIX and TEMP/TMP on Windows.
            env: { ...process.env, TMPDIR: join(root, 'no-such-tmp'), TEMP: join(root, 'no-such-tmp'), TMP: join(root, 'no-such-tmp') },
        })

        expect(run.status).toBe(CANNOT_RUN)
        expect(run.stderr).toContain('The check could not run')
    })
})

// UAMS-Web/uams-statamic-archive#3115: the job judges every submodule HEAD's .gitmodules declares, not `content`
// alone. A superproject declaring the three the runner checks out, each with its own
// remote, so a refusal can only name the submodule whose pointer moved.
const GATE_SUBMODULES = ['content', 'users', 'resources/forms']

/** One remote per gate submodule, each with a `main` and a branch-only commit. */
const remotes = {}

function multiSuperproject(name, heads) {
    const dir = join(root, name)
    sh(root, 'init', '-q', '-b', 'main', dir)
    writeFileSync(join(dir, '.gitmodules'), GATE_SUBMODULES
        .map((path) => `[submodule "${path}"]\n\tpath = ${path}\n\turl = ${remotes[path].url}\n`)
        .join(''))
    sh(dir, 'add', '.gitmodules')
    for (const path of GATE_SUBMODULES) {
        sh(dir, 'update-index', '--add', '--cacheinfo', `160000,${remotes[path].older},${path}`)
    }
    sh(dir, ...identity, 'commit', '-q', '-m', 'base')
    sh(dir, 'checkout', '-q', '-b', 'feature')
    for (const [path, sha] of Object.entries(heads)) {
        sh(dir, 'update-index', '--add', '--cacheinfo', `160000,${sha},${path}`)
    }
    sh(dir, ...identity, 'commit', '-q', '--allow-empty', '-m', 'bump')

    return dir
}

describe('check-content-pointer across every declared submodule (#3115)', () => {
    beforeAll(() => {
        for (const path of GATE_SUBMODULES) {
            const remote = join(root, `remote-${path.replace('/', '-')}`)
            sh(root, 'init', '-q', '-b', 'main', remote)
            const older = commit(remote, 'a.txt', `${path} first`)
            const onMain = commit(remote, 'a.txt', `${path} second`)
            sh(remote, 'checkout', '-q', '-b', `3115-${path.replace('/', '-')}-only`)
            const onBranch = commit(remote, 'b.txt', `${path} branch only`)
            sh(remote, 'checkout', '-q', 'main')
            remotes[path] = { url: pathToFileURL(remote).href, older, onMain, onBranch }
        }
    })

    it('reads every declared submodule from HEAD', () => {
        const dir = multiSuperproject('declared', {})

        expect(declaredSubmodules(dir)).toEqual(GATE_SUBMODULES)
    })

    it.each(GATE_SUBMODULES)('passes a moved `%s` pointer that is on its main, and names every submodule judged', (path) => {
        const result = check(multiSuperproject(`pass-${path.replace('/', '-')}`, { [path]: remotes[path].onMain }))
        const text = result.lines.join('\n')

        expect(result.code).toBe(PASS)
        expect(text).toContain(`The \`${path}\` pointer ${remotes[path].onMain} is on its repository's \`main\``)
        for (const other of GATE_SUBMODULES) {
            expect(text).toContain(`The \`${other}\` pointer`)
        }
    })

    it.each(GATE_SUBMODULES)('refuses a `%s` pointer that is only on a branch, naming that submodule', (path) => {
        const result = check(multiSuperproject(`branch-${path.replace('/', '-')}`, { [path]: remotes[path].onBranch }))
        const text = result.lines.join('\n')

        expect(result.code).toBe(NOT_ON_BRANCH)
        expect(text).toContain(`The \`${path}\` pointer ${remotes[path].onBranch} is not on its repository's \`main\``)
        expect(text).toContain(`3115-${path.replace('/', '-')}-only`)
        expect(text).toContain(`Merge the \`${path}\` change to \`main\` first`)
    })

    it.each(GATE_SUBMODULES)('refuses a `%s` pointer naming an absent commit with its own exit code', (path) => {
        const result = check(multiSuperproject(`absent-${path.replace('/', '-')}`, { [path]: absentSha }))

        expect(result.code).toBe(ABSENT)
        expect(result.lines.join('\n')).toContain(`The \`${path}\` pointer ${absentSha} names a commit that is not in`)
    })

    it('passes unchanged pointers without fetching any of their repositories', () => {
        // Every remote is renamed away, so a clone of any of them would fail.
        const dir = multiSuperproject('unchanged-all', {})
        sh(dir, 'config', '-f', '.gitmodules', 'submodule.users.url', missingUrl())
        sh(dir, 'config', '-f', '.gitmodules', 'submodule.content.url', missingUrl())
        sh(dir, 'config', '-f', '.gitmodules', 'submodule.resources/forms.url', missingUrl())
        sh(dir, 'add', '.gitmodules')
        sh(dir, ...identity, 'commit', '-q', '-m', 'urls')
        const result = checkContentPointer({ cwd: dir, base: 'HEAD', contentBranch: 'main' })

        expect(result.code).toBe(PASS)
        expect(result.lines.filter((line) => line.endsWith('No fetch needed.'))).toHaveLength(GATE_SUBMODULES.length)
    })

    it('reports the most specific refusal when submodules disagree', () => {
        const absentAndBranch = check(multiSuperproject('mixed-absent', { users: absentSha, 'resources/forms': remotes['resources/forms'].onBranch }))
        expect(absentAndBranch.code).toBe(ABSENT)
        expect(absentAndBranch.lines.join('\n')).toContain('`resources/forms` pointer')

        const branchAndPass = check(multiSuperproject('mixed-branch', { content: remotes.content.onMain, users: remotes.users.onBranch }))
        expect(branchAndPass.code).toBe(NOT_ON_BRANCH)
    })

    it('reads the URL of a submodule whose name differs from its path', () => {
        const dir = multiSuperproject('renamed', { 'resources/forms': remotes['resources/forms'].onMain })
        writeFileSync(join(dir, '.gitmodules'), GATE_SUBMODULES
            .map((path) => `[submodule "${path === 'resources/forms' ? 'forms' : path}"]\n\tpath = ${path}\n\turl = ${remotes[path].url}\n`)
            .join(''))
        sh(dir, 'add', '.gitmodules')
        sh(dir, ...identity, 'commit', '-q', '-m', 'rename')

        const result = check(dir)

        expect(result.code).toBe(PASS)
        expect(result.lines.join('\n')).toContain(`The \`resources/forms\` pointer ${remotes['resources/forms'].onMain} is on its repository's \`main\``)
    })

    it('cannot run when HEAD declares no submodule', () => {
        const dir = join(root, 'no-gitmodules')
        sh(root, 'init', '-q', '-b', 'main', dir)
        commit(dir, 'a.txt', 'only')

        const result = checkContentPointer({ cwd: dir, base: 'main', contentBranch: 'main' })

        expect(result.code).toBe(CANNOT_RUN)
        expect(result.lines[0]).toContain('declares no submodule')
    })
})

describe('check-content-pointer helpers', () => {
    it('reads a gitlink and nothing else from ls-tree', () => {
        const sha = 'a'.repeat(40)

        expect(gitlinkFromLsTree(`160000 commit ${sha}\tcontent`)).toBe(sha)
        expect(gitlinkFromLsTree(`040000 tree ${sha}\tcontent`)).toBeNull()
        expect(gitlinkFromLsTree('')).toBeNull()
    })

    it('names the GitHub repository a submodule URL points at', () => {
        expect(githubRepoFromUrl('https://github.com/UAMS-Web/uams-statamic-content.git')).toBe('UAMS-Web/uams-statamic-content')
        expect(githubRepoFromUrl('git@github.com:UAMS-Web/uams-statamic-content.git')).toBe('UAMS-Web/uams-statamic-content')
        expect(githubRepoFromUrl('https://github.com/UAMS-Web/uams-statamic-content')).toBe('UAMS-Web/uams-statamic-content')
        expect(githubRepoFromUrl('file:///tmp/content')).toBeNull()
        expect(githubRepoFromUrl('https://example.com/UAMS-Web/uams-statamic-content.git')).toBeNull()
    })

    it('names an open content pull request to land first, ahead of closed ones', () => {
        const repo = 'UAMS-Web/uams-statamic-content'

        expect(pullRequestLine(repo, { pulls: [
            { number: 70, state: 'closed', url: 'https://github.com/x/70' },
            { number: 71, state: 'open', url: 'https://github.com/x/71' },
        ] })).toBe(`Land first: ${repo}#71 (https://github.com/x/71).`)
        expect(pullRequestLine(repo, { pulls: [{ number: 70, state: 'closed', url: 'u' }] })).toContain('none open')
        expect(pullRequestLine(repo, { pulls: [{ number: 65, state: 'closed', url: 'u', merged: true, merge_commit_sha: '2aecc1f' }] }))
            .toBe(`Already merged as a different commit: ${repo}#65 landed as 2aecc1f. Point \`content\` at that commit.`)
        expect(pullRequestLine(repo, { pulls: [] })).toContain('none found')
        expect(pullRequestLine(repo, { error: 'HTTP 404' })).toContain('could not be resolved (HTTP 404)')
        expect(pullRequestLine(null, null)).toContain('not a GitHub repository')
    })

    it('parses its options and refuses an unknown one', () => {
        expect(parseArgs([])).toEqual({ base: 'origin/main', contentBranch: 'main' })
        expect(parseArgs(['--base', 'origin/dev', '--content-branch', 'trunk'])).toEqual({ base: 'origin/dev', contentBranch: 'trunk' })
        expect(() => parseArgs(['--bogus'])).toThrow('Unknown option')
        expect(() => parseArgs(['--base', '--content-branch'])).toThrow('needs a value')
    })
})
