<?php

declare(strict_types=1);
use Pest\Plugins\Tia\Fingerprint;

/**
 * Tia safety guard (UAMS-Web/uams-statamic-archive#1717) — refuse `pest --tia` when its change detection is
 * structurally blind to a change that is actually present.
 *
 * Pest 5's Tia engine replays cached results for tests it believes a change cannot
 * reach. That is correct for PHP under version control, and UAMS-Web/uams-statamic-archive#1799 confirmed the
 * change detection itself works: Pest hashes the `token_get_all()` token stream, so
 * a comment-only edit is *rightly* filtered as behaviorally unchanged, and a
 * semantic edit re-runs the tests that reach it.
 *
 * WHERE IT IS BLIND: SUBMODULES. Tia's candidate set is `git diff --name-only
 * <sha>..HEAD` plus `git status --porcelain -z --untracked-files=all`
 * (ChangedFiles.php:257), run at the repository root. A submodule surfaces in those
 * as the bare directory path — `content` — and every consumer in `Graph::affected()`
 * then gates on `is_file($projectRoot.'/'.$rel)` (Graph.php:372, 425, 450, 500),
 * which is false for a directory. Content can never earn edges the ordinary way
 * either: edges come from PCOV line coverage, and YAML/Markdown is never executed
 * PHP. A graph recorded here held 152 files — 102 `app/`, 35 `config/`, 11 `tests/`,
 * 3 `routes/`, 1 `bootstrap/` — and zero under `content/`.
 *
 * So a content change of ANY shape yields an empty affected set, and Tia replays
 * every cached result. DEMONSTRATED: adding one `people_groups` entry made `--tia`
 * report `2 passed, 2 replayed` in 3.02s (exit 0) while `--no-tia` on the identical
 * tree reported `1 failed` in 45.28s (exit 1).
 *
 * THE THREE SHAPES A CONTENT CHANGE TAKES, all of which must be caught:
 *
 *  1. A DIRTY WORKTREE — an edited or new file inside the submodule. `.gitmodules`
 *     sets `ignore = dirty` on `content`, so the ROOT status prints nothing at all.
 *  2. HEAD/GITLINK DRIFT — the submodule checked out at a revision other than the
 *     one the superproject records, e.g. previewing a content PR with
 *     `git -C content checkout <branch>`. The submodule's own status is CLEAN here.
 *  3. A COMMITTED GITLINK BUMP — the superproject moves the pointer and
 *     `git submodule update` follows it. Nothing is dirty and nothing has drifted;
 *     the tree is entirely clean, and the content on disk is nonetheless different
 *     from the content the graph was recorded against.
 *
 * Shape 3 is the ROUTINE one and the reason this guard tracks state across runs
 * rather than only inspecting the tree. In this repo **25 of the last 300 commits
 * move the `content` gitlink**, and `1ba5c02ce` is a commit whose entire diff is the
 * single path `content`. The `sync-pr-branch` rule further requires merging
 * `origin/main` before validating, so picking up a content bump is the normal case,
 * not an edge case. Worse, it is self-concealing: Tia's `addOutput()` calls
 * `bumpRecordedSha()` after any replay, advancing the baseline past the bump so the
 * path never appears in a later diff and the stale results are baked in for good.
 *
 * HOW STATE IS TRACKED. A fingerprint of every declared submodule — its revision,
 * or `absent` — is written to `storage/app/` by `--record`, which is what
 * `composer test:tia:fresh` runs alongside `pest --tia --fresh`. A plain
 * `composer test:tia` then refuses unless the current fingerprint matches the
 * recorded one. The guard deliberately does NOT read Pest's own graph to find its
 * baseline sha: that would mean replicating `Storage::projectKey()`'s origin-URL
 * derivation and its linked-worktree fallback, pinning us to vendor internals that
 * UAMS-Web/uams-statamic-archive#1717 already found to be accidental. Owning one small file is cheaper and cannot
 * silently drift.
 *
 * A MISSING STATE FILE REFUSES. That is deliberate fail-closed behavior: absent
 * state is indistinguishable from state that was wiped while a graph survived, and
 * the safe reading of "I don't know" is "don't replay".
 *
 * SECOND HAZARD: NO COVERAGE DRIVER. Tia records its edges from coverage. With no
 * driver the graph records zero edges, and a graph with no edges cannot mark
 * anything affected — so every subsequent run replays. Note this is exactly the
 * state BOTH gate paths run in — `ci.yml` pins `coverage: none` and `local-ci.mjs`
 * passes `-d pcov.enabled=0` — which is one of the three reasons Tia is excluded
 * from the gate entirely. See the `PARITY` comments in those two files.
 *
 * WHY A GUARD AND NOT A FIX. Removing `ignore = dirty` from `content` would make
 * shape 1 visible to the root status, but not usable: the `is_file()` drop above
 * discards the path anyway, and shapes 2 and 3 are untouched by it. The blind spot
 * is a property of combining a submodule-backed content store with a file-graph
 * impact analyser, so the honest move is to refuse the run rather than to report a
 * green one.
 *
 * Written in PHP rather than Node to match {@see check-skip-baseline.php} and
 * {@see check-slowest-class.php}, and framework-free so it needs no booted app.
 *
 * USAGE
 *     php scripts/check-tia-safe.php [--quiet] [--record] [--root=<path>]
 *
 * Unrecognized arguments are ignored on purpose: `composer test:tia` forwards the
 * user's trailing arguments (a test path, a `--filter`) through to Pest, and
 * Composer's forwarding across a multi-step script must not turn a legitimate
 * `composer test:tia -- tests/Feature/FooTest.php` into a guard failure.
 *
 * `--root` exists so {@see TiaSafetyGuardTest} can drive the real CLI against a
 * throwaway fixture repository. A test must never dirty this repo's own `content/`
 * submodule to prove the guard fires — that is a live, shared working tree, and
 * UAMS-Web/uams-statamic-archive#1873 exists because tests writing into it caused real damage.
 *
 * EXIT
 *     0  `--tia` is safe to run in this working tree
 *     1  it is not — a submodule is dirty, drifted, or moved since the graph was
 *        recorded, or no coverage driver is active
 */

