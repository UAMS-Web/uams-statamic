<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * Drift guard for UAMS-Web/uams-statamic-archive#2477. Both gates check out the `users` submodule alongside
 * `content`, so `tests/Feature/Users/RegistrantRecordsAreGitIgnoredTest.php` runs
 * in the gate rather than skipping -- and a reverted allowlist reds the chain.
 * Since UAMS-Web/uams-statamic-archive#2818 they also check out `resources/forms`, so the tests that read the
 * real form configurations run in the gate rather than skipping. And since UAMS-Web/uams-statamic-archive#2497 a
 * manual-run-only job repeats the pest job's checkout step to prove the token, which
 * only means something while the two steps are the same.
 *
 * The decision that put `users` there is recorded in `docs/DECISIONS.md`; this
 * file pins the invocations that carry it out (the two gates, and the manual job's
 * copy of the pest step), because either can be edited
 * back to `content` alone without anything else objecting: the only symptom
 * would be three skips reappearing in the parallel band, which
 * `tests/skip-baseline.json` catches -- but only on a run of the whole suite,
 * hours after the edit. This runs in seconds and names the file.
 *
 * A pure file-read check; like the sibling `ImagickPlatformRequirementTest` it
 * deliberately avoids booting the framework.
 *
 * @command  php artisan test --compact tests/Feature/GatesInitializeUsersSubmoduleTest.php
 */
$root = dirname(__DIR__, 2);

/**
 * Read a repository file, failing with the path when it cannot be read.
 *
 * @param  string  $path  Absolute path to read.
 * @return string The file contents.
 */
$readFile = function (string $path): string {
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read %s.', $path));
    }

    return $contents;
};

/**
 * The `git submodule update` options that consume the token after them, so a
 * value never reads as a path. `--depth` is the one the gates use; the rest are
 * the documented value-taking options, listed so a future `--jobs 4` does not
 * turn `4` into a submodule.
 *
 * @var list<string>
 */
$valueTakingOptions = ['--depth', '--jobs', '-j', '--reference', '--filter'];

/**
 * The submodule names a `git submodule update --init …` command line checks out.
 *
 * Only the rest of the SAME line counts. Backslash continuations are joined
 * first, since the workflow splits its command across two lines — but a bare
 * newline ends the invocation, so a following line of the same step can never be
 * read as its path list. Everything after `--init` that does not start with `-`
 * is a path, and a value-taking option consumes the token after it.
 *
 * @param  string  $commandLine  One `git submodule update --init …` invocation.
 * @return list<string> The submodule paths, in the order given.
 */
$submodulesInitializedBy = function (string $commandLine) use ($valueTakingOptions): array {
    $joined = preg_replace('/\\\\\r?\n[ \t]*/', ' ', $commandLine) ?? $commandLine;

    if (! preg_match('/submodule[ \t]+update[ \t]+--init([^\r\n]*)/', $joined, $match)) {
        return [];
    }

    $tokens = preg_split('/[ \t]+/', trim($match[1])) ?: [];
    $paths = [];

    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (in_array($tokens[$i], $valueTakingOptions, true)) {
            $i++;

            continue;
        }

        if (str_starts_with($tokens[$i], '-') || $tokens[$i] === '') {
            continue;
        }

        $paths[] = $tokens[$i];
    }

    return $paths;
};

it('checks out content, users and resources/forms in the pest job of ci.yml', function () use ($root, $submodulesInitializedBy): void {
    $workflow = Yaml::parseFile($root.'/.github/workflows/ci.yml');

    $jobs = is_array($workflow) && is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [];
    $pest = is_array($jobs['pest'] ?? null) ? $jobs['pest'] : [];
    $steps = is_array($pest['steps'] ?? null) ? $pest['steps'] : [];

    expect($steps)->not->toBeEmpty();

    /** @var list<list<string>> $checkouts */
    $checkouts = [];

    foreach ($steps as $step) {
        $run = is_array($step) && is_string($step['run'] ?? null) ? $step['run'] : '';

        if (str_contains($run, 'submodule update --init')) {
            $checkouts[] = $submodulesInitializedBy($run);
        }
    }

    // Exactly one step does the checkout; a second one would be a place for the
    // two files to disagree with each other.
    expect($checkouts)->toHaveCount(1)
        ->and($checkouts[0])->toContain('content')
        ->and($checkouts[0])->toContain('users')
        ->and($checkouts[0])->toContain('resources/forms');
});

