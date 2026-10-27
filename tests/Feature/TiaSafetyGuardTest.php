<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for the Tia safety check (UAMS-Web/uams-statamic-archive#1717). Runs
 * `scripts/check-tia-safe.php` against throwaway fixture repositories and asserts
 * it refuses every tree where `pest --tia` would replay a stale result — proving
 * the guard is live, not vacuous.
 *
 * WHAT IT IS PROTECTING. Tia replays cached results for tests a change cannot
 * reach. That is correct for tracked PHP, but the affected set also comes back
 * empty when Tia is structurally blind, and then a green run has executed nothing.
 * Reproduced on this repo: with one `people_groups` entry added, `--tia` reported
 * `2 passed, 2 replayed` in 3.02s (exit 0) while `--no-tia` on the identical tree
 * reported `1 failed` in 45.28s (exit 1).
 *
 * All three shapes a content change takes are covered here, because the guard is
 * only as good as its weakest one and the routine shape is the least obvious:
 * a dirty worktree, HEAD/gitlink drift, and a committed pointer bump against which
 * the whole tree is clean.
 *
 * WHY FIXTURE REPOSITORIES AND NOT THIS ONE. Proving the guard fires means moving a
 * submodule, and this repo's `content/` is a live, shared working tree that other
 * worktrees and sessions read. UAMS-Web/uams-statamic-archive#1873 exists precisely because tests wrote into it.
 * The guard takes a `--root` for exactly this reason, so each case builds its own
 * repository under `sys_get_temp_dir()` and never touches the real one.
 *
 * Driven as a subprocess (like {@see SlowestClassGuardTest} and
 * {@see SkipBaselineGuardTest}) rather than by requiring the script: that pins the
 * real CLI contract — the exit code and the text it prints — which is what
 * `composer test:tia` actually depends on. Framework-free, per-run temp paths, so
 * it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/TiaSafetyGuardTest.php
 */
$root = dirname(__DIR__, 2);
$script = $root.'/scripts/check-tia-safe.php';

/**
 * Whether a CHILD php process on this machine has a coverage driver.
 *
 * Probed by running a child rather than read from this process's own `ini_get`,
 * and that distinction is load-bearing: the gate runs the suite under
 * `-d pcov.enabled=0`, so the PARENT reports no driver while the children this test
 * spawns — which inherit `php.ini`, not the parent's `-d` flags — report one. The
 * guard's positive path exits 0 only with a driver, so without this probe the two
 * positive cases would assert an outcome that depends on ambient machine
 * configuration rather than on the code, and would go red on any checkout without
 * PCOV installed (including a CI runner, where `coverage: none` is pinned).
 */
$childHasCoverageDriver = (function () use ($root): bool {
    $probe = new Process([
        PHP_BINARY,
        '-r',
        'echo ((extension_loaded("pcov") && filter_var(ini_get("pcov.enabled"), FILTER_VALIDATE_BOOLEAN))'
            .' || (extension_loaded("xdebug") && str_contains((string) ini_get("xdebug.mode"), "coverage"))) ? "1" : "0";',
    ], $root);
    $probe->run();

    return trim($probe->getOutput()) === '1';
})();

/**
 * Run a git command in `$cwd` and return its trimmed stdout.
 *
 * The identity comes from `-c` flags rather than global config, so the fixtures
 * commit on a machine whose git identity is unset (a fresh CI runner) without
 * writing to `~/.gitconfig`.
 *
 * The arguments are narrowed to `array<string>` through {@see mustBeString()} — the
 * suite's shared narrowing helper — rather than a `(string)` cast, which PHPStan
 * rejects on `mixed` and which would hide a genuinely wrong argument instead of
 * failing on it. The narrowing is needed at all only because PHPStan does not apply
 * a docblock to a closure assigned to a variable, so a `@param list<string>` tag
 * here would be decoration that level 10 rightly ignores.
 */
$git = function (string $cwd, array $arguments): string {
    $process = new Process(array_merge(
        ['git', '-c', 'user.email=guard@example.invalid', '-c', 'user.name=Guard', '-C', $cwd],
        array_map(static fn (mixed $argument): string => mustBeString($argument, 'git argument'), $arguments)
    ), $cwd);
    $process->run();

    return trim($process->getOutput());
};

/**
 * Build a throwaway superproject with one initialized submodule at `content`.
 *
 * Both repositories get a real commit, because the guard compares the submodule's
 * `HEAD` against the superproject's recorded gitlink — neither of which exists in a
 * repository with no commits.
 *
 * @return array{0: string, 1: string} The superproject root and the submodule's revision.
 */
$makeRepo = function () use ($git): array {
    $dir = sys_get_temp_dir().'/tia-guard-'.bin2hex(random_bytes(6));
    mkdir($dir.'/content/collections/people/default', 0o777, true);

    $git($dir.'/content', ['init', '--quiet', '--initial-branch=main']);
    file_put_contents($dir.'/content/collections/people/default/nyota-uhura.md', "---\ntitle: Nyota Uhura\n---\n");
    $git($dir.'/content', ['add', '-A']);
    $git($dir.'/content', ['commit', '--quiet', '-m', 'seed']);
    $revision = $git($dir.'/content', ['rev-parse', 'HEAD']);

    // `ignore = dirty` is the real setting from this repo's .gitmodules, and the
    // reason the root `git status` cannot see content changes at all.
    file_put_contents(
        $dir.'/.gitmodules',
        "[submodule \"content\"]\n\tpath = content\n\turl = https://example.invalid/content.git\n\tignore = dirty\n"
    );

    // The guard writes its state file under storage/, which the real repo ignores
    // via storage/app/.gitignore. Mirror that here, so "the whole tree is clean"
    // stays a meaningful assertion instead of tripping over the guard's own output.
    file_put_contents($dir.'/.gitignore', "storage/\n");

    $git($dir, ['init', '--quiet', '--initial-branch=main']);
    // Register the gitlink without a network fetch: stage the submodule's commit as
    // a mode-160000 entry directly, which is exactly what `git submodule add` writes.
    $git($dir, ['update-index', '--add', '--cacheinfo', '160000,'.$revision.',content']);
    $git($dir, ['add', '.gitmodules', '.gitignore']);
    $git($dir, ['commit', '--quiet', '-m', 'seed superproject']);

    return [$dir, $revision];
};

/**
 * Remove a fixture repository and everything under it.
 */
$cleanUp = function (string $dir): void {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $path = mustBeString($item instanceof SplFileInfo ? $item->getPathname() : '', 'fixture path');
        // Git marks objects read-only on Windows, which blocks unlink().
        @chmod($path, 0o777);
        is_dir($path) ? rmdir($path) : unlink($path);
    }

    rmdir($dir);
};

