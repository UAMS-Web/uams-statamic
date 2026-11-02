#!/usr/bin/env node
/**
 * worktree-slots.mjs — drive the stable worktree slots described in `.claude/rules/worktrees.md`.
 *
 * Branch work happens in a fixed set of long-lived, pre-bootstrapped worktrees ("slots") rather
 * than a throwaway worktree per ticket (UAMS-Web/uams-statamic-archive#1945). That removes the per-ticket bootstrap, and adds
 * exactly one risk in its place: a slot whose dependencies have drifted from the branch it is
 * sitting on. The old model failed *loudly* — no `vendor/`, hard error. A stale slot fails
 * *silently*: the tests run, pass, and pass against the wrong versions.
 *
 * That is not hypothetical. The primary checkout was found carrying a `node_modules/` that
 * predated the `@alpinejs/csp` migration by a month; one suite could not import at all, and
 * nothing reported it. The rule calls the refresh a gate, but a gate that depends on remembering
 * four commands is not a gate — so this script is the gate, and `status` is how the drift becomes
 * visible before it is trusted.
 *
 * Subcommands:
 *   status              every tree, its branch, whether it is free, and any detected drift
 *   refresh <target>    reconcile vendor/, node_modules/ and the submodules, then clear the Stache
 *                       and rebuild the test content mirror if it predates content/ (UAMS-Web/uams-statamic-archive#2935)
 *   release <slot>      assert the tree is clean and not held, then detach to origin/main
 *                       --branch <name>  release a slot still on that branch, deliberately
 *   provision <slot>    create and bootstrap a slot from scratch
 *
 * `refresh` takes `primary` as well as a slot name; `release` and `provision` do not, because the
 * primary must keep `main` checked out and is the clone rather than a worktree of it. Each tree is
 * judged against its own kind: the CI slot is runner-owned, so `scripts/local-ci.mjs` bootstrapping
 * only `content` and `users` there is the expected state and not drift (UAMS-Web/uams-statamic-archive#1952, UAMS-Web/uams-statamic-archive#2477).
 *
 * `composer install` is idempotent and cheap, so refresh simply runs it. `npm ci` is NOT — it
 * deletes `node_modules` first, so running it unconditionally turns a no-op into a multi-minute
 * rebuild. It is therefore gated on a lockfile-hash stamp, following the patch-stamp idiom
 * `local-ci.mjs` uses for the same shape of problem (UAMS-Web/uams-statamic-archive#1743).
 *
 * A submodule is judged on **two** axes, because matching one proves nothing about the other. Its
 * recorded-vs-actual revision answers "is this tree pointing where the branch says", and its
 * working tree answers "is the content actually there". UAMS-Web/uams-statamic-archive#2023 is what happens when only the first
 * is asked: a slot carrying 1,696 staged deletions — every tracked file in `public/assets` and
 * `app/private/assets` — reported `free` with no drift and would have released cleanly, because
 * both submodules sat at exactly the revisions their gitlinks recorded.
 *
 * Exit codes make it usable as a shell guard:
 *   0  the operation completed, or `status` found no drift
 *   1  refused, or `status` found drift (with `--check`)
 *   2  error (not a git repo, unknown slot, git/composer/npm unavailable)
 *
 * Usage:
 *   node scripts/worktree-slots.mjs status [--json] [--check]
 *   node scripts/worktree-slots.mjs refresh a          (or: refresh primary)
 *   node scripts/worktree-slots.mjs release a
 *   node scripts/worktree-slots.mjs provision b
 */

