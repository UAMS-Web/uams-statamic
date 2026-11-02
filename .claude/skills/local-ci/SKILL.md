---
name: local-ci
description: >-
  Run the project's GitHub Actions CI (.github/workflows/ci.yml) locally against any branch using scripts/local-ci.mjs, a cross-platform Node script that checks the branch out into an isolated git worktree and runs the jobs (pest, pint, phpstan, build, js, encoding) with the local PHP 8.4 / Composer / Node 22 toolchain — no Docker, no act, no GitHub Actions minutes. Invoked bare, it runs the full autonomous PR pipeline: arm scripts/watch-github.mjs to watch the five UAMS Statamic repos for PRs opening ready, flipping draft → ready, or flipping ready → draft; seed the queue with existing open ready PRs; bring each PR into compliance with the writing-pull-requests skill; resolve companion PRs and merge order; then update a branch that is behind, read the required remote checks, comment the verdict green or red, and merge on green one PR at a time, handing back anything else. Given a branch, it just validates that branch. Activate when the user runs "/local-ci", is out of GitHub Actions budget, wants to validate a PR/branch before pushing, asks to "run CI locally", "watch for PRs and merge them", "check this branch without using Actions minutes", or "test a different branch without switching off my current one". The worktree keeps the active checkout untouched.
---

# Run CI locally (worktree, no Docker)

## Two invocation modes

| Invocation | Mode | What happens |
|---|---|---|
| `/local-ci` (bare), or "watch for PRs and merge them" | **Autonomous PR pipeline** | Arm the watcher, seed the queue from existing open PRs, then review → update → read the required checks → comment → merge each PR one at a time, indefinitely. |
| `/local-ci <branch>`, "run CI on this branch", "validate before pushing" | **One-off validation** | Run `scripts/local-ci.mjs` against the named branch and report. No queue, no merging. |

Mode A is the rest of this section; everything from *Turning remote CI off* down is the underlying runner both modes share.

## Mode A — the autonomous PR pipeline


**Standing authorization.** Bare `/local-ci` is a standing instruction to *merge on green* without asking again per PR — that is the point of the mode. *Green* means the required remote checks, read as section 4 says; a green local run is not it. The authorization does not extend to force-pushing over a branch you did not author, closing PRs, or merging a PR whose required checks are red, skipped or missing.

**Claim a slot.** The pipeline is long-lived and edits branches to remedy failures, so it collides with any other session on the primary checkout. Claim a free slot first — `node scripts/worktree-slots.mjs status`, then work in `../uams-statamic-a` or `-b` — per the [`worktrees`](../../rules/worktrees.md) rule; only take an ephemeral worktree when both slots are held. The runner builds its *own* sibling worktree for each validation regardless — that is separate and unaffected, and since UAMS-Web/uams-statamic-archive#1953 it resolves to a sibling of the **primary** no matter which tree you drive it from.

### 1. Arm the watcher