/**
 * Run the guard against a fixture root and return [exitCode, combined output].
 *
 * @param  bool  $withCoverage  Whether to leave a coverage driver enabled.
 * @param  bool  $record  Whether to record the current submodule state.
 * @return array{0: int, 1: string}
 */
$runGuard = function (string $fixtureRoot, bool $withCoverage = true, bool $record = false) use ($script, $root): array {
    // PHP_BINARY, not a bare `php`: the suite runs under an interpreter that is not
    // always the PATH default (Herd). Matches SlowestClassGuardTest.
    $command = [PHP_BINARY];
    if (! $withCoverage) {
        $command[] = '-d';
        $command[] = 'pcov.enabled=0';
        $command[] = '-d';
        $command[] = 'xdebug.mode=off';
    }
    $command[] = $script;
    $command[] = '--root='.$fixtureRoot;
    if ($record) {
        $command[] = '--record';
    }

    $process = new Process($command, $root);
    $process->run();

    return [$process->getExitCode() ?? -1, $process->getOutput().$process->getErrorOutput()];
};

it('refuses a dirty submodule worktree, naming the offending file', function () use ($makeRepo, $runGuard, $cleanUp): void {
    [$repo] = $makeRepo();
    $runGuard($repo, record: true);

    // Untracked, not modified: the change that motivated this guard was a NEW entry
    // file, and `--untracked-files=all` is what names it individually rather than
    // collapsing a new directory to its top level.
    file_put_contents($repo.'/content/collections/people/default/hikaru-sulu.md', "---\ntitle: Hikaru Sulu\n---\n");

    [$code, $output] = $runGuard($repo);

    expect($code)->toBe(1);
    expect($output)->toContain('uncommitted changes');
    // The offending path is named, not just counted: a guard that says "something is
    // dirty" without saying what sends you hunting through a 40k-file submodule.
    expect($output)->toContain('hikaru-sulu.md');

    $cleanUp($repo);
});

