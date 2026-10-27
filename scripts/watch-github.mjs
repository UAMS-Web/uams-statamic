#!/usr/bin/env node
/**
 * watch-github.mjs — emit one line per pull-request (and, opt-in, issue) lifecycle event
 * across the UAMS Statamic repos.
 *
 * Feeds the `local-ci` skill's PR pipeline: the agent arms this under the harness `Monitor` tool
 * (`persistent: true`), and every stdout line becomes a notification that enqueues work.
 *
 * Events emitted (one per line, tab-free, newest state last):
 *
 *   READY    <repo>#<n>  opened ready   <title>  <url>
 *   DRAFT    <repo>#<n>  opened draft   <title>  <url>
 *   READY    <repo>#<n>  draft → ready  <title>  <url>
 *   DRAFT    <repo>#<n>  ready → draft  <title>  <url>
 *   PUSH     <repo>#<n>  pushed <sha>   <title>  <url>
 *   GONE     <repo>#<n>  merged|closed  <title>  <url>
 *   WATCH-ERROR  <repo>  <n> consecutive poll failures — <message>
 *
 * With `--issues`, issue lifecycle is reported too, on distinct event kinds so a consumer can
 * route them without parsing the subject (a bare `repo#n` cannot tell an issue from a PR):
 *
 *   ISSUE       <repo>#<n>  opened               <title>  [labels]  <url>
 *   ISSUE-GONE  <repo>#<n>  completed|not plan.  <title>  [labels]  <url>
 *
 * Issue watching is **opt-in** rather than always-on because the `local-ci` PR pipeline already
 * consumes this stream; adding event kinds it never asked for would be a silent change to a
 * load-bearing automation.
 *
 * Pull-request watching is symmetrically **opt-out** (`--no-prs`), so each of those two consumers
 * can take only its half of the stream. Without it, running both watchers at once polls the pulls
 * endpoint twice for every repo they share and hands the same READY event to two agents — which is
 * how a pull request gets validated twice, or merged out from under an in-flight run.
 *
 * The first poll seeds state silently (existing open PRs are handled by the skill's queue-seed
 * step, not replayed as "opened" events); pass `--emit-existing` to announce them instead.
 *
 * PRs are tracked by diffing the full open set, which is bounded and small. Issues are NOT — this
 * org carries hundreds of open issues per repo, so paginating them every minute would be wasteful
 * and rate-limit-hostile. Issues instead ride an `updated_at` watermark: each poll asks only for
 * what changed via `since=`, which is one page in the steady state. Note the REST issues endpoint
 * returns pull requests as issues, so anything carrying a `pull_request` key is discarded — without
 * that filter every PR event would double-fire as an issue event.
 *
 * Uses the GitHub REST API (`gh api`) rather than `gh pr list`, which is GraphQL — the two have
 * separate rate-limit buckets, and a long-lived poll should not compete with interactive GraphQL
 * work in other sessions.
 *
 * Usage:
 *   node scripts/watch-github.mjs
 *   node scripts/watch-github.mjs --interval 30 --emit-existing
 *   node scripts/watch-github.mjs --issues --repos UAMS-Web/uams-statamic
 *   node scripts/watch-github.mjs --no-prs --issue-repos UAMS-Web/uams-statamic
 */

import { execFile } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

/**
 * The `gh` invoker every fetch below goes through. main() swaps in whatever it is handed, so a test
 * can observe exactly which REST endpoints a flag combination requests without spawning `gh`.
 *
 * @var callable
 */
let execGh = execFileAsync;

/** Repositories watched by default: the app plus its five sibling content, asset, user and form repos. */
const DEFAULT_REPOS = [
    'UAMS-Web/uams-statamic',
    'UAMS-Web/uams-statamic-content',
    'UAMS-Web/uams-statamic-users',
    'UAMS-Web/uams-statamic-assets-public',
    'UAMS-Web/uams-statamic-assets-private',
    'UAMS-Web/uams-statamic-forms',
];

/** Consecutive per-repo poll failures tolerated before the failure is surfaced as an event. */
const FAILURE_STREAK_BEFORE_ALERT = 3;

/**
 * Parse `--flag value` / `--flag=value` / bare-boolean CLI arguments, and resolve the two feeds the
 * flags describe into the repository lists each will poll.
 *
 * @param  string[]  $argv
 * @return array{interval: number, repos: string[], emitExisting: bool, prs: bool, issues: bool, prRepos: string[], issueRepos: string[], once: bool, gh: string}
 */
