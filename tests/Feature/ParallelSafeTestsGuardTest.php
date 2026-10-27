<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for the test-isolation lint (UAMS-Web/uams-statamic-archive#1149). Runs
 * `scripts/check-parallel-safe-tests.php` over throwaway fixture trees and asserts
 * it flags un-quarantined parallel-unsafe patterns and passes quarantined, safe,
 * or absent ones — proving the guard is live, not vacuous.
 *
 * Coverage is deliberately broad because the guard's earlier line-regex form had
 * gaps an adversarial review found (multi-line calls, tokens/patterns in comments,
 * variadic and per-test `serial` markers, each uniqueness token); each case below
 * pins one of those so a regression turns this suite red.
 *
 * The flagged tokens (`Storage::persistentFake`, `storage_path`, `framework/testing`) are
 * assembled from split literals so THIS file cannot itself be a subject of the
 * guard. A pure subprocess check; framework-free (no `uses(TestCase)`), and its
 * fixtures live under a per-run `sys_get_temp_dir()` path, so it is parallel-safe
 * by its own rules.
 */
$root = dirname(__DIR__, 2);
$script = $root.'/scripts/check-parallel-safe-tests.php';

// Split so the guard's own scan of tests/ can never match this file.
$fake = 'Storage::'.'persistentFake'; // "Storage::persistentFake", the Rule A form
$plainFake = 'Storage::'.'fake';  // "Storage::fake", per worker and not flagged (#3111)
$sp = 'storage'.'_path';          // "storage_path"
$ft = 'framework/'.'testing';     // "framework/testing"

/**
 * Write a one-file fixture tree and return [dir, file].
 *
 * @return array{0: string, 1: string}
 */
$writeFixture = function (string $body): array {
    $dir = sys_get_temp_dir().'/pguard-'.bin2hex(random_bytes(6));
    mkdir($dir);
    $file = $dir.'/SomeTest.php';
    file_put_contents($file, "<?php\n\n".$body);

    return [$dir, $file];
};

/**
 * Write a multi-file fixture tree (filename => body) and return the dir. Used to
 * show the guard no longer reports the cross-file symbol shapes it handed off.
 *
 * @param  array<string, string>  $files
 */
$writeFixtures = function (array $files): string {
    $dir = sys_get_temp_dir().'/pguard-multi-'.bin2hex(random_bytes(6));
    mkdir($dir);
    foreach ($files as $name => $body) {
        /** @var string $body */
        file_put_contents($dir.'/'.$name, "<?php\n\n".$body);
    }

    return $dir;
};

$runGuard = function (string $dir) use ($root, $script): Process {
    $process = new Process([PHP_BINARY, $script, $dir], $root);
    $process->run();

    return $process;
};

$cleanup = function (string $dir): void {
    foreach (glob($dir.'/*') ?: [] as $item) {
        @unlink($item);
    }
    @rmdir($dir);
};

// --- Rule A: Storage::persistentFake ------------------------------------------

it('flags a Storage::persistentFake test that is not quarantined', function () use ($fake, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('leaks', function () {\n    {$fake}('public');\n});\n");

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('Rule A');
        expect($process->getErrorOutput())->toContain('SomeTest.php');
    } finally {
        $cleanup($dir);
    }
});

it('flags a Storage::persistentFake written in another valid form', function (string $call) use ($writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('leaks', function () {\n    {$call}('public');\n});\n");

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('Rule A');
    } finally {
        $cleanup($dir);
    }
})->with([
    'fully qualified' => ['\\Illuminate\\Support\\Facades\\'.'Storage::'.'persistentFake'],
    'qualified' => ['Facades\\'.'Storage::'.'persistentFake'],
    'method in another case' => ['Storage::'.'PersistentFake'],
    'facade in another case' => ['storage::'.'persistentFake'],
]);

