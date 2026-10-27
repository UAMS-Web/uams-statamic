import { describe, it, expect, vi } from 'vitest'
import { classifyIssue, HELP, isPush, main, parseArgs, pollAll, watchingEvent } from './watch-github.mjs'

// The issue watcher polls by `updated_at` watermark, so every poll returns a superset of what it
// should announce: anything touched since the mark, including label edits and comments. These
// assert the three outcomes that superset has to collapse to — opened, closed, nothing — and the
// boundary conditions that decide between them.

const SINCE = '2026-07-29T00:00:00Z'

const issue = (overrides) => ({
    state: 'open',
    createdAt: '2026-01-01T00:00:00Z',
    closedAt: null,
    stateReason: null,
    ...overrides,
})

describe('classifyIssue', () => {
    it('announces an issue created after the watermark as opened', () => {
        expect(classifyIssue(issue({ createdAt: '2026-07-30T09:00:00Z' }), SINCE))
            .toEqual({ kind: 'ISSUE', transition: 'opened' })
    })

    it('announces an issue closed after the watermark as gone', () => {
        expect(classifyIssue(issue({ state: 'closed', closedAt: '2026-07-30T09:00:00Z', stateReason: 'completed' }), SINCE))
            .toEqual({ kind: 'ISSUE-GONE', transition: 'completed' })
    })

    it('distinguishes a not-planned closure from a completed one', () => {
        expect(classifyIssue(issue({ state: 'closed', closedAt: '2026-07-30T09:00:00Z', stateReason: 'not_planned' }), SINCE))
            .toEqual({ kind: 'ISSUE-GONE', transition: 'not planned' })
    })

    // The watermark query returns everything *updated*, so an untouched-but-commented issue is the
    // common case. Announcing it would be a false event on every poll it appears in.
    it('ignores an old open issue that merely changed', () => {
        expect(classifyIssue(issue({}), SINCE)).toBeNull()
    })

    // A closure predating the watermark was already announced in an earlier window.
    it('ignores a closure older than the watermark', () => {
        expect(classifyIssue(issue({ state: 'closed', closedAt: '2026-01-02T00:00:00Z', stateReason: 'completed' }), SINCE))
            .toBeNull()
    })

    // Opened and closed inside one interval: the closure is the actionable state, and an "opened"
    // that is already stale helps no consumer.
    it('reports an issue opened and closed within one interval as closed only', () => {
        const sameWindow = issue({
            state: 'closed',
            createdAt: '2026-07-30T09:00:00Z',
            closedAt: '2026-07-30T09:30:00Z',
            stateReason: 'completed',
        })

        expect(classifyIssue(sameWindow, SINCE)).toEqual({ kind: 'ISSUE-GONE', transition: 'completed' })
    })

    // GitHub's `since` filter is inclusive, so an issue sitting exactly on the mark comes back.
    it('treats a timestamp exactly on the watermark as in-window', () => {
        expect(classifyIssue(issue({ createdAt: SINCE }), SINCE))
            .toEqual({ kind: 'ISSUE', transition: 'opened' })
    })

    // A closed issue with no `closed_at` must not fall through to a comparison against null.
    it('does not announce a closed issue that carries no closure timestamp', () => {
        expect(classifyIssue(issue({ state: 'closed', closedAt: null }), SINCE)).toBeNull()
    })
})