export function parseArgs(argv) {
    const options = {
        interval: 60,
        repos: DEFAULT_REPOS,
        emitExisting: false,
        prs: true,
        issues: false,
        issueRepos: null,
        once: false,
        gh: process.env.LOCAL_CI_GH ?? 'gh',
    };

    for (let index = 0; index < argv.length; index += 1) {
        const [flag, inlineValue] = argv[index].split(/=(.*)/s);
        const nextValue = () => inlineValue ?? argv[++index];

        switch (flag) {
            case '--interval':
                options.interval = Math.max(10, Number(nextValue()));
                break;
            case '--repos':
                options.repos = nextValue().split(',').map((repo) => repo.trim()).filter(Boolean);
                break;
            case '--emit-existing':
                options.emitExisting = true;
                break;
            case '--no-prs':
                options.prs = false;
                break;
            case '--issues':
                options.issues = true;
                break;
            case '--issue-repos':
                options.issueRepos = nextValue().split(',').map((repo) => repo.trim()).filter(Boolean);
                options.issues = true;
                break;
            case '--once':
                options.once = true;
                break;
            case '--gh':
                options.gh = nextValue();
                break;
            case '--help':
            case '-h':
                process.stdout.write(HELP);
                process.exit(0);
                break;
            default:
                throw new Error(`Unknown flag: ${flag}`);
        }
    }

    // Resolve each feed to the repositories it will actually poll, here rather than in main(), so
    // the guard below can test the real feeds instead of the flags that imply them. A blank or
    // comma-only list filters to empty and would otherwise slip past a flags-only check.
    options.prRepos = options.prs ? options.repos : [];
    options.issueRepos = options.issues ? (options.issueRepos ?? options.repos) : [];

    // Both feeds empty is not a quiet watcher, it is a silent one — the process would poll nothing
    // forever. Refuse at startup rather than leave someone reading an empty stream.
    if (options.prRepos.length === 0 && options.issueRepos.length === 0) {
        throw new Error(options.prs
            ? 'Nothing to watch: every watched repository list is empty.'
            : '--no-prs leaves nothing to watch: add --issues or --issue-repos.');
    }

    return options;
}

export const HELP = `watch-github.mjs — emit one line per PR (and, with --issues, issue) lifecycle event
across the UAMS Statamic repos.

  --interval <seconds>   Poll interval (default 60, floor 10).
  --repos <a,b,c>        Comma-separated owner/repo list (default: the five UAMS Statamic repos).
  --emit-existing        Announce already-open PRs on the first poll instead of seeding silently.
                         Applies to PRs only — the open-issue set is far too large to replay.
  --issues               Also emit ISSUE / ISSUE-GONE events (opened, closed) per repo.
  --issue-repos <a,b,c>  Watch issues on this list instead of --repos (implies --issues), so PR
                         and issue scope can differ — e.g. PRs across all five, issues on one.
  --no-prs               Stop watching pull requests, for an issues-only watcher that never touches
                         the pulls endpoint. Requires --issues or --issue-repos.
  --once                 Poll a single time and exit (queue seeding, smoke-testing).
  --gh <path>            Path to the gh binary (default: gh, or $LOCAL_CI_GH).
  -h, --help             Show this help.
`;

/** GitHub's maximum REST page size. A page shorter than this is the last one. */
const PAGE_SIZE = 100;

/**
 * Sanity ceiling on the page walk — 10,000 items, far above any repo this watches.
 *
 * The stop condition trusts the server to advance on `page=N`. A proxy that ignores the parameter
 * and re-serves page 1 would otherwise loop forever inside a long-running watcher, growing `items`
 * without ever throwing — so the failure-streak path never fires and no `WATCH-ERROR` is emitted.
 * Given this whole fix exists because a proxy mangled pagination, that is worth bounding.
 */
const MAX_PAGES = 100;

/**
 * Fetch every page of a REST collection endpoint, walking `?page=N` explicitly.
 *
 * `gh api --paginate` follows the `Link: rel="next"` header, which GitHub renders in numeric-ID
 * form (`https://api.github.com/repositories/946195283/issues?page=2`). A Claude Code cloud
 * session's GitHub proxy refuses that shape outright — `Numeric-ID repository paths are not
 * supported through this proxy (HTTP 403)` — so `gh` writes page 1 and exits 1, and the walk
 * dies at the first page boundary (UAMS-Web/uams-statamic-archive#1823). Requesting each page by number keeps every URL in
 * the `repos/{owner}/{repo}/...` form the proxy accepts. This is not a cloud-specific branch:
 * the behavior is identical with or without a proxy.
 *
 * Pages are parsed unfiltered rather than through `--jq`. A filter that drops items — the issues
 * feed discards pull requests — makes a *full* page look short, which would end the walk early
 * and silently truncate. Deciding on the raw page length is what keeps the stop condition honest.
 *
 * @param  string  $gh
 * @param  string  $path   REST path with any query string, minus `per_page`/`page`.
 * @return Promise<object[]>
 */
