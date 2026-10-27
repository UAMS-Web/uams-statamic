#!/usr/bin/env node
//
// check-content-pointer.mjs: refuse a commit whose submodule pointers name a
// commit that is not on that submodule's `main` (UAMS-Web/uams-statamic-archive#2965, UAMS-Web/uams-statamic-archive#3115).
//
// A pull request that bumps the pointer can merge while the commit it names
// exists only on a content branch. `main` then points at a commit that resolves
// for whoever has that branch fetched and fails for everyone else, a fresh clone
// and the CI worktree included, and the bump silently undoes itself when the
// content branch is deleted. UAMS-Web/uams-statamic-archive#2863 merged that way, and nothing in the chain
// looked.
//
// Every submodule HEAD's .gitmodules declares is judged, not `content` alone
// (UAMS-Web/uams-statamic-archive#3115): UAMS-Web/uams-statamic-archive#3104 moved `resources/forms` to a companion-branch commit and the job
// passed without looking. That includes the two asset submodules, which the gate
// never checks out: a stranded asset pointer breaks a fresh clone exactly as a
// stranded content one does, and a pointer that did not move costs two `ls-tree`
// reads, so the full clone of an asset repository is paid only by the rare change
// that moves its pointer. The passing output names every submodule judged.
//
// USAGE
//     node scripts/check-content-pointer.mjs [--base <ref>] [--content-branch <name>]
//
//     --base <ref>             The superproject ref the pointer is compared with
//                              (default origin/main). An unchanged pointer passes
//                              without touching the network.
//     --content-branch <name>  The branch every pointer must be on (default main).
//                              The name predates UAMS-Web/uams-statamic-archive#3115; it applies to every submodule.
//
// Run from the superproject's root. Auth for each clone is whatever git is
// configured with: ci.yml and scripts/local-ci.mjs both supply an insteadOf rewrite.
//
// EXIT CODES, each a different answer, because two of them read alike otherwise.
// With several submodules the most specific refusal wins: 3, then 1, then 2.
//     0  pass: every pointer is unchanged from --base, or is on its repository's branch
//     1  a pointer's commit exists but is not an ancestor of its repository's branch
//     3  a pointer's commit is not present in its repository at all
//     2  the check could not run: no submodule declared, no gitlink, an unresolvable
//        base, a failed clone,
//        a submodule URL the change itself rewrites, or any unexpected error
//
// Three properties, each paid for while gating UAMS-Web/uams-statamic-archive#2963:
//
//   - An absent commit is not a failed ancestry test. `merge-base --is-ancestor`
//     exits 128 on an absent commit (git 2.54), not 1, so presence is established
//     first, with `cat-file -e`, and reported as its own answer rather than as an
//     error.
//   - A stale remote-tracking ref under-reports, failing toward "absent from main"
//     when the commit is in fact there. So each repository is a FRESH clone, never
//     a fetch into a submodule that may be behind.
//   - The clone is FULL rather than blob-filtered. A filtered clone is a promisor
//     repository, so `cat-file -e` on a missing commit quietly asks the remote for
//     it, and a commit reachable only from a pull-request ref would read as present.
//     The full bare clone of content measured 2.8 MB and under two seconds on
//     2026-10-08.

import { spawnSync } from 'node:child_process'
import { mkdtempSync, rmSync } from 'node:fs'
import { join } from 'node:path'
import { tmpdir } from 'node:os'
import process from 'node:process'
import { pathToFileURL } from 'node:url'

export const PASS = 0
export const NOT_ON_BRANCH = 1
export const CANNOT_RUN = 2
export const ABSENT = 3

/** The combined exit code's answer when submodules disagree, most specific first. */
const PRECEDENCE = [ABSENT, NOT_ON_BRANCH, CANNOT_RUN, PASS]

/**
 * Parse the command line.
 *
 * @param  {string[]} argv
 * @return {{base: string, contentBranch: string}}
 */
export function parseArgs(argv) {
    const opts = { base: 'origin/main', contentBranch: 'main' }

    for (let i = 0; i < argv.length; i += 1) {
        const arg = argv[i]
        const value = argv[i + 1]

        if ((arg === '--base' || arg === '--content-branch') && (value === undefined || value.startsWith('-'))) {
            throw new Error(`${arg} needs a value.`)
        }

        if (arg === '--base') {
            opts.base = value
            i += 1
        } else if (arg === '--content-branch') {
            opts.contentBranch = value
            i += 1
        } else {
            throw new Error(`Unknown option: ${arg}`)
        }
    }

    return opts
}

