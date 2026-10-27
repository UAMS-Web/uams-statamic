<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Guard for UAMS-Web/uams-statamic-archive#2465, narrowed by UAMS-Web/uams-statamic-archive#2850 to the routing cases the shared suite does not cover.
 *
 * `gen_release_notes.py` used to route each bullet on a Conventional-Commit prefix, which the
 * title conventions forbid, so tooling changes fell through to "What's new". The shared
 * `writing-release-notes` skill now owns the generator and ships `gen_release_notes.test.py`,
 * which the `encoding` gate job runs. Its `RoutingTest` and `ClosingIssueTypeTest` cover label
 * routing, label-before-verb ordering, the default bucket and the ignored `development` label,
 * so those cases were retired here. The old user-facing-path case was retired for a different
 * reason: the script now routes a template change as maintenance unless it is told
 * `--product-path resources`, which this repository's invocation in the skill passes.
 *
 * Three cases stay, each because a mutation of the generator that should fail it leaves the
 * shared suite green (measured on UAMS-Web/uams-statamic-archive#2850):
 *
 * - the prefix STRIPPING: replacing `strip_cc_prefix()` with a plain `strip()`;
 * - a `chore:` title on a product path: routing on that prefix;
 * - the fix verb read before the test-dominant rule: swapping those two rules.
 *
 * A pure subprocess check: it imports the generator and calls `bucket()` directly, so it boots
 * no framework, resolves no pull request, and makes no network call.
 */
$root = dirname(__DIR__, 2);
$script = $root.'/.claude/skills/writing-release-notes/gen_release_notes.py';

/**
 * When this file's tests began, so the bytecode guard at the end can tell a cache they
 * wrote from one an earlier run or a developer left behind (UAMS-Web/uams-statamic-archive#2527).
 */
$startedAt = time();

$resolvePython = static function (): string {
    foreach (['python3', 'python'] as $candidate) {
        $probe = new Process([$candidate, '-c', 'import sys; print(sys.version_info[0])']);
        $probe->run();

        if ($probe->isSuccessful() && str_starts_with(trim($probe->getOutput()), '3')) {
            return $candidate;
        }
    }

    return '';
};

$python = $resolvePython();

/**
 * Call `bucket()` in a subprocess and return the chosen bucket.
 *
 * The diff counts go by keyword: `bucket()`'s fifth positional parameter is `forced_sec`, so a
 * count passed positionally there routes everything to Security.
 *
 * @param  list<string>  $labels
 * @param  list<string>  $paths
 */
$route = static function (
    string $python,
    string $script,
    string $title,
    array $labels = [],
    array $paths = [],
    int $testLines = 0,
    int $otherLines = 0,
): string {
    $payload = json_encode([
        'title' => $title,
        'labels' => $labels,
        'paths' => $paths,
        'test_lines' => $testLines,
        'other_lines' => $otherLines,
    ], JSON_THROW_ON_ERROR);

    $driver = <<<'PY'
import importlib.util, json, sys
spec = importlib.util.spec_from_file_location("gen", sys.argv[1])
gen = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gen)
a = json.loads(sys.argv[2])
sys.stdout.write(gen.bucket(a["title"], a["title"], a["labels"], a["paths"],
                            test_lines=a["test_lines"], other_lines=a["other_lines"]))
PY;

    // Importing the generator makes Python write a bytecode cache beside it (UAMS-Web/uams-statamic-archive#2527).
    // `-X pycache_prefix=` clears any per-user cache prefix, so the write lands in the
    // tree on every platform, as a python.org build's does. Apple's Command Line Tools
    // Python otherwise redirects it and hides the defect on macOS.
    // `PYTHONDONTWRITEBYTECODE` is what then keeps the tree clean.
    $process = new Process(
        [$python, '-X', 'pycache_prefix=', '-c', $driver, $script, $payload],
        null,
        ['PYTHONDONTWRITEBYTECODE' => '1']
    );
    $process->run();

    expect($process->isSuccessful())->toBeTrue(
        'bucket() driver failed: '.$process->getErrorOutput()
    );

    return trim($process->getOutput());
};

it('strips a legacy Conventional-Commit prefix before reading the title verb', function () use ($python, $script, $route) {
    if ($python === '') {
        $this->markTestSkipped('No Python 3 on PATH.');
    }

    // No label and no tooling path, so the maintenance verb `Bump` is the only signal, and
    // only a stripped title starts with it.
    $prefixed = $route($python, $script, 'chore: Bump the importer pin', [], ['app/Importer.php'], 0, 4);
    $plain = $route($python, $script, 'Bump the importer pin', [], ['app/Importer.php'], 0, 4);

    expect($prefixed)->toBe($plain)->toBe('maint');
});

it('keeps a prefixed title on a product path out of maintenance', function () use ($python, $script, $route) {
    if ($python === '') {
        $this->markTestSkipped('No Python 3 on PATH.');
    }

    // The shared suite's only `chore:` case sits on a tooling path, where the answer is
    // `maint` either way, so a rule routing on that prefix passes it. Measured on UAMS-Web/uams-statamic-archive#2850:
    // adding `if subject.lower().startswith("chore"): return "maint"` leaves all 76 shared
    // tests green and turns this case red.
    $prefixed = $route($python, $script, 'chore: add a gallery block', [], ['app/Gallery.php'], 0, 40);
    $plain = $route($python, $script, 'Add a gallery block', [], ['app/Gallery.php'], 0, 40);

    expect($prefixed)->toBe($plain)->toBe('new');
});

it('reads a fix verb before the test-dominant rule', function () use ($python, $script, $route) {
    if ($python === '') {
        $this->markTestSkipped('No Python 3 on PATH.');
    }

    // UAMS-Web/uams-statamic-archive#2399's shape: a repair that is mostly tests is still a fix. The shared suite pins
    // label-before-verb and verb-before-path, but not verb-before-test-dominant. Measured
    // on UAMS-Web/uams-statamic-archive#2850: moving the test-dominant rule above the fix verb leaves all 76 shared tests
    // green and turns this case red. The production path keeps the path rule out of it.
    expect($route($python, $script, 'Stop a missing Vite manifest masking route exceptions', [], ['app/X.php', 'tests/Feature/X.php'], 168, 2))
        ->toBe('fix');
});

it('leaves no bytecode cache beside the generator', function () use ($python, $script, $startedAt) {
    if ($python === '') {
        $this->markTestSkipped('No Python 3 on PATH; the generator cannot be exercised.');
    }

    // Runs last in this file, after the test above has imported the generator with the
    // cache prefix cleared. Anything it wrote would be newer than $startedAt.
    $written = array_filter(
        glob(dirname($script).'/__pycache__/*.pyc') ?: [],
        fn (string $file): bool => filemtime($file) >= $startedAt
    );

    expect($written)->toBe([]);
});