it('refuses when the submodule is checked out off its recorded gitlink', function () use ($makeRepo, $runGuard, $cleanUp, $git): void {
    // Shape 2. The submodule's OWN status is clean here, so a guard that only ran
    // `git -C content status` would pass this tree.
    [$repo] = $makeRepo();
    $runGuard($repo, record: true);

    file_put_contents($repo.'/content/collections/people/default/pavel-chekov.md', "---\ntitle: Pavel Chekov\n---\n");
    $git($repo.'/content', ['add', '-A']);
    $git($repo.'/content', ['commit', '--quiet', '-m', 'move the submodule forward']);

    $status = new Process(['git', '-C', $repo.'/content', 'status', '--porcelain'], $repo);
    $status->run();
    expect(trim($status->getOutput()))->toBe('');

    [$code, $output] = $runGuard($repo);

    expect($code)->toBe(1);
    expect($output)->toContain('different revision');

    $cleanUp($repo);
});

it('refuses when the recorded gitlink moves under a wholly clean tree', function () use ($makeRepo, $runGuard, $cleanUp, $git, $childHasCoverageDriver): void {
    // Shape 3 — the routine one. Nothing is dirty and nothing has drifted: the
    // superproject pointer moved and the submodule followed it, exactly as
    // `git merge origin/main && git submodule update content` produces. 25 of the
    // last 300 commits on main do this. Only cross-run state can catch it.
    [$repo] = $makeRepo();
    $runGuard($repo, record: true);

    file_put_contents($repo.'/content/collections/people/default/montgomery-scott.md', "---\ntitle: Montgomery Scott\n---\n");
    $git($repo.'/content', ['add', '-A']);
    $git($repo.'/content', ['commit', '--quiet', '-m', 'new content']);

    $head = new Process(['git', '-C', $repo.'/content', 'rev-parse', 'HEAD'], $repo);
    $head->run();
    $revision = trim($head->getOutput());

    // The superproject records the NEW revision, so gitlink == HEAD and the whole
    // tree is clean. This is the state a guard inspecting only the tree cannot see.
    $git($repo, ['update-index', '--cacheinfo', '160000,'.$revision.',content']);
    $git($repo, ['commit', '--quiet', '-m', 'bump content pointer']);

    $status = new Process(['git', '-C', $repo, 'status', '--porcelain'], $repo);
    $status->run();
    expect(trim($status->getOutput()))->toBe('');

    [$code, $output] = $runGuard($repo);

    expect($code)->toBe(1);
    // With no coverage driver the guard refuses on the driver before it reaches this
    // state check (and `--record` above refuses too), so the probed driver decides
    // which refusal the output names (UAMS-Web/uams-statamic-archive#3020).
    expect($output)->toContain($childHasCoverageDriver ? 'moved since the graph was recorded' : 'no active coverage driver');

    $cleanUp($repo);
});