describe('parseArgs', () => {
    it('watches pull requests across the six default repos when given no flags', () => {
        const options = parseArgs([])

        expect(options.prs).toBe(true)
        expect(options.issues).toBe(false)
        expect(options.repos).toHaveLength(6)
    })

    it('turns pull-request watching off with --no-prs', () => {
        expect(parseArgs(['--no-prs', '--issues']).prs).toBe(false)
    })

    it('accepts --no-prs alongside --issue-repos, which implies --issues', () => {
        const options = parseArgs(['--no-prs', '--issue-repos', 'UAMS-Web/uams-statamic'])

        expect(options.prs).toBe(false)
        expect(options.issues).toBe(true)
        expect(options.issueRepos).toEqual(['UAMS-Web/uams-statamic'])
    })

    it('resolves each feed to the repositories it will poll', () => {
        expect(parseArgs(['--no-prs', '--issues']).prRepos).toEqual([])
        expect(parseArgs(['--issues']).issueRepos).toHaveLength(6)
        expect(parseArgs([]).issueRepos).toEqual([])
    })

    // Both feeds empty polls nothing at all, which from the outside is indistinguishable from a
    // watcher whose repos are simply quiet.
    it('rejects --no-prs on its own, which would watch nothing', () => {
        expect(() => parseArgs(['--no-prs'])).toThrow(/nothing to watch/)
    })

    // The guard has to test the resolved feeds, not the flags: a blank or comma-only value filters
    // to an empty list while the flag implying it still reads as on.
    it.each([
        [['--repos', '']],
        [['--repos', ',,']],
        [['--no-prs', '--issue-repos', '']],
        [['--repos', '', '--issue-repos', '']],
    ])('rejects %j, whose repository lists all resolve empty', (argv) => {
        expect(() => parseArgs(argv)).toThrow(/nothing to watch/i)
    })
})

/**
 * A stand-in for the `gh` child process that records the REST path of every request handed to it.
 * `--gh` cannot be pointed at a Node script cross-platform, so the invoker is injected instead.
 */
const recordRequests = () => {
    const requests = []

    return {
        requests,
        exec: async (gh, args) => {
            requests.push(args.find((argument) => argument.startsWith('repos/')) ?? args.join(' '))

            // Pages are parsed as raw JSON now, so an empty page is `[]`, not an empty stream.
            return { stdout: '[]' }
        },
    }
}

const matching = (requests, fragment) => requests.filter((request) => request.includes(fragment))

// Whether a feed is really off is a question about the requests the watcher makes, not about the
// flags it parsed — so these drive the seed pass and then `pollAll`, which is verbatim what every
// interval of the steady-state loop runs.
describe('feed selection', () => {
    it('never requests the pulls endpoint when run issues-only', async () => {
        const { requests, exec } = recordRequests()

        await main(['--once', '--no-prs', '--issue-repos', 'UAMS-Web/uams-statamic'], { exec })

        // The seed pass only sets the issue watermark, so a correct issues-only run asks nothing.
        expect(requests).toEqual([])

        requests.length = 0
        await pollAll()

        expect(matching(requests, '/pulls')).toEqual([])
        expect(matching(requests, '/issues')).toHaveLength(1)
    })

    it('keeps polling pull requests across the six default repos when run bare', async () => {
        const { requests, exec } = recordRequests()

        await main(['--once'], { exec })

        expect(matching(requests, '/pulls')).toHaveLength(6)
        expect(matching(requests, '/issues')).toEqual([])

        requests.length = 0
        await pollAll()

        expect(matching(requests, '/pulls')).toHaveLength(6)
        expect(matching(requests, '/issues')).toEqual([])
    })

    it('polls each feed on its own repo list when both are live', async () => {
        const { requests, exec } = recordRequests()

        await main([
            '--once',
            '--repos', 'UAMS-Web/uams-statamic,UAMS-Web/uams-statamic-content',
            '--issue-repos', 'UAMS-Web/uams-statamic',
        ], { exec })

        requests.length = 0
        await pollAll()

        expect(matching(requests, '/pulls')).toHaveLength(2)
        expect(matching(requests, '/issues')).toHaveLength(1)
    })
})

describe('watchingEvent', () => {
    const seed = async (argv) => {
        const { exec } = recordRequests()

        await main(['--once', ...argv], { exec })
    }

    it('reports the bare default as pull requests on six repos, issues off', async () => {
        await seed([])

        expect(watchingEvent(60)).toEqual({
            subject: 'PRs on 6 repos, issues off',
            transition: 'every 60s',
            detail: 'open PRs at start — uams-statamic:0, uams-statamic-content:0, uams-statamic-users:0,'
                + ' uams-statamic-assets-public:0, uams-statamic-assets-private:0, uams-statamic-forms:0',
        })
    })

    it('names an issues-only watcher, with no open-PR count to report', async () => {
        await seed(['--no-prs', '--issue-repos', 'UAMS-Web/uams-statamic'])

        expect(watchingEvent(30)).toEqual({
            subject: 'PRs off, issues on 1 repo',
            transition: 'every 30s',
            detail: 'issues on uams-statamic',
        })
    })

    // The single-repo watcher is common enough that `1 repos` reads as a bug in the watcher itself.
    it('pluralizes a one-repo feed as `repo` and a many-repo feed as `repos`', async () => {
        await seed(['--repos', 'UAMS-Web/uams-statamic,UAMS-Web/uams-statamic-content', '--issue-repos', 'UAMS-Web/uams-statamic'])

        expect(watchingEvent(60).subject).toBe('PRs on 2 repos, issues on 1 repo')
        expect(watchingEvent(60).detail)
            .toBe('open PRs at start — uams-statamic:0, uams-statamic-content:0; issues on uams-statamic')
    })
})