/**
 * The `owner/repo` a GitHub clone URL names, or null for any other URL.
 *
 * @param  {string} url
 * @return {string|null}
 */
export function githubRepoFromUrl(url) {
    const match = url.match(/^(?:https:\/\/github\.com\/|git@github\.com:)([^/]+)\/([^/]+?)(?:\.git)?\/?$/)

    return match ? `${match[1]}/${match[2]}` : null
}

/**
 * The gitlink a tree entry line names, or null when the line is not a gitlink.
 *
 * @param  {string} line  One line of `git ls-tree <ref> -- <submodule>`.
 * @return {string|null}
 */
export function gitlinkFromLsTree(line) {
    const match = line.match(/^160000 commit ([0-9a-f]{40,64})\t/)

    return match ? match[1] : null
}

/**
 * Run git and return its status and output. Never throws on a non-zero exit.
 *
 * @param  {string[]} args
 * @param  {string}   cwd
 * @return {{status: number, stdout: string, stderr: string}}
 */
function git(args, cwd) {
    // No terminal prompt: with no credentials a clone fails at once instead of
    // waiting on a TTY nobody is watching.
    const res = spawnSync('git', args, { cwd, encoding: 'utf8', env: { ...process.env, GIT_TERMINAL_PROMPT: '0' } })

    if (res.error) {
        throw res.error
    }

    return { status: res.status ?? 1, stdout: (res.stdout ?? '').trim(), stderr: (res.stderr ?? '').trim() }
}

/**
 * Ask GitHub which pull requests carry a submodule commit.
 *
 * Best effort: a failure is reported as text, never as a check failure, because
 * the refusal stands on the git answer alone.
 *
 * @param  {string} repo  `owner/repo`
 * @param  {string} sha
 * @return {{pulls: Array<{number: number, state: string, url: string, merged?: boolean, merge_commit_sha?: string}>}|{error: string}}
 */
export function lookupPulls(repo, sha) {
    const res = spawnSync('gh', ['api', `repos/${repo}/commits/${sha}/pulls`, '--jq', '[.[] | {number, state, url: .html_url, merged: (.merged_at != null), merge_commit_sha}]'], { encoding: 'utf8' })

    if (res.error) {
        return { error: res.error.code === 'ENOENT' ? 'gh is not installed' : res.error.message }
    }

    if (res.status !== 0) {
        return { error: (res.stderr ?? '').trim() || `gh exited ${res.status}` }
    }

    try {
        return { pulls: JSON.parse(res.stdout) }
    } catch {
        return { error: 'gh returned output that is not JSON' }
    }
}

/**
 * The line naming the submodule pull request to land first.
 *
 * @param  {string|null} repo
 * @param  {{pulls?: Array<{number: number, state: string, url: string}>, error?: string}|null} lookup
 * @param  {string} [submodule]  The submodule path, for the remedy.
 * @return {string}
 */
export function pullRequestLine(repo, lookup, submodule = 'content') {
    if (repo === null) {
        return 'Pull request: not looked up, because the submodule URL is not a GitHub repository.'
    }

    if (lookup.error !== undefined) {
        return `Pull request: could not be resolved (${lookup.error}).`
    }

    const open = lookup.pulls.filter((pull) => pull.state === 'open')

    if (open.length > 0) {
        return `Land first: ${open.map((pull) => `${repo}#${pull.number} (${pull.url})`).join(', ')}.`
    }

    // A squash or rebase merge puts the change on `main` under a different commit,
    // so the remedy is a new pointer, not another merge.
    const merged = lookup.pulls.filter((pull) => pull.merged && pull.merge_commit_sha)

    if (merged.length > 0) {
        return `Already merged as a different commit: ${merged.map((pull) => `${repo}#${pull.number} landed as ${pull.merge_commit_sha}`).join(', ')}. Point \`${submodule}\` at that commit.`
    }

    if (lookup.pulls.length > 0) {
        return `Pull request: none open. Closed ones carrying it: ${lookup.pulls.map((pull) => `${repo}#${pull.number}`).join(', ')}.`
    }

    return `Pull request: none found in ${repo} carrying this commit.`
}

/**
 * The submodule paths HEAD's .gitmodules declares, in file order.
 *
 * @param  {string} cwd  The superproject root.
 * @return {string[]}
 */
export function declaredSubmodules(cwd) {
    const res = git(['config', '--blob', 'HEAD:.gitmodules', '--get-regexp', '^submodule\\..*\\.path$'], cwd)

    if (res.status !== 0) {
        return []
    }

    return res.stdout.split('\n').filter(Boolean).map((line) => line.slice(line.indexOf(' ') + 1))
}