[`scripts/watch-github.mjs`](../../../scripts/watch-github.mjs) polls the six repos (the app and its five siblings, `uams-statamic-forms` included since UAMS-Web/uams-statamic-archive#2818) over the GitHub **REST** API (a separate rate-limit bucket from the GraphQL one `gh pr list` uses, so a session-length poll never competes with interactive work) and prints one line per lifecycle event. Arm it under the harness `Monitor` tool with `persistent: true`, so each event arrives as a notification:

```bash
node scripts/watch-github.mjs --interval 60
```

Watched repos: `UAMS-Web/uams-statamic`, `uams-statamic-content`, `uams-statamic-users`, `uams-statamic-assets-public`, `uams-statamic-assets-private`.

| Event line | Meaning | Queue effect |
|---|---|---|
| `READY <repo>#<n> opened ready` | PR opened non-draft | **Enqueue** |
| `DRAFT <repo>#<n> opened draft` | PR opened as a draft | Note only — drafts never enter the queue |
| `READY <repo>#<n> draft → ready` | Author flipped it ready | **Enqueue** |
| `DRAFT <repo>#<n> ready → draft` | Author pulled it back | **Dequeue** — and if it is the in-flight PR, abandon the run and do **not** merge |
| `PUSH <repo>#<n> pushed <sha>` | An open, ready PR's head moved | **Enqueue (re-enter)** — this is how a red hand-back comes back |
| `GONE <repo>#<n> merged\|closed` | Left the open set | Dequeue (someone else resolved it) |
| `WATCH-ERROR <repo> …` | Poll failing (auth, access, typo) | Fix the watcher — a silent watcher looks exactly like a quiet queue |

The first poll seeds silently. `--once --emit-existing` does a one-shot dump of what is currently open (queue seeding, smoke-testing); `--once` alone exits after a silent seed.

**`PUSH` fires only when the pull request is ready on both sides of a poll and its head SHA changed** (`isPush()` in [`scripts/watch-github.mjs`](../../../scripts/watch-github.mjs), UAMS-Web/uams-statamic-archive#2338). So a push to a **draft** does not enqueue it — drafts never enter the queue — and a push that leaves the head where it was, such as a force-push to the same SHA or a push to another branch, emits nothing. A push that also flips the draft state is reported as that `READY` or `DRAFT` flip instead, which is the larger fact. **Several pushes before the gate picks the pull request up queue one run of the latest head, not one per push:** the queue holds one entry per pull request, and the run validates whatever head is current when it starts. Record that SHA in the verdict comment, per *Do not re-pick an unmoved head* below.

### 2. Seed the queue from what is already open

Existing open **ready** PRs are processed **first**, ahead of anything the watcher reports afterward — unless a merge-order instruction says otherwise, which always wins over arrival order.

```bash
node scripts/watch-github.mjs --once --emit-existing
```

### 3. Bring the PR into compliance before validating it

For each PR entering the queue, review it against the [`writing-pull-requests`](../writing-pull-requests/SKILL.md) skill and **fix what does not comply** — `gh pr edit <n> --repo <repo> --title '…'` and `--body-file <path>` (never inline `--body`; these bodies are dense with backticks and `$` that the shell mangles). Check the title (imperative verb-first, no Conventional-Commit prefix, no internal-process references, acronym casing, inline-code handles) and the body (leading `Closes #N.`, the conventional section inventory, `-` bullets with em-dash separators, absolute-branch-URL file links, a mandatory `## Test plan` task list).

Then read the body for **companion PRs and merge order** — the `## Companion PRs` section and any explicit "Merge order" block. Treat a cross-repo companion set as **one queue unit**, ordered internally as the bodies specify; never merge half a pair. If a body's stated ordering rests on a premise you can check, **check it** — a body written from memory can assert an ordering step the repo's actual merge settings make unnecessary or wrong, and shipping a wrong instruction is worse than shipping none.

### 4. Update the branch, then read the required checks

**A green local run does not make a pull request mergeable.** `main` requires `CI`, `Attribution footer` and `Closing keywords` (#337), and the decision reads those three on the head that will land. A ready pull request merges when all three are green and every test-plan item is satisfied, subject to the [`pre-merge-check`](../../rules/pre-merge-check.md) rule, which owns the rest of the decision. Anything else is handed back (section 6).

**First, update a branch that is behind `main`:**

```bash
gh api repos/{o}/{r}/compare/main...<head-sha> --jq '.behind_by'
gh api -X PUT repos/{o}/{r}/pulls/<n>/update-branch -f expected_head_sha=<head-sha>
```

A non-zero `behind_by` means update. `expected_head_sha` refuses the update if the head has moved since you read it, so a push is never overwritten. The call answers before the merge exists: read the head again, and treat every check from before the update as saying nothing about the new head. A conflict is refused, and that is a hand-back, since resolving it is the author's.

**Then read the three checks on that head:**

```bash
gh api repos/{o}/{r}/commits/<head-sha>/check-runs'?per_page=100' --jq '.total_count, (.check_runs | length), (.check_runs[] | select(.name == "CI" or .name == "Attribution footer" or .name == "Closing keywords") | [.name, .status, .conclusion] | @tsv)'
```

- **Green is all three `completed` and `success`.** A name that is missing is not green, and neither is `skipped` or `neutral`: GitHub counts those two as satisfying a required check, so the merge would go through on nothing having run.
- **Compare the first two numbers.** The job count grows with the matrix, so a list shorter than `total_count` has been cut off and a missing name proves nothing until the next page is read.
- **A name can appear twice on one head.** A body edit runs `Attribution footer` and `Closing keywords` again, and the older result stays listed, so read the newest by `completed_at`.
- **A run still in progress is a wait, never a hand-back.** Since #348 that wait is about an hour, for the Windows band.

**The sibling repositories have no CI workflow of their own**, so a standalone sibling pull request has no required chain to read. Say so plainly in the verdict rather than implying a suite ran; validate what is actually checkable (the diff is what it claims, YAML parses, a pure rename is a pure rename) and merge on that basis. **An app pull request with a content companion** is read on the app branch: its `pest` jobs check `content/` out at the branch's gitlink, which is fetch-reachable before the companion merges, so one green app run validates the pair.

### 5. Comment the verdict, then merge on green

**Comment the verdict on the pull request before any merge decision, green or red.** The wording below is shared with the sibling repositories that run the same gate ([`uamswp-migration-api#153`](https://github.com/UAMS-Web/uamswp-migration-api/issues/153), [`wordpress-importer#999`](https://github.com/UAMS-Web/wordpress-importer/issues/999)) and is adopted rather than paraphrased, so three procedures for one obligation do not drift apart:

> Comment the verdict on the pull request BEFORE any merge decision, GREEN OR RED.
>
> Name the jobs that ACTUALLY RAN. A run that skipped jobs and a run that passed them are indistinguishable in a bare "green" — and where remote CI is disabled, skipped jobs are the normal appearance rather than a fault.
>
> On a RED the comment IS the hand-back record: name the failing job and the output. Do not flip the pull request to draft — the gate annotates, it does not mutate. Do not re-pick an unmoved head.
>
> A verdict that exists only outside the repository is not recorded.

Three of those clauses carry more than they look like, and each is here because the reassuring reading is the wrong one:

- **Name the jobs that actually ran.** *Skipped is not passed* — the same distinction the standing-authorization paragraph above draws when it withholds merge authority from a validation that is "red **or skipped**". A bare "green" cannot express it, and with remote CI off there is no second surface where a reader could notice.
- **Do not flip the pull request to draft.** The gate annotates; it does not mutate the pull request's state. Flipping one forges the author's own signal — in the watcher's event table a ready → draft transition means *the author pulled it back*, which dequeues the PR and abandons an in-flight run. It would also fail quietly if attempted through the allow-listed path: `PATCH /repos/{o}/{r}/pulls/{n}` **silently ignores a `draft` field** and returns `200` with the value unchanged, so the gate would report a state change that never happened.
- **Do not re-pick an unmoved head.** A red hand-back ends the gate's turn on that PR. Re-validating a head that has not moved re-runs the same failure and holds a slot; the trigger for re-entry is a new commit, which the watcher reports as `PUSH`. **That is the only way a red hand-back comes back:** the gate does not flip it to draft, so the pull request stays ready throughout and no `READY` transition will ever re-enqueue it. Record the head SHA you validated in the comment, so "has it moved?" is answered against a written baseline rather than against memory.

**Post it with [`scripts/post-verdict.mjs`](../../../scripts/post-verdict.mjs), and on a hand-back pass `--hand-back`** (UAMS-Web/uams-statamic-archive#2845). One command posts the comment and, on a hand-back, applies `handed-back` in the same run, then reads the labels back and exits `1` unless they agree:

```bash
node scripts/post-verdict.mjs <n> verdict.md --hand-back   # a red chain, or a finding a review caught on a green one
node scripts/post-verdict.mjs <n> verdict.md               # a green with no finding: the labels are never touched
```

It exists because the label used to be a second call, and that call was the one missed: six of eight hand-backs read from their label events carried no `handed-back`. On a failure it says whether the comment posted, so only the label is retried. It calls REST only, so a spent GraphQL quota does not stop a verdict being recorded. [`.claude/settings.json`](../../settings.json) allow-lists `node scripts/post-verdict.mjs`, so the gate runs it without a prompt. A green that finds `handed-back` still on prints a warning, because a push failed to clear it. Pass `--repo <owner/name>` for a companion repository.

Since UAMS-Web/uams-statamic-archive#2339 the runner can also post a machine-readable `local-ci` commit status on the head (`--commit-status`). That is an addition, not a substitute: a status is a badge, and the comment is the record a person reads.

### The `handed-back` label

**The gate applies it, a push removes it, and nobody else touches it.**

- **Apply it on a hand-back**: a red chain, or a finding a review caught on a green one. It is not a step of its own: `--hand-back` on the verdict command in section 5 applies it in the same run that posts the comment, and fails if it is not there when read back.
- **A push removes it**, because a push is the pull request re-entering the queue — not the author announcing readiness. [`.github/workflows/clear-handed-back.yml`](../../../.github/workflows/clear-handed-back.yml) does the removal on the push itself (UAMS-Web/uams-statamic-archive#2667), so the gate no longer removes it by hand. It used to, and the removal got missed: UAMS-Web/uams-statamic-archive#2619 and UAMS-Web/uams-statamic-archive#2662 kept the label after their authors pushed, and dropped out of the queue depth while they waited. The author never touches it either. Its presence does not block a push, and its absence means *not currently handed back*, never *gated and clean*. An author who thinks a pull request is wrongly marked says so to the gate rather than clearing it.
- **Queue depth is `open AND not labelled handed-back`** — one query, with no separate hand-back list to remember.

**The workflow removes it with `DELETE`, which the gate cannot.** `DELETE /repos/{o}/{r}/issues/{n}/labels/handed-back` removes that one label and leaves the rest alone. This repository's [`.claude/settings.json`](../../settings.json) denies `gh api -X DELETE` and `gh api --method DELETE`, so that call is refused for the gate session; the workflow runs under its own `GITHUB_TOKEN`, which that deny rule never sees. It triggers on `pull_request` `synchronize` only, so opening a pull request, applying the label and a comment cannot remove it, and a push to a pull request without the label is skipped before a runner starts. It is not gated on `RUN_REMOTE_CI`: that switch is off for months at a time, and gating this on it would bring the missed removal back for exactly that long.

**When a push did not clear it, the run is what to read.** The pull request's checks list a `Clear handed-back` run for every push to a labeled pull request. A failed run is a defect in the workflow, so ticket it — including a billing failure, which is how an exhausted Actions allowance shows up here rather than as `skipped`. A missing run means the push event never fired, and one case of that is expected rather than a defect: **a push that leaves the pull request conflicted runs no workflow at all.** GitHub runs `pull_request` workflows against the merge commit, and a pull request it cannot merge has none, so the label stays until the push that resolves the conflict, which fires the run and removes it. Measured on UAMS-Web/uams-statamic-archive#2675 with scratch pull requests against a scratch base, 2026-09-30: a push that kept a pull request conflicted produced no run of any workflow within three minutes; the push that resolved it removed the label; a clean control removed it too. So a hand-back whose branch has fallen behind `main` comes back when the author syncs, not on an earlier push, and that is the right outcome, since a conflicted pull request cannot pass the gate. For any other missing run, until it is fixed, the removal that works from a gate session is a `PATCH /repos/{o}/{r}/issues/{n}` with a `labels` array — and it **replaces the whole set**, so any label left out of the array is removed with no warning. Build the array from what is there now, then read it back:

```bash
keep=$(gh api repos/UAMS-Web/uams-statamic/issues/<n> --jq '{labels: [.labels[].name | select(. != "handed-back")]}')
gh api -X PATCH repos/UAMS-Web/uams-statamic/issues/<n> --input - <<< "$keep"
gh api repos/UAMS-Web/uams-statamic/issues/<n> --jq '[.labels[].name]'   # read back: everything but handed-back
```

**Why a label and not a draft flip:** the gate annotates and does not mutate a pull request's state (see above), and the label clears on the event that actually happened — new code arriving — rather than on the author flipping back to ready, which is how a draft marker would clear whether or not the finding was addressed.

**On a green, merge after the comment. The merge method is not uniform across the repos** — check before merging:

| Repo | Allowed | Use | Why it matters |
|---|---|---|---|
| `uams-statamic` | squash only | `gh pr merge <n> --squash` | Squash is the only method the repo's merge settings permit. |
| `uams-statamic-content` | merge commit only | `gh pr merge <n> --merge` | A **merge commit keeps the branch tip an ancestor of `main`**, so an app PR's `content/` gitlink pointing at that tip stays valid with no re-bump. The repo's merge settings enforce it: squash and rebase merging were disabled on 2026-10-09 (UAMS-Web/uams-statamic-archive#2985), so a `--squash` or `merge_method=squash` copied from app-repo practice is no longer a method the repository accepts. A content commit squashed before that date orphans any gitlink pointing at its branch tip and forces a pointer bump plus a full re-validation. |
| `uams-statamic-users`, `-assets-public`, `-assets-private`, `-forms` | merge commit only | `gh pr merge <n> --merge` | Same reasoning wherever a superproject pins a commit; squash and rebase were disabled on these on the same date. |

**That table fixes the merge *method*, not the command path.** `gh pr merge <n> --<method>` and the REST `gh api -X PUT repos/{o}/{r}/pulls/{n}/merge -f merge_method=<method>` both merge the pull request that `main`'s rulesets require. What differs is whether the call runs without an approval prompt: the repo's [`.claude/settings.json`](../../settings.json) allow-lists the REST shape and carries **no** `gh pr` entry, so REST is the portable path and the one [`github-api-budget`](../../rules/github-api-budget.md) prefers. A developer's own `~/.claude/settings.json` may allow-list `Bash(gh pr *)`, but that is machine-specific and a procedure must not rest on it. **`--admin` is not a third path, and is not part of this flow**: it grants no server-side power, and the pull-request ruleset on `main` has no bypass list (UAMS-Web/uams-statamic-archive#2016), so there is nothing for it to bypass.

For a companion pair: merge the companion first, **verify the app's gitlink is now reachable from the companion repo's `main`** (`gh api repos/<repo>/compare/main...<gitlink-sha>` → `status` of `identical` or `behind` means it is an ancestor), then merge the app PR. Verify — do not assume the merge did what you expected.

After each merge, refresh the primary checkout's `main` (`git -C <primary> pull --ff-only origin main`); a stray `M content` submodule drift there is expected and harmless.

### 6. Red → comment and hand back

**The verdict comment comes first** — section 5's obligation is not conditional on the outcome, and a red is the case where the comment is the only record the author gets. Name the failing job and quote its output, and post it with `scripts/post-verdict.mjs <n> verdict.md --hand-back` so the label lands with it. Then carry on below.

A red ends the gate's turn on that pull request. The remedy is the author's and arrives as a push, which the watcher reports as `PUSH`; the checks then run on the new head and the pull request is read again. Never merge past a red, and never re-pick an unmoved head.

Then take the next queue item. The pipeline runs one PR at a time, start to finish, and only then moves on.

### What a local run is still for

**A local run gates nothing.** It is how an author checks a branch before spending an hour of remote CI, how a red is reproduced on a machine someone can inspect, and how a job is run with nothing billed. Its verdict is evidence for the person running it, and the merge decision never reads it. The `/local-ci <branch>` mode is this and nothing more.

Before a run whose result you will quote, bring the branch current with `origin/main` per the [`sync-pr-branch`](../../rules/sync-pr-branch.md) rule. When re-validating a branch **after pushing a remedy**, pass `--fetch` so `origin/<branch>` is current — the runner then syncs the branch to it and prints the commit it validated (UAMS-Web/uams-statamic-archive#1746). `--fresh` does **not** address a stale commit; see *Reused-worktree hazards* below.

**Report progress while it runs.** A full run is ~20 minutes, most of it in `pest`, and the runner publishes no machine-readable progress. Redirect it to a log and arm [`scripts/local-ci-progress.mjs`](../../../scripts/local-ci-progress.mjs) under `Monitor` alongside it:

```bash
node scripts/local-ci.mjs --branch <branch> --jobs <jobs> > ci.log 2>&1 &
node scripts/local-ci-progress.mjs --log ci.log --worktree ../uams-statamic-ci --interval 600
```

It reports the current stage, and for `pest` the **sub-phase** — that job runs six (route cache, Stache seed, parallel suite, serial tail, then a skip-baseline check per band) whose progress signals differ. The parallel suite is paratest, which streams dots and **no** `PASS` lines; the serial tail is plain Pest, which prints one `PASS <TestClass>` line per file. Counting `PASS` lines against the whole suite is therefore wrong in a way that reads plausibly: a run whose parallel phase has already *finished* reports as barely started. The reporter counts against the serial group only (derived from the worktree, so it tracks the suite as it grows) and says which sub-phase the number belongs to. On a finished run it reports duration as birth→last-write, not "time since start" — the latter keeps climbing after the run ends and silently overstates. Idle ticks are throttled to `--idle-interval` (default hourly) so a drained queue stays quiet.

**Health-check the run on a fixed cadence: T+5, T+10, T+20, then every 20 minutes.** The reporter above tells you what stage a *healthy* run is in; it cannot tell you the run died, and neither can the absence of a notification. Check explicitly at those marks, and confirm three separate things:

1. **A real run is alive** — `pgrep -f 'local-ci\.mjs --branch'`. Match the **invocation**, never a substring. `pgrep -f 'local-ci'` also matches `local-ci-progress.mjs`, every `until grep … ci.log` waiter, and the shell issuing the query — the same self-matching trap [`long-running-commands`](../../rules/long-running-commands.md) documents for `pkill`. Seven such matches once convinced a session that a run which had finished two hours earlier was still going, and both its status checks and its waiter agreed.
2. **The log is advancing** — compare size or last-write time against the previous check. A live PID with a static log is a hang, not progress; a finished run's log stops growing while stray matches keep the PID check green.
3. **It did not die at startup** — a zero-byte log a minute in means the run never began. Usual causes: the branch is already checked out elsewhere (`git worktree add` refuses), a conflicted merge was left in the tree, or an entry-guard no-op (invoking a runner copy through a symlinked path exits 0 having done nothing).

The cadence is chosen against the shape of a run: **T+5** catches a startup fault before it burns the window, **T+10** and **T+20** bracket the `build` → `phpstan` stretch, and the 20-minute repeat covers `pest`, which is most of the wall clock. Report the failure the moment a check fails rather than waiting for the next one.

**Reap a run's waiter when that run ends, not at session end.** A stranded `until … done` waiter polls a log that will never change again. They accumulate silently — four survived from UAMS-Web/uams-statamic-archive#1730, UAMS-Web/uams-statamic-archive#1714, UAMS-Web/uams-statamic-archive#1829 and UAMS-Web/uams-statamic-archive#1848 in a single session, which is precisely what made the broad pattern useless. `kill` by explicit PID once the run is reported, and re-check that zero remain.


**When a local run fails, three classes are not app-code bugs** and must not be "fixed" by editing app code:

- **`git worktree add failed (exit 128)` — the branch is already checked out elsewhere.** Git refuses to check one branch out into two worktrees, and it fails in seconds, before any job runs. Find which tree holds it, then pick the remedy by **what kind of tree that is** — the two are not interchangeable:

  ```bash
  git worktree list                       # which tree is on <branch>
  ```

  - **A slot** (`../uams-statamic-a`, `-b`) — the normal case. The author was supposed to release it as their last act on the ticket, and a forgotten release is the ordinary way this happens; the slot is not litter. **Release it, never delete it:** `node scripts/worktree-slots.mjs release <slot>`, which asserts a clean tree, detaches to `origin/main`, and preserves the branch. Deleting one throws away a full bootstrap — `public/assets` alone is a genuine 170 MB clone per slot — and the [`worktrees`](../../rules/worktrees.md) rule is explicit that a slot is normally never removed.
  - **An ephemeral worktree** under `.claude/worktrees/` — still removable, and now the rarer case: the rule keeps it for three named situations (both slots held, a genuinely file-disjoint fan-out, and subagent isolation). Confirm it is disposable first — `git -C <wt> log --oneline -1` matches `origin/<branch>`, `git -C <wt> status --short` is empty, and `git -C <wt> log origin/<branch>..HEAD` is empty — then `git worktree remove --force <wt>`, falling back to `rm -rf <wt> && git worktree prune` only when an OS handle or untracked scratch blocks it. Both preserve the branch.

  **Do not read a clean tree as permission to delete.** Those three disposability checks pass on a held slot too — it is on the right commit, clean, and fully pushed — so they distinguish *safe to release* from *unsafe*, never *slot* from *ephemeral*. `git worktree list` is what tells you which you have.

- **Stale `content/` pointer** — the companion content has not merged and the branch's gitlink predates it. See *Stale-submodule hazard* below for the signature and the confirmation procedure.
- **Stale-commit validation** — the runner tested an older commit than the PR head. Confirm with `git -C <worktree> rev-parse HEAD` against the PR head before believing any result you are about to merge on.


## Turning remote CI off when the budget is out (`RUN_REMOTE_CI`)

GitHub Actions minutes are billed at the **organization** level — one pool shared by every `UAMS-Web` repo, and only **private** repos consume it (public-repo minutes are free). When that budget is exhausted, every `ci.yml` job fails instantly on a billing error.

To stop burning the queue until the monthly reset, set an Actions **variable** `RUN_REMOTE_CI` to `false`. Every job in [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml) is gated on `if: vars.RUN_REMOTE_CI != 'false'` except `submodule-access`, which runs only when the workflow is started by hand (UAMS-Web/uams-statamic-archive#2497), and the aggregate `CI` job, so the jobs report `skipped` and consume zero runner minutes. Unset (or any value other than `false`) leaves CI fully on — the default is always-on (UAMS-Web/uams-statamic-archive#980).

- **Where to set it:** `Settings → Secrets and variables → Actions → Variables`. Define it as an **organization** variable scoped to **Private repositories** (matches where the budget actually applies, and auto-covers new private repos). A **repository** variable overrides the org value for a single repo.
- **Effect is per-workflow:** an org `RUN_REMOTE_CI=false` only disables a repo whose workflow has the `if:` gate. As of UAMS-Web/uams-statamic-archive#980 that is this repo; other repos honor it once they adopt the same gate.
- **Merging while off:** nothing merges. A ruleset on `main` requires `CI`, `Attribution footer` and `Closing keywords` (#337), and `CI` carries no gate and fails on a skipped job, so with the switch off every merge is held rather than waved through. [`docs/runbooks/branch-protection.md`](../../../docs/runbooks/branch-protection.md) records what `main` enforces.
- **Validation while off:** `scripts/local-ci.mjs` still validates a branch (no Actions minutes), and it ignores `RUN_REMOTE_CI`, but a green local run cannot satisfy `CI`.
- **Turning CI back on:** delete the variable or set it to anything but `false`.

## Prerequisites

The runner is a plain Node script, so the only hard requirement beyond the project toolchain is Node — which the project already depends on. There is **no PowerShell dependency**.

- **Node 22** — already required by the project; provides the `node`/`npm`/`npx` the script and the `build`/`encoding` jobs use.
- **npm >= 11.10.0** — a HARD floor, not a preference. `.npmrc` sets `engine-strict=true` against `engines.npm`, so `npm ci` refuses anything older, and the runner asserts it up front rather than letting you hit an opaque `EBADENGINE`. **Node 22 bundles npm 10.9.x, which is below the floor**, so following the Node-22 line alone is not enough: run `npm install -g npm@^11.10.0` once per machine (CI pins it per job; a local machine does not self-upgrade). Only the `build`, `js`, `spell`, and `browser` jobs need it. See UAMS-Web/uams-statamic-archive#1826.
- **PHP 8.4 + Composer** — via [Laravel Herd](https://herd.laravel.com) on macOS, or the Herd/Windows toolchain on Windows. On macOS the script prefers Herd's version-pinned `php84` binary, so it targets 8.4 even when your default Herd PHP is another version. Verified on **macOS 26.4** (UAMS-Web/uams-statamic-archive#1903), where a run reports `PHP: …/Herd/bin/php84 (8.4.23)`; the pin is load-bearing rather than decorative, because that Herd install carries `php74` through `php85` side by side.
- **A coverage driver — PCOV (recommended) or Xdebug** — a HARD floor for the `mutate` job, added with UAMS-Web/uams-statamic-archive#1731. `pest --mutate` needs one: with no driver it throws `Mutation testing requires code coverage to be enabled` and exits `1`, which is loud and self-explaining. The dangerous shape is the *other* one — `0 Mutations for 0 Files created` with exit `0`, which is what a driver-present-but-tooling-broken run produced in UAMS-Web/uams-statamic-archive#1728 (a `php-code-coverage` format change, still patched in `composer.json`). The job asserts the mutation count precisely because that shape is silent. There is deliberately **no "skip when no driver" fallback** — a skip would make the job a false green about false greens. Herd ships only Xdebug (`herd coverage`); installing PCOV is a per-machine step that does **not** travel via git. See the [`pcov-setup`](../pcov-setup/SKILL.md) skill, which also documents the run gotchas (relative `--path`, worktree scoping, the Windows env-block limit). Only the `mutate` job needs it.
- **Python 3** — for the `encoding` job, which runs the release-notes generator's own suite, `.claude/skills/writing-release-notes/gen_release_notes.test.py` (UAMS-Web/uams-statamic-archive#2850). The runner tries `python3`, then `python`, and accepts only one that reports major version 3. Only the `encoding` job needs it.
- **The Composer `github-oauth` PAT** in your global Composer auth (already configured) — needed for the private packages and the `content/` and `users/` submodule clones.

The script discovers each tool automatically and fails fast with an actionable message if one is missing or the wrong version (a version mismatch warns; an absent tool errors). Override any binary with an env var holding an absolute path: `LOCAL_CI_PHP`, `LOCAL_CI_COMPOSER`, `LOCAL_CI_NODE`, `LOCAL_CI_NPM`, `LOCAL_CI_NPX`, `LOCAL_CI_GIT`, `LOCAL_CI_PYTHON`.

The `pest` and `browser` jobs run the suite with `php -d memory_limit=4G`, because the full framework-booting suite exceeds a typical local CLI cap (Herd's PHP caps at 2G here) and OOMs mid-run as the content set grows — CI doesn't hit this since `setup-php` leaves `memory_limit` unlimited. The pest **parallel workers** get the limit via paratest's `--passthru-php` (they spawn as their own PHP processes at the system CLI cap otherwise, which is where the OOM lands); the single-process serial tail takes it directly. Override the local figure with `LOCAL_CI_PHP_MEMORY_LIMIT` (e.g. `8G`, or `-1` for unlimited).

## Quick start

The driver is [`scripts/local-ci.mjs`](../../../scripts/local-ci.mjs). It builds a sibling worktree (default `../uams-statamic-ci`) of the named branch and runs CI there; the current checkout never moves.

```bash
# Full CI against another branch (creates ../uams-statamic-ci on first run)
node scripts/local-ci.mjs --branch <branch>

# Fast checks only, reusing an already-built worktree
node scripts/local-ci.mjs --branch <branch> --jobs pint,encoding --skip-install

# Refresh refs so the branch sync lands the pushed commit; --fresh also rebuilds vendor/
node scripts/local-ci.mjs --branch <branch> --fetch
```

On macOS/Linux the script is executable, so `./scripts/local-ci.mjs <branch>` works too (the branch may be passed positionally). There's also an npm alias — `npm run ci:local -- --branch <branch>` (note the `--` before the flags).

## Job mapping (mirrors `ci.yml`)

| Job | Runs | Notes |
|---|---|---|
| `pest` | `pest --parallel --exclude-group=serial` then a serial `pest --group=serial` tail (paratest; each worker warms its own isolated per-`TEST_TOKEN` cache), then `check-skip-baseline.php` per band | Only job that inits the private `content/` and `users/` submodules (Composer PAT read at runtime; UAMS-Web/uams-statamic-archive#2477 added `users/`). Does the `.env`/`key:generate`/`post-autoload-dump` framework prep (shared with `phpstan` and the `browser` job) |
| `pint` | `pint --test` on `*.php` changed vs base (`main...HEAD` merge-base) | Skips when nothing changed |
| `rector` | `rector --dry-run` under the **gating** profile (the default; paths come from `rector.php` — **`app/` only**) | Runs in a private `TMPDIR`/`TMP`/`TEMP` so a warm cache from another profile cannot make it under-report (a false green — UAMS-Web/uams-statamic-archive#1720). Static analysis: needs `vendor/` but no framework prep, submodule, or Node |
| `mutate` | One scoped `pest --mutate` over `App\Support\UamsAlert\AlertSiteKeyResolver`, asserting a **non-zero mutation count** and a **100% score** | Gates the mutation **tooling**, not the mutants (UAMS-Web/uams-statamic-archive#1731). The exit code alone is not enough — Pest exits `0` on `0 Mutations for 0 Files created`, which is what UAMS-Web/uams-statamic-archive#1728 actually was. **Needs a coverage driver** (see the floor above); needs `vendor/` but no framework prep, submodule, or Node |
| `phpstan` | `composer stan` (bare `phpstan analyse` at max level, `--memory-limit=2G`; paths come from `phpstan.neon.dist` — **`app/` and `tests/`**) | Does the framework prep (larastan boots the Laravel app to resolve types); reads source not content, so no submodule or Node |
| `build` | `npm run build` + `npm run cp:build` | Runs `npm ci` |
| `js` | `npm run test:js` (Vitest) | Framework-free JS unit tests; needs `npm ci` but no PHP/Composer/submodule |
| `spell` | `npm run spell` (`scripts/spell.mjs`: cspell over the whole tree, not only the diff) | Its globs are `GLOBS` in that script. No submodule needed |
| `isolation` | `php scripts/check-parallel-safe-tests.php` — the parallel-unsafe-pattern guard over `tests/` | Dependency-free PHP. No submodule needed |
| `browser` | `vendor/bin/pest tests/Browser` after `npx playwright install chromium`, reusing this run's `build` output or building it | Headless Control Panel and front-end smoke, plus the axe gate |
| `encoding` | vendored cp1252 double-encoding guard, plus the decision-log order, mutate-ignore and lone-CR guards and the release-notes generator suite (`python3 .claude/skills/writing-release-notes/gen_release_notes.test.py`, UAMS-Web/uams-statamic-archive#2850) | No submodule needed. Needs Python 3 |
| `no-emoji` | `node scripts/check-no-emoji.mjs` — emoji in `.claude/`, `docs/` and `README.md` | Its own job: `--jobs encoding` does not run it. No submodule needed |
| `content-pointer` | `node scripts/check-content-pointer.mjs --base origin/main`: refuses a submodule pointer whose commit is not on that submodule's `main` (UAMS-Web/uams-statamic-archive#2965, UAMS-Web/uams-statamic-archive#3115) | An unchanged pointer passes on two `ls-tree` reads, with no network. A moved one costs a bare clone of that submodule, about two seconds for content, using the Composer PAT or the git credential helper. Exit 1 is "not on main", 3 is "not in the submodule at all", 2 is "could not run" |
| `validate` | `composer validate --no-check-publish --no-check-all`, which fails when `composer.lock`'s content hash no longer matches `composer.json`, then `composer dump-autoload --optimize --dev --strict-psr --dry-run --no-scripts`, which fails on a file with no class matching its PSR-4 path (#322) | Neither step needs an install, submodule, framework, network, or private-package auth. With `vendor/` present the second scans it, which took 23s on a loaded Windows machine. The flags scope the output to an application rather than a published package; they do not change pass/fail (warnings only fail under `--strict`, which reds a clean checkout here) |

`--jobs` accepts any subset of `isolation validate no-emoji content-pointer encoding spell js build pint rector mutate phpstan pest browser` (comma-separated, listed here in the cost-gate order they run, `CHAIN` in `scripts/local-ci.mjs`), or `all` (default). Every job is in `all` — there is no opt-in job, because a suite that runs only when somebody remembers to ask for it sits red unnoticed.

## Choosing what to run

`--jobs all` (the default) is always safe. Scoping down with `--jobs` is a speed optimization whose **one failure mode is skipping a job whose inputs the diff actually touches** — that job's coverage is then silently dropped, and because remote CI is off (see the `RUN_REMOTE_CI` note above), a locally-skipped job is caught nowhere else. So the rule when you scope: **include every job any changed file gates, and when in doubt include it (or just run `all`).**

Map the diff — `git diff --name-only origin/main...HEAD` — to the jobs it gates:

| A changed file matching… | gates |
| --- | --- |
| **any `*.php`** — `app/`, `tests/Feature`, `tests/Unit`, **`tests/Browser`**, `database/`, `config/`, … | **`pint`** (keyed off the extension, not the test suite — see the trap below) |
| **any `*.php`** — `app/` **and `tests/`** — or `composer.lock` | **`phpstan`** (`phpstan.neon.dist` analyzes `tests/` at the same level as `app/`, UAMS-Web/uams-statamic-archive#1344 — see the trap below) |
| `app/**.php`, `rector.php`, `rector-sweep.php`, or `composer.lock` | **`rector`** (`rector.php` pins `withPaths([app/])`, so — unlike `phpstan` — a `tests/`-only diff does **not** gate it; `composer.lock` does, because `withComposerBased()` picks its Laravel/PHPUnit sets from *installed* versions) |
| `app/**`, `tests/{Feature,Unit}/**`, `resources/blueprints/**`, `resources/fieldsets/**`, `resources/views/**` (Antlers render tests), `config/**`, `routes/**`, the `content` pointer, `composer.lock` | `pest` |
| `tests/Browser/**`, or a CP-fieldtype / front-end-theme surface a CP smoke or the axe gate renders | `browser` (already in `all`; name it only for a job-scoped run) |
| `resources/{js,css,scss}/**`, `*.vue`, `vite*.config.js`, `package*.json` | `build` |
| a Vitest spec (`*.test.js` / `*.test.mjs`) or the `resources/js/**` it covers | `js` |
| **any** `*.{php,js,mjs,vue,md,yml,yaml,py}` or `*.antlers.html` | `spell` (the globs are `GLOBS` in `scripts/spell.mjs`) |
| `.claude/**`, `docs/**` or `README.md` | `no-emoji` (its own job, not a stage of `encoding`; the roots are `ROOTS` and `FILES` in `scripts/check-no-emoji.mjs`) |
| `tests/**` | `isolation` (parallel-unsafe-pattern guard) |
| a submodule pointer (`content`, `users`, `resources/forms` or an asset submodule) | `content-pointer` (and `pest`, above, for `content`) |
| `composer.json` or `composer.lock` | `validate` (lock-hash freshness — a `composer.json` edit that skipped the lock refresh) |
| any changed text file | `encoding` (cp1252 guard — cheap, always safe to include) |
| **any diff at all** | `mutate` — the one row with no path key. It runs a single fixed command over one class regardless of what changed, so there is nothing to map it to; at ~9s it is always worth including. It gates the mutation *tooling*, which any dependency bump can break (UAMS-Web/uams-statamic-archive#1728) |

**The trap this table exists to prevent: `pint` and `phpstan` are gated by the file *extension*, not by which directory or test suite the file belongs to.** A `tests/Browser/*.php` file executes in the `browser` job (not `pest`), but it is still a `.php` file — so a diff that touches it **must include `pint` and `phpstan`**. Do not reason "this `.php` is a browser test → the `browser` job covers it" and drop either of them.

**`phpstan` shares that rule**, which is easy to miss because `composer stan` reads like an `app/` concern. It runs a bare `phpstan analyse`, so the paths come from [`phpstan.neon.dist`](../../../phpstan.neon.dist) — and that config lists **`tests` alongside `app`** (UAMS-Web/uams-statamic-archive#1344: first-party test code is analyzed at the same level, so broken mocks, wrong-typed fixtures, and dead assertions are caught like any other bug). A **test-only diff therefore gates `phpstan`**. Scoping it out on the reasoning "no `app/**` changed" is how PR UAMS-Web/uams-statamic-archive#1674 merged PHPStan-red: two real errors — a second `->not` in one Pest expectation chain (`Expectation::$not` does not exist on the object `toContain()` returns) and an unnarrowed nullable `Blueprint::find()` — reached `main` and had to be repaired in a later PR. Remote CI is off, so nothing else catches it.

**So: `*.php` in the diff ⇒ `pint` AND `phpstan`, always.**

Recipes once the mapping is satisfied:

- PHP-only diff: `--jobs pint,rector,phpstan,pest,spell,encoding` — including a **test-only** one, since `phpstan` analyzes `tests/` too; add `isolation` when anything under `tests/` changed. Drop `rector` only when nothing under `app/` changed (it is the one PHP job scoped to `app/` alone). `spell` is there because its globs include `php`, and `encoding` because it covers every text file.
- Docs-only (`*.md`): `--jobs spell,encoding,no-emoji`. `no-emoji` is the one guard scoped to exactly what a docs change touches, and naming `encoding` does not reach it (UAMS-Web/uams-statamic-archive#2645).
- Pure front-end (JS/Vue/CSS, no `.php`): `--jobs js,build,spell,encoding`. Checked against the table: `resources/` is outside `no-emoji`'s roots, so it is not needed here.
- Lightest end-to-end proof the harness works: `--jobs encoding`.
- `build` output is **branch-invariant** unless a PR touches JS/CSS/`vite*.config.js`/`package*.json`; when validating several such PRs, run `build` on one branch only.
- `pest` is the slowest and the only job that needs the `content/` and `users/` submodules (`users/` since UAMS-Web/uams-statamic-archive#2477: a 2 KB pack that lets `RegistrantRecordsAreGitIgnoredTest` run in the gate rather than skip).

## More than one developer running the pipeline

The pipeline has no lock. Two sessions watching the same repo see the same `READY`, work the same branch and then race to merge, where the loser either hits a conflict or merges a branch the winner is still validating. Nothing in the runner prevents this, so the partition is a convention.

**Partition by repository: one gate session per repo.** `--repos` scopes a watcher, and a repository watched by exactly one gate cannot be double-validated no matter who a pull request is assigned to.

```bash
node scripts/watch-github.mjs --repos UAMS-Web/uams-statamic
```

**The watcher does not filter by assignee, and deliberately so (UAMS-Web/uams-statamic-archive#2366).** It carried an `--assignee` flag until then. The flag looked like it partitioned sessions and could not: every session here authenticates as the same account, so it partitioned by *person*, which is not the axis the race runs along. It also cost two things outright — an unassigned pull request was invisible to every filtered watcher, waiting indefinitely with no error anywhere, and a pull request assigned to a second developer was invisible to the first developer's gate. Passing `--assignee` now fails as an unknown flag rather than being quietly ignored.

**The evidence that the filter was never what prevented double validation** is that `wordpress-importer`'s watcher has never carried it — zero occurrences on its default branch — and has never reported the failure. One gate per repository is what does the work.

**A cross-repo companion set must still sit with one gate.** The queue treats a companion set as one unit and merges it in order, which it cannot do if half the set is being validated elsewhere. Where a companion set crosses repositories, one session watches all of them rather than one session per repo.

## Running several branches at once (concurrent)

Submodule-in-worktree behavior is **git-version-dependent**. On git 2.32.0.windows.2 (the Windows dev machine) each worktree gets its own `content` gitdir and its own HEAD — measured once, and recorded in the [`worktrees`](../../rules/worktrees.md) rule rather than restated here — but that has not been measured under other gits, which may share **one** `.git/modules/<name>` across worktrees, where two simultaneous `content/` checkouts can corrupt shared state that also reaches your main checkout. So the isolation this procedure needs is not a property to rely on generally. Version-safe rule: **stage serially, then fan out**:

1. **Serially** add one worktree per branch (distinct `--worktree-path`) and init its `content/` and `users/` submodules — the only step that touches shared `.git` state. Each init is a **full `content/` clone** (network for a branch's new SHA; disk per copy — ~3k files each here).
2. **Concurrently** run each branch's jobs with `--skip-submodule` (and no `--skip-install`, since fresh worktrees need their own `vendor/`/`node_modules/`). The parallel phase does only `composer install` + PHPUnit/Pint/encoding — all per-worktree, none touch the submodule gitdir. Concurrent installs safely share the global Composer cache; the private-package PAT auth works inside each worktree.

```bash
# Serial phase: stage each worktree + its content/ and users/ submodules (touches shared .git).
for pair in "branch-a:../uams-statamic-ci-a" "branch-b:../uams-statamic-ci-b"; do
    b=${pair%%:*}; w=${pair#*:}
    git worktree add "$w" "$b"
    git -C "$w" submodule update --init --depth 1 content users
done

# Parallel phase: per-worktree jobs only — none touch the submodule gitdir.
node scripts/local-ci.mjs --branch branch-a --worktree-path ../uams-statamic-ci-a --skip-submodule &
node scripts/local-ci.mjs --branch branch-b --worktree-path ../uams-statamic-ci-b --skip-submodule &
wait
```

On Windows PowerShell, run the serial phase the same way and launch the parallel phase as background jobs (`Start-Job { node scripts/local-ci.mjs --branch ... --skip-submodule }`) or simply from separate terminals.

A branch already checked out in another worktree **cannot** be added to a second one. Under the slot model the tree holding it is normally a slot, so **release it** — `node scripts/worktree-slots.mjs release <slot>` — rather than moving a checkout off the branch by hand; the primary holds `main` and should not be carrying a branch in the first place. Tear down the *staging* worktrees this section creates with `git worktree remove <path>` per worktree when done: those are ephemeral by construction and are the case removal is still right for.

### The runner refuses a second run in the same worktree, and exit 4 is that refusal

Distinct `--worktree-path` values above are not a convention any more — the runner enforces them (UAMS-Web/uams-statamic-archive#2256). It takes a single-flight lock on the worktree at the first point that path is known, and a second invocation targeting the same tree **refuses and exits 4** rather than proceeding:

```
REFUSED: another local-CI run holds ../uams-statamic-ci.
  Holder: pid 51234, branch 2256-refuse-concurrent-run, started 2026-09-06T11:42:03.118Z.
  Reason: pid 51234 is still running.
```

**Exit 4 is the guard working, not a failure to remedy.** Do not re-run into it and do not delete the lock by hand: wait for the holder to finish, or give the new run its own `--worktree-path`, which is exactly what the fan-out recipe above already does. The exposure is the shared *tree*, so two runs on different paths are unaffected and both proceed.

Two behaviors worth knowing before you meet them. A run killed hard leaves its lock behind; the next invocation **reclaims it and says so** (`WARNING: Reclaiming a stale lock … pid N is gone`), so a dead holder never deadlocks the slot. But a lock whose holder file cannot be read — truncated, empty, missing — is reported as **held** and never reclaimed, because an unreadable lock is not evidence that nobody holds it, and reclaiming on a bad read is how a guard comes to delete a live run's worktree. If you are certain the holder is gone and the file is corrupt, remove `<worktree-path>.lock` deliberately.

**Why this is in the runner rather than in a wrapper.** The local-CI lane already guarded its own invocation and that guard was correct, but it lived in one lane's calling convention: it did not cover a second lane, a future lane, or the same lane invoking the runner differently — and this skill tells people to run the runner. A guard shipped outside the artifact it guards does not travel with it. (The [`worktrees`](../../rules/worktrees.md) rule records the converse trap, where a guard shipped *inside* an artifact cannot report on states that predate it; this is the same seam from the other side.)

The failure it prevents is not wasted CPU. Two runs in one tree produced failures that **moved between them on an identical commit** — the standard sufficient test for a flake — with `content/` at the correct revision both times. The near-miss was one step from a published "flake confirmed". A moved failure implies non-determinism only if the runs were independent.

## Caveats

- **First run per branch is slow** — full `composer install` (and `npm ci` for `build`) in the fresh worktree. After that, pass `--skip-install` to reuse it.
- **The worktree persists** at `../uams-statamic-ci`. Reuse it (it syncs the branch to `origin/<branch>`, then checks it out) or remove it with `git worktree remove ../uams-statamic-ci`. `--fresh` does the removal for you — its job is stale `vendor/`, not a stale commit.
- **Faithful, not identical** — same commands, but your local OS + PHP/Node vs CI's Ubuntu runner. Catches real failures; rare OS-specific edge cases can still differ from CI. The script warns when your local PHP/Node drifts from CI's pins (8.4 / 22) rather than blocking the run.

### Reused-worktree hazards — the two are *different*, and `--fresh` fixes only one

`--fresh` and the branch sync address **different failures**. Conflating them produced UAMS-Web/uams-statamic-archive#1710's false red, which cost a full validation run and nearly sent an already-fixed bug back to its author.

- **Stale commit — handled by the runner, not by `--fresh`.** `git worktree add <path> <branch>` and `git checkout <branch>` both resolve the **local** ref. `--fetch` updates `origin/<branch>` but never fast-forwards the local one, and **`--fresh` removes and recreates the worktree from that same stale local ref — so it changes nothing about which commit is validated.** Only `git reset --hard origin/<branch>`, or `git branch -f <b> origin/<b>` when the branch is not checked out, actually moves it. The runner reconciles the branch with `origin/<branch>` before checkout and prints `Validating: <sha> <subject>` — compare that against the PR head. It fast-forwards when local is strictly behind, warns when local is *ahead* (you are validating work that was never pushed, which is not what the PR shows), and **refuses to run** when the two have diverged rather than guessing which side to test.
- **Stale vendor — this *is* what `--fresh` is for.** A reused worktree installs nothing (`Nothing to install, update or remove`), so a newly added Composer patch would not be applied by the install alone; the runner detects a changed patch set and re-patches, naming the applied patches in its output. `--fresh` remains the blunt remedy for any other stale-`vendor/` state.

Pass `--fetch` so `origin/<branch>` is current before the sync runs; the sync can only be as fresh as your remote refs.

### Stale-submodule hazard — the `content/` pointer must carry companion wiring

The `pest` (and `browser`) jobs render first-party fixtures out of the private `content/` submodule, and the runner checks that submodule out at **the gitlink the branch's superproject commit records** — whatever content commit the branch points at, *not* the tip of content `main`. So when a branch's app code or tests depend on **companion content that hasn't merged yet** (schema-template entries, blueprints, collection configs), and the branch hasn't bumped its `content/` pointer to a commit carrying that work, the run faithfully reproduces the same red remote CI would show — a dependency-ordering failure, not an app-code bug. **Do not "fix" it by editing app code.** Before assuming a red run means broken code, check whether the failures cluster in content-dependent tests and whether an unmerged companion content PR exists.

- **Signature.** A cluster of failures concentrated in **content-dependent** tests — those that render `schema_object` entries or other `content/`-resident fixtures through a service like `SchemaOutputService` — where the code and tests look correct but the rendered node is *missing* expected keys or carries a stale default. Tell-tales: `Undefined array key "<prop>"` on nearly every emitted property, or a wrong/default `@type`, while every *absence* assertion ("omits X when …") still passes (nothing is emitted, so absence checks trivially pass). That shape says the rendered fixture is the un-wired baseline — the submodule predates the companion work.
- **Confirm it's the pointer, not the code.** Point the worktree's submodule at the companion content commit and re-run the affected tests: `git -C <worktree>/content fetch origin <content-branch>` → `git -C <worktree>/content checkout <content-sha>` → `(cd <worktree> && php artisan statamic:stache:clear)` (the Stache won't surface freshly-checked-out content until it re-indexes) → re-run just the failing file. Green = it's the pointer. **Restore afterward** (`git -C <worktree>/content checkout main`) so the worktree matches its recorded gitlink and the next run isn't silently validating divergent content.
- **The real fix lands in git, gated on the companion.** After the companion content PR merges to content `main`, bump the branch's `content/` gitlink to that commit and commit it — from the branch checkout: `git -C content fetch origin && git -C content checkout main && git -C content pull`, then `git add content && git commit`. Until the companion merges, the app branch is expected-red on both remote and local CI; that's dependency ordering, not a defect. Record the dependency in the PR body per the `writing-pull-requests` skill's **Submodule-pointer bumps** note.
- **When the app branch has *already* bumped its gitlink to the companion branch's commit, no workaround is needed — just run the runner.** The `==> Initializing content/ and users/ submodules` step clones the content repo and checks the recorded gitlink out directly, and that clone reaches commits that live only on a companion **branch** — an unmerged companion commit is fetch-reachable, so it resolves. Confirm it in the runner output (`Submodule path 'content': checked out '<sha>'` naming the companion commit) and, if you want the stronger check, assert the companion's actual data landed in the worktree (the moved/added files exist under `<worktree>/content/`). This is the well-formed companion-pair case and it needs none of the `--skip-submodule` setup below. **It passes every job but one:** `content-pointer` refuses a gitlink that is not yet on content `main` (UAMS-Web/uams-statamic-archive#2965), so until the companion merges, run the chain without it, as the landing bullet below says. That setup is for the *other* case: the app branch's gitlink still points at an old content-`main` commit and you want to validate against the companion branch anyway.
- **Run local CI green *before* the companion merges *without* bumping the gitlink — point content at the companion branch + `--skip-submodule`.** When you need `pest` green locally before the content PR merges and the gitlink is bumped, set the reused worktree's submodule to the companion branch and tell the runner to leave it alone. In the worktree (`../uams-statamic-ci` by default): `git -C content fetch origin <content-branch>:refs/remotes/origin/<content-branch>` → `git -C content checkout -b <content-branch> origin/<content-branch>` → `php artisan statamic:stache:clear` (so the Stache re-indexes the swapped-in content). Then run **`node scripts/local-ci.mjs --branch <branch> --skip-submodule`** — `--skip-submodule` is **required**: a normal run does `git submodule update --init`, which resets content back to the recorded gitlink and re-reds the tests. Caveats: `--fresh` removes the worktree and re-inits at the gitlink (undoing this — redo the setup after); `git -C content pull` refreshes it if the companion branch grows; and because the worktree's `content/` is an **independent clone** (measured on git 2.32.0.windows.2 — see the concurrent-runs note above), this does not disturb the primary checkout's content or the site Herd serves. Local-only: the branch's committed gitlink is unchanged, so remote CI and the eventual proper bump are unaffected.
- **Landing a companion-bearing PR: validate the app PR, then merge companion → app.** When an app PR declares a **Companion PRs** section (e.g. a `UAMS-Web/uams-statamic-content` content PR its `content/` pointer targets, or a `wordpress-importer` PR), authorization to merge the app PR **extends to its companion PRs**: the cross-repo change is one logical unit, so no separate approval is needed for the companion. The app PR's **green required checks are the app+companion integration test**: the `pest` job checks out the companion content at the app's gitlink (fetch-reachable even before the companion merges), so a green app run proves the companion too. **That run leaves out `content-pointer`**, which refuses the same gitlink until the companion is on content `main` (UAMS-Web/uams-statamic-archive#2965) and, as the fourth link, would otherwise skip `pest`: pass `--jobs` with every other job in `CHAIN`. Safe order: **validate the app PR green on every job except `content-pointer` → merge the companion PR to its `main` → run `--jobs content-pointer`, which confirms the app's `content` gitlink is now an ancestor of content `main` → merge the app PR.** The content repo allows **merge commits only**, enforced by its merge settings since 2026-10-09 (UAMS-Web/uams-statamic-archive#2985), so an app pointer targeting the companion *branch tip* becomes an ancestor of content `main` on merge and **stays valid with no re-bump**. A companion squashed before that setting leaves the gitlink off `main` for good; the job then names the commit it landed as, and the app PR needs a re-bump and a full re-validation. The content repo is **`UAMS-Web/uams-statamic-content`** (not `content`); the app PR body's *Companion PRs → "Merge first"* line states the order.

## Keeping parity with remote CI

`scripts/local-ci.mjs` is a **hand-maintained mirror** of [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml). They have no shared source of truth, so they drift unless changed together.

- When you add/remove/change a CI job or its command, update **both** files in the same PR.
- Every chained remote job's run step carries an inline `# PARITY:` marker (the manual-run-only `submodule-access` job has no local counterpart and no marker, UAMS-Web/uams-statamic-archive#2497) pointing at the script; the workflow's top-of-file note and the script's header block both list the full job↔command map.
- Parity covers the **pass/fail surface only**: the six jobs' commands, the Pint diff scope (`diff-filter=ACMR`, `base...HEAD` merge-base, `*.php`), and the toolchain pins (PHP 8.4, Node 22, npm >= 11.10.0). It deliberately omits CI-only infra that doesn't change local results — OPcache/JIT ini tuning, `actions/cache` layers, clean-runner guarantees.
- When editing this skill's job-mapping table above, re-check it against `ci.yml` so the three documents stay consistent.

### Enforcement (co-change gate)

A `pre-commit` hook mechanically enforces the "change both together" rule:

- [`.githooks/pre-commit`](../../../.githooks/pre-commit) blocks any commit that stages **exactly one** of `ci.yml` / `scripts/local-ci.mjs`. It's a *co-change* gate, not a semantic diff (the two files are deliberately not byte-identical), so it just forces a human to touch both.
- It's auto-wired in every clone: `composer install`/`update` runs [`scripts/setup-git-hooks.php`](../../../scripts/setup-git-hooks.php), which sets `git config core.hooksPath .githooks` (a no-op outside a git work tree, in a subdirectory of another repository, when `.githooks/pre-commit` is untracked, and under GitHub Actions). To activate without a Composer run: `git config core.hooksPath .githooks`.
- **Bypass** a deliberate one-sided edit (typo in a comment, a script-only flag) with `git commit --no-verify`. The hook is a safety net, not a lock — and it costs zero CI minutes.

See `node scripts/local-ci.mjs --help` for every flag.