import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, realpathSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { basename, dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';
// Imported, not re-derived: UAMS-Web/uams-statamic-archive#1743 solved this exact problem for the local-CI worktree, and two
// copies of the rule are two things to keep in step. See `patchStaleReason` at its use below.
import { GATE_SUBMODULES, patchedPackages, patchStaleReason, readPatchLockHash } from './gate-slot.mjs';
import { primaryCheckout } from './repo-root.mjs';
import { needsShell, which, winQuote } from './spawn-helpers.mjs';

/**
 * Where the `npm ci` stamp lives.
 *
 * Deliberately inside `node_modules/`, which buys two properties no other location does. It is
 * already git-ignored, so the stamp can never show up as untracked and break the clean-tree check
 * `release` depends on. And `npm ci` deletes `node_modules` wholesale, so a hand-deleted or
 * half-installed tree loses its stamp with it and is correctly re-read as stale — the stamp cannot
 * outlive the thing it describes.
 */
const STAMP_PATH = join('node_modules', '.worktree-slot-stamp.json');

/**
 * The command that rebuilds the test-environment content mirror from `content/`.
 *
 * `test:seed-stache` refuses outside `APP_ENV=testing`, and refuses while a test process holds
 * the mirror (UAMS-Web/uams-statamic-archive#2764), so running it here cannot tear a band in flight.
 */
const CONTENT_MIRROR_SEED = Object.freeze({
    command: 'DB_DATABASE=:memory: APP_ENV=testing php artisan test:seed-stache',
    env: Object.freeze({ DB_DATABASE: ':memory:', APP_ENV: 'testing' }),
    args: Object.freeze(['artisan', 'test:seed-stache']),
});

/** Submodules this repo declares, in the order `provision` initializes them. */
export const SUBMODULES = Object.freeze(['content', 'users', 'resources/forms', 'public/assets', 'app/private/assets']);

/** Tree kinds. `slot` is the ordinary case; the other three are judged by their own expectations. */
const PRIMARY = 'primary';
const CI = 'ci';
const SLOT = 'slot';
const EPHEMERAL = 'ephemeral';

/**
 * The git-ignored runtime databases a served slot needs, copied from the primary.
 *
 * There are **two**, and missing the second is a silent half-provision: the CP still reaches 200
 * (it never touches asset metadata) while every front-end page 500s with `Database file at path
 * […/asset-metadata.sqlite] does not exist`. Verifying a new slot by loading `/cp/auth/login`
 * alone therefore reports success on a site that does not render — which is exactly how three
 * slots were provisioned and signed off here before anyone loaded a front-end page.
 *
 * Running `php artisan migrate` does not fix it. The migration that creates the `assets_meta`
 * table is recorded in the *copied* `database.sqlite`, so Laravel sees it as already applied and
 * skips it, while the separate file it was supposed to create never appears.
 */
const RUNTIME_DATABASES = Object.freeze([
    ['database', 'database.sqlite'],
    ['storage', 'framework', 'cache', 'asset-metadata.sqlite'],
]);

/**
 * Normalize a filesystem path for comparison.
 *
 * `git worktree list --porcelain` prints forward slashes on every platform, while Node's `join`
 * yields backslashes on Windows — so a path built here never string-matches one git reported, and
 * every slot would resolve as "not a registered worktree". Case is folded because Windows paths
 * are case-insensitive and git's casing of the drive letter is not guaranteed to match Node's.
 *
 * @param  string  $value
 * @return string
 */
export function normalizePath(value) {
    return String(value).replace(/\\/g, '/').replace(/\/+$/, '').toLowerCase();
}

/**
 * Parse `git worktree list --porcelain` into one record per tree.
 *
 * Records are separated by a blank line. `branch refs/heads/<name>` is present only when the tree
 * has a branch checked out; a detached tree carries a bare `detached` line instead, which is the
 * resting state a free slot sits in.
 *
 * @param  string  $porcelain
 * @return array<int, {path: string, head: ?string, branch: ?string, detached: bool}>
 */
export function parseWorktreeList(porcelain) {
    return String(porcelain)
        .split(/\n\s*\n/)
        .map((block) => block.trim())
        .filter(Boolean)
        .map((block) => {
            const record = { path: null, head: null, branch: null, detached: false };

            for (const line of block.split('\n')) {
                const [key, ...rest] = line.trim().split(' ');
                const value = rest.join(' ');

                if (key === 'worktree') { record.path = value; }
                if (key === 'HEAD') { record.head = value; }
                if (key === 'branch') { record.branch = value.replace(/^refs\/heads\//, ''); }
                if (key === 'detached') { record.detached = true; }
            }

            return record;
        })
        .filter((record) => record.path);
}

/**
 * The directory name a slot takes, derived from the primary checkout rather than hard-coded.
 *
 * The rule states slot paths relative to the primary (`../<primary>-a`) precisely so they do not
 * name one machine's absolute path, and deriving the prefix here keeps that true for a clone under
 * any directory name.
 *
 * @param  string  $repoRoot
 * @param  string  $name
 * @return string
 */
export function slotDirName(repoRoot, name) {
    return `${basename(repoRoot)}-${name}`;
}

/**
 * Resolve a target name to the worktree git actually knows about.
 *
 * Refuses rather than guessing when the resolved path is not a registered worktree. The whole
 * point of a slot is that operations land on a known tree; acting on an unregistered path would
 * silently install into, or detach, whatever happens to sit there.
 *
 * `primary` names the repo root itself rather than a `<basename>-primary` sibling. It is a real
 * target, not a convenience: the primary is a tree people work in, `status` reports its drift, and
 * before UAMS-Web/uams-statamic-archive#1952 nothing could act on it — so the one tree where a stale `node_modules` was actually
 * found (a month behind the `@alpinejs/csp` migration) was the one tree `refresh` could not reach.
 *
 * @param  array   $worktrees
 * @param  string  $repoRoot
 * @param  string  $name
 * @return object
 */
export function resolveSlot(worktrees, repoRoot, name) {
    if (!name || !/^[A-Za-z][\w-]*$/.test(name)) {
        throw new Error(`Invalid slot name '${name}'. Slot names are alphanumeric and start with a letter (primary, a, b, ci, importer).`);
    }

    const path = name === PRIMARY ? repoRoot : join(dirname(repoRoot), slotDirName(repoRoot, name));
    const match = worktrees.find((tree) => normalizePath(tree.path) === normalizePath(path));

    if (!match) {
        const known = worktrees.map((tree) => basename(tree.path)).join(', ');
        // No `provision primary` exists to suggest — the primary is the clone itself.
        const hint = name === PRIMARY ? '' : ` Run 'provision ${name}' to create it.`;

        throw new Error(`No registered worktree at ${path}. Registered: ${known}.${hint}`);
    }

    return match;
}

/**
 * Which kind of tree this is, which decides what it is expected to carry.
 *
 * @param  string  $treePath
 * @param  string  $repoRoot
 * @return string
 */
export function treeKind(treePath, repoRoot) {
    if (normalizePath(treePath) === normalizePath(repoRoot)) {
        return PRIMARY;
    }

    if (basename(treePath) === slotDirName(repoRoot, CI)) {
        return CI;
    }

    // A slot is a SIBLING of the primary named `<primary-basename>-<name>` — that is what
    // `slotDirName` builds and what `resolveSlot` looks for. Anything else git has registered is an
    // ephemeral worktree (`.claude/worktrees/…`, a subagent's isolated tree, a hand-made one), and
    // judging those as slots is a bug with the same shape as the CI slot's: they never carry a
    // bootstrap, `refresh` cannot address them because their path is not `<basename>-<name>`, and
    // they would therefore pin `--check` at 1 for as long as one exists.
    const sibling = normalizePath(dirname(treePath)) === normalizePath(dirname(repoRoot))
        && basename(treePath).startsWith(`${basename(repoRoot)}-`);

    return sibling ? SLOT : EPHEMERAL;
}

/**
 * What a tree of this kind must have before it can be trusted.
 *
 * Only a slot and the primary are held to the full bootstrap, because they are the only trees this
 * script owns. Two exceptions, both deliberate:
 *
 * The **CI slot** is runner-owned. `.claude/rules/worktrees.md` holds it out of pre-provisioning
 * because no session's working directory sits in it and `scripts/local-ci.mjs` bootstraps it on
 * every run — initializing only `content`, `users` and `resources/forms` (UAMS-Web/uams-statamic-archive#2477, UAMS-Web/uams-statamic-archive#2818), and running its own installs
 * without writing this script's stamp. Judging it by a slot's expectations reported four permanent
 * drift lines for a tree in exactly the state it is supposed to be in, which is half of why
 * `--check` could never exit 0 (UAMS-Web/uams-statamic-archive#1952). A missing `content/`, `users/` or `resources/forms/` there
 * is still drift: those three the runner does need.
 *
 * An **ephemeral** worktree is nobody's to keep current. Whoever created it bootstraps whatever
 * that narrow task needs and tears it down after, so this script reports its branch and stays out
 * of the way rather than holding a temporary tree to a slot's standard forever.
 *
 * The **base** check follows the same ownership logic rather than a new one. A slot and the primary
 * are trees this script parks at `origin/main`, so their base is its to judge. The CI slot's base
 * is the runner's — `local-ci.mjs` checks arbitrary branches in and out of it — and an ephemeral
 * tree's is whoever made it. Judging either would report permanent drift on a tree doing exactly
 * what it is for, which is the UAMS-Web/uams-statamic-archive#1952 failure this function exists to prevent.
 *
 * @param  string  $kind
 * @return {{checkInstall: bool, checkBase: bool, checkContentMirror: bool, submodules: array<int, string>}}
 */
export function expectationsFor(kind) {
    // The content mirror follows install: the CI slot's runner seeds its own before every run, and
    // an ephemeral tree is nobody's to keep current (UAMS-Web/uams-statamic-archive#2935)
    if (kind === CI) {
        return { checkInstall: false, checkBase: false, checkContentMirror: false, submodules: [...GATE_SUBMODULES] };
    }

    if (kind === EPHEMERAL) {
        return { checkInstall: false, checkBase: false, checkContentMirror: false, submodules: [] };
    }

    return { checkInstall: true, checkBase: true, checkContentMirror: true, submodules: [...SUBMODULES] };
}

/**
 * Classify a tree and report only the drift its kind actually cares about.
 *
 * This composition is the seam that decides `status --check`'s exit code, and it is deliberately a
 * separate exported function rather than two calls inline in `inspect`: `driftReasons` defaults to
 * the STRICT reading when `expected` is omitted, so dropping the wiring silently restores the
 * UAMS-Web/uams-statamic-archive#1952 bug. Testing the halves in isolation cannot catch that — an adversarial review of this very
 * change confirmed the mutation survives a green suite — so the composition is what gets tested.
 *
 * @param  string  $treePath
 * @param  string  $repoRoot
 * @param  {{lockHash: ?string, stampHash: ?string, composerHash: ?string, stampComposerHash: ?string, submodules: array}}  $probe
 * @return {{kind: string, drift: array<int, string>, notices: array<int, string>}}
 */
export function judgeTree(treePath, repoRoot, probe) {
    const kind = treeKind(treePath, repoRoot);

    const expected = expectationsFor(kind);
    const { notice } = expected.checkBase ? baseState(probe.base) : { notice: null };

    // Notices are reported and NOT counted toward `--check`. Folding them into `drift` would fault
    // a tree for a measurement that failed rather than for a state that is wrong; dropping them
    // would restore the silence UAMS-Web/uams-statamic-archive#2148 is about. They are a third category on purpose.
    const notices = [notice, expected.checkInstall ? puppeteerBrowserNotice(probe.puppeteerBrowser) : null].filter(Boolean);

    return { kind, drift: driftReasons({ ...probe, expected }), notices };
}

/**
 * Whether a slot is free to claim.
 *
 * A slot at rest is **detached** at `origin/main`, never on `main` — git allows a branch in one
 * worktree only, so `main` lives in the primary and two slots could not both rest on it. A slot
 * carrying a branch is held, by an earlier session or a live one.
 *
 * @param  object  $worktree
 * @return string
 */
export function slotOccupancy(worktree) {
    return worktree.branch ? 'held' : 'free';
}

/**
 * Decide whether `node_modules` needs reinstalling, mirroring `patchStaleReason` in `gate-slot.mjs`.
 *
 * A missing stamp means unknown — a cold install, or a tree from before this script existed — and
 * is treated as stale. Reinstalling costs minutes; a false "fresh" is the silent-wrong-versions
 * failure this whole script exists to prevent, so the asymmetry is deliberate.
 *
 * @param  ?string  $lockHash
 * @param  ?string  $stampHash
 * @return ?string
 */
export function installStaleReason(lockHash, stampHash) {
    if (!lockHash) {
        // No lockfile to compare against; nothing declared, nothing to verify.
        return null;
    }
    if (!stampHash) {
        return 'no record of a previous install';
    }
    if (stampHash !== lockHash) {
        return `lockfile changed (${stampHash.slice(0, 12)} -> ${lockHash.slice(0, 12)})`;
    }

    return null;
}

/**
 * Whether a tree's base has fallen behind `origin/main`, and whether that could be measured.
 *
 * The five checks before this one judge a tree's **environment** — installs, patches, submodules.
 * None looked at the commit the tree sits on, so a slot released a week ago reported identically to
 * one released a minute ago (UAMS-Web/uams-statamic-archive#2148). `refresh` reconciles the environment and does not move the
 * base; `release` is the verb for that, and the reason names it.
 *
 * **Only a tree at rest is judged.** A slot carrying a `<N>-<slug>` branch is expected to be behind
 * — that is `sync-pr-branch`'s business, and duplicating it here would flag every branch the moment
 * `main` moved. The primary is judged because it holds `main` rather than a detached HEAD.
 *
 * **A distance that could not be measured is reported and NOT faulted**, which is why a `notice` is
 * returned separately from a `reason`. Faulting it would red `--check` permanently on a checkout
 * whose remote-tracking ref is merely stale — the UAMS-Web/uams-statamic-archive#1952 shape `expectationsFor` exists to prevent, arriving through
 * a new door — and staying silent is the opposite error, the one UAMS-Web/uams-statamic-archive#2148 is about. So it is neither
 * faulted nor hidden. `wordpress-importer#878` reached the same split on its own copy.
 *
 * ⚠ **The notice does not say WHY, because this cannot know why.** A missing `origin/main` and a
 * git invocation that simply failed both arrive as a null count and are indistinguishable here — an
 * extra field would not help, since the one this returns answers a different question. Naming a
 * cause the probe never checked is its own defect, and the sibling shipped it: see
 * `wordpress-importer#880`, where the message reads "no origin/main ref" on a repository where the
 * ref exists.
 *
 * An absent probe is a third thing: a tree inspected before this check existed carries no `base`
 * key, which means "not probed" rather than "not measurable" — the reading UAMS-Web/uams-statamic-archive#2023 chose for the
 * submodule working tree.
 *
 * @param  {{behind: ?number, tracked: bool, ref: ?string, unreadable: ?bool}}  $base
 * @return {{reason: ?string, notice: ?string}}
 */
export function baseState(base) {
    if (!base || !base.tracked) {
        return { reason: null, notice: null };
    }

    if (base.unreadable) {
        return { reason: null, notice: `base: position against ${base.ref} could not be measured` };
    }

    // The remedy differs by tree kind, and naming the wrong one is worse than naming none: a
    // drifted PRIMARY told to `release` is told to run the command this script refuses for it,
    // because releasing detaches and `main` must stay checked out there. It holds a branch, so it
    // fast-forwards; a slot at rest is detached, so it re-detaches.
    return {
        reason: base.behind > 0
            ? `base: ${base.behind} behind ${base.ref} - run \`${base.remedy}\``
            : null,
        notice: null,
    };
}

/**
 * Classify one `vendor/` package directory that is a git source checkout (UAMS-Web/uams-statamic-archive#2691).
 *
 * Composer refuses to update a source checkout whose current branch has no remote-tracking ref on
 * any remote (`GitDownloader::getUnpushedChanges`, "could not be found on any remote and appears to
 * be unpushed"), so such a directory makes `composer install` fail however current the stamps are.
 * **The refusal is about the branch NAME, not about unpushed work**: a checkout whose every commit
 * is upstream still blocks the install if it sits on a branch name no remote carries.
 *
 * Only a named branch can block. A detached HEAD — what `--prefer-source` leaves at a pinned
 * reference — and a branch any remote carries are both the ordinary `host-app-tests` arrangement,
 * and are clean. A checkout whose git state could not be read is drift rather than health, the
 * reading `submoduleProbe` already takes.
 *
 * The remote refs read are the checkout's LOCAL remote-tracking refs; nothing is fetched. A branch
 * pushed since the last fetch therefore still reports as absent until the checkout is fetched.
 *
 * @param  string  $relativePath  e.g. `vendor/uams-web/aether-wordpress-importer`
 * @param  ?string  $toplevel      `git rev-parse --show-toplevel` output, or null when it failed.
 * @param  string  $packagePath   The directory's absolute path, to compare against `$toplevel`.
 * @param  ?string  $branch        `git symbolic-ref --short -q HEAD`, or null when detached.
 * @param  ?string  $remoteRefs    `git for-each-ref --format=%(refname) refs/remotes`, or null when it failed.
 * @return {{path: string, branch: ?string, blocking: bool, unreadable: bool}}
 */
export function vendorCheckoutProbe(relativePath, toplevel, packagePath, branch, remoteRefs) {
    if (!toplevel || normalizePath(toplevel) !== normalizePath(packagePath) || remoteRefs === null) {
        return { path: relativePath, branch, blocking: false, unreadable: true };
    }

    if (!branch) {
        return { path: relativePath, branch: null, blocking: false, unreadable: false };
    }

    // `refs/remotes/<remote>/<branch>`: the remote name is one segment, the branch may be several
    const onRemote = remoteRefs
        .split('\n')
        .map((ref) => ref.trim())
        .some((ref) => /^refs\/remotes\/[^/]+\//.test(ref) && ref.replace(/^refs\/remotes\/[^/]+\//, '') === branch);

    return { path: relativePath, branch, blocking: !onRemote, unreadable: false };
}

/**
 * The drift reason for one probed `vendor/` source checkout, or null when it cannot block an install.
 *
 * @param  {{path: string, branch: ?string, blocking: bool, unreadable: bool}}  $checkout
 * @return ?string
 */
export function vendorCheckoutReason(checkout) {
    if (checkout.unreadable) {
        return `${checkout.path}: source checkout whose git state could not be read`;
    }

    if (checkout.blocking) {
        return `${checkout.path}: source checkout on branch '${checkout.branch}', which no remote carries - \`composer install\` will refuse it`;
    }

    return null;
}

/**
 * How to clear the blocking checkouts `refresh` refuses on, one line per step (UAMS-Web/uams-statamic-archive#2691).
 *
 * @param  string  $treeName
 * @param  array  $checkouts  Blocking or unreadable probes from {@see vendorCheckoutProbe}.
 * @return string
 */
export function vendorCheckoutRecovery(treeName, checkouts) {
    const lines = [`worktree-slots: refusing to refresh ${treeName} before \`composer install\`, which would fail on:`];

    for (const checkout of checkouts) {
        lines.push(`  ${vendorCheckoutReason(checkout)}`);
    }

    lines.push('Recovery:');
    lines.push('  1. move each directory above out of vendor/ (for example `mv <dir> <scratch>/`), keeping it if it holds work you want');
    lines.push(`  2. re-run \`node scripts/worktree-slots.mjs refresh <tree>\` for ${treeName}, which reinstalls the package from the lock`);
    lines.push('  3. confirm the restored install is a dist install: `test ! -e <dir>/.git`');

    return `${lines.join('\n')}\n`;
}

/**
 * Summarize every drift a slot is carrying, as human-readable reasons.
 *
 * Returns an empty array when the slot is current, so the caller can treat "no reasons" as the
 * green state without inspecting shapes.
 *
 * `expected` scopes what counts, so a tree is judged against its own kind rather than against a
 * slot's. It defaults to a slot's expectations, which keeps the strict reading the default and
 * makes every exemption an explicit one.
 *
 * **Both lock files are checked, not just npm's.** `composer.lock` moves far more often than
 * `package-lock.json` here — 42 of the 50 commits that touched it in the last 120 days left npm's
 * alone — so a guard that watched only npm would certify a slot as current while `vendor/` still
 * held the previous branch's PHP packages. That is the exact silent failure the rule opens with
 * (Pest 4.7.5 against a 5.0.1 lock), and it only became reachable once UAMS-Web/uams-statamic-archive#1952 made a green possible
 * at all.
 *
 * **The patch set is checked too, and neither lockfile can stand in for it (UAMS-Web/uams-statamic-archive#2020).** A patch is
 * declared in `composer.json`'s `extra.patches`, so adding one leaves `composer.lock` untouched and
 * editing one leaves everything either lockfile watches untouched. `composer install` on a tree
 * whose `vendor/` already matches the lock reports `Nothing to install`, no package-install event
 * fires, and `cweagans/composer-patches` never applies the new patch — so without this the gate
 * reported `is current` on a tree missing a patch its branch declares. Observed against UAMS-Web/uams-statamic-archive#1673's
 * `statamic/cms` patch: a freshly refreshed slot still held the original hunk order.
 *
 * @param  {{lockHash: ?string, stampHash: ?string, composerHash: ?string, stampComposerHash: ?string, patchHash: ?string, stampPatchHash: ?string, submodules: array, vendorCheckouts: array, expected: ?object}}  $input
 * @return array<int, string>
 */
export function driftReasons({
    lockHash,
    stampHash,
    composerHash = null,
    stampComposerHash = null,
    patchHash = null,
    stampPatchHash = null,
    submodules = [],
    vendorCheckouts = [],
    base = null,
    contentMirror = null,
    expected = null,
}) {
    const reasons = [];
    const expect = expected ?? expectationsFor(SLOT);

    if (expect.checkBase) {
        const { reason } = baseState(base);

        if (reason) {
            reasons.push(reason);
        }
    }

    if (expect.checkInstall) {
        const nodeModules = installStaleReason(lockHash, stampHash);
        const vendor = installStaleReason(composerHash, stampComposerHash);
        const patches = patchStaleReason(patchHash, stampPatchHash);

        if (nodeModules) {
            reasons.push(`node_modules: ${nodeModules}`);
        }
        if (vendor) {
            reasons.push(`vendor: ${vendor}`);
        }
        if (patches) {
            reasons.push(`patches: ${patches}`);
        }

        // Checked alongside the stamps rather than folded into them: a tree whose stamps are both
        // current can still hold a checkout that makes the next install fail (UAMS-Web/uams-statamic-archive#2691).
        for (const checkout of vendorCheckouts) {
            const reason = vendorCheckoutReason(checkout);

            if (reason) {
                reasons.push(reason);
            }
        }
    }

    // A mirror built from a different `content/` is read by every content test while the
    // submodule itself reports current, so it is its own line (UAMS-Web/uams-statamic-archive#2935)
    if (expect.checkContentMirror) {
        const mirror = contentMirrorReason(contentMirror);

        if (mirror) {
            reasons.push(mirror);
        }
    }

    for (const submodule of submodules) {
        if (!submodule.recorded || !expect.submodules.includes(submodule.path)) {
            continue;
        }
        if (!submodule.actual) {
            reasons.push(`${submodule.path}: not initialized`);

            // Nothing to say about the working tree of a directory that has none, and the probe
            // was skipped for exactly that reason — see `submoduleStateOf`.
            continue;
        }
        if (submodule.actual !== submodule.recorded) {
            reasons.push(`${submodule.path}: expected ${submodule.recorded.slice(0, 9)}, found ${submodule.actual.slice(0, 9)}`);
        }

        // "Could not tell" is drift, not health. Reading a failed probe as clean would fail open in
        // the one place this guard exists to be closed — see `submoduleProbe`.
        if (submodule.unreadable) {
            reasons.push(`${submodule.path}: working tree could not be read`);
        }

        // Pushed as its OWN reason rather than folded into the revision line above, because the two
        // are independent and either can be the whole story. UAMS-Web/uams-statamic-archive#2023's slot was at the correct
        // revision on both submodules — `actual === recorded`, zero drift reported — with every
        // tracked file underneath deleted. A tree can equally be at the wrong revision *and* dirty,
        // and collapsing that into one line would let the louder half hide the other.
        const worktree = submoduleWorktreeReason(submodule.worktree);

        if (worktree) {
            reasons.push(`${submodule.path}: ${worktree}`);
        }
    }

    return reasons;
}

/**
 * Why a release must be refused over the SUPERPROJECT's own tree, or null when it may proceed.
 *
 * Releasing detaches the tree, which discards nothing by itself — but a dirty tree means the work
 * was never committed, and detaching strands it where the next claim will not look. Refusing is
 * the whole value: the failure this prevents is silent, and the fix afterwards is not.
 *
 * Submodule contents are judged by {@see submoduleReleaseRefusalReason}, because the status this
 * reads deliberately discards them.
 *
 * @param  string  $porcelainStatus
 * @return ?string
 */
/**
 * Read the content-mirror probe's output (UAMS-Web/uams-statamic-archive#2935).
 *
 * The probe prints `current` or `drifted`, from {@see App\Support\ContentMirror::sourceDrift()},
 * which compares the mirror's stamp with a stat fingerprint of `content/` now. Anything else, or a
 * probe that could not run, is `unreadable`: "could not tell" is reported, never read as current.
 *
 * @param  ?string  $output  The probe's stdout, or null when it exited non-zero
 * @return {{drifted: bool, unreadable: bool}}
 */
export function contentMirrorProbe(output) {
    if (output === 'current') {
        return { drifted: false, unreadable: false };
    }

    if (output === 'drifted') {
        return { drifted: true, unreadable: false };
    }

    return { drifted: false, unreadable: true };
}

/**
 * Why a tree's content mirror is drift, or null when it is not (UAMS-Web/uams-statamic-archive#2935).
 *
 * A null state is a tree the probe was not run on: no `vendor/`, or no initialized `content/`,
 * both of which are already their own drift lines. A mirror that does not exist yet, or has no
 * stamp, is current: `sourceDrift()` answers null for it, and the next test process builds it.
 *
 * @param  {{drifted: bool, unreadable: bool}|null}  $mirror
 * @return ?string
 */
export function contentMirrorReason(mirror) {
    if (!mirror) {
        return null;
    }

    if (mirror.unreadable) {
        return 'content mirror: could not be read';
    }

    return mirror.drifted
        ? `content mirror: built from a different content/, rebuild it with \`${CONTENT_MIRROR_SEED.command}\``
        : null;
}

/** The browser Puppeteer launches headless, which is also its folder in Puppeteer's cache. */
const PUPPETEER_BROWSER = 'chrome-headless-shell';

/** What installs that browser: `.npmrc` keeps `npm ci` from doing it (UAMS-Web/uams-statamic#457). */
const PUPPETEER_BROWSER_INSTALL = `npx --no-install puppeteer browsers install ${PUPPETEER_BROWSER}`;

/**
 * The browser version the installed Puppeteer asks for, read from the source that pins it
 * (UAMS-Web/uams-statamic#457). The file is an ES module, so it is read as text: `status` is
 * synchronous, and importing a dependency would run its code in every tree.
 *
 * @param  ?string  $revisions  The text of Puppeteer's `revisions.js`
 * @return ?string
 */
export function pinnedBrowserVersion(revisions) {
    return revisions?.match(new RegExp(`['"]${PUPPETEER_BROWSER}['"]:\\s*['"]([^'"]+)['"]`))?.[1] ?? null;
}

/**
 * Whether the browser the tree's Puppeteer asks for is in the machine's Puppeteer cache.
 *
 * `ignore-scripts=true` in `.npmrc` keeps Puppeteer's postinstall from running, so `npm ci` never
 * downloads the browser. The cache is per machine and per user, not per tree, and resolves as
 * Puppeteer's own `getConfiguration()` does. It holds `<browser>/<platform>-<version>`, and this
 * checks that folder exists, so a download interrupted part way through still reads as installed.
 *
 * @param  string  $treePath
 * @param  {{PUPPETEER_CACHE_DIR?: string}}  $env
 * @param  string  $home
 * @return {{version: ?string, installed: bool}}
 */
export function puppeteerBrowserStateOf(treePath, env = process.env, home = homedir()) {
    const revisions = join(treePath, 'node_modules', 'puppeteer-core', 'lib', 'puppeteer', 'revisions.js');
    const version = pinnedBrowserVersion(existsSync(revisions) ? readFileSync(revisions, 'utf8') : null);
    const root = join(env.PUPPETEER_CACHE_DIR ?? join(home, '.cache', 'puppeteer'), PUPPETEER_BROWSER);

    return {
        version,
        installed: version !== null && existsSync(root) && readdirSync(root).some((name) => name.endsWith(`-${version}`)),
    };
}

/**
 * Why a tree cannot render a news-release PDF, or null when it can (UAMS-Web/uams-statamic#457).
 *
 * A null state is a tree the probe was not run on. An unread version is reported rather than
 * passed: a Puppeteer upgrade that moves the pin must not turn this check silently clean.
 *
 * @param  {{version: ?string, installed: bool}|null}  $browser
 * @return ?string
 */
export function puppeteerBrowserNotice(browser) {
    if (!browser || browser.installed) {
        return null;
    }

    return browser.version === null
        ? 'Puppeteer browser: could not read the version Puppeteer asks for'
        : `Puppeteer browser: ${PUPPETEER_BROWSER} ${browser.version} is not installed, so news release PDFs fail; run \`${PUPPETEER_BROWSER_INSTALL}\``;
}

/**
 * What `refresh`'s last line reports, from the base check and the content mirror (UAMS-Web/uams-statamic-archive#2938).
 *
 * `baseState()` returns a reason or a notice, never both, and the line printing them is an
 * `if`/`else if`. Joining the mirror's reason into the base's would let a drifted mirror skip the
 * `else if`, dropping the unmeasurable-base notice, which `status --check` never prints and
 * `refresh` is the only place to say. So a notice that arrives beside a reason is folded into the
 * reason, and stands alone only when there is no reason to carry it.
 *
 * @param  {{reason: ?string, notice: ?string}}  $base  `baseState()`'s answer
 * @param  ?string  $mirrorReason  `contentMirrorReason()`'s answer
 * @return {{reason: ?string, notice: ?string}}
 */
export function refreshOutcome(base, mirrorReason = null) {
    const reasons = [base.reason, mirrorReason].filter(Boolean);

    if (reasons.length === 0) {
        return { reason: null, notice: base.notice ?? null };
    }

    return { reason: [...reasons, base.notice].filter(Boolean).join('; '), notice: null };
}

/**
 * The one-line verdict `status --check` prints alongside its exit code.
 *
 * Exported and tested for the same reason {@see releaseRefusal} is: `commandStatus`
 * shells out and is not exported, and the CLEAN arm cannot be reached from a real
 * machine on demand — this repository's own trees drift as work moves through them,
 * so a test that waited for a drift-free machine would be a test that never ran.
 *
 * @param  {string[]}  driftedNames  Names of the trees that drifted, in report order.
 * @return {string}
 */
export function checkVerdictLine(driftedNames) {
    return driftedNames.length
        ? `worktree-slots: DRIFT — ${driftedNames.length} tree(s): ${driftedNames.join(', ')}`
        : 'worktree-slots: CLEAN — no drift in any tree';
}

export function releaseRefusalReason(porcelainStatus) {
    const entries = String(porcelainStatus).split('\n').map((line) => line.trim()).filter(Boolean);

    if (entries.length === 0) {
        return null;
    }

    const shown = entries.slice(0, 5).map((entry) => `    ${entry}`).join('\n');
    const more = entries.length > 5 ? `\n    … and ${entries.length - 5} more` : '';

    return `the tree has ${entries.length} uncommitted change(s):\n${shown}${more}`;
}

/**
 * Why a release must be refused over a submodule's *contents*, or null when it may proceed.
 *
 * Separate from {@see releaseRefusalReason} because the superproject status it reads is captured
 * with `--ignore-submodules=all`, and that flag has to stay. It is what stops a routinely-drifting
 * `content/` **pointer** from blocking every release — but it discards the submodules wholesale, so
 * a slot with 398 deleted assets released cleanly and read `free` to the next session (UAMS-Web/uams-statamic-archive#2023).
 * Checking the submodules separately keeps the pointer tolerated and the contents guarded.
 *
 * **Only tracked changes refuse; untracked residue does not.** The failure this closes is
 * destruction of tracked content, and refusing over untracked paths would fire on the workflow
 * these slots exist to host — a completed import leaves artifacts behind, and the importer slot was
 * holding one when the bug was found. A guard that reds on the prescribed resting state is the
 * UAMS-Web/uams-statamic-archive#1952 failure, and people learn to skip it. An untracked-only submodule is therefore not listed
 * here at all; `status` is where it surfaces. A submodule that has *both* still shows both counts,
 * so the reader sees the whole picture for anything that did refuse.
 *
 * An **unreadable** probe refuses too. It is not evidence of destruction, but it is not evidence of
 * health either, and this is the last gate before the tree is detached.
 *
 * @param  array  $submodules
 * @return ?string
 */
export function submoduleReleaseRefusalReason(submodules) {
    const dirty = (submodules || [])
        .filter((submodule) => (submodule.worktree?.tracked ?? 0) > 0 || submodule.unreadable);

    if (dirty.length === 0) {
        return null;
    }

    const shown = dirty
        .map((submodule) => `    ${submodule.path}: ${submodule.unreadable ? 'working tree could not be read' : submoduleWorktreeReason(submodule.worktree)}`)
        .join('\n');

    // The recovery is named because it is cheap, non-obvious, and NOT what the generic
    // "commit or move the work first" advice would lead you to. These are almost always deletions
    // or app-written files rather than authored work, so restoring to the gitlink is the fix.
    return `${dirty.length} submodule working tree(s) carry uncommitted changes:\n${shown}\n`
        + '    Recover with: git -C <submodule> restore --staged --worktree .';
}

/**
 * Every reason a release must be refused, superproject and submodules alike.
 *
 * This composition is exported and tested for the same reason {@see judgeTree} is, and the lesson
 * was re-learned the hard way here: with both halves fully covered in isolation, deleting the
 * submodule arm from `commandRelease` left all 75 tests green while `release` detached a slot whose
 * `users` submodule had every tracked file staged as a deletion. Testing the halves cannot catch a
 * missing call between them, so the composition is a named, asserted function rather than an inline
 * `a() ?? b()` in `commandRelease` where nothing could reach it.
 *
 * **This narrows the exposure rather than closing it, and the residue is worth naming.** Dropping
 * an arm from *this* function now reds the suite. `commandRelease` bypassing this function
 * altogether — calling `releaseRefusalReason` directly again — still would not, because
 * `commandRelease` shells out to git and is not exported. That is a larger and more deliberate edit
 * than deleting a `??` clause, which is the trade being made; it is not zero risk.
 *
 * The superproject is checked first because its reason is the more specific one — uncommitted work
 * in the tree you are about to detach — and reporting both at once would bury it.
 *
 * @param  string  $porcelainStatus
 * @param  array   $submodules
 * @return ?string
 */
/**
 * Read a `--flag value` pair out of argv, or null when it is absent.
 *
 * Accepts `--flag=value` as well, because a caller who writes it that way is not wrong and the
 * failure would otherwise be a silent null that reads as "no branch named" — which, for
 * `--branch`, turns a deliberate release into a refusal the caller cannot explain.
 *
 * @param {string[]} argv  The raw argument list.
 * @param {string} flag  The flag to read, including its leading dashes.
 */
export function flagValue(argv, flag) {
    const index = argv.indexOf(flag);

    if (index !== -1) {
        const value = argv[index + 1];

        return value === undefined || value.startsWith('--') ? null : value;
    }

    const inline = argv.find((arg) => arg.startsWith(`${flag}=`));

    return inline === undefined ? null : inline.slice(flag.length + 1) || null;
}

/**
 * Why a release must be refused because the slot is OCCUPIED, or null when it may proceed.
 *
 * This is a different question from `releaseRefusalReason`, and the difference is the reason it
 * needed its own guard rather than another arm of that one. That reason asks whether releasing
 * would LOSE work; this asks whether it would TAKE a tree somebody is using. A slot that is clean
 * and on a branch is the normal state for most of a ticket — every moment between a commit and
 * the next edit — and it loses nothing when detached, because `checkout --detach` preserves the
 * branch. What it takes is the holder's `HEAD`, and their next commit lands detached.
 *
 * The `worktrees` rule already defines the signal: "a slot on a `<N>-<slug>` or `wave/<theme>`
 * branch is held". `slotOccupancy()` computes exactly that and `commandRelease` never read it.
 *
 * Naming the branch is what makes the release deliberate. It needs no liveness detection and
 * cannot go stale — the alternative, resolving a live process in the tree, inherits every
 * attribution trap in `long-running-commands`, including the one where a sweep that finds nothing
 * is indistinguishable from a sweep that cannot see.
 *
 * @param {string|null} branch  The branch the slot is on, or null/empty when detached.
 * @param {string|null} requestedBranch  The branch the caller named with `--branch`.
 */
export function heldSlotRefusalReason(branch, requestedBranch) {
    // A slot at rest is detached, so releasing your own finished slot is unaffected.
    if (!branch) {
        return null;
    }

    if (requestedBranch && requestedBranch === branch) {
        return null;
    }

    if (requestedBranch) {
        return `it is on branch '${branch}', not the '${requestedBranch}' you named`;
    }

    return `it is on branch '${branch}', so another session may be working in it`;
}

/**
 * Why a release must be ABORTED because the tree changed after it was judged, or null.
 *
 * `commandRelease` reads the tree, then detaches it, as separate git invocations — so work
 * appearing in between is detached anyway and the success line prints over it. Re-reading
 * immediately before the detach does not make the pair atomic; git offers no way to do that. It
 * narrows the window to two back-to-back local commands, and the `fetch` that used to sit inside
 * it now runs before the guard, so the slowest operation is no longer part of the race.
 *
 * State this as a narrowing rather than a fix. A guard described as closing a race that it only
 * shortens is the kind of claim the next reader stops checking.
 */
export function releaseRaceAbortReason(before, after) {
    return String(before).trim() === String(after).trim()
        ? null
        : 'the tree changed while it was being released';
}

/**
 * The git arguments that detach a released slot at `origin/main`, without recursing into submodules.
 *
 * On a machine whose global config sets `submodule.recurse=true`, a bare `checkout` recurses into
 * every submodule the shared `.git/config` marks active. When the move adds a submodule the slot
 * has never initialized, git looks for a submodule gitdir under the slot's own worktree directory,
 * finds none, and aborts with `could not reset submodule index`: HEAD stays where it was while the
 * working tree holds part of the target commit (UAMS-Web/uams-statamic-archive#3288). `-c submodule.recurse=false` overrides any
 * global setting for this one invocation, so `refresh`'s `submodule update --init` stays the only
 * step that touches submodules.
 *
 * @param  string  $path
 * @return array<int, string>
 */
export function releaseCheckoutArgs(path) {
    return ['-C', path, '-c', 'submodule.recurse=false', 'checkout', '--detach', 'origin/main'];
}

/**
 * The git arguments that fetch `origin` for a tree, without fetching its submodules.
 *
 * With `submodule.recurse=true` in a developer's global config, a bare `fetch` also fetches every
 * initialized submodule over the network, which turned `release` from seconds into minutes (#289).
 * The script never needs those objects from a fetch: `refresh` and `provision` run their own
 * `submodule update --init`. Every fetch in this file goes through here, so the time one takes no
 * longer depends on a global setting.
 *
 * @param  string  $path
 * @param  array<int, string>  $extra
 * @return array<int, string>
 */
export function fetchOriginArgs(path, ...extra) {
    return ['-C', path, 'fetch', '--no-recurse-submodules', 'origin', ...extra];
}

export function releaseRefusal(porcelainStatus, submodules) {
    return releaseRefusalReason(porcelainStatus) ?? submoduleReleaseRefusalReason(submodules);
}

/**
 * Spawn a command, resolving Windows shims first.
 *
 * @param  string  $file
 * @param  array   $args
 * @param  ?string $cwd
 * @param  string  $stdio
 * @return object
 */
function spawn(file, args, cwd, stdio, env = undefined) {
    const exe = which(file) || file;
    const shell = needsShell(exe);

    return spawnSync(
        shell ? winQuote(exe) : exe,
        shell ? args.map(winQuote) : args,
        { cwd, stdio, shell, encoding: 'utf8', env: env ? { ...process.env, ...env } : process.env },
    );
}

/**
 * Run a command and return its trimmed stdout, or null when it fails.
 *
 * @param  string  $file
 * @param  array   $args
 * @param  ?string $cwd
 * @return ?string
 */
function capture(file, args, cwd = undefined) {
    const result = spawn(file, args, cwd, ['ignore', 'pipe', 'ignore']);

    return result.status === 0 && typeof result.stdout === 'string' ? result.stdout.trim() : null;
}

/**
 * Run a command, streaming its output, and throw when it fails.
 *
 * @param  string  $file
 * @param  array   $args
 * @param  string  $cwd
 * @param  ?object $env  Variables added to the inherited environment
 * @return void
 */
function run(file, args, cwd, env = undefined) {
    const result = spawn(file, args, cwd, 'inherit', env);

    if (result.error) {
        throw new Error(`${file} could not be started: ${result.error.message}`);
    }
    if (result.status !== 0) {
        throw new Error(`${file} ${args.join(' ')} exited ${result.status}`);
    }
}

/**
 * The packages installed under a tree's `vendor/`, as sorted `vendor/package` names (UAMS-Web/uams-statamic-archive#2692).
 *
 * Taken before the refresh touches `vendor/` and again after a failed `composer install`, so an
 * install that aborts partway can be caught having left packages missing. `vendor/composer/installed.json`
 * cannot show that: Composer rewrites it only when an install finishes, so an aborted one leaves
 * it describing packages that are no longer on disk.
 *
 * @param  string  $treePath
 * @return array
 */
export function vendorPackages(treePath) {
    const vendor = join(treePath, 'vendor');

    if (!existsSync(vendor)) {
        return [];
    }

    const packages = [];

    for (const owner of readdirSync(vendor, { withFileTypes: true })) {
        // `vendor/bin` holds proxies, and `vendor/composer` is Composer's own bookkeeping
        if (!owner.isDirectory() || owner.name === 'bin' || owner.name === 'composer') {
            continue;
        }

        for (const pkg of readdirSync(join(vendor, owner.name), { withFileTypes: true })) {
            if (pkg.isDirectory()) {
                packages.push(`${owner.name}/${pkg.name}`);
            }
        }
    }

    return packages.sort();
}

/**
 * Whether a tree can still boot, by running `php artisan --version` in it (UAMS-Web/uams-statamic-archive#2692).
 *
 * A probe that could not start at all is reported as not having run, never as a boot result,
 * so a missing PHP is not mistaken for a healthy tree or a broken one.
 *
 * @param  string  $treePath
 * @param  string  $php       The PHP binary; a parameter so a test can point it elsewhere.
 * @return object  `{ ran, ok, detail }`
 */
export function bootProbe(treePath, php = 'php') {
    const result = spawn(php, ['artisan', '--version'], treePath, ['ignore', 'pipe', 'pipe']);

    if (result.error || result.status === null) {
        return { ran: false, ok: false, detail: result.error ? result.error.message : `killed by ${result.signal}` };
    }

    if (result.status === 0) {
        return { ran: true, ok: true, detail: '' };
    }

    const lines = `${result.stderr || ''}\n${result.stdout || ''}`
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
    // The line naming what is missing is the useful one; a stack trace's first line often is not
    const telling = lines.find((line) => /Failed to open stream|Failed opening required|not found|Fatal error/i.test(line));
    const line = telling || lines[0] || `exited ${result.status}`;
    const detail = line.length > 240 ? `${line.slice(0, 240)}...` : line;

    return { ran: true, ok: false, detail };
}

/**
 * The script's own account of the tree a failed `composer install` left behind (UAMS-Web/uams-statamic-archive#2692).
 *
 * Composer's output names the cause of the failure. This names the consequence, which Composer
 * never does: an install is not atomic, so one that aborts partway can leave `vendor/` missing
 * packages it had before, and a checkout that no longer boots. Three outcomes are told apart —
 * nothing changed, packages went missing but the tree still boots, and the tree cannot boot —
 * plus a fourth, the probe itself failing, which is never read as a healthy tree.
 *
 * @param  string  $treeName
 * @param  object  $state  `{ before, after, probe }` — package lists either side of the install,
 *                          and the {@see bootProbe} result.
 * @return string
 */
export function installFailureReport(treeName, { before, after, probe }) {
    const present = new Set(after);
    const missing = before.filter((pkg) => !present.has(pkg));
    const removed = missing.length === 0
        ? ''
        : ` It is missing ${missing.length === 1 ? '1 package that was' : `${missing.length} packages that were`} there before the refresh began: ${missing.join(', ')}.`;
    const remedy = ` Re-run \`composer install --no-interaction\` in ${treeName} once the cause above is cleared.`;

    if (!probe.ran) {
        return `worktree-slots: the boot probe could not run (${probe.detail}), so whether ${treeName} still boots is unknown — treat it as broken until \`php artisan --version\` succeeds there.${removed}${remedy}`;
    }

    if (!probe.ok) {
        return `worktree-slots: ${treeName} CANNOT BOOT after the failed install: ${probe.detail}.${removed}${remedy}`;
    }

    if (missing.length === 0) {
        return `worktree-slots: composer install failed without removing any package, so ${treeName} is unchanged and still boots.`;
    }

    return `worktree-slots: ${treeName} still boots, but it is not the tree the install started from.${removed}${remedy}`;
}

/** @return array */
function worktreesOf(repoRoot) {
    return parseWorktreeList(capture('git', ['-C', repoRoot, 'worktree', 'list', '--porcelain']) || '');
}

/** @return ?string */
function fileHashOf(treePath, name) {
    const file = join(treePath, name);

    return existsSync(file) ? createHash('sha256').update(readFileSync(file)).digest('hex') : null;
}

/** @return ?string */
function lockHashOf(treePath) {
    return fileHashOf(treePath, 'package-lock.json');
}

/** @return ?string */
function composerHashOf(treePath) {
    return fileHashOf(treePath, 'composer.lock');
}

/**
 * The patch set a tree DECLARES, as `patches.lock.json`'s own `_hash`.
 *
 * Read through `readPatchLockHash` rather than hashing the file, because that is the hash
 * `local-ci.mjs` stamps — sharing the derivation is what lets the two gates agree about what
 * "the same patch set" means.
 *
 * Neither lockfile can stand in for this. A patch is declared in `composer.json`'s
 * `extra.patches` and lives in `patches/*.patch`, so adding one moves `composer.lock` not at
 * all, and EDITING one moves nothing tracked by either lockfile. `patches.lock.json` carries a
 * `sha256` per patch file, so its `_hash` covers both.
 *
 * @param  string  $treePath
 * @return ?string
 */
function patchHashOf(treePath) {
    const lock = join(treePath, 'patches.lock.json');

    return existsSync(lock) ? readPatchLockHash(readFileSync(lock, 'utf8')) : null;
}

/**
 * Record what the installs were run against, so a later `status` can tell current from stale.
 *
 * Skipped when `node_modules/` is absent, since that is where the stamp lives — a tree with no npm
 * install has nothing to certify, and reads as stale on the next status, which is the safe
 * direction.
 *
 * `patchHash` certifies a VERIFIED state rather than an attempt: `run()` throws on a non-zero exit,
 * so a patch that will not apply fails the install and this is never reached. That ordering is the
 * whole reason the re-patch goes through `composer install` instead of `composer patches-repatch`,
 * which would have exited 0 and let a failed patch be stamped as applied (UAMS-Web/uams-statamic-archive#2020).
 *
 * @param  string  $treePath
 * @return void
 */
function writeInstallStamp(treePath) {
    if (!existsSync(join(treePath, 'node_modules'))) {
        return;
    }

    const stamp = {
        lockHash: lockHashOf(treePath),
        composerHash: composerHashOf(treePath),
        patchHash: patchHashOf(treePath),
    };

    writeFileSync(join(treePath, STAMP_PATH), `${JSON.stringify(stamp, null, 2)}\n`);
}

/**
 * Read one recorded hash out of a tree's install stamp.
 *
 * @param  string  $treePath
 * @param  string  $key
 * @return ?string
 */
function stampHashOf(treePath, key = 'lockHash') {
    const stamp = join(treePath, STAMP_PATH);

    if (!existsSync(stamp)) {
        return null;
    }

    try {
        return JSON.parse(readFileSync(stamp, 'utf8'))[key] || null;
    } catch {
        return null;
    }
}

/**
 * Whether a submodule directory is a real checkout rather than an empty placeholder.
 *
 * Every `git -C <dir> …` probe against an *uninitialized* submodule directory silently answers for
 * the enclosing superproject instead of failing, because git discovers the nearest repository by
 * walking up. An initialized submodule is its own toplevel, so comparing `--show-toplevel` to the
 * directory itself is what separates a real answer from a walk-up.
 *
 * Extracted as its own predicate because two probes now depend on it and the walk-up is far more
 * dangerous for the second. A bogus `rev-parse HEAD` reads as a revision *mismatch* — wrong, but
 * loud. A bogus `status --porcelain` reports the **superproject's** dirty files as the submodule's,
 * so a slot with uncommitted app changes and an absent `content/` would be accused of having a
 * dirty `content/` working tree, and `release` would refuse over files that are not there.
 *
 * @param  string   $expectedPath
 * @param  ?string  $toplevel
 * @return bool
 */
export function isOwnToplevel(expectedPath, toplevel) {
    return Boolean(toplevel) && normalizePath(toplevel) === normalizePath(expectedPath);
}

/**
 * The revision a submodule directory is actually at, or null when it is not initialized.
 *
 * `git -C <dir> rev-parse HEAD` does **not** fail in an empty submodule directory — it walks up to
 * the enclosing superproject and cheerfully answers for that instead. Every uninitialized
 * submodule therefore reports the *worktree's own* HEAD, which reads as a revision mismatch while
 * the real state is "absent", and the not-initialized branch below could never fire. Observed
 * against the local-CI worktree, where three uninitialized submodules all reported `163b300df` —
 * the commit the superproject was sitting on.
 *
 * @param  string   $expectedPath
 * @param  ?string  $toplevel
 * @param  ?string  $head
 * @return ?string
 */
export function submoduleRevision(expectedPath, toplevel, head) {
    return head && isOwnToplevel(expectedPath, toplevel) ? head : null;
}

/**
 * Split a submodule's `git status --porcelain` into tracked changes and untracked paths.
 *
 * The two are counted separately because they carry different weight, and UAMS-Web/uams-statamic-archive#2023 turns on the
 * distinction. A **tracked** change in a submodule is the destruction case: the importer slot was
 * found carrying 1,696 staged deletions — every tracked file in `public/assets` and
 * `app/private/assets` — while their revisions matched their gitlinks exactly, so nothing reported a
 * thing. An **untracked** path is ordinary workflow residue; the same slot held one
 * (`images/default/2026-08-w3c-full.png`, a test-import artifact), and that is what these slots are
 * *for*.
 *
 * Counts are of porcelain **entries**, not files: the default `--untracked-files=normal` collapses
 * an untracked directory into one line, which is why the reason text says "path(s)" rather than
 * "file(s)". Asking for `--untracked-files=all` instead would walk all 1,211 files of
 * `public/assets` on every `status` to sharpen a number nobody acts on.
 *
 * @param  string  $porcelain
 * @return {{tracked: number, untracked: number}}
 */
export function submoduleWorkingTreeState(porcelain) {
    const entries = String(porcelain).split('\n').map((line) => line.trim()).filter(Boolean);

    return {
        tracked: entries.filter((entry) => !entry.startsWith('??')).length,
        untracked: entries.filter((entry) => entry.startsWith('??')).length,
    };
}

/**
 * Assemble one submodule's record from the raw git output describing it.
 *
 * Pure, and separated from the `git` calls in {@see submoduleStateOf}, because the decisions it
 * makes are the ones that must not regress and I/O would put them out of a test's reach. Mutation
 * testing while writing this confirmed the risk: replacing the guard below with a constant `true`
 * left the whole suite green, because nothing exercised the wiring — only the predicate.
 *
 * `worktree` is null for two different reasons, and `unreadable` is what separates them:
 *
 * - **Not initialized** — the directory is an empty placeholder, so it has no working tree to
 *   describe. `unreadable` is false; there is nothing wrong, only nothing there.
 * - **Initialized but `git status` failed** — a corrupt index, a permissions fault. `unreadable`
 *   is true, and callers must treat it as drift rather than as clean.
 *
 * That asymmetry is deliberate and matches {@see installStaleReason}, which reads a missing stamp
 * as stale rather than fresh. A guard whose whole purpose is catching silently destroyed content
 * must not read "I could not tell" as "nothing is wrong" — `capture()` returns `''` for a clean
 * tree and `null` for a failed command, so the two are distinguishable and collapsing them with
 * `porcelain || ''` would fail open exactly where it matters most.
 *
 * @param  string   $path           the submodule's repo-relative path, carried through to the record
 * @param  string   $expectedPath   where the submodule directory should be on disk
 * @param  ?string  $recorded       the gitlink revision the superproject records
 * @param  ?string  $toplevel       `git -C <dir> rev-parse --show-toplevel`
 * @param  ?string  $head           `git -C <dir> rev-parse HEAD`
 * @param  ?string  $porcelain      `git -C <dir> status --porcelain`, or null when it failed
 * @return {{path: string, recorded: ?string, actual: ?string, worktree: ?object, unreadable: bool}}
 */
export function submoduleProbe(path, expectedPath, recorded, toplevel, head, porcelain) {
    // Gated on the toplevel check rather than on the revision, which is null for two different
    // reasons — uninitialized, or initialized but unreadable. Only the first must skip the status
    // probe. Running it on an uninitialized directory would walk up and report the SUPERPROJECT's
    // dirty files as this submodule's, so a slot with uncommitted app changes and an absent
    // `content/` would be accused of a dirty `content/` and refused a release over files that do
    // not exist. `porcelain` is therefore discarded here rather than trusted.
    const initialized = isOwnToplevel(expectedPath, toplevel);

    return {
        path,
        recorded,
        actual: submoduleRevision(expectedPath, toplevel, head),
        worktree: initialized && porcelain !== null ? submoduleWorkingTreeState(porcelain) : null,
        unreadable: initialized && porcelain === null,
    };
}

/**
 * How a submodule's working tree differs from its commit, as a phrase, or null when it is clean.
 *
 * @param  ?{{tracked: number, untracked: number}}  $worktree
 * @return ?string
 */
export function submoduleWorktreeReason(worktree) {
    if (!worktree) {
        return null;
    }

    const parts = [];

    if (worktree.tracked > 0) {
        parts.push(`${worktree.tracked} uncommitted change(s)`);
    }
    if (worktree.untracked > 0) {
        parts.push(`${worktree.untracked} untracked path(s)`);
    }

    return parts.length > 0 ? parts.join(', ') : null;
}

/**
 * The recorded-vs-actual revision of every declared submodule in a tree.
 *
 * `git ls-tree` is read in the tree itself rather than via `git submodule status`, which reports
 * the *primary's* revision when run from a linked worktree and would invent drift that is not
 * there.
 *
 * @param  string  $treePath
 * @return array
 */
/**
 * Probe every `vendor/<owner>/<package>` directory that is a git source checkout (UAMS-Web/uams-statamic-archive#2691).
 *
 * A dist install has no `.git`, so it costs one `existsSync` and spawns nothing.
 *
 * @param  string  $treePath
 * @return array
 */
export function vendorCheckoutsOf(treePath) {
    const checkouts = [];

    for (const pkg of vendorPackages(treePath)) {
        const packagePath = join(treePath, 'vendor', ...pkg.split('/'));

        if (!existsSync(join(packagePath, '.git'))) {
            continue;
        }

        checkouts.push(vendorCheckoutProbe(
            `vendor/${pkg}`,
            capture('git', ['-C', packagePath, 'rev-parse', '--show-toplevel']),
            // git reports the resolved path, so compare against it (macOS `/var` is `/private/var`)
            realpathSync(packagePath),
            capture('git', ['-C', packagePath, 'symbolic-ref', '--short', '-q', 'HEAD']),
            capture('git', ['-C', packagePath, 'for-each-ref', '--format=%(refname)', 'refs/remotes']),
        ));
    }

    return checkouts;
}

/**
 * Probe a tree's content mirror with `ContentMirror::sourceDrift()` (UAMS-Web/uams-statamic-archive#2935).
 *
 * Runs the app's own comparison rather than a second copy of it in JavaScript, so the two cannot
 * disagree about what counts as changed. Nothing is booted: the paths are passed explicitly, so
 * the class needs only the autoloader. The `content/` walk is a stat of every file (UAMS-Web/uams-statamic-archive#2230 measured
 * 0.7s on a full checkout); `status` over this machine's trees went from 2.6s to 3.0s with it.
 *
 * @param  string  $treePath
 * @param  string  $autoload  Path of the Composer autoloader to load
 * @return {{drifted: bool, unreadable: bool}|null} null when the tree has no autoloader
 */
export function contentMirrorStateOf(treePath, autoload = join(treePath, 'vendor', 'autoload.php')) {
    if (!existsSync(autoload)) {
        return null;
    }

    const quote = (value) => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
    const code = [
        `require ${quote(autoload)};`,
        '$drift = App\\Support\\ContentMirror::sourceDrift(',
        'new Illuminate\\Filesystem\\Filesystem,',
        `${quote(join(treePath, 'content'))},`,
        `${quote(join(treePath, 'storage', 'framework', 'testing', 'content-mirror'))});`,
        "echo $drift === null ? 'current' : 'drifted';",
    ].join(' ');

    return contentMirrorProbe(capture('php', ['-r', code], treePath));
}

function submoduleStateOf(treePath) {
    return SUBMODULES.map((path) => {
        const entry = capture('git', ['-C', treePath, 'ls-tree', 'HEAD', path]);
        const recorded = entry ? (entry.split(/\s+/)[2] || null) : null;
        const modulePath = join(treePath, path);

        // Every decision this record encodes lives in `submoduleProbe`; this function's only job is
        // to hand it the raw git output.
        return submoduleProbe(
            path,
            modulePath,
            recorded,
            capture('git', ['-C', modulePath, 'rev-parse', '--show-toplevel']),
            capture('git', ['-C', modulePath, 'rev-parse', 'HEAD']),
            capture('git', ['-C', modulePath, 'status', '--porcelain']),
        );
    });
}

/**
* Decide what a tree's base state is, from its branch and git's raw count.
 *
 * Pure, and exported, for the same reason `submoduleProbe` is: the branch-scoping rule below is the
 * whole of this check's judgement, and it must be testable without a repository that is actually
 * behind.
 *
 * `tracked` is deliberately not "is this detached". A slot at rest is detached and the primary
 * holds `main`; both are trees this script parks at `origin/main`, so both are judged. A slot
 * carrying `<N>-<slug>` is neither, and being behind there is expected rather than drift — that is
 * `sync-pr-branch`'s business, and duplicating it here would flag every branch the moment `main`
 * moved.
 *
 * @param  ?string  $branch
 * @param  ?string  $count   raw `git rev-list --count` output, or null if it could not be read
 * @return {{tracked: bool, behind: ?number, ref: string, unreadable: bool}}
 */
/**
 * The pinned first-party dependency this repository consumes, and the sibling clone that answers
 * questions about it.
 *
 * The clone's location is per-machine — it is not a sibling of this repository on any machine
 * measured so far — so it is read from an environment variable rather than guessed at. An unset
 * variable is a reported state, not a silent skip: the check going quiet is exactly the failure
 * this exists to prevent.
 */
const PINNED_PACKAGE = 'uams-web/aether-wordpress-importer';
const PINNED_CLONE_ENV = 'UAMS_WORDPRESS_IMPORTER_CLONE';

/**
 * Render the pin's distance from its dependency's own upstream.
 *
 * **This is a notice, never drift.** Being behind an upstream is the ordinary state of a pin — a
 * tag is a fixed point and its branch keeps moving — so faulting a tree for it would make the guard
 * something people learn to skip, which is the failure `worktree-slots.mjs` exists to avoid. What
 * it prevents is the UAMS-Web/uams-statamic-archive#2249 case: four readings taken against the dependency's `main`, which this app
 * does not install, none of them carrying the revision they came from.
 *
 * `ahead` is counted from the clone's **local** `origin/main`, so it needs no network and can be
 * stale. A stale count is reported as such rather than presented as current — an unqualified number
 * from an un-fetched clone is the same class of claim this notice exists to make impossible.
 *
 * @param  {{version: ?string, clone: ?string, ahead: ?number, staleDays: ?number}}  $input
 * @return ?string
 */
export function pinnedDependencyNotice({ version = null, clone = null, ahead = null, staleDays = null }) {
    if (!version) {
        return null;
    }

    const name = PINNED_PACKAGE.split('/').pop();

    if (!clone) {
        return `${name} pinned ${version} - upstream distance unmeasured (${PINNED_CLONE_ENV} unset)`;
    }

    if (ahead === null) {
        return `${name} pinned ${version} - upstream distance unreadable in ${clone}`;
    }

    const distance = ahead === 0 ? 'upstream main level' : `upstream main +${ahead}`;
    const staleness = staleDays !== null && staleDays >= 1 ? `, clone fetched ${staleDays}d ago` : '';

    return `${name} pinned ${version} - ${distance}${staleness}`;
}

export function baseProbe(branch, count, kind = SLOT) {
    const ref = 'origin/main';
    // `release` re-detaches a slot at its base and is REFUSED for the primary, which must keep
    // `main` checked out. So the primary fast-forwards the branch it holds instead.
    const remedy = kind === PRIMARY ? 'git pull --ff-only origin main' : 'release';

    if (branch && branch !== 'main') {
        return { tracked: false, behind: null, ref, unreadable: false, remedy };
    }

    // An unparseable answer is not zero. Reading it as zero would report a tree as being at its
    // base on the strength of git having failed, which is the fail-open this guard exists to avoid.
    if (count === null || !/^\d+$/.test(String(count).trim())) {
        return { tracked: true, behind: null, ref, unreadable: true, remedy };
    }

    return { tracked: true, behind: Number(String(count).trim()), ref, unreadable: false, remedy };
}

/**
 * How far a tree's own base sits behind `origin/main`.
 *
 * Mirrors `submoduleStateOf`: runs git and hands the raw answer to `baseProbe`, so the judgement
 * stays where it can be tested.
 *
 * @param  string  $treePath
 * @param  ?string  $branch
 * @return object
 */
/**
 * Which trees, if any, need the pin reported against them individually.
 *
 * The pin is normally identical across every tree, so emitting it per row would print the same
 * sentence five times — and a line that repeats unchanged is one readers learn to skip, which is
 * the failure this file's own docblock is written against. Reported once as a footer when the trees
 * agree, and per tree only where one genuinely differs, which is the case worth seeing.
 *
 * @param  array<int, {name: string, pinned: {version: ?string}}>  $trees
 * @return {{shared: ?string, divergent: array<int, {name: string, version: ?string}>}}
 */
export function pinnedDependencyGrouping(trees) {
    const versions = trees.map((tree) => tree.pinned?.version ?? null);
    const present = versions.filter((version) => version !== null);

    if (present.length === 0) {
        return { shared: null, divergent: [] };
    }

    const counts = new Map();

    for (const version of present) {
        counts.set(version, (counts.get(version) ?? 0) + 1);
    }

    const [shared] = [...counts.entries()].sort((a, b) => b[1] - a[1])[0];
    const divergent = trees
        .filter((tree) => (tree.pinned?.version ?? null) !== null && tree.pinned.version !== shared)
        .map((tree) => ({ name: tree.name, version: tree.pinned.version }));

    return { shared, divergent };
}

/**
 * The pinned version of the first-party dependency, read from this tree's own `composer.lock`.
 *
 * Per-tree rather than per-repository: a branch is free to move the pin, and a slot sitting on that
 * branch should report what IT installs rather than what the primary does.
 *
 * @return {{version: ?string, reference: ?string}}
 */
function pinnedDependencyOf(treePath) {
    try {
        const lock = JSON.parse(readFileSync(join(treePath, 'composer.lock'), 'utf8'));
        const packages = [...(lock.packages ?? []), ...(lock['packages-dev'] ?? [])];
        const pkg = packages.find((entry) => entry.name === PINNED_PACKAGE);

        return { version: pkg?.version ?? null, reference: pkg?.source?.reference ?? null };
    } catch {
        return { version: null, reference: null };
    }
}

/**
 * How far the dependency's upstream has moved past the pinned revision, measured in its own clone.
 *
 * Counted against the clone's **local** `origin/main` so this never reaches the network — a status
 * command that blocks on a fetch is one people stop running. The cost is that the count can be
 * stale, which is why the clone's last fetch age is returned alongside it rather than left implied.
 *
 * @return {{ahead: ?number, staleDays: ?number}}
 */
function upstreamDistanceOf(clonePath, reference) {
    if (!clonePath || !reference || !existsSync(clonePath)) {
        return { ahead: null, staleDays: null };
    }

    const count = capture('git', ['-C', clonePath, 'rev-list', '--count', `${reference}..origin/main`]);
    const ahead = /^\d+$/.test(count ?? '') ? Number(count) : null;

    // FETCH_HEAD's mtime is when the clone last heard from the remote. Absent on a clone that has
    // never fetched, which is reported as unknown rather than as fresh.
    let staleDays = null;

    try {
        const stamp = statSync(join(clonePath, '.git', 'FETCH_HEAD')).mtimeMs;
        staleDays = Math.floor((Date.now() - stamp) / 86400000);
    } catch {
        staleDays = null;
    }

    return { ahead, staleDays };
}

function baseStateOf(treePath, branch, kind = SLOT) {
    return baseProbe(branch, capture('git', ['-C', treePath, 'rev-list', '--count', 'HEAD..origin/main']), kind);
}

/** @return array */
function inspect(repoRoot) {
    return worktreesOf(repoRoot).map((tree) => {
        const treeType = treeKind(tree.path, repoRoot);
        const { kind, drift, notices } = judgeTree(tree.path, repoRoot, {
            lockHash: lockHashOf(tree.path),
            stampHash: stampHashOf(tree.path),
            composerHash: composerHashOf(tree.path),
            stampComposerHash: stampHashOf(tree.path, 'composerHash'),
            patchHash: patchHashOf(tree.path),
            stampPatchHash: stampHashOf(tree.path, 'patchHash'),
            submodules: submoduleStateOf(tree.path),
            vendorCheckouts: vendorCheckoutsOf(tree.path),
            base: baseStateOf(tree.path, tree.branch, treeType),
            // Only over an initialized `content/`: an absent one is already a submodule drift line,
            // and the walk would fail on a directory that is not there
            contentMirror: existsSync(join(tree.path, 'content', '.git')) ? contentMirrorStateOf(tree.path) : null,
            puppeteerBrowser: puppeteerBrowserStateOf(tree.path),
        });

        return {
            ...tree,
            name: basename(tree.path),
            kind,
            role: kind === PRIMARY || kind === EPHEMERAL ? kind : slotOccupancy(tree),
            drift,
            notices,
            pinned: pinnedDependencyOf(tree.path),
        };
    });
}

/** @return number */
function commandStatus(repoRoot, argv) {
    const trees = inspect(repoRoot);

    if (argv.includes('--json')) {
        process.stdout.write(`${JSON.stringify(trees, null, 2)}\n`);
    } else {
        const width = Math.max(...trees.map((tree) => tree.name.length), 12);

        for (const tree of trees) {
            const full = tree.branch || '(detached)';
            // Issue-linked branch names run long enough to break the column; the number identifies
            // the ticket, which is what the reader is scanning for.
            const branch = full.length > 34 ? `${full.slice(0, 33)}…` : full;
            const shown = [...tree.drift, ...tree.notices];

            process.stdout.write(`${tree.name.padEnd(width)}  ${branch.padEnd(34)}  ${tree.role.padEnd(9)}  ${shown.length ? shown.join('; ') : '-'}\n`);
        }

        const { shared, divergent } = pinnedDependencyGrouping(trees);
        const clone = process.env[PINNED_CLONE_ENV] || null;
        const reference = trees.find((tree) => tree.pinned?.version === shared)?.pinned?.reference ?? null;
        const { ahead, staleDays } = upstreamDistanceOf(clone, reference);
        const notice = pinnedDependencyNotice({ version: shared, clone, ahead, staleDays });

        if (notice) {
            process.stdout.write(`\n${notice}\n`);
        }

        for (const tree of divergent) {
            process.stdout.write(`${tree.name.padEnd(width)}  pins ${tree.version} rather than ${shared}\n`);
        }
    }

    const drifted = trees.filter((tree) => tree.drift.length);

    // ---
    // `--check` carried its verdict ONLY in the exit code, and the ergonomic way to
    // run it discards that: `… --check 2>&1 | tail -6; echo $?` reports `tail`'s
    // status, not this one. Five sessions across four repositories hit that on this
    // command inside ten minutes on 2026-09-06, two of them publishing a drift-free
    // verdict that was wrong; one had merged the fix for the same trap earlier the
    // same session (UAMS-Web/uams-statamic-archive#2282).
    //
    // There is no symptom to notice — a `0` beside a clean-looking table reads
    // exactly like a pass, and it agrees with what the caller expected. So the
    // verdict is printed as well as returned. The exit code stays authoritative;
    // this only stops a pager from being the last thing that spoke.
    //
    // Printed for `--check` alone: a bare `status` is a human-facing table and does
    // not need it, and `--json` must stay parseable.
    // ---
    if (argv.includes('--check') && !argv.includes('--json')) {
        process.stdout.write(`\n${checkVerdictLine(drifted.map((tree) => tree.name))}\n`);
    }

    return argv.includes('--check') && drifted.length ? 1 : 0;
}

/** @return number */
function commandRefresh(repoRoot, name) {
    const slot = resolveSlot(worktreesOf(repoRoot), repoRoot, name);
    const path = slot.path;
    // Reconcile against the same expectations `status` judges by, so the two halves of the gate
    // cannot disagree — a refresh that installs what status does not require would silently pull
    // a 170 MB `public/assets` clone into the runner-owned CI slot that nothing there reads.
    const expected = expectationsFor(treeKind(path, repoRoot));

    process.stdout.write(`Refreshing ${basename(path)} (${slot.branch || 'detached'})\n`);

    if (expected.checkInstall) {
        // Refuse BEFORE anything touches vendor/: Composer fails on these only after it has begun
        // removing packages, which leaves the tree worse than it started (UAMS-Web/uams-statamic-archive#2691, UAMS-Web/uams-statamic-archive#2692).
        const blockingCheckouts = vendorCheckoutsOf(path).filter((checkout) => vendorCheckoutReason(checkout));

        if (blockingCheckouts.length > 0) {
            process.stderr.write(vendorCheckoutRecovery(basename(path), blockingCheckouts));

            throw new Error(`${blockingCheckouts.length} vendor source checkout(s) would block composer install`);
        }

        // Re-apply patches BEFORE the install below, so one `composer install` both reinstalls the
        // removed packages and re-patches them (UAMS-Web/uams-statamic-archive#2020).
        //
        // Deliberately NOT `composer patches-repatch`: it runs its inner install and then
        // `return 0` unconditionally (cweagans/composer-patches 2.0.0, RepatchCommand.php:56-62),
        // so a patch that FAILS to apply still exits 0. Stamping on that would certify a tree whose
        // patches provably did not apply, and the stamp would then suppress every later attempt —
        // locking in a worse false green than the one being fixed. Removing the patched packages
        // and letting the install rebuild them reaches the same state through a path whose failure
        // is observable, because an unapplicable patch fails the install with a non-zero exit.
        // Taken before the patched packages are removed below, so a failed install is also held
        // to account for those: removed on purpose, but still missing if the install never ran.
        const vendorBefore = vendorPackages(path);
        const patchReason = patchStaleReason(patchHashOf(path), stampHashOf(path, 'patchHash'));

        if (patchReason) {
            process.stdout.write(`re-applying patches — ${patchReason}\n`);

            const lock = join(path, 'patches.lock.json');

            for (const pkg of patchedPackages(existsSync(lock) ? readFileSync(lock, 'utf8') : null)) {
                rmSync(join(path, 'vendor', ...pkg.split('/')), { recursive: true, force: true });
            }
        }

        // Idempotent and cheap — it reports "Nothing to install" when the tree is already current.
        // Not atomic, though: an install that aborts partway can leave `vendor/` missing packages
        // it had before, so a failure is followed by the script's own account of the tree (UAMS-Web/uams-statamic-archive#2692).

        try {
            run('composer', ['install', '--no-interaction'], path);
        } catch (error) {
            const report = installFailureReport(basename(path), {
                before: vendorBefore,
                after: vendorPackages(path),
                probe: bootProbe(path),
            });

            process.stdout.write(`\n${report}\n`);

            throw error;
        }

        const reason = installStaleReason(lockHashOf(path), stampHashOf(path));

        if (reason) {
            process.stdout.write(`npm ci — ${reason}\n`);
            run('npm', ['ci'], path);
        } else {
            process.stdout.write('npm ci skipped — node_modules matches package-lock.json\n');
        }

        // Written after BOTH installs, and on every pass: `composer install` always ran, so the
        // recorded composer hash must move even on a run where npm was skipped.
        writeInstallStamp(path);
    } else {
        process.stdout.write(`installs skipped — ${basename(path)} is not this script's to bootstrap\n`);
    }

    if (expected.submodules.length > 0) {
        run('git', ['-C', path, 'submodule', 'update', '--init', ...expected.submodules], path);
    }

    // Clears the local Stache only: this runs outside the test environment, whose cache has its own
    // root. Tests see a content change through the mirror rebuild below, which wipes that cache.
    run('php', ['artisan', 'statamic:stache:clear'], path);

    // The submodule update above moves `content/` but not the test-environment copy every content
    // test reads, so rebuild that copy when it was built from a different `content/` (UAMS-Web/uams-statamic-archive#2935). The
    // seed refuses while a test process holds the mirror (UAMS-Web/uams-statamic-archive#2764); a refusal is reported below
    // rather than thrown, since every other step has already reconciled the tree.
    if (expected.checkContentMirror && contentMirrorStateOf(path)?.drifted) {
        process.stdout.write(`content mirror built from a different content/, rebuilding: ${CONTENT_MIRROR_SEED.command}\n`);

        try {
            run('php', [...CONTENT_MIRROR_SEED.args], path, CONTENT_MIRROR_SEED.env);
        } catch (error) {
            process.stdout.write(`content mirror rebuild failed: ${error.message}\n`);
        }
    }

    // `refresh` reconciles the ENVIRONMENT and deliberately does not move the base — relocating a
    // tree a session may be standing in is `release`'s job, not this one's. But saying `is current`
    // over a stale base is the misleading half of that (UAMS-Web/uams-statamic-archive#2148): the reader is told the tree is fine
    // and given no reason to check the one thing this command did not touch. Report it and name the
    // verb that fixes it.
    // Fetch before reading the base, and ONLY here. `HEAD..origin/main` is measured against the
    // local remote-tracking ref, so without this `refresh` would compute 0 against a ref that is
    // itself behind and print `is current` on a stale tree — UAMS-Web/uams-statamic-archive#2148's defect reproduced through its
    // own fix. `status` deliberately does not fetch: it is a pure read, and a read command reaching
    // the network surprises its caller. `refresh` has no such claim to make — it already runs
    // `composer install`, `npm ci`, and a `git submodule update` that talks to this same remote, so
    // one more git call is neither a surprise nor measurable against them.
    if (expected.checkBase) {
        run('git', fetchOriginArgs(path, '--quiet'), path);
    }

    const base = expected.checkBase
        ? baseState(baseStateOf(path, slot.branch, treeKind(path, repoRoot)))
        : { reason: null, notice: null };

    // Read again after the rebuild, so a refused or failed one is never followed by `is current`
    const mirrorReason = expected.checkContentMirror && existsSync(join(path, 'content', '.git'))
        ? contentMirrorReason(contentMirrorStateOf(path))
        : null;
    const { reason, notice } = refreshOutcome(base, mirrorReason);

    if (reason) {
        process.stdout.write(`${basename(path)} — environment reconciled, but ${reason}\n`);
    } else if (notice) {
        process.stdout.write(`${basename(path)} — environment reconciled; ${notice}\n`);
    } else {
        process.stdout.write(`${basename(path)} is current.\n`);
    }

    return 0;
}

/** @return number */
function commandRelease(repoRoot, name, requestedBranch) {
    // `refresh` gained the primary as a target; release and provision must NOT. Releasing detaches
    // the tree at origin/main, and the primary is the one tree that has to keep `main` checked out
    // — git allows a branch in one worktree only, so detaching it puts `main` nowhere.
    if (name === PRIMARY) {
        process.stderr.write('The primary checkout holds `main` and is never released — only a slot is.\n');

        return 1;
    }

    const slot = resolveSlot(worktreesOf(repoRoot), repoRoot, name);
    const path = slot.path;

    // Occupancy first (UAMS-Web/uams-statamic-archive#2273). Refusing here rather than after the tree reads keeps the cheapest
    // and most common refusal from paying for a status walk and a network fetch.
    const held = heldSlotRefusalReason(slot.branch, requestedBranch);

    if (held) {
        process.stderr.write(`Refusing to release ${basename(path)} — ${held}.\n`);
        process.stderr.write(`Releasing detaches the tree, so the holder's next commit would land on a detached HEAD.\n`);
        process.stderr.write(`If the branch is yours and finished: node scripts/worktree-slots.mjs release ${name} --branch ${slot.branch}\n`);

        return 1;
    }

    // The fetch runs BEFORE the tree is judged, deliberately. It touches no working-tree file, and
    // moving it out from between the guard and the detach takes the one network round-trip out of
    // the window in which a tree can be dirtied without the guard noticing.
    run('git', fetchOriginArgs(path), path);

    // Scoped to the same submodules `status` judges this tree by, so the two halves of the gate
    // cannot disagree about what the tree is even supposed to carry.
    const expected = expectationsFor(treeKind(path, repoRoot));
    const submodules = submoduleStateOf(path).filter((submodule) => expected.submodules.includes(submodule.path));
    const porcelain = capture('git', ['-C', path, 'status', '--porcelain', '--ignore-submodules=all']) || '';
    const refusal = releaseRefusal(porcelain, submodules);

    if (refusal) {
        process.stderr.write(`Refusing to release ${basename(path)} — ${refusal}\n`);
        process.stderr.write('Commit or move the work first; releasing detaches the tree and would strand it.\n');

        return 1;
    }

    // Re-read immediately before the detach. This NARROWS the check-then-act window to two
    // back-to-back local commands; it does not close it, and git offers no way to make the pair
    // atomic. Without it, work appearing after the guard is detached anyway and the success line
    // below prints over it.
    const raceAbort = releaseRaceAbortReason(
        porcelain,
        capture('git', ['-C', path, 'status', '--porcelain', '--ignore-submodules=all']) || '',
    );

    if (raceAbort) {
        process.stderr.write(`Aborting release of ${basename(path)} — ${raceAbort}.\n`);
        process.stderr.write('Nothing was detached. Re-run once the tree is settled.\n');

        return 1;
    }

    run('git', releaseCheckoutArgs(path), path);

    process.stdout.write(`${basename(path)} released — detached at origin/main, branch '${slot.branch || '(none)'}' preserved.\n`);

    return 0;
}

/**
 * The ordered commands that build a slot, up to the point the file copies take over.
 *
 * Exported and data-driven rather than a run of inline calls, because **the order is the thing
 * that was wrong** (UAMS-Web/uams-statamic-archive#1999) and a comment cannot stop an edit from restoring it. A test asserts the
 * sequence against this list, and `commandProvision` executes the same list — so the assertion
 * cannot drift away from the behavior it is guarding.
 *
 * Submodules come **before** `composer install`. Composer's `post-autoload-dump` runs
 * `artisan statamic:install`, which scaffolds the content tree
 * (`content/{assets,collections,globals,navigation,taxonomies}/.gitkeep`), and git refuses to
 * clone a submodule into a non-empty directory. Installing first therefore left every new slot
 * missing `content` — the one submodule the test suite needs — while the other three cloned, so
 * the tree looked provisioned and failed later with the null-method cascade.
 *
 * Reversing it is safe rather than a trade: the content submodule already carries every directory
 * `statamic:install` would create, so it scaffolds nothing and the submodule stays clean through
 * the install. Verified by running a full `composer install` over an initialized `content/` and
 * confirming `git -C content status --porcelain` stayed empty.
 *
 * @param  string  $repoRoot
 * @param  string  $path
 * @return array<int, {{command: string, args: array<int, string>, cwd: string}}>
 */
export function provisionSteps(repoRoot, path) {
    return [
        { command: 'git', args: fetchOriginArgs(repoRoot), cwd: repoRoot },
        { command: 'git', args: ['-C', repoRoot, 'worktree', 'add', '--detach', path, 'origin/main'], cwd: repoRoot },
        { command: 'git', args: ['-C', path, 'submodule', 'update', '--init', ...SUBMODULES], cwd: path },
        { command: 'composer', args: ['install', '--no-interaction'], cwd: path },
        { command: 'npm', args: ['ci'], cwd: path },
    ];
}

/**
 * The ordered commands that finish a slot once its files are in place.
 *
 * Split from {@see provisionSteps} because the `.env` rewrite and the runtime-database copies sit
 * between the two halves; keeping them as one list would have meant either running the file work
 * at the wrong point or faking a step for it.
 *
 * @param  string  $path
 * @return array<int, {{command: string, args: array<int, string>, cwd: string}}>
 */
export function provisionFinishSteps(path) {
    return [
        // Both bundles must come from ONE build, or the addon bundle expects shared dependencies
        // the core bundle no longer provides and the CP renders a 200 with a dead Vue app.
        { command: 'npm', args: ['run', 'build'], cwd: path },
        { command: 'npm', args: ['run', 'cp:build'], cwd: path },
        { command: 'php', args: ['artisan', 'optimize:clear'], cwd: path },
        { command: 'php', args: ['artisan', 'statamic:stache:clear'], cwd: path },
    ];
}

/** @return number */
function commandProvision(repoRoot, name) {
    if (name === PRIMARY) {
        process.stderr.write('The primary checkout is the clone itself — there is nothing to provision.\n');

        return 1;
    }

    const path = join(dirname(repoRoot), slotDirName(repoRoot, name));

    if (worktreesOf(repoRoot).some((tree) => normalizePath(tree.path) === normalizePath(path))) {
        process.stderr.write(`${basename(path)} is already a registered worktree. Use 'refresh ${name}' instead.\n`);

        return 1;
    }

    // Create the tree, populate its submodules, then install — in that order, see `provisionSteps`
    for (const step of provisionSteps(repoRoot, path)) {
        run(step.command, step.args, step.cwd);
    }

    writeInstallStamp(path);

    // The app key rides along with the env; APP_URL is then repointed at THIS slot's Herd host, or
    // the CP's post-login redirect silently bounces the browser to the primary.
    copyFileSync(join(repoRoot, '.env'), join(path, '.env'));
    const env = readFileSync(join(path, '.env'), 'utf8').replace(/^APP_URL=.*$/m, `APP_URL=http://${basename(path)}.test`);
    writeFileSync(join(path, '.env'), env);

    // Both runtime databases, not just the obvious one. Git-ignored, so neither travels with a
    // clone; tests run on `:memory:`, so these are purely for the served app.
    for (const relative of RUNTIME_DATABASES) {
        const source = join(repoRoot, ...relative);

        if (existsSync(source)) {
            mkdirSync(dirname(join(path, ...relative)), { recursive: true });
            copyFileSync(source, join(path, ...relative));
        }
    }

    // Build the bundles and clear the caches over the now-populated tree
    for (const step of provisionFinishSteps(path)) {
        run(step.command, step.args, step.cwd);
    }

    process.stdout.write(`${basename(path)} provisioned — http://${basename(path)}.test\n`);

    return 0;
}

function main() {
    const [command, name] = process.argv.slice(2).filter((arg) => !arg.startsWith('--'));
    const argv = process.argv.slice(2);

    let repoRoot;

    try {
        repoRoot = primaryCheckout(process.cwd());
    } catch (error) {
        process.stderr.write(`worktree-slots: ${error.message}\n`);
        process.exit(2);
    }

    try {
        switch (command) {
            case 'status': process.exit(commandStatus(repoRoot, argv)); break;
            case 'refresh': process.exit(commandRefresh(repoRoot, name)); break;
            case 'release': process.exit(commandRelease(repoRoot, name, flagValue(argv, '--branch'))); break;
            case 'provision': process.exit(commandProvision(repoRoot, name)); break;
            default:
                process.stderr.write('Usage: worktree-slots.mjs <status|refresh|release|provision> [slot] [--json] [--check] [--branch <name>]\n');
                process.exit(2);
        }
    } catch (error) {
        process.stderr.write(`worktree-slots: ${(error.message || '').split('\n')[0]}\n`);
        process.exit(2);
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    main();
}