async function fetchAllPages(gh, path) {
    const separator = path.includes('?') ? '&' : '?';
    const items = [];

    for (let page = 1; page <= MAX_PAGES; page += 1) {
        const { stdout } = await execGh(gh, [
            'api',
            `${path}${separator}per_page=${PAGE_SIZE}&page=${page}`,
        ], { maxBuffer: 16 * 1024 * 1024 });

        const body = stdout.trim();
        const parsed = body === '' ? [] : JSON.parse(body);

        // An object here is an error payload that still exited 0, not a page of results.
        if (! Array.isArray(parsed)) {
            throw new Error(`Expected an array from ${path} page ${page}, got ${typeof parsed}.`);
        }

        items.push(...parsed);

        // A short page is the last page, so a single-page repo costs exactly one request.
        if (parsed.length < PAGE_SIZE) {
            return items;
        }
    }

    // Throwing (rather than returning what we have) keeps a truncated result from passing for a
    // complete one — the whole point of this walk. `pollRepo` counts it as a poll failure.
    throw new Error(`Pagination exceeded ${MAX_PAGES} pages for ${path}; refusing to loop.`);
}

/**
 * Whether an open pull request's head moved between two polls (UAMS-Web/uams-statamic-archive#2338).
 *
 * Pure and exported so the decision is testable without polling: the interesting
 * cases are all about what should NOT fire, and each of them is cheap to state
 * directly and expensive to reproduce against a live repository.
 *
 * Deliberately false when either side has no SHA. A projection recorded before
 * this field existed carries `undefined`, and a poll that could not read one
 * carries `null` — treating either as "changed" would announce a push on the
 * first poll after an upgrade, for every open pull request at once.
 *
 * Also false while the pull request is a draft on either side. A draft is not in
 * the queue, so a push to one has nothing to re-enter; and a push that coincides
 * with a draft flip is reported as the flip, which is the larger fact.
 *
 * @param  {{draft: bool, sha: ?string}}  before
 * @param  {{draft: bool, sha: ?string}}  after
 * @return bool
 */
export function isPush(before, after) {
    if (!before?.sha || !after?.sha) {
        return false;
    }

    if (before.draft || after.draft) {
        return false;
    }

    return before.sha !== after.sha;
}

/**
 * Fetch every open pull request for one repository via the REST API.
 *
 * @param  string  $gh
 * @param  string  $repo
 * @return Promise<Array<{number: number, title: string, draft: bool, url: string, sha: string}>>
 */
async function fetchOpenPulls(gh, repo) {
    const pulls = await fetchAllPages(gh, `repos/${repo}/pulls?state=open`);

    return pulls.map((pull) => ({
        number: pull.number,
        title: pull.title,
        draft: pull.draft,
        url: pull.html_url,
        // UAMS-Web/uams-statamic-archive#2338 — the head SHA is what makes a push visible. Without it the poll
        // returns an identical projection before and after an author pushes a fix,
        // so a pull request handed back red never re-enters the queue and waits on
        // someone saying so out of band.
        sha: pull.head?.sha ?? null,
    }));
}

/**
 * Fetch issues for one repository whose `updated_at` is at or after `since`.
 *
 * The REST issues endpoint returns pull requests as issues, so every payload carrying a
 * `pull_request` key is discarded here — without that filter each PR event would double-fire
 * as an issue event.
 *
 * @param  string  $gh
 * @param  string  $repo
 * @param  string  $since
 * @return Promise<Array<{number: number, title: string, state: string, createdAt: string, closedAt: ?string, stateReason: ?string, labels: string[], url: string}>>
 */
async function fetchRecentIssues(gh, repo, since) {
    const issues = await fetchAllPages(
        gh,
        `repos/${repo}/issues?state=all&sort=updated&direction=desc&since=${since}`,
    );

    return issues
        .filter((issue) => !Object.hasOwn(issue, 'pull_request'))
        .map((issue) => ({
            number: issue.number,
            title: issue.title,
            state: issue.state,
            createdAt: issue.created_at,
            closedAt: issue.closed_at,
            stateReason: issue.state_reason,
            labels: issue.labels.map((label) => label.name),
            url: issue.html_url,
        }));
}