it('runs the pest job checkout step, unchanged, in a manual-run-only job of ci.yml', function () use ($root, $submodulesInitializedBy): void {
    // UAMS-Web/uams-statamic-archive#2497. `submodule-access` proves the token can clone every gate submodule without
    // switching remote CI back on. It proves the pest job's step only while it runs that
    // exact step, so the two are compared whole: name, environment and command.
    $workflow = Yaml::parseFile($root.'/.github/workflows/ci.yml');

    $on = is_array($workflow) && is_array($workflow['on'] ?? null) ? $workflow['on'] : [];
    $jobs = is_array($workflow) && is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [];
    $manual = is_array($jobs['submodule-access'] ?? null) ? $jobs['submodule-access'] : [];
    $pest = is_array($jobs['pest'] ?? null) ? $jobs['pest'] : [];

    expect($on)->toHaveKey('workflow_dispatch')
        ->and($manual)->not->toBeEmpty()
        ->and($manual['if'] ?? null)->toBe("github.event_name == 'workflow_dispatch'")
        ->and($manual)->not->toHaveKey('needs');

    /**
     * The one step in a job that runs `git submodule update --init`.
     *
     * @param  array<mixed>  $job
     * @return array<mixed>
     */
    $checkoutStep = function (array $job): array {
        $steps = is_array($job['steps'] ?? null) ? $job['steps'] : [];
        $found = array_values(array_filter(
            $steps,
            fn (mixed $step): bool => is_array($step) && is_string($step['run'] ?? null) && str_contains($step['run'], 'submodule update --init')
        ));

        expect($found)->toHaveCount(1);

        return is_array($found[0] ?? null) ? $found[0] : [];
    };

    $manualStep = $checkoutStep($manual);
    $run = is_string($manualStep['run'] ?? null) ? $manualStep['run'] : '';

    expect($manualStep)->toBe($checkoutStep($pest))
        ->and($submodulesInitializedBy($run))->toBe(['content', 'users', 'resources/forms'])
        ->and(json_encode($manual, JSON_THROW_ON_ERROR))->not->toContain('RUN_REMOTE_CI');

    // The step alone is not enough: what runs before it (the repository checkout and
    // its persisted credential) and the job's own context decide what the step sees.
    // So everything up to and including the checkout step must match the pest job's,
    // on the same runner, and the manual job may carry nothing that changes the
    // context: no env, defaults, container, or a failure it is allowed to swallow.
    $manualSteps = is_array($manual['steps'] ?? null) ? array_values($manual['steps']) : [];
    $pestSteps = is_array($pest['steps'] ?? null) ? array_values($pest['steps']) : [];
    $through = array_search($manualStep, $manualSteps, true);

    expect($through)->toBeInt()
        ->and(array_slice($manualSteps, 0, (int) $through + 1))->toBe(array_slice($pestSteps, 0, (int) $through + 1))
        ->and($manual['runs-on'] ?? null)->toBe($pest['runs-on'] ?? null)
        ->and($manual)->not->toHaveKeys(['env', 'defaults', 'container', 'continue-on-error']);

    // Outside the chain: no other job may wait on it.
    foreach ($jobs as $id => $job) {
        $needs = is_array($job) ? ($job['needs'] ?? []) : [];

        expect((array) $needs)->not->toContain('submodule-access');
    }
});