/**
 * The name a revision's .gitmodules gives the submodule at a path. The name keys
 * its `url`, and need not equal the path, so the URL is never read by path.
 *
 * @param  {string} cwd   The superproject root.
 * @param  {string} rev   The revision whose .gitmodules is read.
 * @param  {string} path  The submodule path.
 * @return {string|null}  The name, or null when no entry declares that path.
 */
function submoduleName(cwd, rev, path) {
    const res = git(['config', '--blob', `${rev}:.gitmodules`, '--get-regexp', '^submodule\\..*\\.path$'], cwd)

    if (res.status !== 0) {
        return null
    }

    const entry = res.stdout
        .split('\n')
        .filter(Boolean)
        .map((line) => ({ key: line.slice(0, line.indexOf(' ')), path: line.slice(line.indexOf(' ') + 1) }))
        .find((candidate) => candidate.path === path)

    return entry ? entry.key.slice('submodule.'.length, -'.path'.length) : null
}

/**
 * A submodule's URL as a revision's .gitmodules records it, looked up by name.
 *
 * @param  {string} cwd   The superproject root.
 * @param  {string} rev   The revision whose .gitmodules is read.
 * @param  {string} path  The submodule path.
 * @return {{status: number, stdout: string}}
 */
function submoduleUrl(cwd, rev, path) {
    const name = submoduleName(cwd, rev, path)

    return name === null ? { status: 1, stdout: '' } : git(['config', '--blob', `${rev}:.gitmodules`, '--get', `submodule.${name}.url`], cwd)
}

/**
 * Decide whether the validated commit's submodule pointers may merge.
 *
 * @param  {object}   options
 * @param  {string}   options.cwd             The superproject root.
 * @param  {string}   options.base            The superproject ref to compare with.
 * @param  {string}   options.contentBranch   The branch every pointer must be on.
 * @param  {Function} [options.findPulls]     `(repo, sha) => lookup`, for the pull request line.
 * @param  {string[]} [options.submodules]    The paths to judge; every declared one by default.
 * @return {{code: number, lines: string[]}}
 */
export function checkContentPointer({ cwd, base, contentBranch, findPulls = lookupPulls, submodules = declaredSubmodules(cwd) }) {
    if (submodules.length === 0) {
        return { code: CANNOT_RUN, lines: ["HEAD's .gitmodules declares no submodule, so there is no pointer to check."] }
    }

    const results = submodules.map((submodule) => checkSubmodulePointer({ cwd, base, branch: contentBranch, submodule, findPulls }))
    const code = PRECEDENCE.find((candidate) => results.some((result) => result.code === candidate))

    return { code, lines: results.flatMap((result) => result.lines) }
}

/**
 * Decide whether one submodule's pointer may merge.
 *
 * @param  {object}   options
 * @param  {string}   options.cwd        The superproject root.
 * @param  {string}   options.base       The superproject ref to compare with.
 * @param  {string}   options.branch     The branch the pointer must be on.
 * @param  {string}   options.submodule  The submodule path.
 * @param  {Function} options.findPulls  `(repo, sha) => lookup`, for the pull request line.
 * @return {{code: number, lines: string[]}}
 */