/**
 * Resolve whether a pull request that left the open set was merged or just closed.
 *
 * @param  string  $gh
 * @param  string  $repo
 * @param  number  $number
 * @return Promise<string>
 */
async function resolveClosedState(gh, repo, number) {
    try {
        const { stdout } = await execGh(gh, [
            'api',
            `repos/${repo}/pulls/${number}`,
            '--jq',
            'if .merged then "merged" elif .state == "open" then "still open" else "closed" end',
        ]);

        return stdout.trim() || 'closed';
    } catch {
        return 'closed';
    }
}

/**
 * Emit one event line. Every stdout line becomes a notification, so keep them single-line.
 *
 * @param  string  $kind
 * @param  string  $subject
 * @param  string  $transition
 * @param  string  $detail
 * @return void
 */
function emit(kind, subject, transition, detail) {
    console.log(`${kind}  ${subject}  ${transition}  ${detail}`);
}

/**
 * Short `owner/repo` → `repo` label, so event lines stay readable.
 *
 * @param  string  $repo
 * @return string
 */
function shortRepo(repo) {
    return repo.split('/').pop();
}

/**
 * Classify one issue payload into the lifecycle event it represents, relative to a watermark.
 *
 * Returns null when the issue merely changed for some other reason. The watermark query asks for
 * everything *updated* since the mark, which is a superset of opened + closed — a label edit, a
 * comment, or an assignment all come back and must not be announced as either.
 *
 * An issue opened and closed inside a single interval reports as closed only: the closure is the
 * actionable state, and announcing an "opened" that is already stale helps no consumer.
 *
 * @param  array{state: string, createdAt: string, closedAt: ?string, stateReason: ?string}  $issue
 * @param  string  $since
 * @return ?array{kind: string, transition: string}
 */
export function classifyIssue(issue, since) {
    if (issue.state === 'closed' && issue.closedAt && issue.closedAt >= since) {
        return {
            kind: 'ISSUE-GONE',
            transition: issue.stateReason === 'not_planned' ? 'not planned' : 'completed',
        };
    }

    if (issue.createdAt >= since) {
        return { kind: 'ISSUE', transition: 'opened' };
    }

    return null;
}

// Mutable module state, populated by main(). Declared here so the poll helpers below can close
// over it, but assigned only once main() runs — importing this file (the test does) must not parse
// argv or start polling of its own accord.

/** @var ?object */
let options;

/** @var Map<string, ?Map<number, {draft: bool, title: string, url: string}>> */
let state;

/** @var Map<string, number> */
let failureStreaks;

/** Repositories whose pull requests are watched — `--repos`, or none under `--no-prs`. */
let prRepos = [];

/** Repositories whose issues are watched — `--issue-repos`, else the PR list. */
let issueRepos = [];

/**
 * Per-repo `updated_at` high-water mark. Issues are polled by watermark rather than by diffing
 * the open set, because that set runs to hundreds per repo and paginating it every minute would
 * be wasteful and rate-limit-hostile.
 *
 * @var Map<string, ?string>
 */
let issueWatermarks;

/** @var Map<string, number> */
let issueFailureStreaks;

/**
 * Already-announced `<number>:<kind>` pairs per repo. GitHub's `since` filter is inclusive, so an
 * issue updated exactly on the watermark comes back on the next poll; this keeps that from
 * re-firing as a duplicate notification.
 *
 * @var Map<string, Set<string>>
 */
let issueAnnounced;

let stopping = false;

/**
 * Poll one repository and emit any lifecycle events since the previous poll.
 *
 * A repository whose poll fails keeps its previous state untouched, so a transient API error
 * never manufactures phantom "opened"/"closed" events on the next successful poll.
 *
 * @param  string  $repo
 * @return Promise<void>
 */