it('passes a static persistentFake on a class that is not the Storage facade', function () use ($writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('other', function () {\n    \\App\\Support\\StorageLike::"."persistentFake('public');\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

it('passes a Storage::fake that is not quarantined, whose disk root is per worker', function () use ($plainFake, $writeFixture, $runGuard, $cleanup) {
    // Storage::fake() appends _test_{TEST_TOKEN} to the root under --parallel, so
    // it no longer trips Rule A (UAMS-Web/uams-statamic-archive#3111). The old guard exited 1 on this fixture.
    [$dir] = $writeFixture("it('per worker', function () {
    {$plainFake}('public');
});
");

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(0);
        expect($process->getErrorOutput())->not->toContain('Rule A');
    } finally {
        $cleanup($dir);
    }
});

it('ignores a Storage::persistentFake that appears only in a comment', function () use ($fake, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("// We deliberately avoid {$fake}('public') here under --parallel.\nit('ok', function () {\n    expect(true)->toBeTrue();\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

it('accepts a Storage::persistentFake quarantined whole-file by a serial group marker', function (string $marker) use ($writeFixture, $runGuard, $cleanup) {
    // Each $marker embeds a real Storage::persistentFake, so this also mutation-verifies the
    // matching detector: break it and the fake is no longer exempted → red.
    [$dir] = $writeFixture($marker);

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
})->with([
    'Pest uses()->group(serial)' => ["uses()->group('serial');\n\nit('q', function () {\n    {$fake}('public');\n});\n"],
    'Pest variadic uses()->group(serial, slow)' => ["uses()->group('serial', 'slow');\n\nit('q', function () {\n    {$fake}('public');\n});\n"],
    'PHPUnit class #[Group(serial)]' => ["#[Group('serial')]\nclass AttrTest\n{\n    public function testQ(): void\n    {\n        {$fake}('public');\n    }\n}\n"],
    'PHPUnit class @group serial docblock' => ["/**\n * @group serial\n */\nclass DocTest\n{\n    public function testQ(): void\n    {\n        {$fake}('public');\n    }\n}\n"],
]);

it('flags a non-serial Storage::persistentFake sibling of a per-it() serial marker', function () use ($fake, $writeFixture, $runGuard, $cleanup) {
    // The per-it() ->group('serial') quarantines only 'flaky'; 'leaks' still runs
    // in the parallel pass, so its Storage::persistentFake must be flagged.
    [$dir] = $writeFixture("uses(TestCase::class);\n\nit('flaky', function () {})->group('serial');\n\nit('leaks', function () {\n    {$fake}('public');\n});\n");

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('Rule A');
    } finally {
        $cleanup($dir);
    }
});

it('flags a Storage::persistentFake in a file grouped @group serial-slow (a different group)', function () use ($fake, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("/** @group serial-slow */\nclass SerialSlowTest\n{\n    public function testLeaks(): void\n    {\n        {$fake}('public');\n    }\n}\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(1);
    } finally {
        $cleanup($dir);
    }
});

// --- Rule B: hardcoded framework/testing path --------------------------------

it('flags a hardcoded framework/testing path with no uniqueness token', function () use ($sp, $ft, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('leaks', function () {\n    \$d = {$sp}('{$ft}/fixed-name');\n});\n");

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('Rule B');
    } finally {
        $cleanup($dir);
    }
});

it('accepts a framework/testing path made unique by a token', function (string $tokenExpr) use ($sp, $ft, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('safe', function () {\n    \$d = {$sp}('{$ft}/dir-'.{$tokenExpr});\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
})->with([
    // One isolating expression per token, so dropping any single token from the
    // guard's uniqueness set turns exactly one case red (mutation coverage).
    'uniqid' => ["uniqid('', true)"],
    "env('TEST_TOKEN')" => ["env('TEST_TOKEN')"],
    'random_bytes' => ['random_bytes(6)'],
    'bin2hex' => ["bin2hex('seed')"],
    'Str::random' => ['Str::random(8)'],
    // The suite's own helper (UAMS-Web/uams-statamic-archive#1957): per-worker via TEST_TOKEN and per-process
    // otherwise, so the guard has to accept it or the four support bases that call
    // it would all need allowlisting.
    'ThrowawayRoot::segment' => ['ThrowawayRoot::segment()'],
]);

it('flags a framework/testing path wrapped across lines with no token', function () use ($sp, $ft, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('leaks', function () {\n    \$d = {$sp}(\n        '{$ft}/fixed-name'\n    );\n});\n");

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('Rule B');
    } finally {
        $cleanup($dir);
    }
});

it('accepts a wrapped framework/testing path whose token is on the next line', function () use ($sp, $ft, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('safe', function () {\n    \$d = {$sp}('{$ft}/dir-'\n        .uniqid('', true));\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

it('ignores a framework/testing storage_path that appears only in a comment', function () use ($sp, $ft, $writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("// Legacy fixtures lived at {$sp}('{$ft}/legacy'); now per-worker below.\nit('ok', function () {\n    expect(true)->toBeTrue();\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

// --- Rules C and D moved to the shared script (UAMS-Web/uams-statamic-archive#3112) ---------------------------

it('no longer reports a global symbol in two files or a no-op import', function () use ($writeFixtures, $runGuard, $cleanup) {
    // Both shapes now belong to the shared check-global-test-symbols.php, which the
    // same CI job runs after this guard (GlobalTestSymbolsCheckTest covers it). The
    // guard must stay silent on them, so a defect is reported once, not twice.
    $dir = $writeFixtures([
        'ATest.php' => "use RuntimeException;\n\nconst PGUARD_SHARED = 1;\n\nfunction pguardHelper(): int\n{\n    return 1;\n}\n",
        'BTest.php' => "const PGUARD_SHARED = 2;\n\nfunction pguardHelper(): int\n{\n    return 2;\n}\n",
    ]);

    try {
        $process = $runGuard($dir);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('OK');
    } finally {
        $cleanup($dir);
    }
});

// ---------------------------------------------------------------------------
// Rule E — assert() type-narrowing (UAMS-Web/uams-statamic-archive#1865)
// ---------------------------------------------------------------------------

// Unlike the Rule A/B tokens above, these need no splitting: Rule E matches on
// PHP tokens, and a string literal is a single T_CONSTANT_ENCAPSED_STRING that
// can never be an `assert` call — which the "prose or a string literal" case
// below pins directly, so this file cannot become a subject of its own guard.
$as = 'assert';
$qualified = '\\assert';

it('flags a fully-qualified assert() narrowing', function () use ($writeFixture, $runGuard, $cleanup, $qualified) {
    // The form UAMS-Web/uams-statamic-archive#1733's `^\s*assert(` search never matched — the reason 157 of
    // them survived a sweep that reported the suite clean.
    [$dir] = $writeFixture("it('probe', function () {\n    ".$qualified."(\$GLOBALS['x'] instanceof stdClass);\n});\n");

    try {
        $process = $runGuard($dir);
        expect($process->getExitCode())->toBe(1);
        expect($process->getOutput().$process->getErrorOutput())->toContain('Rule E');
    } finally {
        $cleanup($dir);
    }
});

it('flags an unqualified assert() narrowing', function () use ($writeFixture, $runGuard, $cleanup, $as) {
    [$dir] = $writeFixture("it('probe', function () {\n    ".$as."(\$GLOBALS['x'] instanceof stdClass);\n});\n");

    try {
        $process = $runGuard($dir);
        expect($process->getExitCode())->toBe(1);
        expect($process->getOutput().$process->getErrorOutput())->toContain('Rule E');
    } finally {
        $cleanup($dir);
    }
});

it('flags an assert() narrowing even in a serial-quarantined file', function () use ($writeFixture, $runGuard, $cleanup, $qualified) {
    // Rule E is a typing defect, not a parallelism one, so the `serial` group
    // that exempts Rules A/B must not exempt it. This is the case that would
    // silently reopen the gap the sweep closed.
    [$dir] = $writeFixture("uses()->group('serial');\n\nit('probe', function () {\n    ".$qualified."(\$GLOBALS['x'] instanceof stdClass);\n});\n");

    try {
        $process = $runGuard($dir);
        expect($process->getExitCode())->toBe(1);
        expect($process->getOutput().$process->getErrorOutput())->toContain('Rule E');
    } finally {
        $cleanup($dir);
    }
});

it('flags an uppercase ASSERT() narrowing', function () use ($writeFixture, $runGuard, $cleanup, $as) {
    // PHP function names are case-insensitive, so this is the same construct and
    // narrows types identically. A case-sensitive rule would leave exactly the
    // kind of hole that let 157 of these survive UAMS-Web/uams-statamic-archive#1733's sweep.
    [$dir] = $writeFixture("it('probe', function () {\n    ".strtoupper($as)."(\$GLOBALS['x'] instanceof stdClass);\n});\n");

    try {
        $process = $runGuard($dir);
        expect($process->getExitCode())->toBe(1);
        expect($process->getOutput().$process->getErrorOutput())->toContain('Rule E');
    } finally {
        $cleanup($dir);
    }
});

it('does not flag a class instantiation named assert', function () use ($writeFixture, $runGuard, $cleanup, $as) {
    // `new assert(...)` is a constructor call, not the language construct.
    [$dir] = $writeFixture("class {$as} {}\n\nit('probe', function () {\n    \$o = new {$as}();\n    expect(\$o)->toBeObject();\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

it('does not flag a method or static call named assert', function () use ($writeFixture, $runGuard, $cleanup, $as) {
    // `$this->assert(` and `Foo::assert(` are ordinary calls, not the language
    // construct — the false positive that would make the rule unusable.
    [$dir] = $writeFixture("it('probe', function () {\n    \$this->".$as."(1);\n    Foo::".$as."(2);\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

it('does not flag assert named only in prose or a string literal', function () use ($writeFixture, $runGuard, $cleanup, $as, $qualified) {
    // Every docblock in the suite that explains why assert() was the wrong
    // instrument — this file, TypeNarrowing.php, PendingArtisan.php — stays legal.
    [$dir] = $writeFixture("/**\n * Explains why ".$qualified."() was the wrong instrument.\n */\nit('probe', function () {\n    \$s = '".$as."(\$x)';\n    expect(\$s)->toBeString();\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

// --- ALLOWLIST rows are scoped to the rules they name (UAMS-Web/uams-statamic-archive#2939) -----------------

/**
 * Copy the guard into a throwaway repo-shaped tree, so a fixture at an allowlisted
 * repo-relative path is matched by the script's own ALLOWLIST. `$edit` rewrites the
 * copy's source before it is written, for the cases that need a different row.
 *
 * @param  array<string, string>  $files  repo-relative path => file body
 */
$writeRepoFixture = function (array $files, ?Closure $edit = null) use ($script): string {
    // Edit before creating anything, so an edit that fails its own check leaves no tree.
    $source = (string) file_get_contents($script);
    $source = $edit instanceof Closure ? $edit($source) : $source;
    $repo = sys_get_temp_dir().'/pguard-repo-'.bin2hex(random_bytes(6));
    mkdir($repo.'/scripts', 0777, true);
    file_put_contents($repo.'/scripts/check-parallel-safe-tests.php', is_string($source) ? $source : '');

    foreach ($files as $relative => $body) {
        $path = $repo.'/'.$relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "<?php\n\n".(is_string($body) ? $body : ''));
    }

    return $repo;
};

$runRepoGuard = function (string $repo): Process {
    $process = new Process([PHP_BINARY, $repo.'/scripts/check-parallel-safe-tests.php', 'tests'], $repo);
    $process->run();

    return $process;
};

$cleanupRepo = function (string $repo): void {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item instanceof SplFileInfo) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }
    @rmdir($repo);
};

$allowlisted = 'tests/Unit/Support/ContentRootTest.php';

it('suppresses the rule an ALLOWLIST row names', function () use ($sp, $ft, $allowlisted, $writeRepoFixture, $runRepoGuard, $cleanupRepo) {
    $repo = $writeRepoFixture([$allowlisted => "it('path', function () {\n    expect({$sp}('{$ft}/content-mirror'))->toBeString();\n});\n"]);

    try {
        expect($runRepoGuard($repo)->getExitCode())->toBe(0);
    } finally {
        $cleanupRepo($repo);
    }
});

it('still reports a rule an ALLOWLIST row does not name', function () use ($fake, $sp, $ft, $allowlisted, $writeRepoFixture, $runRepoGuard, $cleanupRepo) {
    $repo = $writeRepoFixture([$allowlisted => "it('path', function () {\n    {$fake}('local');\n    expect({$sp}('{$ft}/content-mirror'))->toBeString();\n});\n"]);

    try {
        $process = $runRepoGuard($repo);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain($allowlisted.':4', 'Rule A');
        expect($process->getErrorOutput())->not->toContain('Rule B');
    } finally {
        $cleanupRepo($repo);
    }
});

it('reports an assert() narrowing in an allowlisted file', function () use ($allowlisted, $writeRepoFixture, $runRepoGuard, $cleanupRepo, $qualified) {
    $repo = $writeRepoFixture([$allowlisted => "it('probe', function () {\n    ".$qualified."(\$GLOBALS['x'] instanceof stdClass);\n});\n"]);

    try {
        $process = $runRepoGuard($repo);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('Rule E');
    } finally {
        $cleanupRepo($repo);
    }
});

it('refuses an ALLOWLIST row that names no parallelism rule', function (string $rules) use ($allowlisted, $writeRepoFixture, $runRepoGuard, $cleanupRepo) {
    $repo = $writeRepoFixture(
        [$allowlisted => "it('fine', fn () => expect(true)->toBeTrue());\n"],
        function (string $source) use ($rules): string {
            $edited = str_replace("'rules' => ['B'],", "'rules' => {$rules},", $source, $count);

            expect($count)->toBe(1);

            return $edited;
        },
    );

    try {
        $process = $runRepoGuard($repo);

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('ALLOWLIST row '.$allowlisted);
    } finally {
        $cleanupRepo($repo);
    }
})->with([
    'Rule C, now in the shared script' => ["['C']"],
    'Rule D, now in the shared script' => ["['D']"],
    'Rule E' => ["['E']"],
    'an unknown rule' => ["['X']"],
    'no rule' => ['[]'],
]);

it('passes clean when no fragile pattern is present', function () use ($writeFixture, $runGuard, $cleanup) {
    [$dir] = $writeFixture("it('fine', function () {\n    expect(true)->toBeTrue();\n});\n");

    try {
        expect($runGuard($dir)->getExitCode())->toBe(0);
    } finally {
        $cleanup($dir);
    }
});
