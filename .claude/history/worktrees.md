# History: Rule — work from stable, pre-bootstrapped worktree slots (never share the primary checkout)

The incident history behind [`.claude/rules/worktrees.md`](../rules/worktrees.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## The slots

**They are siblings, not nested under `.claude/worktrees/`, for four reasons.** Herd parks the directory the primary sits in, so each sibling is auto-served at `<dirname>.test` with no `herd link` and no port — which dissolves the port-collision and `APP_URL` traps that a nested worktree has to work around (see *Serving a slot*). They sit outside the repo, so no ignore rule has to hold for them. A search from the primary cannot descend into them. And it matches where the local-CI runner already puts its worktree.

> **Verified on Windows Herd and on macOS Herd** — `herd paths` lists the parked directory, and `herd sites` shows every directory under it already resolving to `<dirname>.test`. On macOS 26.4 (UAMS-Web/uams-statamic-archive#1903) `herd paths` returns `~/Herd/`, and `herd sites` lists the primary and all three slots at `uams-statamic{,-a,-b,-ci}.test` on PHP 8.4, each serving `200`. The importer slot inherits the same treatment with no extra step — `uams-statamic-importer.test` answered `200` on both `/` and `/cp/auth/login` the moment it was provisioned. So the URL holds on both platforms without a `herd link`.

## The importer slot

**Why a dedicated tree rather than a third general slot.** Borrowing costs three things, and none of them is capacity:

- **It blocks local CI.** A borrowed slot sits on a branch, and the runner checks branches out *by name* — so the validating run fails with `fatal: '<branch>' is already used by worktree at …`.

- **The occupancy signal lies.** A slot on `importer-433` reads as this app's ticket work to anyone running `status`. Nothing distinguishes "an importer run is in flight" from "someone is mid-ticket".

- **It halves the concurrency budget.** One host session plus one importer run exhausts A and B, pushing the next session to an ephemeral worktree — paying exactly the bootstrap the slot model exists to avoid.

Clean-on-a-branch is not an edge state — it is where a ticket sits after every commit, and for the whole stretch between the last commit and the pull request — so the unguarded command was releasable by anyone for most of a ticket's life. Nothing is lost when it happens (`checkout --detach` preserves the branch); what is taken is the holder's `HEAD`, and the tell is that their *next* commit lands detached, well after the cause.

## Keeping a slot current — the refresh gate

Five sessions across four repositories hit this on this one command inside ten minutes on 2026-09-06, two of them publishing a drift-free verdict that was wrong, and one had merged the fix for the same trap earlier the same session — **so this is not a caution that attention discharges.**

Observed end to end on Windows, on a slot two merges behind — `status --check` clean, `refresh` reporting `is current` with `HEAD` unmoved, then the same command reporting `base: 1 behind` seconds later once `release` had moved the tree. The two runs differed only in which copy of the script executed. The version boundary is `UAMS-Web/uams-statamic-archive#2191`, measured on macOS: the file at `3fc17c52b` contains no base check at all, and every revision from `e92c4ebcf` onward does.

— an entire bisect was invalidated here by taking those environmental nulls at face value, and it nearly shipped as "the new importer version breaks Gravity Forms / Formidable / the KB."

Verified: three uninitialized submodules in the local-CI worktree every one reporting `163b300df`, the commit the superproject was sitting on.

It read as a regression in a just-merged fix on two machines in one evening (UAMS-Web/uams-statamic-archive#2252).

`git -C <tree>/content rev-parse --absolute-git-dir` gives the primary `.git/modules/content` and every linked worktree `.git/worktrees/<name>/modules/content`. They all sit *inside* the primary's `.git`, which is what makes the shared-storage reading look plausible, but the paths are distinct files.

The confirming observation was a live one rather than a contrived probe: four trees on four branches carrying **two different `content` revisions**, and every tree's `content` HEAD matching its *own* superproject gitlink — the CI slot validating a branch at one content commit while the primary served the site from another. That state cannot exist on a shared HEAD.

```bash
for t in <primary> <slot-a> <slot-b> <ci>; do
  printf '%-20s %-10s %s\n' "$t" \
    "$(git -C "$t/content" rev-parse --short HEAD)" \
    "$(git -C "$t/content" rev-parse --absolute-git-dir)"
done
```

## Provisioning a slot (one time, and once only)

Three slots were provisioned and signed off here on a CP check alone before anyone loaded the front page.

Copying that yields a CP whose `/cp/auth/login` still **200s** (the manifest exists, so no 500 — exactly the check the copy was meant to satisfy) but whose Vue app is dead: the console shows `Uncaught TypeError: Failed to resolve module specifier "dompurify"`, because the addon bundle expects the core bundle to provide it and the two came from different builds. `curl` cannot see this, because the JS never runs.

Found on the primary here: a hot file pointing at `uams-statamic.test:5173` with no Vite process alive, over a stale build whose manifests predated the `@alpinejs/csp` migration by a month.

## File-tool vs. shell-cwd hazards (harness)

Observed twice — once running an app-side test against a stale vendored dependency, once "confirming" two serial-tail failures that were actually re-run against `main`.

Observed on a six-ticket wave: a header-comment fix landed in a different ticket's tree and was found only by a `git status` during teardown, hours later.

The stash entry below is the instance the slot model makes obvious; the others are not about worktrees at all, which is why they went undocumented while this one was written up in detail.

Observed failing exactly that way: the guard ran, matched honestly, and reported nothing while a section was lost.

Observed here — a `stash push` naming a not-yet-added `config/tables.php` failed, and the `pop` restored a stale `!!GitHub_Desktop<611-…>` stash over the working tree: two staged log files, a `package-lock.json` revert, and a deleted `.gitignore`, none of them that session's.