/**
 * A `gh` stand-in that serves canned pages, recording the full REST argument of every request.
 *
 * `pages` maps an endpoint fragment (`/pulls`, `/issues`) to the array of pages to serve for it,
 * so a test can hand back a full page followed by a short one and assert the walk crossed it.
 */
const servePages = (pages) => {
    const requests = []

    return {
        requests,
        exec: async (gh, args) => {
            const path = args.find((argument) => argument.startsWith('repos/')) ?? args.join(' ')
            requests.push(path)

            const fragment = Object.keys(pages).find((key) => path.includes(key))
            const page = Number(/[?&]page=(\d+)/.exec(path)?.[1] ?? 1)

            return { stdout: JSON.stringify(pages[fragment]?.[page - 1] ?? []) }
        },
    }
}

const pullPayload = (number) => ({ number, title: `PR ${number}`, draft: false, html_url: `https://x/${number}` })

const issuePayload = (number, overrides = {}) => ({
    number,
    title: `Issue ${number}`,
    state: 'open',
    created_at: '2026-01-01T00:00:00Z',
    closed_at: null,
    state_reason: null,
    labels: [{ name: 'afk' }],
    html_url: `https://x/${number}`,
    ...overrides,
})

const range = (from, to, build) => Array.from({ length: to - from + 1 }, (_, index) => build(from + index))

// GitHub renders its `Link: rel="next"` in numeric-ID form (`repositories/{id}/...`), which a
// cloud session's GitHub proxy refuses with a 403 — so `gh api --paginate` dies at the first page
// boundary there (UAMS-Web/uams-statamic-archive#1823). These pin the explicit `?page=N` walk that replaced it.
describe('pagination', () => {
    it('walks pages by number, keeping every URL in `repos/{owner}/{repo}/` form', async () => {
        const { requests, exec } = servePages({ '/pulls': [range(1, 100, pullPayload), range(101, 126, pullPayload)] })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec })

        expect(requests).toEqual([
            'repos/UAMS-Web/uams-statamic/pulls?state=open&per_page=100&page=1',
            'repos/UAMS-Web/uams-statamic/pulls?state=open&per_page=100&page=2',
        ])
        expect(requests.every((request) => request.startsWith('repos/'))).toBe(true)
    })

    it('never passes --paginate, whose Link traversal is what the proxy refuses', async () => {
        const seen = []
        const exec = async (gh, args) => {
            seen.push(args)

            return { stdout: '[]' }
        }

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic', '--issues'], { exec })

        expect(seen.flat()).not.toContain('--paginate')
    })

    it('merges every pull request across the page boundary rather than stopping at 100', async () => {
        const { exec } = servePages({ '/pulls': [range(1, 100, pullPayload), range(101, 126, pullPayload)] })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec })

        // The seed pass records the open set it found; the watcher reports its size verbatim.
        expect(watchingEvent(60).detail).toBe('open PRs at start — uams-statamic:126')
    })

    // The trap this replaced: filtering with `--jq` and counting the *filtered* lines makes a full
    // page of pull requests look like a short page of issues, silently ending the walk at page 1.
    it('does not end the issues walk early when a full page is almost entirely pull requests', async () => {
        const firstPage = [
            ...range(1, 99, (number) => issuePayload(number, { pull_request: { url: 'https://x' } })),
            issuePayload(100),
        ]
        const { requests, exec } = servePages({ '/issues': [firstPage, [issuePayload(101)]] })

        await main(['--once', '--no-prs', '--issue-repos', 'UAMS-Web/uams-statamic'], { exec })
        requests.length = 0
        await pollAll()

        expect(matching(requests, '/issues')).toHaveLength(2)
        expect(requests.at(-1)).toContain('page=2')
    })

    it('issues exactly one request when the first page is already short', async () => {
        const { requests, exec } = servePages({ '/pulls': [range(1, 3, pullPayload)] })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec })

        expect(matching(requests, '/pulls')).toHaveLength(1)
    })
})