it('checks out content, users and resources/forms through GATE_SUBMODULES in scripts/local-ci.mjs', function () use ($root, $readFile): void {
    $script = $readFile($root.'/scripts/local-ci.mjs');
    $module = $readFile($root.'/scripts/gate-slot.mjs');

    // scripts/gate-slot.mjs keeps the one exported list, and the runner imports it
    // and spreads it into the one argv its init step builds, so this pins three
    // things: the list's members, that the runner takes the list from that module,
    // and that the init argv reads the list rather than carrying literals of its own.
    expect(preg_match('/^export const GATE_SUBMODULES = Object\\.freeze\\(\\[([^\\]]*)\\]\\)/m', $module, $definition))->toBe(1)
        ->and(preg_match("/^import \\{[^}]*\\bGATE_SUBMODULES\\b[^}]*\\} from '\\.\\/gate-slot\\.mjs'$/m", $script))->toBe(1);

    preg_match_all("/'([^']*)'/", $definition[1] ?? '', $literal);

    expect($literal[1])->toContain('content')
        ->and($literal[1])->toContain('users')
        ->and($literal[1])->toContain('resources/forms');

    preg_match_all("/'submodule',[ \\t]*'update',[ \\t]*'--init'((?:,[ \\t]*(?:'[^']*'|\\.\\.\\.GATE_SUBMODULES))*)\\]/", $script, $inits, PREG_SET_ORDER);

    expect($inits)->not->toBeEmpty();

    foreach ($inits as $init) {
        expect($init[1])->toContain('...GATE_SUBMODULES');
    }
});

it('clears a stranded, uninitialized gate submodule directory before the init in scripts/local-ci.mjs', function () use ($root, $readFile): void {
    $script = $readFile($root.'/scripts/local-ci.mjs');

    // The UAMS-Web/uams-statamic-archive#1999 trap on the runner's reused worktree: the file user driver writes
    // into an uninitialized `users/`, and `submodule update --init` then refuses the
    // clone. The guard loops over the same list the init spreads, skips anything
    // that carries a `.git`, and removes only a plain, non-empty directory. It must
    // sit BEFORE the init argv is built, or it guards nothing.
    $guard = strpos($script, "for (const name of GATE_SUBMODULES) {\n            const path = join(worktreePath, name)");
    $init = strpos($script, "const submoduleInit = ['submodule', 'update', '--init'");

    if ($guard === false || $init === false) {
        $this->fail('The stranded-directory guard or the init argv is no longer in scripts/local-ci.mjs.');
    }

    expect($guard)->toBeLessThan($init);

    $body = substr($script, $guard, $init - $guard);

    expect($body)->toContain("existsSync(join(path, '.git'))")
        ->and($body)->toContain('readdirSync(path)')
        ->and($body)->toContain('rmSync(path, { recursive: true, force: true })');
});

it('parses the checkout shapes it guards and refuses the ones it must not', function () use ($submodulesInitializedBy): void {
    // The control for the parser above: the workflow's own two-line shape with a
    // continuation, and a bare one-line shape. A parser that matched neither would
    // make the assertions above vacuous.
    $workflowShape = "git -c \"url.https://x-access-token:\${T}@github.com/.insteadOf=https://github.com/\" \\\n    submodule update --init --depth 1 content users\n";

    expect($submodulesInitializedBy($workflowShape))->toBe(['content', 'users'])
        ->and($submodulesInitializedBy('git submodule update --init content'))->toBe(['content'])
        ->and($submodulesInitializedBy('git submodule sync'))->toBe([]);

    // The negative controls. An `--init` that ends its line initializes EVERY
    // submodule (all four, 170 MB of assets included) and names none; a following
    // line that happens to mention both names must not be read as its path list.
    // And a value-taking option's value is not a path.
    expect($submodulesInitializedBy("git submodule update --init\necho content users\n"))->toBe([])
        ->and($submodulesInitializedBy('git submodule update --init --jobs 4 content users'))->toBe(['content', 'users']);
});
