<!--
  Synced from UAMS-Web/uams-claude-skills: shared/.claude/skills/evidence-behind-rules/worktrees.md
  Edit it there, not here; the next sync overwrites this copy.
  Delivered to this repository through the profile(s): core.
-->
<!-- cspell:ignore analyse backgrounded deinit Deinit fatals pasteable pathspec rebuildable reflog -->
# `worktrees`: the full text and its evidence

This is the full text of the [`worktrees`](../rule-worktrees/SKILL.md) rule ("work from stable, pre-bootstrapped worktree slots (branch work never happens in the primary checkout)") as it stood before it was condensed in UAMS-Web/uams-claude-skills#356. It keeps the measurements, incidents and worked examples the condensed rule cites. The rule is the instruction: where the two differ, the rule governs.

Branch work happens in one of a **fixed, small set of long-lived worktrees** ("slots") that are bootstrapped once and kept current, never in a worktree created for the ticket and thrown away after it. The primary checkout (the repo root of your main clone; its absolute path differs per machine and per platform, so this rule never names one) is the reference tree: it **holds the `main` branch**, current with `origin/main`, it is the diff base, and branch work does not happen there.

**Why slots rather than a worktree per ticket.** Two sessions on one working copy collide by construction: a branch checkout in one swaps the tree out from under the other, one session's `git stash` gets popped by the other, a stray half-finished file rides along in the other's commit. So work is isolated per tree; the unit is a long-lived slot rather than a fresh worktree, because a fresh worktree is not free:

- **It costs a bootstrap** (`composer install` and whatever else the repository's bootstrap needs (see *Provisioning a slot*)) every time, for work that is usually sequential anyway.
- **It re-opens every bootstrap failure mode this file documents**, and those are the expensive part. A `vendor/` junction silently loads the donor worktree's Pest bootstrap; a directory name starting with a digit fatals every test before it runs. Each detour costs far more than the install it was trying to skip.
- **It changes the session's working-directory string**, which is part of the cached prompt prefix, so a never-before-seen path starts every session cold. A fixed set of paths lets that prefix recur. (Secondary: the cache TTL is an hour, so this pays within a working day, not across days. The bootstrap and the failure modes above are the dominant cost.)

A slot is bootstrapped once, so solo work has no cost reason to stay in the primary either.

## The slots

The set is fixed and small: the primary and two work slots (**A** and **B**). A repository may add one more tree for a specific job. Where the slots sit is decided per repository, because it depends on what scans the directory the primary lives in. A repository with no slots yet sets them up as *Provisioning a slot* describes.

Here the local CI runner also keeps a tree of its own, listed below; see *The CI tree belongs to the runner*.

**Slot names must not begin with a digit**: Pest builds its generated test class's namespace from the file path with separators stripped, so a path segment starting with a digit is an invalid PHP namespace and every test in the tree dies with `Unable to create test case for test file at …` before it runs. Names of the form `<repo>-a` satisfy this by construction; the constraint only bites when you name an *ephemeral* worktree after a ticket number (see below).

Five trees, all **siblings** of each other, all inside Herd's parked directory:

| Tree | Path (relative to the primary) | Role |
| --- | --- | --- |
| Primary | *the main clone itself* | Reference checkout. **Holds the `main` branch**, current with `origin/main`. Serves the site at `uams-statamic.test`. Not a work surface. |
| Slot A | `../uams-statamic-a` | Ticket work. Take this one first. |
| Slot B | `../uams-statamic-b` | A second concurrent session, or a second ticket. |
| Importer | `../uams-statamic-importer` | The sibling `wordpress-importer` package's host-app test runs, and nothing else. See *The importer slot* below. |
| Local CI | `../uams-statamic-ci` | Owned by [`scripts/local-ci.mjs`](../../../scripts/local-ci.mjs). **Never hand-drive it**: the runner checks branches in and out of it. |

History: [The slots](../../../.claude/history/worktrees.md#the-slots).

## Which tree does the work

- **All branch work → a slot.** Claim one, work in it, release it. This holds for a solo session as much as a concurrent one: a session that never touches the primary cannot collide with one that does, and the slot is already bootstrapped so there is no reason to prefer the primary.
- **The primary → `main`, and left there.** It is the tree to diff against. Leaving it on a ticket branch also destroys the oldest drift signal there is: "the primary is on a branch I did not set" no longer means anything if you routinely put it there yourself.

- **`wordpress-importer` host-app test runs → the importer slot**, never A or B. See *The importer slot* below.

- **Local CI → its own tree, driven only by the runner.** The runner resolves its base from the primary, one more reason the primary stays on `main`. See *The CI tree belongs to the runner* below and the `local-ci` skill.

**Detected churn still means isolate first.** If you observe a slot moving under you: the branch changed, untracked files you did not create appear, `git worktree list` shows a slot on a branch you did not set: treat it as a live second session on that slot and take the other one, or an ephemeral worktree if both are held.

## A slot at rest, and how occupancy is read

**A slot at rest is detached at `origin/main`, with a clean tree: both halves, not one.** Git allows a branch to be checked out in exactly one worktree, so `main` can only ever live in one tree: the primary. Two slots both "on `main`" is not a state that exists; `git worktree add` refuses it outright (`fatal: 'main' is already used by worktree at …`). Detaching sidesteps the constraint entirely and costs nothing, because a slot at rest is not a place work happens.

There is no lock file and there does not need to be one: **the slot's own branch is the occupancy signal.** A slot holding a branch is a slot in use, and `git worktree list` answers that half. But it reports the checked-out ref alone; it says nothing about the index or the working tree, so a slot that is detached and carries **uncommitted work** reads exactly like a genuinely free one to that command. Read both:

```bash
git worktree list
git -C <slot-path> status --porcelain --untracked-files=all
```

A slot is free only when it is detached **and** that second command prints nothing. A slot on a `<N>-<slug>` branch is **held**: by you earlier, or by another session right now.

- **Leave a slot detached when you finish with it**: one left on a merged branch reads as occupied to everyone else.
- **Commit or clear your own work before stepping away, and always run the tree check before taking a slot that looks free.**
- **If you find a slot detached and dirty, leave it and ask the other sessions whose it is: do not clean it up, and do not take it.** Cleaning up looks like the tidy move and is the one that loses somebody's patch.

`scripts/worktree-slots.mjs` reads every tree at once, so start there:

```bash
node scripts/worktree-slots.mjs status     # branch, free/held and drift for every tree
git worktree list                          # the raw form, when you want it
```

## Claiming and releasing a slot

Take **A** first, **B** second. If both are held and you did not hold either, you have two live sessions; do not evict one, take an ephemeral worktree (see *Ephemeral worktrees*).

**Claim a slot by branching from `origin/main`**, never from whatever the slot happens to be sitting on:

```bash
cd ../<repo>-a
git fetch origin
git checkout -b <N>-<slug> origin/main     # or: gh issue develop <N>, then check it out
```

**Releasing a slot is a real step, not a formality.** When a ticket ships, return the slot to rest:

```bash
cd ../<repo>-a
git status --porcelain                     # MUST be empty, see the litter note below
git fetch origin
git checkout --detach origin/main
```

Three things go wrong when this is skipped, and all three are silent:

- **The next ticket branches off the last ticket's tip** instead of current `main`, so its PR carries commits that belong to another ticket and its diff against `main` is wrong. This is the single largest new hazard the slot model introduces, and it has no loud failure: everything builds, the branch is just wrong.
- **Untracked scratch from the previous ticket rides into the next commit.** A slot accretes import XML, generated fixtures, notes. Check before you branch, not after you commit.
- **`git status` is blind to empty directories**, so test litter left by a run killed mid-write does not show up there at all. Look for it directly when a slot has hosted an interrupted run.
  - **Stale `storage/framework/testing/` is a false-red that looks like a broken bootstrap.** Interrupted Pest / mutate runs leave throwaway Stache roots, content-mirror trees, and band-lock files under that path (`storage/framework/testing/.gitignore` is `*`, so none of it appears in `git status`). The next isolated-Stache Feature suite then dies with `Call to a member function handle() on null` (often via `Collection::handles()` / status filters resolving a null collection) while the same tests stay green on the primary and on a wiped slot. Measured 2026-10-04 on Windows 11 / Herd PHP 8.4 in slot A during the #255 pass-3 wave: `MergePeopleTest` and `DepartmentHubPeopleTest` failed every case until the directory was cleared; after wipe, both went green without code changes. Recovery (macOS / Linux / Windows; keeps the tracked `.gitignore`):
    ```bash
    node .claude/skills/pest-testing/scripts/clear-testing-litter.mjs
    ```
    That helper, which the [`pest-testing`](../pest-testing/SKILL.md) skill ships as [`scripts/clear-testing-litter.mjs`](../pest-testing/scripts/clear-testing-litter.mjs), runs from the repository root. It deletes everything under `storage/framework/testing` except `.gitignore`, printing each entry it removes, restores the tracked placeholder with `git checkout` when it is missing, then runs `php artisan cache:clear` and `php artisan statamic:stache:clear`. A link inside the directory is removed as a link, never followed. It refuses, changing nothing and exiting non-zero, when the working directory has no `artisan` or no `storage/framework/testing`, or when that directory resolves outside the repository through a symbolic link. Clear that tree before chasing content-submodule or sites.yaml hypotheses, and before trusting a narrow `--mutate` score taken after an interrupted run in the same slot. The suite-collision half of this family is in [`long-running-commands`](../rule-long-running-commands/SKILL.md) (UAMS-Web/uams-statamic-archive#1136); this bullet is the *leftover disk* half.

**Never delete the branch**: releasing the slot moves the tree off it, and `git checkout` does not touch the branch. The slot itself is never torn down.

The guarded form of the release is the helper, and naming the branch is part of it:

```bash
node scripts/worktree-slots.mjs release a --branch <N>-<slug>   # name the branch you are releasing
```

**Naming the branch is required when the slot is on one (UAMS-Web/uams-statamic-archive#2273), and the reason is that the command is used by more than its holder.** `release` refuses on a dirty tree because that would *strand* work; it now also refuses on a clean tree that is on a branch, because that *takes* a tree somebody may be working in. Those are different harms and only the first was guarded. Releasing a slot already at rest still needs no flag, so `release a` on a detached slot stays the no-op it was.

A fourth silent failure joins the three above here: **a submodule's working tree is only half-visible at the root.** `git status` there does show ` M public/assets` and ` M app/private/assets`, but never `content` (`ignore = dirty`) and, where the clone's `.git/config` sets `submodule.users.ignore`, not `users` either, so a clean-looking root proves nothing about up to two of the five. `release` probes all five and refuses on tracked changes, but only when you actually *run* it: branch straight off a slot you never released and nothing looks at all. See *The importer slot* for which submodules the importer suites delete and re-copy, and for the recovery.

**The same "never delete the branch" holds on the remote, for a different reason**: PR bodies link files by absolute head-branch URL, so a deleted head branch 404s every file link in its own pull request. `delete_branch_on_merge` stays off and merges carry no `--delete-branch`: recorded in [`docs/DECISIONS.md`](../../../docs/DECISIONS.md) (UAMS-Web/uams-statamic-archive#2177).

**A held slot blocks local CI from validating its branch: release before you hand off.** Git allows a branch in one worktree only, and [`scripts/local-ci.mjs`](../../../scripts/local-ci.mjs) checks the branch out **by name**, not as a detached SHA:

```js
run(git, ['-C', repoRoot, 'worktree', 'add', worktreePath, opts.branch], repoRoot)   // on the branch
```

So a slot still sitting on `<N>-<slug>` makes the validating run fail with `fatal: '<N>-<slug>' is already used by worktree at …`. A slot holds its branch until you deliberately release it. Since the project flow has the **author flip the PR to ready and a separate session run the gate**, the author's last act on a ticket is to release the slot: otherwise the validator is blocked by a tree it does not own and must not touch. The same applies to the primary: it holds `main`, so **local CI cannot validate `main`** while it does. That is fine for PR branches, and worth knowing before you try.

History: [The importer slot](../../../.claude/history/worktrees.md#the-importer-slot).

## The importer slot

The sibling **`UAMS-Web/wordpress-importer`** package's host-app integration tests (the classes under its `tests/Feature/` that do `use Tests\TestCase;`) cannot run in that package's own CI. They need a booted app, so they run here, following that repo's [`host-app-tests`](https://github.com/UAMS-Web/wordpress-importer/blob/main/.claude/skills/host-app-tests/SKILL.md) skill. `../uams-statamic-importer` exists so those runs stop borrowing A or B.

**Claim it with a marker branch**, so the ordinary free/held reading keeps working:

```bash
cd ../uams-statamic-importer
git fetch origin
git checkout -b importer/<N> origin/main    # <N> is the importer ticket
```

The branch is a **marker, not a work surface**: nothing is committed here. Local CI never wants that branch, so holding it blocks nothing. Release it the same way as any slot (`release importer --branch importer/<N>`), and treat a held importer slot as another importer session, not as something to evict. **The importer slot is outside the A-then-B order in both directions**: importer runs take it and never A or B, and this app's own work never takes it.

**The trap: a clean tree does not mean the vendored importer is back at its pinned release.** The skill copies the importer's changed files into `vendor/uams-web/wordpress-importer` (or reinstalls it `--prefer-source` and checks a branch out there). `vendor/` is git-ignored, so **none of it shows in `git status`, and `release` will not refuse over it**: the next run inherits the last run's importer unless it overwrites the same files. Two consequences worth internalizing:

- **Never read a green run as validating the branch you think it is.** Verify the code landed before trusting the result: `grep` a token from your diff inside `vendor/uams-web/wordpress-importer/`, exactly as the skill's step 3 requires.
- **Restore the pin when you are done with a branch**, or say plainly that you did not: `composer install` puts the vendored package back at the release `composer.json` allows.

**These runs also mutate `content/` *and* `public/assets`, and the restore that covers them is narrower (and more dangerous) than its name suggests.** `snapshotIsolatedState()`, vendored at `vendor/uams-web/wordpress-importer/tests/Feature/WordPressImportTestTrait.php`, restores neither submodule wholesale. Its default path list is empty, and the only two suites that call it name **ten subtrees** between them: `PostImportTest` passes seven: the `posts` and `post_categories` collections, `content/trees/collections/default`, the `post_tags` taxonomy, and `public/assets/{audio,images,news_releases}`, and `KbImportTest` passes four, the `knowledge_bases` collection, that same tree, and the `knowledge_base_tags` and `faq_categories` taxonomies.

**What that leaves uncovered is most of it.** Those three `public/assets` subtrees hold **435 of its 1,211 tracked files** (`images` is 434, `audio` is 1, `news_releases` is tracked-empty), so 776 tracked files there are restored by nothing. `app/private/assets` has no snapshotted path at all: every one of its 485 tracked files is uncovered. So the asymmetry is not "`content/` is protected and the assets are not"; it is that ten subtrees are protected and everything else in all five submodules is not: `resources/forms` has no snapshotted path either.

**The restore is a delete-then-recopy, which makes it a plausible *cause* and not only a protection.** `restoreIsolatedState()` runs `rm -rf <path>` and then copies the snapshot back, on every test. A process killed between those two calls leaves `public/assets/audio` and `public/assets/images` **emptied**: the exact signature of the destroyed asset submodules that prompted UAMS-Web/uams-statamic-archive#2023. And a killed run is unprotected for a simpler reason than it looks: the restore runs from `afterEach`, so a process that dies mid-test never reaches it. (The missing-snapshot skip inside `restoreIsolatedState()` guards a different case: a snapshot that failed to copy: where it deliberately leaves the live path alone rather than deleting what it could not put back.)

**Root `git status` is not the check you want, and what it hides is partly per-machine.** Measured in a slot on macOS: a dirty `public/assets` or `app/private/assets` **does** surface at the root as ` M <path>`, but `content` never does: [`.gitmodules`](../../../.gitmodules) sets `ignore = dirty`, and `users` may not either, if the clone's `.git/config` sets `submodule.users.ignore` (per-machine and untracked; `git config --get submodule.users.ignore` says). On top of that, `release` reads the superproject with `--ignore-submodules=all`, which hides all five regardless. That combination is why the tooling has its own per-submodule probe rather than leaning on the root status.

**The tooling makes this automatic (UAMS-Web/uams-statamic-archive#2023), with one gap.** `release` probes each expected submodule's working tree and refuses on **tracked** changes in any of them, and on a probe it could not read, naming the submodule and printing the recovery. What it deliberately does **not** refuse on is **untracked** residue, which a completed import leaves by design: `status` reports it, `release` lets it through, and whether it is litter or something you meant to keep is the judgment left to you. `vendor/` stays invisible to both, per the trap above.

So the manual command is a **diagnostic rather than a gate**, and when you want it, it covers all five rather than `content/` alone:

```bash
git submodule foreach --quiet 'echo "== $sm_path"; git status --porcelain'
```

**Recovery is one command, and it is safe here precisely because none of this is authored work:**

```bash
git -C <submodule> restore --staged --worktree .
```

That returns the working tree to the revision the submodule has checked out. These are deletions or app-written files rather than anything anyone typed, which is why the generic "commit or move the work first" reflex is the wrong one for a submodule. Read `git status` there first regardless: if the change *is* yours, commit it inside the submodule and bump the pointer instead.

## The CI tree belongs to the runner

The local CI runner creates, reuses and checks out its own tree, always detached at the commit under test, and that tree is **never driven by hand**. Do not branch in it, do not commit from it, and do not treat it as a third slot. Do not provision it either: the runner stands it up itself on its first run and re-bootstraps it on every run afterwards, so hand-building it would only hand the runner a state it overwrites on its next run. No session ever works in it, so it never enters a prompt prefix, and the bootstrap-and-diagnosis cost that makes a hand-built tree expensive: an interactive session running installs, then reading a wall of errors and working out what was missing: is close to free when a script does the same work. The `local-ci` skill owns the runner's flags and its tree.

[`scripts/local-ci.mjs`](../../../scripts/local-ci.mjs) initializes `content` and runs both installs itself unless `--skip-install` is passed. The CI slot is deliberately not pre-provisioned.

## Keeping a slot current: the refresh gate

This is the risk the slot model **introduces**, and it is worse than the one it removes. A fresh worktree fails *loudly*: no `vendor/`, hard error, you notice. A stale slot fails *silently*: the tests run, they pass, and they pass against the wrong dependency versions. The checks invoke the repo-pinned `vendor/bin/{pint,phpstan,pest}`, so a slot whose `vendor/` predates its branch's `composer.lock` produces a green that means nothing. This is not hypothetical: in `uams-statamic` the primary checkout's `vendor/` sat at Pest 4.7.5 while `composer.lock` pinned 5.0.1, and nothing said so.

**So this is a gate, not advice.** Whenever a slot's base moves: you just released it to a newer `main`, or you synced a branch per the [`sync-pr-branch`](../rule-sync-pr-branch/SKILL.md) rule: refresh **before** running any check whose result you intend to trust. The minimum, in any repository, and the whole of it where nothing else is bootstrapped:

```bash
cd ../<repo>-a
composer install                                  # no-op when vendor/ matches composer.lock
npm ci                                            # ONLY if package-lock.json moved, see below
```

- **`composer install` is a no-op when current**, so there is no reason to reason about whether it is needed: just run it. It prints `Nothing to install, update or remove` when the slot was already right.
- **`npm ci`, not `npm install`**: `install` will happily reconcile a drifted tree against the manifest and leave `package-lock.json` modified; `ci` installs exactly the lock. Only needed when the lockfile actually moved.
- Whatever else the repository's bootstrap put in place (submodules, indexes, built assets) has its own refresh step.

This repository's further refresh steps are under its `scripts/worktree-slots.mjs` commands, below.

**The primary needs this as much as any slot does**: the Pest 4.7.5-against-a-5.0.1-lock drift above was found *there*.

**A gate that depends on remembering commands is not a gate**, which is why a repository that carries a tree-checking script makes the script the gate: a check that exits 1 when a tree has drifted works as a shell guard ahead of a long run. What such a check looks at differs per repository, because a guard is only as good as what it looks at. Each tree is judged against its **own kind** (an ephemeral worktree has different expectations from a work slot) because a guard that can never go green is not a guard; it is a line people learn to skip.

Here the script is `scripts/worktree-slots.mjs`: `status --check` is the guard and `refresh` reconciles a tree. It judges the runner-owned CI tree as a kind of its own too. What its check looks at is listed below, and it prints a verdict line as well as its exit code.

**A pipe replaces a guard's exit code with the pager's, so read the status before any pipe.** `… --check 2>&1 | tail -6; echo $?` reports whether `tail` succeeded, which it always does; the guard's `1` is gone and a `0` beside a clean-looking table reads exactly like a pass. On 2026-09-06, in `wordpress-importer`, five false greens were published in one evening by four concurrent sessions on exactly that shape, two of them by sessions that already knew about the trap; one had merged a change about it hours earlier and ran the piped form anyway, and the instances came from both macOS and Windows (Git Bash) sessions: the shape is POSIX pipeline semantics rather than anything platform-specific. Use `>/dev/null 2>&1; echo $?`, or `set -o pipefail`, or capture the status before piping. The exit code is the contract; a verdict line a guard prints is a second channel for the case where the code has already been thrown away, never a replacement.

**A guard shipped inside the artifact it guards cannot report on states that predate the guard, and the reason is structural rather than a defect in it.** The script lives *in the tree it inspects*, so a tree far enough behind is running a copy that predates a check: it reports no drift of that kind, `--check` exits `0`, and that is the value a shell guard reads as permission to proceed. The two kinds of staleness fail differently, and the second is worse:

| What is stale | What happens |
| --- | --- |
| `vendor/` | the check runs, against the wrong dependency versions |
| the tree-checking script itself | the check does not run, and its output does not look truncated: it looks complete |

Here that script is `scripts/worktree-slots.mjs`. The tree *set* is location-independent: the script resolves the primary checkout from the working directory, so any copy judges every tree. The **check set** is not: it comes from whichever file was invoked. `cd <slot> && node scripts/worktree-slots.mjs` runs the slot's copy; `node ../<repo>/scripts/worktree-slots.mjs` runs the primary's. Both enumerate the same trees and can run different checks. **A clean `status --check` run from inside a stale tree is not evidence that the tree is current.**

Anything added to a tree-checking script later (or to any tool that ships in the tree it validates) has the same blind spot at the same place: it is silent exactly where the artifact is most out of date, and silence from it is indistinguishable from health.

**One probe cannot be degraded by any copy's age, because it lives outside the tree:**

```bash
TREE=../<repo>-a
git -C "$TREE" rev-list --count HEAD..origin/main   # 0 = current, N = N commits behind
```

Measured in `wordpress-importer` on macOS (Darwin 25.6.0) against all four trees: it returned `0` for three and `1` for the slot that was genuinely behind. Reach for it when the answer has to be right and you cannot vouch for which copy is executing. It does not fetch (a command that reaches the network on every invocation is one people route around) so a stale remote-tracking ref under-reports; `git fetch origin` before trusting a zero.

**The path is assigned on its own line rather than written `<tree>` inline, and that is deliberate.** An angle-bracket placeholder is this corpus's convention and reads correctly as prose, but in a pasteable fenced recipe it sits where the shell expects a redirection: `<tree>` is an input redirect from a file named `tree`, and `>` before `rev-list` an output redirect to a file named `rev-list`. Pasted with no such file present it aborts harmlessly on the missing input; pasted in a directory that happens to contain a file named `tree`, it runs git on mangled arguments and **creates a file called `rev-list`**. Measured both ways in `wordpress-importer`, macOS and Git Bash on Windows 11, with identical results, and `bash -n` reports the line as syntactically clean either way, so nothing warns a reader who copies it. This is not a call to sweep the other placeholders: `<N>`, `<slug>`, `<branch>` and the rest name no plausible file, so the input redirect fails and the command aborts before any output redirect applies, and inline code inside prose is read as an illustration rather than copied as a command. A placeholder is exposed only when both halves hold: a pasteable fenced recipe and a name that is a plausible file. **Prose has no such protection: every word in it is a valid filename**, so a bare `>` between two words is a redirect wherever it appears, including inside narration that reaches a shell. Measured in the `wordpress-importer` primary on 2026-09-06: four empty files, `ci.yml`, `cspell`, `four,` and `still`, one mtime to the second, from a single line of explanatory text. Write the arrow as `→` (U+2192), which this corpus already uses for version transitions, or quote the whole construction. An ASCII arrow does not help and is the trap it looks like a fix for: measured in `wordpress-importer` on macOS (Darwin 25.6.0), a hyphen followed by the redirect character still redirects, because the hyphen is parsed as an argument and the file is created regardless.

```bash
node scripts/worktree-slots.mjs status          # every tree: branch, free/held, and any drift
node scripts/worktree-slots.mjs refresh a       # reconcile that slot
node scripts/worktree-slots.mjs refresh primary # the clone itself
```

**`refresh` takes `primary` as well as a slot name.** `release` and `provision` deliberately do not: the primary must keep `main` checked out (releasing detaches the tree, which would leave `main` in no worktree at all), and it is the clone rather than a worktree of it.

`status --check` checks seven things: the **npm** install stamp, the **composer** install stamp, the **patch set** (UAMS-Web/uams-statamic-archive#2021), each expected submodule's **revision**, each expected submodule's **working tree** (UAMS-Web/uams-statamic-archive#2023), the tree's own **base** against `origin/main` (UAMS-Web/uams-statamic-archive#2148), and whether the tree's **test content mirror** (`storage/framework/testing/content-mirror`) was built from the `content/` on disk, read through the app's `ContentMirror::sourceDrift()` (UAMS-Web/uams-statamic-archive#2935). The CI slot and ephemeral trees are not judged for the mirror. Since UAMS-Web/uams-statamic-archive#2282 the command also **prints** its verdict: a final `worktree-slots: DRIFT . N tree(s): …` or `CLEAN` line, so a piped run still says which it was even when its exit code has been thrown away. It is printed for `--check` only, and never under `--json`, which stays parseable.

The submodule working-tree check is the one whose absence was least visible. A submodule's revision and its contents are independent facts, and matching the first proves nothing about the second: the importer slot was found carrying **1,696 staged deletions** (every tracked file in `public/assets` and `app/private/assets`) with both submodules sitting at exactly the revisions their gitlinks recorded. `status` reported it `free` with no drift, `refresh` called it current, and `release` would have accepted it. Recovery is cheap once seen (`git -C <submodule> restore --staged --worktree .`), which is what made detection the whole cost.

`composer.lock` is stamped alongside `package-lock.json`, because it moves far more often than npm's lockfile: a check watching only npm would certify a slot as current with the previous branch's `vendor/` still in place, which is the Pest-4.7.5-against-a-5.0.1-lock failure this section opens with. Two trees are judged more loosely: the **CI slot** is runner-owned, so its missing installs and its two uninitialized asset submodules are expected: though a missing `content/`, `users/` or `resources/forms/` there is still drift, since those three the runner does need (`GATE_SUBMODULES` in `scripts/gate-slot.mjs`; UAMS-Web/uams-statamic-archive#2477 for `users`, UAMS-Web/uams-statamic-archive#2860 for `resources/forms`), and an **ephemeral** worktree (`.claude/worktrees/…`, a subagent's isolated tree) is reported and not judged.

The list the script runs, and the reasoning for each step:

```bash
cd ../uams-statamic-a
composer install                                  # no-op when vendor/ matches composer.lock
npm ci                                            # ONLY if package-lock.json moved, see below
git submodule update --init <each path in SUBMODULES, scripts/worktree-slots.mjs>
php artisan statamic:stache:clear                 # content is invisible to tests until this runs
DB_DATABASE=:memory: APP_ENV=testing php artisan test:seed-stache   # only when the mirror predates content/
```

- **`npm ci`** is only needed for tickets that touch frontend/build/JS or run `npm run spell`.
- **The `content/` submodule pointer moves with `main`.** Skipping this is the source of the null-method cascade: collection *configs* (not just entries) live in `content/collections/*.yaml`, so a stale or absent `content/` makes `Collection::findByHandle()` return `null` and every test that touches a collection dies with `Call to a member function entryClass() on null`. **That wall of errors across unrelated suites is the signature of a stale `content/`, not a broken branch.** Verify with `git -C <slot>/content rev-parse HEAD` (the direct read, and the one `scripts/worktree-slots.mjs` uses) after confirming the submodule is initialized (see *Verifying a submodule's state* below). `git submodule status` also reports each linked worktree's own revision (measured on git 2.32.0.windows.2).
- **`refresh` does NOT build assets, and a browser run needs them.** The asset build belongs to *provisioning* (`npm run build && npm run cp:build`), so a slot refreshed all evening still serves whatever it built when it was first stood up. That matters only for `tests/Browser`, which resolves its script tag through `public/build/manifest.json`: a stale build there produces a plausible *behavioral* failure (an undefined Alpine component, a binding that never fires) with nothing saying the assets are old. `Tests\Support\BrowserAssetFreshness` now refuses such a run and names the command, so this is a cost you are told about rather than one you debug; the gate is unaffected, because [`scripts/local-ci.mjs`](../../../scripts/local-ci.mjs)'s chain builds at job 6 and runs `browser` at job 12. **Run `npm run build` before a bare `vendor/bin/pest tests/Browser`.**
- **`statamic:stache:clear` after any `content/` move.** A content change is invisible to tests until the Stache re-indexes, so both arms of a probe pass and prove nothing.
- **The content mirror is rebuilt after the Stache clear, and only when it predates `content/`** (UAMS-Web/uams-statamic-archive#2935). Every content test reads the mirror, so one built from a different `content/` tests the wrong content. `test:seed-stache` refuses outside `APP_ENV=testing` and while a test process holds the mirror, so the rebuild cannot tear a band in flight. When it is refused or fails, `refresh` reports the mirror instead of `is current`.

**Nothing else about the submodule storage is settled, so do not extrapolate from the gitdir measurement below.** The object store is *not* shared either (no gitdir carries an `objects/info/alternates`; each holds its own objects). Why `content` and `app/private/assets` initialize without a network clone while `users` and `public/assets` fetch (the **Windows** observation under provisioning: on macOS none of the four submodules then present fetched) is therefore **unexplained**, not explained by sharing. Measure the specific claim you are about to write down. The `local-ci` skill's staged-serially-then-fan-out procedure remains the **version-safe** habit: the gitdir independence has been measured on one git version only, and that skill owns the concurrency procedure.

History: [Keeping a slot current: the refresh gate](../../../.claude/history/worktrees.md#keeping-a-slot-current--the-refresh-gate).

### Submodules in a linked worktree

**Each linked worktree has its OWN submodule gitdir, so its submodule HEAD moves independently**: measured, not assumed. In `wordpress-importer` on macOS the primary's exports submodule lives at `.git/modules/src/Services/WordPressImport/Tests/Exports` and slot A's at `.git/worktrees/wordpress-importer-a/modules/src/Services/WordPressImport/Tests/Exports`; in `uams-statamic`, on git 2.32.0.windows.2, `content` shows the same shape. Both sit *inside* the primary's `.git`, which is what makes a shared-storage reading look plausible, but they are distinct paths. Confirm on a new machine with `git -C <tree>/<sub> rev-parse --absolute-git-dir`; other git versions do share one `.git/modules/<name>`, so treat this as measured *here* rather than as a general property. So a concurrent session moving a submodule in slot B does **not** move it under slot A's run, and two slots on different submodule revisions is an ordinary state rather than a conflict to sequence around.

**Verifying a submodule's state: the probe that lies.** `rev-parse HEAD` cannot tell you a submodule is *absent*. In an empty submodule directory it does not fail: it walks **up** to the enclosing superproject and answers for that, so an uninitialized submodule reports the *tree's own* HEAD and reads as a revision *mismatch* rather than "not initialized". Measured in a freshly provisioned `wordpress-importer` slot: the empty `Exports/` directory reported `dbd6fa15…`, byte-identical to the slot's own HEAD; in `uams-statamic`, three uninitialized submodules in the local-CI worktree every one reported the commit the superproject was sitting on. Confirm the directory is its own repository first: `git -C <tree>/<sub> rev-parse --show-toplevel` must echo that same path back. `scripts/worktree-slots.mjs` does this check for you, which is the better answer than remembering it.

## Provisioning a slot (one time, and once only)

A slot is created once and kept current by the refresh gate from then on; it is never torn down and recreated per ticket. A repository adopting this model with no slots yet needs the following, and nothing this rule has not already said:

1. **Choose the layout.** Two work slots named after the repository: `<repo>-a` and `<repo>-b`. Make them **siblings** of the primary: they sit outside the repo, so no ignore rule has to hold for them, and a search from the primary cannot descend into them. **If the primary sits in a directory the host application scans**: a WordPress plugin under `wp-content/plugins/`: a plain sibling is a second copy of the plugin on the plugins screen; dot-prefix the slot names (`.<repo>-a`) so the host skips them before opening them, and keep them inside that directory if the test bootstrap locates the host by relative position. Slot names must not begin with a digit (see *The slots*).
2. **Create each slot at rest**: detached, because `main` belongs to the primary:

   ```bash
   git fetch origin
   git worktree add --detach ../<repo>-a origin/main
   ```

3. **Bootstrap it in place.** `composer install`, then `npm ci` where the repository has a `package-lock.json`, then whatever else the repository's checks and its served app need before the slot behaves like the primary: submodules, an `.env`, local databases, built assets. Everything provisioning writes must be git-ignored or a submodule, so none of it can ride a commit: confirm with a clean `git status` afterwards.
4. **Never junction or symlink `vendor/` from another tree to skip `composer install`.** PHP resolves `__DIR__` *through* the link to the real path, so `vendor/bin/pest` reports the **donor** tree . Pest derives its root from that and loads the **donor's** `tests/Pest.php` instead of yours. Your test *files* run; somebody else's bootstrap configures them. The tell is a helper you just added being `Call to undefined function` while pre-existing helpers resolve fine, and a stack trace naming the other tree's vendor path. It cuts both ways: a bootstrap regression passes, and a bootstrap fix appears not to work. A `node_modules/` junction is safe: cspell and the JS tooling do no `__DIR__`-based root detection.
5. **Make `.claude/worktrees/` ignored by the tracked `.gitignore`**, so an ephemeral worktree can never be staged from any checkout (see *Ephemeral worktrees*).

Do not provision the local CI runner's own tree (`<repo>-ci`); the runner stands it up itself (see *The CI tree belongs to the runner*).

Here one command does all of it, and writes the install stamp `status` judges drift from:

```bash
node scripts/worktree-slots.mjs provision b
```

Do this once per slot; the refresh gate above keeps it current from then on. The long form below is what that command runs.

```bash
# 1. Create it as a sibling, at rest (detached: `main` belongs to the primary).
git fetch origin
git worktree add --detach ../uams-statamic-a origin/main

cd ../uams-statamic-a

# 2. Every submodule in SUBMODULES (scripts/worktree-slots.mjs): the content, the login users and the forms live here. This runs BEFORE the
#    installs, and the order is load-bearing, see the note below.
git submodule update --init <each path in SUBMODULES, scripts/worktree-slots.mjs>

# 3. Dependencies (reads your global Composer github-oauth PAT for the private packages).
composer install
npm ci

# 4. Env + app key, then point APP_URL at THIS slot's Herd host, see the trap below.
cp ../uams-statamic/.env .env
sed -i 's|^APP_URL=.*|APP_URL=http://uams-statamic-a.test|' .env

# 5. BOTH served-app databases (git-ignored, so neither travels). Missing the second
#    leaves a slot whose CP loads fine and whose every front-end page 500s.
cp ../uams-statamic/database/database.sqlite database/database.sqlite
mkdir -p storage/framework/cache
cp ../uams-statamic/storage/framework/cache/asset-metadata.sqlite storage/framework/cache/

# 6. Assets: the CP needs these, not just the front end.
npm run build && npm run cp:build

# 7. Clear, and re-index the freshly-checked-out content.
php artisan optimize:clear
php artisan statamic:stache:clear
```

Why each of the non-obvious ones:

- **Submodules go first, and getting this backwards silently half-builds the slot (UAMS-Web/uams-statamic-archive#1999).** `composer install` runs `post-autoload-dump` → `artisan statamic:install`, which **scaffolds the content tree** (`content/{assets,collections,globals,navigation,taxonomies}/.gitkeep`). Git refuses to clone a submodule into a non-empty directory, so installing first makes the `content` clone fail: twice, then abort:

  ```
  fatal: destination path '…/content' already exists and is not an empty directory.
  Failed to clone 'content' a second time, aborting
  ```

  **The tell is that it looks like a network or permissions problem and is neither.** `find content -type f` on the wreckage returns exactly five `.gitkeep` files and nothing else. Worse, the other submodules clone *before* the retry loop gives up on `content`, so the tree ends up with every other submodule present and **only `content` missing**: the one submodule the test suite needs, whose absence produces the null-method cascade documented under *Keeping a slot current*. Reversing the order is safe rather than a trade: the content submodule already carries every directory `statamic:install` would create, so with it in place the install scaffolds nothing and leaves `git -C content status --porcelain` empty (verified directly, not inferred).
- **The login users are a *submodule*, not a git-ignored copy.** `users/<email>.yaml` is **tracked inside the `users` submodule** (`git -C users ls-files` lists them), so `git submodule update --init users` is all a slot needs: there is nothing to copy from the primary. Statamic's user repository here is `file` (`config:show statamic.users.repository`), so login needs no database. Skip this init and the slot has no user and you cannot log in.
- **There are *two* served-app databases, and the second one is the trap.** Both are git-ignored: `database/database.sqlite` (`database/.gitignore`) and `storage/framework/cache/asset-metadata.sqlite` (`storage/framework/cache/.gitignore`), and neither is read by the test suite, which runs `DB_DATABASE=:memory:` ([`phpunit.xml`](../../../phpunit.xml)). Copy both so the slot's `.test` host behaves like the primary's. **Verify a new slot with a front-end page, not just `/cp/auth/login`:** the CP never touches asset metadata, so it returns `200` while every front-end page 500s with `Database file at path […/asset-metadata.sqlite] does not exist`.
  - **`php artisan migrate` does not repair it.** The migration that creates the `assets_meta` table is recorded in the *copied* `database.sqlite`, so Laravel reads it as already applied and skips it, while the separate file it would have created never appears. Copy the file; do not try to rebuild it.
- **There are five submodules, not one**: `content` (11M), `users` (8K), `resources/forms` (15K, measured on Windows 11 on 2026-10-19), `public/assets` (170M), and `app/private/assets` (680K). `content` is needed for tests, and so is `users` since UAMS-Web/uams-statamic-archive#2477: `RegistrantRecordsAreGitIgnoredTest` skips without it and the gate's skip baseline assumes it runs: as well as to log in. `resources/forms` holds the form configurations since UAMS-Web/uams-statamic-archive#2860 (seven YAML files when measured): without it no form loads and the form-dependent tests skip. The two asset modules are needed for the served app to render its media. **Do not assume they all reuse the primary's object store.** What this step *costs* then depends on the platform, and the two measurements do not agree, so budget from the branch that names yours, not from whichever one you read first.
  - **Windows:** `content` and `app/private/assets` checked out with no clone, while `users` and `public/assets` each printed `Cloning into '…'` and fetched over the network, so `public/assets` is a real 170M download per slot, not a local copy. Budget for that, and expect this step to dominate a slot's provisioning time.
  - **macOS:** nothing fetched over the network at all: all four submodules there were then resolved from local objects, with no `Cloning into '…'` line for any of them. Measured provisioning both slots on 2026-08-13: **35s and 32s** for the *entire* `provision` job (`composer install`, `npm ci`, the four submodules, `npm run build` + `npm run cp:build`, and the Stache clear), against warm Composer and npm caches. Standing a slot up here is cheap enough not to plan around, so do not defer it, and do not background it expecting minutes.
  - **`resources/forms` postdates both measurements**, so whether it fetches on either platform is not measured.
  - **Why the two differ is not established.** The object-store measurement under *Keeping a slot current* found no sharing on either platform, so it does not explain the split. Record what you measure on a new machine; do not reason a mechanism into place.
- **`APP_URL` must name the slot's own host.** The `.env` you just copied says `http://uams-statamic.test`: the *primary*. Leave it and the CP's post-login and logout **redirects** bounce the browser to the primary app on `main` while the login page itself renders from the slot. The failure is silent; only the redirect leaks. `SESSION_DOMAIN=null` + `SESSION_DRIVER=file` already scope the cookie to the request host, so once `APP_URL` matches there is no cross-domain login loop.
- **The CP requires built Vite assets, not just the front end.** Statamic 6's CP layout injects the app bundle via `@vite`, so **even `/cp/auth/login` 500s** with `Vite manifest not found at public/vendor/app/manifest.json` until they exist. `cp:build` → `public/vendor/app` is *separate* from `build` → `public/build`, and you need **both from one build** so shared dependencies resolve between them.
  - **Do not copy the primary's `public/build` / `public/vendor/app` instead.** It looks like a shortcut and has a hidden precondition: while the primary is in dev mode, Vite serves assets from the dev server and **never rebuilds** those directories, so they hold whatever a past build left: stale and mutually inconsistent. Build fresh; it is a one-time cost per slot.
  - **"Has a `public/hot` file" and "is running `npm run dev`" are not the same claim**, and the gap between them is its own failure. `public/hot` is written when the dev server starts and is **not** reliably removed when it stops, so a checkout can sit indefinitely with a hot file naming a port nothing is listening on. The app then resolves every asset to a dead `:5173` and serves a page with no working JS, while `public/build` stays as stale as dev mode left it, because nothing rebuilt it either. **Check the process, not the marker**: an orphaned `public/hot` is cleared with `rm -f public/hot` followed by `npm run build && npm run cp:build`.

History: [Provisioning a slot (one time, and once only)](../../../.claude/history/worktrees.md#provisioning-a-slot-one-time-and-once-only).

## Ephemeral worktrees

Slots cover ordinary work. A throwaway worktree is still right in exactly three cases:

- **Both slots are held** and you need a third tree now.
- **Genuinely file-disjoint parallel fan-out**: independent tickets built at the same time, one tree each, when neither slot is free for the second.
- **Subagents**: the `Agent` tool's `isolation: "worktree"`, which is a different mechanism aimed at a different problem and is unaffected by any of this.

For those, the harness `EnterWorktree` tool creates a worktree under `.claude/worktrees/<name>/` on a fresh branch from `origin/<default>` and switches the session into it. It is gated to fire only on a user request *or a project instruction*: this rule is that instruction. Three caveats:

- **`.claude/worktrees/` must be ignored by the tracked `.gitignore`**, not by a per-machine `.git/info/exclude`, so that "can never be staged by construction" holds on every clone. Confirm with `git check-ignore -v .claude/worktrees/x` if you ever doubt it.
- **Name it `t<N>-<slug>`, not `<N>-<slug>`**, for the digit/namespace reason above. The branch name is unaffected and still comes from `gh issue develop`. Verified in `uams-statamic` against the pinned Pest with a clean negative control: a `tests/Feature/9999Probe/` directory errors while an identical `tests/Feature/t9999Probe/` passes.
- **Prefer `EnterWorktree` over `git worktree add` + entering it.** Entering a worktree the harness did not create is recorded as `"enteredExisting": true` in the session's `worktreeSession` metadata and carries no `originalBranch` / `originalHeadCommit`, so the session shows no worktree marker in Claude Code's session history, and its transcript is filed under the *worktree's* project slug rather than the repo's. Wanting a specific branch name is not a reason to take the manual path: create with `EnterWorktree`, then `git checkout -b <branch>` inside it; the metadata is written on entry, so whatever branch you end up on afterwards does not affect it. Reserve the manual path for what `EnterWorktree` cannot express: adding a worktree for a **pre-existing** branch you must not re-create.

- **`pest --tia` was blind in these trees until `symfony/finder` `v8.1.8`, and `composer test:tia` refuses wherever it still is (UAMS-Web/uams-statamic-archive#1996).** Tia decides whether a cached impact graph is still valid from a structural fingerprint over `composer.lock`, `phpunit.xml` and the JS lock files, each gated on a Symfony Finder query with `ignoreVCSIgnored(true)`. Here that query returns nothing for plainly tracked files, every input resolves to `null`, `structuralMatches()` compares null to null and reports a match, so a `composer.lock` change invalidates **nothing** and `--tia` replays results recorded against a different dependency tree. Measured on macOS with pest `v5.1.1`: editing `composer.lock` moved the fingerprint in the primary and in a sibling slot (`structuralMatches()` false, correctly re-runs) and left it `NULL` on both sides under `.claude/worktrees/` (`true`, stale graph replayed). **The sibling-slot layout dodges this by accident**, so do not relocate the slots under an ignored path without re-measuring. [`scripts/check-tia-safe.php`](../../../scripts/check-tia-safe.php) measures the real fingerprint rather than inferring the shape, which is what makes the refusal retire itself once the fingerprint reads correctly.
  - **It retired through Symfony Finder, not through [`pestphp/pest#1813`](https://github.com/pestphp/pest/pull/1813), which is still open.** Measured on macOS on 2026-09-29 against the same fixture the guard's test builds (a linked worktree under an ignored `.claude/worktrees/`, at a realpath root): with `symfony/finder` `v8.1.1` and pest `v5.1.1` all three inputs read `NULL`, and with `symfony/finder` `v8.1.8` and pest `v5.2.1` all three hash, while a control worktree at a non-ignored path hashes under both. Pest's `Fingerprint` is unchanged between those two Pest versions; Finder `e43d10973` (first in `v8.1.7`) resolves the base directory's realpath, and `c8d1b2a09` (first in `v8.1.8`) treats a `.git` **file** (which is what a linked worktree has) as a repository root (#2414). The guard stays: it still refuses on an older Finder and on a tracked input Pest cannot read.
  - **The trigger is characterized, and one part of it is an observation artifact worth knowing.** Three things must hold: the tree is a linked worktree, an ancestor holds a real `.git` **directory** whose ignore rules match the worktree's path, and the root you pass is a **realpath**. Upstream names only the first two. The third matters because, before `symfony/finder` `v8.1.7`, Symfony's `VcsIgnoredFilterIterator` compares a resolved `getRealPath()` against a `baseDir` it never resolves, so a root reached through a symlink silently disengages the ignore filter: the same tree fingerprints resolved through `/var/folders/…` and NULL through `/private/var/folders/…`. The slots are safe because neither Herd's parked directory nor `$HOME` nor `/Users` is a repository, so condition two fails there.

## Committing and pushing needs nothing extra

A fresh worktree can branch, commit, and push immediately, even though `vendor/` is git-ignored and therefore absent from it. A worktree runs whatever hooks the repository configures; in `uams-statamic` and `wordpress-importer` no pre-commit hook runs tests, Pint or PHPStan, and neither has a pre-push hook. Running the checks *does* need a bootstrap, which is what provisioning is for. A push that fails on a missing `vendor/` is an un-bootstrapped *environment*, not a real defect: **bootstrap, don't `--no-verify`**.

This repo's only git hook is [`.githooks/pre-commit`](../../../.githooks/pre-commit), and it has two gates: a CI-parity gate that blocks a commit staging exactly one of the two mirrored CI files (`.github/workflows/ci.yml` / `scripts/local-ci.mjs`), and a merge-resolution gate that blocks a merge commit with resolved-but-unstaged files.

## Serving a slot

Each slot has its own permanent Herd site at `<dirname>.test`: no `herd link`, no port, no dev server. That removes the whole class of "am I looking at the wrong app?" failures that a nested worktree has to work around. Two residual traps:

- **`APP_URL` must match the host you are visiting** (provisioning step 4). This is the one that survives, because it is per-slot configuration rather than a port conflict.
- **Stale compiled views.** If `storage/framework/views` carries compiled Blade with mtimes ≥ your edited source, the framework serves the **old** UI no matter how you edit. A long-lived slot accumulates these, so this bites *more* here than it did with fresh worktrees. Run `php artisan view:clear` (and `optimize:clear`) before trusting anything rendered.

If you do run a dev server anyway, `composer dev` hard-codes `php artisan serve` on `:8000` and Vite on `:5173`. A second `artisan serve` can also bind `:8000` and connections route **nondeterministically** between the two: the browser may load the other checkout's code and data. Diagnose on Windows with PowerShell's `Get-NetTCPConnection -LocalPort 8000 -State Listen` (two rows = collision; `OwningProcess` is the PID to resolve before killing anything, per [`long-running-commands`](../rule-long-running-commands/SKILL.md)); on macOS with `lsof -nP -iTCP:8000 -sTCP:LISTEN`. Serving the slot's `.test` host avoids all of it.

## File-tool vs. shell-cwd hazards (harness)

These matter **more** with long-lived slots, not less: every slot is always present, always on some branch, and always a plausible destination for a misdirected write.

- **`EnterWorktree` switches the shell cwd, but `Edit`/`Write`/`Read` act on the absolute path you pass.** Keep passing a primary-checkout path and your edits land on the primary, on `main`. Always pass the **slot's** absolute path (in your platform's own form) to the file tools, and `Read` the slot's copy before editing it: read-state is tracked per absolute path. If work lands on the wrong tree: `cp` the changed files where they belonged, then `git -C <wrong-tree> checkout -- <file>` (or delete the stray untracked file) to restore it.
- **The Bash session cwd silently reverts between calls.** Sometimes it prints `Shell cwd was reset to …`; sometimes it is simply back at the primary several calls later with nothing said. A backgrounded command inherits whatever the cwd is at launch. The failure is a **false green**: a verification run meant to exercise your branch instead exercises `main` and passes, proving nothing. Put an explicit `cd` in **every** command, and bake a guard into anything long or backgrounded so the output proves which tree ran:

  ```bash
  cd ../<repo>-a && echo "CWD=$(pwd) HEAD=$(git rev-parse --short HEAD)" && <the real command>
  ```

  With slots this guard matters more than with per-ticket trees, because slots do not differ by name: `<repo>-a` and `<repo>-b` look alike, and only `HEAD` distinguishes them.

  **It is not only the primary, and it is not only runs.** The stale cwd is just as likely to point at the *other slot*, so "did it land on `main`?" is the wrong check; "which tree did it land on?" is the right one. And it corrupts **writes**, not just verification: an in-place shell edit (`sed -i`, a `python` heredoc, `cat >`) with no `cd` of its own silently rewrites the other tree's file, leaving the one you meant to edit untouched and green. Prefer the file tools with **absolute** paths for edits; when a shell edit is genuinely easier, give that command its own `cd`.
- **`git checkout -- <file>` reverts the file to HEAD: it discards *all* uncommitted work on it, not just your last change.** Use it ONLY where the file *should* match HEAD (restoring a tree you wrote to by mistake). **Never** reach for it to "undo" a *temporary* edit on a file that holds uncommitted work you want to keep: e.g. hand-mutation-verifying a template change (revert the fix → expect-red → restore), where the file is template-only (so `composer mutate` cannot help) and the work is not committed yet. Restore the temporary mutation with its **inverse** edit, or `cp` the file aside first. If it happens anyway, a never-committed file is often rebuildable by cross-checking three sources: the still-on-disk **compiled view** where the framework keeps one (Laravel: `storage/framework/views/<hash>.php`, the exact last-rendered state), the session **transcript**'s `Read`/`Edit` results, and **HEAD** for the unchanged scaffolding: then re-verified green before trusting it.
- **The stash stack is one instance of a class this corpus had never named: a SHARED READ-MODIFY-WRITE SURFACE.** Something two actors can read, change and write back, with no locking and no arbitration, so the last writer wins and the loser is never told.

  | Surface | Shared between | Safe operation | Destructive operation |
  | --- | --- | --- | --- |
  | git stash stack | every worktree of one repo | a WIP commit, or `cp` the file aside | `stash push` / bare `pop` |
  | project memory | every session on a machine | append a new file, one fact per file | rewriting a shared file whole |
  | GitHub issue / PR body | everyone, over the network | post a comment | editing the body |

  **Append cannot clobber; rewrite can.** That is mechanical and does not depend on anyone remembering to be careful, which matters, because the losses observed here happened *between sessions that were actively coordinating with each other at the time*.

  **Worktree slots do NOT partition project memory.** `~/.claude/projects/<slug>/memory/` resolves to one directory per repository (keyed to the primary checkout's slug, not to the worktree) so slot A, slot B and any other concurrent session on a machine read and write the same files, and no per-slot directory exists. Isolation that stops at the repo boundary still reads as isolation, which is what makes it dangerous. Observed in `wordpress-importer` on 2026-09-05: two sessions rewrote the same memory file wholesale within a minute; one write landed and the other was lost, recoverable only because a copy had been taken outside the directory. **Never write a memory file whole.** Anchor on exact text you expect to be there, assert it appears exactly once, and replace only that:

  ```python
  old = "<exact text I expect to be there>"
  assert s.count(old) == 1, "anchor moved - someone else edited this"
  s = s.replace(old, new)
  ```

  The assert turns a silent clobber into an aborted edit, and catches the opposite fault too: an anchor appearing twice, where `replace` would hit both. **The protection is in the FORM of the edit, not the care of the editor:** a session that used this pattern seven times in one evening still made one truncating rewrite among them, which would have destroyed a concurrent append just as a whole-file write does.

  **An assert-then-replace guard is necessary and NOT sufficient, and it will be proposed by anyone who has not watched it fail.** Confirming your anchor is still present before writing proves *this* edit lands where intended. It proves nothing about a concurrent edit **elsewhere in the same artifact**, and that is the common case rather than the edge, because two writers usually append to different sections.

  **A guard that assumes it is the only writer detects only the races that start after it does.** A base-hash check compares the remote against your copy immediately before writing, so it catches an edit landing *between* your read and your write. An edit that landed *before* your read is already in your copy and the hashes match honestly. Nothing in the output distinguishes "no race" from "a race I was too late to see", which is worse than no guard, because it licenses confidence.

  **Delete only what you wrote, and confirm it is still what you wrote.** Not a mechanical check, which is why it needs stating: an editor removing a block it authored must verify the block is still its own content, not merely that something sits where it left it. The observed clobber replaced a section whose owner had altered it between the read and the write. The same hazard on a shared *remote* artifact (an issue or pull-request body) belongs to [`filing-defects-across-repos`](../rule-filing-defects-across-repos/SKILL.md); this bullet is the local half.

- **The stash stack belongs to the *repository*, not the worktree, so `git stash pop` in your slot pops whatever another session pushed.** Every linked worktree shares one stack, and two mistakes chain into losing someone else's work. First, `git stash push -- <paths>` **fails outright** if any named path is untracked (`error: pathspec '…' did not match any file(s) known to git`) and stashes **nothing**: easy to miss inside a `&&` chain, or when you only read the last line. Then the paired `pop` applies the entry that was already on top: another session's, or an ancient GitHub Desktop one. **Never use the stash to set work aside or to stage a temporary revert**: prefer a WIP commit, or `cp` the file aside (see the negative-control note in [`adversarial-review`](../rule-adversarial-review/SKILL.md)). If you must stash: `git stash push -u -m "<unique-tag>"`, capture the SHA from `git stash list --format='%H %gs'`, and `git stash apply <sha>`: never bare `pop`.
  - **Recovery, if it happens anyway.** `pop` drops the reflog entry but the commit is merely unreachable, so it can be put back:

    ```bash
    git fsck --unreachable --no-progress | awk '/commit/ {print $3}' |
      while read c; do git log -1 --format="$c %s" "$c"; done | grep -E ' (On|WIP on) '
    git stash store -m "<the original message>" <sha>       # back on top of the stack
    ```

    Then revert the popped files out of your tree: `git restore --staged` + `rm` for its untracked adds, `git checkout --` for tracked ones (safe *here*, per the bullet above, because they legitimately should match HEAD). Confirm with `git stash list` that the stack is the depth it was.

History: [File-tool vs. shell-cwd hazards (harness)](../../../.claude/history/worktrees.md#file-tool-vs-shell-cwd-hazards-harness).

## Rebuilding a slot, and removing an ephemeral worktree

A slot is normally never removed: that is the point. Rebuild one only when its bootstrap is beyond refreshing (a corrupted `vendor/`, a wedged submodule). The removal procedure below is the same one an ephemeral worktree needs.

**`git worktree remove --force` is the normal path and it works, and the old folklore about submodules blocking it is wrong.** Measured directly in `wordpress-importer` (git 2.39.5 Apple Git-154, macOS) on a slot with the exports submodule fully initialized, and in `uams-statamic` (git 2.32.0.windows.2, Windows) with `content` initialized: exit 0, tree deleted, deregistered. The long-standing claim that an initialized submodule makes removal refuse **did not reproduce**, and has been removed rather than softened.

**`git worktree move` is init-gated, not declaration-gated.** With no submodule initialized it succeeds; with any one left it fails `fatal: working trees containing submodules cannot be moved or removed`, exit 128, and the shared error message naming both operations is precisely why the folklore stuck. Deinit every initialized submodule before a move: a repository declaring **four** has to `git submodule deinit -f --all`, because deinit-ing only one leaves three and the move still refuses: exactly the trap hit in `uams-statamic` when it declared four, where deinit-ing only `content` was mistaken for proof that deinit does not help. A repository declaring one needs only that one. So a slot *can* be relocated: deinit, `git worktree move`, re-init if needed. The alternative is an OS-level rename plus `git worktree repair <new-path>` to fix the linkage.

Reach for **`rm -rf <path>` then `git worktree prune`** only when something outside git blocks removal: an OS handle, or untracked scratch you have already triaged. The ways that happens, most of them observed on Windows:

1. **Get every shell's cwd *out* of the tree first.** On Windows a shell whose working directory sits inside it **holds a lock**: `rm -rf` then fails `Device or resource busy` and `git worktree remove` cannot delete it. (Corollary: after a removal, invoke a local CI runner by **absolute path**: a cwd stranded in a deleted tree makes the relative path resolve to the gone copy, and the runner never starts.)
2. **Kill any watcher started in it**: `composer dev` / `npm run dev` (Vite) / `php artisan serve` holds handles the same way.
3. **An OS-level rename can itself be denied** (`Permission denied` from `mv`, `Access to the path … is denied` from `Rename-Item`) while a handle is open on the directory. Observed in `uams-statamic` on Windows, and worth knowing in this order: the `readlink /proc/*/cwd` sweep from [`long-running-commands`](../rule-long-running-commands/SKILL.md) came back **empty**, so an empty sweep does not mean unlocked: it only sees MSYS processes, and `Win32_Process` exposes no working directory at all. **Localize the lock before reacting to it.** Writing a file inside the directory and renaming one of its *subdirectories* both succeeding, while renaming the top level fails, means a single handle on the folder itself: most often a shell that recently `cd`'d there, an editor window rooted on it, or Herd watching it as a parked site. **A top-level lock does not block the delete**, which is what makes this a non-event: `rm -rf` removes the contents regardless and fails only on the final `rmdir`, leaving an empty directory that `git worktree prune` still deregisters. These handles are also often transient: the same directory that refused two rename attempts deleted cleanly, top level included, a few minutes later. So do not go hunting the holder, and do not reboot: delete the contents, prune, and let the tool recreate the worktree under the name you wanted.
4. **Untracked scratch also blocks removal.** *Look before you delete*: a worktree accretes stray files (import XML, generated fixtures); copy anything worth keeping to the scratchpad first.
5. **Delete any junction as its own step, before `rm -rf`**: remove the *link*, never the target: `[System.IO.Directory]::Delete($path, $false)` in PowerShell, or `cmd //c rmdir <path>`. Then confirm the target still exists. A recursive delete that follows a reparse point takes the primary's `node_modules` (or another tree's submodule) with it, and nothing tells you until the next command fails.

The branch is preserved by every path above. **Recovery from a half-delete:** if `rm -rf` removed the `.git` file but stalled on a locked one, `git worktree remove --force` then errors (`'…/.git' does not exist`) and the branch stays pinned to the dead tree (`git worktree list` shows it `prunable`). `git worktree prune --expire=now` drops the stale admin entry and **frees the branch**: enough to reuse it even while leftover files linger; clear those once the lock releases, usually the instant a stranded shell cwd moves off it (step 1).

**Machine-global tool caches outlive a removed tree and record its paths.** Where PHPStan's `tmpDir` is the default, its container/stub cache in the system temp directory (`%TEMP%\phpstan` on Windows, PHP's `sys_get_temp_dir()` elsewhere) pins the absolute phar path of whichever checkout built it, so after a tree is deleted PHPStan can fail from *any* checkout with `"phar://…/<deleted-tree>/vendor/phpstan/phpstan.phar/…" is not a file` (observed in `uams-statamic`). `clear-result-cache` does **not** fix this; it clears a different file. Point the run at a private cache rather than wiping a shared directory a concurrent session may be mid-analysis on: write a throwaway config that `includes:` the repo's `phpstan.neon.dist` and sets `parameters: tmpDir:`, then `phpstan analyse -c <that config>`. There is no `--tmp-dir` flag. A repository whose `phpstan.neon.dist` pins `tmpDir` to a path relative to the config file avoids this entirely: each tree gets its own cache inside itself and no shared system-temp directory is involved; what remains there is ordinary reuse staleness in a long-lived tree, which `clear-result-cache` addresses and deleting the cache directory in that tree is the blunt form of. Stable slots make the machine-global case rare either way: it is triggered by paths *dying*, which is exactly what they stop doing.

The PHPStan cache hazard is the same family as Rector's rule-set-blind file cache, see the Rector rule in [`AGENTS.md`](../../../AGENTS.md).

## Where it lands / the DRY line

This file is the standing statement on **worktree lifecycle**: the slot model, claiming and releasing, the refresh gate, provisioning, and removal. `AGENTS.md` carries a one-line pointer here; the `EnterWorktree` / `ExitWorktree` *mechanics* live in those tools' own descriptions: don't restate them. It composes with the branch → PR → merge flow in `AGENTS.md` (a slot changes *where* you branch, not *how* you ship), with [`sync-pr-branch`](../rule-sync-pr-branch/SKILL.md) (which owns bringing a *branch* current: this rule owns refreshing the *tree* that branch sits in), and with [`long-running-commands`](../rule-long-running-commands/SKILL.md) (which owns bounding a run and attributing a process to the tree that started it, and not trampling one somebody else owns once isolated).

The `local-ci` skill owns the CI tree entirely, and the chain that runs in it.

It also composes **across repositories**: this file owns the importer slot's *existence, claim and release*, while the sibling `wordpress-importer` repo's [`host-app-tests`](https://github.com/UAMS-Web/wordpress-importer/blob/main/.claude/skills/host-app-tests/SKILL.md) skill owns what actually happens inside it: pointing `vendor/` at a branch, which test files to run, and how to read a failure. Neither restates the other.