async function pollRepo(repo) {
    let pulls;

    try {
        pulls = await fetchOpenPulls(options.gh, repo);
    } catch (error) {
        const streak = failureStreaks.get(repo) + 1;
        failureStreaks.set(repo, streak);

        // Surface a persistently broken watcher — silence would be indistinguishable from calm.
        // A repo that fails its very first poll never seeded at all, so alert immediately rather
        // than waiting out the streak: that is a setup error (auth, typo, access), not a blip.
        const neverSeeded = state.get(repo) === null;

        if (neverSeeded || streak === FAILURE_STREAK_BEFORE_ALERT || streak % 30 === 0) {
            const message = (error.stderr || error.message || '').split('\n')[0].slice(0, 200);
            emit('WATCH-ERROR', shortRepo(repo), `${streak} consecutive poll failures`, message);
        }

        return;
    }

    failureStreaks.set(repo, 0);

    const previous = state.get(repo);
    const current = new Map(pulls.map((pull) => [pull.number, {
        draft: pull.draft,
        title: pull.title,
        url: pull.url,
        sha: pull.sha,
    }]));

    // First successful poll: seed without replaying history unless explicitly asked.
    if (previous === null) {
        state.set(repo, current);

        if (options.emitExisting) {
            for (const [number, pull] of current) {
                emit(
                    pull.draft ? 'DRAFT' : 'READY',
                    `${shortRepo(repo)}#${number}`,
                    pull.draft ? 'already open, draft' : 'already open, ready',
                    `${pull.title}  ${pull.url}`,
                );
            }
        }

        return;
    }

    for (const [number, pull] of current) {
        const before = previous.get(number);
        const subject = `${shortRepo(repo)}#${number}`;
        const detail = `${pull.title}  ${pull.url}`;

        if (before === undefined) {
            emit(pull.draft ? 'DRAFT' : 'READY', subject, pull.draft ? 'opened draft' : 'opened ready', detail);
            continue;
        }

        if (before.draft && !pull.draft) {
            emit('READY', subject, 'draft → ready', detail);
        } else if (!before.draft && pull.draft) {
            emit('DRAFT', subject, 'ready → draft', detail);
        } else if (isPush(before, pull)) {
            // UAMS-Web/uams-statamic-archive#2338 — a push to an already-open, already-ready pull request. This is
            // how a red hand-back comes back: the author fixes and pushes, and until
            // now nothing woke the gate, so re-entry depended on someone saying so.
            //
            // Emitted only when the PR stays ready across the poll. A push that also
            // flips draft state is reported as that flip above, which is the larger
            // fact and already re-enters the queue on its own.
            emit('PUSH', subject, `pushed ${pull.sha.slice(0, 9)}`, detail);
        }
    }

    for (const [number, pull] of previous) {
        if (current.has(number)) {
            continue;
        }

        const disposition = await resolveClosedState(options.gh, repo, number);

        /*
         * The watched set is every open pull request, so a departure normally means the pull
         * request closed. A still-open one that left the listing is therefore not a state change
         * but a gap in it — a page the API served short, or a poll that raced a reopen.
         *
         * Emitting GONE for that would tell the queue to drop work that is still live, so carry
         * the entry into the next poll instead and say nothing. Silence is right here: the pull
         * request never changed state, and re-announcing it as newly opened when the listing
         * recovers would enqueue it a second time.
         */
        if (disposition === 'still open') {
            current.set(number, pull);
            continue;
        }

        emit('GONE', `${shortRepo(repo)}#${number}`, disposition, `${pull.title}  ${pull.url}`);
    }

    state.set(repo, current);
}

/**
 * Poll one repository's issues and emit any opened/closed events since the previous poll.
 *
 * The first poll only sets the watermark: replaying every already-open issue would be hundreds of
 * notifications, so `--emit-existing` is deliberately PR-only.
 *
 * @param  string  $repo
 * @return Promise<void>
 */
async function pollIssues(repo) {
    const previousWatermark = issueWatermarks.get(repo);

    // Take the mark before the request, so anything updated *during* it is caught next round
    // rather than falling into the gap between the query and its response.
    const nextWatermark = new Date().toISOString();

    if (previousWatermark === null) {
        issueWatermarks.set(repo, nextWatermark);

        return;
    }

    let issues;

    try {
        issues = await fetchRecentIssues(options.gh, repo, previousWatermark);
    } catch (error) {
        const streak = issueFailureStreaks.get(repo) + 1;
        issueFailureStreaks.set(repo, streak);

        // Hold the watermark on failure — advancing it would silently skip the window that failed.
        if (streak === FAILURE_STREAK_BEFORE_ALERT || streak % 30 === 0) {
            const message = (error.stderr || error.message || '').split('\n')[0].slice(0, 200);
            emit('WATCH-ERROR', shortRepo(repo), `${streak} consecutive issue-poll failures`, message);
        }

        return;
    }

    issueFailureStreaks.set(repo, 0);

    const announced = issueAnnounced.get(repo);

    for (const issue of issues) {
        const event = classifyIssue(issue, previousWatermark);

        if (event === null) {
            continue;
        }

        const seenKey = `${issue.number}:${event.kind}`;

        if (announced.has(seenKey)) {
            continue;
        }

        announced.add(seenKey);

        const labels = issue.labels.length ? `  [${issue.labels.join(',')}]` : '';
        emit(event.kind, `${shortRepo(repo)}#${issue.number}`, event.transition, `${issue.title}${labels}  ${issue.url}`);
    }

    issueWatermarks.set(repo, nextWatermark);
}