it('refuses when no submodule state has been recorded, rather than assuming safety', function () use ($makeRepo, $runGuard, $cleanUp, $childHasCoverageDriver): void {
    // Fail closed: absent state is indistinguishable from state wiped while a graph
    // survived, so "I don't know" must not read as "safe".
    [$repo] = $makeRepo();

    [$code, $output] = $runGuard($repo);

    expect($code)->toBe(1);
    // With no coverage driver the guard refuses on the driver before it reaches this
    // state check, so the probed driver decides which refusal the output names (UAMS-Web/uams-statamic-archive#3020).
    if ($childHasCoverageDriver) {
        expect($output)->toContain('no recorded submodule state');
        expect($output)->toContain('composer test:tia:fresh');
    } else {
        expect($output)->toContain('no active coverage driver');
    }

    $cleanUp($repo);
});

it('allows the run when the tree matches the recorded state', function () use ($makeRepo, $runGuard, $cleanUp, $childHasCoverageDriver): void {
    [$repo] = $makeRepo();
    $runGuard($repo, record: true);

    [$code, $output] = $runGuard($repo);

    // Asserted against a PROBED driver rather than an assumed one, so this pins the
    // guard's contract on any machine instead of this machine's php.ini.
    expect($code)->toBe($childHasCoverageDriver ? 0 : 1);
    expect($output)->not->toContain('uncommitted changes');
    expect($output)->not->toContain('moved since the graph was recorded');

    $cleanUp($repo);
});

it('treats a submodule appearing or disappearing as a change, not as clean', function () use ($makeRepo, $runGuard, $cleanUp, $childHasCoverageDriver): void {
    // The `absent` state is a real fingerprint value, not a skip. Without it,
    // removing a submodule between runs would read as "nothing changed" while
    // every content-dependent test silently replays a pass it can no longer earn.
    [$repo] = $makeRepo();
    $runGuard($repo, record: true);

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repo.'/content/.git', FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $path = mustBeString($item instanceof SplFileInfo ? $item->getPathname() : '', 'fixture path');
        @chmod($path, 0o777);
        is_dir($path) ? rmdir($path) : unlink($path);
    }
    rmdir($repo.'/content/.git');

    [$code, $output] = $runGuard($repo);

    expect($code)->toBe(1);
    // With no coverage driver the guard refuses on the driver before it reaches this
    // state check (and `--record` above refuses too), so the probed driver decides
    // which refusal the output names (UAMS-Web/uams-statamic-archive#3020).
    expect($output)->toContain($childHasCoverageDriver ? 'absent' : 'no active coverage driver');

    $cleanUp($repo);
});

it('refuses to run with no active coverage driver, because Tia would record zero edges', function () use ($makeRepo, $runGuard, $cleanUp): void {
    // Disables BOTH drivers: activeCoverageDriver() accepts Xdebug too, and a Herd
    // machine that has run `herd coverage` has it in coverage mode.
    [$repo] = $makeRepo();
    $runGuard($repo, record: true);

    [$code, $output] = $runGuard($repo, withCoverage: false);

    expect($code)->toBe(1);
    expect($output)->toContain('no active coverage driver');

    $cleanUp($repo);
});

it('ignores trailing arguments so composer can forward a test path to Pest', function () use ($makeRepo, $script, $root, $cleanUp, $childHasCoverageDriver): void {
    // `composer test:tia -- tests/Feature/FooTest.php` forwards the user's trailing
    // arguments across the script's steps. If the guard treated an unrecognized
    // argument as an error, that legitimate invocation would fail before Pest ran.
    [$repo] = $makeRepo();
    (new Process([PHP_BINARY, $script, '--root='.$repo, '--quiet', '--record'], $root))->run();

    $process = new Process(
        [PHP_BINARY, $script, '--root='.$repo, '--quiet', 'tests/Feature/FooTest.php', '--filter=bar'],
        $root
    );
    $process->run();

    expect($process->getExitCode())->toBe($childHasCoverageDriver ? 0 : 1);
    if ($childHasCoverageDriver) {
        // --quiet means a clean, matching tree prints nothing at all.
        expect(trim($process->getOutput().$process->getErrorOutput()))->toBe('');
    }

    $cleanUp($repo);
});