require_once __DIR__.'/tia-blindness.php';

/** Exit status meaning `--tia` may run. */
const TIA_SAFE = 0;

/** Exit status meaning `--tia` would replay a stale result. */
const TIA_UNSAFE = 1;

/** Where the recorded submodule fingerprint lives (`storage/app/` is git-ignored). */
const TIA_STATE_FILE = 'storage/app/tia-submodule-state.json';

$root = dirname(__DIR__);
$quiet = in_array('--quiet', $argv, true);
$record = in_array('--record', $argv, true);

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $root = rtrim(substr($argument, 7), '/\\');
    }
}

/**
 * Run a git command and return its trimmed stdout, or null when git failed.
 *
 * @param  list<string>  $arguments  Arguments after `git`.
 */
function git(string $directory, array $arguments): ?string
{
    $command = 'git -C '.escapeshellarg($directory);
    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg($argument);
    }

    /** @var list<string> $output */
    $output = [];
    $status = 0;
    exec($command.' 2>&1', $output, $status);

    return $status === 0 ? trim(implode("\n", $output)) : null;
}

/**
 * Every submodule path declared in `.gitmodules`, in declaration order.
 *
 * Parsed from the file rather than taken from `git submodule status`, because the
 * declaration is what this check asks about: which submodules the repo *declares*,
 * initialized or not. Reading the declaration and then asking each submodule
 * directly answers that; `git submodule status` answers a different question.
 *
 * This docblock used to justify the choice differently — that from a linked
 * worktree `git submodule status` resolves through a shared module directory and
 * reports the PRIMARY checkout's revision, "a documented trap in the `worktrees`
 * rule". That did not reproduce on the pinned git: every tree reported its own
 * revision, including one sitting on a divergent commit (UAMS-Web/uams-statamic-archive#1959). The parsing is
 * still right, for the reason above.
 *
 * @param  string  $root  Absolute path to the repository root.
 * @return list<string> Repo-relative submodule paths, e.g. `content`.
 */