/**
 * Poll every live feed once. The seed pass and each loop iteration both run exactly this, so a feed
 * switched off at startup cannot leak back into the steady state.
 *
 * @return Promise<void>
 */
export async function pollAll() {
    for (const repo of prRepos) {
        await pollRepo(repo);
    }

    for (const repo of issueRepos) {
        await pollIssues(repo);
    }
}

/**
 * `N repo` / `N repos` — the summary line reports a single-repo watcher often enough for `1 repos`
 * to read as a bug in the watcher itself.
 *
 * @param  number  $count
 * @return string
 */
function repoCount(count) {
    return `${count} repo${count === 1 ? '' : 's'}`;
}

/**
 * Compose the startup summary line, naming which feeds are live.
 *
 * Both feeds are named — the off one included — because a watcher whose feed is off is otherwise
 * indistinguishable from one whose repos are simply quiet, and that only becomes apparent after
 * however long it takes to notice the silence.
 *
 * @param  number  $interval
 * @return array{subject: string, transition: string, detail: string}
 */
export function watchingEvent(interval) {
    const feeds = [
        prRepos.length ? `PRs on ${repoCount(prRepos.length)}` : 'PRs off',
        issueRepos.length ? `issues on ${repoCount(issueRepos.length)}` : 'issues off',
    ];

    const detail = [];

    if (prRepos.length) {
        const seedCounts = prRepos.map((repo) => `${shortRepo(repo)}:${state.get(repo)?.size ?? '?'}`);

        detail.push(`open PRs at start — ${seedCounts.join(', ')}`);
    }

    if (issueRepos.length) {
        detail.push(`issues on ${issueRepos.map(shortRepo).join(', ')}`);
    }

    return {
        subject: feeds.join(', '),
        transition: `every ${interval}s`,
        detail: detail.join('; '),
    };
}

/**
 * Parse argv, seed every watched feed, then poll until stopped.
 *
 * @param  string[]  $argv
 * @param  array{exec?: callable}  $dependencies  Overrides the `gh` invoker, for tests.
 * @return Promise<void>
 */
export async function main(argv, dependencies = {}) {
    try {
        options = parseArgs(argv);
    } catch (error) {
        process.stderr.write(`${error.message}\n\n${HELP}`);
        process.exit(2);
    }

    execGh = dependencies.exec ?? execFileAsync;

    prRepos = options.prRepos;
    state = new Map(prRepos.map((repo) => [repo, null]));
    failureStreaks = new Map(prRepos.map((repo) => [repo, 0]));

    issueRepos = options.issueRepos;
    issueWatermarks = new Map(issueRepos.map((repo) => [repo, null]));
    issueFailureStreaks = new Map(issueRepos.map((repo) => [repo, 0]));
    issueAnnounced = new Map(issueRepos.map((repo) => [repo, new Set()]));

    await pollAll();

    if (options.once) {
        return;
    }

    const watching = watchingEvent(options.interval);

    emit('WATCHING', watching.subject, watching.transition, watching.detail);

    while (!stopping) {
        await new Promise((resolve) => setTimeout(resolve, options.interval * 1000));

        await pollAll();
    }
}

// Run only as the entry script, so importing this file for the helpers exported above does not
// parse argv or start polling. process.argv[1] is the entry script; comparing its file URL to
// import.meta.url distinguishes the two cross-platform. argv[1] is absent under `node -e`
// and the REPL, where pathToFileURL would throw on undefined — so presence is checked first.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    // Registered here rather than inside main(), which the test calls repeatedly: process-wide
    // handlers belong to the process that owns the CLI, and one pair per main() call would
    // accumulate until Node warns about a listener leak.
    for (const signal of ['SIGINT', 'SIGTERM']) {
        process.on(signal, () => {
            stopping = true;
            process.exit(0);
        });
    }

    await main(process.argv.slice(2));
}