// UAMS-Web/uams-statamic-archive#1996. Pest decides whether a cached graph is still valid from a structural
// fingerprint over `composer.lock`, `phpunit.xml` and the JS lock files, each gated on
// `Fingerprint::isTrackedByGit()` — a Symfony Finder query with `ignoreVCSIgnored(true)`.
// In some linked-worktree shapes that query returns nothing for plainly tracked files,
// every input resolves to null, `structuralMatches()` compares null to null and reports
// a match, and a dependency change invalidates NOTHING. Measured: editing composer.lock
// moved the hash in a sibling slot (invalidated correctly) and left it NULL under
// `.claude/worktrees/` (stale graph reused).
//
// These drive the guard against REAL worktrees rather than a synthetic fixture, because
// a synthetic one does not reproduce it: a throwaway repo with an ignored nested
// worktree fingerprinted correctly. Bisecting placements inside this repo isolated the
// discriminator to the path being git-ignored by the enclosing repository — every
// non-ignored placement resolved, including a dot-directory and a three-deep one — but
// the fixture result means that is not the whole condition, so the guard MEASURES rather
// than infers, and so do these.
// UAMS-Web/uams-statamic-archive#1996 — the DECISION, driven directly. `scripts/tia-blindness.php` holds it as a pure
// function the CLI delegates to, so the comparison that must not regress is testable
// without standing up the worktree shape for every case.
require_once dirname(__DIR__, 2).'/scripts/tia-blindness.php';

// The exposed shape. Under `.claude/worktrees/` every structural input came back null
// while all three files were present and git-tracked; what matters below is null vs
// non-null, so the non-null values are illustrative rather than captured hashes. `structuralMatches()` then compares null to null, reports a match,
// and a composer.lock change invalidates nothing.
it('reports every tracked input Tia claims is absent', function (): void {
    $blind = tiaBlindFingerprintInputs(TIA_FINGERPRINT_CANDIDATES, [
        'schema' => 18,
        'composer_lock' => null,
        'phpunit_xml' => null,
        'package_lock' => null,
    ]);

    expect($blind)->toBe(['composer.lock', 'phpunit.xml', 'package-lock.json']);
});

// The safe shape, verbatim from a sibling slot — the guard must stay silent here or it
// reds the layout the project standardized on in UAMS-Web/uams-statamic-archive#1945 (the UAMS-Web/uams-statamic-archive#1952 failure).
it('stays silent when every input resolves', function (): void {
    expect(tiaBlindFingerprintInputs(TIA_FINGERPRINT_CANDIDATES, [
        'schema' => 18,
        'composer_lock' => 'd8e56005cfca64a4',
        'phpunit_xml' => '9fa29654949c9007',
        'package_lock' => '101cf9c7ba5d3b2e',
    ]))->toBe([]);
});

// A partial failure is still a failure: the fault nulls every input at once, but a guard
// that only fired on ALL of them would miss any narrower regression upstream introduced.
it('reports a single blind input, not only a wholesale failure', function (): void {
    expect(tiaBlindFingerprintInputs(TIA_FINGERPRINT_CANDIDATES, [
        'composer_lock' => 'd8e56005cfca64a4',
        'phpunit_xml' => null,
        'package_lock' => '101cf9c7ba5d3b2e',
    ]))->toBe(['phpunit.xml']);
});

// The precondition that keeps this from firing everywhere. A null for a file that is
// genuinely absent is CORRECT — most checkouts have no `phpunit.xml.dist` — so the
// caller pre-filters to files that exist and are tracked, and anything outside that set
// must be ignored no matter what the fingerprint says.
it('ignores inputs the caller could not prove are present and tracked', function (): void {
    expect(tiaBlindFingerprintInputs(['composer_lock' => 'composer.lock'], [
        'composer_lock' => 'd8e56005cfca64a4',
        'phpunit_xml' => null,
        'package_lock' => null,
    ]))->toBe([]);
});