function checkSubmodulePointer({ cwd, base, branch: contentBranch, submodule: SUBMODULE, findPulls }) {
    const head = git(['ls-tree', 'HEAD', '--', SUBMODULE], cwd)
    const pointer = head.status === 0 ? gitlinkFromLsTree(head.stdout) : null

    if (pointer === null) {
        return { code: CANNOT_RUN, lines: [`HEAD carries no \`${SUBMODULE}\` gitlink, so there is no pointer to check.`] }
    }

    if (git(['rev-parse', '--verify', '--quiet', `${base}^{commit}`], cwd).status !== 0) {
        return { code: CANNOT_RUN, lines: [`The base ref \`${base}\` does not resolve. Fetch it and run again.`] }
    }

    const baseTree = git(['ls-tree', base, '--', SUBMODULE], cwd)
    const basePointer = baseTree.status === 0 ? gitlinkFromLsTree(baseTree.stdout) : null

    if (basePointer === pointer) {
        return { code: PASS, lines: [`The \`${SUBMODULE}\` pointer ${pointer} is unchanged from ${base}. No fetch needed.`] }
    }

    const url = submoduleUrl(cwd, 'HEAD', SUBMODULE)

    if (url.status !== 0 || url.stdout === '') {
        return { code: CANNOT_RUN, lines: [`HEAD's .gitmodules names no URL for \`${SUBMODULE}\`.`] }
    }

    // The repository checked is the one the base names. A change that moves the
    // pointer and rewrites the URL in one go could otherwise point the check at a
    // fork whose `main` carries the commit, and pass.
    const baseUrl = submoduleUrl(cwd, base, SUBMODULE)

    if (baseUrl.status === 0 && baseUrl.stdout !== '' && baseUrl.stdout !== url.stdout) {
        return {
            code: CANNOT_RUN,
            lines: [`This change moves the \`${SUBMODULE}\` pointer and also changes its URL, from ${baseUrl.stdout} to ${url.stdout}. Change one at a time.`],
        }
    }

    const scratch = mkdtempSync(join(tmpdir(), 'submodule-pointer-'))
    const clone = join(scratch, 'submodule.git')

    try {
        const cloned = git(['clone', '--bare', '--no-tags', '--quiet', url.stdout, clone], scratch)

        if (cloned.status !== 0) {
            return { code: CANNOT_RUN, lines: [`Could not clone ${url.stdout}, so the pointer was not checked.`, cloned.stderr] }
        }

        // The positive control: a clone with no such branch cannot answer the question.
        if (git(['rev-parse', '--verify', '--quiet', `refs/heads/${contentBranch}^{commit}`], clone).status !== 0) {
            return { code: CANNOT_RUN, lines: [`The \`${SUBMODULE}\` clone has no \`${contentBranch}\` branch to check against.`] }
        }

        const before = `It was ${basePointer ?? 'absent'} on ${base}.`

        const repo = githubRepoFromUrl(url.stdout)

        if (git(['cat-file', '-e', `${pointer}^{commit}`], clone).status !== 0) {
            return {
                code: ABSENT,
                lines: [
                    `The \`${SUBMODULE}\` pointer ${pointer} names a commit that is not in ${url.stdout} at all. ${before}`,
                    'No branch of it contains it: it was never pushed, or its branch was deleted or force-pushed.',
                    pullRequestLine(repo, repo === null ? null : findPulls(repo, pointer), SUBMODULE),
                ],
            }
        }

        const ancestry = git(['merge-base', '--is-ancestor', pointer, `refs/heads/${contentBranch}`], clone)

        if (ancestry.status === 0) {
            return { code: PASS, lines: [`The \`${SUBMODULE}\` pointer ${pointer} is on its repository's \`${contentBranch}\`. ${before}`] }
        }

        if (ancestry.status !== 1) {
            return { code: CANNOT_RUN, lines: [`git merge-base exited ${ancestry.status} for \`${SUBMODULE}\`.`, ancestry.stderr] }
        }

        const refs = git(['for-each-ref', '--contains', pointer, '--format=%(refname:short)', 'refs/heads'], clone).stdout
            .split('\n')
            .filter(Boolean)

        const lookup = repo === null ? null : findPulls(repo, pointer)
        const alreadyMerged = (lookup?.pulls ?? []).some((pull) => pull.merged && pull.merge_commit_sha)

        return {
            code: NOT_ON_BRANCH,
            lines: [
                `The \`${SUBMODULE}\` pointer ${pointer} is not on its repository's \`${contentBranch}\`. ${before}`,
                refs.length > 0
                    ? `Branches of \`${SUBMODULE}\` that contain it: ${refs.join(', ')}.`
                    : `No branch of \`${SUBMODULE}\` contains it.`,
                pullRequestLine(repo, lookup, SUBMODULE),
                ...(alreadyMerged ? [] : [`Merge the \`${SUBMODULE}\` change to \`${contentBranch}\` first, then point \`${SUBMODULE}\` at a commit on it.`]),
            ],
        }
    } finally {
        rmSync(scratch, { recursive: true, force: true })
    }
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
    let opts

    try {
        opts = parseArgs(process.argv.slice(2))
    } catch (error) {
        console.error(error.message)
        process.exit(CANNOT_RUN)
    }

    let code
    let lines

    // An unexpected throw would otherwise exit 1, which is the "not on main" answer.
    try {
        ({ code, lines } = checkContentPointer({ cwd: process.cwd(), ...opts }))
    } catch (error) {
        code = CANNOT_RUN
        lines = [`The check could not run: ${error.message}`]
    }

    const out = code === PASS ? console.log : console.error

    for (const line of lines.filter(Boolean)) {
        out(line)
    }

    process.exit(code)
}