/**
 * A `gh` stand-in for the pull-request queue.
 *
 * Two request shapes matter here and they return different things. The list call
 * (`repos/{repo}/pulls?state=open`) yields JSON that `fetchOpenPulls` maps; the single-PR call
 * (`repos/{repo}/pulls/{n}`) is `resolveClosedState`, which passes `--jq` and reads the *result*,
 * so it has to come back as a bare string rather than a payload.
 *
 * `openPulls` is a function so a test can serve one set to the seed pass and another to the poll,
 * which is the only way a departure is expressed.
 */
const serveQueue = (openPulls, dispositions = {}) => {
    const emissions = []
    const log = vi.spyOn(console, 'log').mockImplementation((line) => emissions.push(line))

    return {
        emissions,
        restore: () => log.mockRestore(),
        exec: async (gh, args) => {
            const path = args.find((argument) => argument.startsWith('repos/')) ?? args.join(' ')
            const single = /\/pulls\/(\d+)$/.exec(path)

            if (single !== null) {
                return { stdout: dispositions[Number(single[1])] ?? 'closed' }
            }

            return { stdout: JSON.stringify(path.includes('/pulls') ? openPulls() : []) }
        },
    }
}

const assignedPull = (number, assignees) => ({
    number,
    title: `PR ${number}`,
    draft: false,
    html_url: `https://x/${number}`,
    assignees: assignees.map((login) => ({ login })),
})

const kindsFor = (emissions, number) => emissions
    .filter((line) => line.includes(`#${number}`))
    .map((line) => line.split(/\s+/)[0])

// UAMS-Web/uams-statamic-archive#2366 — the watcher queues every open pull request and holds no opinion about who is assigned.
// The filter it used to carry could not do the job it looked like it was doing: every session here
// authenticates as one account, so it partitioned by person rather than by session, while making an
// unassigned pull request invisible and another developer's invisible outright.
describe('assignee independence', () => {
    it('queues an unassigned pull request', async () => {
        const served = serveQueue(() => [assignedPull(1, [])])

        await main(['--once', '--emit-existing', '--repos', 'UAMS-Web/uams-statamic'], { exec: served.exec })

        expect(kindsFor(served.emissions, 1)).toEqual(['READY'])

        served.restore()
    })

    // The three cases together are the claim: assignment does not change what the watcher emits.
    // Asserting only the unassigned case would pass on a filter defaulting to the current account.
    it('queues a pull request assigned to another account identically', async () => {
        const served = serveQueue(() => [
            assignedPull(1, []),
            assignedPull(2, ['someone-else']),
            assignedPull(3, ['dev-one', 'dev-two']),
        ])

        await main(['--once', '--emit-existing', '--repos', 'UAMS-Web/uams-statamic'], { exec: served.exec })

        expect(kindsFor(served.emissions, 1)).toEqual(['READY'])
        expect(kindsFor(served.emissions, 2)).toEqual(['READY'])
        expect(kindsFor(served.emissions, 3)).toEqual(['READY'])

        served.restore()
    })

    // Reassignment used to remove a pull request from the watched set and emit UNASSIGNED. With no
    // filter it cannot leave the set at all, so the correct outcome is no event whatsoever — not a
    // renamed one. A watcher that re-announced it would enqueue live work twice.
    it('emits nothing when a pull request is reassigned', async () => {
        let assignees = ['dev-one']
        const served = serveQueue(() => [assignedPull(7, assignees)], { 7: 'still open' })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec: served.exec })

        assignees = ['someone-else']
        await pollAll()

        expect(kindsFor(served.emissions, 7)).toEqual([])

        served.restore()
    })

    it('rejects --assignee rather than ignoring it', () => {
        expect(() => parseArgs(['--assignee', 'dev-one'])).toThrow(/Unknown flag: --assignee/)
        expect(() => parseArgs(['--assignee=dev-one'])).toThrow(/Unknown flag: --assignee/)
    })

    it('does not offer --assignee in --help', () => {
        expect(HELP).not.toMatch(/assignee/i)
    })
})