// Closes the seam between the decision and the CLI. The tests above prove
// `tiaBlindFingerprintInputs()`; this proves `check-tia-safe.php` actually CONSULTS it —
// a mutation making the guard ignore the decision left every other test green.
//
// It reaches the contract ("present and tracked, yet reported absent") through an
// unreadable file, which is a DIFFERENT Pest code path from the one the guard exists for:
// `isTrackedByGit()` still returns true here, and the null comes from `@hash_file()` in
// `hashIfExists()`. That makes it a good second case rather than a substitute — the real
// `isTrackedByGit()` failure is covered by the worktree-shape test below.
it('refuses when the CLI is given a tree whose tracked inputs fingerprint as absent', function () use ($makeRepo, $runGuard, $cleanUp, $git): void {
    [$repo] = $makeRepo();

    file_put_contents($repo.'/composer.lock', '{"content-hash":"seed"}'."\n");
    $git($repo, ['add', 'composer.lock']);
    $git($repo, ['commit', '--quiet', '-m', 'add composer.lock']);

    // Tracked and present, but `hash_file()` cannot read it -- so Pest reports the input
    // absent, exactly as it does in an exposed worktree.
    chmod($repo.'/composer.lock', 0o000);

    if (is_readable($repo.'/composer.lock')) {
        // Running as root, or a filesystem that ignores the mode. The premise does not
        // hold, so report a genuine skip rather than a pass that asserted nothing.
        chmod($repo.'/composer.lock', 0o644);
        $cleanUp($repo);

        $this->markTestSkipped('chmod 000 left the file readable; cannot stage an unreadable tracked input here.');
    }

    try {
        [$exitCode, $output] = $runGuard($repo);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('reports tracked files as absent')
            ->and($output)->toContain('composer.lock');
    } finally {
        chmod($repo.'/composer.lock', 0o644);
        $cleanUp($repo);
    }
});