function declaredSubmodulePaths(string $root): array
{
    $file = $root.'/.gitmodules';
    if (! is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    if ($contents === false) {
        return [];
    }

    /** @var list<string> $paths */
    $paths = [];
    if (preg_match_all('/^\s*path\s*=\s*(.+?)\s*$/m', $contents, $matches) === false) {
        return [];
    }

    foreach ($matches[1] as $path) {
        $paths[] = trim($path, "\"'");
    }

    return $paths;
}

/**
 * The current state of one declared submodule.
 *
 * Returns one of:
 *   `absent`        — not initialized on disk
 *   `dirty:<lines>` — working tree has uncommitted or untracked changes (shape 1)
 *   `drift:<a>!<b>` — checked-out revision differs from the recorded gitlink (2)
 *   `<revision>`    — clean, and matching the gitlink; this is the fingerprint (3)
 *
 * `--untracked-files=all` lists each new file individually rather than collapsing a
 * new directory to its top level, so the refusal message can name the actual file.
 *
 * @param  string  $root  Absolute path to the repository root.
 * @param  string  $path  Repo-relative submodule path.
 */
function submoduleState(string $root, string $path): string
{
    $absolute = $root.'/'.$path;
    if (! file_exists($absolute.'/.git')) {
        return 'absent';
    }

    $status = git($absolute, ['status', '--porcelain', '--untracked-files=all']);
    if ($status === null) {
        // Unreadable is not clean. Failing closed here matters because every other
        // branch of this function is a refusal too.
        return 'dirty:could not read submodule status';
    }
    if ($status !== '') {
        return 'dirty:'.$status;
    }

    $head = git($absolute, ['rev-parse', 'HEAD']);
    $gitlink = git($root, ['rev-parse', 'HEAD:'.$path]);

    if ($head === null || $gitlink === null) {
        return 'dirty:could not resolve submodule revision';
    }

    return $head === $gitlink ? $head : sprintf('drift:%s!%s', $head, $gitlink);
}

/**
 * The name of the active coverage driver, or null when none can record edges.
 *
 * PCOV loaded but switched off counts as ABSENT, which is the whole point: that is
 * precisely how both gate paths run, and a loaded-but-disabled extension would
 * otherwise pass an `extension_loaded()` check while recording nothing.
 *
 * `filter_var(..., FILTER_VALIDATE_BOOLEAN)` rather than a `!== '0'` comparison:
 * PHP returns an EMPTY STRING, not `'0'`, for a boolean INI set to `Off`, `False`
 * or `No`, so a single-literal test reports the driver active while PCOV records
 * nothing — a fail-open in the one check that exists to fail closed.
 */
function activeCoverageDriver(): ?string
{
    if (extension_loaded('pcov') && filter_var(ini_get('pcov.enabled'), FILTER_VALIDATE_BOOLEAN)) {
        return 'pcov';
    }

    if (extension_loaded('xdebug')) {
        $mode = ini_get('xdebug.mode');
        if (is_string($mode) && str_contains($mode, 'coverage')) {
            return 'xdebug';
        }
    }

    return null;
}

/**
 * Gather what {@see tiaBlindFingerprintInputs} needs, and delegate the decision.
 *
 * This half is I/O only — which files are present and tracked, and what Pest reports
 * for them. The decision itself lives in `scripts/tia-blindness.php` so it can be
 * tested without constructing the exotic worktree shape that triggers the fault; see
 * that file for the measurements and for why the trigger is not fully characterized.
 *
 * Asking Pest for the real fingerprint rather than re-deriving the condition from the
 * directory layout is deliberate: when upstream lands [`pestphp/pest#1813`] the
 * measurement starts succeeding on its own and this refusal disappears, whereas a
 * layout rule would keep refusing in a tree that had been fixed — and a guard that can
 * never go green is one people learn to skip (UAMS-Web/uams-statamic-archive#1952).
 *
 * @return list<string> the blind inputs, empty when the fingerprint is trustworthy here
 */
function fingerprintBlindInputs(string $root): array
{
    // Only files that genuinely exist AND are tracked can prove anything.
    $provable = [];
    foreach (TIA_FINGERPRINT_CANDIDATES as $key => $file) {
        if (! is_file($root.'/'.$file)) {
            continue;
        }
        if (git($root, ['ls-files', '--error-unmatch', $file]) === null) {
            continue;
        }
        $provable[$key] = $file;
    }

    if ($provable === []) {
        return [];
    }

    // The guard is otherwise framework-free, but the autoloader is not a booted app and
    // this check is worth far more measured than inferred. Loading it lazily keeps the
    // script usable against a fixture `--root` that has no vendor/ of its own.
    $autoload = dirname(__DIR__).'/vendor/autoload.php';

    if (is_file($autoload)) {
        require_once $autoload;
    }

    if (! class_exists(Fingerprint::class)
        || ! method_exists(Fingerprint::class, 'compute')) {
        // Cannot measure. Fall back to the characterized condition: a linked worktree at
        // a path its enclosing repository ignores. See `tia-blindness.php` for the third
        // condition (a realpath root) that this cannot check and does not need to — a
        // symlinked root makes the fault invisible rather than present.
        return isIgnoredByEnclosingRepository($root) ? array_values($provable) : [];
    }

    /** @var array{structural: array<string, int|string|null>} $fingerprint */
    $fingerprint = Fingerprint::compute($root);

    return tiaBlindFingerprintInputs($provable, $fingerprint['structural']);
}

/**
 * Whether an enclosing repository's ignore rules cover `$root` itself.
 *
 * The fallback for {@see fingerprintBlindInputs} when Pest's internals cannot be
 * measured, and the ACTUAL condition — which is narrower than upstream's account.
 * [`pestphp/pest#1813`] attributes the fault to a linked worktree having an ancestor
 * with a `.git` directory. Measured here, that is necessary but NOT sufficient: a
 * linked worktree at `<repo>/tmp-probe` resolved its fingerprint correctly, while one
 * at `<repo>/.claude/worktrees/<name>` did not. The difference is that
 * `.claude/worktrees/` is listed in the repo's own `.gitignore`, so Finder's
 * `ignoreVCSIgnored(true)` treats everything beneath it as ignored and returns no
 * results for plainly tracked files.
 *
 * That distinction is load-bearing rather than pedantic: `EnterWorktree` puts every
 * ephemeral tree under exactly that ignored path, so the exposure is systematic for
 * that flow and absent for a worktree placed anywhere else.
 */
function isIgnoredByEnclosingRepository(string $root): bool
{
    if (! is_file($root.'/.git')) {
        return false;
    }

    $directory = dirname($root);

    while ($directory !== dirname($directory)) {
        if (is_dir($directory.'/.git')) {
            return git($directory, ['check-ignore', '-q', $root]) !== null;
        }

        $directory = dirname($directory);
    }

    return false;
}

/** Emit a refusal and exit non-zero. */
function refuse(string $headline, string $explanation, string $remedy): never
{
    fwrite(STDERR, "\n  Refusing to run --tia: ".$headline."\n\n");
    fwrite(STDERR, $explanation."\n");
    fwrite(STDERR, "\n".$remedy."\n\n");

    exit(TIA_UNSAFE);
}

/** @var array<string, string> $current */
$current = [];
foreach (declaredSubmodulePaths($root) as $path) {
    $current[$path] = submoduleState($root, $path);
}

// --- Absolute refusals: the tree itself disagrees with the commit. ---------------

$blindInputs = fingerprintBlindInputs($root);

if ($blindInputs !== []) {
    refuse(
        'Tia reports tracked files as absent, so it cannot see structural changes.',
        "  Pest validates a cached graph by fingerprinting composer.lock, phpunit.xml and\n"
        ."  the JS lock files. The inputs below are present AND git-tracked here, yet Pest\n"
        ."  reports them absent -- so every structural input is null, `structuralMatches()`\n"
        ."  compares null to null, and a dependency change invalidates NOTHING. --tia would\n"
        ."  replay results recorded against a different composer.lock (UAMS-Web/uams-statamic-archive#1996).\n\n"
        .'    reported absent despite being tracked: '.implode(', ', $blindInputs)."\n\n"
        ."  The usual cause is the worktree shape: an ephemeral tree under an ignored path\n"
        ."  (what `EnterWorktree` creates) hits pestphp/pest#1810. Sibling slots do not.\n"
        .'  An unreadable file produces the same reading for a different reason.',
        "  Run Tia from a sibling slot instead (see .claude/rules/worktrees.md), or run\n"
        .'  without it here:'."\n\n      vendor/bin/pest --no-tia"
    );
}

foreach ($current as $path => $state) {
    if (str_starts_with($state, 'dirty:')) {
        refuse(
            sprintf('the `%s` submodule has uncommitted changes.', $path),
            "  Tia detects changes with `git status --porcelain` at the repository root,\n"
            ."  which cannot see these. `.gitmodules` sets `ignore = dirty` on content/, and\n"
            ."  a submodule path is a directory, which Tia's watch-pattern fallback drops.\n"
            ."  So --tia would replay a cached pass over content it never re-read (UAMS-Web/uams-statamic-archive#1717).\n\n"
            .'    '.str_replace("\n", "\n    ", substr($state, 6)),
            "  Commit or revert the submodule, or run without Tia:\n\n      vendor/bin/pest --no-tia"
        );
    }

    if (str_starts_with($state, 'drift:')) {
        [$head, $gitlink] = explode('!', substr($state, 6), 2);
        refuse(
            sprintf('the `%s` submodule is checked out at a different revision.', $path),
            "  The submodule's own working tree is clean, so `git status` inside it reports\n"
            ."  nothing — but it does not match the revision this commit records, and Tia\n"
            ."  cannot see the difference either way (UAMS-Web/uams-statamic-archive#1717).\n\n"
            .sprintf("    checked out : %s\n    recorded    : %s", $head, $gitlink),
            sprintf("  Restore the recorded revision, or run without Tia:\n\n      git submodule update %s\n      vendor/bin/pest --no-tia", $path)
        );
    }
}

// --- The coverage driver, without which the graph records nothing. ---------------

$driver = activeCoverageDriver();
if ($driver === null) {
    refuse(
        'no active coverage driver.',
        "  Tia records its impact graph from coverage. With no driver it records zero\n"
        ."  edges, and a graph with no edges marks nothing affected -- so every later run\n"
        .'  replays and reports a green suite having executed nothing.',
        "  Enable PCOV (see .claude/skills/pcov-setup/SKILL.md), or run without Tia:\n\n      vendor/bin/pest --no-tia"
    );
}

// --- Cross-run state: has content moved since the graph was recorded? ------------

$stateFile = $root.'/'.TIA_STATE_FILE;

if ($record) {
    $directory = dirname($stateFile);
    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }
    file_put_contents($stateFile, json_encode($current, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

    if (! $quiet) {
        fwrite(STDOUT, sprintf("  Tia safety guard: recorded submodule state, coverage driver '%s' active.\n", $driver));
    }

    exit(TIA_SAFE);
}

$recorded = null;
if (is_file($stateFile)) {
    $raw = file_get_contents($stateFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            /** @var array<string, string> $decoded */
            $recorded = $decoded;
        }
    }
}

if ($recorded === null) {
    refuse(
        'no recorded submodule state to compare against.',
        "  A replay is only sound if the content on disk is the content the graph was\n"
        ."  recorded against, and there is no record of that. Absent state is\n"
        ."  indistinguishable from state that was wiped while a graph survived, so the\n"
        ."  safe reading of \"I don't know\" is \"don't replay\" (UAMS-Web/uams-statamic-archive#1717).",
        "  Record it, rebuilding the graph in the same step:\n\n      composer test:tia:fresh"
    );
}

if ($recorded !== $current) {
    /** @var list<string> $moved */
    $moved = [];
    foreach ($current as $path => $state) {
        $was = $recorded[$path] ?? 'not recorded';
        if ($was !== $state) {
            $moved[] = sprintf('%s: %s -> %s', $path, $was, $state);
        }
    }
    foreach ($recorded as $path => $state) {
        if (! array_key_exists($path, $current)) {
            $moved[] = sprintf('%s: %s -> no longer declared', $path, $state);
        }
    }

    refuse(
        'a submodule has moved since the graph was recorded.',
        "  Nothing is dirty -- the pointer itself moved, which is how content normally\n"
        ."  arrives here (25 of the last 300 commits move the `content` gitlink, and\n"
        ."  syncing from origin/main before validating is required). Tia sees only the\n"
        ."  bare directory path and drops it, so it would replay results recorded against\n"
        ."  the OLD content and then advance its own baseline past the change (UAMS-Web/uams-statamic-archive#1717).\n\n"
        .'    '.implode("\n    ", $moved),
        "  Rebuild the graph against the current content:\n\n      composer test:tia:fresh"
    );
}

if (! $quiet) {
    fwrite(STDOUT, sprintf("  Tia safety guard: submodules match the recorded graph, coverage driver '%s' active.\n", $driver));
}

exit(TIA_SAFE);