// A still-open pull request that vanishes from the listing is a gap in the data rather than a
// state change — a short page, or a poll racing a reopen. Reporting it as GONE would tell the gate
// to drop work that is still live, which is the harm the removed filter's branch guarded against
// and which survives the filter.
describe('a still-open departure', () => {
    it('does not report a still-open pull request as GONE', async () => {
        let present = true
        const served = serveQueue(() => (present ? [assignedPull(11, [])] : []), { 11: 'still open' })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec: served.exec })

        present = false
        await pollAll()

        expect(kindsFor(served.emissions, 11)).toEqual([])

        served.restore()
    })

    // The negative control for the case above: a genuine closure must still be reported, or the
    // guard would be indistinguishable from swallowing every departure.
    it('still reports a merged pull request as GONE', async () => {
        let present = true
        const served = serveQueue(() => (present ? [assignedPull(8, [])] : []), { 8: 'merged' })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec: served.exec })

        present = false
        await pollAll()

        expect(kindsFor(served.emissions, 8)).toEqual(['GONE'])
        expect(served.emissions.some((line) => line.includes('merged'))).toBe(true)

        served.restore()
    })

    // A recovered listing must not re-announce the pull request, which is what carrying the entry
    // forward buys. Without it the next poll reads the return as a newly opened pull request.
    it('does not re-announce the pull request when the listing recovers', async () => {
        let present = true
        const served = serveQueue(() => (present ? [assignedPull(12, [])] : []), { 12: 'still open' })

        await main(['--once', '--repos', 'UAMS-Web/uams-statamic'], { exec: served.exec })

        present = false
        await pollAll()

        present = true
        await pollAll()

        expect(kindsFor(served.emissions, 12)).toEqual([])

        served.restore()
    })
})

// UAMS-Web/uams-statamic-archive#2338 — a push to an open, ready pull request emitted no event, so a PR handed
// back red by the gate never re-entered the queue: re-entry depended on the author
// saying so out of band. Four of one evening's round-trips came back that way.
//
// The predicate is mostly about what must NOT fire. A false positive here wakes a
// gate to re-run a ~19-minute chain against an unchanged tip, which is worse than
// the silence it replaces.
describe('isPush (#2338)', () => {
    const ready = (sha) => ({ draft: false, sha });

    it('fires when the head moves on a pull request that stays ready', () => {
        expect(isPush(ready('aaa1111'), ready('bbb2222'))).toBe(true);
    });

    it('does not fire when the head is unchanged', () => {
        // The common case by far: most polls see the same PR untouched.
        expect(isPush(ready('aaa1111'), ready('aaa1111'))).toBe(false);
    });

    it('does not fire when either side has no SHA', () => {
        // A projection recorded before this field existed carries `undefined`, and a
        // poll that could not read one carries `null`. Treating either as a change
        // would announce a push for every open PR on the first poll after upgrading.
        expect(isPush({ draft: false, sha: undefined }, ready('bbb2222'))).toBe(false);
        expect(isPush(ready('aaa1111'), { draft: false, sha: null })).toBe(false);
        expect(isPush({ draft: false }, { draft: false })).toBe(false);
    });

    it('does not fire while the pull request is a draft on either side', () => {
        // A draft is not in the queue, so a push to one has nothing to re-enter.
        expect(isPush({ draft: true, sha: 'aaa1111' }, { draft: true, sha: 'bbb2222' })).toBe(false);
        expect(isPush({ draft: true, sha: 'aaa1111' }, ready('bbb2222'))).toBe(false);
        expect(isPush(ready('aaa1111'), { draft: true, sha: 'bbb2222' })).toBe(false);
    });

    it('tolerates a missing side rather than throwing', () => {
        // `previous.get()` returns undefined for a PR seen for the first time; that
        // path emits `opened ready` and must not reach a crash here.
        expect(isPush(undefined, ready('bbb2222'))).toBe(false);
        expect(isPush(ready('aaa1111'), undefined)).toBe(false);
    });
});