// The REAL fault, end to end, through `isTrackedByGit()` itself -- and its retirement.
//
// Until `symfony/finder` `v8.1.8` this tree was blind: a linked worktree at a path its
// enclosing repository ignores fingerprinted every structural input as NULL, and the guard
// refused. Two Finder fixes retired it. `e43d10973` (first in `v8.1.7`) resolves the base
// directory's realpath, which a `/var/folders/...` versus `/private/var/folders/...`
// mismatch used to disengage; `c8d1b2a09` (first in `v8.1.8`) treats a `.git` FILE, which
// is what a linked worktree has, as the repository root. Measured on 2026-09-29 with this
// exact fixture: `v8.1.1` read all three inputs NULL, `v8.1.8` hashes all three (UAMS-Web/uams-statamic-archive#2414).
// `pestphp/pest#1813` is still open; the fix did not come through Pest.
//
// So this now pins the fix: on the installed Finder the worktree is NOT reported blind,
// and the guard goes on to refuse for a later reason (no recorded state, or no coverage
// driver). A Finder regression turns it red. The guard's ability to detect a blind input
// is still exercised above, by the tracked-but-unreadable `composer.lock`.
//
// The fixture is still built at `realpath()`, because the pre-`v8.1.7` trigger needed a
// resolved root and this test should keep failing if that trigger ever returns.
it('no longer finds a linked worktree at an ignored path blind, since symfony/finder v8.1.8', function () use ($cleanUp, $runGuard, $git): void {
    $base = realpath(sys_get_temp_dir());
    expect($base)->toBeString();

    $repo = $base.'/tia-blind-'.bin2hex(random_bytes(6));
    mkdir($repo, 0o777, true);

    $git($repo, ['init', '--quiet', '--initial-branch=main']);
    file_put_contents($repo.'/composer.lock', '{"content-hash":"seed"}'."\n");
    file_put_contents($repo.'/phpunit.xml', '<phpunit/>'."\n");
    file_put_contents($repo.'/package-lock.json', '{}'."\n");
    file_put_contents($repo.'/.gitignore', ".claude/worktrees/\n");
    $git($repo, ['add', '-A']);
    $git($repo, ['commit', '--quiet', '-m', 'seed']);

    mkdir($repo.'/.claude/worktrees', 0o777, true);
    $git($repo, ['worktree', 'add', '--detach', $repo.'/.claude/worktrees/probe', 'HEAD']);
    // A control at a NON-ignored path, which was never blind.
    $git($repo, ['worktree', 'add', '--detach', $repo.'/tmp-probe', 'HEAD']);

    try {
        [$ignoredExit, $ignoredOutput] = $runGuard($repo.'/.claude/worktrees/probe');
        [$controlExit, $controlOutput] = $runGuard($repo.'/tmp-probe');

        // Blindness is the first refusal the guard checks, so reaching any other refusal
        // is positive evidence that the fingerprint read, not merely an absent message.
        expect($ignoredExit)->toBe(1)
            ->and($ignoredOutput)->toContain('Refusing to run --tia:')
            ->and($ignoredOutput)->not->toContain('reports tracked files as absent')
            ->and($controlExit)->toBe(1)
            ->and($controlOutput)->toContain('Refusing to run --tia:')
            ->and($controlOutput)->not->toContain('reports tracked files as absent');
    } finally {
        $git($repo, ['worktree', 'remove', '--force', $repo.'/.claude/worktrees/probe']);
        $git($repo, ['worktree', 'remove', '--force', $repo.'/tmp-probe']);
        $cleanUp($repo);
    }
});

// The over-refusal guard, and a correction to what `isTrackedByGit()` actually asks.
// Despite the name it tests "not VCS-IGNORED", not "tracked": an untracked file that no
// ignore rule covers is still found by the Finder and fingerprints normally. The case
// that legitimately yields null is an IGNORED file — and treating that as blindness would
// refuse in any checkout that git-ignores one of these paths. The
// `git ls-files --error-unmatch` pre-filter exists for exactly that, and without this
// test it can be deleted with the suite still green.
it('ignores an input that is present but git-ignored, rather than refusing over it', function () use ($cleanUp, $runGuard, $git): void {
    $base = realpath(sys_get_temp_dir());
    expect($base)->toBeString();

    $repo = $base.'/tia-ignored-'.bin2hex(random_bytes(6));
    mkdir($repo, 0o777, true);

    $git($repo, ['init', '--quiet', '--initial-branch=main']);
    file_put_contents($repo.'/.gitignore', "composer.lock\n");
    $git($repo, ['add', '.gitignore']);
    $git($repo, ['commit', '--quiet', '-m', 'seed']);

    // Present and deliberately ignored. Its fingerprint is null and that is CORRECT.
    file_put_contents($repo.'/composer.lock', '{"content-hash":"seed"}'."\n");

    try {
        [, $output] = $runGuard($repo);

        expect($output)->not->toContain('reports tracked files as absent');
    } finally {
        $cleanUp($repo);
    }
});

// The other half, and the one that keeps the guard usable: this repo's own checkout
// fingerprints correctly, so the guard must stay silent here. A check that refused every
// tree would red the layout the project standardized on in UAMS-Web/uams-statamic-archive#1945 — the UAMS-Web/uams-statamic-archive#1952 failure.
it('stays silent in a tree whose fingerprint resolves', function () use ($runGuard): void {
    [, $output] = $runGuard(dirname(__DIR__, 2));

    // Asserts the absence of THIS refusal specifically; the run still trips unrelated
    // guards depending on recorded state, which is not what this test is about.
    expect($output)->not->toContain('cannot see structural changes');
});
